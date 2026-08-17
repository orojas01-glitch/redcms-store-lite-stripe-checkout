<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory
    . '/src/StripeSandboxContactReadinessPlanner.php';
require_once $projectDirectory
    . '/src/StripeSandboxReadOnlyProbeTransport.php';
require_once $projectDirectory
    . '/src/StripeSandboxReadOnlyProbeOutcomeGate.php';

$assertions = 0;

function red_stripe_p3e8b1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e8b1_readiness(): array
{
    return RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
        [
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'packageVersion' => '0.1.1',
            'packageArtifactSha256' => str_repeat('a', 64),
            'runtimeProviderTransport' => 'disabled',
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

function red_stripe_p3e8b1_evidence(int $statusCode = 404): array
{
    return [
        'operation' => 'stripe.sandbox.read-only-resource-miss-probe',
        'method' => 'GET',
        'targetMatched' => true,
        'statusCode' => $statusCode,
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
    ];
}

try {
    $transportPath = $projectDirectory
        . '/src/StripeSandboxReadOnlyProbeTransport.php';
    $outcomePath = $projectDirectory
        . '/src/StripeSandboxReadOnlyProbeOutcomeGate.php';
    $transportSource = (string) file_get_contents($transportPath);
    $outcomeSource = (string) file_get_contents($outcomePath);
    $packageSource = '';
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $projectDirectory . '/package',
            FilesystemIterator::SKIP_DOTS
        )
    ) as $packageFile) {
        if ($packageFile->isFile()) {
            $packageSource .= (string) file_get_contents(
                $packageFile->getPathname()
            );
        }
    }

    red_stripe_p3e8b1_assert(
        (new ReflectionClass(
            RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport::class
        ))->isFinal()
            && (new ReflectionClass(
                RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::class
            ))->isFinal(),
        'transport and outcome contracts cannot be extended'
    );
    red_stripe_p3e8b1_assert(
        !str_contains($packageSource, 'Read_Only_Probe_Transport')
            && !str_contains($packageSource, 'Read_Only_Probe_Outcome_Gate'),
        'installable package does not load or reference provider-contact source'
    );
    foreach ([
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'error_log(', 'print_r(', 'var_dump(', 'shell_exec(',
        'system(', 'passthru(', 'PDO', 'mysqli', 'sleep(', 'usleep(',
    ] as $forbiddenToken) {
        red_stripe_p3e8b1_assert(
            !str_contains($transportSource . $outcomeSource, $forbiddenToken),
            $forbiddenToken . ' is absent from provider transport contracts'
        );
    }
    foreach ([
        'sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'pk_test_',
        'pk_live_', 'whsec_',
    ] as $credentialToken) {
        red_stripe_p3e8b1_assert(
            !str_contains($transportSource . $outcomeSource, $credentialToken),
            $credentialToken . ' credential-shaped literal is absent'
        );
    }
    foreach ([
        'CURLOPT_HTTPGET', 'CURLAUTH_BASIC', 'CURLOPT_USERPWD',
        'CURLOPT_PROTOCOLS', 'CURLPROTO_HTTPS',
        'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST',
        'CURL_SSLVERSION_TLSv1_2', 'CURLOPT_PROXY', 'CURLOPT_NOPROXY',
        'CURLOPT_FOLLOWLOCATION', 'CURLOPT_MAXREDIRS',
        'CURLOPT_CONNECTTIMEOUT_MS', 'CURLOPT_TIMEOUT_MS',
        'CURLOPT_FRESH_CONNECT', 'CURLOPT_FORBID_REUSE',
        'CURLOPT_WRITEFUNCTION', 'CURLOPT_HEADERFUNCTION',
    ] as $requiredToken) {
        red_stripe_p3e8b1_assert(
            str_contains($transportSource, $requiredToken),
            $requiredToken . ' is fixed in provider-capable source'
        );
    }
    red_stripe_p3e8b1_assert(
        !str_contains($transportSource, 'CURLOPT_POST')
            && !str_contains($transportSource, 'CURLOPT_CUSTOMREQUEST')
            && !str_contains($transportSource, 'CURLOPT_POSTFIELDS'),
        'provider transport contains no mutation-capable request option'
    );

    $readiness = red_stripe_p3e8b1_readiness();
    red_stripe_p3e8b1_assert(
        $readiness['ready'] === true
            && $readiness['executionPerformed'] === false,
        'unchanged P3E-6 planner emits the exact non-executing plan'
    );
    $method = new ReflectionMethod(
        RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport::class,
        'contactPlan'
    );
    red_stripe_p3e8b1_assert(
        $method->invoke(null, $readiness['contactPlan']) === true,
        'transport accepts only the exact reviewed P3E-6 contact plan'
    );
    foreach ([
        ['method', 'POST'],
        ['url', 'https://example.com/'],
        ['packageVersion', '0.1.2'],
        ['runtimeProviderTransport', 'enabled'],
        ['maximumAttempts', 2],
        ['retryAuthorized', true],
        ['mutationAuthorized', true],
        ['executionPerformed', true],
    ] as [$field, $value]) {
        $changed = $readiness['contactPlan'];
        $changed[$field] = $value;
        red_stripe_p3e8b1_assert(
            $method->invoke(null, $changed) === false,
            'transport refuses changed plan field ' . $field
        );
    }
    $expanded = $readiness['contactPlan'];
    $expanded['extra'] = true;
    red_stripe_p3e8b1_assert(
        $method->invoke(null, $expanded) === false,
        'transport refuses plan expansion'
    );

    $syntheticKey = 'rk_' . 'test_' . str_repeat('x', 32);
    $transport =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport(
            $syntheticKey
        );
    red_stripe_p3e8b1_assert(
        $transport->calls() === 0,
        'constructing transport performs no exchange'
    );
    unset($transport, $syntheticKey);
    foreach ([
        '',
        'sk_' . 'test_' . str_repeat('x', 32),
        'rk_' . 'live_' . str_repeat('x', 32),
        'rk_' . 'test_short',
        'rk_' . 'test_' . str_repeat('x', 32) . "\n",
    ] as $refusedCredential) {
        try {
            new RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Transport(
                $refusedCredential
            );
            $refused = false;
        } catch (InvalidArgumentException $throwable) {
            $refused = $throwable->getMessage()
                === 'restricted_test_credential_refused';
        }
        red_stripe_p3e8b1_assert(
            $refused,
            'constructor refuses non-restricted-test credential shape'
        );
    }

    $expectedOutcomes = [
        404 => 'resource_miss_observed',
        401 => 'credential_refused',
        403 => 'permission_refused',
        429 => 'rate_limited',
        500 => 'provider_unavailable',
        599 => 'provider_unavailable',
        200 => 'unexpected_success_status',
        400 => 'unexpected_provider_status',
    ];
    foreach ($expectedOutcomes as $statusCode => $expectedOutcome) {
        $evidence = red_stripe_p3e8b1_evidence($statusCode);
        $projected =
            RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project(
                $evidence
            );
        red_stripe_p3e8b1_assert(
            $projected['valid'] === true
                && $projected['outcome'] === $expectedOutcome
                && $projected['statusCode'] === $statusCode
                && $projected['expectedEffectObserved']
                    === ($statusCode === 404)
                && $projected['responseBodyIncluded'] === false
                && $projected['responseHeadersIncluded'] === false
                && $projected['credentialIncluded'] === false
                && $projected['retryAuthorized'] === false
                && $projected['mutationAuthorized'] === false
                && $projected['executionPerformed'] === true
                && $projected['errors'] === [],
            'status ' . $statusCode . ' maps to bounded outcome '
                . $expectedOutcome
        );
        $encoded = json_encode(
            $evidence,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
        red_stripe_p3e8b1_assert(
            hash('sha256', $encoded)
                === $projected['transportEvidenceSha256'],
            'outcome binds exact synthetic transport evidence bytes'
        );
    }
    $evidence = red_stripe_p3e8b1_evidence();
    $projection =
        RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project(
            $evidence
        );
    red_stripe_p3e8b1_assert(
        $projection ===
            RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project(
                $evidence
            ),
        'outcome projection is deterministic'
    );
    foreach ([
        ['targetMatched', false],
        ['redirectCount', 1],
        ['responseBytes', 65537],
        ['headerBytes', 16385],
        ['responseBodyIncluded', true],
        ['responseHeadersIncluded', true],
        ['credentialIncluded', true],
        ['minimumTlsVersion', '1.1'],
        ['peerVerificationRequired', false],
        ['hostVerificationRequired', false],
        ['proxyDisabled', false],
        ['executionPerformed', false],
    ] as [$field, $value]) {
        $changed = $evidence;
        $changed[$field] = $value;
        $refused =
            RED_CMS_Store_Lite_Stripe_Sandbox_Read_Only_Probe_Outcome_Gate::project(
                $changed
            );
        red_stripe_p3e8b1_assert(
            $refused['valid'] === false
                && $refused['outcome'] === 'indeterminate'
                && $refused['statusCode'] === null
                && $refused['transportEvidenceSha256'] === ''
                && $refused['executionPerformed'] === null
                && $refused['retryAuthorized'] === false
                && $refused['mutationAuthorized'] === false
                && $refused['errors'] === ['transport_evidence_refused'],
            'outcome gate refuses changed field ' . $field
        );
    }

    $packageHashes = [
        'StripeTypedOfflineCheckoutAdapter.php' =>
            '8418682d9fcad1a7e1a1624234d76fe948a1fbbd866c228954250306b694042b',
        'addon.json' =>
            '7e0c49b43db10ac3ae475d6b94157f5bd4cc5fe34fad9248f5c13ef4bcb07e46',
        'addon.php' =>
            '121667a1e771f1272cd14733fda7352a3227890518862eebc59b713ec75f2c2e',
        'identity.json' =>
            'c84660b6437b8926de3b32635b5083b47fa50070c38bc0a37813bbcd7e1a46e7',
        'migrations/2026-08-16-create-checkout-attempts.sql' =>
            'f58ae3b56d5b96d80f2757162e41e0fa4540f5e652934b9708e3884be633c2fa',
        'migrations/2026-08-16-create-event-receipts.sql' =>
            '20b516693d15bf2fb3829de6d9c9fe44202af03b846a05262d0c79f2b0cd2b8d',
    ];
    foreach ($packageHashes as $relativePath => $expectedSha256) {
        red_stripe_p3e8b1_assert(
            hash_file(
                'sha256',
                $projectDirectory . '/package/' . $relativePath
            ) === $expectedSha256,
            'installable package file remains byte-identical: '
                . $relativePath
        );
    }

    echo 'P3E-8B1 provider transport contract self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, credential resolution, or package execution occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
