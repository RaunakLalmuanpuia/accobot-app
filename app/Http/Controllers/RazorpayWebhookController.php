<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\RazorpayPayment;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, RazorpayService $razorpay): Response
    {
        $rawBody  = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        // Reject requests with invalid signatures immediately
        try {
            $razorpay->verifyWebhookSignature($rawBody, $signature);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay webhook signature mismatch', ['ip' => $request->ip()]);
            return response('Invalid signature', 400);
        }

        $payload = json_decode($rawBody, true);
        $event   = $payload['event'] ?? null;

        Log::info('Razorpay webhook received', ['event' => $event]);

        // Extract the subscription entity from the payload
        $entity = $payload['payload']['subscription']['entity'] ?? null;

        if (! $entity || ! isset($entity['id'])) {
            return response('Missing subscription entity', 200);
        }

        $subscription = Subscription::with('plan')
            ->where('razorpay_subscription_id', $entity['id'])
            ->first();

        if (! $subscription) {
            // Check if it belongs to an addon subscription instead
            $addon = SubscriptionAddon::where('razorpay_subscription_id', $entity['id'])->first();

            if ($addon) {
                $this->handleAddonEvent($event, $addon);
                return response('OK', 200);
            }

            Log::warning('Razorpay webhook: subscription not found', ['razorpay_id' => $entity['id']]);
            // Return 200 so Razorpay doesn't keep retrying for unknown IDs
            return response('Subscription not found', 200);
        }

        match ($event) {
            'subscription.activated'  => $this->handleActivated($subscription, $entity, $payload),
            'subscription.charged'    => $this->handleCharged($subscription, $entity, $payload),
            'subscription.halted'     => $this->handleHalted($subscription, $entity),
            'subscription.cancelled'  => $this->handleCancelled($subscription, $entity),
            'subscription.completed'  => $this->handleCompleted($subscription, $entity),
            default                   => null,
        };

        return response('OK', 200);
    }

    private function handleActivated(Subscription $subscription, array $entity, array $payload): void
    {
        $subscription->update([
            'status'               => 'active',
            'razorpay_customer_id' => $entity['customer_id'] ?? $subscription->razorpay_customer_id,
            'current_period_start' => $this->toCarbon($entity['current_start'] ?? null),
            'current_period_end'   => $this->toCarbon($entity['current_end'] ?? null),
            'trial_ends_at'        => null,
            'cancelled_at'         => null,
        ]);

        $this->recordPayment($subscription, 'subscription.activated', $payload);

        AuditEvent::log(
            'subscription.started',
            ['plan' => $subscription->plan->slug, 'source' => 'webhook'],
            null,
            $subscription->tenant_id,
            'system',
        );
    }

    private function handleCharged(Subscription $subscription, array $entity, array $payload): void
    {
        $subscription->update([
            'status'               => 'active',
            'current_period_start' => $this->toCarbon($entity['current_start'] ?? null),
            'current_period_end'   => $this->toCarbon($entity['current_end'] ?? null),
        ]);

        $this->recordPayment($subscription, 'subscription.charged', $payload);

        AuditEvent::log(
            'subscription.renewed',
            ['plan' => $subscription->plan->slug],
            null,
            $subscription->tenant_id,
            'system',
        );
    }

    private function handleHalted(Subscription $subscription, array $entity): void
    {
        $subscription->update(['status' => 'halted']);

        AuditEvent::log(
            'subscription.halted',
            ['plan' => $subscription->plan->slug],
            null,
            $subscription->tenant_id,
            'system',
        );
    }

    private function handleCancelled(Subscription $subscription, array $entity): void
    {
        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        AuditEvent::log(
            'subscription.cancelled',
            ['plan' => $subscription->plan->slug],
            null,
            $subscription->tenant_id,
            'system',
        );
    }

    private function handleCompleted(Subscription $subscription, array $entity): void
    {
        $subscription->update(['status' => 'expired']);

        AuditEvent::log(
            'subscription.expired',
            ['plan' => $subscription->plan->slug],
            null,
            $subscription->tenant_id,
            'system',
        );
    }

    private function recordPayment(Subscription $subscription, string $eventType, array $payload): void
    {
        $payment = $payload['payload']['payment']['entity'] ?? null;

        if (! $payment || empty($payment['id'])) {
            return;
        }

        RazorpayPayment::updateOrCreate(
            ['razorpay_payment_id' => $payment['id']],
            [
                'tenant_id'                => $subscription->tenant_id,
                'subscription_id'          => $subscription->id,
                'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
                'event_type'               => $eventType,
                'amount'                   => $payment['amount'] ?? 0,
                'currency'                 => $payment['currency'] ?? 'INR',
                'status'                   => $payment['status'] ?? 'unknown',
                'method'                   => $payment['method'] ?? null,
                'email'                    => $payment['email'] ?? null,
                'contact'                  => $payment['contact'] ?? null,
                'razorpay_created_at'      => isset($payment['created_at'])
                    ? $this->toCarbon($payment['created_at'])
                    : null,
                'payload'                  => $payload,
            ],
        );
    }

    private function handleAddonEvent(string $event, SubscriptionAddon $addon): void
    {
        match ($event) {
            'subscription.activated',
            'subscription.charged'   => $addon->update(['status' => 'active']),
            'subscription.halted'    => $addon->update(['status' => 'halted']),
            'subscription.cancelled',
            'subscription.completed' => $addon->update(['status' => 'cancelled']),
            default                  => null,
        };
    }

    private function toCarbon(?int $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }
}
