<?php

declare(strict_types=1);

/**
 * Pure P3C-3 planner for one immutable adapter event receipt.
 *
 * It accepts only the already-verified P3C-1 projection, preserves hashes
 * rather than transport material, and performs no database, request, secret,
 * SDK, provider, Store Lite, or network work.
 */
final class RED_CMS_Store_Lite_Stripe_Event_Receipt_Record_Planner
{
    public static function plan(
        array $expected,
        array $verifiedEvent,
        array $evidence
    ): array {
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer::class,
            false
        )) {
            return self::invalid('event_normalizer_unavailable');
        }

        if (!self::exactKeys($evidence, [
            'attemptRecordId',
            'clientScopeSha256',
            'transportBodySha256',
            'verificationEvidenceSha256',
        ])
            || !is_int($evidence['attemptRecordId'] ?? null)
            || $evidence['attemptRecordId'] < 1
            || !self::sha256($evidence['clientScopeSha256'] ?? null)
            || !self::sha256($evidence['transportBodySha256'] ?? null)
            || !self::sha256(
                $evidence['verificationEvidenceSha256'] ?? null
            )
        ) {
            return self::invalid('event_receipt_evidence_invalid');
        }

        $normalized =
            RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer::normalize(
                $expected,
                $verifiedEvent
            );
        if (($normalized['valid'] ?? null) !== true
            || !is_array($normalized['event'] ?? null)
            || ($normalized['errors'] ?? null) !== []
        ) {
            return self::invalid('verified_event_refused');
        }

        $record = [
            'attemptRecordId' => $evidence['attemptRecordId'],
            'clientScopeSha256' => $evidence['clientScopeSha256'],
            'providerEnvironment' => 'sandbox',
            'providerEventRef' => $verifiedEvent['eventRef'],
            'eventEvidenceSha256' =>
                $normalized['event']['eventEvidenceSha256'],
            'transportBodySha256' => $evidence['transportBodySha256'],
            'verificationEvidenceSha256' =>
                $evidence['verificationEvidenceSha256'],
            'checkoutSessionRef' => $verifiedEvent['checkoutSessionRef'],
            'orderId' => $normalized['event']['orderId'],
            'orderSnapshotSha256' =>
                $normalized['event']['orderSnapshotSha256'],
            'providerEventType' => $verifiedEvent['eventType'],
            'providerStatus' => $verifiedEvent['providerStatus'],
            'normalizedOutcome' => $normalized['event']['outcome'],
            'amountMinor' => $normalized['event']['amountMinor'],
            'currency' => $normalized['event']['currency'],
            'replayStatus' => 'unseen',
            'processingStatus' => 'normalized',
            'occurredAt' => $normalized['event']['occurredAt'],
            'receivedAt' => $verifiedEvent['receivedAt'],
        ];
        try {
            $encoded = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return self::invalid('event_receipt_encoding_failed');
        }

        return [
            'valid' => true,
            'record' => $record,
            'planSha256' => hash('sha256', $encoded),
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

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function invalid(string $error): array
    {
        return [
            'valid' => false,
            'record' => null,
            'planSha256' => '',
            'errors' => [$error],
        ];
    }
}
