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

$options = getopt('', [
    'success-port:',
    'bad-ca-port:',
    'certificate:',
    'alternate-certificate:',
    'evidence:',
    'bad-ca-evidence:',
]);
$assertions = 0;

function red_stripe_p3e5_rehearsal_assert(
    bool $condition,
    string $message
): void {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_stripe_p3e5_rehearsal_checkout(): array
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

function red_stripe_p3e5_rehearsal_policy(): array
{
    return [
        'apiVersion' => '2024-09-30.acacia',
        'successUrl' =>
            'https://shop.example.test/checkout/stripe-complete',
        'cancelUrl' => 'https://shop.example.test/checkout',
    ];
}

try {
    $successPort = $options['success-port'] ?? '';
    $badCaPort = $options['bad-ca-port'] ?? '';
    $certificatePath = $options['certificate'] ?? '';
    $alternateCertificatePath = $options['alternate-certificate'] ?? '';
    $evidencePath = $options['evidence'] ?? '';
    $badCaEvidencePath = $options['bad-ca-evidence'] ?? '';
    $secret = getenv('RED_P3E5_SYNTHETIC_SECRET');
    $authorizationSha256 = getenv(
        'RED_P3E5_EXPECTED_AUTHORIZATION_SHA256'
    );
    if (!is_string($successPort)
        || preg_match('/\A[1-9][0-9]{3,4}\z/D', $successPort) !== 1
        || !is_string($badCaPort)
        || preg_match('/\A[1-9][0-9]{3,4}\z/D', $badCaPort) !== 1
        || !is_string($certificatePath)
        || !is_file($certificatePath)
        || !is_string($alternateCertificatePath)
        || !is_file($alternateCertificatePath)
        || !is_string($evidencePath)
        || $evidencePath === ''
        || !is_string($badCaEvidencePath)
        || $badCaEvidencePath === ''
        || !is_string($secret)
        || preg_match('/\Asynthetic_p3e5_[a-f0-9]{64}\z/D', $secret) !== 1
        || !is_string($authorizationSha256)
        || preg_match('/\A[a-f0-9]{64}\z/D', $authorizationSha256) !== 1
    ) {
        throw new RuntimeException('rehearsal_configuration_invalid');
    }
    $certificatePem = file_get_contents($certificatePath);
    $alternateCertificatePem = file_get_contents(
        $alternateCertificatePath
    );
    if (!is_string($certificatePem)
        || !is_string($alternateCertificatePem)
    ) {
        throw new RuntimeException('rehearsal_certificate_read_failed');
    }

    $checkout = red_stripe_p3e5_rehearsal_checkout();
    $policy = red_stripe_p3e5_rehearsal_policy();
    $encoded =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec::encode(
            $checkout,
            $policy
        );
    red_stripe_p3e5_rehearsal_assert(
        $encoded['valid'] === true,
        'reviewed checkout encodes before loopback contact'
    );

    $transport =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport(
            $secret,
            $authorizationSha256,
            'https://127.0.0.1:' . $successPort,
            $certificatePem
        );
    $result =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Adapter::execute(
            $checkout,
            $policy,
            $transport
        );
    red_stripe_p3e5_rehearsal_assert(
        $result['valid'] === true
            && $result['status'] === 'checkout_ready'
            && ($result['checkout']['checkoutSessionRef'] ?? null)
                === 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
            && ($result['checkout']['checkoutUrl'] ?? null)
                === 'https://checkout.stripe.com/c/pay/'
                    . 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
                    . '#fidkdWxOYHwnPyd1blpxYHZxWjA0'
            && $result['planSha256'] === $encoded['planSha256']
            && preg_match(
                '/\A[a-f0-9]{64}\z/D',
                $result['responseEvidenceSha256']
            ) === 1
            && $result['retryAuthorized'] === false
            && $result['errors'] === [],
        'verified TLS fixture traverses cURL, codec, and sealed executor'
    );
    red_stripe_p3e5_rehearsal_assert(
        $transport->calls() === 1,
        'successful HTTPS transport is invoked exactly once'
    );

    $evidenceJson = file_get_contents($evidencePath);
    $evidence = is_string($evidenceJson)
        ? json_decode($evidenceJson, true, 32, JSON_THROW_ON_ERROR)
        : null;
    red_stripe_p3e5_rehearsal_assert(
        is_array($evidence)
            && array_keys($evidence) === [
                'valid',
                'method',
                'path',
                'sourceLoopback',
                'tlsVersion',
                'headerNames',
                'authorizationSha256',
                'bodyBytes',
                'bodySha256',
            ]
            && $evidence['valid'] === true,
        'fixture persisted only the closed non-secret evidence shape'
    );
    red_stripe_p3e5_rehearsal_assert(
        $evidence['method'] === 'POST'
            && $evidence['path'] === '/v1/checkout/sessions'
            && $evidence['sourceLoopback'] === true
            && $evidence['tlsVersion'] === 'TLSv1.2',
        'fixture observed exact method, path, loopback source, and TLS 1.2'
    );
    red_stripe_p3e5_rehearsal_assert(
        $evidence['authorizationSha256'] === $authorizationSha256
            && $evidence['bodyBytes']
                === $encoded['wireRequest']['bodyBytes']
            && $evidence['bodySha256']
                === $encoded['wireRequest']['bodySha256'],
        'fixture observed committed authorization and exact canonical body'
    );
    red_stripe_p3e5_rehearsal_assert(
        in_array('authorization', $evidence['headerNames'], true)
            && in_array('idempotency-key', $evidence['headerNames'], true)
            && in_array('stripe-version', $evidence['headerNames'], true)
            && !in_array('proxy-authorization', $evidence['headerNames'], true),
        'required headers arrived without proxy authorization'
    );
    $serializedEvidence = serialize($evidence);
    $serializedResult = serialize($result);
    $serializedTransport = serialize($transport);
    red_stripe_p3e5_rehearsal_assert(
        !str_contains($serializedEvidence, $secret)
            && !str_contains($serializedEvidence, 'Basic ')
            && !str_contains(
                $serializedEvidence,
                $encoded['wireRequest']['body']
            )
            && !str_contains($serializedResult, $secret)
            && !str_contains($serializedResult, $authorizationSha256)
            && !str_contains($serializedTransport, $secret)
            && !str_contains($serializedTransport, $authorizationSha256),
        'credential, Basic value, and request body do not escape as evidence'
    );

    $badCaSecret = $secret;
    $badCaAuthorizationSha256 = $authorizationSha256;
    $badCaTransport =
        new RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport(
            $badCaSecret,
            $badCaAuthorizationSha256,
            'https://127.0.0.1:' . $badCaPort,
            $alternateCertificatePem
        );
    $badCaResult =
        RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Adapter::execute(
            $checkout,
            $policy,
            $badCaTransport
        );
    red_stripe_p3e5_rehearsal_assert(
        $badCaResult['valid'] === false
            && $badCaResult['status'] === 'indeterminate'
            && $badCaResult['checkout'] === null
            && $badCaResult['transportCode'] === 'transport_exception'
            && $badCaResult['retryAuthorized'] === false
            && $badCaResult['errors'] === ['transport_indeterminate']
            && $badCaTransport->calls() === 1,
        'untrusted certificate fails closed without retry'
    );
    red_stripe_p3e5_rehearsal_assert(
        !str_contains(serialize($badCaTransport), $badCaSecret)
            && !str_contains(
                serialize($badCaTransport),
                $badCaAuthorizationSha256
            )
            && !str_contains(serialize($badCaResult), $badCaSecret),
        'certificate failure still discards credential state'
    );
    red_stripe_p3e5_rehearsal_assert(
        !is_file($badCaEvidencePath),
        'untrusted certificate aborts before an HTTP request reaches fixture'
    );

    echo 'Stripe P3E-5 loopback HTTPS rehearsal passed '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
