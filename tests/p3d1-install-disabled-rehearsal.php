<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath((string) getenv('RED_STRIPE_REHEARSAL_PROJECT_ROOT'));
$databaseName = (string) getenv('RED_DB_NAME');
if (!is_string($projectRoot)
    || !is_dir($projectRoot)
    || preg_match(
        '/\Aredcms_stripe_p3d1_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Stripe P3D-1 rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_install_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_registrar_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$storePackageId = 'redcms.store-lite';
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$actorId = 1;
$assertions = 0;

function red_stripe_p3d1_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3d1_scalar(
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

function red_stripe_p3d1_rows(
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

function red_stripe_p3d1_prepare_owner(
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

function red_stripe_p3d1_store_settings(
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

function red_stripe_p3d1_database_fingerprint(
    mysqli $connection
): string {
    $material = [
        'installations' => red_stripe_p3d1_rows(
            $connection,
            'SELECT PackageID, PackageVersion, PackageType,
                    ManifestSHA256, InventorySHA256, LifecycleState
             FROM RED_Addon_Installations ORDER BY PackageID'
        ),
        'migrations' => red_stripe_p3d1_rows(
            $connection,
            'SELECT PackageID, MigrationID, MigrationPath, Checksum
             FROM RED_Addon_Migrations ORDER BY PackageID, MigrationID'
        ),
        'settings' => red_stripe_p3d1_rows(
            $connection,
            'SELECT PackageID, SettingKey, ValueType, ValueJSON,
                    COALESCE(SecretReference, \'\')
             FROM RED_Addon_Settings ORDER BY PackageID, SettingKey'
        ),
        'activity' => red_stripe_p3d1_rows(
            $connection,
            'SELECT PackageID, EventName, Result, DetailCode
             FROM RED_Addon_Activity_Log ORDER BY RecordID'
        ),
        'packageTables' => red_stripe_p3d1_rows(
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
    red_stripe_p3d1_assert(
        red_stripe_p3d1_scalar($connection, 'SELECT DATABASE()')
            === $databaseName,
        'connection is bound to the approved disposable project database'
    );
    red_stripe_p3d1_prepare_owner($connection, $actorId);

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? null;
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    $storeSnapshot = is_array($storePackage)
        ? red_addon_registry_snapshot($storePackage)
        : null;
    $adapterSnapshot = is_array($adapterPackage)
        ? red_addon_registry_snapshot($adapterPackage)
        : null;
    red_stripe_p3d1_assert(
        !empty($catalog['valid'])
            && is_array($storePackage)
            && !empty($storePackage['valid'])
            && is_array($adapterPackage)
            && !empty($adapterPackage['valid']),
        'staged core discovers only valid Store Lite and adapter packages'
    );
    red_stripe_p3d1_assert(
        is_array($storeSnapshot)
            && $storeSnapshot['version'] === '0.1.35'
            && count($storeSnapshot['migrations']) === 11
            && is_array($adapterSnapshot)
            && $adapterSnapshot['version'] === '0.1.4'
            && count($adapterSnapshot['migrations']) === 2,
        'exact Store Lite and adapter versions and migrations are trusted'
    );
    $profile = red_addon_payment_adapter_profile(
        $adapterPackage['manifest']
    );
    red_stripe_p3d1_assert(
        red_addon_payment_adapter_profile_is_valid($profile)
            && $profile['dependencyPackageId'] === $storePackageId
            && $profile['adapter'] === $adapterPackageId . '/checkout'
            && $profile['serverEventRoute']
                === $adapterPackageId . '/provider-events',
        'adapter retains the closed payment-adapter profile in staged core'
    );

    $beforeDependencyRefusal =
        red_stripe_p3d1_database_fingerprint($connection);
    $dependencyRefusal = red_addon_install_plan(
        $connection,
        $adapterPackage,
        $actorId,
        false,
        $catalog
    );
    red_stripe_p3d1_assert(
        empty($dependencyRefusal['valid'])
            && $dependencyRefusal['errors'] === [
                'required_dependency_not_enabled',
            ]
            && hash_equals(
                $beforeDependencyRefusal,
                red_stripe_p3d1_database_fingerprint($connection)
            ),
        'adapter install refuses before Store Lite is enabled without drift'
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
    red_stripe_p3d1_assert(
        !empty($storeInstallPlan['valid'])
            && count($storeInstallPlan['pendingMigrations']) === 11
            && $storeInstalled['status'] === 'installed_disabled'
            && count($storeInstalled['appliedMigrations']) === 11,
        'Store Lite installs disabled with all eleven migrations'
    );
    red_stripe_p3d1_store_settings(
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
    red_stripe_p3d1_assert(
        !empty($storeEnablePlan['transitionReady'])
            && $storeEnabled['status'] === 'enabled'
            && red_addon_valid_sha256(
                $storeEnabled['registrarEvidenceSha256'] ?? ''
            ),
        'the exact Store Lite dependency enables before adapter installation'
    );

    $adapterInstallPlan = red_addon_install_plan(
        $connection,
        $adapterPackage,
        $actorId,
        false,
        $catalog
    );
    red_stripe_p3d1_assert(
        !empty($adapterInstallPlan['valid'])
            && $adapterInstallPlan['resume'] === false
            && $adapterInstallPlan['appliedMigrations'] === []
            && $adapterInstallPlan['pendingMigrations'] === [
                '2026-08-16-checkout-attempts',
                '2026-08-16-event-receipts',
            ],
        'adapter receives a fresh two-migration install-disabled plan'
    );
    red_stripe_p3d1_assert(
        $adapterInstallPlan['requiredDependencies'] === [[
            'id' => $storePackageId,
            'versionRange' => '>=0.1.35 <1.0',
            'installedVersion' => '0.1.35',
            'manifestSha256' => $storeSnapshot['manifestSha256'],
            'inventorySha256' => $storeSnapshot['inventorySha256'],
            'lifecycleState' => 'enabled',
        ]],
        'install plan binds the exact enabled Store Lite dependency evidence'
    );

    $beforeChangedPlan = red_stripe_p3d1_database_fingerprint($connection);
    $changedPlan = red_addon_install_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        str_repeat('f', 64)
    );
    red_stripe_p3d1_assert(
        $changedPlan['status'] === 'plan_changed'
            && hash_equals(
                $beforeChangedPlan,
                red_stripe_p3d1_database_fingerprint($connection)
            ),
        'changed install-plan evidence is refused before adapter mutation'
    );

    $adapterInstalled = red_addon_install_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $adapterInstallPlan['planSha256']
    );
    red_stripe_p3d1_assert(
        $adapterInstalled['status'] === 'installed_disabled'
            && $adapterInstalled['version'] === '0.1.4'
            && $adapterInstalled['appliedMigrations'] === [
                '2026-08-16-checkout-attempts',
                '2026-08-16-event-receipts',
            ]
            && $adapterInstalled['failedMigration'] === '',
        'real adapter installation ends disabled after both migrations'
    );
    red_stripe_p3d1_assert(
        red_stripe_p3d1_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, PackageType,
                LifecycleState)
             FROM RED_Addon_Installations
             WHERE PackageID='$adapterPackageId'"
        ) === '0.1.4:adapter:installed_disabled',
        'adapter registry stores the exact disabled identity'
    );
    red_stripe_p3d1_assert(
        red_stripe_p3d1_rows(
            $connection,
            "SELECT MigrationID, MigrationPath, Checksum
             FROM RED_Addon_Migrations
             WHERE PackageID='$adapterPackageId'
             ORDER BY MigrationID"
        ) === [[
            '2026-08-16-checkout-attempts',
            'migrations/2026-08-16-create-checkout-attempts.sql',
            'f58ae3b56d5b96d80f2757162e41e0fa4540f5e652934b9708e3884be633c2fa',
        ], [
            '2026-08-16-event-receipts',
            'migrations/2026-08-16-create-event-receipts.sql',
            '20b516693d15bf2fb3829de6d9c9fe44202af03b846a05262d0c79f2b0cd2b8d',
        ]],
        'migration ledger preserves both exact paths and checksums'
    );
    red_stripe_p3d1_assert(
        red_stripe_p3d1_rows(
            $connection,
            "SELECT TABLE_NAME, ENGINE
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME IN (
                 'RED_Addon_StoreLite_Stripe_Checkout_Attempts',
                 'RED_Addon_StoreLite_Stripe_Event_Receipts'
               )
             ORDER BY TABLE_NAME"
        ) === [[
            'RED_Addon_StoreLite_Stripe_Checkout_Attempts',
            'InnoDB',
        ], [
            'RED_Addon_StoreLite_Stripe_Event_Receipts',
            'InnoDB',
        ]],
        'installation creates exactly the two adapter-owned InnoDB tables'
    );
    red_stripe_p3d1_assert(
        red_stripe_p3d1_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Settings
             WHERE PackageID='$adapterPackageId'"
        ) === '0',
        'install-disabled stores no ordinary or secret adapter setting value'
    );
    red_stripe_p3d1_assert(
        red_stripe_p3d1_rows(
            $connection,
            "SELECT EventName, Result, DetailCode
             FROM RED_Addon_Activity_Log
             WHERE PackageID='$adapterPackageId'
             ORDER BY RecordID"
        ) === [[
            'addon.install.started',
            'started',
            'install_started',
        ], [
            'addon.install.completed',
            'succeeded',
            'installed_disabled',
        ]],
        'adapter installation writes only bounded start and completion facts'
    );
    red_stripe_p3d1_assert(
        !in_array(
            $adapterPackage['path'] . '/addon.php',
            get_included_files(),
            true
        ),
        'installation and migration execution do not load adapter PHP'
    );

    $installedFingerprint =
        red_stripe_p3d1_database_fingerprint($connection);
    $repeatPlan = red_addon_install_plan(
        $connection,
        $adapterPackage,
        $actorId,
        false,
        $catalog
    );
    red_stripe_p3d1_assert(
        empty($repeatPlan['valid'])
            && $repeatPlan['errors'] === ['package_already_recorded']
            && hash_equals(
                $installedFingerprint,
                red_stripe_p3d1_database_fingerprint($connection)
            ),
        'repeat installation planning refuses without schema or ledger drift'
    );

    $beforeDatabasePreflight =
        red_stripe_p3d1_database_fingerprint($connection);
    $databasePlan = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_stripe_p3d1_assert(
        red_addon_payment_adapter_database_preflight_is_valid($databasePlan)
            && !empty($databasePlan['databaseEvidenceReady'])
            && $databasePlan['currentState'] === 'installed_disabled'
            && $databasePlan['dependencyCount'] === 1
            && $databasePlan['migrationCount'] === 2
            && $databasePlan['tableCount'] === 2
            && $databasePlan['innoDbTableCount'] === 2,
        'real database evidence binds the disabled adapter and exact tables'
    );
    red_stripe_p3d1_assert(
        !$databasePlan['stateMutation']
            && !$databasePlan['runtimeLoad']
            && !$databasePlan['packageExecution']
            && !$databasePlan['secretResolution']
            && !$databasePlan['networkAccess']
            && !$databasePlan['routeExposure']
            && hash_equals(
                $beforeDatabasePreflight,
                red_stripe_p3d1_database_fingerprint($connection)
            ),
        'database preflight remains read-only, value-free, and offline'
    );

    $beforeRegistrar = red_stripe_p3d1_database_fingerprint($connection);
    $registrarPlan = red_addon_payment_adapter_validate_registrar(
        $adapterPackage,
        $databasePlan
    );
    red_stripe_p3d1_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($registrarPlan)
            && $registrarPlan['adapter']
                === $adapterPackageId . '/checkout'
            && $registrarPlan['serverEventRoute']
                === $adapterPackageId . '/provider-events'
            && $registrarPlan['registrationCount'] === 2,
        'real installed-disabled evidence validates two exact registrations'
    );
    red_stripe_p3d1_assert(
        $registrarPlan['packageExecutionAttempted']
            && $registrarPlan['registrarExecutionCompleted']
            && !$registrarPlan['handlerInvocation']
            && !$registrarPlan['secretResolution']
            && !$registrarPlan['networkAccess']
            && !$registrarPlan['routeExposure']
            && !$registrarPlan['stateMutation']
            && !$registrarPlan['runtimePublication']
            && hash_equals(
                $beforeRegistrar,
                red_stripe_p3d1_database_fingerprint($connection)
            ),
        'contained registrar validation invokes no handler or runtime path'
    );
    red_stripe_p3d1_assert(
        in_array(
            $adapterPackage['path'] . '/addon.php',
            get_included_files(),
            true
        )
            && red_addon_runtime_owner(
                'adapters',
                $adapterPackageId . '/checkout'
            ) === null
            && red_addon_runtime_owner(
                'routes',
                $adapterPackageId . '/provider-events'
            ) === null,
        'registrar is discarded without publishing adapter or route ownership'
    );
    red_stripe_p3d1_assert(
        red_stripe_p3d1_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                SUM(PackageID='$storePackageId' AND LifecycleState='enabled'),
                SUM(PackageID='$adapterPackageId'
                    AND LifecycleState='installed_disabled'))
             FROM RED_Addon_Installations"
        ) === '1:1',
        'rehearsal stops with Store Lite enabled and adapter disabled'
    );

    echo json_encode(
        [
            'ok' => true,
            'adapterVersion' => $adapterSnapshot['version'],
            'database' => $databaseName,
            'installedFingerprintSHA256' => $installedFingerprint,
            'databasePlanSHA256' => $databasePlan['planSha256'],
            'registrarPlanSHA256' => $registrarPlan['planSha256'],
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
