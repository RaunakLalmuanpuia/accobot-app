<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;

class SubscriptionService
{
    /**
     * Check whether a tenant's active subscription includes the given feature.
     */
    public function can(Tenant $tenant, string $feature): bool
    {
        return $tenant->hasFeature($feature);
    }

    /**
     * Abort with 403 if the tenant's plan does not include the feature.
     * Use this at the top of controller methods and AI tool execute() calls.
     */
    public function authorize(Tenant $tenant, string $feature): void
    {
        abort_unless(
            $this->can($tenant, $feature),
            403,
            'Your current plan does not include this feature. Please upgrade to continue.',
        );
    }

    /**
     * Return the full list of feature slugs the tenant currently has access to.
     * Returns an empty array when the subscription is inactive/missing.
     */
    public function features(Tenant $tenant): array
    {
        $subscription = $tenant->subscription()->with('plan', 'addons.plan')->first();

        if (! $subscription || ! $subscription->isAccessible()) {
            return [];
        }

        $features = $subscription->plan->features ?? [];

        // Merge any active addon features
        foreach ($subscription->addons->where('status', 'active') as $addon) {
            $features = array_unique(array_merge($features, $addon->plan->features ?? []));
        }

        return array_values($features);
    }

    /**
     * Summary for sharing to the frontend via HandleInertiaRequests.
     */
    public function summary(Tenant $tenant): array
    {
        $subscription = $tenant->subscription()->with('plan')->first();

        if (! $subscription) {
            return ['status' => null, 'plan' => null, 'features' => []];
        }

        return [
            'status'        => $subscription->status,
            'plan'          => $subscription->plan->slug ?? null,
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'features'      => $this->features($tenant),
        ];
    }
}
