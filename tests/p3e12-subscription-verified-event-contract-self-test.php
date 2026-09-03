<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$configuredCore = getenv('RED_CMS_CORE');
$coreDirectory = is_string($configuredCore) && $configuredCore !== ''
    ? $configuredCore
    : dirname($root) . '/redcms v5.1';
require_once rtrim($coreDirectory, '/') . '/includes/addon_adapter_helpers.php';
require_once $root
    . '/package/StripeSandboxSubscriptionVerifiedEventContract.php';
require_once $root . '/package/StripeTypedOfflineCheckoutAdapter.php';

$assertions = 0;
$assert = static function (
    bool $condition,
    string $message
) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$intentReference = 'sint_' . str_repeat('1', 32);
$offerState = str_repeat('2', 64);
$checkoutReference = 'cs_test_SubscriptionEvent123456';
$subscriptionReference = 'sub_SubscriptionEvent123456';
$event = static function (
    string $eventType,
    string $providerStatus,
    ?string $checkout,
    ?string $subscription,
    ?int $periodEnd
) use ($intentReference, $offerState): array {
    return [
        'verification' => 'verified',
        'replayStatus' => 'unseen',
        'eventRef' => 'evt_SubscriptionEvent123456',
        'eventType' => $eventType,
        'intentReference' => $intentReference,
        'offerStateSha256' => $offerState,
        'checkoutSessionRef' => $checkout,
        'providerSubscriptionRef' => $subscription,
        'providerStatus' => $providerStatus,
        'currentPeriodEndEpoch' => $periodEnd,
        'eventEvidenceSha256' => str_repeat('3', 64),
        'occurredAt' => 1787630500,
        'receivedAt' => 1787630600,
        'livemode' => false,
    ];
};
$pending = [
    'intentReference' => $intentReference,
    'offerStateSha256' => $offerState,
    'subscriptionStatus' => 'pending',
    'entitlementStatus' => 'inactive',
    'providerSubscriptionRefSha256' => null,
    'currentPeriodEndEpoch' => null,
    'checkoutSessionRefSha256' => hash('sha256', $checkoutReference),
];
$providerSha256 = hash('sha256', $subscriptionReference);
$active = [
    'intentReference' => $intentReference,
    'offerStateSha256' => $offerState,
    'subscriptionStatus' => 'active',
    'entitlementStatus' => 'active',
    'providerSubscriptionRefSha256' => $providerSha256,
    'currentPeriodEndEpoch' => 1789000000,
    'checkoutSessionRefSha256' => hash('sha256', $checkoutReference),
];
$pastDue = $active;
$pastDue['subscriptionStatus'] = 'past_due';
$pastDue['entitlementStatus'] = 'revoked';

try {
    $source = (string) file_get_contents(
        $root . '/src/StripeSandboxSubscriptionVerifiedEventContract.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_',
        'socket_', 'PDO', 'mysqli', '$_SERVER', '$_POST', '$_COOKIE',
        'getenv(', 'putenv(', 'shell_exec(', 'exec(', 'sk_test_', 'sk_live_',
        'rk_test_', 'rk_live_', 'whsec_',
    ] as $token) {
        $assert(
            !str_contains($source, $token),
            $token . ' is absent from the pure verified-event contract'
        );
    }
    $assert(
        hash_equals(
            hash_file(
                'sha256',
                $root
                    . '/src/StripeSandboxSubscriptionVerifiedEventContract.php'
            ),
            hash_file(
                'sha256',
                $root
                    . '/package/StripeSandboxSubscriptionVerifiedEventContract.php'
            )
        ),
        'source and adopted package contract are byte-identical'
    );

    $activated =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize(
                $pending,
                $event(
                    'checkout.session.completed',
                    'complete_paid',
                    $checkoutReference,
                    $subscriptionReference,
                    1790308800
                )
            );
    $assert(
        ($activated['valid'] ?? null) === true
            && ($activated['event']['outcome'] ?? '') === 'activated'
            && ($activated['event']['providerSubscriptionRefSha256'] ?? '')
                === $providerSha256
            && ($activated['checkoutSessionRefSha256'] ?? '')
                === hash('sha256', $checkoutReference),
        'completed subscription Checkout activates the matching pending lifecycle'
    );
    $assert(
        !str_contains(
            json_encode(
                $activated,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            $subscriptionReference
        )
            && !str_contains(
                json_encode(
                    $activated,
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                $checkoutReference
            )
            && ($activated['rawProviderReferenceIncluded'] ?? true) === false
            && ($activated['rawCheckoutReferenceIncluded'] ?? true) === false
            && ($activated['customerDataIncluded'] ?? true) === false,
        'normalized activation exposes only hashes and provider-neutral facts'
    );

    $paidFirst =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize(
                $pending,
                $event(
                    'invoice.paid',
                    'paid_active',
                    null,
                    $subscriptionReference,
                    1790308800
                )
            );
    $assert(
        ($paidFirst['valid'] ?? null) === true
            && ($paidFirst['event']['outcome'] ?? '') === 'activated',
        'out-of-order first paid invoice can activate pending access'
    );

    $renewed =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize(
                $active,
                $event(
                    'invoice.paid',
                    'paid_active',
                    null,
                    $subscriptionReference,
                    1791000000
                )
            );
    $assert(
        ($renewed['valid'] ?? null) === true
            && ($renewed['event']['outcome'] ?? '') === 'renewed'
            && ($renewed['event']['currentPeriodEndEpoch'] ?? 0)
                === 1791000000,
        'later paid invoice extends an active matching subscription'
    );

    $failed =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize(
                $active,
                $event(
                    'invoice.payment_failed',
                    'payment_failed',
                    null,
                    $subscriptionReference,
                    1789000000
                )
            );
    $assert(
        ($failed['valid'] ?? null) === true
            && ($failed['event']['outcome'] ?? '') === 'past_due',
        'failed active-subscription invoice revokes access through past-due state'
    );

    $canceled =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize(
                $pastDue,
                $event(
                    'customer.subscription.deleted',
                    'canceled',
                    null,
                    $subscriptionReference,
                    1789000000
                )
            );
    $assert(
        ($canceled['valid'] ?? null) === true
            && ($canceled['event']['outcome'] ?? '') === 'canceled',
        'deleted matching subscription produces a canceled lifecycle event'
    );

    $expired =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize(
                $pending,
                $event(
                    'checkout.session.expired',
                    'expired',
                    $checkoutReference,
                    null,
                    null
                )
            );
    $assert(
        ($expired['valid'] ?? null) === true
            && ($expired['event']['outcome'] ?? '') === 'expired'
            && array_key_exists(
                'providerSubscriptionRefSha256',
                $expired['event'] ?? []
            )
            && $expired['event']['providerSubscriptionRefSha256'] === null,
        'expired uncompleted Checkout closes pending access without provider id'
    );

    $replayedEvent = $event(
        'checkout.session.completed',
        'complete_paid',
        $checkoutReference,
        $subscriptionReference,
        1790308800
    );
    $replayedEvent['replayStatus'] = 'replayed';
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($pending, $replayedEvent)['valid'] === false,
        'provider replay status fails closed before lifecycle projection'
    );

    $liveEvent = $event(
        'checkout.session.completed',
        'complete_paid',
        $checkoutReference,
        $subscriptionReference,
        1790308800
    );
    $liveEvent['livemode'] = true;
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($pending, $liveEvent)['valid'] === false,
        'live-mode event fails the Sandbox-only contract'
    );

    $foreignCheckout = $event(
        'checkout.session.completed',
        'complete_paid',
        'cs_test_ForeignSubscription123456',
        $subscriptionReference,
        1790308800
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($pending, $foreignCheckout)['valid'] === false,
        'foreign Checkout reference cannot activate pending access'
    );

    $foreignSubscription = $event(
        'invoice.paid',
        'paid_active',
        null,
        'sub_ForeignSubscription123456',
        1791000000
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($active, $foreignSubscription)['valid'] === false,
        'foreign provider subscription cannot renew active access'
    );

    $wrongStatus = $event(
        'invoice.paid',
        'payment_failed',
        null,
        $subscriptionReference,
        1791000000
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($active, $wrongStatus)['valid'] === false,
        'event type and provider status must agree exactly'
    );

    $lateEvent = $event(
        'invoice.paid',
        'paid_active',
        null,
        $subscriptionReference,
        1791000000
    );
    $lateEvent['receivedAt'] = $lateEvent['occurredAt'] + 2592001;
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract::
            normalize($active, $lateEvent)['valid'] === false,
        'event outside the bounded thirty-day delivery window is refused'
    );

    $typed =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'subscription.event.normalize-sandbox-verified',
                [
                    'expected' => $pending,
                    'verifiedEvent' => $event(
                        'checkout.session.completed',
                        'complete_paid',
                        $checkoutReference,
                        $subscriptionReference,
                        1790308800
                    ),
                ]
            )
        );
    $assert(
        $typed->successState()
            && ($typed->data()['event']['outcome'] ?? '') === 'activated'
            && $typed->error() === '',
        'typed adapter exposes the pure verified-event operation'
    );
    $refusedTyped =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'subscription.event.normalize-sandbox-verified',
                ['expected' => $pending]
            )
        );
    $assert(
        !$refusedTyped->successState()
            && $refusedTyped->error()
                === 'subscription_verified_event_input_refused',
        'typed operation refuses incomplete input without side effects'
    );

    $manifest = json_decode(
        (string) file_get_contents($root . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $assert(
        ($manifest['version'] ?? null) === '0.1.21'
            && in_array(
                'StripeSandboxSubscriptionVerifiedEventContract.php',
                array_column($manifest['integrity']['files'] ?? [], 'path'),
                true
            )
            && str_contains(
                (string) file_get_contents($root . '/package/addon.php'),
                'StripeSandboxSubscriptionVerifiedEventContract'
            ),
        'adapter 0.1.21 preserves the pure subscription-event contract'
    );

    echo 'Stripe subscription verified-event contract passed '
        . $assertions . " assertions.\n";
    echo "No request parsing, signature verification, secret resolution, network, Stripe contact, webhook route, database, payment, or deployment occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
