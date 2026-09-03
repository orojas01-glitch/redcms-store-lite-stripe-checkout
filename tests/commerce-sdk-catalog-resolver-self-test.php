<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__) . '/package';
require_once $packageRoot . '/StripeSdkCatalogResolver.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

final class FakePriceService
{
    public array $calls = [];
    public array $prices;

    public function __construct(array $prices)
    {
        $this->prices = $prices;
    }

    public function all(array $params): object
    {
        $this->calls[] = $params;
        $wanted = array_flip($params['lookup_keys']);
        return (object) [
            'data' => array_values(array_filter(
                $this->prices,
                static fn (object $price): bool => isset($wanted[$price->lookup_key])
            )),
            'has_more' => false,
        ];
    }
}

final class FakeStripeClient
{
    public FakePriceService $prices;

    public function __construct(array $prices)
    {
        $this->prices = new FakePriceService($prices);
    }
}

$product = static fn (string $offer, bool $active = true): object => (object) [
    'active' => $active,
    'metadata' => (object) [
        'catalog_family' => 'red_sphere_ai_assistant',
        'offer_id' => $offer,
    ],
];
$price = static fn (
    string $id,
    string $key,
    string $offer,
    int $amount,
    ?string $interval
): object => (object) [
    'id' => $id,
    'active' => true,
    'livemode' => false,
    'lookup_key' => $key,
    'unit_amount' => $amount,
    'currency' => 'usd',
    'recurring' => $interval === null ? null : (object) ['interval' => $interval],
    'metadata' => (object) [
        'catalog_family' => 'red_sphere_ai_assistant',
        'offer_id' => $offer,
    ],
    'product' => $product($offer),
];

$prices = [
    $price(
        'price_setup_foundation',
        'rs_ai_ai_assistant_foundation_setup_usd',
        'ai-assistant-foundation',
        80000,
        null
    ),
    $price(
        'price_monthly_foundation',
        'rs_ai_ai_assistant_foundation_monthly_usd',
        'ai-assistant-foundation',
        5900,
        'month'
    ),
    $price(
        'price_setup_language',
        'rs_ai_additional_language_setup_usd',
        'additional-language',
        15000,
        null
    ),
];
$lines = [[
    'itemId' => 'ai-assistant-foundation',
    'quantity' => 1,
    'setupUnitMinor' => 80000,
    'recurringUnitMinor' => 5900,
], [
    'itemId' => 'additional-language',
    'quantity' => 2,
    'setupUnitMinor' => 15000,
    'recurringUnitMinor' => 0,
]];
$lookupBindings = [
    'ai-assistant-foundation' => [
        'offerId' => 'ai-assistant-foundation',
        'setupLookupKey' => 'rs_ai_ai_assistant_foundation_setup_usd',
        'recurringLookupKey' => 'rs_ai_ai_assistant_foundation_monthly_usd',
    ],
    'additional-language' => [
        'offerId' => 'additional-language',
        'setupLookupKey' => 'rs_ai_additional_language_setup_usd',
        'recurringLookupKey' => null,
    ],
];

try {
    $client = new FakeStripeClient($prices);
    $resolved = RED_CMS_Store_Lite_Stripe_SDK_Catalog_Resolver::resolve(
        $client,
        $lines,
        $lookupBindings,
        false,
        'USD'
    );
    $assert(($resolved['valid'] ?? false) === true, 'lookup keys resolve exact active Prices');
    $assert(count($resolved['bindings']) === 2, 'every cart item receives one binding');
    $assert(
        $resolved['bindings']['ai-assistant-foundation']['recurring']['id']
            === 'price_monthly_foundation',
        'authoritative Price ID is provider-resolved'
    );
    $assert(
        $client->prices->calls[0]['lookup_keys'] === [
            'rs_ai_additional_language_setup_usd',
            'rs_ai_ai_assistant_foundation_monthly_usd',
            'rs_ai_ai_assistant_foundation_setup_usd',
        ],
        'lookup keys are deduplicated and deterministic'
    );
    $assert(
        $client->prices->calls[0]['expand'] === ['data.product'],
        'Product metadata is expanded for family validation'
    );

    $missingClient = new FakeStripeClient(array_slice($prices, 0, 2));
    $assert(
        RED_CMS_Store_Lite_Stripe_SDK_Catalog_Resolver::resolve(
            $missingClient,
            $lines,
            $lookupBindings,
            false,
            'USD'
        )['valid'] === false,
        'missing Price fails closed'
    );
    $legacyPrices = $prices;
    $legacyPrices[0]->metadata->catalog_family = 'legacy';
    $assert(
        RED_CMS_Store_Lite_Stripe_SDK_Catalog_Resolver::resolve(
            new FakeStripeClient($legacyPrices),
            $lines,
            $lookupBindings,
            false,
            'USD'
        )['valid'] === false,
        'foreign metadata family fails closed'
    );
    $browserBinding = $lookupBindings;
    $browserBinding['additional-language']['stripePriceId'] = 'price_browser';
    $assert(
        RED_CMS_Store_Lite_Stripe_SDK_Catalog_Resolver::resolve(
            new FakeStripeClient($prices),
            $lines,
            $browserBinding,
            false,
            'USD'
        )['valid'] === false,
        'provider Price IDs are refused in mapping input'
    );

    echo 'Stripe SDK catalog resolver passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
