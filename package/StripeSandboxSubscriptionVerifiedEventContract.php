<?php

declare(strict_types=1);

/**
 * Pure contract for one already-signature-verified Stripe Sandbox
 * subscription event. Raw request parsing and signature verification are
 * deliberately outside this gate.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Verified_Event_Contract
{
    private const PROVIDER_STATUS = [
        'checkout.session.completed' => 'complete_paid',
        'invoice.paid' => 'paid_active',
        'invoice.payment_failed' => 'payment_failed',
        'customer.subscription.deleted' => 'canceled',
        'checkout.session.expired' => 'expired',
    ];

    public static function normalize(
        array $expected,
        array $verifiedEvent
    ): array {
        if (!self::expected($expected)) {
            return self::invalid('subscription_expected_state_invalid');
        }
        if (!self::verifiedEvent($verifiedEvent)) {
            return self::invalid('subscription_verified_event_invalid');
        }
        if ($verifiedEvent['replayStatus'] !== 'unseen') {
            return self::invalid('subscription_event_replayed');
        }
        if (!hash_equals(
            $expected['intentReference'],
            $verifiedEvent['intentReference']
        ) || !hash_equals(
            $expected['offerStateSha256'],
            $verifiedEvent['offerStateSha256']
        )) {
            return self::invalid('subscription_event_mismatch');
        }

        $eventType = $verifiedEvent['eventType'];
        if ((self::PROVIDER_STATUS[$eventType] ?? null)
            !== $verifiedEvent['providerStatus']
        ) {
            return self::invalid('subscription_event_outcome_invalid');
        }

        $providerRef = $verifiedEvent['providerSubscriptionRef'];
        $providerRefSha256 = is_string($providerRef)
            ? hash('sha256', $providerRef)
            : null;
        $checkoutRef = $verifiedEvent['checkoutSessionRef'];
        $checkoutRefSha256 = is_string($checkoutRef)
            ? hash('sha256', $checkoutRef)
            : null;
        $currentProvider = $expected['providerSubscriptionRefSha256'];
        $currentPeriodEnd = $expected['currentPeriodEndEpoch'];
        $periodEnd = $verifiedEvent['currentPeriodEndEpoch'];
        $status = $expected['subscriptionStatus'];
        $outcome = null;

        if ($eventType === 'checkout.session.completed'
            && $status === 'pending'
            && $expected['entitlementStatus'] === 'inactive'
            && is_string($checkoutRefSha256)
            && hash_equals(
                $expected['checkoutSessionRefSha256'],
                $checkoutRefSha256
            )
            && is_string($providerRefSha256)
            && is_int($periodEnd)
            && $periodEnd > $verifiedEvent['occurredAt']
        ) {
            $outcome = 'activated';
        } elseif ($eventType === 'invoice.paid'
            && in_array($status, ['pending', 'active'], true)
            && is_string($providerRefSha256)
            && is_int($periodEnd)
            && $periodEnd > $verifiedEvent['occurredAt']
            && (($status === 'pending' && $currentProvider === null)
                || ($status === 'active'
                    && is_string($currentProvider)
                    && hash_equals($currentProvider, $providerRefSha256)
                    && is_int($currentPeriodEnd)
                    && $periodEnd > $currentPeriodEnd))
        ) {
            $outcome = $status === 'pending' ? 'activated' : 'renewed';
        } elseif ($eventType === 'invoice.payment_failed'
            && $status === 'active'
            && is_string($providerRefSha256)
            && is_string($currentProvider)
            && hash_equals($currentProvider, $providerRefSha256)
            && is_int($periodEnd)
        ) {
            $outcome = 'past_due';
        } elseif ($eventType === 'customer.subscription.deleted'
            && in_array($status, ['active', 'past_due'], true)
            && is_string($providerRefSha256)
            && is_string($currentProvider)
            && hash_equals($currentProvider, $providerRefSha256)
            && ($periodEnd === null || is_int($periodEnd))
        ) {
            $outcome = 'canceled';
        } elseif ($eventType === 'checkout.session.expired'
            && $status === 'pending'
            && $expected['entitlementStatus'] === 'inactive'
            && is_string($checkoutRefSha256)
            && hash_equals(
                $expected['checkoutSessionRefSha256'],
                $checkoutRefSha256
            )
            && $providerRefSha256 === null
            && $periodEnd === null
        ) {
            $outcome = 'expired';
        }

        if ($outcome === null) {
            return self::invalid('subscription_transition_refused');
        }

        return [
            'valid' => true,
            'event' => [
                'verification' => 'verified',
                'replayStatus' => 'unseen',
                'intentReference' => $expected['intentReference'],
                'offerStateSha256' => $expected['offerStateSha256'],
                'outcome' => $outcome,
                'providerSubscriptionRefSha256' => $providerRefSha256,
                'currentPeriodEndEpoch' => $periodEnd,
                'eventEvidenceSha256' =>
                    $verifiedEvent['eventEvidenceSha256'],
                'occurredAt' => $verifiedEvent['occurredAt'],
            ],
            'providerEventType' => $eventType,
            'providerEventRefSha256' => hash(
                'sha256',
                $verifiedEvent['eventRef']
            ),
            'checkoutSessionRefSha256' => $checkoutRefSha256,
            'receivedAt' => $verifiedEvent['receivedAt'],
            'rawProviderReferenceIncluded' => false,
            'rawCheckoutReferenceIncluded' => false,
            'customerDataIncluded' => false,
            'errors' => [],
        ];
    }

    private static function expected(array $value): bool
    {
        if (!self::exactKeys($value, [
            'intentReference', 'offerStateSha256', 'subscriptionStatus',
            'entitlementStatus', 'providerSubscriptionRefSha256',
            'currentPeriodEndEpoch', 'checkoutSessionRefSha256',
        ])
            || !self::intentReference($value['intentReference'] ?? null)
            || !self::sha256($value['offerStateSha256'] ?? null)
            || !self::sha256($value['checkoutSessionRefSha256'] ?? null)
            || !in_array(
                $value['subscriptionStatus'] ?? null,
                ['pending', 'active', 'past_due', 'canceled', 'expired'],
                true
            )
            || !in_array(
                $value['entitlementStatus'] ?? null,
                ['inactive', 'active', 'revoked'],
                true
            )
            || ($value['providerSubscriptionRefSha256'] !== null
                && !self::sha256(
                    $value['providerSubscriptionRefSha256']
                ))
            || ($value['currentPeriodEndEpoch'] !== null
                && !self::timestamp($value['currentPeriodEndEpoch']))
        ) {
            return false;
        }
        return match ($value['subscriptionStatus']) {
            'pending' => $value['entitlementStatus'] === 'inactive'
                && $value['providerSubscriptionRefSha256'] === null
                && $value['currentPeriodEndEpoch'] === null,
            'active' => $value['entitlementStatus'] === 'active'
                && is_string($value['providerSubscriptionRefSha256'])
                && is_int($value['currentPeriodEndEpoch']),
            'past_due', 'canceled' =>
                $value['entitlementStatus'] === 'revoked'
                && is_string($value['providerSubscriptionRefSha256']),
            'expired' => $value['entitlementStatus'] === 'inactive',
            default => false,
        };
    }

    private static function verifiedEvent(array $value): bool
    {
        if (!self::exactKeys($value, [
            'verification', 'replayStatus', 'eventRef', 'eventType',
            'intentReference', 'offerStateSha256', 'checkoutSessionRef',
            'providerSubscriptionRef', 'providerStatus',
            'currentPeriodEndEpoch', 'eventEvidenceSha256',
            'occurredAt', 'receivedAt', 'livemode',
        ])
            || ($value['verification'] ?? null) !== 'verified'
            || !in_array(
                $value['replayStatus'] ?? null,
                ['unseen', 'replayed'],
                true
            )
            || !self::eventReference($value['eventRef'] ?? null)
            || !array_key_exists($value['eventType'] ?? '', self::PROVIDER_STATUS)
            || !self::intentReference($value['intentReference'] ?? null)
            || !self::sha256($value['offerStateSha256'] ?? null)
            || ($value['checkoutSessionRef'] !== null
                && !self::checkoutReference($value['checkoutSessionRef']))
            || ($value['providerSubscriptionRef'] !== null
                && !self::subscriptionReference(
                    $value['providerSubscriptionRef']
                ))
            || !is_string($value['providerStatus'] ?? null)
            || ($value['currentPeriodEndEpoch'] !== null
                && !self::timestamp($value['currentPeriodEndEpoch']))
            || !self::sha256($value['eventEvidenceSha256'] ?? null)
            || !self::timestamp($value['occurredAt'] ?? null)
            || !self::timestamp($value['receivedAt'] ?? null)
            || $value['receivedAt'] < $value['occurredAt']
            || $value['receivedAt'] > $value['occurredAt'] + 2592000
            || ($value['livemode'] ?? null) !== false
        ) {
            return false;
        }

        return match ($value['eventType']) {
            'checkout.session.completed' =>
                is_string($value['checkoutSessionRef'])
                && is_string($value['providerSubscriptionRef'])
                && is_int($value['currentPeriodEndEpoch']),
            'invoice.paid', 'invoice.payment_failed',
            'customer.subscription.deleted' =>
                $value['checkoutSessionRef'] === null
                && is_string($value['providerSubscriptionRef']),
            'checkout.session.expired' =>
                is_string($value['checkoutSessionRef'])
                && $value['providerSubscriptionRef'] === null
                && $value['currentPeriodEndEpoch'] === null,
            default => false,
        };
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private static function intentReference(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Asint_[a-f0-9]{32}\z/D', $value) === 1;
    }

    private static function eventReference(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Aevt_[A-Za-z0-9_]{8,160}\z/D', $value) === 1;
    }

    private static function checkoutReference(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Acs_test_[A-Za-z0-9_]{16,160}\z/D', $value) === 1;
    }

    private static function subscriptionReference(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Asub_[A-Za-z0-9_]{8,160}\z/D', $value) === 1;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
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
            'providerEventType' => '',
            'providerEventRefSha256' => '',
            'checkoutSessionRefSha256' => null,
            'receivedAt' => 0,
            'rawProviderReferenceIncluded' => false,
            'rawCheckoutReferenceIncluded' => false,
            'customerDataIncluded' => false,
            'errors' => [$error],
        ];
    }
}
