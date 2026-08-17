<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectDirectory = dirname(__DIR__);
require_once $projectDirectory . '/src/StripeCheckoutResponseNormalizer.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportPlanner.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutTransportResponseGate.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutSealedTransportDouble.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutSealedExecutor.php';
require_once $projectDirectory . '/src/StripeBoundedJsonDecoder.php';
require_once $projectDirectory . '/src/StripeSandboxCheckoutWireCodec.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutDecodedTranscriptDouble.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutLoopbackHttpsTransport.php';
require_once $projectDirectory
    . '/src/StripeSandboxCheckoutLoopbackHttpsAdapter.php';

$assertions = 0;

function red_stripe_p3e5_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e5_checkout(): array
{
    return [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('a', 64),
        'paymentMethod' => 'stripe_checkout',
        'amountMinor' => 5897,
        'currency' => 'USD',
        'idempotencySha256' => str_repeat('b', 64),
        'lineItems' => [
            [
                'name' => 'Dog scarf - Small / Red',
                'quantity' => 2,
                'unitAmountMinor' => 1999,
                'lineTotalMinor' => 3998,
            ],
            [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ],
        ],
    ];
}

function red_stripe_p3e5_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
    ];
}

function red_stripe_p3e5_secret(): string
{
    return 'synthetic_p3e5_' . bin2hex(random_bytes(32));
}

function red_stripe_p3e5_commitment(string $secret): string
{
    return hash('sha256', 'Basic ' . base64_encode($secret . ':'));
}

function red_stripe_p3e5_certificate(): string
{
    return "-----BEGIN CERTIFICATE-----\n"
        . chunk_split(base64_encode(str_repeat('p3e5-certificate', 32)), 64)
        . "-----END CERTIFICATE-----\n";
}

function red_stripe_p3e5_transport(
    string $secret,
    ?string $commitment = null,
    string $origin = 'https://127.0.0.1:18443',
    ?string $certificate = null
): RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport {
    return new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport(
        $secret,
        $commitment ?? red_stripe_p3e5_commitment($secret),
        $origin,
        $certificate ?? red_stripe_p3e5_certificate()
    );
}

try {
    $source = '';
    foreach ([
        'src/StripeSandboxCheckoutLoopbackHttpsTransport.php',
        'src/StripeSandboxCheckoutLoopbackHttpsAdapter.php',
    ] as $relativePath) {
        $source .= (string) file_get_contents(
            $projectDirectory . '/' . $relativePath
        );
    }
    foreach ([
        'fsockopen(', 'file_get_contents(', 'fopen(', 'stream_', 'socket_',
        'PDO', 'mysqli', '$_SERVER', '$_POST', 'getenv(', 'putenv(',
        'shell_exec(', 'usleep(', 'sleep(', 'error_log(', 'print_r(',
        'var_dump(',
    ] as $forbiddenToken) {
        red_stripe_p3e5_assert(
            strpos($source, $forbiddenToken) === false,
            $forbiddenToken . ' is absent from loopback transport source'
        );
    }
    foreach ([
        '#[SensitiveParameter]',
        "base64_encode(\$secret . ':')",
        'CURLOPT_PROTOCOLS => CURLPROTO_HTTPS',
        'CURLOPT_FOLLOWLOCATION => false',
        "CURLOPT_PROXY => ''",
        "CURLOPT_NOPROXY => '*'",
        'CURLOPT_SSL_VERIFYPEER => true',
        'CURLOPT_SSL_VERIFYHOST => 2',
        'CURL_SSLVERSION_MAX_TLSv1_2',
        "'/v1/checkout/sessions'",
        "'https://api.stripe.com/v1/checkout/sessions'",
    ] as $requiredToken) {
        red_stripe_p3e5_assert(
            str_contains($source, $requiredToken),
            $requiredToken . ' is fixed in loopback transport source'
        );
    }
    red_stripe_p3e5_assert(
        count(get_included_files()) === 11,
        'fixture loads only itself and ten dependency-free local contracts'
    );
    red_stripe_p3e5_assert(
        (new ReflectionClass(
            RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport::class
        ))->isFinal(),
        'loopback transport cannot be extended'
    );

    $secret = red_stripe_p3e5_secret();
    $transport = red_stripe_p3e5_transport($secret);
    red_stripe_p3e5_assert(
        $transport->calls() === 0,
        'valid exact synthetic loopback configuration starts unused'
    );

    $invalidCheckout = red_stripe_p3e5_checkout();
    $invalidCheckout['amountMinor']++;
    $refused =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Adapter::execute(
            $invalidCheckout,
            red_stripe_p3e5_policy(),
            $transport
        );
    red_stripe_p3e5_assert(
        $refused === [
            'valid' => false,
            'status' => 'refused',
            'checkout' => null,
            'planSha256' => '',
            'responseEvidenceSha256' => '',
            'transportCode' => null,
            'retryAuthorized' => false,
            'errors' => ['checkout_plan_refused'],
        ] && $transport->calls() === 0,
        'invalid checkout is refused before cURL or credential use'
    );

    $encoded =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
            red_stripe_p3e5_checkout(),
            red_stripe_p3e5_policy()
        );
    red_stripe_p3e5_assert(
        $encoded['valid'] === true,
        'reviewed checkout produces the exact wire request fixture'
    );
    $forgedRequest = $encoded['wireRequest'];
    $forgedRequest['headers']['Authorization'] = 'forbidden';
    $forgedSecret = red_stripe_p3e5_secret();
    $forgedCommitment = red_stripe_p3e5_commitment($forgedSecret);
    $forged = red_stripe_p3e5_transport(
        $forgedSecret,
        $forgedCommitment
    );
    try {
        $forged->exchange($forgedRequest);
        $forgedRefused = false;
    } catch (Throwable $throwable) {
        $forgedRefused = true;
    }
    red_stripe_p3e5_assert(
        $forgedRefused
            && $forged->calls() === 1
            && !str_contains(serialize($forged), $forgedSecret)
            && !str_contains(serialize($forged), $forgedCommitment),
        'caller Authorization is refused and credential state is discarded'
    );
    try {
        $forged->exchange($encoded['wireRequest']);
        $reused = true;
    } catch (Throwable $throwable) {
        $reused = false;
    }
    red_stripe_p3e5_assert(
        $reused === false && $forged->calls() === 2,
        'refused transport cannot be reused'
    );

    $mismatchSecret = red_stripe_p3e5_secret();
    $mismatch = red_stripe_p3e5_transport(
        $mismatchSecret,
        str_repeat('d', 64)
    );
    try {
        $mismatch->exchange($encoded['wireRequest']);
        $mismatchRefused = false;
    } catch (Throwable $throwable) {
        $mismatchRefused = true;
    }
    red_stripe_p3e5_assert(
        $mismatchRefused
            && $mismatch->calls() === 1
            && !str_contains(serialize($mismatch), $mismatchSecret),
        'commitment mismatch fails before a connection and discards secret'
    );

    $certificate = red_stripe_p3e5_certificate();
    foreach ([
        ['', str_repeat('e', 64), 'https://127.0.0.1:18443', $certificate],
        ['sk_test_' . str_repeat('x', 64), str_repeat('e', 64), 'https://127.0.0.1:18443', $certificate],
        ['sk_live_' . str_repeat('x', 64), str_repeat('e', 64), 'https://127.0.0.1:18443', $certificate],
        [red_stripe_p3e5_secret(), 'invalid', 'https://127.0.0.1:18443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'http://127.0.0.1:18443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://localhost:18443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://[::1]:18443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://127.0.0.2:18443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://127.0.0.1:443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://127.0.0.1:70000', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://127.0.0.1:18443/path', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://user@127.0.0.1:18443', $certificate],
        [red_stripe_p3e5_secret(), str_repeat('e', 64), 'https://127.0.0.1:18443', 'not-a-certificate'],
    ] as [$badSecret, $badCommitment, $badOrigin, $badCertificate]) {
        try {
            new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport(
                $badSecret,
                $badCommitment,
                $badOrigin,
                $badCertificate
            );
            $configurationRefused = false;
        } catch (Throwable $throwable) {
            $configurationRefused = true;
        }
        red_stripe_p3e5_assert(
            $configurationRefused,
            'non-synthetic or non-loopback configuration is refused'
        );
    }

    echo 'Stripe P3E-5 loopback HTTPS transport contract passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
