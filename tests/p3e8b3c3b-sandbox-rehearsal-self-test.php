<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$shellPath = $projectRoot .
    '/tests/p3e8b3c3b-sandbox-rehearsal.sh';
$setupPath = $projectRoot .
    '/tests/p3e8b3c3b-sandbox-rehearsal-setup.php';
$shell = is_file($shellPath) ? (string) file_get_contents($shellPath) : '';
$setup = is_file($setupPath) ? (string) file_get_contents($setupPath) : '';
$assertions = 0;

function red_stripe_b3c3b_contract_assert(
    bool $condition,
    string $message
): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    red_stripe_b3c3b_contract_assert(
        $shell !== '' && $setup !== '',
        'separate wrapper and setup fixtures exist'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, "adapter_version\" != '0.1.4'")
            && str_contains($shell, "store_version\" != '0.1.35'")
            && str_contains($setup, "!== '0.1.35'")
            && str_contains($setup, "!== '0.1.4'"),
        'rehearsal pins exact Store Lite and adapter versions twice'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, "YES_ONE_READ_ONLY_GET")
            && str_contains($setup, "YES_ONE_READ_ONLY_GET")
            && str_contains($shell, 'EXECUTION_REQUESTED=0'),
        'external execution requires the exact one-shot token'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, 'Preflight refuses ambient secret values')
            && str_contains($setup, 'Preflight refuses ambient secret values')
            && str_contains($shell, 'unset RED_ADDON_SECRET_VALUES_JSON'),
        'preflight removes and refuses credential values'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($setup, "array_keys(\$secretValues) !== [\$apiReference]")
            && str_contains(
                $setup,
                "'/\\Ark_test_[A-Za-z0-9_]{16,256}\\z/D'"
            )
            && !str_contains($setup, 'sk_test_')
            && !str_contains($setup, 'sk_live_')
            && !str_contains($setup, 'rk_live_'),
        'execution accepts only one restricted test key shape'
    );
    red_stripe_b3c3b_contract_assert(
        !str_contains($shell, '--secret-key=')
            && !str_contains($setup, '--secret-key=')
            && !str_contains($shell, 'echo "$RED_ADDON_SECRET_VALUES_JSON"')
            && !str_contains($setup, 'echo $secretValuesJson'),
        'no key argument or value output exists'
    );
    foreach ([
        '--confirm-database=', '--confirm-package=', '--confirm-version=',
        '--confirm-state=', '--confirm-plan-sha256=',
        '--confirm-authorization-sha256=',
        '--confirm-claim-state-sha256=',
        '--confirm-execution-start-sha256=',
        '--confirm-secret-availability-sha256=',
        '--confirm-backup-sha256=', '--confirm-operation=',
        '--confirm-target=', '--confirm-credential-mode=',
        '--confirm-maximum-attempts=', '--confirm-retry-authorized=',
        '--confirm-mutation-authorized=', '--apply',
    ] as $confirmation) {
        red_stripe_b3c3b_contract_assert(
            str_contains($shell, $confirmation),
            $confirmation . ' is passed to the merged operator command'
        );
    }
    red_stripe_b3c3b_contract_assert(
        substr_count($shell, "'--apply'") === 0
            && substr_count($shell, '--apply') === 1
            && str_contains(
                $shell,
                'provider-contact.read-only-probe-sandbox'
            )
            && str_contains($shell, 'stripe-sandbox'),
        'wrapper has one exact apply call for one exact operation and target'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, 'CREATE DATABASE')
            && str_contains($shell, 'REVOKE ALL PRIVILEGES')
            && str_contains($shell, 'DROP DATABASE IF EXISTS')
            && str_contains($shell, 'database:0 grant:0')
            && str_contains($shell, 'primary:unchanged'),
        'wrapper owns disposable database, grant, and primary isolation'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, "--exclude='addons'")
            && str_contains($shell, "--exclude='includes/config.local.php'")
            && str_contains($shell, 'staged-project:0'),
        'staging excludes client state and proves exact cleanup'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, '--single-transaction')
            && str_contains($shell, 'pre-contact.sql')
            && str_contains($shell, 'BACKUP_SHA256')
            && str_contains($shell, 'shasum -a 256'),
        'one verified pre-contact database backup is required'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($shell, 'Execution requires one new absolute evidence directory')
            && str_contains($shell, 'SHA256SUMS')
            && str_contains($shell, 'chmod 700')
            && str_contains($shell, 'chmod 600'),
        'execution evidence uses one new private checksummed directory'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($setup, "modify('+10 minutes')")
            && str_contains($setup, 'random_bytes(32)')
            && str_contains($setup, 'maximumAttempts')
            && str_contains($setup, "'retryAuthorized' => false")
            && str_contains($setup, "'mutationAuthorized' => false"),
        'fresh evidence is short-lived, nonce-bound, one-shot, and non-mutating'
    );
    red_stripe_b3c3b_contract_assert(
        str_contains($setup, 'red_addon_provider_contact_authorize(')
            && str_contains($setup, 'red_addon_provider_contact_claim(')
            && str_contains(
                $setup,
                'red_addon_provider_contact_sandbox_execution_plan('
            )
            && !str_contains(
                $setup,
                'red_addon_provider_contact_execute_sandbox('
            ),
        'setup authorizes, claims, and plans without executing the runner'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'Authorization:', 'php://input', '$_POST', 'shell_exec(',
        'passthru(', 'sleep(', 'usleep(',
    ] as $forbidden) {
        red_stripe_b3c3b_contract_assert(
            !str_contains($shell, $forbidden)
                && !str_contains($setup, $forbidden),
            $forbidden . ' is absent from the orchestration fixtures'
        );
    }
    red_stripe_b3c3b_contract_assert(
        !str_contains($shell, 'demo.red-sphere.com')
            && !str_contains($setup, 'demo.red-sphere.com')
            && !str_contains(strtolower($shell . $setup), 'adriana')
            && !str_contains($shell, 'public_html'),
        'no hosted or client installation is named or targeted'
    );

    echo 'Stripe P3E-8B3C3B sandbox rehearsal contract passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
