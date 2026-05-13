<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\RazorpayService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Tenant $tenant)
    {
        $subscription = $tenant->subscription()->with('plan', 'addons.plan')->first();
        $availablePlans = $this->availablePlans($tenant);
        $addonPlan = Plan::where('slug', 'ai_addon')->where('is_active', true)->first();

        return inertia('Billing/Index', [
            'tenant'         => $tenant->only('id', 'name', 'type', 'tally_managed_by_ca'),
            'subscription'   => $subscription ? [
                'id'                  => $subscription->id,
                'status'              => $subscription->status,
                'plan_slug'           => $subscription->plan->slug,
                'plan_name'           => $subscription->plan->name,
                'plan_price'          => $subscription->plan->price,
                'features'            => $subscription->plan->features,
                'trial_ends_at'       => $subscription->trial_ends_at?->toDateString(),
                'current_period_end'  => $subscription->current_period_end?->toDateString(),
                'cancelled_at'        => $subscription->cancelled_at?->toDateString(),
                'has_ai_addon'        => $subscription->addons
                    ->where('status', 'active')
                    ->isNotEmpty(),
            ] : null,
            'availablePlans' => $availablePlans->map(fn($p) => [
                'id'       => $p->id,
                'slug'     => $p->slug,
                'name'     => $p->name,
                'price'    => $p->price,
                'features' => $p->features,
            ])->values(),
            'addonPlan' => $addonPlan ? [
                'id'    => $addonPlan->id,
                'name'  => $addonPlan->name,
                'price' => $addonPlan->price,
            ] : null,
        ]);
    }

    public function cancel(Tenant $tenant, RazorpayService $razorpay)
    {
        $subscription = $tenant->subscription;

        abort_unless($subscription && $subscription->isActive(), 422, 'No active subscription to cancel.');

        if ($subscription->razorpay_subscription_id) {
            $razorpay->cancelSubscription($subscription->razorpay_subscription_id);
        }

        $subscription->update(['cancelled_at' => now()]);

        AuditEvent::log(
            'subscription.cancelled',
            ['plan' => $subscription->plan->slug, 'source' => 'user'],
            auth()->id(),
            $tenant->id,
        );

        return back()->with('success', 'Your subscription has been cancelled and will remain active until the end of the current billing period.');
    }

    public function subscribeAddon(Request $request, Tenant $tenant, RazorpayService $razorpay)
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

        return response()->json([
            'subscription_id' => $result['subscription_id'],
            'key_id'          => config('services.razorpay.key_id'),
        ]);
    }

    public function selectPlan(Tenant $tenant)
    {
        $plans = $this->availablePlans($tenant);
        $subscription = $tenant->subscription()->with('plan')->first();

        return inertia('Billing/SelectPlan', [
            'tenant'       => $tenant->only('id', 'name', 'type', 'tally_managed_by_ca'),
            'plans'        => $plans,
            'subscription' => $subscription ? [
                'status'        => $subscription->status,
                'plan_slug'     => $subscription->plan->slug,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            ] : null,
        ]);
    }

    public function subscribe(Request $request, Tenant $tenant, RazorpayService $razorpay)
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
            $request->user()->phone ?? null,
        );

        // Upsert the local subscription record so the webhook can match it later
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

        return response()->json([
            'subscription_id' => $result['subscription_id'],
            'key_id'          => config('services.razorpay.key_id'),
        ]);
    }

    public function success(Tenant $tenant)
    {
        // Razorpay's hosted page does not redirect back after mandate setup.
        // Users land here by navigating back themselves. Subscription activation
        // is handled entirely by the subscription.activated webhook.
        return redirect()
            ->route('dashboard', $tenant)
            ->with('info', 'Your payment is being processed. You\'ll get access as soon as it\'s confirmed — usually within a minute.');
    }

    private function availablePlans(Tenant $tenant)
    {
        $query = Plan::where('is_active', true)->where('is_addon', false);

        if ($tenant->type === 'ca_firm') {
            return $query->where('slug', 'ca_firm')->get();
        }

        // Business tenants: CA-managed only sees the discounted plan
        if ($tenant->tally_managed_by_ca) {
            return $query->where('slug', 'business_ca')->get();
        }

        return $query->whereIn('slug', ['business_solo', 'personal'])->get();
    }
}
