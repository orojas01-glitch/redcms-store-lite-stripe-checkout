<?php

declare(strict_types=1);

/** Resolve active Stripe Prices exclusively through server-owned lookup keys. */
final class RED_CMS_Store_Lite_Stripe_SDK_Catalog_Resolver
{
    private const CATALOG_FAMILY = 'red_sphere_ai_assistant';

    public static function resolve(
        object $client,
        array $cartLines,
        array $lookupBindings,
        bool $livemode,
        string $currency
    ): array {
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1
            || !is_array($cartLines)
            || !array_is_list($cartLines)
            || count($cartLines) < 1
            || count($cartLines) > 24
            || !isset($client->prices)
            || !is_object($client->prices)
            || !method_exists($client->prices, 'all')
        ) {
            return self::invalid('commerce_catalog_input_refused');
        }

        $lineById = [];
        foreach ($cartLines as $line) {
            if (!is_array($line)
                || !self::exactKeys($line, [
                    'itemId',
                    'quantity',
                    'setupUnitMinor',
                    'recurringUnitMinor',
                ])
                || !is_string($line['itemId'] ?? null)
                || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $line['itemId']) !== 1
                || isset($lineById[$line['itemId']])
                || !is_int($line['quantity'] ?? null)
                || $line['quantity'] < 1
                || $line['quantity'] > 100
                || !is_int($line['setupUnitMinor'] ?? null)
                || !is_int($line['recurringUnitMinor'] ?? null)
                || $line['setupUnitMinor'] < 0
                || $line['recurringUnitMinor'] < 0
            ) {
                return self::invalid('commerce_catalog_line_refused');
            }
            $lineById[$line['itemId']] = $line;
        }
        if (!self::exactKeys($lookupBindings, array_keys($lineById))) {
            return self::invalid('commerce_catalog_mapping_refused');
        }

        $lookupKeys = [];
        $requirements = [];
        foreach ($lineById as $itemId => $line) {
            $mapping = $lookupBindings[$itemId] ?? null;
            if (!is_array($mapping)
                || !self::exactKeys($mapping, [
                    'offerId',
                    'setupLookupKey',
                    'recurringLookupKey',
                ])
                || !is_string($mapping['offerId'] ?? null)
                || preg_match('/\A[a-z0-9][a-z0-9-]{0,79}\z/D', $mapping['offerId']) !== 1
            ) {
                return self::invalid('commerce_catalog_mapping_refused');
            }
            foreach ([
                ['role' => 'setup', 'amountKey' => 'setupUnitMinor', 'lookupKey' => 'setupLookupKey'],
                ['role' => 'recurring', 'amountKey' => 'recurringUnitMinor', 'lookupKey' => 'recurringLookupKey'],
            ] as $term) {
                $key = $mapping[$term['lookupKey']] ?? null;
                $amount = $line[$term['amountKey']];
                if ($amount === 0) {
                    if ($key !== null) {
                        return self::invalid('commerce_catalog_unexpected_lookup_key');
                    }
                    continue;
                }
                if (!is_string($key)
                    || preg_match('/\Ars_ai_[a-z0-9_]{1,180}\z/D', $key) !== 1
                    || isset($requirements[$key])
                ) {
                    return self::invalid('commerce_catalog_lookup_key_refused');
                }
                $requirements[$key] = [
                    'itemId' => $itemId,
                    'offerId' => $mapping['offerId'],
                    'role' => $term['role'],
                    'amount' => $amount,
                ];
                $lookupKeys[] = $key;
            }
        }
        sort($lookupKeys, SORT_STRING);
        if ($lookupKeys === [] || count($lookupKeys) > 48) {
            return self::invalid('commerce_catalog_lookup_keys_refused');
        }

        $pricesByKey = [];
        try {
            foreach (array_chunk($lookupKeys, 10) as $batch) {
                $collection = $client->prices->all([
                    'active' => true,
                    'lookup_keys' => $batch,
                    'expand' => ['data.product'],
                    'limit' => 100,
                ]);
                if (!is_object($collection)
                    || !is_array($collection->data ?? null)
                    || ($collection->has_more ?? null) !== false
                ) {
                    return self::invalid('commerce_catalog_response_refused');
                }
                foreach ($collection->data as $price) {
                    if (!is_object($price)) {
                        return self::invalid('commerce_catalog_price_refused');
                    }
                    $key = self::field($price, 'lookup_key');
                    if (!is_string($key)
                        || !isset($requirements[$key])
                        || isset($pricesByKey[$key])
                    ) {
                        return self::invalid('commerce_catalog_price_refused');
                    }
                    $pricesByKey[$key] = $price;
                }
            }
        } catch (Throwable $throwable) {
            return self::invalid('commerce_catalog_provider_unavailable');
        }
        if (count($pricesByKey) !== count($requirements)) {
            return self::invalid('commerce_catalog_price_missing');
        }

        $bindings = [];
        foreach ($lineById as $itemId => $line) {
            $bindings[$itemId] = ['setup' => null, 'recurring' => null];
        }
        foreach ($requirements as $key => $requirement) {
            $normalized = self::price(
                $pricesByKey[$key],
                $key,
                $requirement,
                $livemode,
                strtolower($currency)
            );
            if ($normalized === null) {
                return self::invalid('commerce_catalog_price_drift');
            }
            $bindings[$requirement['itemId']][$requirement['role']] = $normalized;
        }
        ksort($bindings, SORT_STRING);
        return [
            'valid' => true,
            'bindings' => $bindings,
            'lookupKeys' => $lookupKeys,
            'providerPriceIdsBrowserOwned' => false,
            'errors' => [],
        ];
    }

    private static function price(
        object $price,
        string $lookupKey,
        array $requirement,
        bool $livemode,
        string $currency
    ): ?array {
        $product = self::field($price, 'product');
        $priceMetadata = self::field($price, 'metadata');
        $productMetadata = is_object($product)
            ? self::field($product, 'metadata')
            : null;
        $recurring = self::field($price, 'recurring');
        $interval = is_object($recurring)
            ? self::field($recurring, 'interval')
            : null;
        if (!is_string(self::field($price, 'id'))
            || preg_match('/\Aprice_[A-Za-z0-9_]{8,160}\z/D', self::field($price, 'id')) !== 1
            || self::field($price, 'active') !== true
            || self::field($price, 'livemode') !== $livemode
            || self::field($price, 'lookup_key') !== $lookupKey
            || self::field($price, 'unit_amount') !== $requirement['amount']
            || self::field($price, 'currency') !== $currency
            || !is_object($product)
            || self::field($product, 'active') !== true
            || self::metadata($priceMetadata, 'catalog_family') !== self::CATALOG_FAMILY
            || self::metadata($productMetadata, 'catalog_family') !== self::CATALOG_FAMILY
            || self::metadata($priceMetadata, 'offer_id') !== $requirement['offerId']
            || self::metadata($productMetadata, 'offer_id') !== $requirement['offerId']
            || ($requirement['role'] === 'setup' && $interval !== null)
            || ($requirement['role'] === 'recurring' && $interval !== 'month')
        ) {
            return null;
        }
        return [
            'id' => self::field($price, 'id'),
            'lookupKey' => $lookupKey,
            'offerId' => $requirement['offerId'],
            'unitAmount' => $requirement['amount'],
            'currency' => $currency,
            'interval' => $interval,
            'active' => true,
            'livemode' => $livemode,
            'productActive' => true,
            'catalogFamily' => self::CATALOG_FAMILY,
        ];
    }

    private static function field(object $value, string $key): mixed
    {
        return $value->{$key} ?? null;
    }

    private static function metadata(mixed $value, string $key): mixed
    {
        if (is_object($value)) {
            return $value->{$key} ?? null;
        }
        if (is_array($value)) {
            return $value[$key] ?? null;
        }
        return null;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private static function invalid(string $reason): array
    {
        return [
            'valid' => false,
            'bindings' => [],
            'lookupKeys' => [],
            'providerPriceIdsBrowserOwned' => false,
            'errors' => [$reason],
        ];
    }
}
