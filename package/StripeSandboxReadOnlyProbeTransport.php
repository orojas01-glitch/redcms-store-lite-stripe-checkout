<?php

declare(strict_types=1);

/**
 * P3E-8B1 provider-capable primitive for one exact read-only sandbox probe.
 *
 * This source remains outside the installable package and has no caller in
 * P3E-8B1. Requiring the class performs no credential resolution or network
 * work. A later, separately approved runner must supply the already-claimed
 * exact plan and the owning package's restricted test credential.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport
{
    private const TARGET_URL =
        'https://api.stripe.com/v1/checkout/sessions/'
        . 'cs_test_redcms_readiness_probe';

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

    /**
     * Execute the exact P3E-6 plan once and return only bounded transport facts.
     */
    public function exchange(array $contactPlan): array
    {
        $this->calls++;
        if ($this->calls !== 1 || $this->restrictedTestKey === null) {
            throw new RuntimeException('provider_transport_already_used');
        }

        $credential = $this->restrictedTestKey;
        $this->restrictedTestKey = null;
        $credentialPair = '';
        $handle = null;
        $responseBytes = 0;
        $headerBytes = 0;
        $bodyRejected = false;
        $headerRejected = false;

        try {
            if (!self::contactPlan($contactPlan)) {
                throw new RuntimeException('provider_contact_plan_refused');
            }
            if (!extension_loaded('curl')
                || !function_exists('curl_init')
                || !defined('CURL_SSLVERSION_TLSv1_2')
            ) {
                throw new RuntimeException('provider_curl_unavailable');
            }

            $maximumResponseBytes =
                $contactPlan['maximumResponseBytes'];
            $headerFunction = static function (
                CurlHandle $unused,
                string $line
            ) use (&$headerBytes, &$headerRejected): int {
                $length = strlen($line);
                $headerBytes += $length;
                if ($headerBytes > 16384
                    || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line)
                        === 1
                ) {
                    $headerRejected = true;
                    return 0;
                }
                return $length;
            };
            $writeFunction = static function (
                CurlHandle $unused,
                string $bytes
            ) use (
                &$responseBytes,
                &$bodyRejected,
                $maximumResponseBytes
            ): int {
                $length = strlen($bytes);
                if ($responseBytes + $length > $maximumResponseBytes) {
                    $bodyRejected = true;
                    return 0;
                }
                $responseBytes += $length;
                return $length;
            };

            $credentialPair = $credential . ':';
            $credential = '';
            $handle = curl_init();
            if (!$handle instanceof CurlHandle) {
                throw new RuntimeException('provider_transport_failed');
            }
            $options = [
                CURLOPT_URL => self::TARGET_URL,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $credentialPair,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Expect:',
                ],
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
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_NOSIGNAL => true,
            ];
            if (!curl_setopt_array($handle, $options)
                || curl_exec($handle) !== true
                || $bodyRejected
                || $headerRejected
                || curl_errno($handle) !== CURLE_OK
                || curl_getinfo($handle, CURLINFO_SSL_VERIFYRESULT) !== 0
                || curl_getinfo($handle, CURLINFO_REDIRECT_COUNT) !== 0
                || curl_getinfo($handle, CURLINFO_EFFECTIVE_URL)
                    !== self::TARGET_URL
            ) {
                throw new RuntimeException('provider_transport_failed');
            }
            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if (!is_int($statusCode)
                || $statusCode < 100
                || $statusCode > 599
            ) {
                throw new RuntimeException('provider_transport_failed');
            }

            return [
                'operation' =>
                    'stripe.sandbox.read-only-resource-miss-probe',
                'method' => 'GET',
                'targetMatched' => true,
                'statusCode' => $statusCode,
                'redirectCount' => 0,
                'responseBytes' => $responseBytes,
                'headerBytes' => $headerBytes,
                'responseBodyIncluded' => false,
                'responseHeadersIncluded' => false,
                'credentialIncluded' => false,
                'minimumTlsVersion' => '1.2',
                'peerVerificationRequired' => true,
                'hostVerificationRequired' => true,
                'proxyDisabled' => true,
                'executionPerformed' => true,
            ];
        } catch (Throwable $throwable) {
            throw new RuntimeException('provider_transport_failed');
        } finally {
            $handle = null;
            $credentialPair = '';
            $credential = '';
        }
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private static function contactPlan(array $plan): bool
    {
        return self::exactKeys($plan, [
            'operation', 'packageId', 'packageVersion',
            'packageArtifactSha256', 'runtimeProviderTransport', 'method',
            'url', 'expectedEffect', 'responseBodyProjection',
            'credentialSettingKey', 'credentialMode', 'credentialSource',
            'credentialValueIncluded', 'credentialValueSha256Included',
            'credentialEvidenceSha256', 'minimumTlsVersion', 'verifyPeer',
            'verifyHost', 'proxyMode', 'followRedirects',
            'maximumRedirects', 'connectTimeoutMilliseconds',
            'totalTimeoutMilliseconds', 'maximumResponseBytes',
            'maximumAttempts', 'oneTimeAuthorizationRequired',
            'retryAuthorized', 'mutationAuthorized',
            'checkoutCreationAuthorized', 'paymentAuthorized',
            'webhookAuthorized', 'liveModeAuthorized',
            'clientDeploymentAuthorized', 'executionPerformed',
        ])
            && ($plan['operation'] ?? null)
                === 'stripe.sandbox.read-only-resource-miss-probe'
            && ($plan['packageId'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && ($plan['packageVersion'] ?? null) === '0.1.1'
            && self::sha256($plan['packageArtifactSha256'] ?? null)
            && ($plan['runtimeProviderTransport'] ?? null) === 'disabled'
            && ($plan['method'] ?? null) === 'GET'
            && ($plan['url'] ?? null) === self::TARGET_URL
            && ($plan['expectedEffect'] ?? null)
                === 'read-only-resource-miss'
            && ($plan['responseBodyProjection'] ?? null) === 'none'
            && ($plan['credentialSettingKey'] ?? null)
                === 'stripe.secret-key'
            && ($plan['credentialMode'] ?? null) === 'restricted_test'
            && ($plan['credentialSource'] ?? null)
                === 'process_environment'
            && ($plan['credentialValueIncluded'] ?? null) === false
            && ($plan['credentialValueSha256Included'] ?? null) === false
            && self::sha256($plan['credentialEvidenceSha256'] ?? null)
            && ($plan['minimumTlsVersion'] ?? null) === '1.2'
            && ($plan['verifyPeer'] ?? null) === true
            && ($plan['verifyHost'] ?? null) === true
            && ($plan['proxyMode'] ?? null) === 'disabled'
            && ($plan['followRedirects'] ?? null) === false
            && ($plan['maximumRedirects'] ?? null) === 0
            && ($plan['connectTimeoutMilliseconds'] ?? null) === 5000
            && ($plan['totalTimeoutMilliseconds'] ?? null) === 15000
            && ($plan['maximumResponseBytes'] ?? null) === 65536
            && ($plan['maximumAttempts'] ?? null) === 1
            && ($plan['oneTimeAuthorizationRequired'] ?? null) === true
            && ($plan['retryAuthorized'] ?? null) === false
            && ($plan['mutationAuthorized'] ?? null) === false
            && ($plan['checkoutCreationAuthorized'] ?? null) === false
            && ($plan['paymentAuthorized'] ?? null) === false
            && ($plan['webhookAuthorized'] ?? null) === false
            && ($plan['liveModeAuthorized'] ?? null) === false
            && ($plan['clientDeploymentAuthorized'] ?? null) === false
            && ($plan['executionPerformed'] ?? null) === false;
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
