<?php

declare(strict_types=1);

/**
 * P3E-8B1 pure projection for bounded provider-contact transport evidence.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate
{
    public static function project(array $evidence): array
    {
        if (!self::evidence($evidence)) {
            return self::refused('transport_evidence_refused');
        }

        $statusCode = $evidence['statusCode'];
        $outcome = match (true) {
            $statusCode === 404 => 'resource_miss_observed',
            $statusCode === 401 => 'credential_refused',
            $statusCode === 403 => 'permission_refused',
            $statusCode === 429 => 'rate_limited',
            $statusCode >= 500 => 'provider_unavailable',
            $statusCode >= 200 && $statusCode <= 299 =>
                'unexpected_success_status',
            default => 'unexpected_provider_status',
        };
        $encoded = self::encode($evidence);
        if ($encoded === null) {
            return self::refused('transport_evidence_encoding_failed');
        }

        return [
            'valid' => true,
            'outcome' => $outcome,
            'statusCode' => $statusCode,
            'expectedEffectObserved' => $statusCode === 404,
            'responseBytes' => $evidence['responseBytes'],
            'transportEvidenceSha256' => hash('sha256', $encoded),
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'executionPerformed' => true,
            'errors' => [],
        ];
    }

    private static function evidence(array $evidence): bool
    {
        return self::exactKeys($evidence, [
            'operation', 'method', 'targetMatched', 'statusCode',
            'redirectCount', 'responseBytes', 'headerBytes',
            'responseBodyIncluded', 'responseHeadersIncluded',
            'credentialIncluded', 'minimumTlsVersion',
            'peerVerificationRequired', 'hostVerificationRequired',
            'proxyDisabled', 'executionPerformed',
        ])
            && ($evidence['operation'] ?? null)
                === 'stripe.sandbox.read-only-resource-miss-probe'
            && ($evidence['method'] ?? null) === 'GET'
            && ($evidence['targetMatched'] ?? null) === true
            && is_int($evidence['statusCode'] ?? null)
            && $evidence['statusCode'] >= 100
            && $evidence['statusCode'] <= 599
            && ($evidence['redirectCount'] ?? null) === 0
            && is_int($evidence['responseBytes'] ?? null)
            && $evidence['responseBytes'] >= 0
            && $evidence['responseBytes'] <= 65536
            && is_int($evidence['headerBytes'] ?? null)
            && $evidence['headerBytes'] >= 0
            && $evidence['headerBytes'] <= 16384
            && ($evidence['responseBodyIncluded'] ?? null) === false
            && ($evidence['responseHeadersIncluded'] ?? null) === false
            && ($evidence['credentialIncluded'] ?? null) === false
            && ($evidence['minimumTlsVersion'] ?? null) === '1.2'
            && ($evidence['peerVerificationRequired'] ?? null) === true
            && ($evidence['hostVerificationRequired'] ?? null) === true
            && ($evidence['proxyDisabled'] ?? null) === true
            && ($evidence['executionPerformed'] ?? null) === true;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
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
            'valid' => false,
            'outcome' => 'indeterminate',
            'statusCode' => null,
            'expectedEffectObserved' => false,
            'responseBytes' => null,
            'transportEvidenceSha256' => '',
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'executionPerformed' => null,
            'errors' => [$error],
        ];
    }
}
