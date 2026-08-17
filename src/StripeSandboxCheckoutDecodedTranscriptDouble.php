<?php

declare(strict_types=1);

/**
 * P3E-4 proof-only bridge from one decoded transcript to the P3E-2 executor.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Decoded_Transcript_Double
    implements
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double
{
    private int $calls = 0;

    public function __construct(private array $transcript)
    {
    }

    public function exchange(array $requestPlan): array
    {
        $this->calls++;
        if ($this->calls !== 1) {
            throw new RuntimeException('decoded_transcript_already_used');
        }
        return $this->transcript;
    }
}
