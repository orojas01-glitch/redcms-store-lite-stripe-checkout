<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
$packageDirectory = $projectDirectory . '/package';
require_once $projectDirectory
    . '/src/StripeSandboxContactReadinessPlanner.php';
require_once $packageDirectory
    . '/StripeSandboxReadOnlyProbeTransport.php';
require_once $packageDirectory
    . '/StripeSandboxReadOnlyProbeOutcomeGate.php';

$assertions = 0;

function red_stripe_p3e8b3a_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e8b3a_readiness(): array
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
    $manifest = json_decode(
        (string) file_get_contents($packageDirectory . '/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $identity = json_decode(
        (string) file_get_contents($packageDirectory . '/identity.json'),
        true,
        16,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3e8b3a_assert(
        ($manifest['id'] ?? null)
                === 'redcms.store-lite-stripe-checkout'
            && ($manifest['version'] ?? null) === '0.1.8'
            && ($manifest['type'] ?? null) === 'adapter'
            && ($identity['status'] ?? null)
                === 'p3e9d4a_provider_write_operation_uninvoked'
            && ($identity['futureManifest']['version'] ?? null) === '0.1.8',
        'later identity preserves B3A transport adoption in 0.1.8'
    );
    red_stripe_p3e8b3a_assert(
        ($manifest['outboundHosts'] ?? null) === ['api.stripe.com']
            && ($manifest['permissions'] ?? null) === []
            && ($manifest['publicMutationContracts'] ?? null) === []
            && ($manifest['jobs'] ?? null) === [],
        'package declares one host but no permission, mutation, or job'
    );
    red_stripe_p3e8b3a_assert(
        count($manifest['migrations'] ?? []) === 2
            && ($manifest['migrations'][0]['sha256'] ?? '')
                === 'f58ae3b56d5b96d80f2757162e41e0fa4540f5e652934b9708e3884be633c2fa'
            && ($manifest['migrations'][1]['sha256'] ?? '')
                === '20b516693d15bf2fb3829de6d9c9fe44202af03b846a05262d0c79f2b0cd2b8d',
        'later 0.1.8 preserves both append-only migration checksums'
    );

    $inventory = $manifest['integrity']['files'] ?? [];
    red_stripe_p3e8b3a_assert(
        count($inventory) === 19
            && array_column($inventory, 'path') === [
                'addon.php',
                'StripeTypedOfflineCheckoutAdapter.php',
                'StripeSandboxReadOnlyProbeTransport.php',
                'StripeSandboxReadOnlyProbeOutcomeGate.php',
                'StripeSandboxReadOnlyProbeSyntheticExecutor.php',
                'StripeCheckoutResponseNormalizer.php',
                'StripeBoundedJsonDecoder.php',
                'StripeSandboxCheckoutTransportPlanner.php',
                'StripeSandboxCheckoutTransportResponseGate.php',
                'StripeSandboxCheckoutWireCodec.php',
                'StripeSandboxCheckoutCreationContract.php',
                'StripeSandboxCheckoutCreationSyntheticExecutor.php',
                'StripeSandboxCheckoutRealPostPreflight.php',
                'StripeSandboxCheckoutRealPostExchange.php',
                'StripeSandboxCheckoutRealPostTransport.php',
                'StripeSandboxCheckoutRealPostOperation.php',
                'identity.json',
                'migrations/2026-08-16-create-checkout-attempts.sql',
                'migrations/2026-08-16-create-event-receipts.sql',
            ],
        'integrity inventory lists the exact nineteen payload files'
    );
    foreach ($inventory as $file) {
        $path = $packageDirectory . '/' . ($file['path'] ?? '');
        red_stripe_p3e8b3a_assert(
            is_file($path)
                && hash_equals(
                    (string) ($file['sha256'] ?? ''),
                    hash_file('sha256', $path)
                ),
            ($file['path'] ?? 'unknown') . ' matches its integrity hash'
        );
    }
    red_stripe_p3e8b3a_assert(
        hash_file(
            'sha256',
            $projectDirectory . '/src/StripeSandboxReadOnlyProbeTransport.php'
        ) === hash_file(
            'sha256',
            $packageDirectory . '/StripeSandboxReadOnlyProbeTransport.php'
        ) && hash_file(
            'sha256',
            $projectDirectory . '/src/StripeSandboxReadOnlyProbeOutcomeGate.php'
        ) === hash_file(
            'sha256',
            $packageDirectory . '/StripeSandboxReadOnlyProbeOutcomeGate.php'
        ),
        'package adopts byte-identical reviewed transport and outcome classes'
    );

    $entrypoint = (string) file_get_contents($packageDirectory . '/addon.php');
    $handler = (string) file_get_contents(
        $packageDirectory . '/StripeTypedOfflineCheckoutAdapter.php'
    );
    red_stripe_p3e8b3a_assert(
        str_contains($entrypoint, 'StripeSandboxReadOnlyProbeTransport.php')
            && str_contains(
                $entrypoint,
                'StripeSandboxReadOnlyProbeOutcomeGate.php'
            )
            && str_contains(
                $handler,
                'provider-contact.read-only-probe-sandbox'
            )
            && str_contains(
                $handler,
                'provider-contact.read-only-probe-synthetic'
            )
            && str_contains($handler, 'provider_transport_disabled'),
        'later handler names exact synthetic and provider operations'
    );
    foreach ([
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'PDO', 'mysqli', 'shell_exec(', 'system(', 'passthru(',
        'error_log(', 'print_r(', 'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e8b3a_assert(
            !str_contains($handler, $forbiddenToken),
            $forbiddenToken . ' is absent from the typed handler'
        );
    }
    foreach ([
        'sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_',
    ] as $credentialLiteral) {
        red_stripe_p3e8b3a_assert(
            !str_contains($handler, $credentialLiteral),
            $credentialLiteral . ' literal is absent from the typed handler'
        );
    }
    red_stripe_p3e8b3a_assert(
        substr_count($handler, "'stripe.secret-key'") === 5
            && substr_count($handler, "'stripe.webhook-secret'") === 5,
        'all five secret-aware operations use only declared keys'
    );

    $readiness = red_stripe_p3e8b3a_readiness();
    red_stripe_p3e8b3a_assert(
        ($readiness['ready'] ?? false) === true
            && ($readiness['executionPerformed'] ?? null) === false
            && ($readiness['contactPlan']['packageVersion'] ?? null)
                === '0.1.3'
            && ($readiness['contactPlan']['runtimeProviderTransport'] ?? null)
                === 'synthetic_only',
        'later readiness is synthetic-only and remains non-contact'
    );
    $contactPlanMethod = new ReflectionMethod(
        RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport::class,
        'contactPlan'
    );
    red_stripe_p3e8b3a_assert(
        $contactPlanMethod->invoke(null, $readiness['contactPlan']) === false,
        'adopted provider transport refuses the synthetic-only plan'
    );
    $transport =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport(
            'rk_' . 'test_' . str_repeat('x', 32)
        );
    red_stripe_p3e8b3a_assert(
        $transport->calls() === 0,
        'constructing the package transport performs no provider exchange'
    );
    unset($transport);

    $outcome =
        RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project([
            'operation' => 'stripe.sandbox.read-only-resource-miss-probe',
            'method' => 'GET',
            'targetMatched' => true,
            'statusCode' => 404,
            'redirectCount' => 0,
            'responseBytes' => 227,
            'headerBytes' => 512,
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'minimumTlsVersion' => '1.2',
            'peerVerificationRequired' => true,
            'hostVerificationRequired' => true,
            'proxyDisabled' => true,
            'executionPerformed' => true,
        ]);
    red_stripe_p3e8b3a_assert(
        ($outcome['valid'] ?? false) === true
            && ($outcome['outcome'] ?? null) === 'resource_miss_observed'
            && ($outcome['retryAuthorized'] ?? null) === false
            && ($outcome['mutationAuthorized'] ?? null) === false
            && ($outcome['responseBodyIncluded'] ?? null) === false
            && ($outcome['responseHeadersIncluded'] ?? null) === false
            && ($outcome['credentialIncluded'] ?? null) === false,
        'synthetic evidence still projects to the closed bounded outcome'
    );

    echo 'P3E-8B3A package adoption self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, secret resolution, or handler invocation occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
