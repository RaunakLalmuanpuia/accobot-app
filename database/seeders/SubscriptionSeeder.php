<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $plans = Plan::whereIn('slug', ['ca_firm', 'business_ca', 'business_solo', 'personal'])
            ->get()
            ->keyBy('slug');

        // Business tenants cycle through these plans in order
        $businessPlans = ['business_ca', 'business_solo', 'personal'];
        $businessIndex = 0;

        $periodStart  = now();
        $periodEnd    = now()->addMonth();
        $trialDays    = config('plans.ca_trial_days', 14);

        Tenant::all()->each(function (Tenant $tenant) use ($plans, $businessPlans, &$businessIndex, $periodStart, $periodEnd, $trialDays) {
            // Skip tenants that already have a subscription
            if ($tenant->subscription()->exists()) {
                return;
            }

            if ($tenant->type === 'ca_firm') {
                // Mirror AuthController::register — CA firms start on a free trial
                Subscription::create([
                    'tenant_id'    => $tenant->id,
                    'plan_id'      => $plans->get('ca_firm')->id,
                    'status'       => 'trialing',
                    'trial_ends_at' => now()->addDays($trialDays),
                ]);
                return;
            }

            // Business tenants: seed as active (post-payment state for dev convenience)
            $slug = $businessPlans[$businessIndex % count($businessPlans)];
            $plan = $plans->get($slug);
            $businessIndex++;

            if (! $plan) {
                return;
            }

            Subscription::create([
                'tenant_id'            => $tenant->id,
                'plan_id'              => $plan->id,
                'status'               => 'active',
                'current_period_start' => $periodStart,
                'current_period_end'   => $periodEnd,
            ]);
        });
    }
}
