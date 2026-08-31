<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/src/StripeSandboxSubscriptionCheckoutContract.php';
$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};
$offer = static function (string $period = 'monthly'): array {
    return [
        'id' => 'studio-membership-' . $period,
        'productId' => 'studio-membership',
        'variantId' => null,
        'title' => 'Studio membership',
        'summary' => 'Member access with recurring renewal.',
        'currency' => 'USD',
        'priceMinor' => $period === 'monthly' ? 2900 : 29000,
        'billingPeriod' => $period,
        'state' => 'published',
        'availability' => 'available',
        'buttonLabel' => $period === 'monthly'
            ? 'Subscribe monthly'
            : 'Subscribe yearly',
    ];
};
$intent = static function (array $currentOffer): array {
    return [
        'intentReference' => 'sint_' . str_repeat('1', 32),
        'intentStateSha256' => str_repeat('2', 64),
        'offerStateSha256' => hash('sha256', json_encode(
            $currentOffer,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        )),
        'status' => 'requested',
    ];
};
$policy = static fn (): array => [
    'apiVersion' => '2024-09-30.acacia',
    'successUrl' =>
        'https://shop.example.test/subscription/stripe-complete',
    'cancelUrl' => 'https://shop.example.test/subscription',
    'createdAtEpoch' => 1787630400,
    'expiresAtEpoch' => 1787632200,
];
$projection = static function (
    array $currentIntent,
    array $currentOffer
): array {
    $session = 'cs_test_SubscriptionAbCdEfGhIjKl';
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/' . $session
            . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
        'mode' => 'subscription',
        'status' => 'open',
        'payment_status' => 'unpaid',
        'amount_total' => $currentOffer['priceMinor'],
        'currency' => strtolower($currentOffer['currency']),
        'client_reference_id' => $currentIntent['intentReference'],
        'metadata' => [
            'redcms_intent_state_sha256' =>
                $currentIntent['intentStateSha256'],
            'redcms_offer_state_sha256' =>
                $currentIntent['offerStateSha256'],
        ],
        'livemode' => false,
        'expires_at' => 1787632200,
        'after_expiration' => null,
    ];
};
$envelope = static fn (): array => [
    'statusCode' => 200,
    'contentType' => 'application/json',
    'bodyBytes' => 2048,
    'bodySha256' => str_repeat('3', 64),
    'requestId' => 'req_SubscriptionAbCdEfGh',
    'tlsVersion' => 'TLSv1.3',
    'redirectCount' => 0,
];

try {
    $source = (string) file_get_contents(
        $root . '/src/StripeSandboxSubscriptionCheckoutContract.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_',
        'socket_', 'PDO', 'mysqli', '$_SERVER', '$_POST', '$_COOKIE',
        'getenv(', 'putenv(', 'shell_exec(', 'exec(', 'sk_test_', 'sk_live_',
        'rk_test_', 'rk_live_', 'whsec_',
    ] as $token) {
        $assert(
            !str_contains($source, $token),
            $token . ' is absent from the pure subscription source'
        );
    }

    $monthlyOffer = $offer();
    $monthlyIntent = $intent($monthlyOffer);
    $prepared =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            prepare($monthlyIntent, $monthlyOffer, $policy());
    $assert(
        ($prepared['valid'] ?? null) === true
            && is_array($prepared['contract'] ?? null)
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $prepared['contractSha256'] ?? ''
            ) === 1
            && ($prepared['errors'] ?? null) === [],
        'published monthly intent produces one hashed subscription contract'
    );
    $contract = $prepared['contract'];
    $assert(
        $contract['packageId'] === 'redcms.store-lite-stripe-checkout'
            && $contract['contractVersion'] === 'subscription-checkout-v1'
            && $contract['operation']
                === 'subscription.checkout.prepare-sandbox'
            && $contract['sourceStoreLiteVersion'] === '0.1.48',
        'contract binds the separate adapter and Store Lite source gate'
    );
    $assert(
        $contract['request']['method'] === 'POST'
            && $contract['request']['url']
                === 'https://api.stripe.com/v1/checkout/sessions'
            && $contract['request']['authorization']['valueIncluded'] === false
            && $contract['request']['bodyBytes']
                === strlen($contract['request']['body'])
            && $contract['request']['bodySha256']
                === hash('sha256', $contract['request']['body']),
        'request is canonical, bounded, hashed, and credential free'
    );
    $body = $contract['request']['body'];
    $assert(
        str_starts_with(
            $body,
            'mode=subscription&ui_mode=hosted&submit_type=subscribe'
        )
            && str_contains(
                $body,
                'recurring%5D%5Binterval%5D=month'
            )
            && str_contains($body, 'unit_amount%5D=2900')
            && str_contains($body, 'quantity%5D=1')
            && str_contains(
                $body,
                'subscription_data%5Bmetadata%5D'
            )
            && str_contains(
                $body,
                'redcms_intent_reference%5D=sint_'
            ),
        'monthly offer maps to one fixed recurring Stripe line and metadata'
    );
    $assert(
        !str_contains($body, 'customer')
            && !str_contains($body, 'email')
            && !str_contains($body, 'phone')
            && !str_contains($body, 'trial')
            && !str_contains($body, 'discount')
            && !str_contains($body, 'tax'),
        'request cannot carry browser customer, trial, discount, or tax claims'
    );
    $assert(
        $contract['redirectPolicy'] === [
            'providerOrigin' => 'https://checkout.stripe.com',
            'navigationMode' => 'location.assign',
            'transientOnly' => true,
            'persistCheckoutUrl' => false,
            'cacheControl' => 'no-store',
            'authorizationRequired' => true,
            'browserNavigationAuthorized' => false,
        ]
            && !array_filter($contract['currentExecution']),
        'redirect stays transient and all current external effects stay false'
    );

    $yearlyOffer = $offer('yearly');
    $yearly =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            prepare($intent($yearlyOffer), $yearlyOffer, $policy());
    $assert(
        ($yearly['valid'] ?? null) === true
            && str_contains(
                $yearly['contract']['request']['body'],
                'recurring%5D%5Binterval%5D=year'
            )
            && str_contains(
                $yearly['contract']['request']['body'],
                'unit_amount%5D=29000'
            ),
        'yearly offer maps to one yearly recurring Stripe line'
    );

    $accepted =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            accept(
                $monthlyIntent,
                $monthlyOffer,
                $policy(),
                $envelope(),
                $projection($monthlyIntent, $monthlyOffer)
            );
    $assert(
        ($accepted['valid'] ?? null) === true
            && ($accepted['errors'] ?? null) === []
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $accepted['responseEvidenceSha256'] ?? ''
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $accepted['resultSha256'] ?? ''
            ) === 1,
        'synthetic Stripe response becomes one bounded handoff result'
    );
    $assert(
        $accepted['handoff']['intentReference']
            === $monthlyIntent['intentReference']
            && str_starts_with(
                $accepted['handoff']['checkoutUrl'],
                'https://checkout.stripe.com/c/pay/cs_test_'
            )
            && $accepted['handoff']['navigationMode'] === 'location.assign'
            && $accepted['handoff']['transientOnly'] === true
            && $accepted['handoff']['persistCheckoutUrl'] === false
            && $accepted['handoff']['authorizationRequired'] === true
            && $accepted['handoff']['browserNavigationAuthorized'] === false,
        'validated Session URL is transient and still requires core authority'
    );

    $invalidOffer = $monthlyOffer;
    $invalidOffer['state'] = 'draft';
    $invalidIntent = $intent($invalidOffer);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            prepare($invalidIntent, $invalidOffer, $policy())['valid'] === false,
        'draft offer cannot enter subscription Checkout'
    );
    $staleIntent = $monthlyIntent;
    $staleIntent['offerStateSha256'] = str_repeat('f', 64);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            prepare($staleIntent, $monthlyOffer, $policy())['valid'] === false,
        'stale offer evidence cannot enter subscription Checkout'
    );
    $badPolicy = $policy();
    $badPolicy['expiresAtEpoch']++;
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            prepare($monthlyIntent, $monthlyOffer, $badPolicy)['valid'] === false,
        'noncanonical expiry cannot enter subscription Checkout'
    );
    $badProjection = $projection($monthlyIntent, $monthlyOffer);
    $badProjection['mode'] = 'payment';
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            accept(
                $monthlyIntent,
                $monthlyOffer,
                $policy(),
                $envelope(),
                $badProjection
            )['valid'] === false,
        'one-time response cannot become a subscription redirect'
    );
    $badProjection = $projection($monthlyIntent, $monthlyOffer);
    $badProjection['url'] = 'https://evil.example.test/c/pay/'
        . $badProjection['id'];
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            accept(
                $monthlyIntent,
                $monthlyOffer,
                $policy(),
                $envelope(),
                $badProjection
            )['valid'] === false,
        'foreign redirect origin is refused'
    );
    $badProjection = $projection($monthlyIntent, $monthlyOffer);
    $badProjection['livemode'] = true;
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            accept(
                $monthlyIntent,
                $monthlyOffer,
                $policy(),
                $envelope(),
                $badProjection
            )['valid'] === false,
        'live-mode response is refused'
    );

    $manifest = json_decode(
        (string) file_get_contents($root . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $assert(
        ($manifest['version'] ?? null) === '0.1.20'
            && ($manifest['dependencies']['required'][0]['version'] ?? null)
                === '>=0.1.48 <1.0'
            && in_array(
                'StripeSandboxSubscriptionCheckoutContract.php',
                array_column($manifest['integrity']['files'] ?? [], 'path'),
                true
            )
            && str_contains(
                (string) file_get_contents($root . '/package/addon.php'),
                'StripeSandboxSubscriptionCheckoutContract'
            )
            && hash_equals(
                hash_file(
                    'sha256',
                    $root . '/src/StripeSandboxSubscriptionCheckoutContract.php'
                ),
                hash_file(
                    'sha256',
                    $root . '/package/StripeSandboxSubscriptionCheckoutContract.php'
                )
            ),
        'adapter 0.1.20 preserves the exact source with Store Lite 0.1.48'
    );

    require_once dirname($root) . '/redcms v5.1/includes/addon_adapter_helpers.php';
    require_once $root . '/package/StripeTypedOfflineCheckoutAdapter.php';
    $preparedResult =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'subscription.checkout.prepare-sandbox-offline',
                [
                    'contactTarget' =>
                        'stripe-subscription-sandbox-offline',
                    'intent' => $monthlyIntent,
                    'offer' => $monthlyOffer,
                    'policy' => $policy(),
                ]
            )
        );
    $assert(
        $preparedResult->successState()
            && ($preparedResult->data()['valid'] ?? null) === true
            && $preparedResult->error() === '',
        'typed adapter exposes the offline subscription preparation operation'
    );
    $acceptedResult =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'subscription.checkout.accept-sandbox-synthetic',
                [
                    'contactTarget' =>
                        'stripe-subscription-sandbox-synthetic-response',
                    'intent' => $monthlyIntent,
                    'offer' => $monthlyOffer,
                    'policy' => $policy(),
                    'envelope' => $envelope(),
                    'projection' =>
                        $projection($monthlyIntent, $monthlyOffer),
                ]
            )
        );
    $assert(
        $acceptedResult->successState()
            && ($acceptedResult->data()['valid'] ?? null) === true
            && ($acceptedResult->data()
                ['browserNavigationAuthorized'] ?? null) === false
            && $acceptedResult->error() === '',
        'typed adapter returns only the still-unauthorized transient handoff'
    );

    echo 'Stripe subscription Checkout source contract passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
