<?php

declare(strict_types=1);

/**
 * Pure P3C-2 planner for one adapter-owned checkout-attempt record.
 *
 * It reuses the P3C-1 response validator, then deliberately drops the
 * transient hosted Checkout URL. It performs no persistence, database,
 * request, secret, SDK, provider, Store Lite, or network work.
 */
final class RED_CMS_Store_Lite_Stripe_Checkout_Attempt_Record_Planner
{
    public static function plan(
        array $expected,
        array $response,
        array $evidence
    ): array {
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer::class,
            false
        )) {
            return self::invalid('checkout_normalizer_unavailable');
        }

        if (!self::exactKeys($evidence, [
            'clientScopeSha256',
            'responseEvidenceSha256',
            'createdAt',
            'expiresAt',
        ])
            || !self::sha256($evidence['clientScopeSha256'] ?? null)
            || !self::sha256($evidence['responseEvidenceSha256'] ?? null)
            || !self::timestamp($evidence['createdAt'] ?? null)
            || !self::timestamp($evidence['expiresAt'] ?? null)
            || $evidence['expiresAt'] <= $evidence['createdAt']
            || $evidence['expiresAt'] > $evidence['createdAt'] + 86400
        ) {
            return self::invalid('attempt_evidence_invalid');
        }

        $checkout =
            RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer::normalize(
                $expected,
                $response
            );
        if (($checkout['valid'] ?? null) !== true
            || !is_array($checkout['checkout'] ?? null)
            || !self::exactKeys($checkout['checkout'], [
                'checkoutSessionRef',
                'checkoutUrl',
            ])
            || ($checkout['errors'] ?? null) !== []
        ) {
            return self::invalid('checkout_response_refused');
        }

        $record = [
            'clientScopeSha256' => $evidence['clientScopeSha256'],
            'orderId' => $expected['orderId'],
            'orderSnapshotSha256' => $expected['orderSnapshotSha256'],
            'idempotencySha256' => $expected['idempotencySha256'],
            'checkoutSessionRef' =>
                $checkout['checkout']['checkoutSessionRef'],
            'amountMinor' => $expected['amountMinor'],
            'currency' => $expected['currency'],
            'attemptStatus' => 'created',
            'responseEvidenceSha256' =>
                $evidence['responseEvidenceSha256'],
            'createdAt' => $evidence['createdAt'],
            'expiresAt' => $evidence['expiresAt'],
        ];
        try {
            $encoded = json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return self::invalid('attempt_record_encoding_failed');
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

    private static function timestamp(mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 4102444800;
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
