<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once dirname($root)
    . '/redcms v5.1/includes/addon_adapter_helpers.php';
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

final class RED_Stripe_Subscription_Real_Post_Test_Exchange
    implements RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange
{
    public int $callCount = 0;
    public array $request = [];

    public function __construct(
        private readonly mixed $response,
        private readonly bool $throws = false
    ) {}

    public function exchange(array $wireRequest): array
    {
        $this->callCount++;
        $this->request = $wireRequest;
        if ($this->throws) {
            throw new RuntimeException('sealed_exchange_failure');
        }
        return is_array($this->response) ? $this->response : [];
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
    'id' => 'studio-membership-monthly',
    'productId' => 'studio-membership',
    'variantId' => null,
    'title' => 'Studio membership',
    'summary' => 'Monthly member access.',
    'currency' => 'USD',
    'priceMinor' => 2900,
    'billingPeriod' => 'monthly',
    'state' => 'published',
    'availability' => 'available',
    'buttonLabel' => 'Subscribe monthly',
];
$intent = [
    'intentReference' => 'sint_' . str_repeat('1', 32),
    'intentStateSha256' => str_repeat('2', 64),
    'offerStateSha256' => hash('sha256', json_encode(
        $offer,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    )),
    'status' => 'requested',
];
$policy = [
    'apiVersion' => '2024-09-30.acacia',
    'successUrl' => 'https://shop.example.test/subscription/complete',
    'cancelUrl' => 'https://shop.example.test/subscription',
    'createdAtEpoch' => 1787630400,
    'expiresAtEpoch' => 1787632200,
];
$execution = [
    'planSha256' => str_repeat('3', 64),
    'claimStateSha256' => str_repeat('4', 64),
    'executionStartStateSha256' => str_repeat('5', 64),
];
$session = 'cs_test_SubscriptionRealPost123456';
$projection = [
    'id' => $session,
    'object' => 'checkout.session',
    'url' => 'https://checkout.stripe.com/c/pay/' . $session
        . '#synthetic-real-post',
    'mode' => 'subscription',
    'status' => 'open',
    'payment_status' => 'unpaid',
    'amount_total' => 2900,
    'currency' => 'usd',
    'client_reference_id' => $intent['intentReference'],
    'metadata' => [
        'redcms_intent_state_sha256' => $intent['intentStateSha256'],
        'redcms_offer_state_sha256' => $intent['offerStateSha256'],
    ],
    'livemode' => false,
    'expires_at' => 1787632200,
    'after_expiration' => null,
];
$wireResponse = [
    'statusCode' => 200,
    'headers' => [[
        'name' => 'content-type',
        'value' => 'application/json',
    ], [
        'name' => 'request-id',
        'value' => 'req_SubscriptionRealPost',
    ]],
    'body' => json_encode(
        $projection,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ),
    'tlsVersion' => 'TLSv1.2',
    'redirectCount' => 0,
];

try {
    $source = (string) file_get_contents(
        $root . '/src/StripeSandboxSubscriptionCheckoutRealPostOperation.php'
    );
    $assert(
        !preg_match(
            '/\b(?:curl|mysqli|PDO|getenv|header|setcookie)\b|\$_(?:SERVER|ENV|POST|COOKIE)/',
            $source
        ),
        'operation contains no transport, database, secret, request, or response primitive'
    );
    $assert(
        hash_equals(
            hash_file('sha256', $root
                . '/src/StripeSandboxSubscriptionCheckoutRealPostOperation.php'),
            hash_file('sha256', $root
                . '/package/StripeSandboxSubscriptionCheckoutRealPostOperation.php')
        ),
        'source and package operation are byte-identical'
    );

    $exchange = new RED_Stripe_Subscription_Real_Post_Test_Exchange(
        $wireResponse
    );
    $result =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation::
            execute($intent, $offer, $policy, $execution, $exchange);
    $assert(
        $result['valid'] === true
            && $result['status']
                === 'subscription_checkout_session_created'
            && $result['packageVersion'] === '0.1.13'
            && $result['operation']
                === 'subscription.checkout.create-sandbox-real-post',
        'sealed exchange produces one bounded subscription Checkout outcome'
    );
    $assert(
        $exchange->calls() === 1
            && ($exchange->request['method'] ?? '') === 'POST'
            && ($exchange->request['url'] ?? '')
                === 'https://api.stripe.com/v1/checkout/sessions'
            && preg_match(
                '/\Aredcms-checkout-[a-f0-9]{64}\z/D',
                $exchange->request['headers']['Idempotency-Key'] ?? ''
            ) === 1,
        'one exact idempotent Stripe Sandbox request reaches the sealed exchange'
    );
    $body = $exchange->request['body'] ?? '';
    $assert(
        str_contains($body, 'mode=subscription')
            && str_contains($body, 'submit_type=subscribe')
            && str_contains($body, 'recurring%5D%5Binterval%5D=month')
            && !str_contains($body, 'mode=payment')
            && !str_contains($body, 'customer'),
        'wire body remains recurring, hosted, and customer-data free'
    );
    $assert(
        $result['intentReference'] === $intent['intentReference']
            && $result['checkoutSessionRef'] === $session
            && $result['checkoutUrl'] === $projection['url']
            && $result['transientOnly'] === true
            && $result['persistCheckoutUrl'] === false
            && $result['browserNavigationAuthorized'] === false,
        'validated redirect remains transient and browser-unauthorized'
    );
    $assert(
        $result['networkAccess']
            && $result['providerContact']
            && $result['providerMutation']
            && $result['checkoutCreation']
            && $result['subscriptionCreation']
            && !$result['payment']
            && !$result['webhook']
            && !$result['browserNavigation']
            && !$result['retryAuthorized'],
        'effect report records exactly one Checkout creation attempt'
    );
    $encoded = json_encode(
        $result,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $assert(
        !str_contains($encoded, $wireResponse['body'])
            && !str_contains($encoded, 'Authorization')
            && !str_contains($encoded, 'rk_test_')
            && $result['responseBodyIncluded'] === false
            && $result['credentialValueIncluded'] === false,
        'result excludes credentials and raw provider response material'
    );

    $invalidExecution = $execution;
    $invalidExecution['planSha256'] = 'invalid';
    $unused = new RED_Stripe_Subscription_Real_Post_Test_Exchange(
        $wireResponse
    );
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation::
            execute($intent, $offer, $policy, $invalidExecution, $unused);
    $assert(
        $refused['status'] === 'refused'
            && $unused->calls() === 0
            && !$refused['executionPerformed'],
        'invalid execution evidence is refused before exchange'
    );

    $malformedWire = $wireResponse;
    $malformedWire['body'] = '{';
    $malformed =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation::
            execute(
                $intent,
                $offer,
                $policy,
                $execution,
                new RED_Stripe_Subscription_Real_Post_Test_Exchange(
                    $malformedWire
                )
            );
    $assert(
        $malformed['status'] === 'indeterminate'
            && $malformed['failureStage'] === 'response_decode_failed'
            && !$malformed['retryAuthorized'],
        'malformed post-attempt response is indeterminate with no retry'
    );
    $thrown =
        RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation::
            execute(
                $intent,
                $offer,
                $policy,
                $execution,
                new RED_Stripe_Subscription_Real_Post_Test_Exchange([], true)
            );
    $assert(
        $thrown['status'] === 'indeterminate'
            && $thrown['failureStage'] === 'transport_exchange_failed'
            && $thrown['executionPerformed']
            && !$thrown['retryAuthorized'],
        'throwing exchange remains permanently indeterminate without retry'
    );
    $typed =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'subscription.checkout.create-sandbox-real-post',
                [
                    'contactTarget' =>
                        'stripe-subscription-sandbox-real-post',
                    'intent' => $intent,
                    'offer' => $offer,
                    'policy' => $policy,
                    'execution' => $execution,
                ]
            )
        );
    $assert(
        !$typed->successState()
            && $typed->error() === 'subscription_real_post_secret_refused',
        'typed real operation remains inert without scoped secret availability'
    );

    echo 'Stripe subscription real-POST operation passed '
        . $assertions . " assertions.\n";
    echo "No network, provider, secret resolution, Checkout, payment, or browser action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
