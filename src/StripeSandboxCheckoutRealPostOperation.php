<?php

declare(strict_types=1);

/** Bounded D4A execution around one supplied real-POST exchange. */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Operation
{
    private const PACKAGE_ID = 'redcms.store-lite-stripe-checkout';
    private const PACKAGE_VERSION = '0.1.8';
    private const SOURCE_PACKAGE_VERSION = '0.1.7';
    private const OPERATION = 'checkout.create-sandbox-real-post';

    public static function execute(
        array $checkout,
        array $policy,
        array $profile,
        string $contractSha256,
        array $preflight,
        array $execution,
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange $exchange
    ): array {
        if (!self::dependencies() || !self::execution($execution)) {
            return self::outcome('refused', $execution, false, null);
        }
        $adopted =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::adopt(
                $checkout,
                $policy,
                $profile,
                $contractSha256,
                $preflight
            );
        $prepared =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
                $checkout,
                $policy,
                $profile
            );
        if (($adopted['valid'] ?? null) !== true
            || ($adopted['adopted'] ?? null) !== true
            || ($adopted['packageVersion'] ?? null)
                !== self::PACKAGE_VERSION
            || ($adopted['operation'] ?? null)
                !== 'checkout.create-sandbox-real-post-preflight'
            || ($adopted['providerOperation'] ?? null) !== self::OPERATION
            || ($adopted['errors'] ?? null) !== []
            || ($prepared['valid'] ?? null) !== true
            || !hash_equals(
                $contractSha256,
                (string) ($prepared['contractSha256'] ?? '')
            )
        ) {
            return self::outcome('refused', $execution, false, null);
        }

        $attempted = false;
        $wireResponse = null;
        $decoded = null;
        $rawDecoded = null;
        $rawValue = null;
        $projection = null;
        $accepted = null;
        try {
            $attempted = true;
            $wireResponse = $exchange->exchange(
                $prepared['contract']['request']
            );
            if ($exchange->calls() !== 1) {
                return self::outcome(
                    'indeterminate',
                    $execution,
                    true,
                    null,
                    $adopted
                );
            }
            $decoded =
                RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
                    $wireResponse
                );
            $transcript = $decoded['transcript'] ?? null;
            if (($decoded['valid'] ?? null) !== true
                || !is_array($transcript)
                || ($transcript['outcome'] ?? null) !== 'response'
                || !is_array($transcript['envelope'] ?? null)
                || !is_array($transcript['projection'] ?? null)
                || ($decoded['errors'] ?? null) !== []
            ) {
                return self::outcome(
                    'indeterminate',
                    $execution,
                    true,
                    null,
                    $adopted
                );
            }
            $rawDecoded =
                RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode(
                    $wireResponse['body'] ?? ''
                );
            $rawValue = $rawDecoded['value'] ?? null;
            if (($rawDecoded['valid'] ?? null) !== true
                || !is_array($rawValue)
                || array_is_list($rawValue)
                || !array_key_exists('expires_at', $rawValue)
                || !array_key_exists('after_expiration', $rawValue)
            ) {
                return self::outcome(
                    'indeterminate',
                    $execution,
                    true,
                    null,
                    $adopted
                );
            }
            $projection = $transcript['projection'];
            $projection['expires_at'] = $rawValue['expires_at'];
            $projection['after_expiration'] =
                $rawValue['after_expiration'];
            $accepted =
                RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::accept(
                    $checkout,
                    $policy,
                    $profile,
                    $transcript['envelope'],
                    $projection
                );
            if (($accepted['valid'] ?? null) !== true
                || !is_array($accepted['result'] ?? null)
                || ($accepted['errors'] ?? null) !== []
            ) {
                return self::outcome(
                    'indeterminate',
                    $execution,
                    true,
                    null,
                    $adopted
                );
            }
            return self::outcome(
                'checkout_session_created',
                $execution,
                true,
                $accepted,
                $adopted
            );
        } catch (Throwable $throwable) {
            return self::outcome(
                $attempted ? 'indeterminate' : 'refused',
                $execution,
                $attempted,
                null,
                $adopted
            );
        } finally {
            $wireResponse = null;
            $decoded = null;
            $rawDecoded = null;
            $rawValue = null;
            $projection = null;
            $accepted = null;
        }
    }

    private static function dependencies(): bool
    {
        return class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::class,
            false
        ) && class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::class,
            false
        ) && class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::class,
            false
        ) && class_exists(
            RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::class,
            false
        );
    }

    private static function execution(array $execution): bool
    {
        return self::exactKeys($execution, [
            'planSha256', 'claimStateSha256',
            'executionStartStateSha256',
        ])
            && self::sha256($execution['planSha256'] ?? null)
            && self::sha256($execution['claimStateSha256'] ?? null)
            && self::sha256(
                $execution['executionStartStateSha256'] ?? null
            );
    }

    private static function outcome(
        string $status,
        array $execution,
        bool $attempted,
        ?array $accepted,
        ?array $adopted = null
    ): array {
        $created = $status === 'checkout_session_created';
        $acceptedResult = $created ? ($accepted['result'] ?? null) : null;
        $bounded = is_array($acceptedResult) ? [
            'checkoutSessionRef' =>
                $acceptedResult['checkoutSessionRef'] ?? null,
            'checkoutUrlValidated' =>
                $acceptedResult['checkoutUrlValidated'] ?? null,
            'mode' => $acceptedResult['mode'] ?? null,
            'status' => $acceptedResult['status'] ?? null,
            'paymentStatus' => $acceptedResult['paymentStatus'] ?? null,
            'amountMinor' => $acceptedResult['amountMinor'] ?? null,
            'currency' => $acceptedResult['currency'] ?? null,
            'expiresAtEpoch' => $acceptedResult['expiresAtEpoch'] ?? null,
            'recoveryEnabled' =>
                $acceptedResult['recoveryEnabled'] ?? null,
            'livemode' => $acceptedResult['livemode'] ?? null,
        ] : null;
        $resultSha256 = $created && is_array($bounded)
            ? self::hash([
                'schema' => 1,
                'purpose' => 'sandbox-checkout-real-post-result',
                'execution' => $execution,
                'requestSha256' => $adopted['requestSha256'] ?? '',
                'responseEvidenceSha256' =>
                    $accepted['responseEvidenceSha256'] ?? '',
                'checkout' => $bounded,
            ])
            : '';
        return [
            'valid' => true,
            'status' => $status,
            'packageId' => self::PACKAGE_ID,
            'packageVersion' => self::PACKAGE_VERSION,
            'sourcePackageVersion' => self::SOURCE_PACKAGE_VERSION,
            'operation' => self::OPERATION,
            'providerOperation' => self::OPERATION,
            'execution' => $execution,
            'inputSha256' => $adopted['inputSha256'] ?? '',
            'syntheticPlanSha256' =>
                $adopted['syntheticPlanSha256'] ?? '',
            'contractSha256' => $adopted['contractSha256'] ?? '',
            'requestSha256' => $adopted['requestSha256'] ?? '',
            'checkout' => $bounded,
            'responseEvidenceSha256' => $created
                ? ($accepted['responseEvidenceSha256'] ?? '')
                : '',
            'resultSha256' => $resultSha256,
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'checkoutUrlIncluded' => false,
            'networkAccess' => $attempted,
            'providerContact' => $attempted,
            'providerMutation' => $attempted,
            'checkoutCreation' => $attempted,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => $attempted,
            'errors' => $status === 'indeterminate'
                ? ['provider_execution_indeterminate']
                : ($status === 'refused' ? ['operation_refused'] : []),
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

    private static function hash(array $value): string
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return '';
        }
        return hash('sha256', $encoded);
    }
}
