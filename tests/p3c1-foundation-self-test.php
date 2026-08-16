<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory . '/src/StripeVerifiedEventNormalizer.php';

$assertions = 0;

function red_stripe_p3c1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3c1_expected_checkout(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'idempotencySha256' => str_repeat('b', 64),
    ];
}

function red_stripe_p3c1_checkout_response(): array
{
    $sessionId = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'id' => $sessionId,
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/' . $sessionId,
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

function red_stripe_p3c1_expected_event(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'checkoutSessionRef' =>
            'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
    ];
}

function red_stripe_p3c1_verified_event(
    string $eventType = 'checkout.session.completed',
    string $providerStatus = 'complete_paid'
): array {
    return [
        'verification' => 'verified',
        'replayStatus' => 'unseen',
        'eventRef' => 'evt_AbCdEfGhIjKlMnOp',
        'eventType' => $eventType,
        'checkoutSessionRef' =>
            'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'amountMinor' => 5897,
        'currency' => 'USD',
        'providerStatus' => $providerStatus,
        'eventEvidenceSha256' => str_repeat('c', 64),
        'occurredAt' => 1735689590,
        'receivedAt' => 1735689600,
        'livemode' => false,
    ];
}

function red_stripe_p3c1_checkout_refusal(
    array $expected,
    array $response,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer::normalize(
        $expected,
        $response
    ) === [
        'valid' => false,
        'checkout' => null,
        'errors' => [$error],
    ];
}

function red_stripe_p3c1_event_refusal(
    array $expected,
    array $event,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer::normalize(
        $expected,
        $event
    ) === [
        'valid' => false,
        'event' => null,
        'errors' => [$error],
    ];
}

try {
    $identityPath = $projectDirectory . '/package/identity.json';
    $identity = json_decode(
        (string) file_get_contents($identityPath),
        true,
        16,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3c1_assert(
        ($identity['id'] ?? null) ===
            'redcms.store-lite-stripe-checkout'
            && ($identity['repository'] ?? null) ===
                'redcms-store-lite-stripe-checkout'
            && is_string($identity['status'] ?? null)
            && preg_match('/\Ap3c[1-9]_[a-z0-9_]+\z/D', $identity['status'])
                === 1,
        'package and repository identities remain exact across later gates'
    );
    red_stripe_p3c1_assert(
        ($identity['futureManifest']['requiredDependency']['id'] ?? null)
            === 'redcms.store-lite'
            && ($identity['futureManifest']['requiredDependency']['version']
                ?? null) === '>=0.1.35 <1.0'
            && ($identity['futureManifest']['adapterId'] ?? null)
                === 'redcms.store-lite-stripe-checkout/checkout'
            && ($identity['futureManifest']['outboundHost'] ?? null)
                === 'api.stripe.com',
        'future identity is bound to current Store Lite and one outbound host'
    );
    foreach ([
        'package/registrar.php',
        'composer.json',
        'vendor',
        'package/vendor',
        'package/secrets',
    ] as $forbiddenPath) {
        red_stripe_p3c1_assert(
            !file_exists($projectDirectory . '/' . $forbiddenPath),
            $forbiddenPath . ' remains absent from the bounded package'
        );
    }

    $source = (string) file_get_contents(
        $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php'
    ) . (string) file_get_contents(
        $projectDirectory . '/src/StripeVerifiedEventNormalizer.php'
    );
    foreach ([
        'curl_',
        'file_get_contents(',
        'PDO',
        'mysqli',
        '$_SERVER',
        '$_POST',
        'getenv(',
        'putenv(',
        'shell_exec(',
        'exec(',
    ] as $forbiddenToken) {
        red_stripe_p3c1_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from pure contract source'
        );
    }
    $included = get_included_files();
    red_stripe_p3c1_assert(
        count($included) === 3
            && $included[0] === __FILE__
            && !array_filter(
                $included,
                static fn (string $path): bool =>
                    str_contains($path, '/redcms v5.1/')
                    || str_contains($path, '/redcms-store-lite/package/')
            ),
        'fixture loads only itself and the two local dependency-free classes'
    );

    $expectedCheckout = red_stripe_p3c1_expected_checkout();
    $checkoutResponse = red_stripe_p3c1_checkout_response();
    $checkout =
        RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer::normalize(
            $expectedCheckout,
            $checkoutResponse
        );
    red_stripe_p3c1_assert(
        $checkout === [
            'valid' => true,
            'checkout' => [
                'checkoutSessionRef' =>
                    'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
                'checkoutUrl' =>
                    'https://checkout.stripe.com/c/pay/'
                    . 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
            ],
            'errors' => [],
        ],
        'reviewed sandbox response emits only the P2 opaque reference and URL'
    );
    red_stripe_p3c1_assert(
        array_keys($checkout['checkout']) === [
            'checkoutSessionRef',
            'checkoutUrl',
        ],
        'checkout output contains no customer, state, secret, or provider body'
    );

    $checkoutCases = [];
    $case = $checkoutResponse;
    $case['customer'] = 'cus_forbidden';
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_invalid'];
    $case = $checkoutResponse;
    $case['secret'] = 'forbidden-secret-shaped-input';
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_invalid'];
    $case = $checkoutResponse;
    $case['livemode'] = true;
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_invalid'];
    $case = $checkoutResponse;
    $case['amount_total']++;
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_mismatch'];
    $case = $checkoutResponse;
    $case['currency'] = 'cop';
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_mismatch'];
    $case = $checkoutResponse;
    $case['client_reference_id'] =
        'ord_ffffffffffffffffffffffffffffffff';
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_mismatch'];
    $case = $checkoutResponse;
    $case['metadata']['redcms_order_snapshot_sha256'] = str_repeat('d', 64);
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_response_mismatch'];
    $case = $checkoutResponse;
    $case['metadata']['browser_amount'] = '1';
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_metadata_invalid'];
    $case = $checkoutResponse;
    $case['url'] .= '?redirect=https://example.test';
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_url_invalid'];
    $case = $checkoutResponse;
    $case['url'] = str_replace(
        'checkout.stripe.com',
        'example.test',
        $case['url']
    );
    $checkoutCases[] = [$expectedCheckout, $case, 'checkout_url_invalid'];
    $badExpected = $expectedCheckout;
    $badExpected['paymentMethod'] = 'paypal';
    $checkoutCases[] = [$badExpected, $checkoutResponse, 'expected_checkout_invalid'];
    foreach ($checkoutCases as $index => $checkoutCase) {
        red_stripe_p3c1_assert(
            red_stripe_p3c1_checkout_refusal(...$checkoutCase),
            'checkout refusal ' . $index . ' returns no partial value'
        );
    }

    $expectedEvent = red_stripe_p3c1_expected_event();
    $eventMappings = [
        ['checkout.session.completed', 'complete_paid', 'paid'],
        ['checkout.session.async_payment_failed', 'failed', 'failed'],
        ['checkout.session.expired', 'expired', 'expired'],
        ['charge.refunded', 'refunded', 'refund_confirmed'],
        ['charge.dispute.created', 'disputed', 'reversal_reported'],
    ];
    foreach ($eventMappings as $eventMapping) {
        $normalized =
            RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer::normalize(
                $expectedEvent,
                red_stripe_p3c1_verified_event(
                    $eventMapping[0],
                    $eventMapping[1]
                )
            );
        red_stripe_p3c1_assert(
            $normalized['valid'] === true
                && $normalized['event']['outcome'] === $eventMapping[2]
                && array_keys($normalized['event']) === [
                    'verification',
                    'replayStatus',
                    'outcome',
                    'orderId',
                    'orderSnapshotSha256',
                    'paymentMethod',
                    'amountMinor',
                    'currency',
                    'eventEvidenceSha256',
                    'occurredAt',
                ],
            $eventMapping[0] . ' emits exact Store Lite P3B vocabulary'
        );
    }

    $eventCases = [];
    $case = red_stripe_p3c1_verified_event();
    $case['rawBody'] = '{}';
    $eventCases[] = [$expectedEvent, $case, 'verified_event_invalid'];
    $case = red_stripe_p3c1_verified_event();
    $case['stripeSignature'] = 't=1,v1=forbidden';
    $eventCases[] = [$expectedEvent, $case, 'verified_event_invalid'];
    $case = red_stripe_p3c1_verified_event();
    $case['customer'] = 'cus_forbidden';
    $eventCases[] = [$expectedEvent, $case, 'verified_event_invalid'];
    $case = red_stripe_p3c1_verified_event();
    $case['verification'] = 'unverified';
    $eventCases[] = [$expectedEvent, $case, 'verified_event_invalid'];
    $case = red_stripe_p3c1_verified_event();
    $case['replayStatus'] = 'replayed';
    $eventCases[] = [$expectedEvent, $case, 'event_replayed'];
    $case = red_stripe_p3c1_verified_event();
    $case['livemode'] = true;
    $eventCases[] = [$expectedEvent, $case, 'verified_event_invalid'];
    $case = red_stripe_p3c1_verified_event();
    $case['providerStatus'] = 'unpaid';
    $eventCases[] = [$expectedEvent, $case, 'event_outcome_invalid'];
    $case = red_stripe_p3c1_verified_event('payment_intent.succeeded', 'paid');
    $eventCases[] = [$expectedEvent, $case, 'event_outcome_invalid'];
    foreach ([
        'orderId' => 'ord_ffffffffffffffffffffffffffffffff',
        'orderSnapshotSha256' => str_repeat('d', 64),
        'amountMinor' => 1,
        'currency' => 'COP',
        'checkoutSessionRef' =>
            'cs_test_ZyXwVuTsRqPoNmLkJiHgFeDc',
    ] as $key => $value) {
        $case = red_stripe_p3c1_verified_event();
        $case[$key] = $value;
        $eventCases[] = [$expectedEvent, $case, 'verified_event_mismatch'];
    }
    $case = red_stripe_p3c1_verified_event();
    $case['occurredAt'] = $case['receivedAt'] - 301;
    $eventCases[] = [
        $expectedEvent,
        $case,
        'verified_event_timestamp_invalid',
    ];
    $badExpected = $expectedEvent;
    $badExpected['paymentMethod'] = 'paypal';
    $eventCases[] = [$badExpected, red_stripe_p3c1_verified_event(), 'expected_event_invalid'];
    foreach ($eventCases as $index => $eventCase) {
        red_stripe_p3c1_assert(
            red_stripe_p3c1_event_refusal(...$eventCase),
            'event refusal ' . $index . ' returns no partial value'
        );
    }

    echo 'P3C-1 foundation self-test passed: '
        . $assertions
        . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
