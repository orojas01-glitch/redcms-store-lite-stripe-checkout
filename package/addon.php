<?php

declare(strict_types=1);

/**
 * P3C-4 registration-only package entrypoint.
 *
 * RED-CMS may integrity-check and execute this registrar only to validate the
 * exact declared registration shape. Neither registered handler is available
 * for operational use in P3C-4.
 */
return static function ($registry): void {
    $registry->registerAdapter(
        'redcms.store-lite-stripe-checkout/checkout',
        static function (): never {
            throw new LogicException('p3c4_adapter_handler_not_operational');
        }
    );
    $registry->registerRoute(
        'redcms.store-lite-stripe-checkout/provider-events',
        static function (): never {
            throw new LogicException('p3c4_route_handler_not_operational');
        }
    );
};
