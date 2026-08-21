<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
$coreDirectory = getenv('RED_CMS_CORE');
if (!is_string($coreDirectory) || $coreDirectory === '') {
    $coreDirectory = dirname($projectDirectory) . '/redcms v5.1';
}
$coreHelper = $coreDirectory
    . '/includes/addon_payment_adapter_registrar_helpers.php';
if (!is_file($coreHelper)) {
    throw new RuntimeException(
        'RED-CMS core registrar helper not found; set RED_CMS_CORE.'
    );
}
require_once $coreHelper;

$assertions = 0;
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-stripe-p3c4-'
    . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$packageId = 'redcms.store-lite-stripe-checkout';
$fixturePackage = $fixtureProject
    . '/addons/redcms/store-lite-stripe-checkout';

function red_stripe_p3c4_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3c4_copy_tree(string $source, string $target): void
{
    if (!is_dir($target)
        && !mkdir($target, 0700, true)
        && !is_dir($target)
    ) {
        throw new RuntimeException('Could not create fixture package.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $source,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        $relative = substr($entry->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($destination)
                && !mkdir($destination, 0700, true)
                && !is_dir($destination)
            ) {
                throw new RuntimeException('Could not copy fixture directory.');
            }
            continue;
        }
        if (!copy($entry->getPathname(), $destination)) {
            throw new RuntimeException('Could not copy fixture file.');
        }
    }
}

function red_stripe_p3c4_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function red_stripe_p3c4_database_plan(array $package): array
{
    $profile = red_addon_payment_adapter_profile($package['manifest']);
    $plan = red_addon_payment_adapter_database_result($package['id']);
    $plan['valid'] = true;
    $plan['databaseEvidenceReady'] = true;
    $plan['version'] = $package['manifest']['version'];
    $plan['currentState'] = 'installed_disabled';
    $plan['databaseSha256'] = hash('sha256', 'disposable-database');
    $plan['contractSha256'] = $profile['contractSha256'];
    $plan['baseEnablementSha256'] = hash('sha256', 'base-enablement');
    $plan['dependencyEvidenceSha256'] = hash('sha256', 'dependency');
    $plan['migrationEvidenceSha256'] = hash('sha256', 'migrations');
    $plan['tableEvidenceSha256'] = hash('sha256', 'tables');
    $plan['dependencyCount'] = 1;
    $plan['migrationCount'] = 2;
    $plan['tableCount'] = 2;
    $plan['innoDbTableCount'] = 2;
    foreach ([
        'adapterContract',
        'authorization',
        'trust',
        'registry',
        'dependencies',
        'capabilityNamespace',
        'routeNamespace',
        'migrations',
        'packageTables',
    ] as $gate) {
        $plan['gates'][$gate] = 'passed';
    }
    $plan['blockers'] = [
        ['code' => 'atomic_payment_adapter_enablement_required'],
        ['code' => 'registrar_validation_required'],
        ['code' => 'server_event_ingress_required'],
    ];
    $plan['planSha256'] =
        red_addon_payment_adapter_database_fingerprint($plan);
    return $plan;
}

final class RED_Stripe_P3C4_Refusal_Registry
{
    public array $adapters = [];
    public array $routes = [];

    public function registerAdapter(string $id, callable $handler): void
    {
        $this->adapters[$id] = $handler;
    }

    public function registerRoute(string $id, callable $handler): void
    {
        $this->routes[$id] = $handler;
    }
}

try {
    red_stripe_p3c4_copy_tree(
        $projectDirectory . '/package',
        $fixturePackage
    );

    $package = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    red_stripe_p3c4_assert(
        !empty($package['valid']) && ($package['errors'] ?? []) === [],
        'the package passes current RED-CMS discovery and integrity validation'
    );
    red_stripe_p3c4_assert(
        ($package['id'] ?? null) === $packageId
            && ($package['manifest']['version'] ?? null) === '0.1.7'
            && ($package['manifest']['type'] ?? null) === 'adapter',
        'manifest identity, version, and adapter type are exact'
    );

    $manifest = $package['manifest'];
    $profile = red_addon_payment_adapter_profile($manifest);
    red_stripe_p3c4_assert(
        red_addon_payment_adapter_profile_is_valid($profile)
            && !empty($profile['contractReady'])
            && !$profile['activationSupported'],
        'manifest matches the closed non-activating payment-adapter profile'
    );
    red_stripe_p3c4_assert(
        $profile['adapter'] === $packageId . '/checkout'
            && $profile['dependencyPackageId'] === 'redcms.store-lite'
            && $profile['serverEventRoute']
                === $packageId . '/provider-events'
            && $profile['serverEventPath']
                === '/addons/redcms/store-lite-stripe-checkout/provider-events'
            && $profile['outboundHost'] === 'api.stripe.com',
        'one adapter, Store Lite dependency, event route, and host are declared'
    );
    red_stripe_p3c4_assert(
        $profile['migrationCount'] === 2
            && $profile['ordinarySettingCount'] === 1
            && $profile['secretSettingCount'] === 2,
        'profile exposes two migrations and the exact bounded settings shape'
    );
    red_stripe_p3c4_assert(
        $manifest['permissions'] === []
            && $manifest['publicMutationContracts'] === []
            && $manifest['jobs'] === []
            && $manifest['assets'] === ['public' => [], 'admin' => []],
        'package requests no permissions, mutations, jobs, or assets'
    );
    red_stripe_p3c4_assert(
        !array_key_exists('default', $manifest['settings'][1])
            && !array_key_exists('default', $manifest['settings'][2])
            && $manifest['settings'][1]['type'] === 'secret-reference'
            && $manifest['settings'][2]['type'] === 'secret-reference',
        'secret settings declare references without values or defaults'
    );
    red_stripe_p3c4_assert(
        count($manifest['integrity']['files']) === 15
            && $manifest['integrity']['entrypoint'] === 'addon.php',
        'integrity inventory covers all fifteen payload files exactly once'
    );
    foreach ($manifest['integrity']['files'] as $inventoryFile) {
        $path = $fixturePackage . '/' . $inventoryFile['path'];
        red_stripe_p3c4_assert(
            is_file($path)
                && hash_equals(
                    $inventoryFile['sha256'],
                    hash_file('sha256', $path)
                ),
            $inventoryFile['path'] . ' matches the declared SHA-256'
        );
    }

    $databasePlan = red_stripe_p3c4_database_plan($package);
    red_stripe_p3c4_assert(
        red_addon_payment_adapter_database_preflight_is_valid($databasePlan)
            && !empty($databasePlan['databaseEvidenceReady'])
            && !$databasePlan['stateMutation']
            && !$databasePlan['packageExecution'],
        'synthetic prior-gate evidence remains valid and non-mutating'
    );

    $result = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_stripe_p3c4_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($result)
            && !empty($result['registrarEvidenceReady']),
        'RED-CMS validates the registrar in its contained request-local registry'
    );
    red_stripe_p3c4_assert(
        $result['packageExecutionAttempted']
            && $result['registrarExecutionCompleted']
            && !$result['handlerInvocation']
            && !$result['secretResolution']
            && !$result['networkAccess']
            && !$result['routeExposure']
            && !$result['stateMutation']
            && !$result['runtimePublication'],
        'registration proof executes no handler, secret, network, route, or state'
    );
    red_stripe_p3c4_assert(
        $result['adapter'] === $packageId . '/checkout'
            && $result['serverEventRoute']
                === $packageId . '/provider-events'
            && $result['registrationCount'] === 2
            && array_column($result['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
                'server_event_ingress_required',
            ],
        'only the two exact identifiers are observed and later gates stay blocked'
    );
    $repeat = red_addon_payment_adapter_validate_registrar(
        $package,
        $databasePlan
    );
    red_stripe_p3c4_assert(
        $repeat === $result,
        'unchanged registration-only evidence is deterministic'
    );

    $registrar = include $fixturePackage . '/addon.php';
    $registry = new RED_Stripe_P3C4_Refusal_Registry();
    red_stripe_p3c4_assert(
        is_callable($registrar),
        'entrypoint returns one registrar callable'
    );
    $registrar($registry);
    red_stripe_p3c4_assert(
        array_keys($registry->adapters) === [$packageId . '/checkout']
            && array_keys($registry->routes) === [
                $packageId . '/provider-events',
            ],
        'registrar emits only the declared adapter and route registrations'
    );
    $adapterHandler = $registry->adapters[$packageId . '/checkout'];
    red_stripe_p3c4_assert(
        $adapterHandler === [
            'RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter',
            'handle',
        ] && is_callable($adapterHandler),
        'adapter registration points only to the reviewed typed handler'
    );
    $routeRefused = false;
    try {
        $registry->routes[$packageId . '/provider-events']();
    } catch (LogicException $exception) {
        $routeRefused = $exception->getMessage()
            === 'p3c4_route_handler_not_operational';
    }
    red_stripe_p3c4_assert(
        $routeRefused,
        'provider-event route still refuses outside a later runtime gate'
    );

    $packageSource = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $fixturePackage,
            FilesystemIterator::SKIP_DOTS
        )
    );
    foreach ($iterator as $entry) {
        $packageSource .= (string) file_get_contents($entry->getPathname());
    }
    foreach ([
        'file_get_contents(',
        'PDO',
        'mysqli',
        'getenv(',
        'putenv(',
        'shell_exec(',
        'sk_test_',
        'sk_live_',
        'whsec_',
    ] as $forbiddenToken) {
        red_stripe_p3c4_assert(
            strpos($packageSource, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from the installable package payload'
        );
    }
    foreach ([
        'CURLOPT_HTTPGET', 'CURLAUTH_BASIC', 'CURLOPT_USERPWD',
        'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST',
        'CURLOPT_FOLLOWLOCATION', 'CURLOPT_CONNECTTIMEOUT_MS',
        'CURLOPT_TIMEOUT_MS', 'CURLOPT_FRESH_CONNECT',
        'CURLOPT_FORBID_REUSE',
    ] as $requiredTransportToken) {
        red_stripe_p3c4_assert(
            str_contains($packageSource, $requiredTransportToken),
            $requiredTransportToken
                . ' is present in the adopted read-only transport source'
        );
    }
    red_stripe_p3c4_assert(
        !str_contains($packageSource, 'CURLOPT_POST')
            && !str_contains($packageSource, 'CURLOPT_CUSTOMREQUEST')
            && !str_contains($packageSource, 'CURLOPT_POSTFIELDS'),
        'adopted package contains no mutation-capable request option'
    );
    foreach ([
        'composer.json',
        'composer.lock',
        'vendor',
        'secrets',
    ] as $forbiddenPath) {
        red_stripe_p3c4_assert(
            !file_exists($fixturePackage . '/' . $forbiddenPath),
            $forbiddenPath . ' remains absent from the package'
        );
    }

    echo 'P3C-4 registration-only package self-test passed: '
        . $assertions
        . " assertions.\n";
} finally {
    red_stripe_p3c4_remove_tree($temporaryRoot);
}
