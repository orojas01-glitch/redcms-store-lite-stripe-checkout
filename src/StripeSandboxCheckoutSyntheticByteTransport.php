<?php

declare(strict_types=1);

/**
 * P3E-4 final one-use in-memory byte transport with synthetic credential.
 *
 * It assembles one Basic authorization value privately, verifies only its
 * precommitted SHA-256, discards credential material, and returns preloaded
 * synthetic response bytes. It has no network implementation.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Byte_Transport
{
    private ?string $syntheticSecret;
    private ?string $expectedAuthorizationSha256;
    private int $calls = 0;

    public function __construct(
        #[SensitiveParameter] string $syntheticSecret,
        string $expectedAuthorizationSha256,
        private array $wireResponse
    ) {
        if (preg_match(
            '/\Asynthetic_p3e4_[a-f0-9]{64}\z/D',
            $syntheticSecret
        ) !== 1
            || preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $expectedAuthorizationSha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'synthetic_transport_configuration_invalid'
            );
        }
        $this->syntheticSecret = $syntheticSecret;
        $this->expectedAuthorizationSha256 =
            $expectedAuthorizationSha256;
    }

    public function exchange(array $wireRequest): array
    {
        $this->calls++;
        if ($this->calls !== 1
            || $this->syntheticSecret === null
            || $this->expectedAuthorizationSha256 === null
        ) {
            throw new RuntimeException('synthetic_transport_already_used');
        }

        $secret = $this->syntheticSecret;
        $expectedAuthorizationSha256 =
            $this->expectedAuthorizationSha256;
        $this->syntheticSecret = null;
        $this->expectedAuthorizationSha256 = null;
        $authorization = '';

        try {
            if (!self::wireRequest($wireRequest)) {
                throw new RuntimeException('synthetic_wire_request_refused');
            }
            $authorization = 'Basic ' . base64_encode($secret . ':');
            if (!hash_equals(
                $expectedAuthorizationSha256,
                hash('sha256', $authorization)
            )) {
                throw new RuntimeException(
                    'synthetic_authorization_commitment_mismatch'
                );
            }
            return $this->wireResponse;
        } finally {
            $authorization = '';
            $secret = '';
            $expectedAuthorizationSha256 = '';
        }
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private static function wireRequest(array $request): bool
    {
        if (!self::exactKeys($request, [
            'method',
            'url',
            'headers',
            'authorization',
            'body',
            'bodyBytes',
            'bodySha256',
            'transport',
        ])
            || ($request['method'] ?? null) !== 'POST'
            || ($request['url'] ?? null)
                !== 'https://api.stripe.com/v1/checkout/sessions'
            || !is_array($request['headers'] ?? null)
            || !self::exactKeys($request['headers'], [
                'Accept',
                'Content-Type',
                'Stripe-Version',
                'Idempotency-Key',
            ])
            || $request['headers']['Accept'] !== 'application/json'
            || $request['headers']['Content-Type']
                !== 'application/x-www-form-urlencoded'
            || preg_match(
                '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\.[a-z][a-z0-9_]{1,31}\z/D',
                $request['headers']['Stripe-Version'] ?? ''
            ) !== 1
            || preg_match(
                '/\Aredcms-checkout-[a-f0-9]{64}\z/D',
                $request['headers']['Idempotency-Key'] ?? ''
            ) !== 1
            || array_key_exists('Authorization', $request['headers'])
            || ($request['authorization'] ?? null) !== [
                'scheme' => 'http-basic-username',
                'secretSettingKey' => 'stripe.secret-key',
                'valueIncluded' => false,
            ]
            || !is_string($request['body'] ?? null)
            || strlen($request['body']) < 1
            || strlen($request['body']) > 65536
            || preg_match('/[\x00-\x1F\x7F]/', $request['body']) === 1
            || !is_int($request['bodyBytes'] ?? null)
            || $request['bodyBytes'] !== strlen($request['body'])
            || !is_string($request['bodySha256'] ?? null)
            || !hash_equals(
                hash('sha256', $request['body']),
                $request['bodySha256']
            )
            || ($request['transport'] ?? null) !== [
                'minimumTlsVersion' => '1.2',
                'verifyPeer' => true,
                'verifyHost' => true,
                'followRedirects' => false,
                'maximumRedirects' => 0,
                'connectTimeoutMilliseconds' => 5000,
                'totalTimeoutMilliseconds' => 15000,
                'maximumResponseBytes' => 262144,
            ]
        ) {
            return false;
        }
        return true;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }
}
