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
    || (string) getenv('RED_STRIPE_REHEARSAL_ID') !== 'p3d5'
    || preg_match(
        '/\Aredcms_stripe_p3d5_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Stripe P3D-5 rehearsal refused unsafe input.\n");
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
$apiReference = 'config:p3d2-placeholder-stripe-secret-key';
$webhookReference = 'config:p3d2-placeholder-stripe-webhook-secret';
$syntheticApiValue = null;
$syntheticWebhookValue = null;
$assertions = 0;

function red_stripe_p3d5_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3d5_scalar(
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

function red_stripe_p3d5_rows(
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

function red_stripe_p3d5_database_fingerprint(
    mysqli $connection
): string {
    $material = [
        'installations' => red_stripe_p3d5_rows(
            $connection,
            'SELECT PackageID, PackageVersion, ManifestSHA256,
                    InventorySHA256, LifecycleState,
                    COALESCE(UpdatedByAdminRecordID, 0)
             FROM RED_Addon_Installations ORDER BY PackageID'
        ),
        'activity' => red_stripe_p3d5_rows(
            $connection,
            'SELECT PackageID, EventName, PackageVersion, Result, DetailCode,
                    ActorAdminRecordID
             FROM RED_Addon_Activity_Log ORDER BY RecordID'
        ),
        'settings' => red_stripe_p3d5_rows(
            $connection,
            'SELECT PackageID, SettingKey, ValueType, COALESCE(ValueJSON, \'\'),
                    COALESCE(SecretReference, \'\')
             FROM RED_Addon_Settings ORDER BY PackageID, SettingKey'
        ),
        'businessCounts' => [
            red_stripe_p3d5_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders'
            ),
            red_stripe_p3d5_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Status_History'
            ),
            red_stripe_p3d5_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Stripe_Checkout_Attempts'
            ),
            red_stripe_p3d5_scalar(
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

function red_stripe_p3d5_clear_secret_environment(): void
{
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');
}

try {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_stripe_p3d5_assert(
        red_stripe_p3d5_scalar($connection, 'SELECT DATABASE()')
            === $databaseName
            && red_stripe_p3d5_scalar(
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
    red_stripe_p3d5_assert(
        !empty($catalog['valid'])
            && is_array($storePackage)
            && is_array($adapterPackage)
            && (red_addon_registry_package_report(
                $connection,
                $storePackage
            )['status'] ?? '') === 'enabled_current'
            && (red_addon_registry_package_report(
                $connection,
                $adapterPackage
            )['status'] ?? '') === 'enabled_current'
            && ($storePackage['manifest']['version'] ?? '') === '0.1.35'
            && ($adapterPackage['manifest']['version'] ?? '') === '0.1.3',
        'both exact package identities are enabled and registry-current'
    );
    red_stripe_p3d5_assert(
        getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false,
        'rehearsal refuses to replace any ambient secret configuration'
    );

    $syntheticApiValue = hash('sha256', random_bytes(32));
    $syntheticWebhookValue = hash('sha256', random_bytes(32));
    $apiValueSha256 = hash('sha256', $syntheticApiValue);
    $webhookValueSha256 = hash('sha256', $syntheticWebhookValue);
    $secretJson = json_encode(
        [
            $apiReference => $syntheticApiValue,
            $webhookReference => $syntheticWebhookValue,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $environmentReady = putenv(
        'RED_ADDON_SECRET_REFERENCES=' .
        $apiReference . ',' . $webhookReference
    ) && putenv('RED_ADDON_SECRET_VALUES_JSON=' . $secretJson);
    $secretJson = null;
    $declarations = red_addon_secret_reference_declarations();
    red_stripe_p3d5_assert(
        $environmentReady
            && !empty($declarations['valid'])
            && count($declarations['references'] ?? []) === 2,
        'two process-local synthetic values target the declared references'
    );

    $before = red_stripe_p3d5_database_fingerprint($connection);
    $context = red_addon_runtime_request_bootstrap(
        $connection,
        $projectRoot
    );
    red_stripe_p3d5_assert(
        $context instanceof RED_Addon_Runtime_Context
            && $context->order() === [$storePackageId, $adapterPackageId]
            && red_addon_runtime_current_context() === $context
            && red_addon_runtime_owner('adapters', $adapterId)
                === $adapterPackageId
            && red_addon_runtime_owner('routes', $adapterRouteId)
                === $adapterPackageId
            && red_addon_runtime_owner('services', $storeServiceId)
                === $storePackageId,
        'production request bootstrap installs the exact ownership context'
    );

    $adapterHandler = red_addon_runtime_handler('adapters', $adapterId);
    $routeHandler = red_addon_runtime_handler('routes', $adapterRouteId);
    $serviceHandler = red_addon_runtime_handler('services', $storeServiceId);
    $adapterEntrypoint = realpath(
        $projectRoot . '/addons/redcms/store-lite-stripe-checkout/addon.php'
    );
    $routeReflection = $routeHandler instanceof Closure
        ? new ReflectionFunction($routeHandler)
        : null;
    red_stripe_p3d5_assert(
        $adapterHandler === [
            'RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter',
            'handle',
        ]
            && $routeReflection instanceof ReflectionFunction
            && realpath((string) $routeReflection->getFileName())
                === $adapterEntrypoint
            && $serviceHandler === [
                'RED_CMS_Store_Lite_Payment_Event_Service',
                'handle',
            ],
        'production bootstrap binds exact handler identities without invocation'
    );

    $adapterAccess = red_addon_runtime_secret_access($adapterPackageId);
    red_stripe_p3d5_assert(
        $adapterAccess instanceof RED_Addon_Runtime_Secret_Access
            && $adapterAccess->packageId() === $adapterPackageId
            && $adapterAccess->settingCount() === 2
            && red_addon_runtime_secret_access($storePackageId) === null,
        'only the adapter receives a package-bound two-setting access object'
    );

    $resolvedValue = null;
    $apiResolution = $adapterAccess->resolve(
        'stripe.secret-key',
        $resolvedValue
    );
    red_stripe_p3d5_assert(
        $apiResolution === [
            'valid' => true,
            'resolved' => true,
            'reason' => 'resolved',
        ]
            && is_string($resolvedValue)
            && hash_equals(
                $apiValueSha256,
                hash('sha256', $resolvedValue)
            ),
        'adapter access resolves the synthetic checkout setting privately'
    );
    $resolvedValue = null;

    $webhookResolution = $adapterAccess->resolve(
        'stripe.webhook-secret',
        $resolvedValue
    );
    red_stripe_p3d5_assert(
        $webhookResolution === [
            'valid' => true,
            'resolved' => true,
            'reason' => 'resolved',
        ]
            && is_string($resolvedValue)
            && hash_equals(
                $webhookValueSha256,
                hash('sha256', $resolvedValue)
            ),
        'adapter access resolves the synthetic webhook setting privately'
    );
    $resolvedValue = null;

    $foreignResolution = $adapterAccess->resolve(
        'store.private-setting',
        $resolvedValue
    );
    red_stripe_p3d5_assert(
        $foreignResolution === [
            'valid' => false,
            'resolved' => false,
            'reason' => 'secret_unavailable',
        ] && $resolvedValue === null,
        'package-bound access refuses an undeclared setting without a value'
    );

    $valueFreeEvidence = json_encode(
        [
            'snapshot' => $context->snapshot(),
            'debug' => print_r($adapterAccess, true),
            'order' => $context->order(),
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_stripe_p3d5_assert(
        !str_contains($valueFreeEvidence, $syntheticApiValue)
            && !str_contains($valueFreeEvidence, $syntheticWebhookValue)
            && !str_contains($valueFreeEvidence, $apiReference)
            && !str_contains($valueFreeEvidence, $webhookReference),
        'context snapshot and debug evidence disclose no value or reference'
    );

    $repeatContext = red_addon_runtime_request_bootstrap(
        $connection,
        $projectRoot
    );
    red_stripe_p3d5_assert(
        $repeatContext === $context
            && hash_equals(
                $before,
                red_stripe_p3d5_database_fingerprint($connection)
            ),
        'repeat request bootstrap is idempotent and writes no database row'
    );

    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_stripe_p3d5_assert(
        red_addon_runtime_current_context() === null
            && red_addon_runtime_owner('adapters', $adapterId) === null
            && red_addon_runtime_owner('routes', $adapterRouteId) === null
            && red_addon_runtime_owner('services', $storeServiceId) === null
            && red_addon_runtime_secret_access($adapterPackageId) === null,
        'request teardown removes capability and secret ownership immediately'
    );

    $syntheticApiValue = null;
    $syntheticWebhookValue = null;
    unset($adapterAccess, $context, $repeatContext);
    red_stripe_p3d5_clear_secret_environment();
    red_stripe_p3d5_assert(
        getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false,
        'synthetic process environment is removed before negative proof'
    );

    try {
        red_addon_runtime_request_bootstrap($connection, $projectRoot);
        red_stripe_p3d5_assert(
            false,
            'missing synthetic values must block request bootstrap'
        );
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        red_stripe_p3d5_assert(
            str_contains($message, 'secret configuration')
                && !str_contains($message, $apiReference)
                && !str_contains($message, $webhookReference)
                && red_addon_runtime_current_context() === null
                && hash_equals(
                    $before,
                    red_stripe_p3d5_database_fingerprint($connection)
                ),
            'missing values fail closed without context, disclosure, or writes'
        );
    }

    echo json_encode(
        [
            'ok' => true,
            'adapterVersion' => '0.1.3',
            'storeLiteVersion' => '0.1.35',
            'database' => $databaseName,
            'databaseSHA256' => $before,
            'syntheticValueCount' => 2,
            'assertions' => $assertions,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $syntheticApiValue = null;
    $syntheticWebhookValue = null;
    red_stripe_p3d5_clear_secret_environment();
    $db->close();
    exit(1);
}

unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
$syntheticApiValue = null;
$syntheticWebhookValue = null;
red_stripe_p3d5_clear_secret_environment();
$db->close();
exit(0);
