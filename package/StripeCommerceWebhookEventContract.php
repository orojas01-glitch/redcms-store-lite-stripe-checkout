<?php

declare(strict_types=1);

/**
 * Pure contract for a signature-verified, privacy-bounded Stripe commerce
 * event projection. It deliberately does not trust a success page.
 */
final class RED_CMS_Store_Lite_Stripe_Commerce_Webhook_Event_Contract
{
    private const API_VERSION = '2026-08-26.dahlia';
    private const EVENT_TYPES = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'invoice.paid',
        'invoice.payment_failed',
        'invoice.payment_action_required',
        'invoice.finalization_failed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public static function supportedEventTypes(): array
    {
        return self::EVENT_TYPES;
    }

    public static function normalize(array $expected, array $projection): array
    {
        if (!self::expected($expected) || !self::projection($projection)) {
            return self::invalid('commerce_webhook_projection_refused');
        }
        if (!hash_equals($expected['cartId'], $projection['cartId'])
            || !hash_equals(
                $expected['cartSnapshotSha256'],
                $projection['cartSnapshotSha256']
            )
            || $expected['livemode'] !== $projection['livemode']
        ) {
            return self::invalid('commerce_webhook_cart_mismatch');
        }

        $type = $projection['eventType'];
        $cartEventType = null;
        $paymentAuthoritative = false;
        $subscriptionOutcome = null;
        $outcome = match ($type) {
            'checkout.session.completed' => 'checkout_completed',
            'checkout.session.async_payment_succeeded' => 'checkout_async_succeeded',
            'checkout.session.async_payment_failed' => 'payment_failed',
            'checkout.session.expired' => 'checkout_expired',
            'invoice.paid' => 'payment_paid',
            'invoice.payment_failed' => 'payment_failed',
            'invoice.payment_action_required' => 'payment_action_required',
            'invoice.finalization_failed' => 'operator_review',
            'customer.subscription.created' => 'subscription_created',
            'customer.subscription.updated' => 'subscription_updated',
            'customer.subscription.deleted' => 'subscription_canceled',
        };
        if ($type === 'checkout.session.async_payment_failed'
            || $type === 'invoice.payment_failed'
        ) {
            $cartEventType = 'payment.failed';
            $paymentAuthoritative = true;
        } elseif ($type === 'checkout.session.expired') {
            $cartEventType = 'checkout.expired';
            $paymentAuthoritative = true;
        } elseif ($type === 'invoice.paid') {
            $cartEventType = 'payment.paid';
            $paymentAuthoritative = true;
        }
        if (str_starts_with($type, 'customer.subscription.')) {
            $subscriptionOutcome = $type === 'customer.subscription.deleted'
                ? 'canceled'
                : $projection['subscriptionStatus'];
        } elseif (str_starts_with($type, 'invoice.')) {
            $subscriptionOutcome = $projection['subscriptionStatus'];
        }

        $evidence = [
            'eventIdSha256' => hash('sha256', $projection['eventId']),
            'objectIdSha256' => hash('sha256', $projection['objectId']),
            'eventType' => $type,
            'payloadSha256' => $projection['payloadSha256'],
            'cartId' => $projection['cartId'],
            'cartSnapshotSha256' => $projection['cartSnapshotSha256'],
            'createdAtEpoch' => $projection['createdAtEpoch'],
            'receivedAtEpoch' => $projection['receivedAtEpoch'],
            'outcome' => $outcome,
            'cartEventType' => $cartEventType,
            'paymentAuthoritative' => $paymentAuthoritative,
            'subscriptionOutcome' => $subscriptionOutcome,
        ];
        $evidence['eventEvidenceSha256'] = self::hash($evidence);
        return [
            'valid' => true,
            'event' => $evidence,
            'rawProviderEventIncluded' => false,
            'customerDataIncluded' => false,
            'errors' => [],
        ];
    }

    private static function expected(array $value): bool
    {
        return self::exactKeys(
            $value,
            ['cartId', 'cartSnapshotSha256', 'livemode']
        )
            && self::cartId($value['cartId'] ?? null)
            && self::sha256($value['cartSnapshotSha256'] ?? null)
            && is_bool($value['livemode'] ?? null);
    }

    private static function projection(array $value): bool
    {
        if (!self::exactKeys($value, [
            'eventId',
            'eventType',
            'apiVersion',
            'livemode',
            'createdAtEpoch',
            'receivedAtEpoch',
            'objectId',
            'cartId',
            'cartSnapshotSha256',
            'objectStatus',
            'paymentStatus',
            'subscriptionStatus',
            'payloadSha256',
        ])
            || !is_string($value['eventId'] ?? null)
            || preg_match('/\Aevt_[A-Za-z0-9_]{8,160}\z/D', $value['eventId']) !== 1
            || !in_array($value['eventType'] ?? null, self::EVENT_TYPES, true)
            || ($value['apiVersion'] ?? null) !== self::API_VERSION
            || !is_bool($value['livemode'] ?? null)
            || !self::timestamp($value['createdAtEpoch'] ?? null)
            || !self::timestamp($value['receivedAtEpoch'] ?? null)
            || $value['receivedAtEpoch'] < $value['createdAtEpoch']
            || $value['receivedAtEpoch'] > $value['createdAtEpoch'] + 2592000
            || !is_string($value['objectId'] ?? null)
            || preg_match('/\A[A-Za-z]+_[A-Za-z0-9_]{8,180}\z/D', $value['objectId']) !== 1
            || !self::cartId($value['cartId'] ?? null)
            || !self::sha256($value['cartSnapshotSha256'] ?? null)
            || !self::nullableStatus($value['objectStatus'] ?? null)
            || !self::nullableStatus($value['paymentStatus'] ?? null)
            || !self::nullableStatus($value['subscriptionStatus'] ?? null)
            || !self::sha256($value['payloadSha256'] ?? null)
        ) {
            return false;
        }
        return true;
    }

    private static function nullableStatus(mixed $value): bool
    {
        return $value === null
            || (is_string($value)
                && preg_match('/\A[a-z][a-z0-9_]{0,31}\z/D', $value) === 1);
    }

    private static function cartId(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Acart_[a-f0-9]{32}\z/D', $value) === 1;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }

    private static function timestamp(mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 4102444800;
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
            'event' => null,
            'rawProviderEventIncluded' => false,
            'customerDataIncluded' => false,
            'errors' => [$reason],
        ];
    }
}
