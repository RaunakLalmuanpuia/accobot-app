<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileBillingController extends Controller
{
    /**
     * GET /api/mobile/tenants/{tenant}/billing
     * Returns current subscription status, plan details, and feature list.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $subscription = $tenant->subscription()->with('plan', 'addons.plan')->first();

        if (! $subscription) {
            return response()->json(['subscription' => null, 'features' => []]);
        }

        $service  = app(SubscriptionService::class);
        $features = $service->features($tenant);

        return response()->json([
            'subscription' => [
                'status'              => $subscription->status,
                'plan_slug'           => $subscription->plan->slug,
                'plan_name'           => $subscription->plan->name,
                'plan_price'          => $subscription->plan->price,
                'trial_ends_at'       => $subscription->trial_ends_at?->toDateString(),
                'current_period_end'  => $subscription->current_period_end?->toDateString(),
                'cancelled_at'        => $subscription->cancelled_at?->toDateString(),
                'has_ai_addon'        => $subscription->addons
                    ->where('status', 'active')
                    ->isNotEmpty(),
            ],
            'features' => $features,
        ]);
    }

    /**
     * GET /api/mobile/tenants/{tenant}/billing/plans
     * Returns plans available for this tenant to subscribe to.
     */
    public function plans(Tenant $tenant): JsonResponse
    {
        $plans = $this->availablePlans($tenant)->map(fn($p) => [
            'id'       => $p->id,
            'slug'     => $p->slug,
            'name'     => $p->name,
            'price'    => $p->price,
            'features' => $p->features,
        ])->values();

        $addonPlan = Plan::where('slug', 'ai_addon')->where('is_active', true)->first();

        return response()->json([
            'plans'      => $plans,
            'addon_plan' => $addonPlan ? [
                'id'    => $addonPlan->id,
                'slug'  => $addonPlan->slug,
                'name'  => $addonPlan->name,
                'price' => $addonPlan->price,
            ] : null,
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/billing/subscribe
     * Creates a Razorpay Subscription and returns the hosted checkout URL.
     *
     * Body: { "plan_id": 1 }
     * Response: { "short_url": "https://rzp.io/..." }
     */
    public function subscribe(Request $request, Tenant $tenant, RazorpayService $razorpay): JsonResponse
    {
        $available = $this->availablePlans($tenant)->pluck('id')->all();

        $request->validate([
            'plan_id' => ['required', 'integer', 'in:' . implode(',', $available)],
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        abort_if(blank($plan->razorpay_plan_id), 422, 'This plan is not yet configured for payment. Please contact support.');

        $result = $razorpay->createSubscription(
            $plan,
            $request->user()->email,
            $request->user()->name,
        );

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id'                  => $plan->id,
                'razorpay_subscription_id' => $result['subscription_id'],
                'status'                   => 'pending',
                'trial_ends_at'            => null,
                'current_period_start'     => null,
                'current_period_end'       => null,
                'cancelled_at'             => null,
            ]
        );

        return response()->json(['short_url' => $result['short_url']]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/billing/cancel
     * Cancels the subscription at the end of the current billing cycle.
     */
    public function cancel(Tenant $tenant, RazorpayService $razorpay): JsonResponse
    {
        $subscription = $tenant->subscription;

        abort_unless($subscription && $subscription->isActive(), 422, 'No active subscription to cancel.');

        if ($subscription->razorpay_subscription_id) {
            $razorpay->cancelSubscription($subscription->razorpay_subscription_id);
        }

        $subscription->update(['cancelled_at' => now()]);

        AuditEvent::log(
            'subscription.cancelled',
            ['plan' => $subscription->plan->slug, 'source' => 'user_mobile'],
            auth()->id(),
            $tenant->id,
        );

        return response()->json(['ok' => true, 'message' => 'Subscription will remain active until the end of the current billing period.']);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/billing/addon
     * Subscribes to the AI Assistance addon (Personal plan only).
     * Returns { "short_url": "..." } for the Razorpay checkout.
     */
    public function subscribeAddon(Request $request, Tenant $tenant, RazorpayService $razorpay): JsonResponse
    {
        $subscription = $tenant->subscription;

        abort_unless($subscription && $subscription->isAccessible(), 422, 'No active subscription.');
        abort_unless($subscription->plan->slug === 'personal', 422, 'AI addon is only available on the Personal plan.');

        $addonPlan = Plan::where('slug', 'ai_addon')->where('is_active', true)->firstOrFail();

        $result = $razorpay->createSubscription(
            $addonPlan,
            $request->user()->email,
            $request->user()->name,
        );

        SubscriptionAddon::updateOrCreate(
            ['subscription_id' => $subscription->id, 'plan_id' => $addonPlan->id],
            ['razorpay_subscription_id' => $result['subscription_id'], 'status' => 'pending'],
        );

        return response()->json(['short_url' => $result['short_url']]);
    }

    private function availablePlans(Tenant $tenant)
    {
        $query = Plan::where('is_active', true)->where('is_addon', false);

        if ($tenant->type === 'ca_firm') {
            return $query->where('slug', 'ca_firm')->get();
        }

        if ($tenant->tally_managed_by_ca) {
            return $query->where('slug', 'business_ca')->get();
        }

        return $query->whereIn('slug', ['business_solo', 'personal'])->get();
    }
}
