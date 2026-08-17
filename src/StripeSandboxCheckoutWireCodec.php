<?php

declare(strict_types=1);

/**
 * P3E-3 pure Stripe form encoder and bounded synthetic-response decoder.
 *
 * It has no credential resolver or transport. It converts reviewed P3E-1
 * plans to canonical bytes and raw synthetic response bytes to P3E-2
 * transcripts only.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec
{
    private const MAX_REQUEST_BYTES = 65536;
    private const MAX_RESPONSE_BYTES = 262144;

    public static function encode(array $checkout, array $policy): array
    {
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::class,
            false
        )) {
            return self::encodeInvalid('transport_planner_unavailable');
        }
        $planned =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::plan(
                $checkout,
                $policy
            );
        if (!self::planned($planned)) {
            return self::encodeInvalid('checkout_plan_refused');
        }

        $form = $planned['plan']['request']['form'] ?? null;
        if (!is_array($form) || array_is_list($form) || $form === []) {
            return self::encodeInvalid('request_form_refused');
        }
        $pairs = [];
        foreach ($form as $key => $value) {
            if (!is_string($key)
                || $key === ''
                || !is_string($value)
                || preg_match('/[\x00-\x1F\x7F]/', $key . $value) === 1
            ) {
                return self::encodeInvalid('request_form_refused');
            }
            $pairs[] = urlencode($key) . '=' . urlencode($value);
        }
        $body = implode('&', $pairs);
        $bodyBytes = strlen($body);
        if ($bodyBytes < 1 || $bodyBytes > self::MAX_REQUEST_BYTES) {
            return self::encodeInvalid('request_body_refused');
        }

        $request = $planned['plan']['request'];
        return [
            'valid' => true,
            'wireRequest' => [
                'method' => $request['method'],
                'url' => $request['url'],
                'headers' => $request['headers'],
                'authorization' => $request['authorization'],
                'body' => $body,
                'bodyBytes' => $bodyBytes,
                'bodySha256' => hash('sha256', $body),
                'transport' => $planned['plan']['transport'],
            ],
            'planSha256' => $planned['planSha256'],
            'errors' => [],
        ];
    }

    public static function decode(array $wire): array
    {
        if (!self::wireShape($wire)) {
            return self::decodeInvalid('wire_response_invalid');
        }
        if (strlen($wire['body']) > self::MAX_RESPONSE_BYTES) {
            return self::decodedIndeterminate('response_too_large');
        }

        $headers = self::headers($wire['headers']);
        if ($headers === null) {
            return self::decodedIndeterminate('response_unusable');
        }
        if (!in_array($wire['tlsVersion'], ['TLSv1.2', 'TLSv1.3'], true)) {
            return self::decodedIndeterminate('tls_failure');
        }
        if ($wire['redirectCount'] !== 0) {
            return self::decodedIndeterminate('response_unusable');
        }

        $statusCode = $wire['statusCode'];
        if ($statusCode >= 500 && $statusCode <= 599) {
            return self::decodedIndeterminate('provider_5xx');
        }
        if ($statusCode !== 200
            && ($statusCode < 400 || $statusCode > 499)
        ) {
            return self::decodedIndeterminate('response_unusable');
        }

        $envelope = [
            'statusCode' => $statusCode,
            'contentType' => $headers['content-type'],
            'bodyBytes' => strlen($wire['body']),
            'bodySha256' => hash('sha256', $wire['body']),
            'requestId' => $headers['request-id'],
            'tlsVersion' => $wire['tlsVersion'],
            'redirectCount' => $wire['redirectCount'],
        ];
        if ($statusCode >= 400) {
            return self::decodedResponse($envelope, []);
        }

        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::class,
            false
        )) {
            return self::decodeInvalid('json_decoder_unavailable');
        }
        $decoded =
            RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode(
                $wire['body']
            );
        if (($decoded['valid'] ?? null) !== true
            || !is_array($decoded['value'] ?? null)
            || array_is_list($decoded['value'])
            || ($decoded['errors'] ?? null) !== []
        ) {
            return self::decodedIndeterminate('response_unusable');
        }
        $value = $decoded['value'];
        $projection = [];
        foreach ([
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
        ] as $key) {
            $projection[$key] = $value[$key] ?? null;
        }
        return self::decodedResponse($envelope, $projection);
    }

    private static function planned(array $planned): bool
    {
        return self::exactKeys($planned, [
            'valid', 'plan', 'planSha256', 'errors',
        ])
            && ($planned['valid'] ?? null) === true
            && is_array($planned['plan'] ?? null)
            && self::sha256($planned['planSha256'] ?? null)
            && ($planned['errors'] ?? null) === [];
    }

    private static function wireShape(array $wire): bool
    {
        return self::exactKeys($wire, [
            'statusCode', 'headers', 'body', 'tlsVersion', 'redirectCount',
        ])
            && is_int($wire['statusCode'] ?? null)
            && $wire['statusCode'] >= 100
            && $wire['statusCode'] <= 599
            && is_array($wire['headers'] ?? null)
            && array_is_list($wire['headers'])
            && is_string($wire['body'] ?? null)
            && is_string($wire['tlsVersion'] ?? null)
            && is_int($wire['redirectCount'] ?? null)
            && $wire['redirectCount'] >= 0
            && $wire['redirectCount'] <= 10;
    }

    private static function headers(array $headers): ?array
    {
        if (count($headers) < 2 || count($headers) > 32) {
            return null;
        }
        $normalized = [];
        $bytes = 0;
        foreach ($headers as $header) {
            if (!is_array($header)
                || !self::exactKeys($header, ['name', 'value'])
                || !is_string($header['name'] ?? null)
                || preg_match(
                    '/\A[a-z0-9!#$%&\'*+.^_`|~-]{1,64}\z/D',
                    $header['name']
                ) !== 1
                || !is_string($header['value'] ?? null)
                || strlen($header['value']) > 4096
                || preg_match(
                    '/[\x00-\x1F\x7F]/',
                    $header['value']
                ) === 1
            ) {
                return null;
            }
            $bytes += strlen($header['name']) + strlen($header['value']);
            if ($bytes > 16384) {
                return null;
            }
            if (array_key_exists($header['name'], $normalized)) {
                if (in_array(
                    $header['name'],
                    ['content-type', 'request-id'],
                    true
                )) {
                    return null;
                }
                continue;
            }
            $normalized[$header['name']] = $header['value'];
        }

        if (!isset($normalized['content-type'], $normalized['request-id'])
            || preg_match(
                '/\Aapplication\/json(?:;\s*charset=utf-8)?\z/Di',
                $normalized['content-type']
            ) !== 1
            || preg_match(
                '/\Areq_[A-Za-z0-9]{8,128}\z/D',
                $normalized['request-id']
            ) !== 1
        ) {
            return null;
        }
        return $normalized;
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

    private static function encodeInvalid(string $error): array
    {
        return [
            'valid' => false,
            'wireRequest' => null,
            'planSha256' => '',
            'errors' => [$error],
        ];
    }

    private static function decodeInvalid(string $error): array
    {
        return [
            'valid' => false,
            'transcript' => null,
            'errors' => [$error],
        ];
    }

    private static function decodedIndeterminate(string $code): array
    {
        return [
            'valid' => true,
            'transcript' => [
                'outcome' => 'indeterminate',
                'code' => $code,
                'envelope' => null,
                'projection' => null,
            ],
            'errors' => [],
        ];
    }

    private static function decodedResponse(
        array $envelope,
        array $projection
    ): array {
        return [
            'valid' => true,
            'transcript' => [
                'outcome' => 'response',
                'code' => null,
                'envelope' => $envelope,
                'projection' => $projection,
            ],
            'errors' => [],
        ];
    }
}
