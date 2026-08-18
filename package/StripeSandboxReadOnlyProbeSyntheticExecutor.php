<?php

declare(strict_types=1);

/**
 * One-use P3E-8B3B synthetic-only package execution proof.
 *
 * This class validates the exact synthetic readiness plan and restricted-test
 * credential shape, then projects fixed in-memory evidence. It contains no
 * provider transport, network client, environment reader, or secret resolver.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Synthetic_Executor
{
    private int $calls = 0;

    public function execute(
        array $contactPlan,
        #[SensitiveParameter] string $restrictedTestKey
    ): array {
        $this->calls++;
        if ($this->calls !== 1
            || !self::contactPlan($contactPlan)
            || !self::restrictedTestKey($restrictedTestKey)
        ) {
            $restrictedTestKey = '';
            throw new RuntimeException('synthetic_probe_refused');
        }
        $restrictedTestKey = '';

        $evidence = [
            'operation' => 'stripe.sandbox.read-only-resource-miss-probe',
            'method' => 'GET',
            'targetMatched' => true,
            'statusCode' => 404,
            'redirectCount' => 0,
            'responseBytes' => 0,
            'headerBytes' => 0,
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'minimumTlsVersion' => '1.2',
            'peerVerificationRequired' => true,
            'hostVerificationRequired' => true,
            'proxyDisabled' => true,
            'executionPerformed' => true,
        ];
        $outcome =
            RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project(
                $evidence
            );
        if (($outcome['valid'] ?? false) !== true
            || ($outcome['outcome'] ?? null) !== 'resource_miss_observed'
        ) {
            throw new RuntimeException('synthetic_probe_projection_failed');
        }

        return [
            'valid' => true,
            'contactTarget' => 'synthetic-package',
            'outcome' => $outcome['outcome'],
            'statusCode' => $outcome['statusCode'],
            'expectedEffectObserved' =>
                $outcome['expectedEffectObserved'],
            'responseBytes' => $outcome['responseBytes'],
            'transportEvidenceSha256' =>
                $outcome['transportEvidenceSha256'],
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'executionPerformed' => true,
            'errors' => [],
        ];
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private static function restrictedTestKey(string $value): bool
    {
        $prefix = 'rk_' . 'test_';
        return str_starts_with($value, $prefix)
            && strlen($value) >= 24
            && strlen($value) <= 255
            && preg_match('/[^\x21-\x7E]/', $value) !== 1;
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
            && ($plan['packageVersion'] ?? null) === '0.1.3'
            && self::sha256($plan['packageArtifactSha256'] ?? null)
            && ($plan['runtimeProviderTransport'] ?? null)
                === 'synthetic_only'
            && ($plan['method'] ?? null) === 'GET'
            && ($plan['url'] ?? null)
                === 'https://api.stripe.com/v1/checkout/sessions/'
                    . 'cs_test_redcms_readiness_probe'
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
