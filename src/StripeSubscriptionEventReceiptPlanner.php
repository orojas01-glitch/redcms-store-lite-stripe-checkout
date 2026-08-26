<?php

declare(strict_types=1);

/** Pure claim/completion planner for hash-only subscription webhook receipts. */
final class RED_CMS_Store_Lite_Stripe_Subscription_Event_Receipt_Planner
{
    private const TYPES = [
        'checkout.session.completed', 'invoice.paid',
        'invoice.payment_failed', 'customer.subscription.deleted',
        'checkout.session.expired',
    ];

    public static function claim(array $verified): array
    {
        if (!self::exact($verified, [
            'eventRefSha256', 'rawBodySha256', 'signatureEvidenceSha256',
            'eventType', 'signedAt', 'receivedAt',
        ])
            || !self::sha($verified['eventRefSha256'] ?? null)
            || !self::sha($verified['rawBodySha256'] ?? null)
            || !self::sha($verified['signatureEvidenceSha256'] ?? null)
            || !in_array($verified['eventType'] ?? '', self::TYPES, true)
            || !self::time($verified['signedAt'] ?? null)
            || !self::time($verified['receivedAt'] ?? null)
            || abs($verified['receivedAt'] - $verified['signedAt']) > 300
        ) {
            return self::invalid('receipt_claim_refused');
        }
        $record = [
            'eventRefSha256' => $verified['eventRefSha256'],
            'rawBodySha256' => $verified['rawBodySha256'],
            'signatureEvidenceSha256' =>
                $verified['signatureEvidenceSha256'],
            'providerEventType' => $verified['eventType'],
            'claimStateSha256' => self::hash([
                'purpose' => 'subscription-event-receipt-claim',
                'verified' => $verified,
            ]),
            'receiptStatus' => 'verified',
            'intentReference' => null,
            'eventEvidenceSha256' => null,
            'lifecycleResultSha256' => null,
            'signedAtEpoch' => $verified['signedAt'],
            'receivedAtEpoch' => $verified['receivedAt'],
            'completedAtEpoch' => null,
        ];
        return self::valid($record, 'claim');
    }

    public static function complete(
        array $claim,
        array $completion
    ): array {
        if (($claim['valid'] ?? null) !== true
            || ($claim['operation'] ?? '') !== 'claim'
            || !is_array($claim['record'] ?? null)
            || ($claim['record']['receiptStatus'] ?? '') !== 'verified'
            || !self::exact($completion, [
                'status', 'intentReference', 'eventEvidenceSha256',
                'lifecycleResultSha256', 'completedAtEpoch',
            ])
            || !in_array($completion['status'] ?? '', ['applied', 'refused'], true)
            || !self::intent($completion['intentReference'] ?? null)
            || !self::sha($completion['eventEvidenceSha256'] ?? null)
            || !self::sha($completion['lifecycleResultSha256'] ?? null)
            || !self::time($completion['completedAtEpoch'] ?? null)
            || $completion['completedAtEpoch']
                < $claim['record']['receivedAtEpoch']
        ) {
            return self::invalid('receipt_completion_refused');
        }
        $record = $claim['record'];
        $record['receiptStatus'] = $completion['status'];
        $record['intentReference'] = $completion['intentReference'];
        $record['eventEvidenceSha256'] =
            $completion['eventEvidenceSha256'];
        $record['lifecycleResultSha256'] =
            $completion['lifecycleResultSha256'];
        $record['completedAtEpoch'] = $completion['completedAtEpoch'];
        return self::valid($record, 'complete');
    }

    private static function valid(array $record, string $operation): array
    {
        return [
            'valid' => true,
            'operation' => $operation,
            'record' => $record,
            'planSha256' => self::hash([
                'purpose' => 'subscription-event-receipt-' . $operation,
                'record' => $record,
            ]),
            'rawBodyIncluded' => false,
            'signatureIncluded' => false,
            'secretIncluded' => false,
            'errors' => [],
        ];
    }

    private static function invalid(string $error): array
    {
        return [
            'valid' => false, 'operation' => '', 'record' => null,
            'planSha256' => '', 'rawBodyIncluded' => false,
            'signatureIncluded' => false, 'secretIncluded' => false,
            'errors' => [$error],
        ];
    }

    private static function exact(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);
        return $actual === $keys;
    }
    private static function sha(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\A[a-f0-9]{64}\z/D', $v) === 1;
    }
    private static function intent(mixed $v): bool
    {
        return is_string($v)
            && preg_match('/\Asint_[a-f0-9]{32}\z/D', $v) === 1;
    }
    private static function time(mixed $v): bool
    {
        return is_int($v) && $v >= 1 && $v <= 4102444800;
    }
    private static function hash(array $v): string
    {
        return hash('sha256', json_encode(
            $v,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        ));
    }
}
