<?php

declare(strict_types=1);

/**
 * One-use provider transport for the exact Stripe Sandbox Checkout POST.
 *
 * D4A adds this provider-capable primitive without a core caller. Construction
 * and loading are inert; exchange is reachable only through a later gate.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Transport
    implements RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange
{
    private const TARGET_URL =
        'https://api.stripe.com/v1/checkout/sessions';

    private ?string $restrictedTestKey;
    private int $calls = 0;

    public function __construct(
        #[SensitiveParameter] string $restrictedTestKey
    ) {
        $prefix = 'rk_' . 'test_';
        if (!str_starts_with($restrictedTestKey, $prefix)
            || strlen($restrictedTestKey) < 24
            || strlen($restrictedTestKey) > 255
            || preg_match('/[^\x21-\x7E]/', $restrictedTestKey) === 1
        ) {
            throw new InvalidArgumentException(
                'restricted_test_credential_refused'
            );
        }
        $this->restrictedTestKey = $restrictedTestKey;
    }

    public function exchange(array $wireRequest): array
    {
        $this->calls++;
        if ($this->calls !== 1 || $this->restrictedTestKey === null) {
            throw new RuntimeException('real_post_transport_already_used');
        }

        $credential = $this->restrictedTestKey;
        $this->restrictedTestKey = null;
        $credentialPair = '';
        $requestHeaders = [];
        $responseHeaders = [];
        $responseBody = '';
        $headerBytes = 0;
        $headerRejected = false;
        $bodyRejected = false;
        $handle = null;

        try {
            if (!self::wireRequest($wireRequest)) {
                throw new RuntimeException('real_post_request_refused');
            }
            if (!extension_loaded('curl')
                || !function_exists('curl_init')
                || !defined('CURL_SSLVERSION_MAX_TLSv1_2')
            ) {
                throw new RuntimeException('real_post_curl_unavailable');
            }

            foreach ($wireRequest['headers'] as $name => $value) {
                $requestHeaders[] = $name . ': ' . $value;
            }
            $requestHeaders[] = 'Expect:';
            $credentialPair = $credential . ':';
            $credential = '';

            $maximumResponseBytes =
                $wireRequest['transport']['maximumResponseBytes'];
            $headerFunction = static function (
                CurlHandle $unused,
                string $line
            ) use (
                &$responseHeaders,
                &$headerBytes,
                &$headerRejected
            ): int {
                $length = strlen($line);
                if ($line === "\r\n") {
                    return $length;
                }
                if (preg_match(
                    '/\AHTTP\/1\.[01] [1-5][0-9]{2}(?: [^\r\n]*)?\r\n\z/D',
                    $line
                ) === 1) {
                    $responseHeaders = [];
                    $headerBytes = 0;
                    return $length;
                }
                if (!str_ends_with($line, "\r\n")) {
                    $headerRejected = true;
                    return 0;
                }
                $line = substr($line, 0, -2);
                $separator = strpos($line, ':');
                if ($separator === false) {
                    $headerRejected = true;
                    return 0;
                }
                $name = strtolower(substr($line, 0, $separator));
                $value = trim(substr($line, $separator + 1));
                $headerBytes += strlen($name) + strlen($value);
                if (count($responseHeaders) >= 32
                    || $headerBytes > 16384
                    || preg_match(
                        '/\A[a-z0-9!#$%&\'()*+.^_`|~-]{1,64}\z/D',
                        $name
                    ) !== 1
                    || strlen($value) > 4096
                    || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
                ) {
                    $headerRejected = true;
                    return 0;
                }
                $responseHeaders[] = [
                    'name' => $name,
                    'value' => $value,
                ];
                return $length;
            };
            $writeFunction = static function (
                CurlHandle $unused,
                string $bytes
            ) use (
                &$responseBody,
                &$bodyRejected,
                $maximumResponseBytes
            ): int {
                $length = strlen($bytes);
                if (strlen($responseBody) + $length
                    > $maximumResponseBytes
                ) {
                    $bodyRejected = true;
                    return 0;
                }
                $responseBody .= $bytes;
                return $length;
            };

            $handle = curl_init();
            if (!$handle instanceof CurlHandle) {
                throw new RuntimeException('real_post_transport_failed');
            }
            $tlsVersion = CURL_SSLVERSION_TLSv1_2
                | CURL_SSLVERSION_MAX_TLSv1_2;
            $options = [
                CURLOPT_URL => self::TARGET_URL,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $wireRequest['body'],
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $credentialPair,
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADERFUNCTION => $headerFunction,
                CURLOPT_WRITEFUNCTION => $writeFunction,
                CURLOPT_FAILONERROR => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_TIMEOUT_MS => 15000,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => $tlsVersion,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_NOSIGNAL => true,
            ];
            if (!curl_setopt_array($handle, $options)
                || curl_exec($handle) !== true
                || $headerRejected
                || $bodyRejected
                || curl_errno($handle) !== CURLE_OK
                || curl_getinfo($handle, CURLINFO_SSL_VERIFYRESULT) !== 0
                || curl_getinfo($handle, CURLINFO_REDIRECT_COUNT) !== 0
                || curl_getinfo($handle, CURLINFO_EFFECTIVE_URL)
                    !== self::TARGET_URL
            ) {
                throw new RuntimeException('real_post_transport_failed');
            }
            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if (!is_int($statusCode)
                || $statusCode < 100
                || $statusCode > 599
            ) {
                throw new RuntimeException('real_post_transport_failed');
            }

            return [
                'statusCode' => $statusCode,
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'tlsVersion' => 'TLSv1.2',
                'redirectCount' => 0,
            ];
        } catch (Throwable $throwable) {
            throw new RuntimeException('real_post_transport_failed');
        } finally {
            $handle = null;
            $requestHeaders = [];
            $responseHeaders = [];
            $responseBody = '';
            $credentialPair = '';
            $credential = '';
        }
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private static function wireRequest(array $request): bool
    {
        return self::exactKeys($request, [
            'method', 'url', 'headers', 'authorization', 'body',
            'bodyBytes', 'bodySha256', 'transport',
        ])
            && ($request['method'] ?? null) === 'POST'
            && ($request['url'] ?? null) === self::TARGET_URL
            && ($request['headers'] ?? null) === [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Stripe-Version' =>
                    $request['headers']['Stripe-Version'] ?? null,
                'Idempotency-Key' =>
                    $request['headers']['Idempotency-Key'] ?? null,
            ]
            && preg_match(
                '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\.[a-z][a-z0-9_]{1,31}\z/D',
                $request['headers']['Stripe-Version'] ?? ''
            ) === 1
            && preg_match(
                '/\Aredcms-checkout-[a-f0-9]{64}\z/D',
                $request['headers']['Idempotency-Key'] ?? ''
            ) === 1
            && ($request['authorization'] ?? null) === [
                'scheme' => 'http-basic-username',
                'secretSettingKey' => 'stripe.secret-key',
                'valueIncluded' => false,
            ]
            && is_string($request['body'] ?? null)
            && is_int($request['bodyBytes'] ?? null)
            && $request['bodyBytes'] === strlen($request['body'])
            && $request['bodyBytes'] >= 1
            && $request['bodyBytes'] <= 65536
            && self::sha256($request['bodySha256'] ?? null)
            && hash_equals(
                $request['bodySha256'],
                hash('sha256', $request['body'])
            )
            && str_contains($request['body'], 'mode=payment')
            && str_contains($request['body'], 'expires_at=')
            && !str_contains($request['body'], 'after_expiration')
            && !str_contains($request['body'], '&customer=')
            && !str_contains($request['body'], '&customer_email=')
            && !str_contains($request['body'], '&customer_creation=')
            && !str_contains(
                $request['body'],
                '&phone_number_collection'
            )
            && !str_contains(
                $request['body'],
                '&shipping_address_collection'
            )
            && !str_contains(
                $request['body'],
                '&billing_address_collection'
            )
            && ($request['transport'] ?? null) === [
                'minimumTlsVersion' => '1.2',
                'verifyPeer' => true,
                'verifyHost' => true,
                'followRedirects' => false,
                'maximumRedirects' => 0,
                'connectTimeoutMilliseconds' => 5000,
                'totalTimeoutMilliseconds' => 15000,
                'maximumResponseBytes' => 262144,
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
}
