<?php

declare(strict_types=1);

/**
 * Pure P3C-1 validator for one reviewed Stripe Checkout Session projection.
 *
 * This class has no request, filesystem, database, secret, SDK, or network
 * path. A later transport layer must supply the already-bounded projection.
 */
final class RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer
{
    public static function normalize(array $expected, array $response): array
    {
        if (!self::exactKeys($expected, [
            'orderId',
            'orderSnapshotSha256',
            'paymentMethod',
            'amountMinor',
            'currency',
            'idempotencySha256',
        ])
            || !self::orderId($expected['orderId'] ?? null)
            || !self::sha256($expected['orderSnapshotSha256'] ?? null)
            || ($expected['paymentMethod'] ?? null) !== 'stripe_checkout'
            || !self::amount($expected['amountMinor'] ?? null)
            || !self::currency($expected['currency'] ?? null)
            || !self::sha256($expected['idempotencySha256'] ?? null)
        ) {
            return self::invalid('expected_checkout_invalid');
        }

        if (!self::exactKeys($response, [
            'id',
            'object',
            'url',
            'mode',
            'status',
            'payment_status',
            'amount_total',
            'currency',
            'client_reference_id',
            'metadata',
            'livemode',
        ])
            || !self::checkoutSessionId($response['id'] ?? null)
            || ($response['object'] ?? null) !== 'checkout.session'
            || !is_string($response['url'] ?? null)
            || strlen($response['url']) < 1
            || strlen($response['url']) > 2048
            || ($response['mode'] ?? null) !== 'payment'
            || ($response['status'] ?? null) !== 'open'
            || ($response['payment_status'] ?? null) !== 'unpaid'
            || !self::amount($response['amount_total'] ?? null)
            || !is_string($response['currency'] ?? null)
            || !self::orderId($response['client_reference_id'] ?? null)
            || !is_array($response['metadata'] ?? null)
            || ($response['livemode'] ?? null) !== false
        ) {
            return self::invalid('checkout_response_invalid');
        }

        $metadata = $response['metadata'];
        if (!self::exactKeys($metadata, [
            'redcms_order_snapshot_sha256',
            'redcms_idempotency_sha256',
        ])
            || !self::sha256(
                $metadata['redcms_order_snapshot_sha256'] ?? null
            )
            || !self::sha256($metadata['redcms_idempotency_sha256'] ?? null)
        ) {
            return self::invalid('checkout_metadata_invalid');
        }

        if (!hash_equals(
            $expected['orderId'],
            $response['client_reference_id']
        )
            || !hash_equals(
                $expected['orderSnapshotSha256'],
                $metadata['redcms_order_snapshot_sha256']
            )
            || !hash_equals(
                $expected['idempotencySha256'],
                $metadata['redcms_idempotency_sha256']
            )
            || $expected['amountMinor'] !== $response['amount_total']
            || strtolower($expected['currency']) !== $response['currency']
        ) {
            return self::invalid('checkout_response_mismatch');
        }

        if (!self::checkoutUrl($response['url'], $response['id'])) {
            return self::invalid('checkout_url_invalid');
        }

        return [
            'valid' => true,
            'checkout' => [
                'checkoutSessionRef' => $response['id'],
                'checkoutUrl' => $response['url'],
            ],
            'errors' => [],
        ];
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

    private static function amount(mixed $value): bool
    {
        return is_int($value)
            && $value >= 0
            && $value <= 2400999997599;
    }

    private static function currency(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[A-Z]{3}\z/D', $value) === 1;
    }

    private static function checkoutSessionId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Acs_test_[A-Za-z0-9_]{16,160}\z/D', $value) === 1;
    }

    private static function checkoutUrl(string $value, string $sessionId): bool
    {
        $url = parse_url($value);
        return is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && ($url['host'] ?? null) === 'checkout.stripe.com'
            && !array_key_exists('user', $url)
            && !array_key_exists('pass', $url)
            && !array_key_exists('port', $url)
            && !array_key_exists('query', $url)
            && !array_key_exists('fragment', $url)
            && ($url['path'] ?? null) === '/c/pay/' . $sessionId;
    }

    private static function invalid(string $error): array
    {
        return [
            'valid' => false,
            'checkout' => null,
            'errors' => [$error],
        ];
    }
}
