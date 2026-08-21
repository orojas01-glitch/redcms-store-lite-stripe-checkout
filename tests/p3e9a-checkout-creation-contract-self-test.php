<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportPlanner.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportResponseGate.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutWireCodec.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutCreationContract.php';

$assertions = 0;

function red_stripe_p3e9a_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e9a_checkout(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'idempotencySha256' => str_repeat('b', 64),
        'lineItems' => [
            [
                'name' => 'Dog scarf - Small / Red',
                'quantity' => 2,
                'unitAmountMinor' => 1999,
                'lineTotalMinor' => 3998,
            ],
            [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ],
        ],
    ];
}

function red_stripe_p3e9a_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
        'createdAtEpoch' => 1787025600,
        'expiresAtEpoch' => 1787027400,
    ];
}

function red_stripe_p3e9a_profile(): array
{
    return [
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'contractVersion' => 'p3e9a-v1',
        'operation' => 'checkout.create-sandbox',
        'contactTarget' => 'stripe-sandbox',
        'credentialMode' => 'restricted_test_write',
        'providerContact' => true,
        'providerMutation' => true,
        'checkoutCreation' => true,
        'payment' => false,
        'webhook' => false,
        'browserNavigation' => false,
        'orderMutation' => false,
        'clientDeployment' => false,
        'oneAttempt' => true,
        'automaticRetry' => false,
    ];
}

function red_stripe_p3e9a_envelope(): array
{
    return [
        'statusCode' => 200,
        'contentType' => 'application/json; charset=utf-8',
        'bodyBytes' => 2048,
        'bodySha256' => str_repeat('c', 64),
        'requestId' => 'req_AbCdEfGhIjKlMnOp',
        'tlsVersion' => 'TLSv1.3',
        'redirectCount' => 0,
    ];
}

function red_stripe_p3e9a_projection(): array
{
    $session = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/' . $session
            . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
        'mode' => 'payment',
        'status' => 'open',
        'payment_status' => 'unpaid',
        'amount_total' => 5897,
        'currency' => 'usd',
        'client_reference_id' =>
            'ord_0123456789abcdef0123456789abcdef',
        'metadata' => [
            'redcms_order_snapshot_sha256' => str_repeat('a', 64),
            'redcms_idempotency_sha256' => str_repeat('b', 64),
        ],
        'livemode' => false,
        'expires_at' => 1787027400,
        'after_expiration' => null,
    ];
}

function red_stripe_p3e9a_prepare_refused(
    array $checkout,
    array $policy,
    array $profile,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
        $checkout,
        $policy,
        $profile
    ) === [
        'valid' => false,
        'contract' => null,
        'contractSha256' => '',
        'errors' => [$error],
    ];
}

function red_stripe_p3e9a_accept_refused(
    array $checkout,
    array $policy,
    array $profile,
    array $envelope,
    array $projection,
    string $error
): bool {
    return RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::accept(
        $checkout,
        $policy,
        $profile,
        $envelope,
        $projection
    ) === [
        'valid' => false,
        'result' => null,
        'contractSha256' => '',
        'responseEvidenceSha256' => '',
        'resultSha256' => '',
        'errors' => [$error],
    ];
}

try {
    $source = (string) file_get_contents(
        $projectDirectory . '/src/StripeSandboxCheckoutCreationContract.php'
    );
    foreach ([
        'curl_',
        'fsockopen(',
        'file_get_contents(',
        'fopen(',
        'stream_',
        'socket_',
        'PDO',
        'mysqli',
        '$_SERVER',
        '$_POST',
        'getenv(',
        'putenv(',
        'shell_exec(',
        'exec(',
        'usleep(',
        'sleep(',
        'sk_test_',
        'sk_live_',
        'rk_test_',
        'rk_live_',
        'whsec_',
    ] as $forbiddenToken) {
        red_stripe_p3e9a_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the pure P3E-9A source'
        );
    }
    red_stripe_p3e9a_assert(
        count(get_included_files()) === 6,
        'fixture loads only itself and five dependency-free source contracts'
    );
    red_stripe_p3e9a_assert(
        hash_equals(
            hash_file(
                'sha256',
                $projectDirectory
                    . '/src/StripeSandboxCheckoutCreationContract.php'
            ),
            hash_file(
                'sha256',
                $projectDirectory
                    . '/package/StripeSandboxCheckoutCreationContract.php'
            )
        ),
        'later package adoption keeps the P3E-9A source byte-identical'
    );
    $manifest = json_decode(
        (string) file_get_contents($projectDirectory . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3e9a_assert(
        ($manifest['version'] ?? null) === '0.1.7'
            && count($manifest['integrity']['files'] ?? []) === 15,
        'later P3E-9B package adoption is exact and integrity checked'
    );

    $checkout = red_stripe_p3e9a_checkout();
    $policy = red_stripe_p3e9a_policy();
    $profile = red_stripe_p3e9a_profile();
    $prepared =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
            $checkout,
            $policy,
            $profile
        );
    red_stripe_p3e9a_assert(
        ($prepared['valid'] ?? null) === true
            && is_array($prepared['contract'] ?? null)
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $prepared['contractSha256'] ?? ''
            ) === 1
            && ($prepared['errors'] ?? null) === [],
        'exact synthetic USD order produces one hashed creation contract'
    );
    $contract = $prepared['contract'];
    red_stripe_p3e9a_assert(
        $contract['packageId'] === 'redcms.store-lite-stripe-checkout'
            && $contract['contractVersion'] === 'p3e9a-v1'
            && $contract['operation'] === 'checkout.create-sandbox'
            && $contract['contactTarget'] === 'stripe-sandbox',
        'contract fixes package, version, operation, and sandbox target'
    );
    red_stripe_p3e9a_assert(
        $contract['credential'] === [
            'mode' => 'restricted_test_write',
            'secretSettingKey' => 'stripe.secret-key',
            'valueIncluded' => false,
        ]
            && !preg_match(
                '/\b(?:sk|rk)_(?:test|live)_[A-Za-z0-9]+\b/',
                serialize($contract)
            ),
        'contract names only the least-privilege value-free credential mode'
    );
    red_stripe_p3e9a_assert(
        $contract['request']['method'] === 'POST'
            && $contract['request']['url']
                === 'https://api.stripe.com/v1/checkout/sessions'
            && $contract['request']['authorization']['valueIncluded'] === false
            && !array_key_exists(
                'Authorization',
                $contract['request']['headers']
            ),
        'request reuses the exact P3E endpoint and non-secret auth boundary'
    );
    red_stripe_p3e9a_assert(
        str_contains(
            $contract['request']['body'],
            '&expires_at=1787027400'
        )
            && !str_contains(
                $contract['request']['body'],
                'after_expiration'
            )
            && !str_contains($contract['request']['body'], 'customer')
            && !str_contains($contract['request']['body'], 'email'),
        'request adds only minimum expiry and no recovery or customer fields'
    );
    red_stripe_p3e9a_assert(
        $contract['request']['bodyBytes']
            === strlen($contract['request']['body'])
            && $contract['request']['bodySha256']
                === hash('sha256', $contract['request']['body'])
            && $contract['request']['bodyBytes'] <= 65536,
        'extended canonical body remains exactly measured, hashed, and bounded'
    );
    red_stripe_p3e9a_assert(
        $contract['expiry'] === [
            'createdAtEpoch' => 1787025600,
            'expiresAtEpoch' => 1787027400,
            'durationSeconds' => 1800,
            'recoveryEnabled' => false,
        ],
        'contract fixes the reviewed thirty-minute expiry without recovery'
    );
    red_stripe_p3e9a_assert(
        $contract['requestedEffect'] === [
            'providerContact' => true,
            'providerMutation' => true,
            'checkoutCreation' => true,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'clientDeployment' => false,
            'oneAttempt' => true,
            'automaticRetry' => false,
        ],
        'future effect is exact and excludes payment, retry, and client state'
    );
    red_stripe_p3e9a_assert(
        $contract['currentExecution'] === [
            'authorized' => false,
            'network' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'clientDeployment' => false,
        ],
        'P3E-9A contract explicitly authorizes and executes nothing'
    );
    red_stripe_p3e9a_assert(
        $prepared ===
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
                $checkout,
                $policy,
                $profile
            ),
        'identical inputs produce an identical contract and hash'
    );

    $invalidPolicy = $policy;
    $invalidPolicy['expiresAtEpoch'] = $policy['createdAtEpoch'] + 1799;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $checkout,
            $invalidPolicy,
            $profile,
            'creation_policy_invalid'
        ),
        'expiry shorter than thirty minutes is refused before encoding'
    );
    $invalidPolicy = $policy;
    $invalidPolicy['expiresAtEpoch'] = $policy['createdAtEpoch'] + 86401;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $checkout,
            $invalidPolicy,
            $profile,
            'creation_policy_invalid'
        ),
        'expiry longer than twenty-four hours is refused before encoding'
    );
    $invalidPolicy = $policy;
    $invalidPolicy['recoveryEnabled'] = false;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $checkout,
            $invalidPolicy,
            $profile,
            'creation_policy_invalid'
        ),
        'extra policy fields are refused rather than copied to Stripe'
    );
    $invalidCheckout = $checkout;
    $invalidCheckout['currency'] = 'COP';
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $invalidCheckout,
            $policy,
            $profile,
            'creation_checkout_refused'
        ),
        'the first Checkout-creation contract remains fixed to synthetic USD'
    );
    $invalidCheckout = $checkout;
    $invalidCheckout['customerEmail'] = 'person@example.test';
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $invalidCheckout,
            $policy,
            $profile,
            'creation_checkout_refused'
        ),
        'customer identity cannot enter the closed checkout projection'
    );
    $invalidProfile = $profile;
    $invalidProfile['credentialMode'] = 'restricted_test_read';
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $checkout,
            $policy,
            $invalidProfile,
            'operation_profile_invalid'
        ),
        'the completed read-only credential profile cannot be reused'
    );
    $invalidProfile = $profile;
    $invalidProfile['providerMutation'] = false;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $checkout,
            $policy,
            $invalidProfile,
            'operation_profile_invalid'
        ),
        'a mutation-disabled profile cannot describe Checkout creation'
    );
    $invalidProfile = $profile;
    $invalidProfile['automaticRetry'] = true;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_prepare_refused(
            $checkout,
            $policy,
            $invalidProfile,
            'operation_profile_invalid'
        ),
        'automatic retry remains refused by the first creation contract'
    );

    $envelope = red_stripe_p3e9a_envelope();
    $projection = red_stripe_p3e9a_projection();
    $accepted =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::accept(
            $checkout,
            $policy,
            $profile,
            $envelope,
            $projection
        );
    red_stripe_p3e9a_assert(
        ($accepted['valid'] ?? null) === true
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $accepted['responseEvidenceSha256'] ?? ''
            ) === 1
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $accepted['resultSha256'] ?? ''
            ) === 1
            && ($accepted['errors'] ?? null) === [],
        'exact synthetic extended projection yields bounded hashed evidence'
    );
    red_stripe_p3e9a_assert(
        $accepted['contractSha256'] === $prepared['contractSha256'],
        'accepted response remains bound to the exact creation contract'
    );
    red_stripe_p3e9a_assert(
        $accepted['result'] === [
            'operation' => 'checkout.create-sandbox',
            'checkoutSessionRef' =>
                'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
            'checkoutUrlValidated' => true,
            'mode' => 'payment',
            'status' => 'open',
            'paymentStatus' => 'unpaid',
            'amountMinor' => 5897,
            'currency' => 'usd',
            'expiresAtEpoch' => 1787027400,
            'recoveryEnabled' => false,
            'livemode' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'clientDeployment' => false,
        ],
        'result is closed, non-live, unpaid, non-executing, and non-retryable'
    );
    red_stripe_p3e9a_assert(
        !array_key_exists('checkoutUrl', $accepted['result'])
            && !str_contains(
                json_encode(
                    $accepted,
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                'checkout.stripe.com'
            ),
        'validated Checkout URL is discarded from the P3E-9A result'
    );
    red_stripe_p3e9a_assert(
        $accepted ===
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::accept(
                $checkout,
                $policy,
                $profile,
                $envelope,
                $projection
            ),
        'identical synthetic response produces identical bounded evidence'
    );

    $invalidProjection = $projection;
    $invalidProjection['expires_at']++;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_accept_refused(
            $checkout,
            $policy,
            $profile,
            $envelope,
            $invalidProjection,
            'creation_response_refused'
        ),
        'mismatched Session expiry is refused without partial result'
    );
    $invalidProjection = $projection;
    $invalidProjection['after_expiration'] = [
        'recovery' => ['enabled' => true],
    ];
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_accept_refused(
            $checkout,
            $policy,
            $profile,
            $envelope,
            $invalidProjection,
            'creation_response_refused'
        ),
        'recovery-enabled Session projection is refused'
    );
    $invalidProjection = $projection;
    $invalidProjection['livemode'] = true;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_accept_refused(
            $checkout,
            $policy,
            $profile,
            $envelope,
            $invalidProjection,
            'creation_response_refused'
        ),
        'live-mode projection is refused through the retained response gate'
    );
    $invalidProjection = $projection;
    $invalidProjection['payment_status'] = 'paid';
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_accept_refused(
            $checkout,
            $policy,
            $profile,
            $envelope,
            $invalidProjection,
            'creation_response_refused'
        ),
        'paid projection is refused by the creation-only contract'
    );
    $invalidProjection = $projection;
    $invalidProjection['customer_email'] = 'person@example.test';
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_accept_refused(
            $checkout,
            $policy,
            $profile,
            $envelope,
            $invalidProjection,
            'creation_response_refused'
        ),
        'extra customer response fields are refused by exact projection shape'
    );
    $invalidEnvelope = $envelope;
    $invalidEnvelope['statusCode'] = 500;
    red_stripe_p3e9a_assert(
        red_stripe_p3e9a_accept_refused(
            $checkout,
            $policy,
            $profile,
            $invalidEnvelope,
            $projection,
            'creation_response_refused'
        ),
        'provider error evidence yields no accepted creation result'
    );

    echo 'P3E-9A Checkout-creation contract self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
