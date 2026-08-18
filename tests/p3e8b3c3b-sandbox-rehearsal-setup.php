<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath(
    (string) getenv('RED_STRIPE_B3C3B_PROJECT_ROOT')
);
$adapterRepository = realpath(
    (string) getenv('RED_STRIPE_B3C3B_ADAPTER_ROOT')
);
$evidencePath = (string) getenv('RED_STRIPE_B3C3B_EVIDENCE_PATH');
$databaseName = (string) getenv('RED_DB_NAME');
$executeRequested = (string) getenv('RED_STRIPE_B3C3B_EXECUTE')
    === 'YES_ONE_READ_ONLY_GET';
$apiReference = 'config:b3c3b-stripe-secret-key';
$webhookReference = 'config:b3c3b-stripe-webhook-secret';
$expectedReferences = $apiReference . ',' . $webhookReference;

if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || !is_string($adapterRepository)
    || !is_dir($adapterRepository)
    || preg_match(
        '/\Aredcms_stripe_p3e8b3c3b_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
    || !str_starts_with($evidencePath, sys_get_temp_dir() . DIRECTORY_SEPARATOR)
    || (string) getenv('RED_ADDON_SECRET_REFERENCES')
        !== $expectedReferences
) {
    fwrite(STDERR, "Stripe B3C3B setup refused unsafe input.\n");
    exit(64);
}

$secretValuesJson = getenv('RED_ADDON_SECRET_VALUES_JSON');
if (!$executeRequested && $secretValuesJson !== false) {
    fwrite(STDERR, "Preflight refuses ambient secret values.\n");
    exit(65);
}
if ($executeRequested) {
    try {
        $secretValues = is_string($secretValuesJson)
            ? json_decode(
                $secretValuesJson,
                true,
                8,
                JSON_THROW_ON_ERROR
            )
            : null;
    } catch (Throwable $throwable) {
        $secretValues = null;
    }
    if (!is_array($secretValues)
        || array_keys($secretValues) !== [$apiReference]
        || !is_string($secretValues[$apiReference] ?? null)
        || preg_match(
            '/\Ark_test_[A-Za-z0-9_]{16,256}\z/D',
            $secretValues[$apiReference]
        ) !== 1
    ) {
        $secretValues = null;
        fwrite(
            STDERR,
            "Execution requires exactly one package-scoped rk_test_ value.\n"
        );
        exit(65);
    }
    $secretValues[$apiReference] = null;
    $secretValues = null;
}
$secretValuesJson = null;

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot .
    '/includes/addon_payment_adapter_enable_helpers.php';
require_once $projectRoot .
    '/includes/addon_provider_contact_provider_execution_helpers.php';
require_once $adapterRepository .
    '/src/StripeSandboxContactReadinessPlanner.php';
require_once $adapterRepository .
    '/src/StripeSandboxContactAuthorizationGate.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$actorId = 1;
$storePackageId = 'redcms.store-lite';
$adapterPackageId = 'redcms.store-lite-stripe-checkout';

function red_stripe_b3c3b_fail(string $message): never
{
    throw new RuntimeException($message);
}

function red_stripe_b3c3b_store_settings(
    mysqli $connection,
    string $packageId,
    int $actorId,
    array $rows
): void {
    foreach ($rows as [$key, $type, $value, $reference]) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Settings (
                PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$statement
            || !mysqli_stmt_execute($statement, [
                $packageId,
                $key,
                $type,
                $value,
                $reference,
                $actorId,
            ])
        ) {
            if ($statement) {
                mysqli_stmt_close($statement);
            }
            red_stripe_b3c3b_fail('setting_write_failed');
        }
        mysqli_stmt_close($statement);
    }
}

try {
    mysqli_query(
        $connection,
        "INSERT IGNORE INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.install', 'addons.enable'] as $capability) {
        $escaped = mysqli_real_escape_string($connection, $capability);
        mysqli_query(
            $connection,
            "INSERT IGNORE INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES ($actorId, '$escaped', $actorId)"
        );
    }

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? null;
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    if (empty($catalog['valid'])
        || !is_array($storePackage)
        || !is_array($adapterPackage)
        || ($storePackage['manifest']['version'] ?? null) !== '0.1.35'
        || ($adapterPackage['manifest']['version'] ?? null) !== '0.1.4'
    ) {
        red_stripe_b3c3b_fail('package_discovery_failed');
    }

    $storeInstallPlan = red_addon_install_plan(
        $connection,
        $storePackage,
        $actorId,
        false,
        $catalog
    );
    $storeInstalled = red_addon_install_package(
        $connection,
        $storePackageId,
        $projectRoot,
        $actorId,
        $storeInstallPlan['planSha256'] ?? ''
    );
    red_stripe_b3c3b_store_settings(
        $connection,
        $storePackageId,
        $actorId,
        [
            ['catalog.currency', 'text', json_encode('USD'), null],
            ['checkout.delivery-enabled', 'boolean', 'false', null],
            ['checkout.delivery-fee-minor', 'integer', '0', null],
            ['checkout.pay-on-receipt-enabled', 'boolean', 'true', null],
            ['checkout.pickup-enabled', 'boolean', 'true', null],
        ]
    );
    $storeEnablePlan = red_addon_enable_transition_plan(
        $connection,
        $storePackage,
        $actorId,
        $catalog
    );
    $storeEnabled = red_addon_enable_package(
        $connection,
        $storePackageId,
        $projectRoot,
        $actorId,
        $storeEnablePlan['planSha256'] ?? ''
    );

    $adapterInstallPlan = red_addon_install_plan(
        $connection,
        $adapterPackage,
        $actorId,
        false,
        $catalog
    );
    $adapterInstalled = red_addon_install_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $adapterInstallPlan['planSha256'] ?? ''
    );
    red_stripe_b3c3b_store_settings(
        $connection,
        $adapterPackageId,
        $actorId,
        [
            [
                'checkout.return-origin',
                'url',
                json_encode('https://b3c3b.invalid'),
                null,
            ],
            ['stripe.secret-key', 'secret-reference', null, $apiReference],
            [
                'stripe.webhook-secret',
                'secret-reference',
                null,
                $webhookReference,
            ],
        ]
    );
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference, $webhookReference],
        ''
    );
    $adapterEnablePlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    $adapterEnabled = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $adapterEnablePlan['planSha256'] ?? '',
        $declarations
    );
    if (($storeInstalled['status'] ?? null) !== 'installed_disabled'
        || ($storeEnabled['status'] ?? null) !== 'enabled'
        || ($adapterInstalled['status'] ?? null) !== 'installed_disabled'
        || ($adapterEnabled['status'] ?? null) !== 'enabled'
    ) {
        red_stripe_b3c3b_fail('package_lifecycle_failed');
    }

    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $artifactPath = $projectRoot .
        '/addons/redcms/store-lite-stripe-checkout/addon.json';
    $artifactSha256 = hash_file('sha256', $artifactPath);
    if (!red_addon_provider_contact_sha256($ownerSubject)
        || !is_string($artifactSha256)
    ) {
        red_stripe_b3c3b_fail('operator_evidence_failed');
    }
    $credentialMaterial = [
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
    ];
    $credentialEvidence = $credentialMaterial + [
        'evidenceSha256' => hash(
            'sha256',
            json_encode(
                $credentialMaterial,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        ),
    ];
    $readiness =
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Readiness_Planner::plan(
            [
                'packageId' => $adapterPackageId,
                'packageVersion' => '0.1.4',
                'packageArtifactSha256' => $artifactSha256,
                'runtimeProviderTransport' => 'provider_read_only',
            ],
            $credentialEvidence,
            [
                'providerHost' => 'api.stripe.com',
                'providerPort' => 443,
                'method' => 'GET',
                'path' => '/v1/checkout/sessions/' .
                    'cs_test_redcms_readiness_probe',
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
    $issuedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiresAt = $issuedAt->modify('+10 minutes');
    $prepared =
        RED_CMS_Store_Lite_Stripe_Sandbox_Contact_Authorization_Gate::prepare(
            $readiness,
            [
                'action' => 'authorize-stripe-sandbox-read-only-probe',
                'planSha256' => $readiness['planSha256'] ?? '',
                'operatorSubjectSha256' => $ownerSubject,
                'authorizationNonceSha256' => hash(
                    'sha256',
                    random_bytes(32)
                ),
                'issuedAtUtc' => $issuedAt->format('Y-m-d\TH:i:s\Z'),
                'expiresAtUtc' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
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
            ],
            $issuedAt->format('Y-m-d\TH:i:s\Z')
        );
    if (empty($readiness['ready']) || empty($prepared['prepared'])) {
        red_stripe_b3c3b_fail('readiness_preparation_failed');
    }
    $authorized = red_addon_provider_contact_authorize(
        $connection,
        $projectRoot,
        $actorId,
        $readiness,
        $prepared,
        $prepared['authorizationSha256'] ?? '',
        $issuedAt->format('Y-m-d\TH:i:s\Z')
    );
    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    $claimPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $prepared,
        $issuedAt->format('Y-m-d\TH:i:s\Z')
    );
    $claimed = red_addon_provider_contact_claim(
        $connection,
        $projectRoot,
        $actorId,
        $readiness,
        $prepared,
        $claimPlan['authorizationSha256'] ?? '',
        $claimPlan['authorizationStateSha256'] ?? '',
        $claimPlan['claimStateSha256'] ?? '',
        $issuedAt->format('Y-m-d\TH:i:s\Z')
    );
    $executionPlan = red_addon_provider_contact_sandbox_execution_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $prepared,
        $issuedAt->format('Y-m-d\TH:i:s\Z'),
        false,
        $declarations
    );
    if (($authorized['status'] ?? null) !== 'authorized'
        || ($claimed['status'] ?? null) !== 'claimed'
        || empty($executionPlan['ready'])
    ) {
        red_stripe_b3c3b_fail('authorization_claim_failed');
    }

    $evidence = ['readiness' => $readiness, 'prepared' => $prepared];
    if (file_put_contents(
        $evidencePath,
        json_encode(
            $evidence,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
    ) === false || !chmod($evidencePath, 0600)) {
        red_stripe_b3c3b_fail('evidence_write_failed');
    }

    echo json_encode(
        [
            'ready' => true,
            'executionRequested' => $executeRequested,
            'database' => $databaseName,
            'actorAdminRecordId' => $actorId,
            'packageId' => $adapterPackageId,
            'packageVersion' => '0.1.4',
            'lifecycleState' => 'enabled',
            'planSha256' => $executionPlan['planSha256'],
            'authorizationSha256' =>
                $executionPlan['authorizationSha256'],
            'claimStateSha256' => $executionPlan['claimStateSha256'],
            'executionStartStateSha256' =>
                $executionPlan['executionStartStateSha256'],
            'secretAvailabilitySha256' =>
                $executionPlan['secretAvailabilitySha256'],
            'expiresAtUtc' => $executionPlan['expiresAtUtc'],
            'operation' => 'provider-contact.read-only-probe-sandbox',
            'contactTarget' => 'stripe-sandbox',
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    $db->close();
    exit(1);
}

$db->close();
exit(0);

?>
