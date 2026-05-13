<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'razorpay_subscription_id', 'razorpay_customer_id', 'razorpay_short_url',
        'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at',
    ];

    protected $casts = [
        'trial_ends_at'        => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'cancelled_at'         => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function isAccessible(): bool
    {
        return $this->isActive() || $this->onTrial();
    }

    public function hasFeature(string $feature): bool
    {
        if (! $this->relationLoaded('plan')) {
            $this->load('plan');
        }

        if ($this->plan && $this->plan->hasFeature($feature)) {
            return true;
        }

        // Check active addons
        if (! $this->relationLoaded('addons')) {
            $this->load('addons.plan');
        }

        return $this->addons
            ->where('status', 'active')
            ->contains(fn ($addon) => $addon->plan->hasFeature($feature));
    }
}
