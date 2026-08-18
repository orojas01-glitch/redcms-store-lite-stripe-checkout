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
    || (string) getenv('RED_STRIPE_REHEARSAL_ID') !== 'p3d7'
    || preg_match(
        '/\Aredcms_stripe_p3d7_[A-Za-z0-9_]+\z/D',
        $databaseName
    ) !== 1
) {
    fwrite(STDERR, "Stripe P3D-7 rehearsal refused unsafe input.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_registry_helpers.php';
require_once $projectRoot . '/includes/addon_runtime_helpers.php';
require_once $projectRoot . '/includes/addon_adapter_helpers.php';

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

function red_stripe_p3d7_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3d7_scalar(
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

function red_stripe_p3d7_rows(
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

function red_stripe_p3d7_database_fingerprint(
    mysqli $connection
): string {
    $material = [
        'installations' => red_stripe_p3d7_rows(
            $connection,
            'SELECT PackageID, PackageVersion, ManifestSHA256,
                    InventorySHA256, LifecycleState,
                    COALESCE(UpdatedByAdminRecordID, 0)
             FROM RED_Addon_Installations ORDER BY PackageID'
        ),
        'activity' => red_stripe_p3d7_rows(
            $connection,
            'SELECT PackageID, EventName, PackageVersion, Result, DetailCode,
                    ActorAdminRecordID
             FROM RED_Addon_Activity_Log ORDER BY RecordID'
        ),
        'settings' => red_stripe_p3d7_rows(
            $connection,
            'SELECT PackageID, SettingKey, ValueType, COALESCE(ValueJSON, \'\'),
                    COALESCE(SecretReference, \'\')
             FROM RED_Addon_Settings ORDER BY PackageID, SettingKey'
        ),
        'businessCounts' => [
            red_stripe_p3d7_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders'
            ),
            red_stripe_p3d7_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Status_History'
            ),
            red_stripe_p3d7_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Addon_StoreLite_Stripe_Checkout_Attempts'
            ),
            red_stripe_p3d7_scalar(
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

function red_stripe_p3d7_clear_secret_environment(): void
{
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');
}

try {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_stripe_p3d7_assert(
        red_stripe_p3d7_scalar($connection, 'SELECT DATABASE()')
            === $databaseName
            && red_stripe_p3d7_scalar(
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
    red_stripe_p3d7_assert(
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
        'Store Lite and adapter 0.1.3 are enabled and registry-current'
    );
    red_stripe_p3d7_assert(
        getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false,
        'rehearsal refuses to replace ambient secret configuration'
    );

    $unavailable = red_addon_adapter_invoke(
        $adapterId,
        'contract.probe',
        []
    );
    red_stripe_p3d7_assert(
        empty($unavailable['invoked'])
            && $unavailable['reason'] === 'adapter_unavailable',
        'typed invocation refuses before request-local bootstrap'
    );

    $syntheticApiValue = hash('sha256', random_bytes(32));
    $syntheticWebhookValue = hash('sha256', random_bytes(32));
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
    red_stripe_p3d7_assert(
        $environmentReady,
        'two random process-local values are available only to this rehearsal'
    );

    $before = red_stripe_p3d7_database_fingerprint($connection);
    $context = red_addon_runtime_request_bootstrap(
        $connection,
        $projectRoot
    );
    red_stripe_p3d7_assert(
        $context instanceof RED_Addon_Runtime_Context
            && $context->order() === [$storePackageId, $adapterPackageId]
            && red_addon_runtime_owner('adapters', $adapterId)
                === $adapterPackageId
            && red_addon_runtime_owner('routes', $adapterRouteId)
                === $adapterPackageId
            && red_addon_runtime_owner('services', $storeServiceId)
                === $storePackageId,
        'production bootstrap installs the exact isolated ownership context'
    );

    $adapterHandler = red_addon_runtime_handler('adapters', $adapterId);
    $routeHandler = red_addon_runtime_handler('routes', $adapterRouteId);
    red_stripe_p3d7_assert(
        $adapterHandler === [
            'RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter',
            'handle',
        ] && $routeHandler instanceof Closure,
        'runtime binds the reviewed typed adapter and still-inert event route'
    );

    $probe = red_addon_adapter_invoke(
        $adapterId,
        'contract.probe',
        []
    );
    red_stripe_p3d7_assert(
        $probe === [
            'invoked' => true,
            'success' => false,
            'adapter' => $adapterId,
            'package' => $adapterPackageId,
            'operation' => 'contract.probe',
            'data' => [],
            'error' => 'provider_transport_disabled',
            'reason' => 'adapter_error',
        ],
        'typed probe consumes configuration but keeps provider transport closed'
    );

    $wrongOperation = red_addon_adapter_invoke(
        $adapterId,
        'checkout.prepare',
        []
    );
    red_stripe_p3d7_assert(
        !empty($wrongOperation['invoked'])
            && $wrongOperation['error'] === 'unsupported_operation'
            && $wrongOperation['reason'] === 'adapter_error',
        'unreviewed checkout operations fail closed inside the typed boundary'
    );
    $unexpectedInput = red_addon_adapter_invoke(
        $adapterId,
        'contract.probe',
        ['unexpected' => true]
    );
    red_stripe_p3d7_assert(
        !empty($unexpectedInput['invoked'])
            && $unexpectedInput['error'] === 'unsupported_operation'
            && $unexpectedInput['reason'] === 'adapter_error',
        'the offline contract probe accepts no caller-supplied value'
    );

    $valueFreeEvidence = json_encode(
        [$probe, $wrongOperation, $unexpectedInput, $context->snapshot()],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_stripe_p3d7_assert(
        !str_contains($valueFreeEvidence, $syntheticApiValue)
            && !str_contains($valueFreeEvidence, $syntheticWebhookValue)
            && !str_contains($valueFreeEvidence, $apiReference)
            && !str_contains($valueFreeEvidence, $webhookReference),
        'typed results and runtime evidence disclose no value or reference'
    );
    red_stripe_p3d7_assert(
        hash_equals(
            $before,
            red_stripe_p3d7_database_fingerprint($connection)
        ) && red_stripe_p3d7_scalar(
            $connection,
            'SELECT CONCAT_WS(\':\',
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Orders),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Order_Status_History),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Stripe_Checkout_Attempts),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Stripe_Event_Receipts))'
        ) === '0:0:0:0',
        'all adapter, Store Lite, lifecycle, audit, and setting state is unchanged'
    );

    $handlerSource = (string) file_get_contents(
        $projectRoot
            . '/addons/redcms/store-lite-stripe-checkout/'
            . 'StripeTypedOfflineCheckoutAdapter.php'
    );
    foreach ([
        'curl_',
        'file_get_contents(',
        'fsockopen(',
        'stream_socket_client(',
        'PDO',
        'mysqli',
        'RED_CMS_Store_Lite_Payment_Event_Service',
        'red_addon_service_invoke(',
        'api.stripe.com',
        'checkout.stripe.com',
    ] as $forbiddenToken) {
        red_stripe_p3d7_assert(
            !str_contains($handlerSource, $forbiddenToken),
            $forbiddenToken . ' is absent from the typed offline handler'
        );
    }

    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_stripe_p3d7_assert(
        red_addon_runtime_current_context() === null
            && red_addon_runtime_owner('adapters', $adapterId) === null
            && red_addon_runtime_secret_access($adapterPackageId) === null,
        'request teardown removes adapter and secret ownership immediately'
    );

    $syntheticApiValue = null;
    $syntheticWebhookValue = null;
    unset($context);
    red_stripe_p3d7_clear_secret_environment();
    red_stripe_p3d7_assert(
        getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false
            && red_addon_adapter_invoke(
                $adapterId,
                'contract.probe',
                []
            )['reason'] === 'adapter_unavailable',
        'synthetic environment and invocation capability are absent after exit'
    );

    echo json_encode(
        [
            'ok' => true,
            'adapterVersion' => '0.1.3',
            'storeLiteVersion' => '0.1.35',
            'database' => $databaseName,
            'databaseSHA256' => $before,
            'operation' => 'contract.probe',
            'result' => 'provider_transport_disabled',
            'assertions' => $assertions,
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $syntheticApiValue = null;
    $syntheticWebhookValue = null;
    red_stripe_p3d7_clear_secret_environment();
    $db->close();
    exit(1);
}

unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
$syntheticApiValue = null;
$syntheticWebhookValue = null;
red_stripe_p3d7_clear_secret_environment();
$db->close();
exit(0);

?>
