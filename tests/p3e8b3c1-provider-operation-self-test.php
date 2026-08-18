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
require_once $packageDirectory
    . '/StripeSandboxReadOnlyProbeSyntheticExecutor.php';
require_once $packageDirectory . '/StripeTypedOfflineCheckoutAdapter.php';

$assertions = 0;

function red_stripe_p3e8b3c1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e8b3c1_package(
    string $version = '0.1.4',
    string $mode = 'provider_read_only'
): array {
    return [
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => $version,
        'packageArtifactSha256' => str_repeat('a', 64),
        'runtimeProviderTransport' => $mode,
    ];
}

function red_stripe_p3e8b3c1_credential(): array
{
    return [
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
    ];
}

function red_stripe_p3e8b3c1_network(): array
{
    return [
        'providerHost' => 'api.stripe.com',
        'providerPort' => 443,
        'method' => 'GET',
        'path' => '/v1/checkout/sessions/cs_test_redcms_readiness_probe',
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
    ];
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
    red_stripe_p3e8b3c1_assert(
        ($manifest['version'] ?? null) === '0.1.5'
            && ($identity['status'] ?? null)
                === 'p3e9b1_synthetic_checkout_operation_available'
            && ($identity['futureManifest']['version'] ?? null) === '0.1.5',
        'later package preserves the exact read-only provider operation'
    );
    red_stripe_p3e8b3c1_assert(
        ($manifest['outboundHosts'] ?? null) === ['api.stripe.com']
            && ($manifest['permissions'] ?? null) === []
            && ($manifest['publicMutationContracts'] ?? null) === []
            && ($manifest['jobs'] ?? null) === [],
        'package retains one host and no permission, mutation, or job'
    );
    red_stripe_p3e8b3c1_assert(
        count($manifest['migrations'] ?? []) === 2
            && ($manifest['migrations'][0]['sha256'] ?? '')
                === 'f58ae3b56d5b96d80f2757162e41e0fa4540f5e652934b9708e3884be633c2fa'
            && ($manifest['migrations'][1]['sha256'] ?? '')
                === '20b516693d15bf2fb3829de6d9c9fe44202af03b846a05262d0c79f2b0cd2b8d',
        'provider operation changes no migration path or checksum'
    );
    foreach ($manifest['integrity']['files'] ?? [] as $file) {
        $path = $packageDirectory . '/' . ($file['path'] ?? '');
        red_stripe_p3e8b3c1_assert(
            is_file($path)
                && hash_equals(
                    (string) ($file['sha256'] ?? ''),
                    hash_file('sha256', $path)
                ),
            ($file['path'] ?? 'unknown') . ' matches package integrity'
        );
    }
    red_stripe_p3e8b3c1_assert(
        count($manifest['integrity']['files'] ?? []) === 14,
        'later synthetic Checkout adoption has exact current package inventory'
    );
    red_stripe_p3e8b3c1_assert(
        hash_file(
            'sha256',
            $projectDirectory . '/src/StripeSandboxReadOnlyProbeTransport.php'
        ) === hash_file(
            'sha256',
            $packageDirectory . '/StripeSandboxReadOnlyProbeTransport.php'
        ),
        'package transport remains byte-identical to reviewed source'
    );

    $readiness = RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
        red_stripe_p3e8b3c1_package(),
        red_stripe_p3e8b3c1_credential(),
        red_stripe_p3e8b3c1_network()
    );
    red_stripe_p3e8b3c1_assert(
        ($readiness['ready'] ?? false) === true
            && ($readiness['contactPlan']['packageVersion'] ?? null)
                === '0.1.4'
            && ($readiness['contactPlan']['runtimeProviderTransport'] ?? null)
                === 'provider_read_only'
            && ($readiness['executionPerformed'] ?? null) === false,
        'planner emits exact value-free provider-read-only readiness'
    );
    $synthetic = RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
        red_stripe_p3e8b3c1_package('0.1.3', 'synthetic_only'),
        red_stripe_p3e8b3c1_credential(),
        red_stripe_p3e8b3c1_network()
    );
    red_stripe_p3e8b3c1_assert(
        ($synthetic['ready'] ?? false) === true
            && ($synthetic['executionPerformed'] ?? null) === false,
        'planner preserves the separately exact synthetic-only profile'
    );
    foreach ([
        ['0.1.4', 'synthetic_only'],
        ['0.1.3', 'provider_read_only'],
        ['0.1.2', 'disabled'],
        ['0.1.4', 'enabled'],
    ] as [$version, $mode]) {
        $refused = RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
            red_stripe_p3e8b3c1_package($version, $mode),
            red_stripe_p3e8b3c1_credential(),
            red_stripe_p3e8b3c1_network()
        );
        red_stripe_p3e8b3c1_assert(
            ($refused['ready'] ?? true) === false,
            'planner refuses mismatched profile ' . $version . '/' . $mode
        );
    }

    $transportMethod = new ReflectionMethod(
        RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport::class,
        'contactPlan'
    );
    red_stripe_p3e8b3c1_assert(
        $transportMethod->invoke(null, $readiness['contactPlan']) === true
            && $transportMethod->invoke(null, $synthetic['contactPlan'])
                === false,
        'provider transport accepts only the 0.1.4 read-only profile'
    );
    $input = [
        'contactTarget' => 'stripe-sandbox',
        'contactPlan' => $readiness['contactPlan'],
        'planSha256' => $readiness['planSha256'],
        'claimStateSha256' => str_repeat('c', 64),
        'executionStartStateSha256' => str_repeat('d', 64),
    ];
    $providerInput = new ReflectionMethod(
        RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::class,
        'providerInput'
    );
    red_stripe_p3e8b3c1_assert(
        $providerInput->invoke(null, $input) === true,
        'typed handler accepts only the exact hash-bound provider input'
    );
    foreach ([
        ['contactTarget', 'synthetic-package'],
        ['planSha256', str_repeat('e', 64)],
    ] as [$field, $value]) {
        $changed = $input;
        $changed[$field] = $value;
        red_stripe_p3e8b3c1_assert(
            $providerInput->invoke(null, $changed) === false,
            'provider input refuses changed field ' . $field
        );
    }
    $expanded = $input;
    $expanded['extra'] = true;
    red_stripe_p3e8b3c1_assert(
        $providerInput->invoke(null, $expanded) === false,
        'provider input refuses expansion'
    );

    $handlerSource = (string) file_get_contents(
        $packageDirectory . '/StripeTypedOfflineCheckoutAdapter.php'
    );
    $transportSource = (string) file_get_contents(
        $packageDirectory . '/StripeSandboxReadOnlyProbeTransport.php'
    );
    red_stripe_p3e8b3c1_assert(
        str_contains(
            $handlerSource,
            'provider-contact.read-only-probe-sandbox'
        ) && str_contains(
            $handlerSource,
            'new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport'
        ) && str_contains($handlerSource, '->exchange('),
        'typed handler contains one explicit provider-operation path'
    );
    foreach ([
        'CURLOPT_HTTPGET', 'CURLAUTH_BASIC', 'CURLOPT_USERPWD',
        'CURLOPT_PROTOCOLS', 'CURLPROTO_HTTPS',
        'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST',
        'CURLOPT_FOLLOWLOCATION', 'CURLOPT_MAXREDIRS',
        'CURLOPT_CONNECTTIMEOUT_MS', 'CURLOPT_TIMEOUT_MS',
        'CURLOPT_FRESH_CONNECT', 'CURLOPT_FORBID_REUSE',
        'CURLOPT_WRITEFUNCTION', 'CURLOPT_HEADERFUNCTION',
    ] as $requiredToken) {
        red_stripe_p3e8b3c1_assert(
            str_contains($transportSource, $requiredToken),
            $requiredToken . ' remains fixed in provider transport'
        );
    }
    red_stripe_p3e8b3c1_assert(
        !str_contains($transportSource, 'CURLOPT_POST')
            && !str_contains($transportSource, 'CURLOPT_CUSTOMREQUEST')
            && !str_contains($transportSource, 'CURLOPT_POSTFIELDS'),
        'provider transport contains no mutation-capable request option'
    );
    foreach ([
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'PDO', 'mysqli', 'shell_exec(', 'system(', 'passthru(',
        'error_log(', 'print_r(', 'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e8b3c1_assert(
            !str_contains($handlerSource . $transportSource, $forbiddenToken),
            $forbiddenToken . ' is absent from provider operation source'
        );
    }
    foreach ([
        'sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_',
    ] as $credentialLiteral) {
        red_stripe_p3e8b3c1_assert(
            !str_contains($handlerSource . $transportSource, $credentialLiteral),
            $credentialLiteral . ' literal is absent'
        );
    }

    $transport =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport(
            'rk_' . 'test_' . str_repeat('x', 32)
        );
    red_stripe_p3e8b3c1_assert(
        $transport->calls() === 0,
        'constructing provider transport performs no exchange'
    );
    unset($transport);

    echo 'P3E-8B3C1 provider operation self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, credential resolution, or handler invocation occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
