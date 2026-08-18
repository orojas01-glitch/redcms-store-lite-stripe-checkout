<?php

declare(strict_types=1);

/**
 * Typed offline, synthetic, and exact read-only sandbox probe operations.
 *
 * The synthetic operation validates the core-supplied plan and scoped
 * restricted-test key, but it cannot construct or call provider transport.
 */
final class RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter
{
    private const ADAPTER_ID =
        'redcms.store-lite-stripe-checkout/checkout';

    public static function handle(
        RED_Addon_Adapter_Request $request
    ): RED_Addon_Adapter_Result {
        if ($request->adapter() !== self::ADAPTER_ID) {
            return RED_Addon_Adapter_Result::failure(
                'unsupported_operation'
            );
        }

        if ($request->operation() === 'contract.probe') {
            return self::contractProbe($request);
        }
        if ($request->operation()
            === 'provider-contact.read-only-probe-synthetic'
        ) {
            return self::syntheticProbe($request);
        }
        if ($request->operation()
            === 'provider-contact.read-only-probe-sandbox'
        ) {
            return self::providerProbe($request);
        }
        if ($request->operation()
            === 'checkout.create-sandbox-synthetic'
        ) {
            return self::syntheticCheckout($request);
        }
        return RED_Addon_Adapter_Result::failure('unsupported_operation');
    }

    private static function contractProbe(
        RED_Addon_Adapter_Request $request
    ): RED_Addon_Adapter_Result {
        if ($request->input() !== []) {
            return RED_Addon_Adapter_Result::failure(
                'unsupported_operation'
            );
        }

        $apiValue = null;
        $apiResolution = $request->secret(
            'stripe.secret-key',
            $apiValue
        );
        $webhookValue = null;
        $webhookResolution = $request->secret(
            'stripe.webhook-secret',
            $webhookValue
        );
        $configured = ($apiResolution['resolved'] ?? false) === true
            && ($webhookResolution['resolved'] ?? false) === true
            && is_string($apiValue)
            && $apiValue !== ''
            && is_string($webhookValue)
            && $webhookValue !== '';
        $apiValue = null;
        $webhookValue = null;

        if (!$configured) {
            return RED_Addon_Adapter_Result::failure(
                'configuration_unavailable'
            );
        }

        return RED_Addon_Adapter_Result::failure(
            'provider_transport_disabled'
        );
    }

    private static function syntheticProbe(
        RED_Addon_Adapter_Request $request
    ): RED_Addon_Adapter_Result {
        $input = $request->input();
        if (!self::syntheticInput($input)) {
            return RED_Addon_Adapter_Result::failure(
                'synthetic_probe_input_refused'
            );
        }

        $apiValue = null;
        $apiResolution = $request->secret('stripe.secret-key', $apiValue);
        $webhookValue = null;
        $webhookResolution = $request->secret(
            'stripe.webhook-secret',
            $webhookValue
        );
        if (($apiResolution['resolved'] ?? false) !== true
            || !is_string($apiValue)
            || $apiValue === ''
            || ($webhookResolution['resolved'] ?? false) !== false
            || $webhookValue !== null
        ) {
            $apiValue = null;
            return RED_Addon_Adapter_Result::failure(
                'synthetic_probe_secret_refused'
            );
        }

        try {
            $executor =
                new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Synthetic_Executor();
            $outcome = $executor->execute($input['contactPlan'], $apiValue);
        } catch (Throwable $throwable) {
            $apiValue = null;
            return RED_Addon_Adapter_Result::failure(
                'synthetic_probe_failed'
            );
        }
        $apiValue = null;
        return RED_Addon_Adapter_Result::success($outcome);
    }

    private static function syntheticInput(array $input): bool
    {
        $keys = array_keys($input);
        $expected = [
            'claimStateSha256', 'contactPlan', 'contactTarget',
            'executionStartStateSha256', 'planSha256',
        ];
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected
            || ($input['contactTarget'] ?? null) !== 'synthetic-package'
            || !is_array($input['contactPlan'] ?? null)
            || !self::sha256($input['claimStateSha256'] ?? null)
            || !self::sha256($input['executionStartStateSha256'] ?? null)
            || !self::sha256($input['planSha256'] ?? null)
        ) {
            return false;
        }
        try {
            $encoded = json_encode(
                $input['contactPlan'],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return false;
        }
        return hash_equals(
            $input['planSha256'],
            hash('sha256', $encoded)
        );
    }

    private static function providerProbe(
        RED_Addon_Adapter_Request $request
    ): RED_Addon_Adapter_Result {
        $input = $request->input();
        if (!self::providerInput($input)) {
            return RED_Addon_Adapter_Result::failure(
                'provider_probe_input_refused'
            );
        }

        $apiValue = null;
        $apiResolution = $request->secret('stripe.secret-key', $apiValue);
        $webhookValue = null;
        $webhookResolution = $request->secret(
            'stripe.webhook-secret',
            $webhookValue
        );
        if (($apiResolution['resolved'] ?? false) !== true
            || !is_string($apiValue)
            || $apiValue === ''
            || ($webhookResolution['resolved'] ?? false) !== false
            || $webhookValue !== null
        ) {
            $apiValue = null;
            return RED_Addon_Adapter_Result::failure(
                'provider_probe_secret_refused'
            );
        }

        try {
            $transport =
                new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport(
                    $apiValue
                );
            $apiValue = null;
            $evidence = $transport->exchange($input['contactPlan']);
            $outcome =
                RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project(
                    $evidence
                );
        } catch (Throwable $throwable) {
            $apiValue = null;
            return RED_Addon_Adapter_Result::failure(
                'provider_probe_failed'
            );
        }
        $apiValue = null;
        if (($outcome['valid'] ?? false) !== true) {
            return RED_Addon_Adapter_Result::failure(
                'provider_probe_failed'
            );
        }
        return RED_Addon_Adapter_Result::success([
            'valid' => true,
            'contactTarget' => 'stripe-sandbox',
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
            'networkAccess' => true,
            'providerContact' => true,
            'executionPerformed' => true,
            'errors' => [],
        ]);
    }

    private static function syntheticCheckout(
        RED_Addon_Adapter_Request $request
    ): RED_Addon_Adapter_Result {
        $input = $request->input();
        if (!self::syntheticCheckoutInput($input)) {
            return RED_Addon_Adapter_Result::failure(
                'synthetic_checkout_input_refused'
            );
        }

        $apiValue = null;
        $apiResolution = $request->secret('stripe.secret-key', $apiValue);
        $webhookValue = null;
        $webhookResolution = $request->secret(
            'stripe.webhook-secret',
            $webhookValue
        );
        if (($apiResolution['resolved'] ?? false) !== true
            || !is_string($apiValue)
            || $apiValue === ''
            || ($webhookResolution['resolved'] ?? false) !== false
            || $webhookValue !== null
        ) {
            $apiValue = null;
            return RED_Addon_Adapter_Result::failure(
                'synthetic_checkout_secret_refused'
            );
        }

        try {
            $executor =
                new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Synthetic_Executor();
            $outcome = $executor->execute(
                $input['checkout'],
                $input['policy'],
                $input['profile'],
                $input['contractSha256'],
                $apiValue
            );
        } catch (Throwable $throwable) {
            $apiValue = null;
            return RED_Addon_Adapter_Result::failure(
                'synthetic_checkout_failed'
            );
        }
        $apiValue = null;
        return RED_Addon_Adapter_Result::success($outcome);
    }

    private static function syntheticCheckoutInput(array $input): bool
    {
        $keys = array_keys($input);
        $expected = [
            'checkout', 'contactTarget', 'contractSha256', 'policy', 'profile',
        ];
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected
            || ($input['contactTarget'] ?? null)
                !== 'synthetic-checkout-package'
            || !is_array($input['checkout'] ?? null)
            || !is_array($input['policy'] ?? null)
            || !is_array($input['profile'] ?? null)
            || !self::sha256($input['contractSha256'] ?? null)
        ) {
            return false;
        }
        $prepared =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
                $input['checkout'],
                $input['policy'],
                $input['profile']
            );
        return ($prepared['valid'] ?? null) === true
            && hash_equals(
                $input['contractSha256'],
                (string) ($prepared['contractSha256'] ?? '')
            );
    }

    private static function providerInput(array $input): bool
    {
        if (!self::boundedInput($input, 'stripe-sandbox')) {
            return false;
        }
        $plan = $input['contactPlan'];
        return ($plan['packageVersion'] ?? null) === '0.1.4'
            && ($plan['runtimeProviderTransport'] ?? null)
                === 'provider_read_only';
    }

    private static function boundedInput(array $input, string $target): bool
    {
        $keys = array_keys($input);
        $expected = [
            'claimStateSha256', 'contactPlan', 'contactTarget',
            'executionStartStateSha256', 'planSha256',
        ];
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected
            || ($input['contactTarget'] ?? null) !== $target
            || !is_array($input['contactPlan'] ?? null)
            || !self::sha256($input['claimStateSha256'] ?? null)
            || !self::sha256($input['executionStartStateSha256'] ?? null)
            || !self::sha256($input['planSha256'] ?? null)
        ) {
            return false;
        }
        try {
            $encoded = json_encode(
                $input['contactPlan'],
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            return false;
        }
        return hash_equals(
            $input['planSha256'],
            hash('sha256', $encoded)
        );
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}

?>
