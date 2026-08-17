<?php

declare(strict_types=1);

/**
 * P3E-5 final one-use HTTPS transport restricted to a loopback fixture.
 *
 * The reviewed Stripe URL remains in the wire request. Only this proof class
 * substitutes its path onto an exact 127.0.0.1 fixture origin. No provider
 * hostname or caller-selected path can enter the cURL boundary.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport
{
    private ?string $syntheticSecret;
    private ?string $expectedAuthorizationSha256;
    private int $calls = 0;

    public function __construct(
        #[SensitiveParameter] string $syntheticSecret,
        string $expectedAuthorizationSha256,
        private string $loopbackOrigin,
        private string $caCertificatePem
    ) {
        if (preg_match(
            '/\Asynthetic_p3e5_[a-f0-9]{64}\z/D',
            $syntheticSecret
        ) !== 1
            || preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $expectedAuthorizationSha256
            ) !== 1
            || !self::loopbackOrigin($loopbackOrigin)
            || !self::certificate($caCertificatePem)
        ) {
            throw new InvalidArgumentException(
                'loopback_transport_configuration_invalid'
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
            throw new RuntimeException('loopback_transport_already_used');
        }

        $secret = $this->syntheticSecret;
        $expectedAuthorizationSha256 =
            $this->expectedAuthorizationSha256;
        $this->syntheticSecret = null;
        $this->expectedAuthorizationSha256 = null;
        $authorization = '';
        $requestHeaders = [];
        $responseHeaders = [];
        $responseBody = '';
        $headerBytes = 0;
        $headerRejected = false;
        $bodyRejected = false;
        $handle = null;

        try {
            if (!self::wireRequest($wireRequest)) {
                throw new RuntimeException('loopback_wire_request_refused');
            }
            if (!extension_loaded('curl')
                || !function_exists('curl_init')
                || !defined('CURLOPT_CAINFO_BLOB')
                || !defined('CURL_SSLVERSION_MAX_TLSv1_2')
            ) {
                throw new RuntimeException('loopback_curl_unavailable');
            }

            $authorization = 'Basic ' . base64_encode($secret . ':');
            if (!hash_equals(
                $expectedAuthorizationSha256,
                hash('sha256', $authorization)
            )) {
                throw new RuntimeException(
                    'loopback_authorization_commitment_mismatch'
                );
            }

            foreach ($wireRequest['headers'] as $name => $value) {
                $requestHeaders[] = $name . ': ' . $value;
            }
            $requestHeaders[] = 'Authorization: ' . $authorization;
            $requestHeaders[] = 'Expect:';

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
                        '/\A[a-z0-9!#$%&\'*+.^_`|~-]{1,64}\z/D',
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
                if (strlen($responseBody) + strlen($bytes)
                    > $maximumResponseBytes
                ) {
                    $bodyRejected = true;
                    return 0;
                }
                $responseBody .= $bytes;
                return strlen($bytes);
            };

            $handle = curl_init();
            if (!$handle instanceof CurlHandle) {
                throw new RuntimeException('loopback_transport_failed');
            }
            $target = $this->loopbackOrigin . '/v1/checkout/sessions';
            $tlsVersion = CURL_SSLVERSION_TLSv1_2
                | CURL_SSLVERSION_MAX_TLSv1_2;
            $options = [
                CURLOPT_URL => $target,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $wireRequest['body'],
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADERFUNCTION => $headerFunction,
                CURLOPT_WRITEFUNCTION => $writeFunction,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT_MS =>
                    $wireRequest['transport']['connectTimeoutMilliseconds'],
                CURLOPT_TIMEOUT_MS =>
                    $wireRequest['transport']['totalTimeoutMilliseconds'],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => $tlsVersion,
                CURLOPT_CAINFO_BLOB => $this->caCertificatePem,
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
                || curl_getinfo($handle, CURLINFO_EFFECTIVE_URL) !== $target
            ) {
                throw new RuntimeException('loopback_transport_failed');
            }
            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if (!is_int($statusCode)
                || $statusCode < 100
                || $statusCode > 599
            ) {
                throw new RuntimeException('loopback_transport_failed');
            }

            return [
                'statusCode' => $statusCode,
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'tlsVersion' => 'TLSv1.2',
                'redirectCount' => 0,
            ];
        } catch (Throwable $throwable) {
            throw new RuntimeException('loopback_transport_failed');
        } finally {
            $handle = null;
            $requestHeaders = [];
            $authorization = '';
            $secret = '';
            $expectedAuthorizationSha256 = '';
        }
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private static function loopbackOrigin(string $origin): bool
    {
        if (preg_match(
            '/\Ahttps:\/\/127\.0\.0\.1:([1-9][0-9]{3,4})\z/D',
            $origin,
            $matches
        ) !== 1) {
            return false;
        }
        $port = (int) $matches[1];
        return $port >= 1024 && $port <= 65535;
    }

    private static function certificate(string $certificate): bool
    {
        return strlen($certificate) >= 256
            && strlen($certificate) <= 32768
            && preg_match(
                '/\A-----BEGIN CERTIFICATE-----\r?\n'
                    . '[A-Za-z0-9+\/=\r\n]+'
                    . '-----END CERTIFICATE-----\r?\n?\z/D',
                $certificate
            ) === 1;
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
