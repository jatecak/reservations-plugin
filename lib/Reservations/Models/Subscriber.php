<?php

namespace Reservations\Models;

use Illuminate\Database\Eloquent\Model;
use Nette\Utils\Strings;
use Sofa\Eloquence;

class Subscriber extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable;

    protected $table      = 'subscribers';
    protected $primaryKey = 'subscriber_id';
    public $timestamps    = false;

    protected $fillable = [
        "hash",

        "first_name",
        "last_name",
        "date_of_birth",
        "address",
        "personal_number",
        "health_restrictions",
        "health_insurance_code",
        "shirt_size",
        "swimmer",
        "used_medicine",
        "facebook",
        "independent_leave",
        "preferred_level",

        "rep_first_name",
        "rep_last_name",
        "rep_date_of_birth",
        "rep_address",
        "rep_personal_number",

        "contact_email",
        "contact_phone",
        "contact_phone_2",

        "referrer",
        "referrer_other",
        "reason",
        "reason_other",

        "carpool",
        "carpool_seats",
        "carpool_contact",

        "catering",
        "meal",
    ];

    protected $maps = [
        "id"                  => "subscriber_id",
        "firstName"           => "first_name",
        "lastName"            => "last_name",
        "dateOfBirth"         => "date_of_birth",
        "personalNumber"      => "personal_number",
        "healthInsuranceCode" => "health_insurance_code",
        "healthRestrictions"  => "health_restrictions",
        "independentLeave"    => "independent_leave",
        "shirtSize"           => "shirt_size",
        "usedMedicine"        => "used_medicine",

        "repFirstName"        => "rep_first_name",
        "repLastName"         => "rep_last_name",
        "repDateOfBirth"      => "rep_date_of_birth",
        "repAddress"          => "rep_address",
        "repPersonalNumber"   => "rep_personal_number",

        "contactEmail"        => "contact_email",
        "contactPhone"        => "contact_phone",
        "contactPhone2"       => "contact_phone_2",

        "referrerOther"       => "referrer_other",
        "reasonOther"         => "reason_other",

        "carpoolSeats"        => "carpool_seats",
        "carpoolContact"      => "carpool_contact",
    ];

    protected $dates = [
        "date_of_birth",
        "rep_date_of_birth",
    ];

    /* Relationships */

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, "subscriber_id");
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    /* Scopes */

    public function scopeSearchQuery($builder, $q)
    {
        $builder->where("first_name", "like", "%" . $q . "%")
            ->orWhere("last_name", "like", "%" . $q . "%")
            ->orWhere("address", "like", "%" . $q . "%")
            ->orWhere("health_restrictions", "like", "%" . $q . "%")
            ->orWhere("used_medicine", "like", "%" . $q . "%")
            ->orWhere("health_insurance_code", "like", "%" . $q . "%")
            ->orWhere("personal_number", "like", "%" . $q . "%")
            ->orWhere("facebook", "like", "%" . $q . "%")

            ->orWhere("rep_first_name", "like", "%" . $q . "%")
            ->orWhere("rep_last_name", "like", "%" . $q . "%")
            ->orWhere("rep_address", "like", "%" . $q . "%")
            ->orWhere("rep_personal_number", "like", "%" . $q . "%")

            ->orWhere("contact_email", "like", "%" . $q . "%")
            ->orWhere("contact_phone", "like", "%" . $q . "%")
            ->orWhere("contact_phone_2", "like", "%" . $q . "%")
            ->orWhere("carpool_contact", "like", "%" . $q . "%");
    }

    public function scopeHash($builder, $hash)
    {
        $builder->where("hash", $hash);
    }

    /* Attributes */

    public function getFullNameAttribute()
    {
        return $this->firstName . " " . $this->lastName;
    }

    public function getRepFullNameAttribute()
    {
        return $this->repFirstName . " " . $this->repLastName;
    }

    /* Methods */

    public function generateUsername()
    {
        $username = str_replace("-", ".", sanitize_title($this->repFullName));

        return sanitize_user($username, true);
    }

    public function getEmailVariables()
    {
        $variables = [];

        $variables["name"]         = $this->fullName;
        $variables["repName"]      = $this->repFullName;
        $variables["contactEmail"] = $this->contactEmail;

        return $variables;
    }

}
