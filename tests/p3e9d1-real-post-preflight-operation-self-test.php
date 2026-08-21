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
require_once $coreDirectory
    . '/includes/addon_sandbox_checkout_synthetic_execution_helpers.php';
require_once $coreDirectory
    . '/includes/addon_sandbox_checkout_real_post_preflight_helpers.php';
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
    . '/package/StripeSandboxCheckoutRealPostPreflight.php';
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

function red_stripe_p3e9d1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e9d1_hash(array $value): string
{
    return hash('sha256', json_encode(
        $value,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    ));
}

function red_stripe_p3e9d1_checkout(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'idempotencySha256' => str_repeat('b', 64),
        'lineItems' => [[
            'name' => 'Dog scarf - Small / Red',
            'quantity' => 2,
            'unitAmountMinor' => 1999,
            'lineTotalMinor' => 3998,
        ], [
            'name' => 'Delivery fee',
            'quantity' => 1,
            'unitAmountMinor' => 1899,
            'lineTotalMinor' => 1899,
        ]],
    ];
}

function red_stripe_p3e9d1_policy(): array
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

function red_stripe_p3e9d1_profile(): array
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

function red_stripe_p3e9d1_preflight(
    array $checkout,
    array $policy,
    array $profile,
    string $contractSha256
): array {
    $input = [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => $checkout,
        'policy' => $policy,
        'profile' => $profile,
        'contractSha256' => $contractSha256,
    ];
    $inputSha256 = red_addon_checkout_synthetic_hash($input);
    $syntheticPlan = [
        'valid' => true,
        'ready' => true,
        'status' => 'ready',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.5',
        'adapterId' => 'redcms.store-lite-stripe-checkout/checkout',
        'operation' => 'checkout.create-sandbox-synthetic',
        'manifestSha256' => str_repeat('d', 64),
        'inventorySha256' => str_repeat('e', 64),
        'inputSha256' => $inputSha256,
        'planSha256' => str_repeat('f', 64),
        'adapterInvoked' => false,
        'boundedOutcome' => null,
        'outcomeSha256' => '',
        'executionPerformed' => false,
        'networkAccess' => false,
        'providerContact' => false,
        'providerMutation' => false,
        'checkoutCreation' => false,
        'payment' => false,
        'webhook' => false,
        'browserNavigation' => false,
        'orderMutation' => false,
        'retryAuthorized' => false,
        'clientDeployment' => false,
        'errors' => [],
    ];
    $preflight = red_addon_checkout_real_post_preflight(
        $syntheticPlan,
        $input
    );
    unset($preflight['formFields']);
    return $preflight;
}

try {
    $source = (string) file_get_contents(
        $projectDirectory
            . '/package/StripeSandboxCheckoutRealPostPreflight.php'
    );
    $handler = (string) file_get_contents(
        $projectDirectory . '/package/StripeTypedOfflineCheckoutAdapter.php'
    );
    $handlerStart = strpos(
        $handler,
        'private static function realPostPreflight'
    );
    $handlerEnd = strpos($handler, 'private static function providerInput');
    if (!is_int($handlerStart)
        || !is_int($handlerEnd)
        || $handlerEnd <= $handlerStart
    ) {
        throw new RuntimeException('Real POST preflight handler unavailable.');
    }
    $preflightHandler = substr(
        $handler,
        $handlerStart,
        $handlerEnd - $handlerStart
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'php://input', 'PDO', 'mysqli', 'shell_exec(', 'system(',
        'passthru(', 'sleep(', 'usleep(', 'error_log(', 'print_r(',
        'var_dump(', '->secret(', 'Authorization:',
    ] as $forbiddenToken) {
        red_stripe_p3e9d1_assert(
            !str_contains($source . $preflightHandler, $forbiddenToken),
            $forbiddenToken . ' is absent from real POST preflight source'
        );
    }
    foreach (['sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_']
        as $credentialLiteral
    ) {
        red_stripe_p3e9d1_assert(
            !str_contains($source . $preflightHandler, $credentialLiteral),
            $credentialLiteral . ' literal is absent'
        );
    }

    $manifest = json_decode(
        (string) file_get_contents($projectDirectory . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $identity = json_decode(
        (string) file_get_contents($projectDirectory . '/package/identity.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3e9d1_assert(
        ($manifest['version'] ?? null) === '0.1.7'
            && ($identity['futureManifest']['version'] ?? null) === '0.1.7'
            && ($identity['status'] ?? null)
                === 'p3e9d1_canonical_core_hash_compatible',
        'manifest and identity advance to canonical-hash package 0.1.7'
    );
    red_stripe_p3e9d1_assert(
        count($manifest['integrity']['files'] ?? []) === 15,
        'integrity inventory covers exactly fifteen payload files'
    );
    $inventoryPaths = [];
    foreach ($manifest['integrity']['files'] as $inventoryFile) {
        $path = $inventoryFile['path'] ?? null;
        $sha256 = $inventoryFile['sha256'] ?? null;
        red_stripe_p3e9d1_assert(
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
    red_stripe_p3e9d1_assert(
        hash_equals(
            hash_file(
                'sha256',
                $projectDirectory
                    . '/src/StripeSandboxCheckoutRealPostPreflight.php'
            ),
            hash_file(
                'sha256',
                $projectDirectory
                    . '/package/StripeSandboxCheckoutRealPostPreflight.php'
            )
        ),
        'package adopts byte-identical reviewed real POST preflight source'
    );
    red_stripe_p3e9d1_assert(
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

    $checkout = red_stripe_p3e9d1_checkout();
    $policy = red_stripe_p3e9d1_policy();
    $profile = red_stripe_p3e9d1_profile();
    $prepared =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
            $checkout,
            $policy,
            $profile
        );
    red_stripe_p3e9d1_assert(
        ($prepared['valid'] ?? null) === true,
        'existing P3E-9A contract prepares exact adoption input'
    );
    $preflight = red_stripe_p3e9d1_preflight(
        $checkout,
        $policy,
        $profile,
        $prepared['contractSha256']
    );
    $coreInput = [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => $checkout,
        'policy' => $policy,
        'profile' => $profile,
        'contractSha256' => $prepared['contractSha256'],
    ];
    $reorderedInput = array_reverse($coreInput, true);
    red_stripe_p3e9d1_assert(
        $preflight['inputSha256']
                === red_addon_checkout_synthetic_hash($coreInput)
            && $preflight['inputSha256']
                === red_addon_checkout_synthetic_hash($reorderedInput),
        'D0 input SHA-256 is canonical across associative insertion order'
    );
    red_stripe_p3e9d1_assert(
        $preflight['inputSha256']
            !== red_stripe_p3e9d1_hash($coreInput),
        'regression fixture distinguishes canonical input from raw JSON order'
    );
    $adopted =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::adopt(
            $checkout,
            $policy,
            $profile,
            $prepared['contractSha256'],
            $preflight
        );
    red_stripe_p3e9d1_assert(
        ($adopted['valid'] ?? null) === true
            && ($adopted['adopted'] ?? null) === true
            && ($adopted['status'] ?? null) === 'request_contract_adopted'
            && ($adopted['packageVersion'] ?? null) === '0.1.7'
            && ($adopted['sourcePackageVersion'] ?? null) === '0.1.5',
        'exact canonical core D0 request is adopted into package 0.1.7'
    );
    red_stripe_p3e9d1_assert(
        ($adopted['operation'] ?? null)
                === 'checkout.create-sandbox-real-post-preflight'
            && ($adopted['providerOperation'] ?? null)
                === 'checkout.create-sandbox-real-post'
            && ($adopted['request']['method'] ?? null) === 'POST'
            && ($adopted['request']['host'] ?? null) === 'api.stripe.com'
            && ($adopted['request']['path'] ?? null)
                === '/v1/checkout/sessions',
        'preflight and future provider operations remain distinctly named'
    );
    $adoptedFields = [];
    foreach ($adopted['request']['formFields'] ?? [] as $field) {
        if (is_array($field) && is_string($field['name'] ?? null)) {
            $adoptedFields[$field['name']] = $field['value'] ?? null;
        }
    }
    red_stripe_p3e9d1_assert(
        ($adoptedFields['mode'] ?? null) === 'payment'
            && ($adoptedFields['expires_at'] ?? null) === 1787027400
            && ($adoptedFields
                ['line_items[0][price_data][unit_amount]'] ?? null) === 1999
            && ($adoptedFields['line_items[1][quantity]'] ?? null) === 1,
        'exact payment mode, expiry, and line items survive adoption'
    );
    red_stripe_p3e9d1_assert(
        ($adopted['restrictedTestWriteKeyRequired'] ?? null) === true
            && ($adopted['credentialValueIncluded'] ?? null) === false
            && ($adopted['authorizationHeaderIncluded'] ?? null) === false
            && ($adopted['executionReady'] ?? null) === false
            && ($adopted['networkAccess'] ?? null) === false
            && ($adopted['providerContact'] ?? null) === false
            && ($adopted['providerMutation'] ?? null) === false
            && ($adopted['checkoutCreation'] ?? null) === false
            && ($adopted['executionPerformed'] ?? null) === false,
        'adoption exposes no credential, transport, or provider effect'
    );

    foreach ([
        ['requestSha256', str_repeat('0', 64)],
        ['packageVersion', '0.1.7'],
        ['networkAccess', true],
        ['operation', 'checkout.create-sandbox'],
    ] as [$field, $value]) {
        $changed = $preflight;
        $changed[$field] = $value;
        $refused =
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::adopt(
                $checkout,
                $policy,
                $profile,
                $prepared['contractSha256'],
                $changed
            );
        red_stripe_p3e9d1_assert(
            ($refused['valid'] ?? null) === false
                && array_key_exists('request', $refused)
                && $refused['request'] === null
                && ($refused['executionPerformed'] ?? null) === false,
            'changed D0 preflight field is refused: ' . $field
        );
    }
    $extra = $preflight;
    $extra['providerObject'] = [];
    red_stripe_p3e9d1_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::adopt(
            $checkout,
            $policy,
            $profile,
            $prepared['contractSha256'],
            $extra
        )['valid'] === false,
        'extra provider object is refused without partial adoption'
    );
    $changedCheckout = $checkout;
    $changedCheckout['amountMinor']++;
    red_stripe_p3e9d1_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::adopt(
            $changedCheckout,
            $policy,
            $profile,
            $prepared['contractSha256'],
            $preflight
        )['valid'] === false,
        'changed Checkout input cannot borrow the original contract'
    );

    $input = [
        'contactTarget' => 'stripe-sandbox-real-post-preflight',
        'checkout' => $checkout,
        'policy' => $policy,
        'profile' => $profile,
        'contractSha256' => $prepared['contractSha256'],
        'realPostPreflight' => $preflight,
    ];
    $singleVisibleSecret = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        ['stripe.secret-key' => 'rk_' . 'test_' . str_repeat('x', 32)]
    );
    $result =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox-real-post-preflight',
                $input,
                $singleVisibleSecret
            )
        );
    red_stripe_p3e9d1_assert(
        $result->successState()
            && $result->error() === ''
            && $result->data()['adopted'] === true
            && $result->data()['executionReady'] === false
            && $result->data()['executionPerformed'] === false,
        'typed adapter adopts the request without resolving its visible secret'
    );
    $visibleSecrets = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        [
            'stripe.secret-key' => 'rk_' . 'test_' . str_repeat('x', 32),
            'stripe.webhook-secret' => 'whsec_' . str_repeat('y', 32),
        ]
    );
    $withSecrets =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox-real-post-preflight',
                $input,
                $visibleSecrets
            )
        );
    red_stripe_p3e9d1_assert(
        $withSecrets->successState()
            && $withSecrets->data() === $result->data()
            && !str_contains(json_encode($withSecrets->data()), 'rk_test_')
            && !str_contains(json_encode($withSecrets->data()), 'whsec_'),
        'secret visibility cannot alter or enter the pure preflight result'
    );
    $wrongTarget = $input;
    $wrongTarget['contactTarget'] = 'stripe-sandbox';
    $wrong =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox-real-post-preflight',
                $wrongTarget,
                $singleVisibleSecret
            )
        );
    red_stripe_p3e9d1_assert(
        !$wrong->successState()
            && $wrong->error() === 'real_post_preflight_input_refused',
        'changed preflight target fails before adoption'
    );
    $realOperation =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'checkout.create-sandbox-real-post',
                $input,
                $visibleSecrets
            )
        );
    red_stripe_p3e9d1_assert(
        !$realOperation->successState()
            && $realOperation->error() === 'unsupported_operation',
        'actual real POST operation remains unavailable'
    );

    echo 'P3E-9D1 real POST preflight operation self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, secret resolution, database, Checkout Session, or payment occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
