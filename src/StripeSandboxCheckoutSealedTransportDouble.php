<?php

declare(strict_types=1);

/**
 * P3E-2 proof-only port for one sealed in-memory transport double.
 *
 * A production HTTP transport must not implement or use this proof interface.
 * That requires a later, separately reviewed gate.
 */
interface RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double
{
    /**
     * Accept one non-secret P3E-1 request plan and return one closed transcript.
     */
    public function exchange(array $requestPlan): array;
}
