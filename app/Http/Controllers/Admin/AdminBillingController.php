<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Plan;
use App\Models\RazorpayPayment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminBillingController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)->where('is_addon', false)->orderBy('price')->get()
            ->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'price' => $p->price]);

        $tenants = Tenant::with(['subscription.plan', 'subscription.addons.plan'])
            ->orderBy('name')
            ->get();

        $tenantIds = $tenants->pluck('id');

        // Total paid per tenant (captured payments only)
        $paymentTotals = RazorpayPayment::whereIn('tenant_id', $tenantIds)
            ->where('status', 'captured')
            ->selectRaw('tenant_id, SUM(amount) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        // Summary stats
        $statuses     = $tenants->pluck('subscription.status')->filter()->countBy();
        $activePlans  = $tenants->filter(fn ($t) => $t->subscription?->isActive());
        $mrr          = $activePlans->sum(fn ($t) => $t->subscription->plan?->price ?? 0);

        $stats = [
            'total_tenants'  => $tenants->count(),
            'active'         => $statuses['active']   ?? 0,
            'trialing'       => $statuses['trialing'] ?? 0,
            'halted'         => $statuses['halted']   ?? 0,
            'no_sub'         => $tenants->filter(fn ($t) => ! $t->subscription)->count(),
            'mrr_paise'      => $mrr,
        ];

        $rows = $tenants->map(function ($tenant) use ($paymentTotals) {
            $sub = $tenant->subscription;

            return [
                'id'               => $tenant->id,
                'name'             => $tenant->name,
                'type'             => $tenant->type,
                'subscription'     => $sub ? [
                    'id'                 => $sub->id,
                    'status'             => $sub->status,
                    'plan_id'            => $sub->plan_id,
                    'plan_slug'          => $sub->plan?->slug,
                    'plan_name'          => $sub->plan?->name,
                    'plan_price'         => $sub->plan?->price,
                    'trial_ends_at'      => $sub->trial_ends_at?->toDateString(),
                    'current_period_end' => $sub->current_period_end?->toDateString(),
                    'cancelled_at'       => $sub->cancelled_at?->toDateString(),
                    'has_ai_addon'       => $sub->addons->where('status', 'active')->isNotEmpty(),
                ] : null,
                'total_paid_paise' => (int) ($paymentTotals[$tenant->id] ?? 0),
            ];
        })->values();

        return Inertia::render('Admin/Billing', [
            'stats' => $stats,
            'rows'  => $rows,
            'plans' => $plans,
        ]);
    }

    public function changePlan(Request $request, Tenant $tenant)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::where('id', $request->plan_id)
            ->where('is_active', true)
            ->where('is_addon', false)
            ->firstOrFail();

        $sub = $tenant->subscription;
        abort_unless($sub, 404, 'This tenant has no subscription record.');

        $old = $sub->plan?->slug;
        $sub->update(['plan_id' => $plan->id]);

        AuditEvent::log(
            'subscription.plan_changed',
            ['from' => $old, 'to' => $plan->slug, 'source' => 'admin', 'channel' => 'web'],
            auth()->id(),
            $tenant->id,
        );

        return back()->with('success', "Plan changed to {$plan->name} for {$tenant->name}.");
    }

    public function changePlanAndRebill(Request $request, Tenant $tenant, RazorpayService $razorpay)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::where('id', $request->plan_id)
            ->where('is_active', true)
            ->where('is_addon', false)
            ->firstOrFail();

        abort_if(blank($plan->razorpay_plan_id), 422, 'This plan has no Razorpay plan ID configured.');

        $sub = $tenant->subscription;
        abort_unless($sub, 404, 'This tenant has no subscription record.');

        $old = $sub->plan?->slug;

        // Cancel the existing Razorpay subscription if one is live
        if ($sub->razorpay_subscription_id && in_array($sub->status, ['active', 'trialing', 'halted', 'pending'])) {
            try {
                $razorpay->cancelSubscription($sub->razorpay_subscription_id);
            } catch (\Throwable $e) {
                // Already cancelled / not found in Razorpay — safe to proceed
                Log::warning('admin rebill: could not cancel old Razorpay subscription', [
                    'tenant_id'              => $tenant->id,
                    'razorpay_subscription_id' => $sub->razorpay_subscription_id,
                    'error'                  => $e->getMessage(),
                ]);
            }
        }

        // Resolve email + name from the tenant's creator
        $owner = $tenant->createdBy;
        $email = $tenant->email ?? $owner?->email ?? 'noreply@accobot.in';
        $name  = $owner?->name ?? $tenant->name;

        $result = $razorpay->createSubscription($plan, $email, $name);

        DB::transaction(function () use ($sub, $plan, $result, $old, $tenant) {
            $sub->update([
                'plan_id'                  => $plan->id,
                'razorpay_subscription_id' => $result['subscription_id'],
                'razorpay_short_url'       => $result['short_url'],
                'status'                   => 'pending',
                'trial_ends_at'            => null,
                'current_period_start'     => null,
                'current_period_end'       => null,
                'cancelled_at'             => null,
            ]);

            AuditEvent::log(
                'subscription.plan_changed_and_rebilled',
                ['from' => $old, 'to' => $plan->slug, 'razorpay_sub' => $result['subscription_id'], 'source' => 'admin', 'channel' => 'web'],
                auth()->id(),
                $tenant->id,
            );
        });

        return back()
            ->with('success', "Plan changed to {$plan->name} for {$tenant->name}. Share the payment link with the tenant.")
            ->with('payment_url', $result['short_url']);
    }

    public function cancel(Tenant $tenant, RazorpayService $razorpay)
    {
        $sub = $tenant->subscription;
        abort_unless($sub && $sub->isActive(), 422, 'Tenant has no active subscription to cancel.');

        if ($sub->razorpay_subscription_id) {
            $razorpay->cancelSubscription($sub->razorpay_subscription_id);
        }

        $sub->update(['cancelled_at' => now()]);

        AuditEvent::log(
            'subscription.cancelled',
            ['plan' => $sub->plan?->slug, 'source' => 'admin', 'channel' => 'web'],
            auth()->id(),
            $tenant->id,
        );

        return back()->with('success', "Subscription cancelled for {$tenant->name}.");
    }

    public function grantTrial(Request $request, Tenant $tenant)
    {
        $request->validate([
            'trial_ends_at' => ['required', 'date', 'after:today'],
        ]);

        $plan = Plan::where('slug', 'ca_firm')->where('is_active', true)->first()
            ?? Plan::where('is_active', true)->where('is_addon', false)->first();

        $planId = $plan?->id ?? $tenant->subscription?->plan_id;
        abort_unless($planId, 422, 'No active plan found to assign. Please configure at least one plan first.');

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id'              => $planId,
                'status'               => 'trialing',
                'trial_ends_at'        => $request->trial_ends_at,
                'current_period_start' => null,
                'current_period_end'   => null,
                'cancelled_at'         => null,
            ]
        );

        AuditEvent::log(
            'subscription.trial_granted',
            ['trial_ends_at' => $request->trial_ends_at, 'source' => 'admin', 'channel' => 'web'],
            auth()->id(),
            $tenant->id,
        );

        return back()->with('success', "Trial granted for {$tenant->name} until {$request->trial_ends_at}.");
    }

    public function overrideStatus(Request $request, Tenant $tenant)
    {
        $request->validate([
            'status'        => ['required', 'in:pending,trialing,active,halted,cancelled,expired'],
            'plan_id'       => ['nullable', 'integer', 'exists:plans,id'],
            'trial_ends_at' => ['nullable', 'date'],
        ]);

        $sub = $tenant->subscription;
        abort_unless($sub, 404, 'This tenant has no subscription record.');

        $updates = ['status' => $request->status];

        if ($request->filled('plan_id')) {
            $updates['plan_id'] = $request->plan_id;
        }
        if ($request->filled('trial_ends_at')) {
            $updates['trial_ends_at'] = $request->trial_ends_at;
        }
        $updates['cancelled_at'] = $request->status === 'cancelled' ? ($sub->cancelled_at ?? now()) : null;

        $sub->update($updates);

        AuditEvent::log(
            'subscription.status_overridden',
            ['status' => $request->status, 'plan_id' => $request->plan_id, 'source' => 'admin', 'channel' => 'web'],
            auth()->id(),
            $tenant->id,
        );

        return back()->with('success', "Subscription status set to {$request->status} for {$tenant->name}.");
    }
}
