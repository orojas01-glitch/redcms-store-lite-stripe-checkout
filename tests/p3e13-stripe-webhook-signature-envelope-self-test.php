<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/package/StripeBoundedJsonDecoder.php';
require_once $root . '/package/StripeSandboxWebhookSignatureEnvelope.php';

$assertions = 0;
$assert = static function (
    bool $condition,
    string $message
) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$secret = 'whsec_synthetic_signature_fixture_123456789';
$signedAt = 1787630500;
$receivedAt = 1787630550;
$event = [
    'id' => 'evt_SignatureEnvelope123456',
    'object' => 'event',
    'api_version' => '2024-09-30.acacia',
    'created' => 1787630400,
    'data' => [
        'object' => [
            'id' => 'cs_test_SignatureEnvelope123456',
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer_details' => [
                'email' => 'private@example.test',
            ],
        ],
    ],
    'livemode' => false,
    'pending_webhooks' => 1,
    'request' => ['id' => null, 'idempotency_key' => null],
    'type' => 'checkout.session.completed',
];
$body = json_encode(
    $event,
    JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
);
$sign = static fn (
    string $payload,
    string $currentSecret,
    int $timestamp
): string => hash_hmac(
    'sha256',
    $timestamp . '.' . $payload,
    $currentSecret
);
$header = 't=' . $signedAt . ',v1=' . $sign($body, $secret, $signedAt);

try {
    $source = (string) file_get_contents(
        $root . '/src/StripeSandboxWebhookSignatureEnvelope.php'
    );
    $assert(
        !preg_match(
            '/\$_(?:GET|POST|COOKIE|SERVER|SESSION|ENV)'
                . '|\b(?:mysqli|PDO|curl|fsockopen|getenv|setcookie|file_get_contents)\s*\('
                . '|\bheader\s*\(/',
            $source
        ),
        'signature envelope has no request, database, network, or response primitive'
    );
    $assert(
        hash_equals(
            hash_file(
                'sha256',
                $root . '/src/StripeSandboxWebhookSignatureEnvelope.php'
            ),
            hash_file(
                'sha256',
                $root . '/package/StripeSandboxWebhookSignatureEnvelope.php'
            )
        ),
        'source and adopted signature envelope are byte-identical'
    );

    $verified =
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify($body, $header, $secret, $receivedAt);
    $assert(
        ($verified['valid'] ?? null) === true
            && ($verified['verification'] ?? '') === 'verified'
            && ($verified['providerEnvironment'] ?? '') === 'sandbox'
            && ($verified['apiVersion'] ?? '') === '2024-09-30.acacia'
            && ($verified['eventType'] ?? '')
                === 'checkout.session.completed'
            && ($verified['objectType'] ?? '') === 'checkout.session',
        'valid v1 signature admits one fixed Sandbox event envelope'
    );

    $currentEvent = $event;
    $currentEvent['api_version'] = '2026-07-29.dahlia';
    $currentBody = json_encode(
        $currentEvent,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $currentHeader = 't=' . $signedAt . ',v1='
        . $sign($currentBody, $secret, $signedAt);
    $currentVerified =
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify($currentBody, $currentHeader, $secret, $receivedAt);
    $assert(
        ($currentVerified['valid'] ?? null) === true
            && ($currentVerified['apiVersion'] ?? '')
                === '2026-07-29.dahlia'
            && ($currentVerified['eventType'] ?? '')
                === 'checkout.session.completed',
        'current Dashboard Sandbox API version is accepted and retained'
    );
    $assert(
        ($verified['signedAt'] ?? 0) === $signedAt
            && ($verified['receivedAt'] ?? 0) === $receivedAt
            && ($verified['signatureAgeSeconds'] ?? 0) === 50
            && ($verified['signatureCount'] ?? 0) === 1
            && ($verified['bodyBytes'] ?? 0) === strlen($body)
            && ($verified['rawBodySha256'] ?? '') === hash('sha256', $body),
        'verification evidence binds exact raw bytes and bounded timing'
    );
    foreach ([
        'eventRefSha256', 'objectProjectionSha256',
        'signatureEvidenceSha256',
    ] as $key) {
        $assert(
            preg_match('/\A[a-f0-9]{64}\z/D', $verified[$key] ?? '') === 1,
            $key . ' is a valid SHA-256'
        );
    }
    $encoded = json_encode(
        $verified,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $assert(
        !str_contains($encoded, $secret)
            && !str_contains($encoded, $header)
            && !str_contains($encoded, $body)
            && !str_contains($encoded, 'private@example.test')
            && ($verified['rawBodyIncluded'] ?? true) === false
            && ($verified['signatureHeaderIncluded'] ?? true) === false
            && ($verified['endpointSecretIncluded'] ?? true) === false
            && ($verified['decodedEventIncluded'] ?? true) === false
            && ($verified['customerDataIncluded'] ?? true) === false,
        'result exposes no body, signature, endpoint secret, or decoded customer data'
    );
    $assert(
        !$verified['networkAccess']
            && !$verified['providerContact']
            && !$verified['routeExposure'],
        'verification performs no external or route effect'
    );

    $rotatedHeader = 't=' . $signedAt
        . ',v1=' . str_repeat('0', 64)
        . ',v0=' . str_repeat('a', 64)
        . ',v1=' . $sign($body, $secret, $signedAt);
    $rotated =
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify($body, $rotatedHeader, $secret, $receivedAt);
    $assert(
        ($rotated['valid'] ?? null) === true
            && ($rotated['signatureCount'] ?? 0) === 2,
        'one matching v1 signature is accepted during bounded secret rotation'
    );

    $altered = $body . "\n";
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify($altered, $header, $secret, $receivedAt)['valid'] === false,
        'any raw-body byte change invalidates the signature'
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $body,
                $header,
                'whsec_wrong_signature_fixture_123456789',
                $receivedAt
            )['valid'] === false,
        'wrong endpoint secret fails closed'
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify($body, $header, $secret, $signedAt + 301)['valid'] === false,
        'stale signature timestamp fails the five-minute tolerance'
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify($body, $header, $secret, $signedAt - 301)['valid'] === false,
        'far-future signature timestamp also fails closed'
    );

    foreach ([
        'v1=' . $sign($body, $secret, $signedAt),
        't=' . $signedAt . ',t=' . $signedAt
            . ',v1=' . $sign($body, $secret, $signedAt),
        't=' . $signedAt . ',v1=' . str_repeat('A', 64),
        't=' . $signedAt . ',v1=' . str_repeat('0', 64)
            . ',v1=' . str_repeat('0', 64),
        't=' . $signedAt . ', v1=' . $sign($body, $secret, $signedAt),
    ] as $invalidHeader) {
        $assert(
            RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
                verify(
                    $body,
                    $invalidHeader,
                    $secret,
                    $receivedAt
                )['valid'] === false,
            'malformed or ambiguous signature header fails closed'
        );
    }

    $liveEvent = $event;
    $liveEvent['livemode'] = true;
    $liveBody = json_encode(
        $liveEvent,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $liveHeader = 't=' . $signedAt . ',v1='
        . $sign($liveBody, $secret, $signedAt);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $liveBody,
                $liveHeader,
                $secret,
                $receivedAt
            )['valid'] === false,
        'validly signed live-mode event fails the Sandbox contract'
    );

    $wrongVersion = $event;
    $wrongVersion['api_version'] = '2024-06-20';
    $wrongVersionBody = json_encode(
        $wrongVersion,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $wrongVersionHeader = 't=' . $signedAt . ',v1='
        . $sign($wrongVersionBody, $secret, $signedAt);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $wrongVersionBody,
                $wrongVersionHeader,
                $secret,
                $receivedAt
            )['valid'] === false,
        'event API-version drift fails closed'
    );

    $wrongObject = $event;
    $wrongObject['data']['object']['object'] = 'invoice';
    $wrongObjectBody = json_encode(
        $wrongObject,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $wrongObjectHeader = 't=' . $signedAt . ',v1='
        . $sign($wrongObjectBody, $secret, $signedAt);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $wrongObjectBody,
                $wrongObjectHeader,
                $secret,
                $receivedAt
            )['valid'] === false,
        'event type and embedded Stripe object must agree'
    );

    $unsupported = $event;
    $unsupported['type'] = 'customer.created';
    $unsupported['data']['object']['object'] = 'customer';
    $unsupportedBody = json_encode(
        $unsupported,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $unsupportedHeader = 't=' . $signedAt . ',v1='
        . $sign($unsupportedBody, $secret, $signedAt);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $unsupportedBody,
                $unsupportedHeader,
                $secret,
                $receivedAt
            )['valid'] === false,
        'unallowlisted event type fails closed'
    );

    $duplicateBody = '{"id":"evt_Duplicate123456","id":"evt_Duplicate654321"}';
    $duplicateHeader = 't=' . $signedAt . ',v1='
        . $sign($duplicateBody, $secret, $signedAt);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $duplicateBody,
                $duplicateHeader,
                $secret,
                $receivedAt
            )['valid'] === false,
        'duplicate JSON keys fail after valid signature verification'
    );

    $futureCreated = $event;
    $futureCreated['created'] = $signedAt + 1;
    $futureBody = json_encode(
        $futureCreated,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $futureHeader = 't=' . $signedAt . ',v1='
        . $sign($futureBody, $secret, $signedAt);
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $futureBody,
                $futureHeader,
                $secret,
                $receivedAt
            )['valid'] === false,
        'event creation after its signature timestamp fails closed'
    );

    $oversized = '{"x":"' . str_repeat('a', 262140) . '"}';
    $assert(
        strlen($oversized) > 262144
            && RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
                verify(
                    $oversized,
                    't=' . $signedAt . ',v1=' . str_repeat('0', 64),
                    $secret,
                    $receivedAt
                )['valid'] === false,
        'oversized raw body fails before signature work'
    );
    $assert(
        RED_CMS_Store_Lite_Stripe_Sandbox_Webhook_Signature_Envelope::
            verify(
                $body,
                $header,
                'synthetic_secret_without_prefix',
                $receivedAt
            )['valid'] === false,
        'non-webhook credential fails before signature work'
    );

    $manifest = json_decode(
        (string) file_get_contents($root . '/package/addon.json'),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $entrypoint = (string) file_get_contents($root . '/package/addon.php');
    $assert(
        ($manifest['version'] ?? null) === '0.1.19'
            && count($manifest['integrity']['files'] ?? []) === 27
            && in_array(
                'StripeSandboxWebhookSignatureEnvelope.php',
                array_column(
                    $manifest['integrity']['files'] ?? [],
                    'path'
                ),
                true
            )
            && str_contains(
                $entrypoint,
                'StripeSandboxWebhookSignatureEnvelope'
            )
            && str_contains(
                $entrypoint,
                'p3c4_route_handler_not_operational'
            ),
        'adapter 0.1.19 inventories the verifier while retaining a non-operational route'
    );

    echo 'Stripe Sandbox signature envelope passed '
        . $assertions . " assertions.\n";
    echo "Only synthetic in-memory secrets and bodies were used; no request, route, network, Stripe, database, payment, or deployment action occurred.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
