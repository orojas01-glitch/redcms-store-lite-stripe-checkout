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
if (!is_file(
    $coreDirectory
        . '/includes/addon_sandbox_checkout_real_post_preflight_helpers.php'
)) {
    throw new RuntimeException('RED-CMS core not found; set RED_CMS_CORE.');
}
require_once $coreDirectory
    . '/includes/addon_sandbox_checkout_real_post_preflight_helpers.php';
require_once $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportPlanner.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportResponseGate.php';
require_once $projectDirectory . '/src/StripeBoundedJsonDecoder.php';
require_once $projectDirectory . '/src/StripeSandboxCheckoutWireCodec.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutCreationContract.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutRealPostPreflight.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutRealPostExchange.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutRealPostTransport.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutRealPostOperation.php';

$assertions = 0;

function red_stripe_p3e9d4a_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e9d4a_checkout(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'idempotencySha256' => str_repeat('b', 64),
        'lineItems' => [[
            'name' => 'Dog scarf - Small / Red',
            'quantity' => 2,
            'unitAmountMinor' => 1999,
            'lineTotalMinor' => 3998,
        ], [
            'name' => 'Delivery fee',
            'quantity' => 1,
            'unitAmountMinor' => 1899,
            'lineTotalMinor' => 1899,
        ]],
    ];
}

function red_stripe_p3e9d4a_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' => 'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
        'createdAtEpoch' => 1787025600,
        'expiresAtEpoch' => 1787027400,
    ];
}

function red_stripe_p3e9d4a_profile(): array
{
    return [
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'contractVersion' => 'p3e9a-v1',
        'operation' => 'checkout.create-sandbox',
        'contactTarget' => 'stripe-sandbox',
        'credentialMode' => 'restricted_test_write',
        'providerContact' => true,
        'providerMutation' => true,
        'checkoutCreation' => true,
        'payment' => false,
        'webhook' => false,
        'browserNavigation' => false,
        'orderMutation' => false,
        'clientDeployment' => false,
        'oneAttempt' => true,
        'automaticRetry' => false,
    ];
}

function red_stripe_p3e9d4a_projection(): array
{
    $session = 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx';
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/' . $session
            . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
        'mode' => 'payment',
        'status' => 'open',
        'payment_status' => 'unpaid',
        'amount_total' => 5897,
        'currency' => 'usd',
        'client_reference_id' =>
            'ord_0123456789abcdef0123456789abcdef',
        'metadata' => [
            'redcms_order_snapshot_sha256' => str_repeat('a', 64),
            'redcms_idempotency_sha256' => str_repeat('b', 64),
        ],
        'livemode' => false,
        'expires_at' => 1787027400,
        'after_expiration' => null,
    ];
}

function red_stripe_p3e9d4a_evidence(
    array $checkout,
    array $policy,
    array $profile,
    string $contractSha256
): array {
    $input = [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => $checkout,
        'policy' => $policy,
        'profile' => $profile,
        'contractSha256' => $contractSha256,
    ];
    $syntheticPlan = [
        'valid' => true,
        'ready' => true,
        'status' => 'ready',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.5',
        'adapterId' => 'redcms.store-lite-stripe-checkout/checkout',
        'operation' => 'checkout.create-sandbox-synthetic',
        'manifestSha256' => str_repeat('d', 64),
        'inventorySha256' => str_repeat('e', 64),
        'inputSha256' => red_addon_checkout_synthetic_hash($input),
        'planSha256' => str_repeat('f', 64),
        'adapterInvoked' => false,
        'boundedOutcome' => null,
        'outcomeSha256' => '',
        'executionPerformed' => false,
        'networkAccess' => false,
        'providerContact' => false,
        'providerMutation' => false,
        'checkoutCreation' => false,
        'payment' => false,
        'webhook' => false,
        'browserNavigation' => false,
        'orderMutation' => false,
        'retryAuthorized' => false,
        'clientDeployment' => false,
        'errors' => [],
    ];
    $preflight = red_addon_checkout_real_post_preflight(
        $syntheticPlan,
        $input
    );
    unset($preflight['formFields']);
    return [
        'input' => $input,
        'syntheticPlan' => $syntheticPlan,
        'preflight' => $preflight,
    ];
}

final class RED_Stripe_P3E9D4A_Exchange_Double
    implements RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Exchange
{
    private int $calls = 0;

    public function __construct(private string $mode = 'success')
    {
    }

    public function exchange(array $wireRequest): array
    {
        $this->calls++;
        if ($this->mode === 'throw') {
            throw new RuntimeException('synthetic_exchange_failure');
        }
        if (($wireRequest['method'] ?? null) !== 'POST'
            || ($wireRequest['url'] ?? null)
                !== 'https://api.stripe.com/v1/checkout/sessions'
            || !str_contains($wireRequest['body'] ?? '', 'expires_at=')
        ) {
            throw new RuntimeException('synthetic_wire_request_refused');
        }
        $projection = red_stripe_p3e9d4a_projection();
        if ($this->mode === 'live') {
            $projection['livemode'] = true;
        }
        return [
            'statusCode' => 200,
            'headers' => [[
                'name' => 'content-type',
                'value' => 'application/json; charset=utf-8',
            ], [
                'name' => 'request-id',
                'value' => 'req_AbCdEfGhIjKlMnOp',
            ]],
            'body' => json_encode(
                $projection,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            'tlsVersion' => 'TLSv1.2',
            'redirectCount' => 0,
        ];
    }

    public function calls(): int
    {
        return $this->calls;
    }
}

try {
    $transportSource = (string) file_get_contents(
        $projectDirectory . '/src/StripeSandboxCheckoutRealPostTransport.php'
    );
    $operationSource = (string) file_get_contents(
        $projectDirectory . '/src/StripeSandboxCheckoutRealPostOperation.php'
    );
    $handlerSource = (string) file_get_contents(
        $projectDirectory . '/package/StripeTypedOfflineCheckoutAdapter.php'
    );
    foreach ([
        'CURLOPT_POST', 'CURLOPT_POSTFIELDS', 'CURLOPT_HTTPAUTH',
        'CURLOPT_USERPWD', 'CURLOPT_PROTOCOLS', 'CURLPROTO_HTTPS',
        'CURLOPT_PROXY', "CURLOPT_PROXY => ''", 'CURLOPT_NOPROXY',
        'CURLOPT_FOLLOWLOCATION', 'CURLOPT_MAXREDIRS',
        'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST',
        'CURL_SSLVERSION_TLSv1_2', 'CURL_SSLVERSION_MAX_TLSv1_2',
        'CURLOPT_CONNECTTIMEOUT_MS', 'CURLOPT_TIMEOUT_MS',
        'CURLOPT_FRESH_CONNECT', 'CURLOPT_FORBID_REUSE',
        'CURLOPT_NOSIGNAL', 'CURLOPT_FAILONERROR',
    ] as $required) {
        red_stripe_p3e9d4a_assert(
            str_contains($transportSource, $required),
            $required . ' is fixed in the future one-use provider transport'
        );
    }
    foreach ([
        'getenv(', 'putenv(', '$_ENV', '$_SERVER', '$_POST', '$_GET',
        'php://input', 'PDO', 'mysqli', 'file_get_contents(', 'fopen(',
        'shell_exec(', 'passthru(', 'system(', 'sleep(', 'usleep(',
        'error_log(', 'print_r(', 'var_dump(', 'CURLOPT_CUSTOMREQUEST',
    ] as $forbidden) {
        red_stripe_p3e9d4a_assert(
            !str_contains($transportSource . $operationSource, $forbidden),
            $forbidden . ' is absent from the D4A operation sources'
        );
    }
    foreach (['sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'whsec_']
        as $literal
    ) {
        red_stripe_p3e9d4a_assert(
            !str_contains(
                $transportSource . $operationSource . $handlerSource,
                $literal
            ),
            $literal . ' credential literal is absent'
        );
    }
    red_stripe_p3e9d4a_assert(
        str_contains(
            $transportSource,
            "'https://api.stripe.com/v1/checkout/sessions'"
        )
            && substr_count(
                $transportSource,
                'https://api.stripe.com/v1/checkout/sessions'
            ) === 1,
        'provider transport fixes one exact Checkout Sessions endpoint'
    );
    red_stripe_p3e9d4a_assert(
        str_contains($handlerSource, "'checkout.create-sandbox-real-post'")
            && str_contains($handlerSource, "'stripe.secret-key'")
            && str_contains($handlerSource, "'stripe.webhook-secret'")
            && str_contains($handlerSource, 'real_post_secret_refused'),
        'typed operation resolves only the owning restricted key and refuses webhook access'
    );
    $handlerStart = strpos($handlerSource, 'private static function realPost(');
    $preflightPosition = strpos(
        $handlerSource,
        'Real_Post_Preflight::adopt(',
        is_int($handlerStart) ? $handlerStart : 0
    );
    $secretPosition = strpos(
        $handlerSource,
        "\$request->secret('stripe.secret-key'",
        is_int($handlerStart) ? $handlerStart : 0
    );
    red_stripe_p3e9d4a_assert(
        is_int($handlerStart)
            && is_int($preflightPosition)
            && is_int($secretPosition)
            && $handlerStart < $preflightPosition
            && $preflightPosition < $secretPosition,
        'handler revalidates the exact D1 preflight before secret access'
    );

    foreach ([
        'StripeBoundedJsonDecoder.php',
        'StripeSandboxCheckoutRealPostExchange.php',
        'StripeSandboxCheckoutRealPostTransport.php',
        'StripeSandboxCheckoutRealPostOperation.php',
    ] as $file) {
        red_stripe_p3e9d4a_assert(
            hash_equals(
                hash_file('sha256', $projectDirectory . '/src/' . $file),
                hash_file('sha256', $projectDirectory . '/package/' . $file)
            ),
            $file . ' source and package copy are byte-identical'
        );
    }

    $checkout = red_stripe_p3e9d4a_checkout();
    $policy = red_stripe_p3e9d4a_policy();
    $profile = red_stripe_p3e9d4a_profile();
    $contract =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract::prepare(
            $checkout,
            $policy,
            $profile
        );
    $evidence = red_stripe_p3e9d4a_evidence(
        $checkout,
        $policy,
        $profile,
        $contract['contractSha256']
    );
    $execution = [
        'planSha256' => str_repeat('1', 64),
        'claimStateSha256' => str_repeat('2', 64),
        'executionStartStateSha256' => str_repeat('3', 64),
    ];
    $adoptedPreflight =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Preflight::adopt(
            $checkout,
            $policy,
            $profile,
            $contract['contractSha256'],
            $evidence['preflight']
        );
    red_stripe_p3e9d4a_assert(
        ($adoptedPreflight['valid'] ?? null) === true
            && ($adoptedPreflight['packageVersion'] ?? null) === '0.1.8',
        'D4A retains exact 0.1.8 preflight adoption: '
            . json_encode($adoptedPreflight, JSON_UNESCAPED_SLASHES)
    );
    $double = new RED_Stripe_P3E9D4A_Exchange_Double();
    $created =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Operation::execute(
            $checkout,
            $policy,
            $profile,
            $contract['contractSha256'],
            $evidence['preflight'],
            $execution,
            $double
        );
    red_stripe_p3e9d4a_assert(
        $double->calls() === 1
            && ($created['valid'] ?? null) === true
            && ($created['status'] ?? null) === 'checkout_session_created'
            && ($created['packageVersion'] ?? null) === '0.1.8'
            && ($created['sourcePackageVersion'] ?? null) === '0.1.7'
            && ($created['operation'] ?? null)
                === 'checkout.create-sandbox-real-post'
            && ($created['execution'] ?? null) === $execution,
        'one sealed synthetic exchange yields the exact D4A operation result: '
            . json_encode($created, JSON_UNESCAPED_SLASHES)
    );
    red_stripe_p3e9d4a_assert(
        ($created['checkout'] ?? null) === [
            'checkoutSessionRef' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
            'checkoutUrlValidated' => true,
            'mode' => 'payment',
            'status' => 'open',
            'paymentStatus' => 'unpaid',
            'amountMinor' => 5897,
            'currency' => 'usd',
            'expiresAtEpoch' => 1787027400,
            'recoveryEnabled' => false,
            'livemode' => false,
        ]
            && red_addon_checkout_synthetic_sha256(
                $created['responseEvidenceSha256'] ?? null
            )
            && red_addon_checkout_synthetic_sha256(
                $created['resultSha256'] ?? null
            ),
        'created result retains only the bounded open unpaid non-live Session projection'
    );
    red_stripe_p3e9d4a_assert(
        ($created['credentialValueIncluded'] ?? null) === false
            && ($created['authorizationHeaderIncluded'] ?? null) === false
            && ($created['responseBodyIncluded'] ?? null) === false
            && ($created['responseHeadersIncluded'] ?? null) === false
            && ($created['checkoutUrlIncluded'] ?? null) === false
            && ($created['networkAccess'] ?? null) === true
            && ($created['providerContact'] ?? null) === true
            && ($created['providerMutation'] ?? null) === true
            && ($created['checkoutCreation'] ?? null) === true
            && ($created['payment'] ?? null) === false
            && ($created['webhook'] ?? null) === false
            && ($created['browserNavigation'] ?? null) === false
            && ($created['storeLiteMutation'] ?? null) === false
            && ($created['retryAuthorized'] ?? null) === false
            && ($created['liveMode'] ?? null) === false
            && ($created['clientDeployment'] ?? null) === false,
        'result models only the future creation effect and discloses no transport or credential material'
    );
    red_stripe_p3e9d4a_assert(
        !str_contains(
            json_encode($created, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'checkout.stripe.com'
        )
            && !array_key_exists('body', $created)
            && !array_key_exists('headers', $created),
        'Checkout URL, response body, and response headers do not escape the operation'
    );

    $changedPreflight = $evidence['preflight'];
    $changedPreflight['requestSha256'] = str_repeat('0', 64);
    $refusalDouble = new RED_Stripe_P3E9D4A_Exchange_Double();
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Operation::execute(
            $checkout,
            $policy,
            $profile,
            $contract['contractSha256'],
            $changedPreflight,
            $execution,
            $refusalDouble
        );
    red_stripe_p3e9d4a_assert(
        $refusalDouble->calls() === 0
            && ($refused['status'] ?? null) === 'refused'
            && empty($refused['executionPerformed'])
            && empty($refused['providerContact'])
            && empty($refused['checkoutCreation']),
        'changed preflight evidence is refused before the exchange boundary'
    );

    $throwing = new RED_Stripe_P3E9D4A_Exchange_Double('throw');
    $indeterminate =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Operation::execute(
            $checkout,
            $policy,
            $profile,
            $contract['contractSha256'],
            $evidence['preflight'],
            $execution,
            $throwing
        );
    red_stripe_p3e9d4a_assert(
        $throwing->calls() === 1
            && ($indeterminate['status'] ?? null) === 'indeterminate'
            && ($indeterminate['networkAccess'] ?? null) === true
            && ($indeterminate['providerContact'] ?? null) === true
            && ($indeterminate['providerMutation'] ?? null) === true
            && ($indeterminate['checkoutCreation'] ?? null) === true
            && ($indeterminate['checkout'] ?? null) === null
            && ($indeterminate['retryAuthorized'] ?? null) === false,
        'every post-boundary failure is conservatively indeterminate and permanently no-retry'
    );

    $liveDouble = new RED_Stripe_P3E9D4A_Exchange_Double('live');
    $liveRefused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Operation::execute(
            $checkout,
            $policy,
            $profile,
            $contract['contractSha256'],
            $evidence['preflight'],
            $execution,
            $liveDouble
        );
    red_stripe_p3e9d4a_assert(
        $liveDouble->calls() === 1
            && ($liveRefused['status'] ?? null) === 'indeterminate'
            && ($liveRefused['checkout'] ?? null) === null
            && ($liveRefused['liveMode'] ?? null) === false
            && ($liveRefused['retryAuthorized'] ?? null) === false,
        'live-mode provider output yields no accepted Session and no retry'
    );

    $reflection = new ReflectionClass(
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Transport::class
    );
    $wireValidator = $reflection->getMethod('wireRequest');
    $preparedRequest = $contract['contract']['request'];
    red_stripe_p3e9d4a_assert(
        $wireValidator->invoke(null, $preparedRequest) === true,
        'future provider transport accepts only the exact reviewed wire request'
    );
    foreach ([
        ['url', 'https://example.test/v1/checkout/sessions'],
        ['method', 'GET'],
        ['body', $preparedRequest['body'] . '&customer=forbidden'],
    ] as [$key, $value]) {
        $changed = $preparedRequest;
        $changed[$key] = $value;
        red_stripe_p3e9d4a_assert(
            $wireValidator->invoke(null, $changed) === false,
            'transport refuses changed wire field ' . $key
        );
    }
    $syntheticKey = 'rk_' . 'test_' . str_repeat('a', 32);
    $latentTransport =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Real_Post_Transport(
            $syntheticKey
        );
    $syntheticKey = '';
    red_stripe_p3e9d4a_assert(
        $latentTransport->calls() === 0,
        'provider transport remains constructed but uninvoked in D4A acceptance'
    );

    $manifest = json_decode(
        (string) file_get_contents($projectDirectory . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $identity = json_decode(
        (string) file_get_contents($projectDirectory . '/package/identity.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    red_stripe_p3e9d4a_assert(
        ($manifest['version'] ?? null) === '0.1.8'
            && ($identity['futureManifest']['version'] ?? null) === '0.1.8'
            && ($identity['status'] ?? null)
                === 'p3e9d4a_provider_write_operation_uninvoked'
            && count($manifest['integrity']['files'] ?? []) === 19,
        'manifest and identity advance to the exact uninvoked D4A package'
    );
    $inventoryPaths = [];
    foreach ($manifest['integrity']['files'] ?? [] as $entry) {
        $path = $entry['path'] ?? '';
        $inventoryPaths[] = $path;
        red_stripe_p3e9d4a_assert(
            is_string($path)
                && is_file($projectDirectory . '/package/' . $path)
                && hash_equals(
                    $entry['sha256'] ?? '',
                    hash_file('sha256', $projectDirectory . '/package/' . $path)
                ),
            'integrity hash matches ' . $path
        );
    }
    red_stripe_p3e9d4a_assert(
        count($inventoryPaths) === count(array_unique($inventoryPaths))
            && in_array('StripeBoundedJsonDecoder.php', $inventoryPaths, true)
            && in_array(
                'StripeSandboxCheckoutRealPostExchange.php',
                $inventoryPaths,
                true
            )
            && in_array(
                'StripeSandboxCheckoutRealPostTransport.php',
                $inventoryPaths,
                true
            )
            && in_array(
                'StripeSandboxCheckoutRealPostOperation.php',
                $inventoryPaths,
                true
            ),
        'nineteen-file inventory includes each D4A payload exactly once'
    );
    red_stripe_p3e9d4a_assert(
        ($manifest['migrations'] ?? null) === [[
            'id' => '2026-08-16-checkout-attempts',
            'path' => 'migrations/2026-08-16-create-checkout-attempts.sql',
            'sha256' =>
                'f58ae3b56d5b96d80f2757162e41e0fa4540f5e652934b9708e3884be633c2fa',
        ], [
            'id' => '2026-08-16-event-receipts',
            'path' => 'migrations/2026-08-16-create-event-receipts.sql',
            'sha256' =>
                '20b516693d15bf2fb3829de6d9c9fe44202af03b846a05262d0c79f2b0cd2b8d',
        ]],
        'D4A changes no migration path or checksum'
    );
    red_stripe_p3e9d4a_assert(
        ($manifest['dependencies']['required'] ?? null) === [[
            'id' => 'redcms.store-lite',
            'version' => '>=0.1.35 <1.0',
        ]]
            && ($manifest['permissions'] ?? null) === []
            && ($manifest['jobs'] ?? null) === []
            && ($manifest['publicMutationContracts'] ?? null) === []
            && ($manifest['outboundHosts'] ?? null) === ['api.stripe.com'],
        'dependency and all automatic/public execution surfaces remain closed'
    );

    echo 'P3E-9D4A provider-write operation self-test passed: '
        . $assertions . " assertions.\n";
    echo "No DNS, TLS, HTTP, Stripe, Checkout Session, payment, database, or client effect occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
