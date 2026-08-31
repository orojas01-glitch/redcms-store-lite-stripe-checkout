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

$assert(
    method_exists(
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::class,
        'prepareCatalogPrice'
    ),
    'catalog Price preparation must exist'
);

$intent = [
    'intentReference' => 'sint_0123456789abcdef0123456789abcdef',
    'intentStateSha256' => str_repeat('a', 64),
    'offerStateSha256' => '',
    'status' => 'requested',
];
$offer = [
    'id' => 'ai-assistant-foundation-monthly',
    'productId' => 'ai-assistant-foundation',
    'variantId' => null,
    'title' => 'AI Assistance Foundation',
    'summary' => 'Configured AI messaging assistant.',
    'currency' => 'USD',
    'priceMinor' => 5900,
    'billingPeriod' => 'monthly',
    'state' => 'published',
    'availability' => 'available',
    'buttonLabel' => 'Start sandbox subscription',
];
$intent['offerStateSha256'] = hash('sha256', json_encode(
    $offer,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
));

$policy = [
    'apiVersion' => '2024-09-30.acacia',
    'successUrl' => 'https://demo.red-sphere.com/subscription/complete',
    'cancelUrl' => 'https://demo.red-sphere.com/subscription/cancel',
    'createdAtEpoch' => 1788148800,
    'expiresAtEpoch' => 1788150600,
];
$catalog = [
    'offerId' => 'ai-assistant-foundation-monthly',
    'stripeProductId' => 'prod_VAdppdm2hxfXT7',
    'stripePriceId' => 'price_1UAIXQPzjg2rInjnX5CypQNL',
    'currency' => 'USD',
    'priceMinor' => 5900,
    'billingPeriod' => 'monthly',
    'active' => true,
    'livemode' => false,
];

$prepared =
    RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
        prepareCatalogPrice($intent, $offer, $policy, $catalog);
$assert(
    ($prepared['valid'] ?? null) === true,
    'matching catalog Price accepted: ' . json_encode($prepared)
);
$contract = $prepared['contract'] ?? [];
$body = $contract['request']['body'] ?? '';
$assert(str_contains($body, 'mode=subscription'), 'subscription mode retained');
$assert(
    str_contains(
        $body,
        'line_items%5B0%5D%5Bprice%5D=price_1UAIXQPzjg2rInjnX5CypQNL'
    ),
    'existing Stripe Price ID used'
);
$assert(!str_contains($body, 'price_data'), 'inline Price creation removed');
$assert(
    ($contract['providerCatalog']['stripeProductId'] ?? '')
        === 'prod_VAdppdm2hxfXT7',
    'Stripe Product identity retained as evidence'
);
$assert(
    ($contract['providerCatalog']['stripePriceId'] ?? '')
        === 'price_1UAIXQPzjg2rInjnX5CypQNL',
    'Stripe Price identity retained as evidence'
);
$assert(
    ($contract['currentExecution']['providerCatalogRead'] ?? null) === false,
    'contract does not contact Stripe'
);
$assert(
    ($contract['currentExecution']['providerCatalogMutation'] ?? null) === false,
    'contract cannot mutate Stripe catalog'
);

$session = 'cs_test_CatalogPriceSubscription123';
$accepted =
    RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
        acceptCatalogPrice(
            $intent,
            $offer,
            $policy,
            $catalog,
            [
                'statusCode' => 200,
                'contentType' => 'application/json',
                'bodyBytes' => 2048,
                'bodySha256' => str_repeat('b', 64),
                'requestId' => 'req_CatalogPriceSubscription',
                'tlsVersion' => 'TLSv1.3',
                'redirectCount' => 0,
            ],
            [
                'id' => $session,
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/' . $session,
                'mode' => 'subscription',
                'status' => 'open',
                'payment_status' => 'unpaid',
                'amount_total' => 5900,
                'currency' => 'usd',
                'client_reference_id' => $intent['intentReference'],
                'metadata' => [
                    'redcms_intent_state_sha256' =>
                        $intent['intentStateSha256'],
                    'redcms_offer_state_sha256' =>
                        $intent['offerStateSha256'],
                ],
                'livemode' => false,
                'expires_at' => $policy['expiresAtEpoch'],
                'after_expiration' => null,
            ]
        );
$assert(($accepted['valid'] ?? null) === true, 'catalog Session accepted');
$assert(
    ($accepted['contractSha256'] ?? '') === $prepared['contractSha256'],
    'catalog request identity retained through acceptance'
);

foreach ([
    ['stripePriceId', 'price_live_invalid'],
    ['stripeProductId', 'prod_invalid space'],
    ['offerId', 'foreign-offer'],
    ['priceMinor', 5800],
    ['currency', 'EUR'],
    ['billingPeriod', 'yearly'],
    ['active', false],
    ['livemode', true],
] as [$key, $value]) {
    $changed = $catalog;
    $changed[$key] = $value;
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
            prepareCatalogPrice($intent, $offer, $policy, $changed);
    $assert(
        ($refused['valid'] ?? null) === false,
        'catalog mismatch refused: ' . $key
    );
}

echo 'Stripe P3E-18 subscription catalog Price contract passed: '
    . $assertions . " assertions.\n";
