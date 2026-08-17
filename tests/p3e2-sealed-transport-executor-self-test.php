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

$assertions = 0;

function red_stripe_p3e2_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e2_checkout(): array
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
                'name' => 'Delivery',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ],
        ],
    ];
}

function red_stripe_p3e2_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
    ];
}

function red_stripe_p3e2_envelope(): array
{
    return [
        'statusCode' => 200,
        'contentType' => 'application/json',
        'bodyBytes' => 2048,
        'bodySha256' => str_repeat('c', 64),
        'requestId' => 'req_AbCdEfGhIjKlMnOp',
        'tlsVersion' => 'TLSv1.3',
        'redirectCount' => 0,
    ];
}

function red_stripe_p3e2_projection(): array
{
    $session = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/' . $session
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
    ];
}

function red_stripe_p3e2_response_transcript(): array
{
    return [
        'outcome' => 'response',
        'code' => null,
        'envelope' => red_stripe_p3e2_envelope(),
        'projection' => red_stripe_p3e2_projection(),
    ];
}

final class RED_Stripe_P3E2_Sealed_Double implements
    RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double
{
    public int $calls = 0;
    public array $plans = [];

    public function __construct(private array $transcript)
    {
    }

    public function exchange(array $requestPlan): array
    {
        $this->calls++;
        $this->plans[] = $requestPlan;
        return $this->transcript;
    }
}

final class RED_Stripe_P3E2_Throwing_Double implements
    RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double
{
    public int $calls = 0;

    public function exchange(array $requestPlan): array
    {
        $this->calls++;
        throw new RuntimeException(
            'synthetic-secret-shaped-message-must-never-escape'
        );
    }
}

try {
    $source = '';
    foreach ([
        'src/StripeSandboxCheckoutSealedTransportDouble.php',
        'src/StripeSandboxCheckoutSealedExecutor.php',
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
        red_stripe_p3e2_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the sealed executor source'
        );
    }
    red_stripe_p3e2_assert(
        count(get_included_files()) === 6,
        'fixture loads only itself and five dependency-free local contracts'
    );

    $checkout = red_stripe_p3e2_checkout();
    $policy = red_stripe_p3e2_policy();
    $transport = new RED_Stripe_P3E2_Sealed_Double(
        red_stripe_p3e2_response_transcript()
    );
    $result =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
            $checkout,
            $policy,
            $transport
        );
    red_stripe_p3e2_assert(
        $result['valid'] === true
            && $result['status'] === 'checkout_ready'
            && ($result['checkout']['checkoutSessionRef'] ?? null)
                === 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
            && str_contains(
                $result['checkout']['checkoutUrl'] ?? '',
                '#fidkdWxOYHwnPyd1blpxYHZxWjA0'
            )
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $result['planSha256']
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $result['responseEvidenceSha256']
            ) === 1
            && $result['transportCode'] === null
            && $result['retryAuthorized'] === false
            && $result['errors'] === [],
        'one sealed response yields only checkout and two evidence hashes'
    );
    red_stripe_p3e2_assert(
        $transport->calls === 1 && count($transport->plans) === 1,
        'executor invokes the sealed transport exactly once'
    );
    red_stripe_p3e2_assert(
        ($transport->plans[0]['environment'] ?? null) === 'sandbox'
            && ($transport->plans[0]['request']['url'] ?? null)
                === 'https://api.stripe.com/v1/checkout/sessions'
            && !array_key_exists(
                'Authorization',
                $transport->plans[0]['request']['headers'] ?? []
            )
            && !preg_match(
                '/\b(?:sk|rk)_(?:test|live)_[A-Za-z0-9]+\b/',
                serialize($transport->plans[0])
            ),
        'sealed double receives the exact non-secret sandbox plan only'
    );
    red_stripe_p3e2_assert(
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
        'success output shape is closed and contains no transport transcript'
    );

    $invalidCheckout = $checkout;
    $invalidCheckout['amountMinor']++;
    $unused = new RED_Stripe_P3E2_Sealed_Double(
        red_stripe_p3e2_response_transcript()
    );
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
            $invalidCheckout,
            $policy,
            $unused
        );
    red_stripe_p3e2_assert(
        $refused === [
            'valid' => false,
            'status' => 'refused',
            'checkout' => null,
            'planSha256' => '',
            'responseEvidenceSha256' => '',
            'transportCode' => null,
            'retryAuthorized' => false,
            'errors' => ['checkout_plan_refused'],
        ] && $unused->calls === 0,
        'invalid order arithmetic is refused before transport invocation'
    );

    $malformedTranscripts = [];
    $case = red_stripe_p3e2_response_transcript();
    $case['extra'] = true;
    $malformedTranscripts[] = $case;
    $case = red_stripe_p3e2_response_transcript();
    $case['outcome'] = 'success';
    $malformedTranscripts[] = $case;
    $case = red_stripe_p3e2_response_transcript();
    $case['code'] = 'provider_5xx';
    $malformedTranscripts[] = $case;
    $case = [
        'outcome' => 'indeterminate',
        'code' => 'unknown_reason',
        'envelope' => null,
        'projection' => null,
    ];
    $malformedTranscripts[] = $case;
    foreach ($malformedTranscripts as $transcript) {
        $double = new RED_Stripe_P3E2_Sealed_Double($transcript);
        $refused =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
                $checkout,
                $policy,
                $double
            );
        red_stripe_p3e2_assert(
            $refused['valid'] === false
                && $refused['status'] === 'refused'
                && $refused['checkout'] === null
                && $refused['responseEvidenceSha256'] === ''
                && $refused['transportCode'] === null
                && $refused['retryAuthorized'] === false
                && $refused['errors'] === ['transport_contract_refused']
                && $double->calls === 1,
            'expanded, inconsistent, or unknown transcript fails closed once'
        );
    }

    $indeterminateResponseCases = [];
    $case = red_stripe_p3e2_response_transcript();
    $case['envelope']['statusCode'] = 500;
    $indeterminateResponseCases[] = [$case, 'provider_5xx'];
    $case = red_stripe_p3e2_response_transcript();
    $case['envelope']['tlsVersion'] = 'TLSv1.1';
    $indeterminateResponseCases[] = [$case, 'response_unusable'];
    $case = red_stripe_p3e2_response_transcript();
    $case['projection']['amount_total']++;
    $indeterminateResponseCases[] = [$case, 'response_unusable'];
    $case = red_stripe_p3e2_response_transcript();
    $case['projection']['livemode'] = true;
    $indeterminateResponseCases[] = [$case, 'response_unusable'];
    foreach ($indeterminateResponseCases as [$transcript, $code]) {
        $double = new RED_Stripe_P3E2_Sealed_Double($transcript);
        $indeterminate =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
                $checkout,
                $policy,
                $double
            );
        red_stripe_p3e2_assert(
            $indeterminate['valid'] === false
                && $indeterminate['status'] === 'indeterminate'
                && $indeterminate['checkout'] === null
                && $indeterminate['responseEvidenceSha256'] === ''
                && $indeterminate['transportCode'] === $code
                && $indeterminate['retryAuthorized'] === false
                && $indeterminate['errors'] === ['transport_indeterminate']
                && $double->calls === 1,
            '5xx or unusable success remains indeterminate without retry'
        );
    }

    $providerRefusal = red_stripe_p3e2_response_transcript();
    $providerRefusal['envelope']['statusCode'] = 400;
    $double = new RED_Stripe_P3E2_Sealed_Double($providerRefusal);
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
            $checkout,
            $policy,
            $double
        );
    red_stripe_p3e2_assert(
        $refused['valid'] === false
            && $refused['status'] === 'refused'
            && $refused['checkout'] === null
            && $refused['responseEvidenceSha256'] === ''
            && $refused['transportCode'] === null
            && $refused['retryAuthorized'] === false
            && $refused['errors'] === ['transport_response_refused']
            && $double->calls === 1,
        'definite provider 4xx is refused once without body interpretation'
    );

    foreach ([
        'connect_timeout',
        'total_timeout',
        'connection_closed',
        'dns_failure',
        'tls_failure',
        'response_too_large',
        'provider_5xx',
        'response_unusable',
    ] as $code) {
        $double = new RED_Stripe_P3E2_Sealed_Double([
            'outcome' => 'indeterminate',
            'code' => $code,
            'envelope' => null,
            'projection' => null,
        ]);
        $indeterminate =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
                $checkout,
                $policy,
                $double
            );
        red_stripe_p3e2_assert(
            $indeterminate['valid'] === false
                && $indeterminate['status'] === 'indeterminate'
                && $indeterminate['checkout'] === null
                && preg_match(
                    '/\A[a-f0-9]{64}\z/D',
                    $indeterminate['planSha256']
                ) === 1
                && $indeterminate['responseEvidenceSha256'] === ''
                && $indeterminate['transportCode'] === $code
                && $indeterminate['retryAuthorized'] === false
                && $indeterminate['errors'] === ['transport_indeterminate']
                && $double->calls === 1,
            $code . ' remains indeterminate with no automatic retry'
        );
    }

    $throwing = new RED_Stripe_P3E2_Throwing_Double();
    $indeterminate =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
            $checkout,
            $policy,
            $throwing
        );
    red_stripe_p3e2_assert(
        $indeterminate['valid'] === false
            && $indeterminate['status'] === 'indeterminate'
            && $indeterminate['checkout'] === null
            && $indeterminate['transportCode'] === 'transport_exception'
            && $indeterminate['retryAuthorized'] === false
            && $indeterminate['errors'] === ['transport_indeterminate']
            && $throwing->calls === 1
            && !str_contains(
                serialize($indeterminate),
                'synthetic-secret-shaped-message'
            ),
        'transport exception is bounded, undisclosed, and never retried'
    );

    echo 'Stripe P3E-2 sealed transport executor passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
