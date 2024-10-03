<?php

namespace Reservations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Reservations;
use Reservations\Mail;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Utils\Cached;
use Reservations\Utils;
use Sofa\Eloquence;

class Payment extends Model
{
    use SoftDeletes;
    use Eloquence\Eloquence, Eloquence\Mappable;
    use Cached;

    protected $table      = 'payments';
    protected $primaryKey = 'payment_id';
    public $timestamps    = false;

    protected $fillable = [
        "template_hash",
        "hash",
        "amount",
        "paid",
        "paid_amount",
        "notification_email_sent",
        "confirmation_email_sent",
        "created_at",
    ];

    protected $maps = [
        "id"                    => "payment_id",
        "templateHash"          => "template_hash",
        "paidAmount"            => "paid_amount",
        "notificationEmailSent" => "notification_email_sent",
        "confirmationEmailSent" => "confirmation_email_sent",
        "createdAt"             => "created_at",
    ];

    protected $dates = [
        'created_at',
    ];

    protected $cached = [
        "paymentTemplate",
    ];

    /* Relationships */

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, "subscription_id");
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, "payment_id");
    }

    /* Scopes */

    public function scopePaid($builder)
    {
        $builder->where("paid", true);
    }

    public function scopePaidPartially($builder)
    {
        $builder->where("paid", true)->orWhereHas("transactions", function ($builder) {
            $builder->where("paid", true);
        });
    }

    public function scopeHash($builder, $hash)
    {
        $builder->where("hash", $hash);
    }

    /* Attributes */

    public function getPaidAmountAttribute($paidAmount)
    {
        if ($this->paid) {
            return $paidAmount;
        }

        return $this->transactions()->get()->pluck("paidAmount")->sum();
    }

    public function getToPayAmountAttribute()
    {
        return $this->amount - $this->paidAmount;
    }

    public function getPaymentTemplateAttribute()
    {
        $templateHash = $this->templateHash;

        return collect($this->subscription->paymentTemplates)->first(function ($template) use ($templateHash) {
            return $template["hash"] === $templateHash;
        });
    }

    public function getPaidAtAttribute()
    {
        if(!$this->paid)
            return null;

        return $this->transactions()->where("paid", true)->get()->pluck("paid_at")->max();
    }

    public function getInitialAttribute()
    {
        $template = $this->paymentTemplate;
        return $template && isset($template["initial"]) && $template["initial"];
    }

    /* Methods */

    public function getGoPay()
    {
        return $this->subscription->getGoPay();
    }

    public function updatePaidStatus($queryGoPay = true)
    {
        if ($this->paid) {
            $fresh = $this->fresh();

            if (!$fresh->confirmationEmailSent) {
                $this->sendConfirmationEmail();
            }

            return true;
        }

        if ($queryGoPay) {
            foreach ($this->transactions()->get() as $transaction) {
                $transaction->updatePaidStatus(false);
            }
        }

        $paid = $this->toPayAmount <= 0;

        if ($paid && !$this->paid) {
            $this->paidAmount = $this->paidAmount;
            $this->paid       = true;
            $this->save();

            $this->subscription->updatePaidStatus($queryGoPay, $this);
        }

        $fresh = $this->fresh();

        if ($this->paid && !$fresh->confirmationEmailSent) {
            $this->sendConfirmationEmail();
        }

        return $paid;
    }

    public function createTransaction($options)
    {
        $subscriber = $this->subscription->subscriber;
        $gopay      = $this->getGoPay();

        $amount = max(0, $this->toPayAmount);

        if ($amount === 0) {
            return [null, null];
        }

        $gopayTransaction = $gopay->createPayment([
            "payer"             => [
                "contact" => [
                    "first_name"   => $subscriber->rep_first_name,
                    "last_name"    => $subscriber->rep_last_name,
                    "email"        => $subscriber->contact_email,
                    "phone_number" => $subscriber->contact_phone,
                ],
            ],
            "target"            => [
                "type" => "ACCOUNT",
                "goid" => $gopay->gopay->getConfig("goid"),
            ],
            "amount"            => $amount * 100,
            "currency"          => "CZK",
            "order_number"      => $this->subscription->subscription_id,
            "order_description" => $options["order_description"],
            "items"             => [
                [
                    "type"        => "ITEM",
                    "product_url" => $options["item_url"],
                    "count"       => 1,
                    "amount"      => $amount * 100,
                    "name"        => $options["item_description"],
                    "vat_rate"    => 0,
                ],
            ],
            "callback"          => [
                "return_url"       => $options["return_url"],
                "notification_url" => $options["notification_url"],
            ],
            "lang"              => "CS",
        ]);

        if ($gopayTransaction->hasSucceed()) {
            $transaction = $this->transactions()->create([
                "gopay_transaction_id" => $gopayTransaction->json["id"],
                "amount"               => $this->amount,
                "paid"                 => false,
                "created_at"           => Utils::now(),
            ]);

            return [$transaction, $gopayTransaction];
        }

        return [null, $gopayTransaction];
    }

    public function markPaidManually()
    {
        if ($this->paid) {
            return null;
        }

        $transaction = $this->transactions()->create([
            "gopay_transaction_id" => 0,
            "amount"               => $this->toPayAmount,
            "paid"                 => true,
            "created_at"           => Utils::now(),
            "paid_at"              => Utils::now(),
        ]);

        $this->updatePaidStatus(false);

        return $transaction;
    }

    private function getPayUrl()
    {
        $pageRouter   = Reservations::instance()->pageRouter;
        $subscription = $this->subscription;

        if ($subscription->objectType === ObjectType::EVENT) {
            if (Reservations::instance()->isFeatureEnabled("unified_events")) {
                $page = $pageRouter->eventsPage;
            } else {
                $page = $pageRouter->eventTypePages[$subscription->event->eventType["id"]];
            }
        } else if ($subscription->objectType === ObjectType::TRAININGS) {
            $page = $pageRouter->trainingsPage;
        }

        return get_permalink($page->ID) . _x('payment', 'url slug', 'reservations') . '/?id=' . $this->hash;
    }

    private function sendEmail($templateKey, $attachmentSetKey)
    {
        $paymentTemplate = $this->paymentTemplate;

        if (!$paymentTemplate || $paymentTemplate[$templateKey] === "") {
            return;
        }

        $messageTemplateModel = Local\MessageTemplate::find($paymentTemplate[$templateKey]);

        if (!$messageTemplateModel || $messageTemplateModel["body"] === "") {
            return;
        }

        $messageTemplate = Mail\MessageTemplate::fromModel($messageTemplateModel);
        $subscription    = $this->subscription;

        $variables = $subscription->getEmailVariables();

        $variables["amount"]            = Utils::formatNumber($this->amount);
        $variables["paymentPaidAmount"] = Utils::formatNumber($this->paidAmount);
        $variables["paidAmount"]        = Utils::formatNumber($this->subscription->paidAmount);
        $variables["toPayAmount"]       = Utils::formatNumber($this->subscription->toPayAmount);
        $variables["paymentAmount"]     = $variables["totalAmount"]     = Utils::formatNumber($this->subscription->paymentAmount);
        $variables["payUrl"]            = $this->getPayUrl();

        $message = $messageTemplate->createMessage($variables);

        $message->texturizeBody();
        $message->addTo($subscription->subscriber->contactEmail);

        if ($messageTemplateModel["attach_application_form"] && $subscription->applicationFormFilename) {
            $message->addUrlAttachment(Reservations::instance()->getOption("form_filler_url"), _x('application_form.pdf', 'attachment file name', 'reservations'), $subscription->getApplicationFormStreamContext());
        }

        if (!empty($messageTemplateModel["attach_invoice_if_paid"]) && $subscription->paid) {
            try {
                $pdf = $this->subscription->generateInvoicePdf();
            } catch(\Exception $e) {
                $pdf = null;
            }

            if($pdf) {
                $message->addStringAttachment($pdf, _x('invoice.pdf', 'invoice file name', 'reservations'));
            }
        }

        $attachmentSet = $paymentTemplate[$attachmentSetKey] !== null ? ($subscription->object->attachmentSets[$paymentTemplate[$attachmentSetKey]] ?? []) : [];

        foreach (Utils::resolveAttachmentIds($attachmentSet, false) as $path) {
            $message->addAttachment($path);
        }

        return Reservations::instance()->mailer->send($message);
    }

    public function sendNotificationEmail()
    {
        $paymentTemplate = $this->paymentTemplate;

        if (!$paymentTemplate) {
            return;
        }

        if (isset($paymentTemplate["initial"]) && $paymentTemplate["initial"] &&
            !Reservations::instance()->isFeatureEnabled("initial_payment_notification")) {
            return;
        }

        $ok = $this->sendEmail("notification_email_template", "notification_email_attachment_set");

        if ($ok) {
            $this->notificationEmailSent = true;
            $this->save();
        }

        return $ok;
    }

    public function sendConfirmationEmail()
    {
        $ok = $this->sendEmail("confirmation_email_template", "confirmation_email_attachment_set");

        if ($ok) {
            $this->confirmationEmailSent = true;
            $this->save();
        }

        return $ok;
    }

}
