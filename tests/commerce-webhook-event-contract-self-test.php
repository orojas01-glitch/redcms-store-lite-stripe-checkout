<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__) . '/package';
require_once $packageRoot . '/StripeCommerceWebhookEventContract.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$cartId = 'cart_' . str_repeat('a', 32);
$snapshot = hash('sha256', 'cart-snapshot');
$expected = [
    'cartId' => $cartId,
    'cartSnapshotSha256' => $snapshot,
    'livemode' => false,
];
$event = static function (string $type, array $overrides = []) use ($cartId, $snapshot): array {
    return array_replace([
        'eventId' => 'evt_' . str_repeat('a', 24),
        'eventType' => $type,
        'apiVersion' => '2026-08-26.dahlia',
        'livemode' => false,
        'createdAtEpoch' => 1788395230,
        'receivedAtEpoch' => 1788395231,
        'objectId' => 'obj_' . str_repeat('b', 24),
        'cartId' => $cartId,
        'cartSnapshotSha256' => $snapshot,
        'objectStatus' => 'active',
        'paymentStatus' => 'paid',
        'subscriptionStatus' => 'active',
        'payloadSha256' => hash('sha256', $type),
    ], $overrides);
};

try {
    $supported = RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::supportedEventTypes();
    $assert(count($supported) === 11, 'minimal commerce webhook allowlist has eleven events');
    foreach ([
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'invoice.paid',
        'invoice.payment_failed',
        'invoice.payment_action_required',
        'invoice.finalization_failed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ] as $type) {
        $assert(in_array($type, $supported, true), $type . ' is allowlisted');
    }

    $paid = RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::normalize(
        $expected,
        $event('invoice.paid')
    );
    $assert(($paid['valid'] ?? false) === true, 'paid invoice is accepted');
    $assert($paid['event']['cartEventType'] === 'payment.paid', 'invoice paid is payment authority');
    $assert($paid['event']['paymentAuthoritative'] === true, 'paid invoice is authoritative');

    $completed = RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::normalize(
        $expected,
        $event('checkout.session.completed')
    );
    $assert($completed['event']['cartEventType'] === null, 'success return cannot mark payment paid');
    $assert($completed['event']['paymentAuthoritative'] === false, 'Checkout completion is not payment authority');

    $asyncSucceeded = RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::normalize(
        $expected,
        $event('checkout.session.async_payment_succeeded')
    );
    $assert(
        $asyncSucceeded['event']['cartEventType'] === null,
        'async Checkout success waits for the paid subscription invoice'
    );
    $failed = RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::normalize(
        $expected,
        $event('invoice.payment_failed', [
            'paymentStatus' => 'unpaid',
            'subscriptionStatus' => 'past_due',
        ])
    );
    $assert($failed['event']['cartEventType'] === 'payment.failed', 'invoice failure is visible');
    $deleted = RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::normalize(
        $expected,
        $event('customer.subscription.deleted', [
            'objectStatus' => 'canceled',
            'paymentStatus' => null,
            'subscriptionStatus' => 'canceled',
        ])
    );
    $assert($deleted['event']['subscriptionOutcome'] === 'canceled', 'subscription cancellation is preserved');
    $assert($deleted['event']['cartEventType'] === null, 'cancellation does not rewrite a paid cart');
    $assert(
        preg_match('/\A[0-9a-f]{64}\z/D', $paid['event']['eventEvidenceSha256']) === 1,
        'normalized event has durable evidence'
    );

    $invalid = [];
    $invalid[] = $event('invoice.paid', ['cartId' => 'cart_' . str_repeat('f', 32)]);
    $invalid[] = $event('invoice.paid', ['livemode' => true]);
    $invalid[] = $event('invoice.paid', ['apiVersion' => '2024-09-30.acacia']);
    $invalid[] = $event('charge.succeeded');
    $invalid[] = $event('invoice.paid', ['payloadSha256' => 'bad']);
    foreach ($invalid as $candidate) {
        $assert(
            RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract::normalize(
                $expected,
                $candidate
            )['valid'] === false,
            'foreign cart, mode, API version, type, and payload evidence fail closed'
        );
    }

    echo 'Stripe commerce webhook event contract passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
