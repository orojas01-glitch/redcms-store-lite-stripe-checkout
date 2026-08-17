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
    . '/src/StripeSandboxContactAuthorizationGate.php';

$assertions = 0;

function red_stripe_p3e6_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e6_package(): array
{
    return [
        'packageId' => 'redcms.store-lite.stripe-checkout',
        'packageVersion' => '0.1.1',
        'packageArtifactSha256' => str_repeat('a', 64),
        'runtimeProviderTransport' => 'disabled',
    ];
}

function red_stripe_p3e6_credential(): array
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

function red_stripe_p3e6_network(): array
{
    return [
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
    ];
}

function red_stripe_p3e6_confirmation(string $planSha256): array
{
    return [
        'action' => 'authorize-stripe-sandbox-read-only-probe',
        'planSha256' => $planSha256,
        'operatorSubjectSha256' => str_repeat('c', 64),
        'authorizationNonceSha256' => str_repeat('d', 64),
        'issuedAtUtc' => '2026-08-17T12:00:00Z',
        'expiresAtUtc' => '2026-08-17T12:15:00Z',
        'confirmedRestrictedTestKey' => true,
        'confirmedReadOnlyGet' => true,
        'confirmedSingleAttempt' => true,
        'confirmedNoRetry' => true,
        'confirmedNoMutation' => true,
        'confirmedNoCheckoutCreation' => true,
        'confirmedNoPayment' => true,
        'confirmedNoWebhook' => true,
        'confirmedNoLiveMode' => true,
        'confirmedNoClientDeployment' => true,
        'credentialValueIncluded' => false,
    ];
}

try {
    $source = (string) file_get_contents(
        $projectDirectory . '/src/StripeSandboxContactReadinessPlanner.php'
    ) . (string) file_get_contents(
        $projectDirectory . '/src/StripeSandboxContactAuthorizationGate.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_',
        'socket_', 'PDO', 'mysqli', '$_SERVER', '$_POST', 'getenv(',
        'putenv(', 'shell_exec(', 'usleep(', 'sleep(', 'error_log(',
        'print_r(', 'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e6_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from non-contact source'
        );
    }
    foreach ([
        'sk_test_', 'rk_test_', 'pk_test_', 'sk_live_', 'rk_live_',
        'pk_live_', 'Authorization:', 'CURLOPT_', 'api.stripe.com:443',
    ] as $forbiddenValue) {
        red_stripe_p3e6_assert(
            !str_contains($source, $forbiddenValue),
            $forbiddenValue . ' value or executable transport is absent'
        );
    }
    red_stripe_p3e6_assert(
        count(get_included_files()) === 3,
        'fixture loads only itself and two dependency-free contracts'
    );
    red_stripe_p3e6_assert(
        (new ReflectionClass(
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::class
        ))->isFinal()
            && (new ReflectionClass(
                RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::class
            ))->isFinal(),
        'readiness and authorization contracts cannot be extended'
    );

    $package = red_stripe_p3e6_package();
    $credential = red_stripe_p3e6_credential();
    $network = red_stripe_p3e6_network();
    $inputsBefore = serialize([$package, $credential, $network]);
    $readiness =
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
            $package,
            $credential,
            $network
        );
    red_stripe_p3e6_assert(
        $readiness['ready'] === true
            && is_array($readiness['contactPlan'])
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $readiness['planSha256']
            ) === 1
            && $readiness['executionPerformed'] === false
            && $readiness['errors'] === [],
        'exact value-free evidence produces one non-executing plan'
    );
    $plan = $readiness['contactPlan'];
    red_stripe_p3e6_assert(
        $plan['operation']
            === 'stripe.sandbox.read-only-resource-miss-probe'
            && $plan['method'] === 'GET'
            && $plan['url']
                === 'https://api.stripe.com/v1/checkout/sessions/'
                    . 'cs_test_redcms_readiness_probe'
            && $plan['expectedEffect'] === 'read-only-resource-miss'
            && $plan['responseBodyProjection'] === 'none',
        'plan fixes the read-only sandbox resource-miss probe'
    );
    red_stripe_p3e6_assert(
        $plan['packageId'] === 'redcms.store-lite.stripe-checkout'
            && $plan['packageVersion'] === '0.1.1'
            && $plan['packageArtifactSha256'] === str_repeat('a', 64)
            && $plan['runtimeProviderTransport'] === 'disabled',
        'plan binds exact package identity while runtime stays disabled'
    );
    red_stripe_p3e6_assert(
        $plan['credentialSettingKey'] === 'stripe.secret-key'
            && $plan['credentialMode'] === 'restricted_test'
            && $plan['credentialSource'] === 'process_environment'
            && $plan['credentialValueIncluded'] === false
            && $plan['credentialValueSha256Included'] === false
            && $plan['credentialEvidenceSha256'] === str_repeat('b', 64),
        'plan retains only value-free restricted-key readiness evidence'
    );
    red_stripe_p3e6_assert(
        $plan['minimumTlsVersion'] === '1.2'
            && $plan['verifyPeer'] === true
            && $plan['verifyHost'] === true
            && $plan['proxyMode'] === 'disabled'
            && $plan['followRedirects'] === false
            && $plan['maximumRedirects'] === 0
            && $plan['connectTimeoutMilliseconds'] === 5000
            && $plan['totalTimeoutMilliseconds'] === 15000
            && $plan['maximumResponseBytes'] === 65536,
        'plan fixes bounded HTTPS transport facts without implementing them'
    );
    red_stripe_p3e6_assert(
        $plan['maximumAttempts'] === 1
            && $plan['oneTimeAuthorizationRequired'] === true
            && $plan['retryAuthorized'] === false
            && $plan['mutationAuthorized'] === false
            && $plan['checkoutCreationAuthorized'] === false
            && $plan['paymentAuthorized'] === false
            && $plan['webhookAuthorized'] === false
            && $plan['liveModeAuthorized'] === false
            && $plan['clientDeploymentAuthorized'] === false
            && $plan['executionPerformed'] === false,
        'all mutation, retry, live, payment, and deployment authority is false'
    );
    $encodedPlan = json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    );
    red_stripe_p3e6_assert(
        hash('sha256', $encodedPlan) === $readiness['planSha256'],
        'plan hash binds exact canonical output bytes'
    );
    $repeat =
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
            $package,
            $credential,
            $network
        );
    red_stripe_p3e6_assert(
        $repeat === $readiness
            && serialize([$package, $credential, $network]) === $inputsBefore,
        'readiness planning is deterministic and does not mutate inputs'
    );

    $packageCases = [
        ['packageId', 'wrong.package'],
        ['packageVersion', '0.1.2'],
        ['packageArtifactSha256', 'invalid'],
        ['runtimeProviderTransport', 'enabled'],
    ];
    foreach ($packageCases as [$key, $value]) {
        $casePackage = $package;
        $casePackage[$key] = $value;
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
                $casePackage,
                $credential,
                $network
            );
        red_stripe_p3e6_assert(
            $case['ready'] === false
                && $case['contactPlan'] === null
                && $case['planSha256'] === ''
                && $case['executionPerformed'] === false
                && $case['errors'] === ['readiness_evidence_refused'],
            'substituted package evidence is refused without partial plan'
        );
    }
    $packageExtra = $package;
    $packageExtra['extra'] = true;
    red_stripe_p3e6_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
            $packageExtra,
            $credential,
            $network
        )['ready'] === false,
        'expanded package evidence is refused'
    );

    $credentialCases = [
        ['settingKey', 'wrong.setting'],
        ['keyMode', 'secret_test'],
        ['source', 'file'],
        ['available', false],
        ['valueIncluded', true],
        ['valueSha256Included', true],
        ['repositoryScan', 'unknown'],
        ['configurationScan', 'unknown'],
        ['logScan', 'unknown'],
        ['leastPrivilegeReview', 'unrestricted'],
        ['rotationRunbook', 'missing'],
        ['revocationRunbook', 'missing'],
        ['evidenceSha256', 'invalid'],
    ];
    foreach ($credentialCases as [$key, $value]) {
        $caseCredential = $credential;
        $caseCredential[$key] = $value;
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
                $package,
                $caseCredential,
                $network
            );
        red_stripe_p3e6_assert(
            $case['ready'] === false
                && $case['contactPlan'] === null
                && $case['executionPerformed'] === false,
            'unsafe or incomplete credential evidence is refused'
        );
    }
    $credentialExtra = $credential;
    $credentialExtra['value'] = 'forbidden';
    red_stripe_p3e6_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
            $package,
            $credentialExtra,
            $network
        )['ready'] === false,
        'credential value field is refused rather than ignored'
    );

    $networkCases = [
        ['providerHost', 'example.test'],
        ['providerPort', 80],
        ['method', 'POST'],
        ['path', '/v1/checkout/sessions'],
        ['dnsRequired', false],
        ['httpsOnly', false],
        ['minimumTlsVersion', '1.1'],
        ['verifyPeer', false],
        ['verifyHost', false],
        ['proxyMode', 'system'],
        ['followRedirects', true],
        ['maximumRedirects', 1],
        ['connectTimeoutMilliseconds', 5001],
        ['totalTimeoutMilliseconds', 15001],
        ['maximumResponseBytes', 65537],
    ];
    foreach ($networkCases as [$key, $value]) {
        $caseNetwork = $network;
        $caseNetwork[$key] = $value;
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
                $package,
                $credential,
                $caseNetwork
            );
        red_stripe_p3e6_assert(
            $case['ready'] === false
                && $case['contactPlan'] === null
                && $case['executionPerformed'] === false,
            'expanded or weakened network evidence is refused'
        );
    }

    $confirmation = red_stripe_p3e6_confirmation(
        $readiness['planSha256']
    );
    $authorization =
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
            $readiness,
            $confirmation,
            '2026-08-17T12:07:30Z'
        );
    red_stripe_p3e6_assert(
        $authorization['prepared'] === true
            && is_array($authorization['authorization'])
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $authorization['authorizationSha256']
            ) === 1
            && $authorization['ownerAuthorityRevalidationRequired'] === true
            && $authorization['nonceConsumptionRequired'] === true
            && $authorization['contactAuthorized'] === false
            && $authorization['executionPerformed'] === false
            && $authorization['errors'] === [],
        'exact fresh confirmation produces one hash-bound envelope'
    );
    $envelope = $authorization['authorization'];
    red_stripe_p3e6_assert(
        $envelope['planSha256'] === $readiness['planSha256']
            && $envelope['operatorSubjectSha256'] === str_repeat('c', 64)
            && $envelope['authorizationNonceSha256'] === str_repeat('d', 64)
            && $envelope['issuedAtUtc'] === '2026-08-17T12:00:00Z'
            && $envelope['expiresAtUtc'] === '2026-08-17T12:15:00Z'
            && $envelope['maximumAttempts'] === 1
            && $envelope['oneTimeConsumptionRequired'] === true
            && $envelope['ownerAuthorityRevalidationRequired'] === true,
        'envelope binds plan, opaque operator, nonce, window, and one attempt'
    );
    red_stripe_p3e6_assert(
        $envelope['restrictedTestKeyRequired'] === true
            && $envelope['readOnlyGetAuthorized'] === true
            && $envelope['retryAuthorized'] === false
            && $envelope['mutationAuthorized'] === false
            && $envelope['checkoutCreationAuthorized'] === false
            && $envelope['paymentAuthorized'] === false
            && $envelope['webhookAuthorized'] === false
            && $envelope['liveModeAuthorized'] === false
            && $envelope['clientDeploymentAuthorized'] === false
            && $envelope['credentialValueIncluded'] === false
            && $envelope['contactAuthorized'] === false
            && $envelope['executionPerformed'] === false,
        'authorization remains read-only, value-free, and non-executing'
    );
    $encodedAuthorization = json_encode(
        $envelope,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    );
    red_stripe_p3e6_assert(
        hash('sha256', $encodedAuthorization)
            === $authorization['authorizationSha256'],
        'authorization hash binds exact canonical envelope bytes'
    );
    $authorizationRepeat =
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
            $readiness,
            $confirmation,
            '2026-08-17T12:07:30Z'
        );
    red_stripe_p3e6_assert(
        $authorizationRepeat === $authorization,
        'authorization is deterministic for identical facts'
    );

    foreach ([
        'confirmedRestrictedTestKey',
        'confirmedReadOnlyGet',
        'confirmedSingleAttempt',
        'confirmedNoRetry',
        'confirmedNoMutation',
        'confirmedNoCheckoutCreation',
        'confirmedNoPayment',
        'confirmedNoWebhook',
        'confirmedNoLiveMode',
        'confirmedNoClientDeployment',
    ] as $confirmationKey) {
        $caseConfirmation = $confirmation;
        $caseConfirmation[$confirmationKey] = false;
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
                $readiness,
                $caseConfirmation,
                '2026-08-17T12:07:30Z'
            );
        red_stripe_p3e6_assert(
            $case['prepared'] === false
                && $case['authorization'] === null
                && $case['authorizationSha256'] === ''
                && $case['ownerAuthorityRevalidationRequired'] === true
                && $case['nonceConsumptionRequired'] === true
                && $case['contactAuthorized'] === false
                && $case['executionPerformed'] === false,
            'missing safety confirmation refuses the entire authorization'
        );
    }
    foreach ([
        ['2026-08-17T11:59:59Z', 'contact_authorization_expired'],
        ['2026-08-17T12:15:00Z', 'contact_authorization_expired'],
        ['not-a-time', 'contact_authorization_expired'],
        ['2026-02-30T12:00:00Z', 'contact_authorization_expired'],
    ] as [$evaluatedAt, $expectedError]) {
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
                $readiness,
                $confirmation,
                $evaluatedAt
            );
        red_stripe_p3e6_assert(
            $case['prepared'] === false
                && $case['errors'] === [$expectedError]
                && $case['executionPerformed'] === false,
            'authorization is valid only inside the exact UTC window'
        );
    }
    foreach ([
        ['issuedAtUtc', '2026-08-17T12:15:00Z'],
        ['expiresAtUtc', '2026-08-17T12:00:00Z'],
        ['expiresAtUtc', '2026-08-17T12:15:01Z'],
        ['issuedAtUtc', '2026-08-17 12:00:00Z'],
        ['expiresAtUtc', '2026-08-17T12:15:00+00:00'],
    ] as [$key, $value]) {
        $caseConfirmation = $confirmation;
        $caseConfirmation[$key] = $value;
        $case =
            RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
                $readiness,
                $caseConfirmation,
                '2026-08-17T12:07:30Z'
            );
        red_stripe_p3e6_assert(
            $case['prepared'] === false
                && $case['authorization'] === null
                && $case['executionPerformed'] === false,
            'invalid or overlong authorization window is refused'
        );
    }

    $wrongPlanConfirmation = $confirmation;
    $wrongPlanConfirmation['planSha256'] = str_repeat('e', 64);
    red_stripe_p3e6_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
            $readiness,
            $wrongPlanConfirmation,
            '2026-08-17T12:07:30Z'
        )['prepared'] === false,
        'confirmation for another plan is refused'
    );
    $tamperedReadiness = $readiness;
    $tamperedReadiness['contactPlan']['method'] = 'POST';
    red_stripe_p3e6_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
            $tamperedReadiness,
            $confirmation,
            '2026-08-17T12:07:30Z'
        )['prepared'] === false,
        'tampered plan is refused even with its original hash'
    );
    $expandedConfirmation = $confirmation;
    $expandedConfirmation['secret'] = 'forbidden';
    red_stripe_p3e6_assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
            $readiness,
            $expandedConfirmation,
            '2026-08-17T12:07:30Z'
        )['prepared'] === false,
        'expanded confirmation cannot smuggle credential material'
    );

    echo 'Stripe P3E-6 sandbox contact readiness passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
