<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/package/StripeSubscriptionEventReceiptPlanner.php';
$assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void {
    $assertions++;
    if (!$ok) throw new RuntimeException('Assertion failed: ' . $message);
};
$verified = [
    'eventRefSha256' => str_repeat('1', 64),
    'rawBodySha256' => str_repeat('2', 64),
    'signatureEvidenceSha256' => str_repeat('3', 64),
    'eventType' => 'invoice.paid',
    'signedAt' => 1787630500,
    'receivedAt' => 1787630550,
];
try {
    $claim = RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::claim($verified);
    $assert($claim['valid'] && $claim['record']['receiptStatus'] === 'verified'
        && $claim['record']['intentReference'] === null,
        'verified envelope creates one hash-only claim');
    $assert(preg_match('/\A[a-f0-9]{64}\z/D', $claim['planSha256']) === 1
        && !$claim['rawBodyIncluded'] && !$claim['signatureIncluded']
        && !$claim['secretIncluded'],
        'claim exposes deterministic hashes and no sensitive material');
    $completion = [
        'status' => 'applied',
        'intentReference' => 'sint_' . str_repeat('4', 32),
        'eventEvidenceSha256' => str_repeat('5', 64),
        'lifecycleResultSha256' => str_repeat('6', 64),
        'completedAtEpoch' => 1787630600,
    ];
    $applied = RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::complete($claim, $completion);
    $assert($applied['valid'] && $applied['record']['receiptStatus'] === 'applied',
        'claim completes once as applied');
    $refusedCompletion = array_replace($completion, ['status' => 'refused']);
    $refused = RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::complete($claim, $refusedCompletion);
    $assert($refused['valid'] && $refused['record']['receiptStatus'] === 'refused',
        'claim may terminate refused');
    foreach ([
        array_replace($verified, ['eventType' => 'customer.created']),
        array_replace($verified, ['receivedAt' => 1787630801]),
        array_replace($verified, ['eventRefSha256' => str_repeat('x', 64)]),
        array_merge($verified, ['rawBody' => '{}']),
    ] as $bad) {
        $assert(!RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::claim($bad)['valid'],
            'invalid claim is refused');
    }
    foreach ([
        array_replace($completion, ['status' => 'pending']),
        array_replace($completion, ['intentReference' => 'bad']),
        array_replace($completion, ['completedAtEpoch' => 1787630549]),
    ] as $bad) {
        $assert(!RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::complete($claim, $bad)['valid'],
            'invalid completion is refused');
    }
    $assert(!RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner::complete($applied, $completion)['valid'],
        'completed receipt cannot complete again');
    $sql = (string) file_get_contents(
        $root . '/package/migrations/2026-08-29-create-subscription-event-receipts.sql'
    );
    $assert(str_contains($sql, 'RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts')
        && str_contains($sql, 'uq_stripe_subscription_event_ref')
        && str_contains($sql, "'verified','applied','refused'")
        && !str_contains($sql, 'RawBody' . chr(96))
        && !str_contains($sql, 'SignatureHeader')
        && !str_contains($sql, 'Secret'),
        'migration stores only hashes and closed states');
    $assert(hash_equals(
        hash_file('sha256', $root . '/src/StripeSubscriptionEventReceiptPlanner.php'),
        hash_file('sha256', $root . '/package/StripeSubscriptionEventReceiptPlanner.php')
    ), 'source and package planner are identical');
    echo 'Stripe subscription-event receipt planner passed ' . $assertions . " assertions.\n";
    echo "No raw body, signature, secret, database, request, route, network, Stripe, payment, or deployment action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
