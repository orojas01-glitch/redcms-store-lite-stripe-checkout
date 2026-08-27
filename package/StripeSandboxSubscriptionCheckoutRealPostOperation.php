<?php

declare(strict_types=1);

/** One-attempt real Stripe Sandbox POST for a validated subscription intent. */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Real_Post_Operation
{
    private const PACKAGE_ID = 'redcms.store-lite-stripe-checkout';
    private const PACKAGE_VERSION = '0.1.15';
    private const OPERATION = 'subscription.checkout.create-sandbox-real-post';

    public static function execute(
        array $intent,
        array $offer,
        array $policy,
        array $execution,
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange $exchange
    ): array {
        if (!self::dependencies() || !self::execution($execution)) {
            return self::outcome(
                'refused', $execution, false, null,
                'preflight_refused'
            );
        }
        $prepared =
            RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
                prepare($intent, $offer, $policy);
        $contract = $prepared['contract'] ?? null;
        $wireRequest = is_array($contract)
            ? self::wireRequest($contract, $prepared['contractSha256'] ?? '')
            : null;
        if (($prepared['valid'] ?? null) !== true
            || ($prepared['errors'] ?? null) !== []
            || !is_array($contract)
            || !is_array($wireRequest)
        ) {
            return self::outcome(
                'refused', $execution, false, null,
                'preflight_refused'
            );
        }

        $attempted = false;
        $failureStage = 'transport_exchange_failed';
        try {
            $attempted = true;
            $wireResponse = $exchange->exchange($wireRequest);
            if ($exchange->calls() !== 1) {
                return self::outcome(
                    'indeterminate', $execution, true, null,
                    'exchange_invariant_failed'
                );
            }
            $failureStage = 'response_decode_failed';
            $decoded =
                RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::
                    decode($wireResponse);
            $transcript = $decoded['transcript'] ?? null;
            $rawDecoded =
                RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder::decode(
                    $wireResponse['body'] ?? ''
                );
            $raw = $rawDecoded['value'] ?? null;
            if (($decoded['valid'] ?? null) !== true
                || !is_array($transcript)
                || ($transcript['outcome'] ?? null) !== 'response'
                || !is_array($transcript['envelope'] ?? null)
                || !is_array($transcript['projection'] ?? null)
                || ($decoded['errors'] ?? null) !== []
                || ($rawDecoded['valid'] ?? null) !== true
                || !is_array($raw)
                || array_is_list($raw)
                || !array_key_exists('expires_at', $raw)
                || !array_key_exists('after_expiration', $raw)
            ) {
                return self::outcome(
                    'indeterminate', $execution, true, null,
                    'response_decode_failed'
                );
            }
            $projection = $transcript['projection'];
            $projection['expires_at'] = $raw['expires_at'];
            $projection['after_expiration'] = $raw['after_expiration'];
            $failureStage = 'response_acceptance_failed';
            $accepted =
                RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::
                    accept(
                        $intent,
                        $offer,
                        $policy,
                        $transcript['envelope'],
                        $projection
                    );
            if (($accepted['valid'] ?? null) !== true
                || !is_array($accepted['handoff'] ?? null)
                || ($accepted['errors'] ?? null) !== []
            ) {
                return self::outcome(
                    'indeterminate', $execution, true, null,
                    'response_acceptance_failed'
                );
            }
            $accepted['requestSha256'] =
                $contract['request']['bodySha256'];
            return self::outcome(
                'subscription_checkout_session_created',
                $execution,
                true,
                $accepted,
                'none'
            );
        } catch (Throwable $throwable) {
            return self::outcome(
                $attempted ? 'indeterminate' : 'refused',
                $execution,
                $attempted,
                null,
                $attempted ? $failureStage : 'preflight_refused'
            );
        }
    }

    private static function wireRequest(
        array $contract,
        string $contractSha256
    ): ?array {
        $request = $contract['request'] ?? null;
        $intent = $contract['intent'] ?? null;
        if (!is_array($request)
            || !is_array($intent)
            || !self::sha256($contractSha256)
            || !self::sha256($request['bodySha256'] ?? null)
            || !is_string($intent['intentReference'] ?? null)
        ) {
            return null;
        }
        $idempotencySha256 = self::hash([
            'schema' => 1,
            'purpose' => 'subscription-checkout-idempotency',
            'intentReference' => $intent['intentReference'],
            'contractSha256' => $contractSha256,
            'requestSha256' => $request['bodySha256'],
        ]);
        if (!self::sha256($idempotencySha256)) {
            return null;
        }
        return [
            'method' => 'POST',
            'url' => 'https://api.stripe.com/v1/checkout/sessions',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Stripe-Version' => $request['headers']['Stripe-Version'] ?? '',
                'Idempotency-Key' => 'redcms-checkout-' . $idempotencySha256,
            ],
            'authorization' => [
                'scheme' => 'http-basic-username',
                'secretSettingKey' => 'stripe.secret-key',
                'valueIncluded' => false,
            ],
            'body' => $request['body'],
            'bodyBytes' => $request['bodyBytes'],
            'bodySha256' => $request['bodySha256'],
            'transport' => [
                'minimumTlsVersion' => '1.2',
                'verifyPeer' => true,
                'verifyHost' => true,
                'followRedirects' => false,
                'maximumRedirects' => 0,
                'connectTimeoutMilliseconds' => 5000,
                'totalTimeoutMilliseconds' => 15000,
                'maximumResponseBytes' => 262144,
            ],
        ];
    }

    private static function dependencies(): bool
    {
        return class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Subscription_Checkout_Contract::class,
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
        string $failureStage
    ): array {
        $created = $status === 'subscription_checkout_session_created';
        $handoff = $created ? ($accepted['handoff'] ?? null) : null;
        $resultSha256 = $created && is_array($handoff)
            ? self::hash([
                'schema' => 1,
                'purpose' => 'subscription-checkout-real-post-result',
                'execution' => $execution,
                'contractSha256' => $accepted['contractSha256'] ?? '',
                'responseEvidenceSha256' =>
                    $accepted['responseEvidenceSha256'] ?? '',
                'handoffSha256' => hash(
                    'sha256',
                    (string) ($handoff['checkoutUrl'] ?? '')
                ),
            ])
            : '';
        return [
            'valid' => true,
            'status' => $status,
            'packageId' => self::PACKAGE_ID,
            'packageVersion' => self::PACKAGE_VERSION,
            'operation' => self::OPERATION,
            'execution' => $execution,
            'intentReference' => $created
                ? ($handoff['intentReference'] ?? '') : '',
            'checkoutSessionRef' => $created
                ? ($handoff['checkoutSessionRef'] ?? '') : '',
            'checkoutUrl' => $created
                ? ($handoff['checkoutUrl'] ?? '') : '',
            'expiresAtEpoch' => $created
                ? ($handoff['expiresAtEpoch'] ?? 0) : 0,
            'navigationMode' => $created
                ? ($handoff['navigationMode'] ?? '') : '',
            'transientOnly' => $created
                ? ($handoff['transientOnly'] ?? false) : false,
            'persistCheckoutUrl' => $created
                ? ($handoff['persistCheckoutUrl'] ?? true) : false,
            'cacheControl' => $created
                ? ($handoff['cacheControl'] ?? '') : '',
            'authorizationRequired' => $created
                ? ($handoff['authorizationRequired'] ?? false) : false,
            'browserNavigationAuthorized' => $created
                ? ($handoff['browserNavigationAuthorized'] ?? true) : false,
            'contractSha256' => $created
                ? ($accepted['contractSha256'] ?? '') : '',
            'requestSha256' => $created
                ? ($accepted['requestSha256'] ?? '') : '',
            'responseEvidenceSha256' => $created
                ? ($accepted['responseEvidenceSha256'] ?? '') : '',
            'resultSha256' => $resultSha256,
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'networkAccess' => $attempted,
            'providerContact' => $attempted,
            'providerMutation' => $attempted,
            'checkoutCreation' => $attempted,
            'subscriptionCreation' => $attempted,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => $attempted,
            'failureStage' => $created ? 'none' : $failureStage,
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
            return hash('sha256', json_encode(
                self::canonical($value),
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            ));
        } catch (Throwable $throwable) {
            return '';
        }
    }

    private static function canonical(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => is_array($item)
                    ? self::canonical($item) : $item,
                $value
            );
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonical($item);
            }
        }
        return $value;
    }
}
