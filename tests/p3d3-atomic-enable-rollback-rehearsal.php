<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath((string) getenv('RED_STRIPE_REHEARSAL_PROJECT_ROOT'));
$databaseName = (string) getenv('RED_DB_NAME');
$rehearsalId = (string) getenv('RED_STRIPE_REHEARSAL_ID');
if (!in_array($rehearsalId, ['p3d3', 'p3d4'], true)) {
    $rehearsalId = 'p3d3';
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
        "Stripe $rehearsalLabel atomic rehearsal refused unsafe input.\n"
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

function red_stripe_p3d3_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3d3_scalar(
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

function red_stripe_p3d3_rows(
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

function red_stripe_p3d3_atomic_fingerprint(
    mysqli $connection,
    string $adapterPackageId
): string {
    $escapedPackageId = mysqli_real_escape_string(
        $connection,
        $adapterPackageId
    );
    $material = [
        'installations' => red_stripe_p3d3_rows(
            $connection,
            "SELECT PackageID, PackageVersion, ManifestSHA256,
                    InventorySHA256, LifecycleState,
                    COALESCE(UpdatedByAdminRecordID, 0)
             FROM RED_Addon_Installations
             WHERE PackageID IN (
                'redcms.store-lite', '$escapedPackageId'
             ) ORDER BY PackageID"
        ),
        'adapterActivity' => red_stripe_p3d3_rows(
            $connection,
            "SELECT EventName, PackageVersion, Result, DetailCode,
                    COALESCE(ActorAdminRecordID, 0)
             FROM RED_Addon_Activity_Log
             WHERE PackageID='$escapedPackageId'
             ORDER BY RecordID"
        ),
        'adapterSettings' => red_stripe_p3d3_rows(
            $connection,
            "SELECT SettingKey, ValueType, COALESCE(ValueJSON, ''),
                    COALESCE(SecretReference, '')
             FROM RED_Addon_Settings
             WHERE PackageID='$escapedPackageId'
             ORDER BY SettingKey"
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
    red_stripe_p3d3_assert(
        red_stripe_p3d3_scalar($connection, 'SELECT DATABASE()')
            === $databaseName,
        'connection is bound to the approved P3D-3 disposable database'
    );

    $catalog = red_addon_discover($projectRoot, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? null;
    red_stripe_p3d3_assert(
        !empty($catalog['valid'])
            && is_array($adapterPackage)
            && !empty($adapterPackage['valid'])
            && red_stripe_p3d3_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='$adapterPackageId'),
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='$storePackageId'),
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='$adapterPackageId'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='$adapterPackageId'
                       AND EventName='addon.enable.completed'))"
            ) === 'installed_disabled:enabled:3:0',
        'P3D-2 leaves the exact enable-ready adapter baseline'
    );

    $returnOrigin = 'https://checkout.p3d2.example.test';
    $apiReference = 'config:p3d2-placeholder-stripe-secret-key';
    $webhookReference = 'config:p3d2-placeholder-stripe-webhook-secret';
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference, $webhookReference],
        ''
    );
    $plan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    $encodedPlan = json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_stripe_p3d3_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($plan)
            && $plan['enableReady']
            && !str_contains($encodedPlan, $returnOrigin)
            && !str_contains($encodedPlan, $apiReference)
            && !str_contains($encodedPlan, $webhookReference),
        'fresh value-free evidence recreates the exact enable-ready plan'
    );

    $baselineFingerprint = red_stripe_p3d3_atomic_fingerprint(
        $connection,
        $adapterPackageId
    );
    $stale = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        hash('sha256', 'p3d3-stale-plan'),
        $declarations
    );
    red_stripe_p3d3_assert(
        $stale['status'] === 'plan_changed'
            && hash_equals(
                $baselineFingerprint,
                red_stripe_p3d3_atomic_fingerprint(
                    $connection,
                    $adapterPackageId
                )
            ),
        'stale plan fails before lifecycle or audit mutation'
    );

    $observedEnabledState = false;
    $afterStateFailure = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $plan['planSha256'],
        $declarations,
        null,
        static function (mysqli $lockedConnection) use (
            &$observedEnabledState,
            $adapterPackageId
        ): void {
            $observedEnabledState = red_stripe_p3d3_scalar(
                $lockedConnection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='$adapterPackageId'"
            ) === 'enabled';
            throw new RuntimeException('p3d3_forced_after_state_failure');
        }
    );
    red_stripe_p3d3_assert(
        $observedEnabledState
            && $afterStateFailure['status'] === 'enable_transaction_failed'
            && hash_equals(
                $baselineFingerprint,
                red_stripe_p3d3_atomic_fingerprint(
                    $connection,
                    $adapterPackageId
                )
            ),
        'failure after compare-and-swap restores the exact baseline facts'
    );

    $observedAuditRow = false;
    $auditFailure = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $plan['planSha256'],
        $declarations,
        static function (
            mysqli $lockedConnection,
            string $eventName,
            string $packageId,
            string $packageVersion,
            int $adminRecordId,
            string $result,
            string $detailCode
        ) use (&$observedAuditRow): bool {
            $written = red_addon_payment_adapter_enable_audit_record(
                $lockedConnection,
                $eventName,
                $packageId,
                $packageVersion,
                $adminRecordId,
                $result,
                $detailCode
            );
            $observedAuditRow = $written
                && red_stripe_p3d3_scalar(
                    $lockedConnection,
                    "SELECT CONCAT_WS(':',
                        (SELECT LifecycleState FROM RED_Addon_Installations
                         WHERE PackageID='$packageId'),
                        (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                         WHERE PackageID='$packageId'
                           AND EventName='addon.enable.completed'
                           AND DetailCode='payment_adapter_enabled'))"
                ) === 'enabled:1';
            return false;
        }
    );
    red_stripe_p3d3_assert(
        $observedAuditRow
            && $auditFailure['status'] === 'enable_transaction_failed'
            && hash_equals(
                $baselineFingerprint,
                red_stripe_p3d3_atomic_fingerprint(
                    $connection,
                    $adapterPackageId
                )
            ),
        'reported audit failure rolls back its real row and enabled state'
    );

    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    $encodedEnabled = json_encode(
        $enabled,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    red_stripe_p3d3_assert(
        $enabled['status'] === 'enabled'
            && $enabled['packageId'] === $adapterPackageId
            && $enabled['version'] === '0.1.0'
            && hash_equals(
                $plan['planSha256'],
                $enabled['planSha256']
            )
            && hash_equals(
                $plan['registrationSha256'],
                $enabled['registrationSha256']
            )
            && hash_equals(
                $plan['ingressContractSha256'],
                $enabled['ingressContractSha256']
            )
            && !str_contains($encodedEnabled, $returnOrigin)
            && !str_contains($encodedEnabled, $apiReference)
            && !str_contains($encodedEnabled, $webhookReference),
        'exact locked revalidation returns only value-free commit evidence'
    );
    red_stripe_p3d3_assert(
        red_stripe_p3d3_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'
                   AND EventName='addon.enable.completed'
                   AND Result='succeeded'
                   AND DetailCode='payment_adapter_enabled'))
             FROM RED_Addon_Installations
             WHERE PackageID='$adapterPackageId'"
        ) === 'enabled:1'
            && red_stripe_p3d3_rows(
                $connection,
                "SELECT EventName, PackageVersion, Result, DetailCode,
                        ActorAdminRecordID
                 FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'
                   AND EventName='addon.enable.completed'"
            ) === [[
                'addon.enable.completed',
                '0.1.0',
                'succeeded',
                'payment_adapter_enabled',
                '1',
            ]],
        'enabled state and one bounded audit fact commit atomically'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_enable_helpers.php'
    );
    red_stripe_p3d3_assert(
        red_addon_runtime_owner(
            'adapters',
            $adapterPackageId . '/checkout'
        ) === null
            && red_addon_runtime_owner(
                'routes',
                $adapterPackageId . '/provider-events'
            ) === null
            && preg_match(
                '/(?:\$_SERVER|\$_ENV|php:\/\/input|\bcurl_|\bfsockopen\s*\(|\bstream_socket_client\s*\(|red_addon_(?:runtime_)?secret_(?:resolve|access)|->handler\s*\(|\bheader\s*\(|\bhttp_response_code\s*\()/i',
                $helperSource
            ) !== 1,
        'commit publishes no runtime owner and has no request or provider path'
    );

    $committedFingerprint = red_stripe_p3d3_atomic_fingerprint(
        $connection,
        $adapterPackageId
    );
    $repeat = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $projectRoot,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_stripe_p3d3_assert(
        $repeat['status'] === 'database_payment_adapter_evidence_invalid'
            && hash_equals(
                $committedFingerprint,
                red_stripe_p3d3_atomic_fingerprint(
                    $connection,
                    $adapterPackageId
                )
            ),
        'enabled adapter refuses replay without a second audit or state drift'
    );

    echo json_encode(
        [
            'ok' => true,
            'adapterVersion' => '0.1.0',
            'database' => $databaseName,
            'enablementPlanSHA256' => $plan['planSha256'],
            'committedStateSHA256' => $committedFingerprint,
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
