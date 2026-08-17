<?php

declare(strict_types=1);

/**
 * Pure P3E-1 gate for a future bounded Stripe Sandbox transport response.
 *
 * The caller must separately bound and decode the raw body. This class accepts
 * only transport evidence plus the closed P3C-1 Checkout projection.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate
{
    public static function accept(
        array $expected,
        array $envelope,
        array $projection
    ): array {
        if (!self::envelope($envelope)) {
            return self::invalid('transport_response_refused');
        }
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer::class,
            false
        )) {
            return self::invalid('checkout_normalizer_unavailable');
        }

        $normalized =
            RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer::normalize(
                $expected,
                $projection
            );
        if (($normalized['valid'] ?? null) !== true
            || !is_array($normalized['checkout'] ?? null)
            || ($normalized['errors'] ?? null) !== []
        ) {
            return self::invalid('checkout_projection_refused');
        }

        $evidence = [
            'requestId' => $envelope['requestId'],
            'bodySha256' => $envelope['bodySha256'],
            'bodyBytes' => $envelope['bodyBytes'],
            'tlsVersion' => $envelope['tlsVersion'],
        ];
        try {
            $encoded = json_encode(
                $evidence,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return self::invalid('transport_evidence_encoding_failed');
        }

        return [
            'valid' => true,
            'checkout' => $normalized['checkout'],
            'responseEvidenceSha256' => hash('sha256', $encoded),
            'errors' => [],
        ];
    }

    private static function envelope(array $envelope): bool
    {
        return self::exactKeys($envelope, [
            'statusCode',
            'contentType',
            'bodyBytes',
            'bodySha256',
            'requestId',
            'tlsVersion',
            'redirectCount',
        ])
            && ($envelope['statusCode'] ?? null) === 200
            && is_string($envelope['contentType'] ?? null)
            && preg_match(
                '/\Aapplication\/json(?:;\s*charset=utf-8)?\z/Di',
                $envelope['contentType']
            ) === 1
            && is_int($envelope['bodyBytes'] ?? null)
            && $envelope['bodyBytes'] >= 2
            && $envelope['bodyBytes'] <= 262144
            && self::sha256($envelope['bodySha256'] ?? null)
            && is_string($envelope['requestId'] ?? null)
            && preg_match(
                '/\Areq_[A-Za-z0-9]{8,128}\z/D',
                $envelope['requestId']
            ) === 1
            && in_array(
                $envelope['tlsVersion'] ?? null,
                ['TLSv1.2', 'TLSv1.3'],
                true
            )
            && ($envelope['redirectCount'] ?? null) === 0;
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
            'checkout' => null,
            'responseEvidenceSha256' => '',
            'errors' => [$error],
        ];
    }
}
