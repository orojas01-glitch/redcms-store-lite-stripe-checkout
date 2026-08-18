<?php

declare(strict_types=1);

/**
 * P3E-8B3A typed package entrypoint.
 *
 * RED-CMS may integrity-check and execute this registrar only to validate the
 * exact declared registration shape. Loading and registering the package
 * performs no secret resolution or network request. The provider-event route
 * remains unavailable.
 */
require_once __DIR__ . '/StripeSandboxReadOnlyProbeTransport.php';
require_once __DIR__ . '/StripeSandboxReadOnlyProbeOutcomeGate.php';
require_once __DIR__ . '/StripeTypedOfflineCheckoutAdapter.php';

return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-stripe-checkout/checkout',
        [
            'RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter',
            'handle',
        ]
    );
    $registry->registerRoute(
        'redcms.store-lite-stripe-checkout/provider-events',
        static function (): never {
            throw new LogicException('p3c4_route_handler_not_operational');
        }
    );
};
