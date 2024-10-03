<?php

namespace Reservations\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Reservations;
use Reservations\Invoices\IInvoiceGenerator;
use Reservations\Mail;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Taxonomies;
use Reservations\Utils;
use Sofa\Eloquence;

class Subscription extends Model
{
    use SoftDeletes;
    use Eloquence\Eloquence, Eloquence\Mappable;

    protected $table      = 'subscriptions';
    protected $primaryKey = 'subscription_id';
    public $timestamps    = false;

    protected $fillable = [
        "hash",

        "date_from",
        "date_to",
        "age_group",
        "is_replacement",
        "paid",
        "paid_amount",
        "application_form_received",
        "notification_email_sent",
        "type",
        "force_inactive",
        "num_months",
        "created_at",
        "invoice_id"
    ];

    protected $maps = [
        "id"                      => "subscription_id",
        "dateFrom"                => "date_from",
        "dateTo"                  => "date_to",
        "ageGroup"                => "age_group",
        "isReplacement"           => "is_replacement",
        "applicationFormReceived" => "application_form_received",
        "notificationEmailSent"   => "notification_email_sent",
        "paidAmount"              => "paid_amount",
        "numMonths"               => "num_months",
        "forceInactive"           => "force_inactive",
        "createdAt"               => "created_at",
        "invoiceId"               => "invoice_id",
    ];

    protected $dates = [
        'date_from',
        'date_to',
        'created_at',
        'deleted_at',
    ];

    /* Relationships */

    public function gym()
    {
        trigger_error("Subscription->Gym relationship is broken.", E_USER_WARNING);

        return $this->belongsTo(Gym::class, "gym_id");
    }

    public function trainingGroup()
    {
        return $this->belongsTo(TrainingGroup::class, "tgroup_id");
    }

    public function event()
    {
        return $this->belongsTo(Event::class, "event_id");
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, "subscriber_id");
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, "subscription_id");
    }

    public function transactions()
    {
        return $this->hasManyThrough(Transaction::class, Payment::class);
    }

    /* Scopes */

    public function scopeActive($builder, $allowFuture = false)
    {
        $builder->where(function ($builder) use ($allowFuture) {
            return $builder->where("type", "single")->orWhere(function ($builder) use ($allowFuture) {
                if (!$allowFuture) {
                    $builder->where("date_from", "<=", Utils::today()->toDateString());
                }

                $builder->where("date_to", ">=", Utils::today()->toDateString());
            });
        })->where("force_inactive", "!=", true);
    }

    public function scopePaid($builder)
    {
        $builder->where("paid", true);
    }

    public function scopePaidPartially($builder)
    {
        $builder->where("paid", true)->orWhereHas("payments", function ($builder) {
            $builder->where("paid", true);
        });
    }

    public function getPaidAtAttribute()
    {
        if (!$this->paid)
            return null;

        return $this->payments()->where("paid", true)->get()->pluck("paid_at")->max();
    }

    public function scopeAgeGroup($builder, $ageGroup)
    {
        if (is_array($ageGroup)) {
            $ageGroup = $ageGroup["id"];
        }

        $builder->where("age_group", $ageGroup);
    }

    public function scopeForEvent($builder)
    {
        $builder->whereNotNull("event_id");
    }

    public function scopeForEvents($builder)
    {
        $builder->whereNotNull("event_id");
    }

    public function scopeForTraining($builder)
    {
        $builder->whereNotNull("gym_id")->orWhereNotNull("tgroup_id");
    }

    public function scopeForTrainings($builder)
    {
        $builder->whereNotNull("gym_id")->orWhereNotNull("tgroup_id");
    }

    public function scopeAccessible($builder, $user = null)
    {
        if (is_null($user)) {
            $user = User::current();
        }

        $accessibleCities         = $user->getAccessibleCities(true);
        $accessibleTrainingGroups = $user->getAccessibleTrainingGroups(true);

        return $builder->where(function ($builder) use ($accessibleTrainingGroups) {
            return $builder->whereNull("event_id")->whereIn("tgroup_id", $accessibleTrainingGroups);
        })->orWhere(function ($builder) use ($accessibleCities) {
            return $builder->whereNotNull("event_id")->whereHas("event", function ($builder) use ($accessibleCities) {
                return $builder->whereHas("termTaxonomies", function ($builder) use ($accessibleCities) {
                    $builder->where("taxonomy", Taxonomies\City::NAME)->whereIn("term_id", $accessibleCities);
                });
            });
        });
    }

    public function scopeSortByName($builder, $order = "ASC")
    {
        $builder->join("subscribers", "subscriptions.subscriber_id", "=", "subscribers.subscriber_id")
            ->orderBy("subscribers.last_name", $order)->orderBy("subscribers.first_name", $order);
    }

    public function scopeSortByLocation($builder, $order = "ASC")
    {
        // $builder->leftJoin("terms", "subscriptions.gym_id", "=", "terms.term_id")
        //     ->leftJoin("term_relationships", "subscriptions.event_id", "=", "term_relationships.object_id")
        //     ->leftJoin("term_taxonomy", "term_relationships.term_taxonomy_id", "=", "term_taxonomy.term_taxonomy_id")
        //     ->whereNull("term_taxonomy.taxonomy")->orWhere("term_taxonomy.taxonomy", Taxonomies\City::NAME)
        //     ->leftJoin("terms2", "term_taxonomy.term_id", "=", "terms.term_id")

        //     ->orderBy("subscribers.last_name")->orderBy("subscribers.first_name");
    }

    public function scopeSortByDateTo($builder, $order = "ASC")
    {
        $builder->orderBy("date_to", $order);
    }

    public function scopeSortByDateFrom($builder, $order = "ASC")
    {
        $builder->orderBy("date_from", $order);
    }

    public function scopeSearchQuery($builder, $q)
    {
        $builder->whereHas("subscriber", function ($builder) use ($q) {
            $builder->searchQuery($q);
        });

        $matchedEvents  = Event::searchQuery($q, true)->get();
        $matchedTgroups = TrainingGroup::searchQuery($q, true)->get();

        $builder->orWhereIn("tgroup_id", $matchedTgroups->pluck("id"))
            ->orWhereIn("event_id", $matchedEvents->pluck("id"));
    }

    public function scopeHash($builder, $hash)
    {
        $builder->where("hash", $hash);
    }

    /* Attributes */

    public function getDateFromAttribute($date)
    {
        return Carbon::parse($date, Utils::getTimezone());
    }

    public function getDateToAttribute($date)
    {
        return Carbon::parse($date, Utils::getTimezone());
    }

    // public function getCreatedAtAttribute($time)
    // {
    //     return Carbon::parse($time, "UTC")->setTimezone(Utils::getTimezone());
    // }

    public function isActive($allowFuture = false)
    {
        if ($this->forceInactive) {
            return false;
        }

        if ($this->subscriptionType === SubscriptionType::SINGLE) {
            return true;
        }

        $today = Utils::today();
        return ($allowFuture || $this->dateFrom <= $today) && $this->dateTo >= $today;
    }

    public function getActiveAttribute()
    {
        return $this->isActive();
    }

    public function getAgeGroupAttribute($ageGroup)
    {
        return Local\AgeGroup::find($ageGroup);
    }

    public function getObjectTypeAttribute()
    {
        if ($this->event_id !== null) {
            return ObjectType::EVENT;
        } elseif ($this->gym_id !== null || $this->tgroup_id !== null) {
            return ObjectType::TRAININGS;
        } else {
            return null;
        }
    }

    public function getObjectAttribute()
    {
        switch ($this->objectType) {
            case ObjectType::EVENT:
                return $this->event;
            case ObjectType::TRAININGS:
                return $this->trainingGroup;

            default:
                return null;
        }
    }

    public function getSubscriptionTypeAttribute()
    {
        return $this->type;
    }

    public function getPaymentTemplatesAttribute()
    {
        return $this->object->getPaymentTemplatesFiltered(null, $this->subscriptionType, $this->numMonths);
    }

    public function getPaymentAmountAttribute()
    {
        return Utils::sumPaymentTemplates($this->paymentTemplates);
    }

    public function getInitialPaymentTemplateAttribute()
    {
        return Utils::getInitialPaymentTemplate($this->paymentTemplates);
    }

    public function getInitialPaymentAmountAttribute()
    {
        $initialTemplate = $this->initialPaymentTemplate;

        return $initialTemplate ? $initialTemplate["amount"] : 0;
    }

    public function getPaidAmountAttribute($paidAmount)
    {
        if ($this->paid) {
            return $paidAmount;
        }

        return $this->payments()->get()->pluck("paidAmount")->sum();
    }

    public function getToPayAmountAttribute()
    {
        return $this->paymentAmount - $this->paidAmount;
    }

    public function getApplicationFormFilenameAttribute()
    {
        $plugin = Reservations::instance();

        switch ($this->objectType) {
            case ObjectType::EVENT:
                return $plugin->getOption("form_filename_" . $this->event->eventType["slugPlural"]);
            case ObjectType::TRAININGS:
                return $plugin->getOption("form_filename");

            default:
                return null;
        }
    }

    public function getDescriptionAttribute()
    {
        switch ($this->objectType) {
            case ObjectType::EVENT:
                return __('Subscription for', 'reservations') . " " . $this->event->title;

            case ObjectType::TRAININGS:
                if ($this->subscriptionType === SubscriptionType::MONTHLY) {
                    return sprintf(_n('L.E.A.D. Parkour subscription for %d month', 'L.E.A.D. Parkour subscription for %d months', $this->numMonths, 'reservations'), $this->numMonths);
                } else if ($this->subscriptionType === SubscriptionType::BIANNUAL) {
                    return __('L.E.A.D. Parkour biannual subscription', 'reservations');
                } else if ($this->subscriptionType === SubscriptionType::ANNUAL) {
                    return __('L.E.A.D. Parkour annual subscription', 'reservations');
                }

            default:
                return __('L.E.A.D. Parkour subscription', 'reservations');
        }
    }

    public function getInvoiceDescriptionAttribute()
    {
        $subscriber = $this->subscriber;

        $price = $this->paid ? $this->paidAmount : $this->paymentAmount;

        $subscriberPart = sprintf(_x('(%s %s, p.n. %s, %s)', 'invoice description subscriber part', 'reservations'), $subscriber->first_name, $subscriber->last_name, $subscriber->personal_number, $subscriber->address);
        $pricePart = sprintf(__('Price %s US$.', 'reservations'), Utils::formatNumber($price));

        switch ($this->objectType) {
            case ObjectType::EVENT:
                return sprintf(_x('Subscription for %s %s. %s', 'invoice description for event', 'reservations'), $this->event->title, $subscriberPart, $pricePart);

            case ObjectType::TRAININGS:
                $periodPart = sprintf(_x('Period %s – %s', 'invoice description duraiton part', 'reservations'), $this->dateFrom->format("j. n. Y"), $this->dateTo->format("j. n. Y"));

                if ($this->subscriptionType === SubscriptionType::MONTHLY) {
                    $periodSuffix = sprintf(_n(' (%d month)', ' (%d months)', $this->numMonths, 'reservations'), $this->numMonths);

                    return sprintf(__('Monthly subscription %s. %s %s%s.', 'reservations'), $subscriberPart, $pricePart, $periodPart, $periodSuffix);
                } else if ($this->subscriptionType === SubscriptionType::BIANNUAL) {
                    return sprintf(__('Biannual subscription %s. %s %s.', 'reservations'), $subscriberPart, $pricePart, $periodPart);
                } else if ($this->subscriptionType === SubscriptionType::ANNUAL) {
                    return sprintf(__('Annual subscription %s. %s %s.', 'reservations'), $subscriberPart, $pricePart, $periodPart);
                }

            default:
                return __('L.E.A.D. Parkour subscription', 'reservations');
        }
    }

    /* Methods */

    public function getGoPay()
    {
        return Reservations::instance()->goPayManager->getGoPay($this->objectType, $this->objectType === ObjectType::EVENT ? $this->event->eventType : null);
    }

    public function updatePaidStatus($queryGoPay = true, $skipPayment = null)
    {
        if ($this->paid) {
            return true;
        }

        foreach ($this->payments()->get() as $payment) {
            if ($payment->is($skipPayment))
                continue;

            $payment->updatePaidStatus($queryGoPay);
        }

        $paid = $this->toPayAmount <= 0;

        if ($paid && !$this->paid) {
            $this->paidAmount = $this->paidAmount;
            $this->paid       = true;
            $this->save();
        }

        return $paid;
    }

    public function getApplicationFormStreamContext()
    {
        $subscriber = $this->subscriber;
        $plugin     = Reservations::instance();

        $data                  = $subscriber->toArray();
        $data["date_of_birth"] = $subscriber->date_of_birth->format("j. n. Y");

        if (Reservations::MODE === "lubo") {
            $data["organisation_1"]      = $data["organisation_2"]      = $data["organisation_3"]      = $plugin->getOption("organisation");
            $data["registration_number"] = $plugin->getOption("registration_number");
            $data["rep_date_of_birth"]   = $subscriber->rep_date_of_birth ? $subscriber->rep_date_of_birth->format("j. n. Y") : "";
        } else if ($this->objectType === ObjectType::EVENT) {
            $data["rep_date_of_birth"] = $subscriber->rep_date_of_birth ? $subscriber->rep_date_of_birth->format("j. n. Y") : "";
        }

        if ($this->objectType === ObjectType::EVENT && $this->event->eventType["id"] === "workshop") {
            $data["catering"] = TranslatableEnums::yesNoUcFirst()[$data["catering"]] ?? $data["catering"];
            $data["carpool"]  = TranslatableEnums::carpoolApplicationFormUcFirst()[$data["carpool"]] ?? $data["carpool"];
        } else if ($this->objectType === ObjectType::EVENT && $this->event->eventType["id"] === "camp") {
            $data["shirt_size"] = TranslatableEnums::shirtSizes()[$data["shirt_size"]] ?? $data["shirt_size"];
            $data["swimmer"]    = TranslatableEnums::yesNoUcFirst()[$data["swimmer"]] ?? $data["swimmer"];
        }

        $req = [
            "data" => $data,
            "form" => $this->applicationFormFilename,
        ];

        return stream_context_create([
            "http" => [
                "method"  => "POST",
                "content" => http_build_query($req),
                "header"  => "Content-Type: application/x-www-form-urlencoded\r\n",
            ],
        ]);
    }

    public function getPayment($template)
    {
        return $this->payments()->where("templateHash", $template["hash"])->first();
    }

    public function getOrCreatePayment($template, $force = false)
    {
        $payment = $this->getPayment($template);

        if ($payment && !$force) {
            return $payment;
        }

        $payment = $this->payments()->create([
            "template_hash"           => $template["hash"],
            "hash"                    => Utils::createHash(),
            "amount"                  => $template["amount"],
            "paid"                    => false,
            "notification_email_sent" => false,
            "confirmation_email_sent" => false,
            "created_at"              => Utils::now(),
        ]);

        return $payment;
    }

    public function getInitialPayment()
    {
        $initialTemplate = $this->initialPaymentTemplate;

        if (!$initialTemplate) {
            return null;
        }

        return $this->getOrCreatePayment($initialTemplate);
    }

    public function getEmailVariables()
    {
        $variables = [];

        $variables += $this->subscriber->getEmailVariables();

        $variables["subscriptionType"]   = TranslatableEnums::subscriptionTypesUcFirst()[$this->type] ?? "";
        $variables["subscriptionTypeLC"] = TranslatableEnums::subscriptionTypes()[$this->type] ?? "";

        if ($this->objectType === ObjectType::TRAININGS) {
            $variables["dateFrom"] = $this->dateFrom->format("j. n. Y");
            $variables["dateTo"]   = $this->dateTo->format("j. n. Y");

            $monthsLeft = max(0, Utils::today()->diffInMonths($this->dateTo));
            $daysLeft   = max(0, Utils::today()->diffInDays($this->dateTo));

            $variables["monthsRemaining"]     = $monthsLeft;
            $variables["daysRemaining"]       = $daysLeft;
            $variables["monthsRemainingSuff"] = sprintf(_n('%d month', '%d months', $monthsLeft, 'reservations'), $monthsLeft);
            $variables["daysRemainingSuff"]   = sprintf(_n('%d day', '%d days', $daysLeft, 'reservations'), $daysLeft);
        }

        if ($this->object) {
            $variables["subscribeUrl"] = $this->object->subscribeLink . "?id=" . $this->subscriber->hash;

            $variables += $this->object->getEmailVariables();
        }

        return $variables;
    }

    public function generateInvoicePdf()
    {
        /** @var IInvoiceGenerator */
        $invoiceGenerator = Reservations::instance()->invoiceGenerator;
        if (!$invoiceGenerator || !$this->paid)
            return null;

        return $invoiceGenerator->generateInvoicePdfForSubscription($this);
    }

    public function sendNotificationEmail()
    {
        $templateId = Reservations::instance()->getOption("subscription_notification_template", null);

        if (!$templateId) {
            return;
        }

        $messageTemplateModel = Local\MessageTemplate::find($templateId);

        if (!$messageTemplateModel || $messageTemplateModel["body"] === "") {
            return;
        }

        $messageTemplate = Mail\MessageTemplate::fromModel($messageTemplateModel);

        $variables = $this->getEmailVariables();

        $variables["paymentAmount"] = $variables["totalAmount"] = Utils::formatNumber($this->paymentAmount);

        $message = $messageTemplate->createMessage($variables);

        $message->texturizeBody();
        $message->addTo($this->subscriber->contactEmail);

        if ($messageTemplateModel["attach_application_form"] && $this->applicationFormFilename) {
            $message->addUrlAttachment(Reservations::instance()->getOption("form_filler_url"), _x('application_form.pdf', 'attachment file name', 'reservations'), $this->getApplicationFormStreamContext());
        }

        $ok = Reservations::instance()->mailer->send($message);

        if ($ok) {
            $this->notificationEmailSent = true;
            $this->save();
        }

        return $ok;
    }
}
