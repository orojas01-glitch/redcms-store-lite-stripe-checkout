<?php

declare(strict_types=1);

/** Verify a Stripe signature with the official SDK before any projection. */
final class RED_CMS_Store_Lite_Stripe_SDK_Webhook_Verifier
{
    private const MAX_BODY_BYTES = 262144;
    private const MAX_SIGNATURE_BYTES = 4096;
    private const TOLERANCE_SECONDS = 300;

    public static function verify(
        string $rawBody,
        string $signature,
        string $endpointSecret
    ): array {
        $bodyBytes = strlen($rawBody);
        $signatureBytes = strlen($signature);
        if ($bodyBytes < 1
            || $bodyBytes > self::MAX_BODY_BYTES
            || preg_match('//u', $rawBody) !== 1
            || $signatureBytes < 1
            || $signatureBytes > self::MAX_SIGNATURE_BYTES
            || preg_match('/\At=[0-9]+,v1=[A-Fa-f0-9]{64}(?:,v1=[A-Fa-f0-9]{64})*\z/D', $signature) !== 1
            || preg_match('/\Awhsec_[A-Za-z0-9]{24,200}\z/D', $endpointSecret) !== 1
            || !class_exists('\\Stripe\\Webhook')
        ) {
            return self::invalid('commerce_webhook_signature_input_refused');
        }
        try {
            $event = \Stripe\Webhook::constructEvent(
                $rawBody,
                $signature,
                $endpointSecret,
                self::TOLERANCE_SECONDS
            );
            $endpointSecret = '';
            if (!is_object($event)
                || !is_string($event->id ?? null)
                || preg_match('/\Aevt_[A-Za-z0-9_]{8,160}\z/D', $event->id) !== 1
                || !is_string($event->type ?? null)
                || !is_string($event->api_version ?? null)
                || !is_bool($event->livemode ?? null)
                || !is_int($event->created ?? null)
            ) {
                return self::invalid('commerce_webhook_verified_event_refused');
            }
            return [
                'valid' => true,
                'event' => $event,
                'payloadSha256' => hash('sha256', $rawBody),
                'rawBodyPersisted' => false,
                'signaturePersisted' => false,
                'errors' => [],
            ];
        } catch (Throwable $throwable) {
            $endpointSecret = '';
            return self::invalid('commerce_webhook_signature_refused');
        }
    }

    private static function invalid(string $reason): array
    {
        return [
            'valid' => false,
            'event' => null,
            'payloadSha256' => '',
            'rawBodyPersisted' => false,
            'signaturePersisted' => false,
            'errors' => [$reason],
        ];
    }
}
