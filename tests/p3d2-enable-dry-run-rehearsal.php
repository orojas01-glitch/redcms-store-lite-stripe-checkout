<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath((string) getenv('RED_STRIPE_REHEARSAL_PROJECT_ROOT'));
$databaseName = (string) getenv('RED_DB_NAME');
$rehearsalId = (string) getenv('RED_STRIPE_REHEARSAL_ID');
if (!in_array($rehearsalId, ['p3d2', 'p3d3', 'p3d4', 'p3d5'], true)) {
    $rehearsalId = 'p3d2';
}
$rehearsalLabel = strtoupper($rehearsalId);
if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match(
        '/\Aredcms_stripe_' . preg_quote($rehearsalId, '/')
            . '_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(
        STDERR,
        "Stripe $rehearsalLabel readiness rehearsal refused unsafe input.\n"
    );
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_enable_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$storePackageId = 'redcms.store-lite';
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$actorId = 1;
$assertions = 0;

function red_stripe_p3d2_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3d2_scalar(
    mysqli $connection,
    string $sql
): string {
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_stripe_p3d2_rows(
    mysqli $connection,
    string $sql
): array {
    $query = mysqli_query($connection, $sql);
    $rows = [];
    while ($query && ($row = mysqli_fetch_row($query))) {
        $rows[] = array_map(
            static fn (mixed $value): string =>
                $value === null ? '' : (string) $value,
            $row
        );
    }
    if ($query) {
        mysqli_free_result($query);
    }
    return $rows;
}

function red_stripe_p3d2_prepare_owner(
    mysqli $connection,
    int $actorId
): void {
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
}

function red_stripe_p3d2_store_store_lite_settings(
    mysqli $connection,
    string $packageId,
    int $actorId
): void {
    $settings = [
        ['catalog.currency', 'text', 'USD'],
        ['checkout.delivery-enabled', 'boolean', false],
        ['checkout.delivery-fee-minor', 'integer', 0],
        ['checkout.pay-on-receipt-enabled', 'boolean', true],
        ['checkout.pickup-enabled', 'boolean', true],
    ];
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
            (PackageID, SettingKey, ValueType, ValueJSON,
             SecretReference, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, NULL, ?)'
    );
    foreach ($settings as [$key, $type, $value]) {
        mysqli_stmt_execute($statement, [
            $packageId,
            $key,
            $type,
            json_encode($value, JSON_THROW_ON_ERROR),
            $actorId,
        ]);
    }
    mysqli_stmt_close($statement);
}

function red_stripe_p3d2_store_adapter_settings(
    mysqli $connection,
    string $packageId,
    int $actorId,
    string $returnOrigin,
    string $apiReference,
    string $webhookReference
): void {
    $rows = [[
        'checkout.return-origin',
        'url',
        json_encode($returnOrigin, JSON_THROW_ON_ERROR),
        null,
    ], [
        'stripe.secret-key',
        'secret-reference',
        null,
        $apiReference,
    ], [
        'stripe.webhook-secret',
        'secret-reference',
        null,
        $webhookReference,
    ]];
    foreach ($rows as $row) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Settings (
                PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_execute($statement, [
            $packageId,
            $row[0],
            $row[1],
            $row[2],
            $row[3],
            $actorId,
        ]);
        mysqli_stmt_close($statement);
    }
}

function red_stripe_p3d2_database_fingerprint(
    mysqli $connection
): string {
    $material = [
        'installations' => red_stripe_p3d2_rows(
            $connection,
            'SELECT PackageID, PackageVersion, PackageType,
                    ManifestSHA256, InventorySHA256, LifecycleState
             FROM RED_Addon_Installations ORDER BY PackageID'
        ),
        'migrations' => red_stripe_p3d2_rows(
            $connection,
            'SELECT PackageID, MigrationID, MigrationPath, Checksum
             FROM RED_Addon_Migrations ORDER BY PackageID, MigrationID'
        ),
        'settings' => red_stripe_p3d2_rows(
            $connection,
            'SELECT PackageID, SettingKey, ValueType, ValueJSON,
                    COALESCE(SecretReference, \'\')
             FROM RED_Addon_Settings ORDER BY PackageID, SettingKey'
        ),
        'activity' => red_stripe_p3d2_rows(
            $connection,
            'SELECT PackageID, EventName, Result, DetailCode
             FROM RED_Addon_Activity_Log ORDER BY RecordID'
        ),
        'packageTables' => red_stripe_p3d2_rows(
            $connection,
            "SELECT TABLE_NAME, ENGINE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME LIKE 'RED_Addon_StoreLite%'
             ORDER BY TABLE_NAME"
        ),
    ];
    return hash(
        'sha256',
        json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    );
}

try {
    red_stripe_p3d2_assert(
        red_stripe_p3d2_scalar($connection, 'SELECT DATABASE()')
            === $databaseName,
        'connection is bound to the approved P3D-2 disposable database'
    );
    red_stripe_p3d2_prepare_owner($connection, $actorId);

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? null;
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    red_stripe_p3d2_assert(
        !empty($catalog['valid'])
            && is_array($storePackage)
            && !empty($storePackage['valid'])
            && is_array($adapterPackage)
            && !empty($adapterPackage['valid']),
        'staged core discovers exact valid Store Lite and adapter packages'
    );

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
    red_stripe_p3d2_store_store_lite_settings(
        $connection,
        $storePackageId,
        $actorId
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
    red_stripe_p3d2_assert(
        $storeInstalled['status'] === 'installed_disabled'
            && $storeEnabled['status'] === 'enabled'
            && $adapterInstalled['status'] === 'installed_disabled'
            && count($adapterInstalled['appliedMigrations']) === 2,
        'P3D-1 foundation is reproduced before configuration readiness'
    );

    $returnOrigin = 'https://checkout.p3d2.example.test';
    $apiReference = 'config:p3d2-placeholder-stripe-secret-key';
    $webhookReference = 'config:p3d2-placeholder-stripe-webhook-secret';
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference, $webhookReference],
        ''
    );
    red_stripe_p3d2_assert(
        !empty($declarations['valid'])
            && count($declarations['references']) === 2
            && red_addon_valid_sha256(
                $declarations['declarationSha256'] ?? ''
            ),
        'two non-secret placeholder references form valid local declarations'
    );

    $beforeMissingConfiguration =
        red_stripe_p3d2_database_fingerprint($connection);
    $missingConfiguration = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        empty($missingConfiguration['valid'])
            && $missingConfiguration['errors'] === [
                'payment_adapter_configuration_invalid',
            ]
            && hash_equals(
                $beforeMissingConfiguration,
                red_stripe_p3d2_database_fingerprint($connection)
            ),
        'enable dry-run refuses absent settings with no database drift'
    );

    red_stripe_p3d2_store_adapter_settings(
        $connection,
        $adapterPackageId,
        $actorId,
        $returnOrigin,
        $apiReference,
        $webhookReference
    );
    red_stripe_p3d2_assert(
        red_stripe_p3d2_rows(
            $connection,
            "SELECT SettingKey, ValueType, COALESCE(ValueJSON, ''),
                    COALESCE(SecretReference, '')
             FROM RED_Addon_Settings
             WHERE PackageID='$adapterPackageId'
             ORDER BY SettingKey"
        ) === [[
            'checkout.return-origin',
            'url',
            json_encode($returnOrigin, JSON_THROW_ON_ERROR),
            '',
        ], [
            'stripe.secret-key',
            'secret-reference',
            '',
            $apiReference,
        ], [
            'stripe.webhook-secret',
            'secret-reference',
            '',
            $webhookReference,
        ]],
        'storage separates one ordinary value from two opaque references'
    );

    $availability = red_addon_secret_availability_storage_evidence(
        $connection,
        $adapterPackage['manifest'],
        $adapterPackageId,
        $declarations
    );
    red_stripe_p3d2_assert(
        !empty($availability['valid'])
            && !empty($availability['available'])
            && $availability['secretSettingCount'] === 2
            && $availability['availableCount'] === 2
            && $availability['missing'] === []
            && red_addon_valid_sha256(
                $availability['configurationSha256'] ?? ''
            )
            && red_addon_valid_sha256(
                $availability['evidenceSha256'] ?? ''
            ),
        'storage evidence proves both references available without resolution'
    );
    $encodedAvailability = json_encode(
        $availability,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_stripe_p3d2_assert(
        !str_contains($encodedAvailability, $returnOrigin)
            && !str_contains($encodedAvailability, $apiReference)
            && !str_contains($encodedAvailability, $webhookReference),
        'availability evidence exposes only counts, missing keys, and hashes'
    );

    $partialDeclarations = red_addon_secret_reference_declarations(
        [$apiReference],
        ''
    );
    $partialAvailability = red_addon_secret_availability_storage_evidence(
        $connection,
        $adapterPackage['manifest'],
        $adapterPackageId,
        $partialDeclarations
    );
    red_stripe_p3d2_assert(
        !empty($partialAvailability['valid'])
            && empty($partialAvailability['available'])
            && $partialAvailability['availableCount'] === 1
            && $partialAvailability['missing'] === [
                'stripe.webhook-secret',
            ],
        'partial declarations identify only the missing setting key'
    );
    $beforePartialPlan = red_stripe_p3d2_database_fingerprint($connection);
    $partialPlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $partialDeclarations
    );
    red_stripe_p3d2_assert(
        empty($partialPlan['valid'])
            && $partialPlan['errors'] === [
                'payment_adapter_configuration_incomplete',
            ]
            && hash_equals(
                $beforePartialPlan,
                red_stripe_p3d2_database_fingerprint($connection)
            ),
        'incomplete availability blocks enable readiness without mutation'
    );

    $configuredFingerprint =
        red_stripe_p3d2_database_fingerprint($connection);
    $plan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($plan)
            && $plan['enableReady']
            && $plan['activationSupported']
            && $plan['currentState'] === 'installed_disabled'
            && $plan['targetState'] === 'enabled',
        'complete value-free evidence yields one enable-ready dry-run plan'
    );
    red_stripe_p3d2_assert(
        $plan['settingCount'] === 3
            && $plan['configuredSettingCount'] === 3
            && $plan['secretSettingCount'] === 2
            && $plan['availableSecretCount'] === 2
            && $plan['gates'] === [
                'adapterContract' => 'passed',
                'databaseEvidence' => 'passed',
                'registrarValidation' => 'passed',
                'serverEventIngress' => 'passed',
                'settingsConfiguration' => 'passed',
                'secretAvailability' => 'passed',
                'atomicEnablement' => 'ready',
            ],
        'dry run joins the exact three settings with every prior readiness gate'
    );
    red_stripe_p3d2_assert(
        !$plan['stateMutation']
            && !$plan['runtimePublication']
            && !$plan['handlerInvocation']
            && !$plan['secretResolution']
            && !$plan['networkAccess']
            && !$plan['routeExposure']
            && $plan['packageExecutionAttempted']
            && $plan['registrarExecutionCompleted']
            && hash_equals(
                $configuredFingerprint,
                red_stripe_p3d2_database_fingerprint($connection)
            ),
        'dry run is read-only and invokes no handler, secret, route, or network'
    );
    $encodedPlan = json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_stripe_p3d2_assert(
        !str_contains($encodedPlan, $returnOrigin)
            && !str_contains($encodedPlan, $apiReference)
            && !str_contains($encodedPlan, $webhookReference),
        'enablement plan exposes no setting value or secret-reference string'
    );
    $repeatPlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        $repeatPlan === $plan,
        'unchanged complete evidence produces an identical dry-run plan'
    );
    $tamperedPlan = $plan;
    $tamperedPlan['availableSecretCount'] = 1;
    red_stripe_p3d2_assert(
        !red_addon_payment_adapter_enablement_plan_is_valid($tamperedPlan),
        'tampered value-free evidence fails deterministic validation'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Settings
         SET ValueJSON='\"https://changed.p3d2.example.test\"'
         WHERE PackageID='$adapterPackageId'
           AND SettingKey='checkout.return-origin'"
    );
    $changedPlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($changedPlan)
            && !hash_equals($plan['planSha256'], $changedPlan['planSha256'])
            && !hash_equals(
                $plan['settingsStateSha256'],
                $changedPlan['settingsStateSha256']
            )
            && red_stripe_p3d2_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='$adapterPackageId'"
            ) === 'installed_disabled',
        'configuration drift changes dry-run evidence but not lifecycle state'
    );
    $statement = mysqli_prepare(
        $connection,
        'UPDATE RED_Addon_Settings SET ValueJSON=?
         WHERE PackageID=? AND SettingKey=?'
    );
    $returnOriginJson = json_encode($returnOrigin, JSON_THROW_ON_ERROR);
    $returnOriginKey = 'checkout.return-origin';
    mysqli_stmt_execute($statement, [
        $returnOriginJson,
        $adapterPackageId,
        $returnOriginKey,
    ]);
    mysqli_stmt_close($statement);
    $restoredPlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        $restoredPlan === $plan,
        'restoring exact configuration restores the exact dry-run evidence'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId AND Capability='addons.enable'"
    );
    $revoked = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        empty($revoked['valid'])
            && $revoked['errors'] === [
                'database_payment_adapter_evidence_invalid',
            ],
        'fresh Owner enable authority remains mandatory for every dry run'
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'addons.enable', $actorId)"
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled'
         WHERE PackageID='$storePackageId'"
    );
    $disabledDependency = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_stripe_p3d2_assert(
        empty($disabledDependency['valid'])
            && $disabledDependency['errors'] === [
                'database_payment_adapter_evidence_invalid',
            ],
        'disabled Store Lite dependency blocks enable readiness'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='$storePackageId'"
    );

    $fixtureSource = (string) file_get_contents(__FILE__);
    red_stripe_p3d2_assert(
        !str_contains(
            $fixtureSource,
            'red_addon_payment_adapter_enable_' . 'package('
        )
            && red_stripe_p3d2_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    SUM(EventName='addon.enable.completed'))
                 FROM RED_Addon_Installations installation
                 LEFT JOIN RED_Addon_Activity_Log activity
                   ON activity.PackageID=installation.PackageID
                 WHERE installation.PackageID='$adapterPackageId'"
            ) === 'installed_disabled:0'
            && red_addon_runtime_owner(
                'adapters',
                $adapterPackageId . '/checkout'
            ) === null
            && red_addon_runtime_owner(
                'routes',
                $adapterPackageId . '/provider-events'
            ) === null,
        'P3D-2 contains no apply call and publishes no runtime ownership'
    );

    echo json_encode(
        [
            'ok' => true,
            'adapterVersion' => '0.1.0',
            'database' => $databaseName,
            'configurationSHA256' => $availability['configurationSha256'],
            'secretAvailabilitySHA256' => $availability['evidenceSha256'],
            'enablementPlanSHA256' => $plan['planSha256'],
            'assertions' => $assertions,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $db->close();
    exit(1);
}

unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
$db->close();
exit(0);
