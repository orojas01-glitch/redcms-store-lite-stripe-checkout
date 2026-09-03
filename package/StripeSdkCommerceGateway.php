<?php

declare(strict_types=1);

/** Bounded Stripe PHP SDK gateway for sandbox subscription Checkout creation. */
final class RED_CMS_Store_Lite_Stripe_SDK_Commerce_Gateway
{
    private const API_VERSION = '2026-08-26.dahlia';

    public static function createCheckoutSession(
        string $restrictedKey,
        array $sessionParams,
        string $idempotencyKey
    ): array {
        if (preg_match('/\Ark_test_[A-Za-z0-9]{24,200}\z/D', $restrictedKey) !== 1
            || preg_match('/\Ars_cart_[0-9a-f]{64}\z/D', $idempotencyKey) !== 1
            || !self::params($sessionParams)
            || !class_exists('\\Stripe\\StripeClient')
        ) {
            return self::invalid('commerce_sdk_checkout_input_refused');
        }
        try {
            $client = new \Stripe\StripeClient([
                'api_key' => $restrictedKey,
                'stripe_version' => self::API_VERSION,
            ]);
            $session = $client->checkout->sessions->create(
                $sessionParams,
                ['idempotency_key' => $idempotencyKey]
            );
            $restrictedKey = '';
            $normalized = self::session($session);
            if ($normalized === null) {
                return self::invalid('commerce_sdk_checkout_response_refused');
            }
            return [
                'valid' => true,
                'session' => $normalized,
                'networkAttempted' => true,
                'automaticRetry' => false,
                'errors' => [],
            ];
        } catch (Throwable $throwable) {
            $restrictedKey = '';
            return [
                'valid' => false,
                'session' => null,
                'networkAttempted' => true,
                'automaticRetry' => false,
                'errors' => ['commerce_sdk_checkout_indeterminate'],
            ];
        }
    }

    private static function params(array $value): bool
    {
        if (($value['mode'] ?? null) !== 'subscription'
            || ($value['ui_mode'] ?? null) !== 'hosted'
            || !is_array($value['line_items'] ?? null)
            || !array_is_list($value['line_items'])
            || count($value['line_items']) < 1
            || count($value['line_items']) > 48
            || array_key_exists('payment_method_types', $value)
            || array_key_exists('automatic_tax', $value)
        ) {
            return false;
        }
        foreach ($value['line_items'] as $line) {
            if (!is_array($line)
                || array_keys($line) !== ['price', 'quantity']
                || !is_string($line['price'] ?? null)
                || preg_match('/\Aprice_[A-Za-z0-9_]{8,160}\z/D', $line['price']) !== 1
                || !is_int($line['quantity'] ?? null)
                || $line['quantity'] < 1
                || $line['quantity'] > 100
            ) {
                return false;
            }
        }
        return true;
    }

    private static function session(object $value): ?array
    {
        $id = $value->id ?? null;
        $url = $value->url ?? null;
        $mode = $value->mode ?? null;
        $status = $value->status ?? null;
        $paymentStatus = $value->payment_status ?? null;
        $livemode = $value->livemode ?? null;
        $expiresAt = $value->expires_at ?? null;
        if (!is_string($id)
            || preg_match('/\Acs_test_[A-Za-z0-9_]{8,180}\z/D', $id) !== 1
            || !is_string($url)
            || !str_starts_with($url, 'https://checkout.stripe.com/')
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || $mode !== 'subscription'
            || !is_string($status)
            || !is_string($paymentStatus)
            || $livemode !== false
            || !is_int($expiresAt)
            || $expiresAt < 1
        ) {
            return null;
        }
        return [
            'idSha256' => hash('sha256', $id),
            'url' => $url,
            'mode' => $mode,
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'livemode' => false,
            'expiresAtEpoch' => $expiresAt,
        ];
    }

    private static function invalid(string $reason): array
    {
        return [
            'valid' => false,
            'session' => null,
            'networkAttempted' => false,
            'automaticRetry' => false,
            'errors' => [$reason],
        ];
    }
}
