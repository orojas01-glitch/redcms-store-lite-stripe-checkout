<?php

declare(strict_types=1);

/**
 * P3E-2 one-attempt executor for a sealed in-memory transport double.
 *
 * It plans one non-secret request, invokes the supplied proof double exactly
 * once, and normalizes only a closed synthetic transcript. It contains no
 * credential resolver, HTTP implementation, persistence, retry, or network.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor
{
    private const INDETERMINATE_CODES = [
        'connect_timeout',
        'total_timeout',
        'connection_closed',
        'dns_failure',
        'tls_failure',
        'response_too_large',
        'provider_5xx',
        'response_unusable',
    ];

    public static function execute(
        array $checkout,
        array $policy,
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double
            $transport
    ): array {
        if (!class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::class,
            false
        ) || !class_exists(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate::class,
            false
        )) {
            return self::refused('', 'transport_contract_unavailable');
        }

        $planned =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner::plan(
                $checkout,
                $policy
            );
        if (!self::planned($planned)) {
            return self::refused('', 'checkout_plan_refused');
        }
        $planSha256 = $planned['planSha256'];

        try {
            $transcript = $transport->exchange($planned['plan']);
        } catch (Throwable $throwable) {
            return self::indeterminate(
                $planSha256,
                'transport_exception'
            );
        }
        if (!self::transcript($transcript)) {
            return self::refused(
                $planSha256,
                'transport_contract_refused'
            );
        }
        if ($transcript['outcome'] === 'indeterminate') {
            return self::indeterminate(
                $planSha256,
                $transcript['code']
            );
        }

        $statusCode = $transcript['envelope']['statusCode'] ?? null;
        if (is_int($statusCode) && $statusCode >= 500 && $statusCode <= 599) {
            return self::indeterminate($planSha256, 'provider_5xx');
        }
        if (is_int($statusCode) && $statusCode >= 400 && $statusCode <= 499) {
            return self::refused(
                $planSha256,
                'transport_response_refused'
            );
        }

        $expected = $checkout;
        unset($expected['lineItems']);
        $accepted =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate::accept(
                $expected,
                $transcript['envelope'],
                $transcript['projection']
            );
        if (!self::accepted($accepted)) {
            return self::indeterminate(
                $planSha256,
                'response_unusable'
            );
        }

        return [
            'valid' => true,
            'status' => 'checkout_ready',
            'checkout' => $accepted['checkout'],
            'planSha256' => $planSha256,
            'responseEvidenceSha256' =>
                $accepted['responseEvidenceSha256'],
            'transportCode' => null,
            'retryAuthorized' => false,
            'errors' => [],
        ];
    }

    private static function planned(array $planned): bool
    {
        return self::exactKeys($planned, [
            'valid', 'plan', 'planSha256', 'errors',
        ])
            && ($planned['valid'] ?? null) === true
            && is_array($planned['plan'] ?? null)
            && self::sha256($planned['planSha256'] ?? null)
            && ($planned['errors'] ?? null) === [];
    }

    private static function transcript(array $transcript): bool
    {
        if (!self::exactKeys($transcript, [
            'outcome', 'code', 'envelope', 'projection',
        ])) {
            return false;
        }
        if (($transcript['outcome'] ?? null) === 'response') {
            return ($transcript['code'] ?? null) === null
                && is_array($transcript['envelope'] ?? null)
                && is_array($transcript['projection'] ?? null);
        }
        if (($transcript['outcome'] ?? null) === 'indeterminate') {
            return is_string($transcript['code'] ?? null)
                && in_array(
                    $transcript['code'],
                    self::INDETERMINATE_CODES,
                    true
                )
                && ($transcript['envelope'] ?? null) === null
                && ($transcript['projection'] ?? null) === null;
        }
        return false;
    }

    private static function accepted(array $accepted): bool
    {
        return self::exactKeys($accepted, [
            'valid', 'checkout', 'responseEvidenceSha256', 'errors',
        ])
            && ($accepted['valid'] ?? null) === true
            && is_array($accepted['checkout'] ?? null)
            && self::sha256($accepted['responseEvidenceSha256'] ?? null)
            && ($accepted['errors'] ?? null) === [];
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
