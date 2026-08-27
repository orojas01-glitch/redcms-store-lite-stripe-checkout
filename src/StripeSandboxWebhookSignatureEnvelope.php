<?php

declare(strict_types=1);

/**
 * Verify one bounded Stripe Sandbox v1 webhook signature envelope.
 *
 * This pure class receives the untouched raw body from a future core ingress
 * boundary. It does not read request globals, expose decoded provider data, or
 * resolve the endpoint secret itself.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope
{
    private const API_VERSIONS = [
        '2024-09-30.acacia',
        '2026-07-29.dahlia',
    ];
    private const MAX_BODY_BYTES = 262144;
    private const MAX_HEADER_BYTES = 4096;
    private const TOLERANCE_SECONDS = 300;
    private const EVENT_OBJECTS = [
        'checkout.session.completed' => 'checkout.session',
        'invoice.paid' => 'invoice',
        'invoice.payment_failed' => 'invoice',
        'customer.subscription.deleted' => 'subscription',
        'checkout.session.expired' => 'checkout.session',
    ];

    public static function verify(
        string $rawBody,
        string $signatureHeader,
        #[SensitiveParameter] string $endpointSecret,
        int $receivedAt
    ): array {
        $bodyBytes = strlen($rawBody);
        if ($bodyBytes < 2
            || $bodyBytes > self::MAX_BODY_BYTES
            || preg_match('//u', $rawBody) !== 1
            || strlen($signatureHeader) < 1
            || strlen($signatureHeader) > self::MAX_HEADER_BYTES
            || preg_match('/[^\x21-\x7E]/', $signatureHeader) === 1
            || !str_starts_with($endpointSecret, 'whsec_')
            || strlen($endpointSecret) < 16
            || strlen($endpointSecret) > 255
            || preg_match('/[^\x21-\x7E]/', $endpointSecret) === 1
            || !self::timestamp($receivedAt)
        ) {
            $endpointSecret = '';
            return self::invalid('webhook_input_refused');
        }

        $header = self::parseSignatureHeader($signatureHeader);
        if (($header['valid'] ?? false) !== true) {
            $endpointSecret = '';
            return self::invalid('webhook_signature_refused');
        }
        $signedAt = $header['timestamp'];
        if (abs($receivedAt - $signedAt) > self::TOLERANCE_SECONDS) {
            $endpointSecret = '';
            return self::invalid('webhook_signature_refused');
        }

        $expected = hash_hmac(
            'sha256',
            $signedAt . '.' . $rawBody,
            $endpointSecret
        );
        $endpointSecret = '';
        $matched = '';
        foreach ($header['signatures'] as $signature) {
            if (hash_equals($expected, $signature)) {
                $matched = $signature;
            }
        }
        $expected = '';
        if ($matched === '') {
            return self::invalid('webhook_signature_refused');
        }

        $decoded =
            RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode($rawBody);
        $event = $decoded['value'] ?? null;
        $data = is_array($event) ? ($event['data'] ?? null) : null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;
        $eventType = is_array($event) ? ($event['type'] ?? null) : null;
        $expectedObject = is_string($eventType)
            ? (self::EVENT_OBJECTS[$eventType] ?? null)
            : null;
        if (($decoded['valid'] ?? false) !== true
            || !is_array($event)
            || array_is_list($event)
            || !is_array($data)
            || array_is_list($data)
            || !is_array($object)
            || array_is_list($object)
            || !self::eventReference($event['id'] ?? null)
            || ($event['object'] ?? null) !== 'event'
            || !in_array(
                $event['api_version'] ?? null,
                self::API_VERSIONS,
                true
            )
            || !self::timestamp($event['created'] ?? null)
            || $event['created'] > $signedAt
            || $signedAt - $event['created'] > 2592000
            || ($event['livemode'] ?? null) !== false
            || !is_string($eventType)
            || !is_string($expectedObject)
            || ($object['object'] ?? null) !== $expectedObject
        ) {
            $matched = '';
            return self::invalid('webhook_event_refused');
        }

        $rawBodySha256 = hash('sha256', $rawBody);
        $eventRefSha256 = hash('sha256', $event['id']);
        $objectProjectionSha256 = hash('sha256', json_encode(
            $object,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        ));
        $signatureEvidenceSha256 = hash('sha256', json_encode([
            'schema' => 1,
            'purpose' => 'stripe-sandbox-webhook-signature-envelope',
            'signedAt' => $signedAt,
            'receivedAt' => $receivedAt,
            'rawBodySha256' => $rawBodySha256,
            'matchedSignatureSha256' => hash('sha256', $matched),
            'eventRefSha256' => $eventRefSha256,
            'eventType' => $eventType,
            'apiVersion' => $event['api_version'],
            'objectProjectionSha256' => $objectProjectionSha256,
        ], JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR));
        $matched = '';

        return [
            'valid' => true,
            'verification' => 'verified',
            'providerEnvironment' => 'sandbox',
            'apiVersion' => $event['api_version'],
            'eventType' => $eventType,
            'eventRefSha256' => $eventRefSha256,
            'eventCreatedAt' => $event['created'],
            'objectType' => $expectedObject,
            'objectProjectionSha256' => $objectProjectionSha256,
            'signedAt' => $signedAt,
            'receivedAt' => $receivedAt,
            'signatureAgeSeconds' => abs($receivedAt - $signedAt),
            'signatureCount' => count($header['signatures']),
            'bodyBytes' => $bodyBytes,
            'rawBodySha256' => $rawBodySha256,
            'signatureEvidenceSha256' => $signatureEvidenceSha256,
            'rawBodyIncluded' => false,
            'signatureHeaderIncluded' => false,
            'endpointSecretIncluded' => false,
            'decodedEventIncluded' => false,
            'customerDataIncluded' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'routeExposure' => false,
            'errors' => [],
        ];
    }

    private static function parseSignatureHeader(string $value): array
    {
        $parts = explode(',', $value);
        if (count($parts) < 2 || count($parts) > 16) {
            return ['valid' => false];
        }
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            $separator = strpos($part, '=');
            if ($separator === false || $separator < 1) {
                return ['valid' => false];
            }
            $scheme = substr($part, 0, $separator);
            $candidate = substr($part, $separator + 1);
            if (preg_match('/\A[a-z][a-z0-9]{0,15}\z/D', $scheme) !== 1
                || $candidate === ''
                || strlen($candidate) > 256
                || preg_match('/\A[0-9A-Za-z]+\z/D', $candidate) !== 1
            ) {
                return ['valid' => false];
            }
            if ($scheme === 't') {
                if ($timestamp !== null
                    || preg_match('/\A[1-9][0-9]{0,9}\z/D', $candidate) !== 1
                ) {
                    return ['valid' => false];
                }
                $timestamp = (int) $candidate;
            } elseif ($scheme === 'v1') {
                if (count($signatures) >= 8
                    || preg_match('/\A[a-f0-9]{64}\z/D', $candidate) !== 1
                    || in_array($candidate, $signatures, true)
                ) {
                    return ['valid' => false];
                }
                $signatures[] = $candidate;
            }
        }
        return is_int($timestamp)
            && self::timestamp($timestamp)
            && $signatures !== []
                ? [
                    'valid' => true,
                    'timestamp' => $timestamp,
                    'signatures' => $signatures,
                ]
                : ['valid' => false];
    }

    private static function eventReference(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\Aevt_[A-Za-z0-9_]{8,160}\z/D', $value) === 1;
    }

    private static function timestamp(mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 4102444800;
    }

    private static function invalid(string $error): array
    {
        return [
            'valid' => false,
            'verification' => 'refused',
            'providerEnvironment' => 'sandbox',
            'apiVersion' => '',
            'eventType' => '',
            'eventRefSha256' => '',
            'eventCreatedAt' => 0,
            'objectType' => '',
            'objectProjectionSha256' => '',
            'signedAt' => 0,
            'receivedAt' => 0,
            'signatureAgeSeconds' => 0,
            'signatureCount' => 0,
            'bodyBytes' => 0,
            'rawBodySha256' => '',
            'signatureEvidenceSha256' => '',
            'rawBodyIncluded' => false,
            'signatureHeaderIncluded' => false,
            'endpointSecretIncluded' => false,
            'decodedEventIncluded' => false,
            'customerDataIncluded' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'routeExposure' => false,
            'errors' => [$error],
        ];
    }
}
