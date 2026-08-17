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
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutSyntheticByteTransport.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutDecodedTranscriptDouble.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutSyntheticTransportAdapter.php';

$assertions = 0;

function red_stripe_p3e4_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e4_checkout(): array
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

function red_stripe_p3e4_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
    ];
}

function red_stripe_p3e4_response_value(): array
{
    $session = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'amount_total' => 5897,
        'client_reference_id' =>
            'ord_0123456789abcdef0123456789abcdef',
        'currency' => 'usd',
        'livemode' => false,
        'metadata' => [
            'redcms_order_snapshot_sha256' => str_repeat('a', 64),
            'redcms_idempotency_sha256' => str_repeat('b', 64),
        ],
        'mode' => 'payment',
        'payment_status' => 'unpaid',
        'status' => 'open',
        'url' => 'https://checkout.stripe.com/c/pay/' . $session
            . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
    ];
}

function red_stripe_p3e4_wire_response(
    int $statusCode = 200,
    ?string $body = null
): array {
    return [
        'statusCode' => $statusCode,
        'headers' => [
            ['name' => 'content-type', 'value' => 'application/json'],
            ['name' => 'request-id', 'value' => 'req_AbCdEfGhIjKlMnOp'],
        ],
        'body' => $body ?? json_encode(
            red_stripe_p3e4_response_value(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
        'tlsVersion' => 'TLSv1.3',
        'redirectCount' => 0,
    ];
}

function red_stripe_p3e4_secret(): string
{
    return 'synthetic_p3e4_' . bin2hex(random_bytes(32));
}

function red_stripe_p3e4_authorization_commitment(string $secret): string
{
    $authorization = 'Basic ' . base64_encode($secret . ':');
    $commitment = hash('sha256', $authorization);
    $authorization = '';
    return $commitment;
}

function red_stripe_p3e4_transport(
    string $secret,
    array $wireResponse,
    ?string $commitment = null
): RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Byte_Transport {
    return new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Byte_Transport(
        $secret,
        $commitment ?? red_stripe_p3e4_authorization_commitment($secret),
        $wireResponse
    );
}

try {
    $source = '';
    foreach ([
        'src/StripeSandboxCheckoutSyntheticByteTransport.php',
        'src/StripeSandboxCheckoutDecodedTranscriptDouble.php',
        'src/StripeSandboxCheckoutSyntheticTransportAdapter.php',
    ] as $relativePath) {
        $source .= (string) file_get_contents(
            $projectDirectory . '/' . $relativePath
        );
    }
    foreach ([
        'curl_', 'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_',
        'socket_', 'PDO', 'mysqli', '$_SERVER', '$_POST', 'getenv(',
        'putenv(', 'shell_exec(', 'exec(', 'usleep(', 'sleep(',
        'error_log(', 'print_r(', 'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e4_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from synthetic transport source'
        );
    }
    red_stripe_p3e4_assert(
        count(get_included_files()) === 11,
        'fixture loads only itself and ten dependency-free local contracts'
    );
    red_stripe_p3e4_assert(
        str_contains($source, '#[SensitiveParameter]')
            && str_contains($source, "base64_encode(\$secret . ':')")
            && str_contains($source, 'hash_equals('),
        'transport marks input sensitive and privately verifies Basic auth'
    );

    $checkout = red_stripe_p3e4_checkout();
    $policy = red_stripe_p3e4_policy();
    $secret = red_stripe_p3e4_secret();
    $commitment = red_stripe_p3e4_authorization_commitment($secret);
    $transport = red_stripe_p3e4_transport(
        $secret,
        red_stripe_p3e4_wire_response(),
        $commitment
    );
    $result =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Transport_Adapter::execute(
            $checkout,
            $policy,
            $transport
        );
    red_stripe_p3e4_assert(
        $result['valid'] === true
            && $result['status'] === 'checkout_ready'
            && ($result['checkout']['checkoutSessionRef'] ?? null)
                === 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $result['planSha256']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $result['responseEvidenceSha256']
            ) === 1
            && $result['retryAuthorized'] === false
            && $result['errors'] === [],
        'synthetic credential and bytes traverse codec and executor once'
    );
    red_stripe_p3e4_assert(
        $transport->calls() === 1,
        'concrete synthetic byte transport is invoked exactly once'
    );
    $transportState = serialize($transport);
    $resultState = serialize($result);
    red_stripe_p3e4_assert(
        !str_contains($transportState, $secret)
            && !str_contains($transportState, $commitment)
            && !str_contains($transportState, 'Basic ')
            && !str_contains($resultState, $secret)
            && !str_contains($resultState, $commitment)
            && !str_contains($resultState, 'Basic '),
        'secret, authorization commitment, and Basic value are discarded'
    );
    red_stripe_p3e4_assert(
        array_keys($result) === [
            'valid',
            'status',
            'checkout',
            'planSha256',
            'responseEvidenceSha256',
            'transportCode',
            'retryAuthorized',
            'errors',
        ],
        'adapter returns only the closed P3E-2 result shape'
    );

    $invalidCheckout = $checkout;
    $invalidCheckout['amountMinor']++;
    $unusedSecret = red_stripe_p3e4_secret();
    $unused = red_stripe_p3e4_transport(
        $unusedSecret,
        red_stripe_p3e4_wire_response()
    );
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Transport_Adapter::execute(
            $invalidCheckout,
            $policy,
            $unused
        );
    red_stripe_p3e4_assert(
        $refused === [
            'valid' => false,
            'status' => 'refused',
            'checkout' => null,
            'planSha256' => '',
            'responseEvidenceSha256' => '',
            'transportCode' => null,
            'retryAuthorized' => false,
            'errors' => ['checkout_plan_refused'],
        ] && $unused->calls() === 0,
        'invalid checkout is refused before credential or transport use'
    );

    $mismatchSecret = red_stripe_p3e4_secret();
    $mismatchCommitment = str_repeat('d', 64);
    $mismatch = red_stripe_p3e4_transport(
        $mismatchSecret,
        red_stripe_p3e4_wire_response(),
        $mismatchCommitment
    );
    $indeterminate =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Transport_Adapter::execute(
            $checkout,
            $policy,
            $mismatch
        );
    red_stripe_p3e4_assert(
        $indeterminate['valid'] === false
            && $indeterminate['status'] === 'indeterminate'
            && $indeterminate['checkout'] === null
            && $indeterminate['transportCode'] === 'transport_exception'
            && $indeterminate['retryAuthorized'] === false
            && $indeterminate['errors'] === ['transport_indeterminate']
            && $mismatch->calls() === 1,
        'authorization mismatch is contained and never retried'
    );
    $mismatchState = serialize($mismatch);
    red_stripe_p3e4_assert(
        !str_contains($mismatchState, $mismatchSecret)
            && !str_contains($mismatchState, $mismatchCommitment),
        'failed authorization also discards secret and commitment'
    );

    $encoded =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
            $checkout,
            $policy
        );
    $forgedRequest = $encoded['wireRequest'];
    $forgedRequest['headers']['Authorization'] = 'forbidden';
    $forgedSecret = red_stripe_p3e4_secret();
    $forged = red_stripe_p3e4_transport(
        $forgedSecret,
        red_stripe_p3e4_wire_response()
    );
    try {
        $forged->exchange($forgedRequest);
        $forgedRefused = false;
    } catch (Throwable $throwable) {
        $forgedRefused = true;
    }
    red_stripe_p3e4_assert(
        $forgedRefused
            && $forged->calls() === 1
            && !str_contains(serialize($forged), $forgedSecret),
        'caller-supplied Authorization is refused and credential discarded'
    );
    try {
        $forged->exchange($encoded['wireRequest']);
        $reused = true;
    } catch (Throwable $throwable) {
        $reused = false;
    }
    red_stripe_p3e4_assert(
        $reused === false && $forged->calls() === 2,
        'used transport cannot be manually invoked a second time'
    );

    foreach ([
        [400, '{"error":{"message":"not returned"}}', 'refused', null],
        [500, '{}', 'indeterminate', 'provider_5xx'],
        [200, '{"id":', 'indeterminate', 'response_unusable'],
    ] as [$statusCode, $body, $status, $transportCode]) {
        $caseSecret = red_stripe_p3e4_secret();
        $caseTransport = red_stripe_p3e4_transport(
            $caseSecret,
            red_stripe_p3e4_wire_response($statusCode, $body)
        );
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Transport_Adapter::execute(
                $checkout,
                $policy,
                $caseTransport
            );
        red_stripe_p3e4_assert(
            $case['valid'] === false
                && $case['status'] === $status
                && $case['checkout'] === null
                && $case['responseEvidenceSha256'] === ''
                && $case['transportCode'] === $transportCode
                && $case['retryAuthorized'] === false
                && $caseTransport->calls() === 1
                && !str_contains(serialize($case), 'not returned')
                && !str_contains(serialize($caseTransport), $caseSecret),
            $statusCode . ' response stays closed, redacted, and non-retrying'
        );
    }

    $badWireSecret = red_stripe_p3e4_secret();
    $badWireTransport = red_stripe_p3e4_transport(
        $badWireSecret,
        ['unknown' => true]
    );
    $badWire =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Transport_Adapter::execute(
            $checkout,
            $policy,
            $badWireTransport
        );
    red_stripe_p3e4_assert(
        $badWire['valid'] === false
            && $badWire['status'] === 'indeterminate'
            && $badWire['transportCode'] === 'response_unusable'
            && $badWire['retryAuthorized'] === false
            && !str_contains(serialize($badWireTransport), $badWireSecret),
        'expanded raw response shape is indeterminate without disclosure'
    );

    foreach ([
        ['', str_repeat('e', 64)],
        [str_repeat('x', 31), str_repeat('e', 64)],
        ['sk_test_' . str_repeat('x', 64), str_repeat('e', 64)],
        ['sk_live_' . str_repeat('x', 64), str_repeat('e', 64)],
        [str_repeat('x', 32), 'not-a-sha256'],
        [str_repeat('x', 32) . ':', str_repeat('e', 64)],
    ] as [$badSecret, $badCommitment]) {
        try {
            new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Byte_Transport(
                $badSecret,
                $badCommitment,
                red_stripe_p3e4_wire_response()
            );
            $configurationRefused = false;
        } catch (Throwable $throwable) {
            $configurationRefused = true;
        }
        red_stripe_p3e4_assert(
            $configurationRefused,
            'malformed synthetic credential configuration is refused'
        );
    }

    echo 'Stripe P3E-4 synthetic credential transport passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
