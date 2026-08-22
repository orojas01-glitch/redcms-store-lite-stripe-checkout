<?php

declare(strict_types=1);

/**
 * Typed provider operations through the uninvoked D4A real-POST boundary.
 *
 * RED-CMS may integrity-check and execute this registrar only to validate the
 * exact declared registration shape. Loading and registering the package
 * performs no secret resolution or network request. The provider-event route
 * remains unavailable.
 */
require_once __DIR__ . '/StripeSandboxReadOnlyProbeTransport.php';
require_once __DIR__ . '/StripeSandboxReadOnlyProbeOutcomeGate.php';
require_once __DIR__ . '/StripeSandboxReadOnlyProbeSyntheticExecutor.php';
require_once __DIR__ . '/StripeCheckoutResponseNormalizer.php';
require_once __DIR__ . '/StripeSandboxCheckoutTransportPlanner.php';
require_once __DIR__ . '/StripeSandboxCheckoutTransportResponseGate.php';
require_once __DIR__ . '/StripeBoundedJsonDecoder.php';
require_once __DIR__ . '/StripeSandboxCheckoutWireCodec.php';
require_once __DIR__ . '/StripeSandboxCheckoutCreationContract.php';
require_once __DIR__ . '/StripeSandboxCheckoutCreationSyntheticExecutor.php';
require_once __DIR__ . '/StripeSandboxCheckoutRealPostPreflight.php';
require_once __DIR__ . '/StripeSandboxCheckoutRealPostExchange.php';
require_once __DIR__ . '/StripeSandboxCheckoutRealPostTransport.php';
require_once __DIR__ . '/StripeSandboxCheckoutRealPostOperation.php';
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
