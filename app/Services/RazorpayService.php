<?php

namespace App\Services;

use App\Models\Plan;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    private Api $client;

    public function __construct()
    {
        $this->client = new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret'),
        );
    }

    /**
     * Create a Razorpay Subscription for a plan.
     *
     * Returns ['subscription_id' => '...', 'short_url' => '...']
     * The short_url is Razorpay's hosted payment page — redirect the user there.
     */
    public function createSubscription(Plan $plan, string $email, string $name, ?string $phone = null, ?string $callbackUrl = null): array
    {
        $payload = [
            'plan_id'         => $plan->razorpay_plan_id,
            'total_count'     => 120,   // 10 years; effectively open-ended
            'quantity'        => 1,
            'customer_notify' => 1,
            'notify_info'     => array_filter([
                'notify_phone' => $phone,
                'notify_email' => $email,
            ]),
            'notes' => [
                'plan_slug' => $plan->slug,
                'customer'  => $name,
            ],
        ];

        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $subscription = $this->client->subscription->create($payload);

        return [
            'subscription_id' => $subscription->id,
            'short_url'       => $subscription->short_url,
        ];
    }

    /**
     * Cancel a Razorpay Subscription at the end of the current billing cycle.
     */
    public function cancelSubscription(string $razorpaySubscriptionId): void
    {
        $this->client->subscription->fetch($razorpaySubscriptionId)->cancel([
            'cancel_at_cycle_end' => 1,
        ]);
    }

    /**
     * Verify the webhook signature sent by Razorpay.
     *
     * Throws SignatureVerificationError on mismatch — catch it in the webhook controller.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): void
    {
        $this->client->utility->verifyWebhookSignature(
            $rawBody,
            $signature,
            config('services.razorpay.webhook_secret'),
        );
    }

    /**
     * Verify the signature Razorpay appends to the subscription callback URL.
     *
     * Razorpay passes razorpay_payment_id, razorpay_subscription_id, razorpay_signature
     * as query params when redirecting back after mandate authorization.
     * Throws SignatureVerificationError on mismatch.
     */
    public function verifyPaymentSignature(string $paymentId, string $subscriptionId, string $signature): void
    {
        $this->client->utility->verifyPaymentSignature([
            'razorpay_payment_id'      => $paymentId,
            'razorpay_subscription_id' => $subscriptionId,
            'razorpay_signature'       => $signature,
        ]);
    }

    /**
     * Fetch a subscription from Razorpay (used to reconcile status on demand).
     */
    public function fetchSubscription(string $razorpaySubscriptionId): object
    {
        return $this->client->subscription->fetch($razorpaySubscriptionId);
    }
}
