#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
PHP_CLI=${PHP_CLI:-php}
OPENSSL_CLI=${OPENSSL_CLI:-openssl}
TEMP_ROOT=${TMPDIR:-/tmp}
TEMP_DIR=$(mktemp -d "$TEMP_ROOT/redcms-stripe-p3e5.XXXXXX")
SERVER_ONE_PID=''
SERVER_TWO_PID=''
CAFFEINATE_PID=''

cleanup() {
    if [ -n "$SERVER_ONE_PID" ] && kill -0 "$SERVER_ONE_PID" 2>/dev/null; then
        kill "$SERVER_ONE_PID" 2>/dev/null || true
        wait "$SERVER_ONE_PID" 2>/dev/null || true
    fi
    if [ -n "$SERVER_TWO_PID" ] && kill -0 "$SERVER_TWO_PID" 2>/dev/null; then
        kill "$SERVER_TWO_PID" 2>/dev/null || true
        wait "$SERVER_TWO_PID" 2>/dev/null || true
    fi
    if [ -n "$CAFFEINATE_PID" ] && kill -0 "$CAFFEINATE_PID" 2>/dev/null; then
        kill "$CAFFEINATE_PID" 2>/dev/null || true
        wait "$CAFFEINATE_PID" 2>/dev/null || true
    fi
    RED_P3E5_SYNTHETIC_SECRET=''
    RED_P3E5_EXPECTED_AUTHORIZATION_SHA256=''
    export RED_P3E5_SYNTHETIC_SECRET
    export RED_P3E5_EXPECTED_AUTHORIZATION_SHA256
    rm -rf -- "$TEMP_DIR"
}
trap cleanup EXIT HUP INT TERM

if ! command -v "$PHP_CLI" >/dev/null 2>&1 && [ ! -x "$PHP_CLI" ]; then
    echo "PHP CLI not found or not executable: $PHP_CLI" >&2
    exit 1
fi
if ! command -v "$OPENSSL_CLI" >/dev/null 2>&1; then
    echo "OpenSSL CLI not found: $OPENSSL_CLI" >&2
    exit 1
fi
if ! "$PHP_CLI" -m | grep -qx curl; then
    echo "PHP cURL extension is required." >&2
    exit 1
fi
if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    CAFFEINATE_PID=$!
    echo "Mac sleep prevention is active for this rehearsal only."
fi

PORT_ONE=$("$PHP_CLI" -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); if (!$s) exit(1); $n=stream_socket_get_name($s, false); fclose($s); echo substr($n, strrpos($n, ":") + 1);')
PORT_TWO=$("$PHP_CLI" -r '$s=stream_socket_server("tcp://127.0.0.1:0", $e, $m); if (!$s) exit(1); $n=stream_socket_get_name($s, false); fclose($s); echo substr($n, strrpos($n, ":") + 1);')
if [ "$PORT_ONE" = "$PORT_TWO" ]; then
    echo "Unable to allocate two distinct loopback ports." >&2
    exit 1
fi

CONFIG_PATH="$TEMP_DIR/openssl.cnf"
{
    echo '[req]'
    echo 'distinguished_name = dn'
    echo 'x509_extensions = v3'
    echo 'prompt = no'
    echo '[dn]'
    echo 'CN = 127.0.0.1'
    echo '[v3]'
    echo 'subjectAltName = IP:127.0.0.1'
    echo 'basicConstraints = critical,CA:TRUE'
    echo 'keyUsage = critical,digitalSignature,keyEncipherment,keyCertSign'
    echo 'extendedKeyUsage = serverAuth'
} > "$CONFIG_PATH"

"$OPENSSL_CLI" req -x509 -newkey rsa:2048 -nodes -sha256 -days 1 \
    -keyout "$TEMP_DIR/server.key" \
    -out "$TEMP_DIR/server.crt" \
    -config "$CONFIG_PATH" >/dev/null 2>&1
"$OPENSSL_CLI" req -x509 -newkey rsa:2048 -nodes -sha256 -days 1 \
    -keyout "$TEMP_DIR/alternate.key" \
    -out "$TEMP_DIR/alternate.crt" \
    -config "$CONFIG_PATH" >/dev/null 2>&1

RED_P3E5_SYNTHETIC_SECRET=$("$PHP_CLI" -r 'echo "synthetic_p3e5_", bin2hex(random_bytes(32));')
export RED_P3E5_SYNTHETIC_SECRET
RED_P3E5_EXPECTED_AUTHORIZATION_SHA256=$("$PHP_CLI" -r '$s=getenv("RED_P3E5_SYNTHETIC_SECRET"); echo hash("sha256", "Basic ".base64_encode($s.":"));')
export RED_P3E5_EXPECTED_AUTHORIZATION_SHA256

"$PHP_CLI" "$SCRIPT_DIR/fixtures/p3e5-loopback-https-fixture.php" \
    --port="$PORT_ONE" \
    --certificate="$TEMP_DIR/server.crt" \
    --private-key="$TEMP_DIR/server.key" \
    --ready="$TEMP_DIR/server-one.ready" \
    --evidence="$TEMP_DIR/server-one-evidence.json" \
    --expected-authorization-sha256="$RED_P3E5_EXPECTED_AUTHORIZATION_SHA256" \
    >"$TEMP_DIR/server-one.log" 2>&1 &
SERVER_ONE_PID=$!

"$PHP_CLI" "$SCRIPT_DIR/fixtures/p3e5-loopback-https-fixture.php" \
    --port="$PORT_TWO" \
    --certificate="$TEMP_DIR/server.crt" \
    --private-key="$TEMP_DIR/server.key" \
    --ready="$TEMP_DIR/server-two.ready" \
    --evidence="$TEMP_DIR/server-two-evidence.json" \
    --expected-authorization-sha256="$RED_P3E5_EXPECTED_AUTHORIZATION_SHA256" \
    >"$TEMP_DIR/server-two.log" 2>&1 &
SERVER_TWO_PID=$!

ATTEMPT=0
while [ ! -f "$TEMP_DIR/server-one.ready" ] || [ ! -f "$TEMP_DIR/server-two.ready" ]; do
    ATTEMPT=$((ATTEMPT + 1))
    if [ "$ATTEMPT" -ge 100 ]; then
        echo "Loopback fixtures did not become ready." >&2
        exit 1
    fi
    if ! kill -0 "$SERVER_ONE_PID" 2>/dev/null || ! kill -0 "$SERVER_TWO_PID" 2>/dev/null; then
        echo "A loopback fixture exited before readiness." >&2
        exit 1
    fi
    sleep 0.05
done

"$PHP_CLI" "$SCRIPT_DIR/p3e5-loopback-https-transport-rehearsal.php" \
    --success-port="$PORT_ONE" \
    --bad-ca-port="$PORT_TWO" \
    --certificate="$TEMP_DIR/server.crt" \
    --alternate-certificate="$TEMP_DIR/alternate.crt" \
    --evidence="$TEMP_DIR/server-one-evidence.json" \
    --bad-ca-evidence="$TEMP_DIR/server-two-evidence.json"

wait "$SERVER_ONE_PID"
SERVER_ONE_PID=''
wait "$SERVER_TWO_PID" 2>/dev/null || true
SERVER_TWO_PID=''

if grep -F "$RED_P3E5_SYNTHETIC_SECRET" "$TEMP_DIR"/* 2>/dev/null; then
    echo "Synthetic credential escaped into a rehearsal artifact." >&2
    exit 1
fi

cleanup
trap - EXIT HUP INT TERM
echo "Stripe P3E-5 loopback cleanup passed: process:0 temp:0 credential:absent provider:untouched"
