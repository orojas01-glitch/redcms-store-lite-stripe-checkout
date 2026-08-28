<?php

declare(strict_types=1);

/** Pure projection of a signature-bound Stripe subscription Event object. */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Raw_Event_Projector
{
    private const API_VERSIONS = [
        '2024-09-30.acacia',
        '2026-07-29.dahlia',
    ];

    public static function project(array $envelope, array $event): array
    {
        $object = is_array($event['data'] ?? null)
            ? ($event['data']['object'] ?? null) : null;
        if (!self::envelope($envelope)
            || !is_array($object)
            || array_is_list($object)
            || ($event['object'] ?? null) !== 'event'
            || !in_array(
                $event['api_version'] ?? null,
                self::API_VERSIONS,
                true
            )
            || ($event['api_version'] ?? null)
                !== ($envelope['apiVersion'] ?? null)
            || ($event['livemode'] ?? null) !== false
            || ($event['type'] ?? null) !== $envelope['eventType']
            || ($event['created'] ?? null) !== $envelope['eventCreatedAt']
            || !self::eventRef($event['id'] ?? null)
            || hash('sha256', $event['id']) !== $envelope['eventRefSha256']
            || hash('sha256', json_encode(
                $object,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            )) !== $envelope['objectProjectionSha256']
            || ($object['object'] ?? null) !== $envelope['objectType']
        ) {
            return self::invalid('subscription_raw_event_refused');
        }

        $type = $event['type'];
        $metadata = null;
        $intentReference = null;
        $offerState = null;
        $checkoutRef = null;
        $subscriptionRef = null;
        $providerStatus = null;
        $periodEnd = null;

        $expectedObjectType = match ($type) {
            'checkout.session.completed', 'checkout.session.expired' =>
                'checkout.session',
            'invoice.paid', 'invoice.payment_failed' => 'invoice',
            'customer.subscription.deleted' => 'subscription',
            default => null,
        };
        if ($expectedObjectType === null
            || $envelope['objectType'] !== $expectedObjectType
        ) {
            return self::invalid('subscription_raw_event_projection_refused');
        }

        if (in_array(
            $type,
            ['checkout.session.completed', 'checkout.session.expired'],
            true
        )) {
            $metadata = $object['metadata'] ?? null;
            $intentReference = $object['client_reference_id'] ?? null;
            $offerState = is_array($metadata)
                ? ($metadata['redcms_offer_state_sha256'] ?? null) : null;
            $checkoutRef = $object['id'] ?? null;
            if ($type === 'checkout.session.completed') {
                $expanded = $object['subscription'] ?? null;
                if (is_array($expanded)) {
                    $subscriptionRef = $expanded['id'] ?? null;
                    $periodEnd = $expanded['current_period_end'] ?? null;
                } elseif (is_string($expanded)) {
                    $subscriptionRef = $expanded;
                }
                if (($object['status'] ?? null) === 'complete'
                    && ($object['payment_status'] ?? null) === 'paid'
                ) {
                    $providerStatus = is_int($periodEnd)
                        ? 'complete_paid'
                        : 'complete_paid_deferred';
                }
            } else {
                $providerStatus =
                    ($object['status'] ?? null) === 'expired'
                    && ($object['payment_status'] ?? null) === 'unpaid'
                    && ($object['subscription'] ?? null) === null
                        ? 'expired' : null;
            }
        } elseif (in_array(
            $type,
            ['invoice.paid', 'invoice.payment_failed'],
            true
        )) {
            $invoice = self::invoiceSubscription(
                $object,
                $event['api_version']
            );
            $metadata = $invoice['metadata'];
            $intentReference = is_array($metadata)
                ? ($metadata['redcms_intent_reference'] ?? null) : null;
            $offerState = is_array($metadata)
                ? ($metadata['redcms_offer_state_sha256'] ?? null) : null;
            $subscriptionRef = $invoice['subscriptionRef'];
            $periodEnd = $invoice['periodEnd'];
            $providerStatus = $type === 'invoice.paid'
                && ($object['status'] ?? null) === 'paid'
                && ($object['paid'] ?? null) === true
                    ? 'paid_active'
                    : ($type === 'invoice.payment_failed'
                        && ($object['paid'] ?? null) === false
                            ? 'payment_failed' : null);
        } elseif ($type === 'customer.subscription.deleted') {
            $metadata = $object['metadata'] ?? null;
            $intentReference = is_array($metadata)
                ? ($metadata['redcms_intent_reference'] ?? null) : null;
            $offerState = is_array($metadata)
                ? ($metadata['redcms_offer_state_sha256'] ?? null) : null;
            $subscriptionRef = $object['id'] ?? null;
            $periodEnd = $object['current_period_end'] ?? null;
            $providerStatus = ($object['status'] ?? null) === 'canceled'
                ? 'canceled' : null;
        }

        if (!self::intent($intentReference)
            || !self::sha($offerState)
            || ($checkoutRef !== null && !self::checkout($checkoutRef))
            || ($subscriptionRef !== null
                && !self::subscription($subscriptionRef))
            || !is_string($providerStatus)
            || (in_array(
                $type,
                ['invoice.paid', 'invoice.payment_failed'],
                true
            ) && !self::time($periodEnd))
            || ($periodEnd !== null && !self::time($periodEnd))
        ) {
            return self::invalid('subscription_raw_event_projection_refused');
        }

        $verifiedEvent = [
            'verification' => 'verified',
            'replayStatus' => 'unseen',
            'eventRef' => $event['id'],
            'eventType' => $type,
            'intentReference' => $intentReference,
            'offerStateSha256' => $offerState,
            'checkoutSessionRef' => $checkoutRef,
            'providerSubscriptionRef' => $subscriptionRef,
            'providerStatus' => $providerStatus,
            'currentPeriodEndEpoch' => $periodEnd,
            'eventEvidenceSha256' => hash('sha256', json_encode([
                'rawBodySha256' => $envelope['rawBodySha256'],
                'signatureEvidenceSha256' =>
                    $envelope['signatureEvidenceSha256'],
                'eventRefSha256' => $envelope['eventRefSha256'],
                'objectProjectionSha256' =>
                    $envelope['objectProjectionSha256'],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'occurredAt' => $event['created'],
            'receivedAt' => $envelope['receivedAt'],
            'livemode' => false,
        ];
        return [
            'valid' => true,
            'verifiedEvent' => $verifiedEvent,
            'rawBodySha256' => $envelope['rawBodySha256'],
            'signatureEvidenceSha256' =>
                $envelope['signatureEvidenceSha256'],
            'customerDataIncluded' => false,
            'paymentMethodDataIncluded' => false,
            'addressDataIncluded' => false,
            'rawEventIncluded' => false,
            'errors' => [],
        ];
    }

    private static function invoiceSubscription(
        array $object,
        string $apiVersion
    ): array {
        $metadata = null;
        $subscriptionRef = null;
        $periodEnd = null;
        if ($apiVersion === '2024-09-30.acacia') {
            $details = $object['subscription_details'] ?? null;
            $metadata = is_array($details)
                ? ($details['metadata'] ?? null) : null;
            $subscriptionRef = $object['subscription'] ?? null;
            $periodEnd = $object['period_end'] ?? null;
        } elseif ($apiVersion === '2026-07-29.dahlia') {
            $parent = $object['parent'] ?? null;
            $details = is_array($parent)
                && !array_is_list($parent)
                && ($parent['type'] ?? null) === 'subscription_details'
                    ? ($parent['subscription_details'] ?? null) : null;
            $metadata = is_array($details)
                ? ($details['metadata'] ?? null) : null;
            $subscriptionRef = is_array($details)
                ? ($details['subscription'] ?? null) : null;
            $periodEnd = self::invoiceLinePeriodEnd(
                $object,
                $subscriptionRef,
                $metadata
            );
        }
        return [
            'metadata' => $metadata,
            'subscriptionRef' => $subscriptionRef,
            'periodEnd' => $periodEnd,
        ];
    }

    private static function invoiceLinePeriodEnd(
        array $object,
        mixed $subscriptionRef,
        mixed $metadata
    ): ?int {
        $lines = $object['lines'] ?? null;
        $data = is_array($lines) && !array_is_list($lines)
            ? ($lines['data'] ?? null) : null;
        if (!self::subscription($subscriptionRef)
            || !is_array($metadata)
            || !self::intent($metadata['redcms_intent_reference'] ?? null)
            || !self::sha($metadata['redcms_offer_state_sha256'] ?? null)
            || !is_array($data)
            || !array_is_list($data)
            || count($data) < 1
            || count($data) > 100
        ) {
            return null;
        }
        $ends = [];
        foreach ($data as $line) {
            if (!is_array($line) || array_is_list($line)) {
                return null;
            }
            $parent = $line['parent'] ?? null;
            $details = is_array($parent)
                && !array_is_list($parent)
                && ($parent['type'] ?? null)
                    === 'subscription_item_details'
                    ? ($parent['subscription_item_details'] ?? null)
                    : null;
            $lineMetadata = $line['metadata'] ?? null;
            $period = $line['period'] ?? null;
            $end = is_array($period) && !array_is_list($period)
                ? ($period['end'] ?? null) : null;
            if (!is_array($details)
                || ($details['subscription'] ?? null) !== $subscriptionRef
                || !is_array($lineMetadata)
                || ($lineMetadata['redcms_intent_reference'] ?? null)
                    !== $metadata['redcms_intent_reference']
                || ($lineMetadata['redcms_offer_state_sha256'] ?? null)
                    !== $metadata['redcms_offer_state_sha256']
                || !self::time($end)
            ) {
                continue;
            }
            $ends[(string) $end] = $end;
        }
        return count($ends) === 1 ? array_values($ends)[0] : null;
    }

    private static function envelope(array $v): bool
    {
        return ($v['valid'] ?? null) === true
            && ($v['verification'] ?? null) === 'verified'
            && ($v['providerEnvironment'] ?? null) === 'sandbox'
            && in_array(
                $v['apiVersion'] ?? null,
                self::API_VERSIONS,
                true
            )
            && self::sha($v['eventRefSha256'] ?? null)
            && self::sha($v['objectProjectionSha256'] ?? null)
            && self::sha($v['rawBodySha256'] ?? null)
            && self::sha($v['signatureEvidenceSha256'] ?? null)
            && self::time($v['eventCreatedAt'] ?? null)
            && self::time($v['receivedAt'] ?? null)
            && in_array($v['eventType'] ?? null, [
                'checkout.session.completed', 'invoice.paid',
                'invoice.payment_failed', 'customer.subscription.deleted',
                'checkout.session.expired',
            ], true)
            && in_array($v['objectType'] ?? null, [
                'checkout.session', 'invoice', 'subscription',
            ], true);
    }
    private static function eventRef(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\Aevt_[A-Za-z0-9_]{8,160}\z/D', $v) === 1;
    }
    private static function intent(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\Asint_[a-f0-9]{32}\z/D', $v) === 1;
    }
    private static function checkout(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\Acs_test_[A-Za-z0-9_]{16,160}\z/D', $v) === 1;
    }
    private static function subscription(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\Asub_[A-Za-z0-9_]{8,160}\z/D', $v) === 1;
    }
    private static function sha(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\A[a-f0-9]{64}\z/D', $v) === 1;
    }
    private static function time(mixed $v): bool
    {
        return is_int($v) && $v >= 1 && $v <= 4102444800;
    }
    private static function invalid(string $error): array
    {
        return [
            'valid' => false, 'verifiedEvent' => null,
            'rawBodySha256' => '', 'signatureEvidenceSha256' => '',
            'customerDataIncluded' => false,
            'paymentMethodDataIncluded' => false,
            'addressDataIncluded' => false, 'rawEventIncluded' => false,
            'errors' => [$error],
        ];
    }
}
