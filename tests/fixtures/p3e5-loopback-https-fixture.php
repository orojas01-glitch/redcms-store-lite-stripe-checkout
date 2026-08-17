<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', [
    'port:',
    'certificate:',
    'private-key:',
    'ready:',
    'evidence:',
    'expected-authorization-sha256:',
]);

function red_stripe_p3e5_fixture_fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function red_stripe_p3e5_fixture_exact_headers(array $headers): bool
{
    foreach ([
        'accept',
        'authorization',
        'content-length',
        'content-type',
        'host',
        'idempotency-key',
        'stripe-version',
    ] as $required) {
        if (!isset($headers[$required]) || count($headers[$required]) !== 1) {
            return false;
        }
    }
    return !isset($headers['transfer-encoding'], $headers['proxy-authorization']);
}

$portText = $options['port'] ?? '';
$certificate = $options['certificate'] ?? '';
$privateKey = $options['private-key'] ?? '';
$readyPath = $options['ready'] ?? '';
$evidencePath = $options['evidence'] ?? '';
$expectedAuthorizationSha256 =
    $options['expected-authorization-sha256'] ?? '';

if (!is_string($portText)
    || preg_match('/\A[1-9][0-9]{3,4}\z/D', $portText) !== 1
    || (int) $portText < 1024
    || (int) $portText > 65535
    || !is_string($certificate)
    || !is_file($certificate)
    || !is_string($privateKey)
    || !is_file($privateKey)
    || !is_string($readyPath)
    || $readyPath === ''
    || !is_string($evidencePath)
    || $evidencePath === ''
    || !is_string($expectedAuthorizationSha256)
    || preg_match(
        '/\A[a-f0-9]{64}\z/D',
        $expectedAuthorizationSha256
    ) !== 1
) {
    red_stripe_p3e5_fixture_fail('fixture_configuration_invalid');
}

$context = stream_context_create([
    'ssl' => [
        'local_cert' => $certificate,
        'local_pk' => $privateKey,
        'verify_peer' => false,
        'allow_self_signed' => true,
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER,
    ],
]);
$errno = 0;
$error = '';
$server = @stream_socket_server(
    'tls://127.0.0.1:' . $portText,
    $errno,
    $error,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);
if (!is_resource($server)) {
    red_stripe_p3e5_fixture_fail('fixture_bind_failed');
}
if (file_put_contents($readyPath, "ready\n", LOCK_EX) === false) {
    fclose($server);
    red_stripe_p3e5_fixture_fail('fixture_ready_failed');
}

$connection = @stream_socket_accept($server, 20);
if (!is_resource($connection)) {
    fclose($server);
    red_stripe_p3e5_fixture_fail('fixture_accept_failed');
}
stream_set_timeout($connection, 5);

try {
    $requestLine = fgets($connection, 8192);
    if (!is_string($requestLine)
        || $requestLine !== "POST /v1/checkout/sessions HTTP/1.1\r\n"
    ) {
        throw new RuntimeException('fixture_request_line_refused');
    }

    $headers = [];
    $headerBytes = strlen($requestLine);
    while (true) {
        $line = fgets($connection, 8192);
        if (!is_string($line)) {
            throw new RuntimeException('fixture_header_read_failed');
        }
        $headerBytes += strlen($line);
        if ($headerBytes > 16384) {
            throw new RuntimeException('fixture_headers_exceeded');
        }
        if ($line === "\r\n") {
            break;
        }
        if (!str_ends_with($line, "\r\n")) {
            throw new RuntimeException('fixture_header_line_refused');
        }
        $line = substr($line, 0, -2);
        $separator = strpos($line, ':');
        if ($separator === false) {
            throw new RuntimeException('fixture_header_line_refused');
        }
        $name = strtolower(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if (preg_match(
            '/\A[a-z0-9!#$%&\'*+.^_`|~-]{1,64}\z/D',
            $name
        ) !== 1
            || strlen($value) > 4096
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            throw new RuntimeException('fixture_header_line_refused');
        }
        $headers[$name][] = $value;
    }

    if (!red_stripe_p3e5_fixture_exact_headers($headers)
        || $headers['host'][0] !== '127.0.0.1:' . $portText
        || $headers['accept'][0] !== 'application/json'
        || $headers['content-type'][0]
            !== 'application/x-www-form-urlencoded'
        || preg_match(
            '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\.[a-z][a-z0-9_]{1,31}\z/D',
            $headers['stripe-version'][0]
        ) !== 1
        || preg_match(
            '/\Aredcms-checkout-[a-f0-9]{64}\z/D',
            $headers['idempotency-key'][0]
        ) !== 1
        || !hash_equals(
            $expectedAuthorizationSha256,
            hash('sha256', $headers['authorization'][0])
        )
        || preg_match('/\A[1-9][0-9]{0,5}\z/D', $headers['content-length'][0])
            !== 1
    ) {
        throw new RuntimeException('fixture_request_headers_refused');
    }

    $contentLength = (int) $headers['content-length'][0];
    if ($contentLength < 1 || $contentLength > 65536) {
        throw new RuntimeException('fixture_request_body_refused');
    }
    $body = '';
    while (strlen($body) < $contentLength) {
        $chunk = fread($connection, $contentLength - strlen($body));
        if (!is_string($chunk) || $chunk === '') {
            throw new RuntimeException('fixture_request_body_read_failed');
        }
        $body .= $chunk;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $body) === 1) {
        throw new RuntimeException('fixture_request_body_refused');
    }

    $metadata = stream_get_meta_data($connection);
    $tlsProtocol = $metadata['crypto']['protocol'] ?? null;
    if ($tlsProtocol !== 'TLSv1.2') {
        throw new RuntimeException('fixture_tls_version_refused');
    }

    $headerNames = array_keys($headers);
    sort($headerNames, SORT_STRING);
    $evidence = [
        'valid' => true,
        'method' => 'POST',
        'path' => '/v1/checkout/sessions',
        'sourceLoopback' => true,
        'tlsVersion' => 'TLSv1.2',
        'headerNames' => $headerNames,
        'authorizationSha256' => hash(
            'sha256',
            $headers['authorization'][0]
        ),
        'bodyBytes' => strlen($body),
        'bodySha256' => hash('sha256', $body),
    ];
    $encodedEvidence = json_encode(
        $evidence,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    if (file_put_contents($evidencePath, $encodedEvidence, LOCK_EX) === false) {
        throw new RuntimeException('fixture_evidence_write_failed');
    }

    $responseValue = [
        'id' => 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx',
        'object' => 'checkout.session',
        'amount_total' => 5897,
        'client_reference_id' =>
            'ord_0123456789abcdef0123456789abcdef',
        'currency' => 'usd',
        'livemode' => false,
        'metadata' => [
            'redcms_order_snapshot_sha256' => str_repeat('a', 64),
            'redcms_idempotency_sha256' => str_repeat('b', 64),
        ],
        'mode' => 'payment',
        'payment_status' => 'unpaid',
        'status' => 'open',
        'url' => 'https://checkout.stripe.com/c/pay/'
            . 'cs_test_AbCdEfGhIjKlMnOpQrStUvWx'
            . '#fidkdWxOYHwnPyd1blpxYHZxWjA0',
    ];
    $responseBody = json_encode(
        $responseValue,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $response = "HTTP/1.1 200 OK\r\n"
        . "Content-Type: application/json\r\n"
        . "Request-Id: req_P3E5Loopback123456\r\n"
        . 'X-Red-Fixture-Authorization-Sha256: '
        . $evidence['authorizationSha256'] . "\r\n"
        . 'X-Red-Fixture-Body-Sha256: '
        . $evidence['bodySha256'] . "\r\n"
        . 'Content-Length: ' . strlen($responseBody) . "\r\n"
        . "Connection: close\r\n\r\n"
        . $responseBody;
    $written = 0;
    while ($written < strlen($response)) {
        $bytes = fwrite($connection, substr($response, $written));
        if (!is_int($bytes) || $bytes < 1) {
            throw new RuntimeException('fixture_response_write_failed');
        }
        $written += $bytes;
    }
} catch (Throwable $throwable) {
    @file_put_contents(
        $evidencePath,
        json_encode([
            'valid' => false,
            'code' => $throwable->getMessage(),
        ], JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    fclose($connection);
    fclose($server);
    red_stripe_p3e5_fixture_fail('fixture_exchange_failed');
}

fclose($connection);
fclose($server);
echo "P3E-5 loopback fixture completed.\n";
