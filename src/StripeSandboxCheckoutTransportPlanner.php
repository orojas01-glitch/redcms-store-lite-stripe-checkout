<?php

declare(strict_types=1);

/**
 * Pure P3E-1 plan for one future Stripe Sandbox Checkout request.
 *
 * The result is data only. It contains no credential value and performs no
 * request, secret lookup, filesystem, database, SDK, or network operation.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner
{
    private const MAX_AMOUNT_MINOR = 2400999997599;

    public static function plan(array $checkout, array $policy): array
    {
        if (!self::checkout($checkout)) {
            return self::invalid('checkout_projection_invalid');
        }
        if (!self::policy($policy)) {
            return self::invalid('transport_policy_invalid');
        }

        $body = [
            'mode' => 'payment',
            'ui_mode' => 'hosted',
            'client_reference_id' => $checkout['orderId'],
            'success_url' => $policy['successUrl'],
            'cancel_url' => $policy['cancelUrl'],
            'metadata[redcms_order_snapshot_sha256]' =>
                $checkout['orderSnapshotSha256'],
            'metadata[redcms_idempotency_sha256]' =>
                $checkout['idempotencySha256'],
        ];
        foreach ($checkout['lineItems'] as $index => $line) {
            $prefix = 'line_items[' . $index . ']';
            $body[$prefix . '[price_data][currency]'] =
                strtolower($checkout['currency']);
            $body[$prefix . '[price_data][product_data][name]'] =
                $line['name'];
            $body[$prefix . '[price_data][unit_amount]'] =
                (string) $line['unitAmountMinor'];
            $body[$prefix . '[quantity]'] = (string) $line['quantity'];
        }

        $plan = [
            'environment' => 'sandbox',
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.stripe.com/v1/checkout/sessions',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Stripe-Version' => $policy['apiVersion'],
                    'Idempotency-Key' =>
                        'redcms-checkout-' . $checkout['idempotencySha256'],
                ],
                'authorization' => [
                    'scheme' => 'http-basic-username',
                    'secretSettingKey' => 'stripe.secret-key',
                    'valueIncluded' => false,
                ],
                'form' => $body,
            ],
            'transport' => [
                'minimumTlsVersion' => '1.2',
                'verifyPeer' => true,
                'verifyHost' => true,
                'followRedirects' => false,
                'maximumRedirects' => 0,
                'connectTimeoutMilliseconds' => 5000,
                'totalTimeoutMilliseconds' => 15000,
                'maximumResponseBytes' => 262144,
            ],
            'response' => [
                'statusCode' => 200,
                'contentType' => 'application/json',
                'projectionNormalizer' =>
                    'RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer',
            ],
        ];
        try {
            $encoded = json_encode(
                $plan,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return self::invalid('transport_plan_encoding_failed');
        }

        return [
            'valid' => true,
            'plan' => $plan,
            'planSha256' => hash('sha256', $encoded),
            'errors' => [],
        ];
    }

    private static function checkout(array $checkout): bool
    {
        if (!self::exactKeys($checkout, [
            'orderId',
            'orderSnapshotSha256',
            'paymentMethod',
            'amountMinor',
            'currency',
            'idempotencySha256',
            'lineItems',
        ])
            || !self::orderId($checkout['orderId'] ?? null)
            || !self::sha256($checkout['orderSnapshotSha256'] ?? null)
            || ($checkout['paymentMethod'] ?? null) !== 'stripe_checkout'
            || !self::amount($checkout['amountMinor'] ?? null, false)
            || !self::currency($checkout['currency'] ?? null)
            || !self::sha256($checkout['idempotencySha256'] ?? null)
            || !is_array($checkout['lineItems'] ?? null)
            || !array_is_list($checkout['lineItems'])
            || count($checkout['lineItems']) < 1
            || count($checkout['lineItems']) > 24
        ) {
            return false;
        }

        $total = 0;
        foreach ($checkout['lineItems'] as $line) {
            if (!is_array($line)
                || !self::exactKeys($line, [
                    'name', 'quantity', 'unitAmountMinor', 'lineTotalMinor',
                ])
                || !self::text($line['name'] ?? null, 160)
                || !is_int($line['quantity'] ?? null)
                || $line['quantity'] < 1
                || $line['quantity'] > 100
                || !self::amount($line['unitAmountMinor'] ?? null, true)
                || !self::amount($line['lineTotalMinor'] ?? null, true)
                || $line['lineTotalMinor']
                    !== $line['unitAmountMinor'] * $line['quantity']
            ) {
                return false;
            }
            $total += $line['lineTotalMinor'];
            if ($total > self::MAX_AMOUNT_MINOR) {
                return false;
            }
        }

        return $total === $checkout['amountMinor'];
    }

    private static function policy(array $policy): bool
    {
        return self::exactKeys($policy, [
            'apiVersion', 'successUrl', 'cancelUrl',
        ])
            && self::apiVersion($policy['apiVersion'] ?? null)
            && self::returnUrl($policy['successUrl'] ?? null)
            && self::returnUrl($policy['cancelUrl'] ?? null)
            && self::sameOrigin(
                $policy['successUrl'],
                $policy['cancelUrl']
            );
    }

    private static function apiVersion(mixed $value): bool
    {
        if (!is_string($value)
            || preg_match(
                '/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\.[a-z][a-z0-9_]{1,31}\z/D',
                $value,
                $matches
            ) !== 1
        ) {
            return false;
        }

        return checkdate(
            (int) $matches[2],
            (int) $matches[3],
            (int) $matches[1]
        );
    }

    private static function returnUrl(mixed $value): bool
    {
        if (!is_string($value)
            || strlen($value) < 1
            || strlen($value) > 2048
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            return false;
        }
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && self::host($url['host'] ?? null)
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && !array_key_exists('fragment', $url)
            && is_string($url['path'] ?? null)
            && str_starts_with($url['path'], '/')
            && $url['path'] !== '/';
    }

    private static function sameOrigin(string $left, string $right): bool
    {
        $leftUrl = parse_url($left);
        $rightUrl = parse_url($right);
        return is_array($leftUrl)
            && is_array($rightUrl)
            && ($leftUrl['scheme'] ?? null) === ($rightUrl['scheme'] ?? null)
            && ($leftUrl['host'] ?? null) === ($rightUrl['host'] ?? null);
    }

    private static function host(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= 253
            && preg_match(
                '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/D',
                $value
            ) === 1;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private static function orderId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Aord_[a-f0-9]{32}\z/D', $value) === 1;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function amount(mixed $value, bool $allowZero): bool
    {
        return is_int($value)
            && $value >= ($allowZero ? 0 : 1)
            && $value <= self::MAX_AMOUNT_MINOR;
    }

    private static function currency(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[A-Z]{3}\z/D', $value) === 1;
    }

    private static function text(mixed $value, int $maximum): bool
    {
        return is_string($value)
            && strlen($value) >= 1
            && strlen($value) <= $maximum
            && trim($value) === $value
            && preg_match('//u', $value) === 1
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    private static function invalid(string $error): array
    {
        return [
            'valid' => false,
            'plan' => null,
            'planSha256' => '',
            'errors' => [$error],
        ];
    }
}
