<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportPlanner.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportResponseGate.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutSealedTransportDouble.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutSealedExecutor.php';
require_once $projectDirectory . '/src/StripeBoundedJsonDecoder.php';
require_once $projectDirectory . '/src/StripeSandboxCheckoutWireCodec.php';

$assertions = 0;

function red_stripe_p3e3_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e3_checkout(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'idempotencySha256' => str_repeat('b', 64),
        'lineItems' => [
            [
                'name' => 'Dog scarf - Small / Red',
                'quantity' => 2,
                'unitAmountMinor' => 1999,
                'lineTotalMinor' => 3998,
            ],
            [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ],
        ],
    ];
}

function red_stripe_p3e3_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
    ];
}

function red_stripe_p3e3_response_value(): array
{
    $session = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'after_expiration' => null,
        'amount_subtotal' => 5897,
        'amount_total' => 5897,
        'automatic_tax' => ['enabled' => false, 'status' => null],
        'client_reference_id' =>
            'ord_0123456789abcdef0123456789abcdef',
        'currency' => 'usd',
        'livemode' => false,
        'metadata' => [
            'redcms_order_snapshot_sha256' => str_repeat('a', 64),
            'redcms_idempotency_sha256' => str_repeat('b', 64),
        ],
        'mode' => 'payment',
        'payment_method_types' => ['card'],
        'payment_status' => 'unpaid',
        'status' => 'open',
        'url' => 'https://checkout.stripe.com/c/pay/' . $session
            . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
    ];
}

function red_stripe_p3e3_wire(
    int $statusCode = 200,
    ?string $body = null
): array {
    return [
        'statusCode' => $statusCode,
        'headers' => [
            ['name' => 'content-type', 'value' => 'application/json'],
            ['name' => 'request-id', 'value' => 'req_AbCdEfGhIjKlMnOp'],
            ['name' => 'stripe-version', 'value' => '2024-09-30.acacia'],
        ],
        'body' => $body ?? json_encode(
            red_stripe_p3e3_response_value(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
        'tlsVersion' => 'TLSv1.3',
        'redirectCount' => 0,
    ];
}

final class RED_Stripe_P3E3_Decoded_Double implements
    RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double
{
    public int $calls = 0;

    public function __construct(private array $transcript)
    {
    }

    public function exchange(array $requestPlan): array
    {
        $this->calls++;
        return $this->transcript;
    }
}

try {
    $source = '';
    foreach ([
        'src/StripeBoundedJsonDecoder.php',
        'src/StripeSandboxCheckoutWireCodec.php',
    ] as $relativePath) {
        $source .= (string) file_get_contents(
            $projectDirectory . '/' . $relativePath
        );
    }
    foreach ([
        'curl_', 'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_',
        'socket_', 'PDO', 'mysqli', '$_SERVER', '$_POST', 'getenv(',
        'putenv(', 'shell_exec(', 'exec(', 'usleep(', 'sleep(',
        'sk_test_', 'sk_live_', 'rk_test_',
    ] as $forbiddenToken) {
        red_stripe_p3e3_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the pure wire-codec source'
        );
    }
    red_stripe_p3e3_assert(
        count(get_included_files()) === 8,
        'fixture loads only itself and seven dependency-free local contracts'
    );

    $checkout = red_stripe_p3e3_checkout();
    $policy = red_stripe_p3e3_policy();
    $encoded =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
            $checkout,
            $policy
        );
    red_stripe_p3e3_assert(
        $encoded['valid'] === true
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $encoded['planSha256']
            ) === 1
            && $encoded['errors'] === [],
        'reviewed checkout produces one hashed canonical wire request'
    );
    $request = $encoded['wireRequest'];
    red_stripe_p3e3_assert(
        $request['method'] === 'POST'
            && $request['url']
                === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['headers']['Content-Type']
                === 'application/x-www-form-urlencoded'
            && $request['authorization']['valueIncluded'] === false
            && !array_key_exists('Authorization', $request['headers']),
        'wire request retains endpoint and non-secret authorization boundary'
    );
    red_stripe_p3e3_assert(
        str_starts_with($request['body'], 'mode=payment&ui_mode=hosted&')
            && str_contains(
                $request['body'],
                'line_items%5B0%5D%5Bprice_data%5D%5Bproduct_data%5D'
                    . '%5Bname%5D=Dog+scarf+-+Small+%2F+Red'
            )
            && str_contains(
                $request['body'],
                'metadata%5Bredcms_order_snapshot_sha256%5D='
                    . str_repeat('a', 64)
            )
            && !str_contains($request['body'], "\r")
            && !str_contains($request['body'], "\n"),
        'form order and RFC-1738 percent encoding are deterministic and closed'
    );
    red_stripe_p3e3_assert(
        $request['bodyBytes'] === strlen($request['body'])
            && $request['bodySha256'] === hash('sha256', $request['body'])
            && $request['bodyBytes'] < 65536
            && !preg_match(
                '/\b(?:sk|rk)_(?:test|live)_[A-Za-z0-9]+\b/',
                serialize($request)
            ),
        'encoded bytes are bounded and hashed without any credential value'
    );
    red_stripe_p3e3_assert(
        $encoded ===
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
                $checkout,
                $policy
            ),
        'identical checkout and policy produce identical encoded bytes'
    );
    $invalidCheckout = $checkout;
    $invalidCheckout['amountMinor']++;
    red_stripe_p3e3_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
            $invalidCheckout,
            $policy
        ) === [
            'valid' => false,
            'wireRequest' => null,
            'planSha256' => '',
            'errors' => ['checkout_plan_refused'],
        ],
        'invalid order arithmetic yields no partial encoded request'
    );

    $jsonCases = [
        ['{"a":1,"b":[true,false,null],"c":"caf\\u00e9"}', true],
        ['{"a":1,"a":2}', false],
        ['{"a":1,"\\u0061":2}', false],
        ['{"a":{"nested":1,"nested":2}}', false],
        ['{"a":01}', false],
        ['{"a":1} trailing', false],
        ['{"a":"\\uD800"}', false],
        ['{"a":"bad' . "\n" . 'control"}', false],
        ['[1,2,]', false],
        ['{"a":NaN}', false],
        ['{"a":1e400}', false],
    ];
    foreach ($jsonCases as [$json, $valid]) {
        $decodedJson =
            RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode($json);
        red_stripe_p3e3_assert(
            $valid
                ? $decodedJson['valid'] === true
                    && $decodedJson['errors'] === []
                : $decodedJson === [
                    'valid' => false,
                    'value' => null,
                    'errors' => ['json_invalid'],
                ],
            'strict decoder accepts canonical JSON and uniformly rejects ambiguity'
        );
    }
    $deep = str_repeat('[', 18) . '0' . str_repeat(']', 18);
    red_stripe_p3e3_assert(
        RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode($deep)['valid']
            === false,
        'JSON nesting beyond the closed depth bound is refused'
    );
    $many = '[' . implode(',', array_fill(0, 4097, '0')) . ']';
    red_stripe_p3e3_assert(
        RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode($many)['valid']
            === false,
        'JSON values beyond the closed token bound are refused'
    );
    red_stripe_p3e3_assert(
        RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode(
            "{\"bad\":\"\xC3\x28\"}"
        )['valid'] === false,
        'invalid UTF-8 is refused before parsing'
    );

    $decoded =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
            red_stripe_p3e3_wire()
        );
    red_stripe_p3e3_assert(
        $decoded['valid'] === true
            && $decoded['transcript']['outcome'] === 'response'
            && $decoded['transcript']['code'] === null
            && $decoded['transcript']['projection'] === [
                'id' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/c/pay/'
                    . 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
                    . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
                'mode' => 'payment',
                'status' => 'open',
                'payment_status' => 'unpaid',
                'amount_total' => 5897,
                'currency' => 'usd',
                'client_reference_id' =>
                    'ord_0123456789abcdef0123456789abcdef',
                'metadata' => [
                    'redcms_order_snapshot_sha256' => str_repeat('a', 64),
                    'redcms_idempotency_sha256' => str_repeat('b', 64),
                ],
                'livemode' => false,
            ]
            && $decoded['errors'] === [],
        'realistic extra-field JSON projects only the closed Checkout subset'
    );
    red_stripe_p3e3_assert(
        $decoded['transcript']['envelope']['bodySha256']
            === hash('sha256', red_stripe_p3e3_wire()['body'])
            && $decoded['transcript']['envelope']['bodyBytes']
                === strlen(red_stripe_p3e3_wire()['body'])
            && $decoded['transcript']['envelope']['requestId']
                === 'req_AbCdEfGhIjKlMnOp',
        'wire decoder hashes exact raw bytes and retains bounded evidence only'
    );

    $double = new RED_Stripe_P3E3_Decoded_Double(
        $decoded['transcript']
    );
    $executed =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
            $checkout,
            $policy,
            $double
        );
    red_stripe_p3e3_assert(
        $executed['valid'] === true
            && $executed['status'] === 'checkout_ready'
            && $executed['retryAuthorized'] === false
            && $double->calls === 1,
        'decoded transcript traverses the sealed executor exactly once'
    );

    $ambiguousBodies = [
        '{"id":"first","id":"second"}',
        '{"metadata":{"x":1,"x":2}}',
        '{"id":',
        '{"id":"ok"} trailing',
    ];
    foreach ($ambiguousBodies as $body) {
        $result =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
                red_stripe_p3e3_wire(200, $body)
            );
        red_stripe_p3e3_assert(
            $result === [
                'valid' => true,
                'transcript' => [
                    'outcome' => 'indeterminate',
                    'code' => 'response_unusable',
                    'envelope' => null,
                    'projection' => null,
                ],
                'errors' => [],
            ],
            'duplicate, truncated, or trailing provider JSON is indeterminate'
        );
    }

    $headerCases = [];
    $case = red_stripe_p3e3_wire();
    $case['headers'][] = [
        'name' => 'content-type', 'value' => 'application/json',
    ];
    $headerCases[] = $case;
    $case = red_stripe_p3e3_wire();
    $case['headers'][0]['name'] = 'Content-Type';
    $headerCases[] = $case;
    $case = red_stripe_p3e3_wire();
    $case['headers'][1]['value'] = "req_valid\nforged";
    $headerCases[] = $case;
    $case = red_stripe_p3e3_wire();
    array_splice($case['headers'], 1, 1);
    $headerCases[] = $case;
    foreach ($headerCases as $case) {
        $result =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
                $case
            );
        red_stripe_p3e3_assert(
            ($result['transcript']['outcome'] ?? null) === 'indeterminate'
                && ($result['transcript']['code'] ?? null)
                    === 'response_unusable',
            'duplicate, noncanonical, injected, or missing header is refused'
        );
    }
    $repeatedNoncritical = red_stripe_p3e3_wire();
    $repeatedNoncritical['headers'][] = [
        'name' => 'vary', 'value' => 'Origin',
    ];
    $repeatedNoncritical['headers'][] = [
        'name' => 'vary', 'value' => 'Accept-Encoding',
    ];
    red_stripe_p3e3_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
            $repeatedNoncritical
        )['transcript']['outcome'] === 'response',
        'repeated noncritical response headers are bounded and ignored'
    );

    $indeterminateCases = [];
    $case = red_stripe_p3e3_wire(500, '{}');
    $indeterminateCases[] = [$case, 'provider_5xx'];
    $case = red_stripe_p3e3_wire(302, '{}');
    $indeterminateCases[] = [$case, 'response_unusable'];
    $case = red_stripe_p3e3_wire();
    $case['tlsVersion'] = 'TLSv1.1';
    $indeterminateCases[] = [$case, 'tls_failure'];
    $case = red_stripe_p3e3_wire();
    $case['redirectCount'] = 1;
    $indeterminateCases[] = [$case, 'response_unusable'];
    $case = red_stripe_p3e3_wire(200, str_repeat('x', 262145));
    $indeterminateCases[] = [$case, 'response_too_large'];
    foreach ($indeterminateCases as [$case, $code]) {
        $result =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
                $case
            );
        red_stripe_p3e3_assert(
            ($result['transcript']['outcome'] ?? null) === 'indeterminate'
                && ($result['transcript']['code'] ?? null) === $code
                && array_key_exists('envelope', $result['transcript'])
                && $result['transcript']['envelope'] === null
                && array_key_exists('projection', $result['transcript'])
                && $result['transcript']['projection'] === null,
            $code . ' produces no partial envelope or projection'
        );
    }

    $clientError =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
            red_stripe_p3e3_wire(400, '{"error":{"message":"ignored"}}')
        );
    red_stripe_p3e3_assert(
        $clientError['valid'] === true
            && $clientError['transcript']['outcome'] === 'response'
            && $clientError['transcript']['envelope']['statusCode'] === 400
            && $clientError['transcript']['projection'] === []
            && !str_contains(serialize($clientError), 'ignored'),
        'definite 4xx retains bounded envelope without decoding error body'
    );
    $double = new RED_Stripe_P3E3_Decoded_Double(
        $clientError['transcript']
    );
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
            $checkout,
            $policy,
            $double
        );
    red_stripe_p3e3_assert(
        $refused['valid'] === false
            && $refused['status'] === 'refused'
            && $refused['retryAuthorized'] === false
            && $refused['errors'] === ['transport_response_refused'],
        'decoded 4xx remains definite refusal with no retry authorization'
    );

    $unknown = red_stripe_p3e3_wire();
    $unknown['unknown'] = true;
    red_stripe_p3e3_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
            $unknown
        ) === [
            'valid' => false,
            'transcript' => null,
            'errors' => ['wire_response_invalid'],
        ],
        'expanded caller-owned wire shape is refused before decoding'
    );

    echo 'Stripe P3E-3 bounded wire codec passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
