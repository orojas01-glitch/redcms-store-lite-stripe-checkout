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
    || (string) getenv('RED_STRIPE_REHEARSAL_ID') !== 'p3d4'
    || preg_match(
        '/\Aredcms_stripe_p3d4_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Stripe P3D-4 rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_registry_helpers.php';
require_once $projectRoot . '/includes/addon_runtime_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$storePackageId = 'redcms.store-lite';
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$adapterId = $adapterPackageId . '/checkout';
$adapterRouteId = $adapterPackageId . '/provider-events';
$storeServiceId = 'commerce.orders';
$assertions = 0;

function red_stripe_p3d4_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3d4_scalar(
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

function red_stripe_p3d4_rows(
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

function red_stripe_p3d4_database_fingerprint(
    mysqli $connection
): string {
    $material = [
        'installations' => red_stripe_p3d4_rows(
            $connection,
            'SELECT PackageID, PackageVersion, ManifestSHA256,
                    InventorySHA256, LifecycleState,
                    COALESCE(UpdatedByAdminRecordID, 0)
             FROM RED_Addon_Installations ORDER BY PackageID'
        ),
        'activity' => red_stripe_p3d4_rows(
            $connection,
            'SELECT PackageID, EventName, PackageVersion, Result, DetailCode,
                    ActorAdminRecordID
             FROM RED_Addon_Activity_Log ORDER BY RecordID'
        ),
        'settings' => red_stripe_p3d4_rows(
            $connection,
            'SELECT PackageID, SettingKey, ValueType, COALESCE(ValueJSON, \'\'),
                    COALESCE(SecretReference, \'\')
             FROM RED_Addon_Settings ORDER BY PackageID, SettingKey'
        ),
        'businessCounts' => [
            red_stripe_p3d4_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders'
            ),
            red_stripe_p3d4_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Status_History'
            ),
            red_stripe_p3d4_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Stripe_Checkout_Attempts'
            ),
            red_stripe_p3d4_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Stripe_Event_Receipts'
            ),
        ],
    ];
    return hash(
        'sha256',
        json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    );
}

function red_stripe_p3d4_adapter_registry_is_exact(
    RED_Addon_Runtime_Registry $registry,
    string $adapterId,
    string $routeId
): bool {
    $registrations = $registry->snapshot()['registrations'] ?? null;
    if (!is_array($registrations)
        || ($registrations['adapters'] ?? null) !== [$adapterId]
        || ($registrations['routes'] ?? null) !== [$routeId]
    ) {
        return false;
    }
    foreach ($registrations as $type => $ids) {
        if (!in_array($type, ['adapters', 'routes'], true) && $ids !== []) {
            return false;
        }
    }
    return true;
}

try {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_stripe_p3d4_assert(
        red_stripe_p3d4_scalar($connection, 'SELECT DATABASE()')
            === $databaseName
            && red_stripe_p3d4_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='$storePackageId'),
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='$adapterPackageId'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='$adapterPackageId'
                       AND EventName='addon.enable.completed'
                       AND DetailCode='payment_adapter_enabled'))"
            ) === 'enabled:enabled:1',
        'P3D-3 leaves the exact enabled two-package disposable baseline'
    );

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? null;
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    $storeReport = is_array($storePackage)
        ? red_addon_registry_package_report($connection, $storePackage)
        : [];
    $adapterReport = is_array($adapterPackage)
        ? red_addon_registry_package_report($connection, $adapterPackage)
        : [];
    red_stripe_p3d4_assert(
        !empty($catalog['valid'])
            && is_array($storePackage)
            && is_array($adapterPackage)
            && ($storeReport['status'] ?? '') === 'enabled_current'
            && ($adapterReport['status'] ?? '') === 'enabled_current'
            && ($storePackage['manifest']['version'] ?? '') === '0.1.35'
            && ($adapterPackage['manifest']['version'] ?? '') === '0.1.4',
        'both exact package identities are enabled and registry-current'
    );

    $loadErrors = [];
    $order = red_addon_runtime_load_order(
        $catalog,
        [$adapterPackageId, $storePackageId],
        $loadErrors
    );
    red_stripe_p3d4_assert(
        $order === [$storePackageId, $adapterPackageId]
            && $loadErrors === []
            && red_addon_runtime_namespace_errors(
                $catalog,
                [$adapterPackageId, $storePackageId]
            ) === [],
        'dependency ordering loads Store Lite first with no namespace conflict'
    );
    red_stripe_p3d4_assert(
        red_addon_runtime_current_context() === null
            && red_addon_runtime_owner('adapters', $adapterId) === null
            && red_addon_runtime_owner('routes', $adapterRouteId) === null
            && red_addon_runtime_owner('services', $storeServiceId) === null,
        'no capability exists before the request-local context is installed'
    );

    $before = red_stripe_p3d4_database_fingerprint($connection);
    $storeRegistry = red_addon_runtime_register_package($storePackage);
    $adapterRegistry = red_addon_runtime_register_package($adapterPackage);
    red_stripe_p3d4_assert(
        ($storeRegistry->snapshot()['registrations']['services'] ?? null)
            === ['commerce.cart', 'commerce.catalog', 'commerce.orders']
            && red_stripe_p3d4_adapter_registry_is_exact(
                $adapterRegistry,
                $adapterId,
                $adapterRouteId
            ),
        'integrity-checked registrars expose only their declared capabilities'
    );

    $storeHandler = $storeRegistry->handler('services', $storeServiceId);
    $adapterHandler = $adapterRegistry->handler('adapters', $adapterId);
    $routeHandler = $adapterRegistry->handler('routes', $adapterRouteId);
    $adapterEntrypoint = realpath(
        $projectRoot . '/addons/redcms/store-lite-stripe-checkout/addon.php'
    );
    $routeReflection = $routeHandler instanceof Closure
        ? new ReflectionFunction($routeHandler)
        : null;
    red_stripe_p3d4_assert(
        $storeHandler === [
            'RED_CMS_Store_Lite_Payment_Event_Service',
            'handle',
        ]
            && $adapterHandler === [
                'RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter',
                'handle',
            ]
            && $routeReflection instanceof ReflectionFunction
            && realpath((string) $routeReflection->getFileName())
                === $adapterEntrypoint,
        'handler identities bind to exact Store Lite and adapter entrypoints'
    );

    $context = new RED_Addon_Runtime_Context(
        $order,
        [
            $storePackageId => $storeRegistry,
            $adapterPackageId => $adapterRegistry,
        ]
    );
    red_addon_runtime_set_request_context($context);
    red_stripe_p3d4_assert(
        $context->order() === [$storePackageId, $adapterPackageId]
            && red_addon_runtime_owner('adapters', $adapterId)
                === $adapterPackageId
            && red_addon_runtime_owner('routes', $adapterRouteId)
                === $adapterPackageId
            && red_addon_runtime_owner('services', $storeServiceId)
                === $storePackageId,
        'one request-local context owns the exact adapter, route, and service'
    );

    $adapterManifest = red_addon_runtime_manifest($adapterPackageId);
    $storeManifest = red_addon_runtime_manifest($storePackageId);
    red_stripe_p3d4_assert(
        $adapterManifest === $adapterPackage['manifest']
            && $storeManifest === $storePackage['manifest']
            && ($adapterManifest['dependencies']['required'] ?? null) === [[
                'id' => $storePackageId,
                'version' => '>=0.1.35 <1.0',
            ]]
            && in_array(
                $storeServiceId,
                $storeManifest['provides']['services'] ?? [],
                true
            ),
        'adapter dependency and Store Lite payment-service declaration agree'
    );
    red_stripe_p3d4_assert(
        $context->secretAccess($adapterPackageId) === null
            && $context->secretAccess($storePackageId) === null,
        'the offline request-local context contains no secret-access object'
    );

    $fixtureSource = (string) file_get_contents(__FILE__);
    red_stripe_p3d4_assert(
        !str_contains(
            $fixtureSource,
            'red_addon_runtime_' . 'bootstrap('
        )
            && !str_contains(
                $fixtureSource,
                'red_addon_runtime_request_' . 'bootstrap('
            )
            && !str_contains(
                $fixtureSource,
                'red_addon_service_' . 'invoke('
            )
            && !str_contains(
                $fixtureSource,
                'red_addon_secret_' . 'resolve('
            ),
        'P3D-4 invokes no full bootstrap, handler, service, or secret resolver'
    );
    red_stripe_p3d4_assert(
        hash_equals(
            $before,
            red_stripe_p3d4_database_fingerprint($connection)
        ),
        'registrar and request-local ownership assembly writes no database row'
    );

    $snapshot = $context->snapshot();
    $snapshotSha256 = hash(
        'sha256',
        json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_stripe_p3d4_assert(
        red_addon_runtime_current_context() === null
            && red_addon_runtime_owner('adapters', $adapterId) === null
            && red_addon_runtime_owner('routes', $adapterRouteId) === null
            && red_addon_runtime_owner('services', $storeServiceId) === null,
        'removing the request context removes every runtime owner immediately'
    );

    $repeatStore = red_addon_runtime_register_package($storePackage);
    $repeatAdapter = red_addon_runtime_register_package($adapterPackage);
    $repeatContext = new RED_Addon_Runtime_Context(
        $order,
        [
            $storePackageId => $repeatStore,
            $adapterPackageId => $repeatAdapter,
        ]
    );
    red_stripe_p3d4_assert(
        $repeatContext->snapshot() === $snapshot
            && hash_equals(
                $before,
                red_stripe_p3d4_database_fingerprint($connection)
            ),
        'a second isolated context reproduces exact ownership without writes'
    );

    echo json_encode(
        [
            'ok' => true,
            'adapterVersion' => '0.1.4',
            'storeLiteVersion' => '0.1.35',
            'database' => $databaseName,
            'runtimeSnapshotSHA256' => $snapshotSha256,
            'databaseSHA256' => $before,
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
