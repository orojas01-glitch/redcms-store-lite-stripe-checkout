<?php

declare(strict_types=1);

/**
 * P3E-6 pure, value-free readiness plan for a future read-only contact.
 *
 * This class accepts only non-secret evidence and cannot execute DNS, HTTP,
 * credential resolution, persistence, or an adapter operation.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner
{
    public static function plan(
        array $packageEvidence,
        array $credentialEvidence,
        array $networkEvidence
    ): array {
        if (!self::packageEvidence($packageEvidence)
            || !self::credentialEvidence($credentialEvidence)
            || !self::networkEvidence($networkEvidence)
        ) {
            return self::refused('readiness_evidence_refused');
        }

        $contactPlan = [
            'operation' => 'stripe.sandbox.read-only-resource-miss-probe',
            'packageId' => $packageEvidence['packageId'],
            'packageVersion' => $packageEvidence['packageVersion'],
            'packageArtifactSha256' =>
                $packageEvidence['packageArtifactSha256'],
            'runtimeProviderTransport' =>
                $packageEvidence['runtimeProviderTransport'],
            'method' => 'GET',
            'url' => 'https://api.stripe.com/v1/checkout/sessions/'
                . 'cs_test_redcms_readiness_probe',
            'expectedEffect' => 'read-only-resource-miss',
            'responseBodyProjection' => 'none',
            'credentialSettingKey' => 'stripe.secret-key',
            'credentialMode' => 'restricted_test',
            'credentialSource' => 'process_environment',
            'credentialValueIncluded' => false,
            'credentialValueSha256Included' => false,
            'credentialEvidenceSha256' =>
                $credentialEvidence['evidenceSha256'],
            'minimumTlsVersion' => '1.2',
            'verifyPeer' => true,
            'verifyHost' => true,
            'proxyMode' => 'disabled',
            'followRedirects' => false,
            'maximumRedirects' => 0,
            'connectTimeoutMilliseconds' => 5000,
            'totalTimeoutMilliseconds' => 15000,
            'maximumResponseBytes' => 65536,
            'maximumAttempts' => 1,
            'oneTimeAuthorizationRequired' => true,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'checkoutCreationAuthorized' => false,
            'paymentAuthorized' => false,
            'webhookAuthorized' => false,
            'liveModeAuthorized' => false,
            'clientDeploymentAuthorized' => false,
            'executionPerformed' => false,
        ];
        $encoded = self::encode($contactPlan);
        if ($encoded === null) {
            return self::refused('readiness_plan_encoding_failed');
        }

        return [
            'ready' => true,
            'contactPlan' => $contactPlan,
            'planSha256' => hash('sha256', $encoded),
            'executionPerformed' => false,
            'errors' => [],
        ];
    }

    private static function packageEvidence(array $evidence): bool
    {
        $profileValid = (($evidence['packageVersion'] ?? null) === '0.1.3'
                && ($evidence['runtimeProviderTransport'] ?? null)
                    === 'synthetic_only')
            || (($evidence['packageVersion'] ?? null) === '0.1.4'
                && ($evidence['runtimeProviderTransport'] ?? null)
                    === 'provider_read_only');
        return self::exactKeys($evidence, [
            'packageId',
            'packageVersion',
            'packageArtifactSha256',
            'runtimeProviderTransport',
        ])
            && ($evidence['packageId'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && $profileValid
            && self::sha256($evidence['packageArtifactSha256'] ?? null);
    }

    private static function credentialEvidence(array $evidence): bool
    {
        return self::exactKeys($evidence, [
            'settingKey',
            'keyMode',
            'source',
            'available',
            'valueIncluded',
            'valueSha256Included',
            'repositoryScan',
            'configurationScan',
            'logScan',
            'leastPrivilegeReview',
            'rotationRunbook',
            'revocationRunbook',
            'evidenceSha256',
        ])
            && ($evidence['settingKey'] ?? null) === 'stripe.secret-key'
            && ($evidence['keyMode'] ?? null) === 'restricted_test'
            && ($evidence['source'] ?? null) === 'process_environment'
            && ($evidence['available'] ?? null) === true
            && ($evidence['valueIncluded'] ?? null) === false
            && ($evidence['valueSha256Included'] ?? null) === false
            && ($evidence['repositoryScan'] ?? null) === 'clean'
            && ($evidence['configurationScan'] ?? null) === 'clean'
            && ($evidence['logScan'] ?? null) === 'clean'
            && ($evidence['leastPrivilegeReview'] ?? null)
                === 'checkout_sessions_read_only'
            && ($evidence['rotationRunbook'] ?? null) === 'ready'
            && ($evidence['revocationRunbook'] ?? null) === 'ready'
            && self::sha256($evidence['evidenceSha256'] ?? null);
    }

    private static function networkEvidence(array $evidence): bool
    {
        return self::exactKeys($evidence, [
            'providerHost',
            'providerPort',
            'method',
            'path',
            'dnsRequired',
            'httpsOnly',
            'minimumTlsVersion',
            'verifyPeer',
            'verifyHost',
            'proxyMode',
            'followRedirects',
            'maximumRedirects',
            'connectTimeoutMilliseconds',
            'totalTimeoutMilliseconds',
            'maximumResponseBytes',
        ])
            && ($evidence['providerHost'] ?? null) === 'api.stripe.com'
            && ($evidence['providerPort'] ?? null) === 443
            && ($evidence['method'] ?? null) === 'GET'
            && ($evidence['path'] ?? null)
                === '/v1/checkout/sessions/cs_test_redcms_readiness_probe'
            && ($evidence['dnsRequired'] ?? null) === true
            && ($evidence['httpsOnly'] ?? null) === true
            && ($evidence['minimumTlsVersion'] ?? null) === '1.2'
            && ($evidence['verifyPeer'] ?? null) === true
            && ($evidence['verifyHost'] ?? null) === true
            && ($evidence['proxyMode'] ?? null) === 'disabled'
            && ($evidence['followRedirects'] ?? null) === false
            && ($evidence['maximumRedirects'] ?? null) === 0
            && ($evidence['connectTimeoutMilliseconds'] ?? null) === 5000
            && ($evidence['totalTimeoutMilliseconds'] ?? null) === 15000
            && ($evidence['maximumResponseBytes'] ?? null) === 65536;
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

    private static function encode(array $value): ?string
    {
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return null;
        }
    }

    private static function refused(string $error): array
    {
        return [
            'ready' => false,
            'contactPlan' => null,
            'planSha256' => '',
            'executionPerformed' => false,
            'errors' => [$error],
        ];
    }
}
