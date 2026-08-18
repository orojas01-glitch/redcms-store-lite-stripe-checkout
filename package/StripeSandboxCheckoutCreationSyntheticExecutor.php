<?php

declare(strict_types=1);

/**
 * One-use P3E-9B1 synthetic Checkout-creation package proof.
 *
 * It validates the adopted P3E-9A contract and a scoped restricted-test key,
 * then creates and validates fixed in-memory response facts. It contains no
 * provider transport, network client, database, or credential resolver.
 */
final class RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Synthetic_Executor
{
    private int $calls = 0;

    public function execute(
        array $checkout,
        array $policy,
        array $profile,
        string $expectedContractSha256,
        #[SensitiveParameter] string $restrictedTestKey
    ): array {
        $this->calls++;
        if ($this->calls !== 1
            || !self::sha256($expectedContractSha256)
            || !self::restrictedTestKey($restrictedTestKey)
        ) {
            $restrictedTestKey = '';
            throw new RuntimeException('synthetic_checkout_refused');
        }

        $prepared =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
                $checkout,
                $policy,
                $profile
            );
        if (($prepared['valid'] ?? null) !== true
            || !hash_equals(
                $expectedContractSha256,
                (string) ($prepared['contractSha256'] ?? '')
            )
        ) {
            $restrictedTestKey = '';
            throw new RuntimeException('synthetic_checkout_refused');
        }
        $restrictedTestKey = '';

        $session = 'cs_test_REDcmsP3E9BSyntheticSession01';
        $projection = [
            'id' => $session,
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/' . $session,
            'mode' => 'payment',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => $checkout['amountMinor'] ?? null,
            'currency' => strtolower((string) ($checkout['currency'] ?? '')),
            'client_reference_id' => $checkout['orderId'] ?? null,
            'metadata' => [
                'redcms_order_snapshot_sha256' =>
                    $checkout['orderSnapshotSha256'] ?? null,
                'redcms_idempotency_sha256' =>
                    $checkout['idempotencySha256'] ?? null,
            ],
            'livemode' => false,
            'expires_at' => $policy['expiresAtEpoch'] ?? null,
            'after_expiration' => null,
        ];
        try {
            $syntheticBody = json_encode(
                $projection,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            throw new RuntimeException('synthetic_checkout_failed');
        }
        $envelope = [
            'statusCode' => 200,
            'contentType' => 'application/json',
            'bodyBytes' => strlen($syntheticBody),
            'bodySha256' => hash('sha256', $syntheticBody),
            'requestId' => 'req_REDcmsP3E9BSynthetic',
            'tlsVersion' => 'TLSv1.3',
            'redirectCount' => 0,
        ];
        $accepted =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::accept(
                $checkout,
                $policy,
                $profile,
                $envelope,
                $projection
            );
        if (($accepted['valid'] ?? null) !== true
            || !is_array($accepted['result'] ?? null)
            || ($accepted['errors'] ?? null) !== []
            || ($accepted['contractSha256'] ?? null)
                !== $expectedContractSha256
        ) {
            throw new RuntimeException('synthetic_checkout_failed');
        }

        return [
            'valid' => true,
            'contactTarget' => 'synthetic-checkout-package',
            'outcome' => 'checkout_contract_accepted',
            'checkoutSessionRef' =>
                $accepted['result']['checkoutSessionRef'],
            'expiresAtEpoch' => $accepted['result']['expiresAtEpoch'],
            'contractSha256' => $accepted['contractSha256'],
            'responseEvidenceSha256' =>
                $accepted['responseEvidenceSha256'],
            'resultSha256' => $accepted['resultSha256'],
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'checkoutUrlIncluded' => false,
            'credentialIncluded' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'clientDeployment' => false,
            'executionPerformed' => true,
            'errors' => [],
        ];
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private static function restrictedTestKey(string $value): bool
    {
        $prefix = 'rk_' . 'test_';
        return str_starts_with($value, $prefix)
            && strlen($value) >= 24
            && strlen($value) <= 255
            && preg_match('/[^\x21-\x7E]/', $value) !== 1;
    }

    private static function sha256(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}
