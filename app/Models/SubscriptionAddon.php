<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddon extends Model
{
    protected $fillable = [
        'subscription_id', 'plan_id', 'razorpay_subscription_id', 'status', 'current_period_end',
    ];

    protected $casts = [
        'current_period_end' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
