<?php

declare(strict_types=1);

namespace Stripe {
    final class Webhook
    {
        public static array $calls = [];

        public static function constructEvent(
            string $payload,
            string $signature,
            string $secret,
            int $tolerance
        ): object {
            self::$calls[] = [$payload, $signature, $secret, $tolerance];
            if ($signature === 'invalid') {
                throw new \RuntimeException('invalid signature');
            }
            return (object) [
                'id' => 'evt_' . str_repeat('a', 24),
                'type' => 'invoice.paid',
                'api_version' => '2026-08-26.dahlia',
                'livemode' => false,
                'created' => 1788395230,
                'data' => (object) [],
            ];
        }
    }
}

namespace {
    $packageRoot = dirname(__DIR__) . '/package';
    require_once $packageRoot . '/StripeSdkWebhookVerifier.php';

    $assertions = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        $assertions++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    };
    $raw = '{"id":"evt_test","type":"invoice.paid"}';
    $signature = 't=1788395230,v1=' . str_repeat('a', 64);
    try {
        $verified = RED_CMS_Store_Lite_Stripe_SDK_Webhook_Verifier::verify(
            $raw,
            $signature,
            'whsec_' . str_repeat('x', 48)
        );
        $assert(($verified['valid'] ?? false) === true, 'SDK verifies the untouched body');
        $assert($verified['event']->type === 'invoice.paid', 'verified event remains transient for projection');
        $assert($verified['rawBodyPersisted'] === false, 'raw payload is not persistable evidence');
        $assert($verified['signaturePersisted'] === false, 'signature is not persistable evidence');
        $assert(
            \Stripe\Webhook::$calls[0][0] === $raw
                && \Stripe\Webhook::$calls[0][1] === $signature
                && \Stripe\Webhook::$calls[0][3] === 300,
            'SDK receives exact raw body, signature, and five-minute tolerance'
        );
        $assert(
            preg_match('/\A[0-9a-f]{64}\z/D', $verified['payloadSha256']) === 1,
            'only payload hash is durable evidence'
        );
        foreach ([
            ['', $signature, 'whsec_' . str_repeat('x', 48)],
            [$raw, 'invalid', 'whsec_' . str_repeat('x', 48)],
            [$raw, $signature, 'sk_test_' . str_repeat('x', 48)],
        ] as [$body, $header, $secret]) {
            $assert(
                RED_CMS_Store_Lite_Stripe_SDK_Webhook_Verifier::verify(
                    $body,
                    $header,
                    $secret
                )['valid'] === false,
                'empty body, invalid signature, and wrong secret scope fail closed'
            );
        }

        echo 'Stripe SDK webhook verifier passed '
            . $assertions . " assertions.\n";
    } catch (\Throwable $throwable) {
        fwrite(STDERR, $throwable->getMessage() . "\n");
        exit(1);
    }
}
