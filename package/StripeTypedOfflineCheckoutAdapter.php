<?php

declare(strict_types=1);

/**
 * Typed offline contract probe plus the P3E-8B3B synthetic package proof.
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

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}

?>
