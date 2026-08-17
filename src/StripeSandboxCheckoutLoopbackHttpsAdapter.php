<?php

declare(strict_types=1);

/**
 * P3E-5 proof orchestration for the loopback-only HTTPS transport.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Adapter
{
    public static function execute(
        array $checkout,
        array $policy,
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport
            $transport
    ): array {
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::class,
            false
        ) || !class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::class,
            false
        ) || !class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Decoded_Transcript_Double::class,
            false
        )) {
            return self::refused('', 'loopback_contract_unavailable');
        }

        $encoded =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
                $checkout,
                $policy
            );
        if (!self::encoded($encoded)) {
            return self::refused('', 'checkout_plan_refused');
        }
        $planSha256 = $encoded['planSha256'];

        try {
            $wireResponse = $transport->exchange($encoded['wireRequest']);
        } catch (Throwable $throwable) {
            return self::indeterminate(
                $planSha256,
                'transport_exception'
            );
        }

        $decoded =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::decode(
                $wireResponse
            );
        $transcript = self::transcript($decoded)
            ? $decoded['transcript']
            : [
                'outcome' => 'indeterminate',
                'code' => 'response_unusable',
                'envelope' => null,
                'projection' => null,
            ];
        $double =
            new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Decoded_Transcript_Double(
                $transcript
            );
        $result =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor::execute(
                $checkout,
                $policy,
                $double
            );
        if (($result['planSha256'] ?? null) !== $planSha256) {
            return self::refused('', 'plan_evidence_mismatch');
        }
        return $result;
    }

    private static function encoded(array $encoded): bool
    {
        return self::exactKeys($encoded, [
            'valid', 'wireRequest', 'planSha256', 'errors',
        ])
            && ($encoded['valid'] ?? null) === true
            && is_array($encoded['wireRequest'] ?? null)
            && self::sha256($encoded['planSha256'] ?? null)
            && ($encoded['errors'] ?? null) === [];
    }

    private static function transcript(array $decoded): bool
    {
        return self::exactKeys($decoded, [
            'valid', 'transcript', 'errors',
        ])
            && ($decoded['valid'] ?? null) === true
            && is_array($decoded['transcript'] ?? null)
            && ($decoded['errors'] ?? null) === [];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function refused(string $planSha256, string $error): array
    {
        return [
            'valid' => false,
            'status' => 'refused',
            'checkout' => null,
            'planSha256' => $planSha256,
            'responseEvidenceSha256' => '',
            'transportCode' => null,
            'retryAuthorized' => false,
            'errors' => [$error],
        ];
    }

    private static function indeterminate(
        string $planSha256,
        string $code
    ): array {
        return [
            'valid' => false,
            'status' => 'indeterminate',
            'checkout' => null,
            'planSha256' => $planSha256,
            'responseEvidenceSha256' => '',
            'transportCode' => $code,
            'retryAuthorized' => false,
            'errors' => ['transport_indeterminate'],
        ];
    }
}
