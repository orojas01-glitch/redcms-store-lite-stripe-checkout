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

$assertions = 0;

function red_stripe_p3e1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e1_checkout(): array
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
                'name' => 'Dog scarf - Medium / Blue',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ],
        ],
    ];
}

function red_stripe_p3e1_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
    ];
}

function red_stripe_p3e1_expected(): array
{
    $checkout = red_stripe_p3e1_checkout();
    unset($checkout['lineItems']);
    return $checkout;
}

function red_stripe_p3e1_projection(): array
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

function red_stripe_p3e1_envelope(): array
{
    return [
        'statusCode' => 200,
        'contentType' => 'application/json; charset=utf-8',
        'bodyBytes' => 2048,
        'bodySha256' => str_repeat('c', 64),
        'requestId' => 'req_AbCdEfGhIjKlMnOp',
        'tlsVersion' => 'TLSv1.3',
        'redirectCount' => 0,
    ];
}

function red_stripe_p3e1_plan_refusal(
    array $checkout,
    array $policy,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::plan(
        $checkout,
        $policy
    ) === [
        'valid' => false,
        'plan' => null,
        'planSha256' => '',
        'errors' => [$error],
    ];
}

function red_stripe_p3e1_response_refusal(
    array $expected,
    array $envelope,
    array $projection,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate::accept(
        $expected,
        $envelope,
        $projection
    ) === [
        'valid' => false,
        'checkout' => null,
        'responseEvidenceSha256' => '',
        'errors' => [$error],
    ];
}

try {
    $source = '';
    foreach ([
        'src/StripeSandboxCheckoutTransportPlanner.php',
        'src/StripeSandboxCheckoutTransportResponseGate.php',
    ] as $relativePath) {
        $source .= (string) file_get_contents(
            $projectDirectory . '/' . $relativePath
        );
    }
    foreach ([
        'curl_', 'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_',
        'PDO', 'mysqli', '$_SERVER', '$_POST', 'getenv(', 'putenv(',
        'shell_exec(', 'exec(', 'sk_test_', 'sk_live_', 'rk_test_',
    ] as $forbiddenToken) {
        red_stripe_p3e1_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the non-executing source'
        );
    }
    red_stripe_p3e1_assert(
        count(get_included_files()) === 4,
        'fixture loads only itself and three dependency-free local classes'
    );

    $checkout = red_stripe_p3e1_checkout();
    $policy = red_stripe_p3e1_policy();
    $planned =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::plan(
            $checkout,
            $policy
        );
    red_stripe_p3e1_assert(
        ($planned['valid'] ?? null) === true
            && is_array($planned['plan'] ?? null)
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $planned['planSha256'] ?? ''
            ) === 1
            && ($planned['errors'] ?? null) === [],
        'closed server-derived checkout projection produces one hashed plan'
    );
    $plan = $planned['plan'];
    red_stripe_p3e1_assert(
        $plan['environment'] === 'sandbox'
            && $plan['request']['method'] === 'POST'
            && $plan['request']['url']
                === 'https://api.stripe.com/v1/checkout/sessions'
            && $plan['request']['headers'] === [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Stripe-Version' => '2024-09-30.acacia',
                'Idempotency-Key' =>
                    'redcms-checkout-' . str_repeat('b', 64),
            ],
        'plan fixes HTTPS v1 Checkout, form encoding, version, and idempotency'
    );
    red_stripe_p3e1_assert(
        $plan['request']['authorization'] === [
            'scheme' => 'http-basic-username',
            'secretSettingKey' => 'stripe.secret-key',
            'valueIncluded' => false,
        ]
            && !array_key_exists(
                'Authorization',
                $plan['request']['headers']
            )
            && !preg_match(
                '/\b(?:sk|rk)_(?:test|live)_[A-Za-z0-9]+\b/',
                serialize($plan)
            ),
        'plan names the package-local secret boundary without carrying a key'
    );
    red_stripe_p3e1_assert(
        $plan['transport'] === [
            'minimumTlsVersion' => '1.2',
            'verifyPeer' => true,
            'verifyHost' => true,
            'followRedirects' => false,
            'maximumRedirects' => 0,
            'connectTimeoutMilliseconds' => 5000,
            'totalTimeoutMilliseconds' => 15000,
            'maximumResponseBytes' => 262144,
        ],
        'plan fixes peer and host verification, no redirects, and hard bounds'
    );
    red_stripe_p3e1_assert(
        $plan['request']['form']['mode'] === 'payment'
            && $plan['request']['form']['ui_mode'] === 'hosted'
            && $plan['request']['form']['client_reference_id']
                === $checkout['orderId']
            && $plan['request']['form'][
                'metadata[redcms_order_snapshot_sha256]'
            ] === str_repeat('a', 64)
            && $plan['request']['form'][
                'metadata[redcms_idempotency_sha256]'
            ] === str_repeat('b', 64),
        'form carries only immutable reconciliation identities and hosted mode'
    );
    red_stripe_p3e1_assert(
        $plan['request']['form'][
            'line_items[0][price_data][product_data][name]'
        ] === 'Dog scarf - Small / Red'
            && $plan['request']['form'][
                'line_items[0][price_data][currency]'
            ] === 'usd'
            && $plan['request']['form'][
                'line_items[0][price_data][unit_amount]'
            ] === '1999'
            && $plan['request']['form']['line_items[0][quantity]'] === '2',
        'variant name, unit amount, currency, and quantity remain exact'
    );
    red_stripe_p3e1_assert(
        $planned ===
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::plan(
                $checkout,
                $policy
            ),
        'identical immutable input produces an identical request plan and hash'
    );

    $invalidCheckouts = [];
    $case = $checkout;
    $case['amountMinor']++;
    $invalidCheckouts[] = $case;
    $case = $checkout;
    $case['lineItems'][0]['lineTotalMinor']++;
    $invalidCheckouts[] = $case;
    $case = $checkout;
    $case['lineItems'][0]['name'] = "forged\nheader";
    $invalidCheckouts[] = $case;
    $case = $checkout;
    $case['lineItems'][0]['browserPrice'] = 1;
    $invalidCheckouts[] = $case;
    $case = $checkout;
    $case['paymentMethod'] = 'pay_on_receipt';
    $invalidCheckouts[] = $case;
    $case = $checkout;
    $case['secret'] = 'forbidden';
    $invalidCheckouts[] = $case;
    foreach ($invalidCheckouts as $invalidCheckout) {
        red_stripe_p3e1_assert(
            red_stripe_p3e1_plan_refusal(
                $invalidCheckout,
                $policy,
                'checkout_projection_invalid'
            ),
            'forged, mismatched, unsupported, or expanded checkout fails closed'
        );
    }

    $invalidPolicies = [];
    $case = $policy;
    $case['apiVersion'] = 'latest';
    $invalidPolicies[] = $case;
    $case = $policy;
    $case['successUrl'] = 'http://shop.example.test/complete';
    $invalidPolicies[] = $case;
    $case = $policy;
    $case['cancelUrl'] = 'https://evil.example.test/checkout';
    $invalidPolicies[] = $case;
    $case = $policy;
    $case['successUrl'] .= '?paid=true';
    $invalidPolicies[] = $case;
    $case = $policy;
    $case['timeout'] = 999999;
    $invalidPolicies[] = $case;
    foreach ($invalidPolicies as $invalidPolicy) {
        red_stripe_p3e1_assert(
            red_stripe_p3e1_plan_refusal(
                $checkout,
                $invalidPolicy,
                'transport_policy_invalid'
            ),
            'unpinned, insecure, cross-origin, query, or expanded policy fails'
        );
    }

    $expected = red_stripe_p3e1_expected();
    $envelope = red_stripe_p3e1_envelope();
    $projection = red_stripe_p3e1_projection();
    $accepted =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate::accept(
            $expected,
            $envelope,
            $projection
        );
    red_stripe_p3e1_assert(
        ($accepted['valid'] ?? null) === true
            && ($accepted['checkout']['checkoutSessionRef'] ?? null)
                === 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
            && str_contains(
                $accepted['checkout']['checkoutUrl'] ?? '',
                '#fidkdWxOYHwnPyd1blpxYHZxWjA0'
            )
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $accepted['responseEvidenceSha256'] ?? ''
            ) === 1
            && ($accepted['errors'] ?? null) === [],
        'bounded TLS JSON envelope and exact sandbox projection are accepted'
    );
    red_stripe_p3e1_assert(
        array_keys($accepted) === [
            'valid', 'checkout', 'responseEvidenceSha256', 'errors',
        ]
            && !str_contains(serialize($accepted), 'req_')
            && !str_contains(serialize($accepted), str_repeat('c', 64)),
        'accepted result emits only checkout navigation and hashed evidence'
    );

    foreach ([
        ['statusCode', 500],
        ['contentType', 'text/html'],
        ['bodyBytes', 262145],
        ['bodySha256', str_repeat('C', 64)],
        ['requestId', "req_bad\nheader"],
        ['tlsVersion', 'TLSv1.1'],
        ['redirectCount', 1],
    ] as [$key, $value]) {
        $case = $envelope;
        $case[$key] = $value;
        red_stripe_p3e1_assert(
            red_stripe_p3e1_response_refusal(
                $expected,
                $case,
                $projection,
                'transport_response_refused'
            ),
            'bad status, type, size, hash, request id, TLS, or redirect fails'
        );
    }
    $case = $projection;
    $case['livemode'] = true;
    red_stripe_p3e1_assert(
        red_stripe_p3e1_response_refusal(
            $expected,
            $envelope,
            $case,
            'checkout_projection_refused'
        ),
        'live-mode provider projection remains categorically refused'
    );
    $case = $projection;
    $case['amount_total']++;
    red_stripe_p3e1_assert(
        red_stripe_p3e1_response_refusal(
            $expected,
            $envelope,
            $case,
            'checkout_projection_refused'
        ),
        'provider amount mismatch returns no partial checkout or evidence'
    );
    $case = $projection;
    $case['url'] .= "\nforged";
    red_stripe_p3e1_assert(
        red_stripe_p3e1_response_refusal(
            $expected,
            $envelope,
            $case,
            'checkout_projection_refused'
        ),
        'control characters in hosted URL fragment remain refused'
    );

    echo 'Stripe P3E-1 sandbox transport contract passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
