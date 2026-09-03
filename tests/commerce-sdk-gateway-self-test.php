<?php

declare(strict_types=1);

namespace Stripe {
    final class FakeSessionsService
    {
        public array $calls = [];
        public static bool $throw = false;

        public function create(array $params, array $options): object
        {
            $this->calls[] = [$params, $options];
            if (self::$throw) {
                throw new \RuntimeException('synthetic provider ambiguity');
            }
            return (object) [
                'id' => 'cs_test_' . str_repeat('a', 24),
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_' . str_repeat('a', 24),
                'mode' => 'subscription',
                'status' => 'open',
                'payment_status' => 'unpaid',
                'livemode' => false,
                'expires_at' => 1788397030,
            ];
        }
    }

    final class StripeClient
    {
        public static array $configs = [];
        public object $checkout;

        public function __construct(array $config)
        {
            self::$configs[] = $config;
            $this->checkout = (object) [
                'sessions' => new FakeSessionsService(),
            ];
        }
    }
}

namespace {
    $packageRoot = dirname(__DIR__) . '/package';
    require_once $packageRoot . '/StripeSdkCommerceGateway.php';

    $assertions = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        $assertions++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    };
    $params = [
        'mode' => 'subscription',
        'ui_mode' => 'hosted',
        'line_items' => [[
            'price' => 'price_test_12345678',
            'quantity' => 1,
        ]],
        'success_url' => 'https://commerce.red-sphere.com/success',
        'cancel_url' => 'https://commerce.red-sphere.com/cancel',
        'integration_identifier' => 'red_sphere_ai_abcdwxyz',
    ];

    try {
        $source = (string) file_get_contents(
            $packageRoot . '/StripeSdkCommerceGateway.php'
        );
        $assert(str_contains($source, 'new \\Stripe\\StripeClient'), 'gateway instantiates StripeClient');
        $assert(!str_contains($source, 'setApiKey'), 'deprecated global key configuration is absent');
        $assert(
            !preg_match('/[\"\']payment_method_types[\"\']\s*=>/', $source),
            'gateway never assigns a payment-method allowlist'
        );
        $result = RED_CMS_Store_Lite_Stripe_SDK_Commerce_Gateway::createCheckoutSession(
            'rk_test_' . str_repeat('x', 48),
            $params,
            'rs_cart_' . str_repeat('a', 64)
        );
        $assert(($result['valid'] ?? false) === true, 'restricted sandbox key executes one SDK call');
        $assert(
            \Stripe\StripeClient::$configs[0]['stripe_version'] === '2026-08-26.dahlia',
            'gateway pins current stable API generation'
        );
        $assert(
            $result['session']['mode'] === 'subscription'
                && $result['session']['livemode'] === false,
            'gateway returns bounded non-live subscription Session evidence'
        );
        $assert(
            $result['session']['url']
                === 'https://checkout.stripe.com/c/pay/cs_test_' . str_repeat('a', 24),
            'only a validated hosted Checkout URL is returned'
        );
        $refused = RED_CMS_Store_Lite_Stripe_SDK_Commerce_Gateway::createCheckoutSession(
            'sk_live_' . str_repeat('x', 48),
            $params,
            'rs_cart_' . str_repeat('a', 64)
        );
        $assert($refused['valid'] === false, 'live and unrestricted keys are refused in sandbox contract');
        \Stripe\FakeSessionsService::$throw = true;
        $indeterminate = RED_CMS_Store_Lite_Stripe_SDK_Commerce_Gateway::createCheckoutSession(
            'rk_test_' . str_repeat('x', 48),
            $params,
            'rs_cart_' . str_repeat('b', 64)
        );
        $assert(
            $indeterminate['valid'] === false
                && $indeterminate['networkAttempted'] === true
                && $indeterminate['automaticRetry'] === false,
            'post-boundary failure is indeterminate and never automatically retried'
        );

        echo 'Stripe SDK commerce gateway passed '
            . $assertions . " assertions.\n";
    } catch (\Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage() . "\n");
        exit(1);
    }
}
