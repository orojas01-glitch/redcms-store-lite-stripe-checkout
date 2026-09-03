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
foreach ([
    'StripeBoundedJsonDecoder.php',
    'StripeSandboxCheckoutWireCodec.php',
    'StripeSandboxCheckoutRealPostExchange.php',
    'StripeSandboxCheckoutRealPostTransport.php',
    'StripeSandboxSubscriptionCheckoutContract.php',
    'StripeSandboxSubscriptionCheckoutRealPostOperation.php',
    'StripeTypedOfflineCheckoutAdapter.php',
] as $file) {
    require_once $root . '/package/' . $file;
}

final class RED_Stripe_Catalog_Price_Test_Exchange
    implements RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange
{
    public int $callCount = 0;
    public array $request = [];

    public function __construct(private readonly array $response) {}

    public function exchange(array $wireRequest): array
    {
        $this->callCount++;
        $this->request = $wireRequest;
        return $this->response;
    }

    public function calls(): int
    {
        return $this->callCount;
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

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
$intent = [
    'intentReference' => 'sint_0123456789abcdef0123456789abcdef',
    'intentStateSha256' => str_repeat('a', 64),
    'offerStateSha256' => hash('sha256', json_encode(
        $offer,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    )),
    'status' => 'requested',
];
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
$execution = [
    'planSha256' => str_repeat('b', 64),
    'claimStateSha256' => str_repeat('c', 64),
    'executionStartStateSha256' => str_repeat('d', 64),
    'providerCatalog' => $catalog,
];
$session = 'cs_test_CatalogPriceOperation123';
$projection = [
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
        'redcms_intent_state_sha256' => $intent['intentStateSha256'],
        'redcms_offer_state_sha256' => $intent['offerStateSha256'],
    ],
    'livemode' => false,
    'expires_at' => $policy['expiresAtEpoch'],
    'after_expiration' => null,
];
$wireResponse = [
    'statusCode' => 200,
    'headers' => [
        ['name' => 'content-type', 'value' => 'application/json'],
        ['name' => 'request-id', 'value' => 'req_CatalogPriceOperation'],
    ],
    'body' => json_encode(
        $projection,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ),
    'tlsVersion' => 'TLSv1.3',
    'redirectCount' => 0,
];

$exchange = new RED_Stripe_Catalog_Price_Test_Exchange($wireResponse);
$result =
    RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation::
        execute($intent, $offer, $policy, $execution, $exchange);
$assert(
    ($result['status'] ?? '') === 'subscription_checkout_session_created',
    'catalog Price operation creates a bounded Session result'
);
$assert($exchange->calls() === 1, 'catalog Price exchange runs exactly once');
$body = $exchange->request['body'] ?? '';
$assert(
    str_contains(
        $body,
        'line_items%5B0%5D%5Bprice%5D=price_1UAIXQPzjg2rInjnX5CypQNL'
    ),
    'operation sends the configured catalog Price ID'
);
$assert(!str_contains($body, 'price_data'), 'operation sends no inline Price');
$transportReflection = new ReflectionClass(
    RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Transport::class
);
$wireValidator = $transportReflection->getMethod('wireRequest');
$assert(
    $wireValidator->invoke(null, $exchange->request) === true,
    'real provider transport accepts the exact catalog Price request'
);
$hybridRequest = $exchange->request;
$hybridRequest['body'] .=
    '&line_items%5B0%5D%5Bprice_data%5D%5Brecurring%5D%5Binterval%5D=month';
$hybridRequest['bodyBytes'] = strlen($hybridRequest['body']);
$hybridRequest['bodySha256'] = hash('sha256', $hybridRequest['body']);
$assert(
    $wireValidator->invoke(null, $hybridRequest) === false,
    'real provider transport refuses a mixed catalog and inline Price request'
);
$assert(
    ($result['checkoutUrl'] ?? '') === $projection['url']
        && ($result['persistCheckoutUrl'] ?? true) === false,
    'catalog Checkout URL remains transient'
);

$foreign = $execution;
$foreign['providerCatalog']['offerId'] = 'foreign-offer';
$unused = new RED_Stripe_Catalog_Price_Test_Exchange($wireResponse);
$refused =
    RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation::
        execute($intent, $offer, $policy, $foreign, $unused);
$assert(
    ($refused['status'] ?? '') === 'refused' && $unused->calls() === 0,
    'foreign catalog mapping is refused before exchange'
);

$typed = RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
    new RED_Addon_Adapter_Request(
        'redcms.store-lite-stripe-checkout/checkout',
        'subscription.checkout.create-sandbox-real-post',
        [
            'contactTarget' => 'stripe-subscription-sandbox-real-post',
            'intent' => $intent,
            'offer' => $offer,
            'policy' => $policy,
            'providerCatalog' => $catalog,
            'execution' => [
                'planSha256' => $execution['planSha256'],
                'claimStateSha256' => $execution['claimStateSha256'],
                'executionStartStateSha256' =>
                    $execution['executionStartStateSha256'],
            ],
        ]
    )
);
$assert(
    !$typed->successState()
        && $typed->error() === 'subscription_real_post_secret_refused',
    'typed catalog operation reaches the existing scoped-secret boundary'
);

echo 'Stripe P3E-18 subscription catalog Price operation passed: '
    . $assertions . " assertions.\n";
