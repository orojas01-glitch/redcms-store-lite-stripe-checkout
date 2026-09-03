<?php

declare(strict_types=1);

$migration = dirname(__DIR__)
    . '/package/migrations/2026-09-02-create-commerce-checkout-receipts.sql';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $sql = file_get_contents($migration);
    $assert(is_string($sql) && $sql !== '', 'commerce receipt migration is readable');
    $assert(
        substr_count($sql, 'CREATE TABLE IF NOT EXISTS') === 2,
        'migration creates Checkout attempts and event receipts only'
    );
    $assert(
        str_contains($sql, '`RED_Addon_StoreLite_Stripe_Commerce_Checkout_Attempts`')
            && str_contains($sql, '`RED_Addon_StoreLite_Stripe_Commerce_Event_Receipts`'),
        'adapter-owned receipt tables are declared'
    );
    $assert(
        str_contains($sql, 'UNIQUE KEY `uq_stripe_commerce_checkout_idempotency`')
            && str_contains($sql, 'UNIQUE KEY `uq_stripe_commerce_event_reference`'),
        'Checkout and webhook replay identities are unique'
    );
    $assert(
        !preg_match('/`(?:RawBody|Payload|Signature|Secret|CustomerEmail)`/i', $sql),
        'receipts store no raw payload, signature, secret, or customer email'
    );
    $assert(substr_count($sql, 'ENGINE=InnoDB') === 2, 'both tables are transactional');

    echo 'Stripe commerce receipts migration passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
