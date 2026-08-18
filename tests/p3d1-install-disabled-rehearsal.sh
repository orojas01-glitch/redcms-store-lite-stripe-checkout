#!/bin/bash

set -euo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ADAPTER_REPOSITORY="$(cd "$TEST_DIR/.." && pwd)"
RED_CMS_CORE_ROOT="${RED_CMS_CORE_ROOT:-$(dirname "$ADAPTER_REPOSITORY")/redcms v5.1}"
STORE_LITE_REPOSITORY="${STORE_LITE_REPOSITORY:-$(dirname "$ADAPTER_REPOSITORY")/redcms-store-lite}"
if [[ ! -f "$RED_CMS_CORE_ROOT/scripts/db-common.sh" ]]; then
    printf 'RED-CMS core checkout is unavailable: %s\n' "$RED_CMS_CORE_ROOT" >&2
    exit 66
fi

# shellcheck source=/dev/null
source "$RED_CMS_CORE_ROOT/scripts/db-common.sh"

FRANKENPHP_BIN="${FRANKENPHP_BIN:-/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
REHEARSAL_ID="${RED_STRIPE_REHEARSAL_ID:-p3d1}"
REHEARSAL_FIXTURES=()
case "$REHEARSAL_ID" in
    p3d1)
        REHEARSAL_LABEL='P3D-1'
        REHEARSAL_FIXTURES=(
            "$TEST_DIR/p3d1-install-disabled-rehearsal.php"
        )
        REHEARSAL_DATABASE="${RED_STRIPE_REHEARSAL_DATABASE:-${RED_STRIPE_P3D1_DATABASE:-redcms_stripe_p3d1_$(date +%s)_$$}}"
        ;;
    p3d2)
        REHEARSAL_LABEL='P3D-2'
        REHEARSAL_FIXTURES=(
            "$TEST_DIR/p3d2-enable-dry-run-rehearsal.php"
        )
        REHEARSAL_DATABASE="${RED_STRIPE_REHEARSAL_DATABASE:-redcms_stripe_p3d2_$(date +%s)_$$}"
        ;;
    p3d3)
        REHEARSAL_LABEL='P3D-3'
        REHEARSAL_FIXTURES=(
            "$TEST_DIR/p3d2-enable-dry-run-rehearsal.php"
            "$TEST_DIR/p3d3-atomic-enable-rollback-rehearsal.php"
        )
        REHEARSAL_DATABASE="${RED_STRIPE_REHEARSAL_DATABASE:-redcms_stripe_p3d3_$(date +%s)_$$}"
        ;;
    p3d4)
        REHEARSAL_LABEL='P3D-4'
        REHEARSAL_FIXTURES=(
            "$TEST_DIR/p3d2-enable-dry-run-rehearsal.php"
            "$TEST_DIR/p3d3-atomic-enable-rollback-rehearsal.php"
            "$TEST_DIR/p3d4-runtime-service-binding-rehearsal.php"
        )
        REHEARSAL_DATABASE="${RED_STRIPE_REHEARSAL_DATABASE:-redcms_stripe_p3d4_$(date +%s)_$$}"
        ;;
    p3d5)
        REHEARSAL_LABEL='P3D-5'
        REHEARSAL_FIXTURES=(
            "$TEST_DIR/p3d2-enable-dry-run-rehearsal.php"
            "$TEST_DIR/p3d3-atomic-enable-rollback-rehearsal.php"
            "$TEST_DIR/p3d5-synthetic-secret-bootstrap-rehearsal.php"
        )
        REHEARSAL_DATABASE="${RED_STRIPE_REHEARSAL_DATABASE:-redcms_stripe_p3d5_$(date +%s)_$$}"
        ;;
    p3d7)
        REHEARSAL_LABEL='P3D-7'
        REHEARSAL_FIXTURES=(
            "$TEST_DIR/p3d2-enable-dry-run-rehearsal.php"
            "$TEST_DIR/p3d3-atomic-enable-rollback-rehearsal.php"
            "$TEST_DIR/p3d7-typed-offline-adapter-invocation-rehearsal.php"
        )
        REHEARSAL_DATABASE="${RED_STRIPE_REHEARSAL_DATABASE:-redcms_stripe_p3d7_$(date +%s)_$$}"
        ;;
    *)
        printf 'Unsupported Stripe rehearsal id: %s\n' "$REHEARSAL_ID" >&2
        exit 64
        ;;
esac
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
DATABASE_CREATED=0
GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
KEEP_AWAKE_PID=0

red_stripe_p3d1_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_stripe_p3d1_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_stripe_p3d1_primary_snapshot() {
    "$RED_MYSQLDUMP_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --single-transaction \
        --skip-lock-tables \
        --no-tablespaces \
        --skip-comments \
        --compact \
        --hex-blob \
        "$RED_DB_NAME_RESOLVED" \
        | shasum -a 256 \
        | awk '{print $1}'
}

red_stripe_p3d1_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""
    local process_count=0

    trap - EXIT INT TERM
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_stripe_p3d1_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_stripe_p3d1_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$REHEARSAL_DATABASE\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_stripe_p3d1_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
        " 2>/dev/null)"
        grant_output="$(red_stripe_p3d1_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$REHEARSAL_DATABASE\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: disposable database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(red_stripe_p3d1_primary_snapshot 2>/dev/null)"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary database changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-stripe-$REHEARSAL_ID."*
        && -d "$TEMP_ROOT"
    ]]; then
        rm -rf -- "$TEMP_ROOT"
        if [[ -e "$TEMP_ROOT" ]]; then
            printf '%s\n' 'Cleanup failure: staged project remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE" && -f "$ADMIN_DEFAULTS_FILE" ]]; then
        rm -f -- "$ADMIN_DEFAULTS_FILE"
    fi
    red_remove_defaults_file
    if [[ "$KEEP_AWAKE_PID" -gt 0 ]]; then
        kill -TERM "$KEEP_AWAKE_PID" >/dev/null 2>&1 || true
        wait "$KEEP_AWAKE_PID" >/dev/null 2>&1 || true
        if kill -0 "$KEEP_AWAKE_PID" >/dev/null 2>&1; then
            process_count=1
            cleanup_status=1
        fi
    fi

    if [[ "$cleanup_status" -eq 0
        && "$DATABASE_CREATED" -eq 1
        && "$GRANT_CREATED" -eq 1
    ]]; then
        printf 'Stripe %s cleanup passed: database:0 grant:0 staged-project:0 process:%s primary:unchanged\n' "$REHEARSAL_LABEL" "$process_count"
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_stripe_p3d1_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ $# -ne 0 ]]; then
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi
if [[ ! "$REHEARSAL_DATABASE" =~ ^redcms_stripe_${REHEARSAL_ID}_[A-Za-z0-9_]+$
    || ${#REHEARSAL_DATABASE} -gt 64
    || "$REHEARSAL_DATABASE" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe Stripe %s database name: %s\n' "$REHEARSAL_LABEL" "$REHEARSAL_DATABASE" >&2
    exit 64
fi
if [[ ! -x "$FRANKENPHP_BIN"
    || ! -s "$ADAPTER_REPOSITORY/package/addon.json"
    || ! -s "$STORE_LITE_REPOSITORY/package/addon.json"
]]; then
    printf '%s\n' 'Adapter, Store Lite, or rehearsal fixture is unavailable.' >&2
    exit 66
fi
for rehearsal_fixture in "${REHEARSAL_FIXTURES[@]}"; do
    if [[ ! -s "$rehearsal_fixture" ]]; then
        printf 'Rehearsal fixture is unavailable: %s\n' \
            "$rehearsal_fixture" >&2
        exit 66
    fi
done
if [[ -e "$RED_CMS_CORE_ROOT/addons" ]]; then
    printf '%s\n' 'Clean RED-CMS checkout unexpectedly contains an addons directory.' >&2
    exit 65
fi

adapter_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$ADAPTER_REPOSITORY/package/addon.json")"
store_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$STORE_LITE_REPOSITORY/package/addon.json")"
if [[ "$adapter_version" != '0.1.4' || "$store_version" != '0.1.35' ]]; then
    printf '%s requires adapter 0.1.4 and Store Lite 0.1.35; found %s and %s.\n' \
        "$REHEARSAL_LABEL" "$adapter_version" "$store_version" >&2
    exit 65
fi

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    KEEP_AWAKE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for this rehearsal only.'
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-stripe-$REHEARSAL_ID.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout"
rsync -a \
    --exclude='.git' \
    --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_CMS_CORE_ROOT/" "$STAGED_PROJECT/"
rsync -a \
    "$STORE_LITE_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"
rsync -a \
    "$ADAPTER_REPOSITORY/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout/"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-stripe-$REHEARSAL_ID-admin.XXXXXX")"
chmod 600 "$ADMIN_DEFAULTS_FILE"
{
    printf '[client]\n'
    printf 'protocol=tcp\n'
    printf 'host=%s\n' "$RED_DB_HOST_RESOLVED"
    printf 'port=%s\n' "$RED_DB_PORT_RESOLVED"
    printf 'user=%s\n' "${RED_ACCEPTANCE_DB_ADMIN_USER:-root}"
    printf 'password=%s\n' "${RED_ACCEPTANCE_DB_ADMIN_PASS:-}"
    printf 'default-character-set=utf8mb4\n'
} > "$ADMIN_DEFAULTS_FILE"
red_stripe_p3d1_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_stripe_p3d1_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application database account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_stripe_p3d1_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$REHEARSAL_DATABASE';
")"
if [[ "$database_count" != '0' ]]; then
    printf 'Refusing to reuse database: %s\n' "$REHEARSAL_DATABASE" >&2
    exit 65
fi

PRIMARY_SNAPSHOT_BEFORE="$(red_stripe_p3d1_primary_snapshot)"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
    printf '%s\n' 'Could not fingerprint the configured primary database.' >&2
    exit 67
fi

red_stripe_p3d1_admin_mysql --execute="
    CREATE DATABASE \`$REHEARSAL_DATABASE\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_stripe_p3d1_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$REHEARSAL_DATABASE\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1

printf 'Preparing fresh %s project database: %s\n' "$REHEARSAL_LABEL" "$REHEARSAL_DATABASE"
"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$REHEARSAL_DATABASE" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$REHEARSAL_DATABASE" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" \
    "--database=$REHEARSAL_DATABASE"

for rehearsal_fixture in "${REHEARSAL_FIXTURES[@]}"; do
    RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$REHEARSAL_DATABASE" \
    RED_STRIPE_REHEARSAL_PROJECT_ROOT="$STAGED_PROJECT" \
    RED_STRIPE_REHEARSAL_ID="$REHEARSAL_ID" \
        "$FRANKENPHP_BIN" php-cli \
        "$rehearsal_fixture"
done

printf 'Stripe %s rehearsal passed before cleanup.\n' "$REHEARSAL_LABEL"
