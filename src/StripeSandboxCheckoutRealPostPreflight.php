<?php

declare(strict_types=1);

/**
 * Pure P3E-9D1 adoption of the core P3E-9D0 real-POST preflight.
 *
 * This class revalidates and normalizes only non-secret request facts. It has
 * no credential resolver, provider transport, database, or execution path.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight
{
    private const PACKAGE_ID = 'redcms.store-lite-stripe-checkout';
    private const SOURCE_PACKAGE_VERSION = '0.1.5';
    private const PACKAGE_VERSION = '0.1.8';
    private const INPUT_TARGET = 'synthetic-checkout-package';
    private const OPERATION = 'checkout.create-sandbox-real-post-preflight';
    private const PROVIDER_OPERATION = 'checkout.create-sandbox-real-post';

    public static function adopt(
        array $checkout,
        array $policy,
        array $profile,
        string $expectedContractSha256,
        array $preflight
    ): array {
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::class,
            false
        ) || !self::sha256($expectedContractSha256)) {
            return self::invalid('real_post_preflight_unavailable');
        }

        $prepared =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
                $checkout,
                $policy,
                $profile
            );
        if (($prepared['valid'] ?? null) !== true
            || !hash_equals(
                $expectedContractSha256,
                (string) ($prepared['contractSha256'] ?? '')
            )
        ) {
            return self::invalid('real_post_contract_refused');
        }

        $input = [
            'contactTarget' => self::INPUT_TARGET,
            'checkout' => $checkout,
            'policy' => $policy,
            'profile' => $profile,
            'contractSha256' => $expectedContractSha256,
        ];
        $inputSha256 = self::inputHash($input);
        $formFields = self::formFields($checkout, $policy, $inputSha256);
        if ($inputSha256 === null || $formFields === null) {
            return self::invalid('real_post_request_refused');
        }
        $providerRequest = [
            'method' => 'POST',
            'host' => 'api.stripe.com',
            'path' => '/v1/checkout/sessions',
            'apiVersion' => $policy['apiVersion'] ?? null,
            'contentType' => 'application/x-www-form-urlencoded',
            'idempotencyKey' =>
                'redcms-checkout-' . ($checkout['idempotencySha256'] ?? ''),
            'formFields' => $formFields,
        ];
        $requestSha256 = self::hash($providerRequest);
        if ($requestSha256 === null
            || !self::preflight(
                $preflight,
                $inputSha256,
                $requestSha256,
                $providerRequest
            )
        ) {
            return self::invalid('real_post_preflight_refused');
        }
        $request = self::typedRequest($providerRequest);

        return [
            'valid' => true,
            'adopted' => true,
            'status' => 'request_contract_adopted',
            'packageId' => self::PACKAGE_ID,
            'packageVersion' => self::PACKAGE_VERSION,
            'sourcePackageVersion' => self::SOURCE_PACKAGE_VERSION,
            'operation' => self::OPERATION,
            'providerOperation' => self::PROVIDER_OPERATION,
            'request' => $request,
            'inputSha256' => $inputSha256,
            'syntheticPlanSha256' => $preflight['syntheticPlanSha256'],
            'contractSha256' => $expectedContractSha256,
            'requestSha256' => $requestSha256,
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'executionReady' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => false,
            'errors' => [],
        ];
    }

    private static function preflight(
        array $preflight,
        string $inputSha256,
        string $requestSha256,
        array $request
    ): bool {
        if (!self::exactKeys($preflight, [
            'valid', 'ready', 'status', 'packageId', 'packageVersion',
            'operation', 'method', 'host', 'path', 'apiVersion',
            'contentType', 'idempotencyKey', 'inputSha256',
            'syntheticPlanSha256', 'requestSha256',
            'restrictedTestWriteKeyRequired', 'credentialValueIncluded',
            'networkAccess', 'providerContact', 'providerMutation',
            'checkoutCreation', 'payment', 'webhook', 'browserNavigation',
            'storeLiteMutation', 'retryAuthorized', 'liveMode',
            'clientDeployment', 'executionPerformed', 'errors',
        ])) {
            return false;
        }
        return ($preflight['valid'] ?? null) === true
            && ($preflight['ready'] ?? null) === true
            && ($preflight['status'] ?? null) === 'ready'
            && ($preflight['packageId'] ?? null) === self::PACKAGE_ID
            && ($preflight['packageVersion'] ?? null)
                === self::SOURCE_PACKAGE_VERSION
            && ($preflight['operation'] ?? null) === self::PROVIDER_OPERATION
            && ($preflight['method'] ?? null) === $request['method']
            && ($preflight['host'] ?? null) === $request['host']
            && ($preflight['path'] ?? null) === $request['path']
            && ($preflight['apiVersion'] ?? null) === $request['apiVersion']
            && ($preflight['contentType'] ?? null)
                === $request['contentType']
            && ($preflight['idempotencyKey'] ?? null)
                === $request['idempotencyKey']
            && ($preflight['inputSha256'] ?? null) === $inputSha256
            && self::sha256($preflight['syntheticPlanSha256'] ?? null)
            && ($preflight['requestSha256'] ?? null) === $requestSha256
            && ($preflight['restrictedTestWriteKeyRequired'] ?? null) === true
            && ($preflight['credentialValueIncluded'] ?? null) === false
            && ($preflight['networkAccess'] ?? null) === false
            && ($preflight['providerContact'] ?? null) === false
            && ($preflight['providerMutation'] ?? null) === false
            && ($preflight['checkoutCreation'] ?? null) === false
            && ($preflight['payment'] ?? null) === false
            && ($preflight['webhook'] ?? null) === false
            && ($preflight['browserNavigation'] ?? null) === false
            && ($preflight['storeLiteMutation'] ?? null) === false
            && ($preflight['retryAuthorized'] ?? null) === false
            && ($preflight['liveMode'] ?? null) === false
            && ($preflight['clientDeployment'] ?? null) === false
            && ($preflight['executionPerformed'] ?? null) === false
            && ($preflight['errors'] ?? null) === [];
    }

    private static function formFields(
        array $checkout,
        array $policy,
        ?string $inputSha256
    ): ?array {
        if (!self::sha256($inputSha256)
            || !is_array($checkout['lineItems'] ?? null)
        ) {
            return null;
        }
        $fields = [
            'mode' => 'payment',
            'success_url' => $policy['successUrl'] ?? null,
            'cancel_url' => $policy['cancelUrl'] ?? null,
            'expires_at' => $policy['expiresAtEpoch'] ?? null,
            'client_reference_id' => $checkout['orderId'] ?? null,
            'metadata[order_snapshot_sha256]' =>
                $checkout['orderSnapshotSha256'] ?? null,
            'metadata[input_sha256]' => $inputSha256,
        ];
        foreach ($checkout['lineItems'] as $index => $line) {
            if (!is_int($index) || !is_array($line)) {
                return null;
            }
            $prefix = 'line_items[' . $index . ']';
            $fields[$prefix . '[price_data][currency]'] = 'usd';
            $fields[$prefix . '[price_data][product_data][name]'] =
                $line['name'] ?? null;
            $fields[$prefix . '[price_data][unit_amount]'] =
                $line['unitAmountMinor'] ?? null;
            $fields[$prefix . '[quantity]'] = $line['quantity'] ?? null;
        }
        return $fields;
    }

    private static function typedRequest(array $request): array
    {
        $fields = [];
        foreach ($request['formFields'] as $name => $value) {
            $fields[] = ['name' => $name, 'value' => $value];
        }
        $request['formFields'] = $fields;
        return $request;
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

    private static function inputHash(array $value): ?string
    {
        return self::hash(self::canonical($value));
    }

    private static function canonical(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => is_array($item)
                    ? self::canonical($item)
                    : $item,
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

    private static function invalid(string $error): array
    {
        return [
            'valid' => false,
            'adopted' => false,
            'status' => 'invalid',
            'packageId' => self::PACKAGE_ID,
            'packageVersion' => self::PACKAGE_VERSION,
            'sourcePackageVersion' => self::SOURCE_PACKAGE_VERSION,
            'operation' => self::OPERATION,
            'providerOperation' => self::PROVIDER_OPERATION,
            'request' => null,
            'inputSha256' => '',
            'syntheticPlanSha256' => '',
            'contractSha256' => '',
            'requestSha256' => '',
            'restrictedTestWriteKeyRequired' => true,
            'credentialValueIncluded' => false,
            'authorizationHeaderIncluded' => false,
            'executionReady' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => false,
            'errors' => [$error],
        ];
    }
}
