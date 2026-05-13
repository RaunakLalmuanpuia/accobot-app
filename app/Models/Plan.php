<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug', 'name', 'price', 'tenant_type',
        'razorpay_plan_id', 'features', 'is_addon', 'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_addon' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function priceInRupees(): float
    {
        return $this->price / 100;
    }
}
