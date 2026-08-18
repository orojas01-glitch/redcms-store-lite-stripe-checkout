<?php

declare(strict_types=1);

/**
 * Pure P3E-9A Checkout-creation contract around the P3E-1/P3E-3 source.
 *
 * It adds no package registration, credential value, database, transport,
 * request, provider object, Checkout Session, browser flow, or authorization.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract
{
    private const PACKAGE_ID = 'redcms.store-lite-stripe-checkout';
    private const CONTRACT_VERSION = 'p3e9a-v1';
    private const OPERATION = 'checkout.create-sandbox';
    private const MINIMUM_EXPIRY_SECONDS = 1800;
    private const MAXIMUM_EXPIRY_SECONDS = 86400;

    public static function prepare(
        array $checkout,
        array $policy,
        array $profile
    ): array {
        if (!self::dependencies()) {
            return self::prepareInvalid('creation_contract_unavailable');
        }
        if (!self::profile($profile)) {
            return self::prepareInvalid('operation_profile_invalid');
        }
        $basePolicy = self::basePolicy($policy);
        if ($basePolicy === null) {
            return self::prepareInvalid('creation_policy_invalid');
        }
        if (($checkout['currency'] ?? null) !== 'USD') {
            return self::prepareInvalid('creation_checkout_refused');
        }

        $encoded =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
                $checkout,
                $basePolicy
            );
        if (!self::encoded($encoded)) {
            return self::prepareInvalid('creation_checkout_refused');
        }

        $wireRequest = $encoded['wireRequest'];
        if (str_contains($wireRequest['body'], 'expires_at=')) {
            return self::prepareInvalid('creation_request_refused');
        }
        $wireRequest['body'] .= '&expires_at=' . urlencode(
            (string) $policy['expiresAtEpoch']
        );
        $wireRequest['bodyBytes'] = strlen($wireRequest['body']);
        if ($wireRequest['bodyBytes'] < 1
            || $wireRequest['bodyBytes'] > 65536
        ) {
            return self::prepareInvalid('creation_request_refused');
        }
        $wireRequest['bodySha256'] = hash(
            'sha256',
            $wireRequest['body']
        );

        $contract = [
            'packageId' => self::PACKAGE_ID,
            'contractVersion' => self::CONTRACT_VERSION,
            'operation' => self::OPERATION,
            'contactTarget' => 'stripe-sandbox',
            'credential' => [
                'mode' => 'restricted_test_write',
                'secretSettingKey' => 'stripe.secret-key',
                'valueIncluded' => false,
            ],
            'request' => $wireRequest,
            'expiry' => [
                'createdAtEpoch' => $policy['createdAtEpoch'],
                'expiresAtEpoch' => $policy['expiresAtEpoch'],
                'durationSeconds' =>
                    $policy['expiresAtEpoch'] - $policy['createdAtEpoch'],
                'recoveryEnabled' => false,
            ],
            'requestedEffect' => [
                'providerContact' => true,
                'providerMutation' => true,
                'checkoutCreation' => true,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'orderMutation' => false,
                'clientDeployment' => false,
                'oneAttempt' => true,
                'automaticRetry' => false,
            ],
            'currentExecution' => [
                'authorized' => false,
                'network' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'orderMutation' => false,
                'clientDeployment' => false,
            ],
            'basePlanSha256' => $encoded['planSha256'],
        ];
        $contractSha256 = self::hash($contract);
        if ($contractSha256 === null) {
            return self::prepareInvalid('creation_contract_encoding_failed');
        }

        return [
            'valid' => true,
            'contract' => $contract,
            'contractSha256' => $contractSha256,
            'errors' => [],
        ];
    }

    public static function accept(
        array $checkout,
        array $policy,
        array $profile,
        array $envelope,
        array $projection
    ): array {
        $prepared = self::prepare($checkout, $policy, $profile);
        if (!self::prepared($prepared)) {
            return self::acceptInvalid('creation_contract_refused');
        }
        if (!self::projection($projection, $policy['expiresAtEpoch'])) {
            return self::acceptInvalid('creation_response_refused');
        }

        $baseProjection = [];
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
            $baseProjection[$key] = $projection[$key];
        }
        $expected = $checkout;
        unset($expected['lineItems']);
        $accepted =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate::accept(
                $expected,
                $envelope,
                $baseProjection
            );
        if (!self::accepted($accepted)) {
            return self::acceptInvalid('creation_response_refused');
        }

        $result = [
            'operation' => self::OPERATION,
            'checkoutSessionRef' =>
                $accepted['checkout']['checkoutSessionRef'],
            'checkoutUrlValidated' => true,
            'mode' => 'payment',
            'status' => 'open',
            'paymentStatus' => 'unpaid',
            'amountMinor' => $projection['amount_total'],
            'currency' => $projection['currency'],
            'expiresAtEpoch' => $projection['expires_at'],
            'recoveryEnabled' => false,
            'livemode' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'clientDeployment' => false,
        ];
        $resultSha256 = self::hash([
            'contractSha256' => $prepared['contractSha256'],
            'responseEvidenceSha256' =>
                $accepted['responseEvidenceSha256'],
            'result' => $result,
        ]);
        if ($resultSha256 === null) {
            return self::acceptInvalid('creation_result_encoding_failed');
        }

        return [
            'valid' => true,
            'result' => $result,
            'contractSha256' => $prepared['contractSha256'],
            'responseEvidenceSha256' =>
                $accepted['responseEvidenceSha256'],
            'resultSha256' => $resultSha256,
            'errors' => [],
        ];
    }

    private static function dependencies(): bool
    {
        return class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::class,
            false
        ) && class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate::class,
            false
        );
    }

    private static function profile(array $profile): bool
    {
        return self::exactKeys($profile, [
            'packageId',
            'contractVersion',
            'operation',
            'contactTarget',
            'credentialMode',
            'providerContact',
            'providerMutation',
            'checkoutCreation',
            'payment',
            'webhook',
            'browserNavigation',
            'orderMutation',
            'clientDeployment',
            'oneAttempt',
            'automaticRetry',
        ])
            && ($profile['packageId'] ?? null) === self::PACKAGE_ID
            && ($profile['contractVersion'] ?? null)
                === self::CONTRACT_VERSION
            && ($profile['operation'] ?? null) === self::OPERATION
            && ($profile['contactTarget'] ?? null) === 'stripe-sandbox'
            && ($profile['credentialMode'] ?? null)
                === 'restricted_test_write'
            && ($profile['providerContact'] ?? null) === true
            && ($profile['providerMutation'] ?? null) === true
            && ($profile['checkoutCreation'] ?? null) === true
            && ($profile['payment'] ?? null) === false
            && ($profile['webhook'] ?? null) === false
            && ($profile['browserNavigation'] ?? null) === false
            && ($profile['orderMutation'] ?? null) === false
            && ($profile['clientDeployment'] ?? null) === false
            && ($profile['oneAttempt'] ?? null) === true
            && ($profile['automaticRetry'] ?? null) === false;
    }

    private static function basePolicy(array $policy): ?array
    {
        if (!self::exactKeys($policy, [
            'apiVersion',
            'successUrl',
            'cancelUrl',
            'createdAtEpoch',
            'expiresAtEpoch',
        ])
            || !is_int($policy['createdAtEpoch'] ?? null)
            || $policy['createdAtEpoch'] < 1
            || !is_int($policy['expiresAtEpoch'] ?? null)
            || $policy['expiresAtEpoch'] <= $policy['createdAtEpoch']
        ) {
            return null;
        }
        $duration = $policy['expiresAtEpoch'] - $policy['createdAtEpoch'];
        if ($duration < self::MINIMUM_EXPIRY_SECONDS
            || $duration > self::MAXIMUM_EXPIRY_SECONDS
        ) {
            return null;
        }

        return [
            'apiVersion' => $policy['apiVersion'] ?? null,
            'successUrl' => $policy['successUrl'] ?? null,
            'cancelUrl' => $policy['cancelUrl'] ?? null,
        ];
    }

    private static function encoded(array $encoded): bool
    {
        if (!self::exactKeys($encoded, [
            'valid', 'wireRequest', 'planSha256', 'errors',
        ])
            || ($encoded['valid'] ?? null) !== true
            || !is_array($encoded['wireRequest'] ?? null)
            || !self::sha256($encoded['planSha256'] ?? null)
            || ($encoded['errors'] ?? null) !== []
        ) {
            return false;
        }
        $request = $encoded['wireRequest'];
        return self::exactKeys($request, [
            'method',
            'url',
            'headers',
            'authorization',
            'body',
            'bodyBytes',
            'bodySha256',
            'transport',
        ])
            && ($request['method'] ?? null) === 'POST'
            && ($request['url'] ?? null)
                === 'https://api.stripe.com/v1/checkout/sessions'
            && is_array($request['headers'] ?? null)
            && is_array($request['authorization'] ?? null)
            && ($request['authorization']['valueIncluded'] ?? null) === false
            && is_string($request['body'] ?? null)
            && is_int($request['bodyBytes'] ?? null)
            && self::sha256($request['bodySha256'] ?? null)
            && is_array($request['transport'] ?? null);
    }

    private static function prepared(array $prepared): bool
    {
        return self::exactKeys($prepared, [
            'valid', 'contract', 'contractSha256', 'errors',
        ])
            && ($prepared['valid'] ?? null) === true
            && is_array($prepared['contract'] ?? null)
            && self::sha256($prepared['contractSha256'] ?? null)
            && ($prepared['errors'] ?? null) === [];
    }

    private static function projection(
        array $projection,
        int $expiresAtEpoch
    ): bool {
        return self::exactKeys($projection, [
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
            'expires_at',
            'after_expiration',
        ])
            && ($projection['expires_at'] ?? null) === $expiresAtEpoch
            && ($projection['after_expiration'] ?? null) === null;
    }

    private static function accepted(array $accepted): bool
    {
        return self::exactKeys($accepted, [
            'valid', 'checkout', 'responseEvidenceSha256', 'errors',
        ])
            && ($accepted['valid'] ?? null) === true
            && self::exactKeys($accepted['checkout'] ?? [], [
                'checkoutSessionRef', 'checkoutUrl',
            ])
            && is_string(
                $accepted['checkout']['checkoutSessionRef'] ?? null
            )
            && is_string($accepted['checkout']['checkoutUrl'] ?? null)
            && self::sha256(
                $accepted['responseEvidenceSha256'] ?? null
            )
            && ($accepted['errors'] ?? null) === [];
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

    private static function hash(array $value): ?string
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return null;
        }
        return hash('sha256', $encoded);
    }

    private static function prepareInvalid(string $error): array
    {
        return [
            'valid' => false,
            'contract' => null,
            'contractSha256' => '',
            'errors' => [$error],
        ];
    }

    private static function acceptInvalid(string $error): array
    {
        return [
            'valid' => false,
            'result' => null,
            'contractSha256' => '',
            'responseEvidenceSha256' => '',
            'resultSha256' => '',
            'errors' => [$error],
        ];
    }
}
