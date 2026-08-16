#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
PHP_CLI=${PHP_CLI:-php}

if ! command -v "$PHP_CLI" >/dev/null 2>&1 && [ ! -x "$PHP_CLI" ]; then
    echo "PHP CLI not found or not executable: $PHP_CLI" >&2
    exit 1
fi

"$PHP_CLI" -l "$PROJECT_DIR/src/StripeCheckoutResponseNormalizer.php"
"$PHP_CLI" -l "$PROJECT_DIR/src/StripeVerifiedEventNormalizer.php"
"$PHP_CLI" -l "$PROJECT_DIR/src/StripeCheckoutAttemptRecordPlanner.php"
"$PHP_CLI" -l "$PROJECT_DIR/src/StripeEventReceiptRecordPlanner.php"
"$PHP_CLI" -l "$PROJECT_DIR/package/addon.php"
"$PHP_CLI" -l "$PROJECT_DIR/tests/p3c1-foundation-self-test.php"
"$PHP_CLI" -l "$PROJECT_DIR/tests/p3c2-checkout-attempt-storage-self-test.php"
"$PHP_CLI" -l "$PROJECT_DIR/tests/p3c3-event-replay-storage-self-test.php"
"$PHP_CLI" -l "$PROJECT_DIR/tests/p3c4-registration-only-package-self-test.php"
"$PHP_CLI" -l "$PROJECT_DIR/tests/p3d1-install-disabled-rehearsal.php"
"$PHP_CLI" "$PROJECT_DIR/tests/p3c1-foundation-self-test.php"
"$PHP_CLI" "$PROJECT_DIR/tests/p3c2-checkout-attempt-storage-self-test.php"
"$PHP_CLI" "$PROJECT_DIR/tests/p3c3-event-replay-storage-self-test.php"
"$PHP_CLI" "$PROJECT_DIR/tests/p3c4-registration-only-package-self-test.php"
