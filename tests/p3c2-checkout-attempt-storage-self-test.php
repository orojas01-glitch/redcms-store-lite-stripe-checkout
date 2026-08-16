<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory . '/src/StripeCheckoutAttemptRecordPlanner.php';

$assertions = 0;

function red_stripe_p3c2_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3c2_expected(): array
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

function red_stripe_p3c2_response(): array
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

function red_stripe_p3c2_evidence(): array
{
    return [
        'clientScopeSha256' => str_repeat('c', 64),
        'responseEvidenceSha256' => str_repeat('d', 64),
        'createdAt' => 1735689600,
        'expiresAt' => 1735693200,
    ];
}

function red_stripe_p3c2_refusal(
    array $expected,
    array $response,
    array $evidence,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Checkout_Attempt_Record_Planner::plan(
        $expected,
        $response,
        $evidence
    ) === [
        'valid' => false,
        'record' => null,
        'planSha256' => '',
        'errors' => [$error],
    ];
}

try {
    $identity = json_decode(
        (string) file_get_contents($projectDirectory . '/package/identity.json'),
        true,
        16,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3c2_assert(
        is_string($identity['status'] ?? null)
            && preg_match(
                '/\Ap3c[2-9]_[a-z0-9_]+\z/D',
                $identity['status']
            ) === 1,
        'identity advances only through named P3C-2 and later gates'
    );
    red_stripe_p3c2_assert(
        in_array('migration-execution', $identity['exclusions'] ?? [], true)
            && in_array(
                'database-connection',
                $identity['exclusions'] ?? [],
                true
            )
            && in_array(
                'database-writer',
                $identity['exclusions'] ?? [],
                true
            ),
        'identity separates migration design from execution and persistence'
    );

    $migrationDirectory = $projectDirectory . '/package/migrations';
    $migrations = glob($migrationDirectory . '/*.sql');
    red_stripe_p3c2_assert(
        is_array($migrations)
            && in_array(
                $migrationDirectory
                    . '/2026-08-16-create-checkout-attempts.sql',
                $migrations,
                true
            ),
        'the immutable P3C-2 checkout-attempt migration remains present'
    );
    $sql = (string) file_get_contents($migrations[0]);
    red_stripe_p3c2_assert(
        preg_match_all('/\bCREATE\s+TABLE\b/i', $sql) === 1
            && preg_match_all('/;/', $sql) === 1
            && str_contains(
                $sql,
                'RED_Addon_StoreLite_Stripe_Checkout_Attempts'
            ),
        'migration creates exactly one package-namespaced table'
    );
    red_stripe_p3c2_assert(
        str_contains(
            $sql,
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        ),
        'attempt table is explicitly InnoDB with the reviewed charset'
    );
    foreach ([
        'ClientScopeSHA256',
        'OrderID',
        'OrderSnapshotSHA256',
        'IdempotencySHA256',
        'CheckoutSessionRef',
        'AmountMinor',
        'Currency',
        'AttemptStatus',
        'ResponseEvidenceSHA256',
        'CreatedAt',
        'ExpiresAt',
        'RecordedAt',
    ] as $column) {
        red_stripe_p3c2_assert(
            substr_count($sql, '`' . $column . '`') >= 1,
            $column . ' is present in the bounded attempt schema'
        );
    }
    red_stripe_p3c2_assert(
        str_contains($sql, 'uq_stripe_attempt_idempotency')
            && str_contains($sql, 'uq_stripe_attempt_session')
            && str_contains($sql, 'idx_stripe_attempt_order'),
        'schema enforces idempotency, session uniqueness, and order lookup'
    );
    red_stripe_p3c2_assert(
        str_contains($sql, "`AttemptStatus` = 'created'")
            && str_contains($sql, '`ExpiresAt` <= `CreatedAt` + 86400'),
        'initial state and maximum lifetime are storage constrained'
    );
    foreach ([
        'rawbody',
        'payload',
        'signature',
        'secret',
        'checkouturl',
        'providererror',
        'errormessage',
        'customer',
        'email',
        'phone',
        'address',
        'cardnumber',
        'securitycode',
        'walletdetail',
        'bankaccount',
        'accesstoken',
        'browserquery',
    ] as $forbiddenStorage) {
        red_stripe_p3c2_assert(
            stripos($sql, $forbiddenStorage) === false,
            $forbiddenStorage . ' is absent from adapter storage'
        );
    }
    red_stripe_p3c2_assert(
        preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql) !== 1
            && stripos($sql, 'REFERENCES') === false,
        'migration writes no row and creates no cross-package foreign key'
    );

    $expected = red_stripe_p3c2_expected();
    $response = red_stripe_p3c2_response();
    $evidence = red_stripe_p3c2_evidence();
    $plan =
        RED_CMS_Store_Lite_Stripe_Checkout_Attempt_Record_Planner::plan(
            $expected,
            $response,
            $evidence
        );
    $expectedRecord = [
        'clientScopeSha256' => str_repeat('c', 64),
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'idempotencySha256' => str_repeat('b', 64),
        'checkoutSessionRef' =>
            'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'attemptStatus' => 'created',
        'responseEvidenceSha256' => str_repeat('d', 64),
        'createdAt' => 1735689600,
        'expiresAt' => 1735693200,
    ];
    red_stripe_p3c2_assert(
        $plan['valid'] === true
            && $plan['record'] === $expectedRecord
            && preg_match('/\A[a-f0-9]{64}\z/D', $plan['planSha256']) === 1
            && $plan['errors'] === [],
        'planner returns only the exact deterministic attempt record'
    );
    red_stripe_p3c2_assert(
        !array_key_exists('checkoutUrl', $plan['record'])
            && !str_contains(
                json_encode($plan['record'], JSON_THROW_ON_ERROR),
                'checkout.stripe.com'
            ),
        'transient hosted URL is dropped before persistence planning'
    );
    red_stripe_p3c2_assert(
        RED_CMS_Store_Lite_Stripe_Checkout_Attempt_Record_Planner::plan(
            $expected,
            $response,
            $evidence
        ) === $plan,
        'identical immutable inputs produce an identical plan and hash'
    );

    $evidenceCases = [];
    $case = $evidence;
    $case['extra'] = true;
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['clientScopeSha256'] = str_repeat('z', 64);
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['responseEvidenceSha256'] = '';
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['createdAt'] = 0;
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['expiresAt'] = $case['createdAt'];
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['expiresAt'] = $case['createdAt'] + 86401;
    $evidenceCases[] = $case;
    foreach ($evidenceCases as $index => $evidenceCase) {
        red_stripe_p3c2_assert(
            red_stripe_p3c2_refusal(
                $expected,
                $response,
                $evidenceCase,
                'attempt_evidence_invalid'
            ),
            'invalid attempt evidence ' . $index . ' returns no partial record'
        );
    }

    $responseCases = [];
    $case = $response;
    $case['checkout_url'] = $case['url'];
    $responseCases[] = $case;
    $case = $response;
    $case['amount_total']++;
    $responseCases[] = $case;
    $case = $response;
    $case['livemode'] = true;
    $responseCases[] = $case;
    $case = $response;
    $case['url'] .= '?browser=1';
    $responseCases[] = $case;
    foreach ($responseCases as $index => $responseCase) {
        red_stripe_p3c2_assert(
            red_stripe_p3c2_refusal(
                $expected,
                $responseCase,
                $evidence,
                'checkout_response_refused'
            ),
            'invalid checkout response ' . $index . ' yields no attempt record'
        );
    }

    $source = (string) file_get_contents(
        $projectDirectory . '/src/StripeCheckoutAttemptRecordPlanner.php'
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
        red_stripe_p3c2_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the pure attempt planner'
        );
    }
    red_stripe_p3c2_assert(
        get_included_files() === [
            __FILE__,
            $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php',
            $projectDirectory . '/src/StripeCheckoutAttemptRecordPlanner.php',
        ],
        'fixture loads no RED-CMS, Store Lite, SDK, or external dependency'
    );

    echo 'P3C-2 checkout-attempt storage self-test passed: '
        . $assertions
        . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
