<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__) . '/package';
require_once $packageRoot . '/StripeCommerceCheckoutContract.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$sha = static fn (string $value): string => hash('sha256', $value);
$cart = [
    'cartId' => 'cart_' . str_repeat('a', 32),
    'snapshotSha256' => $sha('cart-snapshot'),
    'currency' => 'USD',
    'amountDueTodayMinor' => 115900,
    'futureRenewalMinor' => 5900,
    'customerEmail' => 'client@example.com',
    'lines' => [[
        'itemId' => 'ai-assistant-foundation',
        'quantity' => 1,
        'setupUnitMinor' => 80000,
        'recurringUnitMinor' => 5900,
    ], [
        'itemId' => 'additional-language',
        'quantity' => 2,
        'setupUnitMinor' => 15000,
        'recurringUnitMinor' => 0,
    ]],
];
$price = static function (
    string $id,
    string $lookupKey,
    string $offerId,
    int $amount,
    ?string $interval
): array {
    return [
        'id' => $id,
        'lookupKey' => $lookupKey,
        'offerId' => $offerId,
        'unitAmount' => $amount,
        'currency' => 'usd',
        'interval' => $interval,
        'active' => true,
        'livemode' => false,
        'productActive' => true,
        'catalogFamily' => 'red_sphere_ai_assistant',
    ];
};
$bindings = [
    'ai-assistant-foundation' => [
        'setup' => $price(
            'price_setup_foundation',
            'rs_ai_ai_assistant_foundation_setup_usd',
            'ai-assistant-foundation',
            80000,
            null
        ),
        'recurring' => $price(
            'price_monthly_foundation',
            'rs_ai_ai_assistant_foundation_monthly_usd',
            'ai-assistant-foundation',
            5900,
            'month'
        ),
    ],
    'additional-language' => [
        'setup' => $price(
            'price_setup_language',
            'rs_ai_additional_language_setup_usd',
            'additional-language',
            15000,
            null
        ),
        'recurring' => null,
    ],
];
$policy = [
    'livemode' => false,
    'successUrl' => 'https://commerce.red-sphere.com/cart/return/success',
    'cancelUrl' => 'https://commerce.red-sphere.com/cart/return/cancel',
    'integrationIdentifier' => 'red_sphere_ai_abcdwxyz',
    'checkoutCreatedAtEpoch' => 1788395230,
    'checkoutExpiresAtEpoch' => 1788397030,
];

try {
    $source = (string) file_get_contents(
        $packageRoot . '/StripeCommerceCheckoutContract.php'
    );
    $assert(
        !preg_match('/\b(?:curl|mysqli|PDO|fopen|file_put_contents|getenv)\b|\$_(?:GET|POST|SERVER|SESSION)/', $source),
        'Checkout contract is pure and non-networking'
    );
    $prepared = RED_CMS_Store_Lite_Stripe_Commerce_Checkout_Contract::prepare(
        $cart,
        $bindings,
        $policy
    );
    $assert(($prepared['valid'] ?? false) === true, 'fixed cart is accepted');
    $params = $prepared['sessionParams'];
    $assert($params['mode'] === 'subscription', 'recurring cart uses subscription mode');
    $assert($params['ui_mode'] === 'hosted', 'Checkout remains Stripe hosted');
    $assert(count($params['line_items']) === 3, 'setup and recurring Prices are separate line items');
    $assert(
        array_sum(array_map(
            static fn (array $line): int => $line['authoritativeAmountMinor'] * $line['quantity'],
            $prepared['resolvedLines']
        )) === $cart['amountDueTodayMinor'],
        'authoritative Price lines equal amount due today'
    );
    $assert(
        !array_key_exists('payment_method_types', $params),
        'dynamic payment methods remain enabled'
    );
    $assert(
        !array_key_exists('automatic_tax', $params),
        'tax is not enabled by the adapter'
    );
    $assert(
        $params['integration_identifier'] === 'red_sphere_ai_abcdwxyz',
        'integration identifier is included'
    );
    $assert($params['expires_at'] === 1788397030, 'Checkout expires after the approved 30 minutes');
    $assert(
        $params['metadata']['redcms_cart_id'] === $cart['cartId']
            && $params['subscription_data']['metadata']['redcms_cart_snapshot_sha256']
                === $cart['snapshotSha256'],
        'opaque cart identity and snapshot bind Session and Subscription'
    );
    $assert(
        preg_match('/\Ars_cart_[0-9a-f]{64}\z/D', $prepared['idempotencyKey']) === 1,
        'Checkout uses deterministic cart-snapshot idempotency'
    );

    $invalid = [];
    $amountDrift = $bindings;
    $amountDrift['ai-assistant-foundation']['setup']['unitAmount'] = 1;
    $invalid[] = [$cart, $amountDrift, $policy];
    $liveDrift = $bindings;
    $liveDrift['ai-assistant-foundation']['setup']['livemode'] = true;
    $invalid[] = [$cart, $liveDrift, $policy];
    $archived = $bindings;
    $archived['additional-language']['setup']['productActive'] = false;
    $invalid[] = [$cart, $archived, $policy];
    $missing = $bindings;
    unset($missing['additional-language']);
    $invalid[] = [$cart, $missing, $policy];
    $wrongFamily = $bindings;
    $wrongFamily['ai-assistant-foundation']['recurring']['catalogFamily'] = 'legacy';
    $invalid[] = [$cart, $wrongFamily, $policy];
    $badIdentifier = $policy;
    $badIdentifier['integrationIdentifier'] = 'red_sphere_ai_12345678';
    $invalid[] = [$cart, $bindings, $badIdentifier];
    $browserPrice = $cart;
    $browserPrice['lines'][0]['stripePriceId'] = 'price_browser';
    $invalid[] = [$browserPrice, $bindings, $policy];
    foreach ($invalid as [$candidateCart, $candidateBindings, $candidatePolicy]) {
        $assert(
            RED_CMS_Store_Lite_Stripe_Commerce_Checkout_Contract::prepare(
                $candidateCart,
                $candidateBindings,
                $candidatePolicy
            )['valid'] === false,
            'amount, mode, product, mapping, family, identifier, and browser provider drift fail closed'
        );
    }

    echo 'Stripe commerce Checkout contract passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
