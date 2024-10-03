<?php

namespace Reservations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Reservations;
use Reservations\GoPay;
use Reservations\Utils;
use Sofa\Eloquence;

class Transaction extends Model
{
    use SoftDeletes;
    use Eloquence\Eloquence, Eloquence\Mappable;

    protected $table      = 'transactions';
    protected $primaryKey = 'transaction_id';
    public $timestamps    = false;

    protected $fillable = [
        "gopay_transaction_id",
        "amount",
        "paid",
        "created_at",
        "paid_at",
    ];

    protected $maps = [
        "id"                 => "transaction_id",
        "gopayTransactionId" => "gopay_transaction_id",
        "goPayTransactionId" => "gopay_transaction_id",
        "createdAt"          => "created_at",
        "paidAt"             => "paid_at",
    ];

    protected $dates = [
        'created_at',
        'paid_at',
    ];

    /* Relationships */

    public function payment()
    {
        return $this->belongsTo(Payment::class, "payment_id");
    }

    /* Scopes */

    public function scopePaid($builder)
    {
        $builder->where("paid", true);
    }

    public function scopeTransactionId($builder, $transactionId)
    {
        $builder->whereNotNull("gopayTransactionId")->where("goPayTransactionId", (int) $transactionId);
    }

    public function scopeManual($builder)
    {
        $builder->where("gopay_transaction_id", 0);
    }

    /* Attributes */

    public function getPaidAmountAttribute()
    {
        return $this->paid ? $this->amount : 0;
    }

    public function getManualAttribute()
    {
        return $this->goPayTransactionId === 0;
    }

    /* Methods */

    public function getGoPay()
    {
        return $this->payment->getGoPay();
    }

    public function getStatus()
    {
        if ($this->manual) {
            return null;
        }

        return GoPay\PaymentStatus::getStatus($this->getGoPay(), $this->goPayTransactionId);
    }

    public function updatePaidStatus($updatePayment = true)
    {
        $status = $this->getStatus();

        if ($status !== null && $status->paid && !$this->paid) {
            $this->paid   = true;
            $this->paidAt = Utils::now();

            $this->save();

            if (!$this->payment->paid && $updatePayment) {
                $this->payment->updatePaidStatus(false);
            }
        }

        return $status;
    }
}
