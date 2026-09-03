<?php

declare(strict_types=1);

/**
 * Pure multi-line Checkout contract for one authoritative Store Lite review
 * cart. Provider Price facts must already have been resolved server-side.
 */
final class RED_CMS_Store_Lite_Stripe_Commerce_Checkout_Contract
{
    private const CATALOG_FAMILY = 'red_sphere_ai_assistant';

    public static function prepare(
        array $cart,
        array $bindings,
        array $policy
    ): array {
        if (!self::cart($cart)
            || !self::policy($policy)
            || !self::exactKeys($bindings, array_column($cart['lines'], 'itemId'))
        ) {
            return self::invalid('commerce_checkout_input_refused');
        }

        $lineItems = [];
        $resolvedLines = [];
        $seenPriceIds = [];
        $seenLookupKeys = [];
        $dueToday = 0;
        $futureRenewal = 0;
        $recurringCount = 0;
        foreach ($cart['lines'] as $line) {
            $binding = $bindings[$line['itemId']] ?? null;
            if (!is_array($binding)
                || !self::exactKeys($binding, ['setup', 'recurring'])
            ) {
                return self::invalid('commerce_checkout_binding_refused');
            }
            foreach ([
                ['role' => 'setup', 'amountKey' => 'setupUnitMinor'],
                ['role' => 'recurring', 'amountKey' => 'recurringUnitMinor'],
            ] as $term) {
                $amount = $line[$term['amountKey']];
                $price = $binding[$term['role']];
                if ($amount === 0) {
                    if ($price !== null) {
                        return self::invalid('commerce_checkout_unexpected_price');
                    }
                    continue;
                }
                if (!is_array($price)
                    || !self::price(
                        $price,
                        $amount,
                        strtolower($cart['currency']),
                        $policy['livemode'],
                        $term['role']
                    )
                    || isset($seenPriceIds[$price['id']])
                    || isset($seenLookupKeys[$price['lookupKey']])
                ) {
                    return self::invalid('commerce_checkout_price_refused');
                }
                $seenPriceIds[$price['id']] = true;
                $seenLookupKeys[$price['lookupKey']] = true;
                $lineItems[] = [
                    'price' => $price['id'],
                    'quantity' => $line['quantity'],
                ];
                $resolvedLines[] = [
                    'itemId' => $line['itemId'],
                    'offerId' => $price['offerId'],
                    'role' => $term['role'],
                    'lookupKey' => $price['lookupKey'],
                    'priceIdSha256' => hash('sha256', $price['id']),
                    'quantity' => $line['quantity'],
                    'authoritativeAmountMinor' => $price['unitAmount'],
                    'interval' => $price['interval'],
                ];
                $lineTotal = $price['unitAmount'] * $line['quantity'];
                $dueToday += $lineTotal;
                if ($term['role'] === 'recurring') {
                    $futureRenewal += $lineTotal;
                    $recurringCount++;
                }
            }
        }
        if ($recurringCount < 1
            || count($lineItems) < 1
            || count($lineItems) > 48
            || $dueToday !== $cart['amountDueTodayMinor']
            || $futureRenewal !== $cart['futureRenewalMinor']
        ) {
            return self::invalid('commerce_checkout_totals_refused');
        }

        $metadata = [
            'redcms_cart_id' => $cart['cartId'],
            'redcms_cart_snapshot_sha256' => $cart['snapshotSha256'],
        ];
        $sessionParams = [
            'mode' => 'subscription',
            'ui_mode' => 'hosted',
            'submit_type' => 'subscribe',
            'line_items' => $lineItems,
            'success_url' => $policy['successUrl'],
            'cancel_url' => $policy['cancelUrl'],
            'client_reference_id' => $cart['cartId'],
            'customer_email' => strtolower($cart['customerEmail']),
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
            'integration_identifier' => $policy['integrationIdentifier'],
            'expires_at' => $policy['checkoutExpiresAtEpoch'],
        ];
        $contract = [
            'schema' => 1,
            'cartId' => $cart['cartId'],
            'cartSnapshotSha256' => $cart['snapshotSha256'],
            'livemode' => $policy['livemode'],
            'sessionParams' => $sessionParams,
            'resolvedLines' => $resolvedLines,
            'amountDueTodayMinor' => $dueToday,
            'futureRenewalMinor' => $futureRenewal,
        ];
        $contractSha256 = self::hash($contract);
        return [
            'valid' => true,
            'sessionParams' => $sessionParams,
            'resolvedLines' => $resolvedLines,
            'idempotencyKey' => 'rs_cart_' . $cart['snapshotSha256'],
            'contractSha256' => $contractSha256,
            'errors' => [],
        ];
    }

    private static function cart(array $value): bool
    {
        if (!self::exactKeys($value, [
            'cartId',
            'snapshotSha256',
            'currency',
            'amountDueTodayMinor',
            'futureRenewalMinor',
            'customerEmail',
            'lines',
        ])
            || !is_string($value['cartId'] ?? null)
            || preg_match('/\Acart_[a-f0-9]{32}\z/D', $value['cartId']) !== 1
            || !self::sha256($value['snapshotSha256'] ?? null)
            || !is_string($value['currency'] ?? null)
            || preg_match('/\A[A-Z]{3}\z/D', $value['currency']) !== 1
            || !is_int($value['amountDueTodayMinor'] ?? null)
            || !is_int($value['futureRenewalMinor'] ?? null)
            || $value['amountDueTodayMinor'] < 1
            || $value['futureRenewalMinor'] < 1
            || !is_string($value['customerEmail'] ?? null)
            || filter_var($value['customerEmail'], FILTER_VALIDATE_EMAIL) === false
            || !is_array($value['lines'] ?? null)
            || !array_is_list($value['lines'])
            || count($value['lines']) < 1
            || count($value['lines']) > 24
        ) {
            return false;
        }
        $seen = [];
        foreach ($value['lines'] as $line) {
            if (!is_array($line)
                || !self::exactKeys($line, [
                    'itemId',
                    'quantity',
                    'setupUnitMinor',
                    'recurringUnitMinor',
                ])
                || !is_string($line['itemId'] ?? null)
                || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/D', $line['itemId']) !== 1
                || isset($seen[$line['itemId']])
                || !is_int($line['quantity'] ?? null)
                || $line['quantity'] < 1
                || $line['quantity'] > 100
                || !is_int($line['setupUnitMinor'] ?? null)
                || !is_int($line['recurringUnitMinor'] ?? null)
                || $line['setupUnitMinor'] < 0
                || $line['recurringUnitMinor'] < 0
                || ($line['setupUnitMinor'] === 0
                    && $line['recurringUnitMinor'] === 0)
            ) {
                return false;
            }
            $seen[$line['itemId']] = true;
        }
        return true;
    }

    private static function policy(array $value): bool
    {
        return self::exactKeys($value, [
            'livemode',
            'successUrl',
            'cancelUrl',
            'integrationIdentifier',
            'checkoutCreatedAtEpoch',
            'checkoutExpiresAtEpoch',
        ])
            && is_bool($value['livemode'] ?? null)
            && self::httpsUrl($value['successUrl'] ?? null)
            && self::httpsUrl($value['cancelUrl'] ?? null)
            && parse_url($value['successUrl'], PHP_URL_HOST)
                === parse_url($value['cancelUrl'], PHP_URL_HOST)
            && is_string($value['integrationIdentifier'] ?? null)
            && preg_match(
                '/\A[a-z][a-z0-9_]{1,47}_[a-z]{8}\z/D',
                $value['integrationIdentifier']
            ) === 1
            && is_int($value['checkoutCreatedAtEpoch'] ?? null)
            && is_int($value['checkoutExpiresAtEpoch'] ?? null)
            && $value['checkoutCreatedAtEpoch'] >= 1
            && $value['checkoutExpiresAtEpoch']
                === $value['checkoutCreatedAtEpoch'] + 1800;
    }

    private static function price(
        array $value,
        int $expectedAmount,
        string $expectedCurrency,
        bool $expectedLivemode,
        string $role
    ): bool {
        return self::exactKeys($value, [
            'id',
            'lookupKey',
            'offerId',
            'unitAmount',
            'currency',
            'interval',
            'active',
            'livemode',
            'productActive',
            'catalogFamily',
        ])
            && is_string($value['id'] ?? null)
            && preg_match('/\Aprice_[A-Za-z0-9_]{8,160}\z/D', $value['id']) === 1
            && is_string($value['lookupKey'] ?? null)
            && preg_match('/\Ars_ai_[a-z0-9_]{1,180}\z/D', $value['lookupKey']) === 1
            && is_string($value['offerId'] ?? null)
            && preg_match('/\A[a-z0-9][a-z0-9-]{0,79}\z/D', $value['offerId']) === 1
            && ($value['unitAmount'] ?? null) === $expectedAmount
            && ($value['currency'] ?? null) === $expectedCurrency
            && ($value['active'] ?? null) === true
            && ($value['livemode'] ?? null) === $expectedLivemode
            && ($value['productActive'] ?? null) === true
            && ($value['catalogFamily'] ?? null) === self::CATALOG_FAMILY
            && (($role === 'setup' && ($value['interval'] ?? null) === null)
                || ($role === 'recurring'
                    && ($value['interval'] ?? null) === 'month'));
    }

    private static function httpsUrl(mixed $value): bool
    {
        if (!is_string($value)
            || strlen($value) > 2048
            || filter_var($value, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }
        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https'
            && is_string(parse_url($value, PHP_URL_HOST))
            && parse_url($value, PHP_URL_USER) === null
            && parse_url($value, PHP_URL_PASS) === null
            && parse_url($value, PHP_URL_FRAGMENT) === null;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private static function hash(array $value): string
    {
        return hash(
            'sha256',
            json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );
    }

    private static function invalid(string $reason): array
    {
        return [
            'valid' => false,
            'sessionParams' => null,
            'resolvedLines' => [],
            'idempotencyKey' => '',
            'contractSha256' => '',
            'errors' => [$reason],
        ];
    }
}
