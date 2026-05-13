<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RazorpayPayment extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'razorpay_payment_id',
        'razorpay_subscription_id',
        'event_type',
        'amount',
        'currency',
        'status',
        'method',
        'email',
        'contact',
        'razorpay_created_at',
        'payload',
    ];

    protected $casts = [
        'payload'             => 'array',
        'razorpay_created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function amountInRupees(): float
    {
        return $this->amount / 100;
    }
}
