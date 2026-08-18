<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
$coreDirectory = getenv('RED_CMS_CORE');
if (!is_string($coreDirectory) || $coreDirectory === '') {
    $coreDirectory = dirname($projectDirectory) . '/redcms v5.1';
}
if (!is_file($coreDirectory . '/includes/addon_adapter_helpers.php')) {
    throw new RuntimeException('RED-CMS core not found; set RED_CMS_CORE.');
}
require_once $coreDirectory . '/includes/addon_adapter_helpers.php';
require_once $projectDirectory . '/package/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory
    . '/package/StripeSandboxCheckoutTransportPlanner.php';
require_once $projectDirectory
    . '/package/StripeSandboxCheckoutTransportResponseGate.php';
require_once $projectDirectory
    . '/package/StripeSandboxCheckoutWireCodec.php';
require_once $projectDirectory
    . '/package/StripeSandboxCheckoutCreationContract.php';
require_once $projectDirectory
    . '/package/StripeSandboxCheckoutCreationSyntheticExecutor.php';
require_once $projectDirectory
    . '/package/StripeSandboxReadOnlyProbeTransport.php';
require_once $projectDirectory
    . '/package/StripeSandboxReadOnlyProbeOutcomeGate.php';
require_once $projectDirectory
    . '/package/StripeSandboxReadOnlyProbeSyntheticExecutor.php';
require_once $projectDirectory
    . '/package/StripeTypedOfflineCheckoutAdapter.php';

$assertions = 0;

function red_stripe_p3e9b1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e9b1_checkout(): array
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

function red_stripe_p3e9b1_policy(): array
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

function red_stripe_p3e9b1_profile(): array
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

try {
    $executorSource = (string) file_get_contents(
        $projectDirectory
            . '/package/StripeSandboxCheckoutCreationSyntheticExecutor.php'
    );
    $handlerSource = (string) file_get_contents(
        $projectDirectory . '/package/StripeTypedOfflineCheckoutAdapter.php'
    );
    $handlerStart = strpos(
        $handlerSource,
        'private static function syntheticCheckout'
    );
    $handlerEnd = strpos(
        $handlerSource,
        'private static function providerInput'
    );
    if (!is_int($handlerStart)
        || !is_int($handlerEnd)
        || $handlerEnd <= $handlerStart
    ) {
        throw new RuntimeException('Synthetic Checkout handler unavailable.');
    }
    $syntheticHandlerSource = substr(
        $handlerSource,
        $handlerStart,
        $handlerEnd - $handlerStart
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'PDO', 'mysqli', 'shell_exec(', 'system(', 'passthru(',
        'sleep(', 'usleep(', 'error_log(', 'print_r(', 'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e9b1_assert(
            !str_contains(
                $executorSource . $syntheticHandlerSource,
                $forbiddenToken
            ),
            $forbiddenToken . ' is absent from synthetic Checkout source'
        );
    }
    foreach ([
        'sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_',
    ] as $credentialLiteral) {
        red_stripe_p3e9b1_assert(
            !str_contains(
                $executorSource . $syntheticHandlerSource,
                $credentialLiteral
            ),
            $credentialLiteral . ' literal is absent'
        );
    }
    red_stripe_p3e9b1_assert(
        !str_contains($executorSource, 'Read_Only_Probe_Transport')
            && !str_contains(
                $syntheticHandlerSource,
                'new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport'
            ),
        'synthetic Checkout path cannot construct provider transport'
    );

    $manifest = json_decode(
        (string) file_get_contents($projectDirectory . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $identity = json_decode(
        (string) file_get_contents(
            $projectDirectory . '/package/identity.json'
        ),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3e9b1_assert(
        ($manifest['version'] ?? null) === '0.1.5'
            && ($identity['futureManifest']['version'] ?? null) === '0.1.5'
            && ($identity['status'] ?? null)
                === 'p3e9b1_synthetic_checkout_operation_available',
        'manifest and identity advance exactly to synthetic package 0.1.5'
    );
    red_stripe_p3e9b1_assert(
        count($manifest['integrity']['files'] ?? []) === 14,
        'integrity inventory covers the exact fourteen payload files'
    );
    $inventoryPaths = [];
    foreach ($manifest['integrity']['files'] as $inventoryFile) {
        $path = $inventoryFile['path'] ?? null;
        $sha256 = $inventoryFile['sha256'] ?? null;
        red_stripe_p3e9b1_assert(
            is_string($path)
                && !array_key_exists($path, $inventoryPaths)
                && is_string($sha256)
                && hash_equals(
                    $sha256,
                    hash_file('sha256', $projectDirectory . '/package/' . $path)
                ),
            'integrity SHA-256 matches package file ' . (string) $path
        );
        $inventoryPaths[$path] = true;
    }
    foreach ([
        'StripeCheckoutResponseNormalizer.php',
        'StripeSandboxCheckoutTransportPlanner.php',
        'StripeSandboxCheckoutTransportResponseGate.php',
        'StripeSandboxCheckoutWireCodec.php',
        'StripeSandboxCheckoutCreationContract.php',
    ] as $path) {
        red_stripe_p3e9b1_assert(
            hash_equals(
                hash_file('sha256', $projectDirectory . '/src/' . $path),
                hash_file('sha256', $projectDirectory . '/package/' . $path)
            ),
            'package adopts byte-identical reviewed source ' . $path
        );
    }
    red_stripe_p3e9b1_assert(
        hash_file(
            'sha256',
            $projectDirectory
                . '/package/migrations/2026-08-16-create-checkout-attempts.sql'
        ) === 'f58ae3b56d5b96d80f2757162e41e0fa4540f5e652934b9708e3884be633c2fa'
            && hash_file(
                'sha256',
                $projectDirectory
                    . '/package/migrations/2026-08-16-create-event-receipts.sql'
            ) === '20b516693d15bf2fb3829de6d9c9fe44202af03b846a05262d0c79f2b0cd2b8d',
        'both historical migration checksums remain byte-identical'
    );

    $checkout = red_stripe_p3e9b1_checkout();
    $policy = red_stripe_p3e9b1_policy();
    $profile = red_stripe_p3e9b1_profile();
    $prepared =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
            $checkout,
            $policy,
            $profile
        );
    red_stripe_p3e9b1_assert(
        ($prepared['valid'] ?? null) === true
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $prepared['contractSha256'] ?? ''
            ) === 1,
        'adopted contract prepares one exact synthetic Checkout plan'
    );
    $syntheticKey = 'rk_' . 'test_' . str_repeat('x', 32);
    $executor =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Synthetic_Executor();
    $outcome = $executor->execute(
        $checkout,
        $policy,
        $profile,
        $prepared['contractSha256'],
        $syntheticKey
    );
    red_stripe_p3e9b1_assert(
        $executor->calls() === 1
            && ($outcome['valid'] ?? null) === true
            && ($outcome['contactTarget'] ?? null)
                === 'synthetic-checkout-package'
            && ($outcome['outcome'] ?? null)
                === 'checkout_contract_accepted'
            && ($outcome['networkAccess'] ?? null) === false
            && ($outcome['providerContact'] ?? null) === false
            && ($outcome['providerMutation'] ?? null) === false
            && ($outcome['checkoutCreation'] ?? null) === false
            && ($outcome['payment'] ?? null) === false
            && ($outcome['retryAuthorized'] ?? null) === false
            && ($outcome['executionPerformed'] ?? null) === true,
        'one synthetic package execution returns only closed non-network facts'
    );
    red_stripe_p3e9b1_assert(
        ($outcome['checkoutUrlIncluded'] ?? null) === false
            && ($outcome['credentialIncluded'] ?? null) === false
            && !str_contains(json_encode($outcome), $syntheticKey)
            && !str_contains(json_encode($outcome), 'checkout.stripe.com'),
        'credential and validated Checkout URL are absent from package output'
    );
    try {
        $executor->execute(
            $checkout,
            $policy,
            $profile,
            $prepared['contractSha256'],
            $syntheticKey
        );
        $repeatRefused = false;
    } catch (RuntimeException $exception) {
        $repeatRefused = $exception->getMessage()
            === 'synthetic_checkout_refused';
    }
    red_stripe_p3e9b1_assert(
        $repeatRefused && $executor->calls() === 2,
        'synthetic Checkout executor is permanently one-use'
    );

    foreach ([
        ['contractSha256', str_repeat('f', 64)],
        ['credentialMode', 'restricted_test_read'],
        ['providerMutation', false],
        ['automaticRetry', true],
    ] as [$field, $value]) {
        $changedProfile = $profile;
        $changedHash = $prepared['contractSha256'];
        if ($field === 'contractSha256') {
            $changedHash = $value;
        } else {
            $changedProfile[$field] = $value;
        }
        $case =
            new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Synthetic_Executor();
        try {
            $case->execute(
                $checkout,
                $policy,
                $changedProfile,
                $changedHash,
                $syntheticKey
            );
            $refused = false;
        } catch (RuntimeException $exception) {
            $refused = $exception->getMessage()
                === 'synthetic_checkout_refused';
        }
        red_stripe_p3e9b1_assert(
            $refused && $case->calls() === 1,
            'synthetic executor refuses changed contract field ' . $field
        );
    }

    $input = [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => $checkout,
        'policy' => $policy,
        'profile' => $profile,
        'contractSha256' => $prepared['contractSha256'],
    ];
    $scopedAccess = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        ['stripe.secret-key' => $syntheticKey]
    );
    $request = new RED_Addon_Adapter_Request(
        'redcms.store-lite-stripe-checkout/checkout',
        'checkout.create-sandbox-synthetic',
        $input,
        $scopedAccess
    );
    $result =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            $request
        );
    red_stripe_p3e9b1_assert(
        $result->successState()
            && $result->error() === ''
            && $result->data()['contactTarget']
                === 'synthetic-checkout-package'
            && $result->data()['networkAccess'] === false
            && $result->data()['providerContact'] === false
            && $result->data()['checkoutCreation'] === false
            && !str_contains(json_encode($result->data()), $syntheticKey),
        'typed package handler succeeds only with scoped key and closed result'
    );

    $unscopedAccess = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        [
            'stripe.secret-key' => $syntheticKey,
            'stripe.webhook-secret' => 'whsec_' . str_repeat('y', 32),
        ]
    );
    $unscoped =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox-synthetic',
                $input,
                $unscopedAccess
            )
        );
    red_stripe_p3e9b1_assert(
        !$unscoped->successState()
            && $unscoped->error() === 'synthetic_checkout_secret_refused',
        'webhook-secret visibility fails the scoped synthetic operation closed'
    );

    $changedInput = $input;
    $changedInput['profile']['credentialMode'] = 'restricted_test_read';
    $changed =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox-synthetic',
                $changedInput,
                $scopedAccess
            )
        );
    red_stripe_p3e9b1_assert(
        !$changed->successState()
            && $changed->error() === 'synthetic_checkout_input_refused',
        'read-only profile is refused before package secret access'
    );
    $wrongOperation =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox',
                $input,
                $scopedAccess
            )
        );
    red_stripe_p3e9b1_assert(
        !$wrongOperation->successState()
            && $wrongOperation->error() === 'unsupported_operation',
        'real Checkout creation operation remains unavailable'
    );

    echo 'P3E-9B1 synthetic Checkout package self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, database, Checkout Session, or payment occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
