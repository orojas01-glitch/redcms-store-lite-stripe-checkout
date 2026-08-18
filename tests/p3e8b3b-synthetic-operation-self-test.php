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
require_once $projectDirectory
    . '/src/StripeSandboxContactReadinessPlanner.php';
require_once $projectDirectory
    . '/package/StripeSandboxReadOnlyProbeOutcomeGate.php';
require_once $projectDirectory
    . '/package/StripeSandboxReadOnlyProbeSyntheticExecutor.php';
require_once $projectDirectory
    . '/package/StripeTypedOfflineCheckoutAdapter.php';

$assertions = 0;

function red_stripe_p3e8b3b_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e8b3b_readiness(): array
{
    return RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
        [
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.3',
            'packageArtifactSha256' => str_repeat('a', 64),
            'runtimeProviderTransport' => 'synthetic_only',
        ],
        [
            'settingKey' => 'stripe.secret-key',
            'keyMode' => 'restricted_test',
            'source' => 'process_environment',
            'available' => true,
            'valueIncluded' => false,
            'valueSha256Included' => false,
            'repositoryScan' => 'clean',
            'configurationScan' => 'clean',
            'logScan' => 'clean',
            'leastPrivilegeReview' => 'checkout_sessions_read_only',
            'rotationRunbook' => 'ready',
            'revocationRunbook' => 'ready',
            'evidenceSha256' => str_repeat('b', 64),
        ],
        [
            'providerHost' => 'api.stripe.com',
            'providerPort' => 443,
            'method' => 'GET',
            'path' =>
                '/v1/checkout/sessions/cs_test_redcms_readiness_probe',
            'dnsRequired' => true,
            'httpsOnly' => true,
            'minimumTlsVersion' => '1.2',
            'verifyPeer' => true,
            'verifyHost' => true,
            'proxyMode' => 'disabled',
            'followRedirects' => false,
            'maximumRedirects' => 0,
            'connectTimeoutMilliseconds' => 5000,
            'totalTimeoutMilliseconds' => 15000,
            'maximumResponseBytes' => 65536,
        ]
    );
}

try {
    $executorSource = (string) file_get_contents(
        $projectDirectory
            . '/package/StripeSandboxReadOnlyProbeSyntheticExecutor.php'
    );
    $handlerSource = (string) file_get_contents(
        $projectDirectory . '/package/StripeTypedOfflineCheckoutAdapter.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'PDO', 'mysqli', 'shell_exec(', 'system(', 'passthru(',
        'sleep(', 'usleep(', 'error_log(', 'print_r(', 'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e8b3b_assert(
            !str_contains($executorSource . $handlerSource, $forbiddenToken),
            $forbiddenToken . ' is absent from synthetic operation source'
        );
    }
    foreach ([
        'sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_',
    ] as $credentialLiteral) {
        red_stripe_p3e8b3b_assert(
            !str_contains($executorSource . $handlerSource, $credentialLiteral),
            $credentialLiteral . ' literal is absent'
        );
    }
    red_stripe_p3e8b3b_assert(
        !str_contains(
            $handlerSource,
            'new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport'
        ) && !str_contains($executorSource, 'Read_Only_Probe_Transport'),
        'synthetic path cannot construct or reference provider transport'
    );
    red_stripe_p3e8b3b_assert(
        str_contains(
            $handlerSource,
            'provider-contact.read-only-probe-synthetic'
        ) && str_contains($handlerSource, "'stripe.secret-key'")
            && str_contains($handlerSource, "'stripe.webhook-secret'"),
        'handler exposes one exact operation and enforces scoped secret access'
    );

    $readiness = red_stripe_p3e8b3b_readiness();
    red_stripe_p3e8b3b_assert(
        ($readiness['ready'] ?? false) === true
            && ($readiness['executionPerformed'] ?? null) === false
            && ($readiness['contactPlan']['packageVersion'] ?? null)
                === '0.1.3'
            && ($readiness['contactPlan']['runtimeProviderTransport'] ?? null)
                === 'synthetic_only',
        'planner produces only the exact 0.1.3 synthetic readiness plan'
    );
    $plan = $readiness['contactPlan'];
    $executor =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Synthetic_Executor();
    $syntheticKey = 'rk_' . 'test_' . str_repeat('x', 32);
    $outcome = $executor->execute($plan, $syntheticKey);
    red_stripe_p3e8b3b_assert(
        $executor->calls() === 1
            && ($outcome['valid'] ?? false) === true
            && ($outcome['contactTarget'] ?? null) === 'synthetic-package'
            && ($outcome['outcome'] ?? null) === 'resource_miss_observed'
            && ($outcome['statusCode'] ?? null) === 404
            && ($outcome['responseBytes'] ?? null) === 0
            && ($outcome['networkAccess'] ?? null) === false
            && ($outcome['providerContact'] ?? null) === false
            && ($outcome['retryAuthorized'] ?? null) === false
            && ($outcome['mutationAuthorized'] ?? null) === false
            && ($outcome['executionPerformed'] ?? null) === true,
        'one synthetic execution returns only the closed non-network outcome'
    );
    red_stripe_p3e8b3b_assert(
        !str_contains(serialize($executor), $syntheticKey)
            && !str_contains(json_encode($outcome), $syntheticKey),
        'credential bytes are absent from executor state and bounded outcome'
    );
    try {
        $executor->execute($plan, $syntheticKey);
        $repeatRefused = false;
    } catch (RuntimeException $exception) {
        $repeatRefused = $exception->getMessage()
            === 'synthetic_probe_refused';
    }
    red_stripe_p3e8b3b_assert(
        $repeatRefused && $executor->calls() === 2,
        'synthetic executor is permanently one-use'
    );
    foreach ([
        ['packageVersion', '0.1.2'],
        ['runtimeProviderTransport', 'enabled'],
        ['method', 'POST'],
        ['maximumAttempts', 2],
        ['retryAuthorized', true],
        ['mutationAuthorized', true],
    ] as [$field, $value]) {
        $changed = $plan;
        $changed[$field] = $value;
        $case =
            new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Synthetic_Executor();
        try {
            $case->execute($changed, $syntheticKey);
            $refused = false;
        } catch (RuntimeException $exception) {
            $refused = $exception->getMessage() === 'synthetic_probe_refused';
        }
        red_stripe_p3e8b3b_assert(
            $refused && $case->calls() === 1,
            'synthetic executor refuses changed plan field ' . $field
        );
    }

    $input = [
        'contactTarget' => 'synthetic-package',
        'contactPlan' => $plan,
        'planSha256' => $readiness['planSha256'],
        'claimStateSha256' => str_repeat('c', 64),
        'executionStartStateSha256' => str_repeat('d', 64),
    ];
    $scopedAccess = new RED_Addon_Runtime_Secret_Access(
        'redcms.store-lite-stripe-checkout',
        ['stripe.secret-key' => $syntheticKey]
    );
    $request = new RED_Addon_Adapter_Request(
        'redcms.store-lite-stripe-checkout/checkout',
        'provider-contact.read-only-probe-synthetic',
        $input,
        $scopedAccess
    );
    $result =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            $request
        );
    red_stripe_p3e8b3b_assert(
        $result->successState()
            && $result->error() === ''
            && $result->data()['contactTarget'] === 'synthetic-package'
            && $result->data()['networkAccess'] === false
            && $result->data()['providerContact'] === false
            && !str_contains(json_encode($result->data()), $syntheticKey),
        'typed handler succeeds only with the scoped key and bounded result'
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
                'provider-contact.read-only-probe-synthetic',
                $input,
                $unscopedAccess
            )
        );
    red_stripe_p3e8b3b_assert(
        !$unscoped->successState()
            && $unscoped->error() === 'synthetic_probe_secret_refused',
        'normal unscoped runtime secret access fails closed'
    );
    $changedInput = $input;
    $changedInput['contactTarget'] = 'stripe-sandbox';
    $changed =
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle(
            new RED_Addon_Adapter_Request(
                'redcms.store-lite-stripe-checkout/checkout',
                'provider-contact.read-only-probe-synthetic',
                $changedInput,
                $scopedAccess
            )
        );
    red_stripe_p3e8b3b_assert(
        !$changed->successState()
            && $changed->error() === 'synthetic_probe_input_refused',
        'changed target is refused before synthetic execution'
    );

    echo 'P3E-8B3B synthetic operation self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, provider transport, or database access occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
