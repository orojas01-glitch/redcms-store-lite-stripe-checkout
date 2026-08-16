<?php

declare(strict_types=1);

/**
 * Pure P3C-1 normalizer for an already-verified Stripe event projection.
 *
 * Signature verification, raw-body parsing, provider retrieval, replay
 * persistence, Store Lite invocation, and order mutation belong to later
 * gates. Extra or provider-sensitive input fails closed here.
 */
final class RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer
{
    private const EVENT_OUTCOMES = [
        'checkout.session.completed' => [
            'providerStatus' => 'complete_paid',
            'outcome' => 'paid',
        ],
        'checkout.session.async_payment_failed' => [
            'providerStatus' => 'failed',
            'outcome' => 'failed',
        ],
        'checkout.session.expired' => [
            'providerStatus' => 'expired',
            'outcome' => 'expired',
        ],
        'charge.refunded' => [
            'providerStatus' => 'refunded',
            'outcome' => 'refund_confirmed',
        ],
        'charge.dispute.created' => [
            'providerStatus' => 'disputed',
            'outcome' => 'reversal_reported',
        ],
    ];

    public static function normalize(array $expected, array $verifiedEvent): array
    {
        if (!self::exactKeys($expected, [
            'orderId',
            'orderSnapshotSha256',
            'paymentMethod',
            'amountMinor',
            'currency',
            'checkoutSessionRef',
        ])
            || !self::orderId($expected['orderId'] ?? null)
            || !self::sha256($expected['orderSnapshotSha256'] ?? null)
            || ($expected['paymentMethod'] ?? null) !== 'stripe_checkout'
            || !self::amount($expected['amountMinor'] ?? null)
            || !self::currency($expected['currency'] ?? null)
            || !self::checkoutSessionId(
                $expected['checkoutSessionRef'] ?? null
            )
        ) {
            return self::invalid('expected_event_invalid');
        }

        if (!self::exactKeys($verifiedEvent, [
            'verification',
            'replayStatus',
            'eventRef',
            'eventType',
            'checkoutSessionRef',
            'orderId',
            'orderSnapshotSha256',
            'amountMinor',
            'currency',
            'providerStatus',
            'eventEvidenceSha256',
            'occurredAt',
            'receivedAt',
            'livemode',
        ])
            || ($verifiedEvent['verification'] ?? null) !== 'verified'
            || !is_string($verifiedEvent['replayStatus'] ?? null)
            || !in_array(
                $verifiedEvent['replayStatus'],
                ['unseen', 'replayed'],
                true
            )
            || !self::eventId($verifiedEvent['eventRef'] ?? null)
            || !is_string($verifiedEvent['eventType'] ?? null)
            || !self::checkoutSessionId(
                $verifiedEvent['checkoutSessionRef'] ?? null
            )
            || !self::orderId($verifiedEvent['orderId'] ?? null)
            || !self::sha256(
                $verifiedEvent['orderSnapshotSha256'] ?? null
            )
            || !self::amount($verifiedEvent['amountMinor'] ?? null)
            || !self::currency($verifiedEvent['currency'] ?? null)
            || !is_string($verifiedEvent['providerStatus'] ?? null)
            || !self::sha256(
                $verifiedEvent['eventEvidenceSha256'] ?? null
            )
            || !self::timestamp($verifiedEvent['occurredAt'] ?? null)
            || !self::timestamp($verifiedEvent['receivedAt'] ?? null)
            || ($verifiedEvent['livemode'] ?? null) !== false
        ) {
            return self::invalid('verified_event_invalid');
        }

        if ($verifiedEvent['replayStatus'] !== 'unseen') {
            return self::invalid('event_replayed');
        }

        $eventType = $verifiedEvent['eventType'];
        $mapping = self::EVENT_OUTCOMES[$eventType] ?? null;
        if (!is_array($mapping)
            || $mapping['providerStatus'] !== $verifiedEvent['providerStatus']
        ) {
            return self::invalid('event_outcome_invalid');
        }

        if (!hash_equals($expected['orderId'], $verifiedEvent['orderId'])
            || !hash_equals(
                $expected['orderSnapshotSha256'],
                $verifiedEvent['orderSnapshotSha256']
            )
            || !hash_equals(
                $expected['checkoutSessionRef'],
                $verifiedEvent['checkoutSessionRef']
            )
            || $expected['amountMinor'] !== $verifiedEvent['amountMinor']
            || $expected['currency'] !== $verifiedEvent['currency']
        ) {
            return self::invalid('verified_event_mismatch');
        }

        if ($verifiedEvent['occurredAt'] > $verifiedEvent['receivedAt']
            || $verifiedEvent['occurredAt'] < $verifiedEvent['receivedAt'] - 300
        ) {
            return self::invalid('verified_event_timestamp_invalid');
        }

        return [
            'valid' => true,
            'event' => [
                'verification' => 'verified',
                'replayStatus' => 'unseen',
                'outcome' => $mapping['outcome'],
                'orderId' => $expected['orderId'],
                'orderSnapshotSha256' => $expected['orderSnapshotSha256'],
                'paymentMethod' => 'stripe_checkout',
                'amountMinor' => $expected['amountMinor'],
                'currency' => $expected['currency'],
                'eventEvidenceSha256' =>
                    $verifiedEvent['eventEvidenceSha256'],
                'occurredAt' => $verifiedEvent['occurredAt'],
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

    private static function eventId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Aevt_[A-Za-z0-9_]{8,160}\z/D', $value) === 1;
    }

    private static function checkoutSessionId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Acs_test_[A-Za-z0-9_]{16,160}\z/D', $value) === 1;
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

    private static function timestamp(mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 4102444800;
    }

    private static function invalid(string $error): array
    {
        return [
            'valid' => false,
            'event' => null,
            'errors' => [$error],
        ];
    }
}
