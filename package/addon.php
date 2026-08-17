<?php

declare(strict_types=1);

/**
 * P3D-7 typed offline package entrypoint.
 *
 * RED-CMS may integrity-check and execute this registrar only to validate the
 * exact declared registration shape. The adapter supports only the typed
 * value-free contract probe; the provider-event route remains unavailable.
 */
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
