<?php

declare(strict_types=1);

/** One-use exchange boundary for the exact D4A Checkout Session POST. */
interface RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange
{
    public function exchange(array $wireRequest): array;

    public function calls(): int;
}
