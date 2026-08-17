<?php

declare(strict_types=1);

/**
 * Typed offline adoption of the RED-CMS adapter invocation boundary.
 *
 * P3D-7 deliberately supports only a value-free contract probe. It may
 * confirm that this package can privately resolve its two declared settings,
 * but it cannot construct a Stripe request, create a Checkout Session, read
 * Store Lite state, persist an attempt, or invoke another capability.
 */
final class RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter
{
    private const ADAPTER_ID =
        'redcms.store-lite-stripe-checkout/checkout';

    public static function handle(
        RED_Addon_Adapter_Request $request
    ): RED_Addon_Adapter_Result {
        if ($request->adapter() !== self::ADAPTER_ID
            || $request->operation() !== 'contract.probe'
            || $request->input() !== []
        ) {
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
}

?>
