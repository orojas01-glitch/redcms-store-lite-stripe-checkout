<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory . '/src/StripeVerifiedEventNormalizer.php';
require_once $projectDirectory . '/src/StripeEventReceiptRecordPlanner.php';

$assertions = 0;

function red_stripe_p3c3_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3c3_expected(): array
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

function red_stripe_p3c3_event(
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
        'eventEvidenceSha256' => str_repeat('b', 64),
        'occurredAt' => 1735689590,
        'receivedAt' => 1735689600,
        'livemode' => false,
    ];
}

function red_stripe_p3c3_evidence(): array
{
    return [
        'attemptRecordId' => 42,
        'clientScopeSha256' => str_repeat('c', 64),
        'transportBodySha256' => str_repeat('d', 64),
        'verificationEvidenceSha256' => str_repeat('e', 64),
    ];
}

function red_stripe_p3c3_refusal(
    array $expected,
    array $event,
    array $evidence,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Event_Receipt_Record_Planner::plan(
        $expected,
        $event,
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
    red_stripe_p3c3_assert(
        is_string($identity['status'] ?? null)
            && preg_match(
                '/\Ap3(?:c[3-9]|d[1-9])_[a-z0-9_]+\z/D',
                $identity['status']
            ) === 1,
        'identity preserves the P3C-3 storage contract across later gates'
    );

    $migrationDirectory = $projectDirectory . '/package/migrations';
    $migrations = glob($migrationDirectory . '/*.sql');
    sort($migrations, SORT_STRING);
    red_stripe_p3c3_assert(
        $migrations === [
            $migrationDirectory
                . '/2026-08-16-create-checkout-attempts.sql',
            $migrationDirectory
                . '/2026-08-16-create-event-receipts.sql',
        ],
        'P3C-3 appends exactly one migration after the attempt schema'
    );
    $sql = (string) file_get_contents($migrations[1]);
    red_stripe_p3c3_assert(
        preg_match_all('/\bCREATE\s+TABLE\b/i', $sql) === 1
            && preg_match_all('/;/', $sql) === 1
            && str_contains(
                $sql,
                'RED_Addon_StoreLite_Stripe_Event_Receipts'
            ),
        'migration creates exactly one package-namespaced event table'
    );
    red_stripe_p3c3_assert(
        str_contains(
            $sql,
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        ),
        'event receipt table is explicitly InnoDB'
    );
    foreach ([
        'AttemptRecordID',
        'ClientScopeSHA256',
        'ProviderEnvironment',
        'ProviderEventRef',
        'EventEvidenceSHA256',
        'TransportBodySHA256',
        'VerificationEvidenceSHA256',
        'CheckoutSessionRef',
        'OrderID',
        'OrderSnapshotSHA256',
        'ProviderEventType',
        'ProviderStatus',
        'NormalizedOutcome',
        'AmountMinor',
        'Currency',
        'ReplayStatus',
        'ProcessingStatus',
        'OccurredAt',
        'ReceivedAt',
        'RecordedAt',
    ] as $column) {
        red_stripe_p3c3_assert(
            substr_count($sql, '`' . $column . '`') >= 1,
            $column . ' is present in the bounded event schema'
        );
    }
    red_stripe_p3c3_assert(
        str_contains($sql, 'uq_stripe_event_provider_ref')
            && str_contains($sql, 'uq_stripe_event_evidence'),
        'provider event and evidence relations are unique per client scope'
    );
    red_stripe_p3c3_assert(
        str_contains(
            $sql,
            'REFERENCES `RED_Addon_StoreLite_Stripe_Checkout_Attempts`'
        )
            && str_contains($sql, 'ON DELETE RESTRICT ON UPDATE RESTRICT'),
        'receipt is linked only to the package-owned attempt with no cascade'
    );
    red_stripe_p3c3_assert(
        str_contains($sql, "`ProviderEnvironment` = 'sandbox'")
            && str_contains($sql, "`ReplayStatus` = 'unseen'")
            && str_contains($sql, "`ProcessingStatus` = 'normalized'"),
        'environment, replay, and initial processing facts are constrained'
    );
    foreach ([
        'checkout.session.completed',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'charge.refunded',
        'charge.dispute.created',
        'refund_confirmed',
        'reversal_reported',
    ] as $closedVocabulary) {
        red_stripe_p3c3_assert(
            str_contains($sql, "'" . $closedVocabulary . "'"),
            $closedVocabulary . ' is present in the closed event projection'
        );
    }
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
        red_stripe_p3c3_assert(
            stripos($sql, $forbiddenStorage) === false,
            $forbiddenStorage . ' is absent from event receipt storage'
        );
    }
    red_stripe_p3c3_assert(
        preg_match(
            '/(?:\A|;)\s*(?:INSERT|UPDATE|DELETE|REPLACE)\b/i',
            $sql
        ) !== 1,
        'migration creates schema but writes no receipt row'
    );

    $expected = red_stripe_p3c3_expected();
    $evidence = red_stripe_p3c3_evidence();
    $eventMappings = [
        ['checkout.session.completed', 'complete_paid', 'paid'],
        ['checkout.session.async_payment_failed', 'failed', 'failed'],
        ['checkout.session.expired', 'expired', 'expired'],
        ['charge.refunded', 'refunded', 'refund_confirmed'],
        ['charge.dispute.created', 'disputed', 'reversal_reported'],
    ];
    foreach ($eventMappings as $eventMapping) {
        $event = red_stripe_p3c3_event($eventMapping[0], $eventMapping[1]);
        $plan = RED_CMS_Store_Lite_Stripe_Event_Receipt_Record_Planner::plan(
            $expected,
            $event,
            $evidence
        );
        red_stripe_p3c3_assert(
            $plan['valid'] === true
                && $plan['record']['providerEventType'] === $eventMapping[0]
                && $plan['record']['providerStatus'] === $eventMapping[1]
                && $plan['record']['normalizedOutcome'] === $eventMapping[2]
                && $plan['record']['providerEnvironment'] === 'sandbox'
                && $plan['record']['replayStatus'] === 'unseen'
                && $plan['record']['processingStatus'] === 'normalized'
                && preg_match('/\A[a-f0-9]{64}\z/D', $plan['planSha256'])
                    === 1
                && $plan['errors'] === [],
            $eventMapping[0] . ' produces one deterministic bounded receipt'
        );
    }

    $plan = RED_CMS_Store_Lite_Stripe_Event_Receipt_Record_Planner::plan(
        $expected,
        red_stripe_p3c3_event(),
        $evidence
    );
    red_stripe_p3c3_assert(
        array_keys($plan['record']) === [
            'attemptRecordId',
            'clientScopeSha256',
            'providerEnvironment',
            'providerEventRef',
            'eventEvidenceSha256',
            'transportBodySha256',
            'verificationEvidenceSha256',
            'checkoutSessionRef',
            'orderId',
            'orderSnapshotSha256',
            'providerEventType',
            'providerStatus',
            'normalizedOutcome',
            'amountMinor',
            'currency',
            'replayStatus',
            'processingStatus',
            'occurredAt',
            'receivedAt',
        ],
        'receipt exposes only the exact reviewed storage vocabulary'
    );
    $encodedRecord = json_encode($plan['record'], JSON_THROW_ON_ERROR);
    foreach ([
        'rawBody',
        'stripeSignature',
        'secret',
        'checkoutUrl',
        'customer',
        'providerError',
    ] as $forbiddenRecord) {
        red_stripe_p3c3_assert(
            !str_contains($encodedRecord, $forbiddenRecord),
            $forbiddenRecord . ' is absent from the planned receipt'
        );
    }
    red_stripe_p3c3_assert(
        RED_CMS_Store_Lite_Stripe_Event_Receipt_Record_Planner::plan(
            $expected,
            red_stripe_p3c3_event(),
            $evidence
        ) === $plan,
        'identical verified facts produce an identical receipt and plan hash'
    );

    $evidenceCases = [];
    $case = $evidence;
    $case['extra'] = true;
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['attemptRecordId'] = 0;
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['clientScopeSha256'] = str_repeat('z', 64);
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['transportBodySha256'] = '';
    $evidenceCases[] = $case;
    $case = $evidence;
    $case['verificationEvidenceSha256'] = str_repeat('A', 64);
    $evidenceCases[] = $case;
    foreach ($evidenceCases as $index => $evidenceCase) {
        red_stripe_p3c3_assert(
            red_stripe_p3c3_refusal(
                $expected,
                red_stripe_p3c3_event(),
                $evidenceCase,
                'event_receipt_evidence_invalid'
            ),
            'invalid receipt evidence ' . $index . ' returns no partial record'
        );
    }

    $eventCases = [];
    $case = red_stripe_p3c3_event();
    $case['rawBody'] = '{}';
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event();
    $case['stripeSignature'] = 'forbidden-signature-shaped-input';
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event();
    $case['secret'] = 'forbidden-secret-shaped-input';
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event();
    $case['replayStatus'] = 'replayed';
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event();
    $case['livemode'] = true;
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event();
    $case['amountMinor']++;
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event();
    $case['receivedAt'] = $case['occurredAt'] + 301;
    $eventCases[] = $case;
    $case = red_stripe_p3c3_event('payment_intent.succeeded', 'paid');
    $eventCases[] = $case;
    foreach ($eventCases as $index => $eventCase) {
        red_stripe_p3c3_assert(
            red_stripe_p3c3_refusal(
                $expected,
                $eventCase,
                $evidence,
                'verified_event_refused'
            ),
            'invalid or replayed event ' . $index . ' yields no receipt'
        );
    }

    $source = (string) file_get_contents(
        $projectDirectory . '/src/StripeEventReceiptRecordPlanner.php'
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
        red_stripe_p3c3_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the pure receipt planner'
        );
    }
    red_stripe_p3c3_assert(
        get_included_files() === [
            __FILE__,
            $projectDirectory . '/src/StripeVerifiedEventNormalizer.php',
            $projectDirectory . '/src/StripeEventReceiptRecordPlanner.php',
        ],
        'fixture loads no RED-CMS, Store Lite, SDK, or external dependency'
    );

    echo 'P3C-3 event/replay storage self-test passed: '
        . $assertions
        . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
