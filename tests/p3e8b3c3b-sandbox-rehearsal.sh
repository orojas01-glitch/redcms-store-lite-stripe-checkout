#!/bin/bash

set -euo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ADAPTER_ROOT="$(cd "$TEST_DIR/.." && pwd)"
RED_CMS_CORE_ROOT="${RED_CMS_CORE_ROOT:-$(dirname "$ADAPTER_ROOT")/redcms v5.1}"
STORE_LITE_ROOT="${STORE_LITE_REPOSITORY:-$(dirname "$ADAPTER_ROOT")/redcms-store-lite}"
if [[ ! -f "$RED_CMS_CORE_ROOT/scripts/db-common.sh" ]]; then
    printf 'RED-CMS core checkout is unavailable: %s\n' "$RED_CMS_CORE_ROOT" >&2
    exit 66
fi

# shellcheck source=/dev/null
source "$RED_CMS_CORE_ROOT/scripts/db-common.sh"

FRANKENPHP_BIN="${FRANKENPHP_BIN:-\
/Users/oscarrojas/Documents/red-cms-dev/frankenphp-1.12.4/frankenphp}"
DATABASE_NAME="${RED_STRIPE_B3C3B_DATABASE:-redcms_stripe_p3e8b3c3b_$(date +%s)_$$}"
API_REFERENCE='config:b3c3b-stripe-secret-key'
WEBHOOK_REFERENCE='config:b3c3b-stripe-webhook-secret'
REFERENCE_LIST="$API_REFERENCE,$WEBHOOK_REFERENCE"
EXECUTION_TOKEN="${RED_STRIPE_B3C3B_EXECUTE:-}"
EXECUTION_REQUESTED=0
TEMP_ROOT=""
STAGED_PROJECT=""
ADMIN_DEFAULTS_FILE=""
APP_ACCOUNT_USER=""
APP_ACCOUNT_HOST=""
DATABASE_CREATED=0
GRANT_CREATED=0
PRIMARY_SNAPSHOT_BEFORE=""
KEEP_AWAKE_PID=0
SETUP_REPORT=""
DRY_RUN_REPORT=""
EXECUTION_REPORT=""
BACKUP_FILE=""
ARCHIVE_DIR=""

red_stripe_b3c3b_admin_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$ADMIN_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_stripe_b3c3b_app_mysql() {
    "$RED_MYSQL_BIN" \
        "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
        --batch --raw --skip-column-names \
        "$@"
}

red_stripe_b3c3b_primary_snapshot() {
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

red_stripe_b3c3b_json_field() {
    "$RED_PHP_BIN_RESOLVED" -r '
        $value = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
        $field = $value[$argv[2]] ?? null;
        if (is_bool($field)) {
            echo $field ? "true" : "false";
        } elseif (is_int($field) || is_string($field)) {
            echo $field;
        } else {
            exit(1);
        }
    ' "$1" "$2"
}

red_stripe_b3c3b_cleanup() {
    local original_status=$?
    local cleanup_status=0
    local schema_count=""
    local grant_output=""
    local primary_snapshot_after=""
    local process_count=0

    trap - EXIT INT TERM
    set +e
    if [[ "$GRANT_CREATED" -eq 1 ]]; then
        red_stripe_b3c3b_admin_mysql --execute="
            REVOKE ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
            FROM '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ "$DATABASE_CREATED" -eq 1 ]]; then
        red_stripe_b3c3b_admin_mysql --execute="
            DROP DATABASE IF EXISTS \`$DATABASE_NAME\`;
        " >/dev/null 2>&1 || cleanup_status=1
    fi
    if [[ -n "$ADMIN_DEFAULTS_FILE"
        && -n "$APP_ACCOUNT_USER"
        && -n "$APP_ACCOUNT_HOST"
    ]]; then
        schema_count="$(red_stripe_b3c3b_admin_mysql --execute="
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='$DATABASE_NAME';
        " 2>/dev/null)"
        grant_output="$(red_stripe_b3c3b_admin_mysql --execute="
            SHOW GRANTS FOR '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
        " 2>/dev/null)"
        if [[ "$schema_count" != '0'
            || "$grant_output" == *"\`$DATABASE_NAME\`.*"*
        ]]; then
            printf '%s\n' 'Cleanup failure: database or grant remains.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
        primary_snapshot_after="$(
            red_stripe_b3c3b_primary_snapshot 2>/dev/null
        )"
        if [[ $? -ne 0
            || "$primary_snapshot_after" != "$PRIMARY_SNAPSHOT_BEFORE"
        ]]; then
            printf '%s\n' 'Cleanup failure: configured primary changed.' >&2
            cleanup_status=1
        fi
    fi
    if [[ -n "$TEMP_ROOT"
        && "$TEMP_ROOT" == "${TMPDIR:-/tmp}/redcms-stripe-p3e8b3c3b."*
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
    if [[ "$cleanup_status" -eq 0 && "$DATABASE_CREATED" -eq 1 ]]; then
        printf '%s process:%s primary:unchanged\n' \
            'Stripe B3C3B cleanup passed: database:0 grant:0 staged-project:0' \
            "$process_count"
    fi
    if [[ "$original_status" -ne 0 ]]; then
        exit "$original_status"
    fi
    exit "$cleanup_status"
}

trap red_stripe_b3c3b_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ $# -ne 0 ]]; then
    printf 'Usage: %s\n' "$0" >&2
    exit 64
fi
if [[ "$EXECUTION_TOKEN" == 'YES_ONE_READ_ONLY_GET' ]]; then
    EXECUTION_REQUESTED=1
    if [[ -z "${RED_ADDON_SECRET_VALUES_JSON:-}" ]]; then
        printf '%s\n' 'Execution requested without secret-value JSON.' >&2
        exit 65
    fi
elif [[ -n "$EXECUTION_TOKEN" ]]; then
    printf '%s\n' 'Unknown B3C3B execution token.' >&2
    exit 64
elif [[ -n "${RED_ADDON_SECRET_VALUES_JSON:-}" ]]; then
    printf '%s\n' 'Preflight refuses ambient secret values.' >&2
    exit 65
fi
if [[ -n "${RED_ADDON_SECRET_REFERENCES:-}"
    && "${RED_ADDON_SECRET_REFERENCES}" != "$REFERENCE_LIST"
]]; then
    printf '%s\n' 'Ambient secret references do not match B3C3B.' >&2
    exit 65
fi
if [[ ! "$DATABASE_NAME" =~ ^redcms_stripe_p3e8b3c3b_[A-Za-z0-9_]+$
    || ${#DATABASE_NAME} -gt 64
    || "$DATABASE_NAME" == "$RED_DB_NAME_RESOLVED"
]]; then
    printf 'Unsafe B3C3B database name: %s\n' "$DATABASE_NAME" >&2
    exit 64
fi
if [[ ! -x "$FRANKENPHP_BIN"
    || ! -s "$ADAPTER_ROOT/package/addon.json"
    || ! -s "$STORE_LITE_ROOT/package/addon.json"
    || ! -s "$RED_CMS_CORE_ROOT/scripts/admin-provider-contact-sandbox-execute.php"
    || ! -s "$TEST_DIR/p3e8b3c3b-sandbox-rehearsal-setup.php"
]]; then
    printf '%s\n' 'B3C3B core, packages, runtime, or setup is unavailable.' >&2
    exit 66
fi
if [[ -e "$RED_CMS_CORE_ROOT/addons" ]]; then
    printf '%s\n' 'Clean core unexpectedly contains an addons directory.' >&2
    exit 65
fi

adapter_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$ADAPTER_ROOT/package/addon.json")"
store_version="$("$RED_PHP_BIN_RESOLVED" -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
    echo $manifest["version"] ?? "";
' "$STORE_LITE_ROOT/package/addon.json")"
if [[ "$adapter_version" != '0.1.4'
    || "$store_version" != '0.1.35'
]]; then
    printf 'B3C3B requires adapter 0.1.4 and Store Lite 0.1.35; found %s and %s.\n' \
        "$adapter_version" "$store_version" >&2
    exit 65
fi

if command -v caffeinate >/dev/null 2>&1; then
    caffeinate -dimsu -w $$ &
    KEEP_AWAKE_PID=$!
    printf '%s\n' 'Mac sleep prevention is active for B3C3B only.'
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/redcms-stripe-p3e8b3c3b.XXXXXX")"
STAGED_PROJECT="$TEMP_ROOT/project"
SETUP_REPORT="$TEMP_ROOT/setup.json"
DRY_RUN_REPORT="$TEMP_ROOT/dry-run.txt"
EXECUTION_REPORT="$TEMP_ROOT/execution.txt"
BACKUP_FILE="$TEMP_ROOT/pre-contact.sql"
EVIDENCE_FILE="$TEMP_ROOT/provider-contact-evidence.json"
mkdir -p \
    "$STAGED_PROJECT/addons/redcms/store-lite" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout"
rsync -a \
    --exclude='.git' \
    --exclude='addons' \
    --exclude='includes/config.local.php' \
    "$RED_CMS_CORE_ROOT/" "$STAGED_PROJECT/"
rsync -a "$STORE_LITE_ROOT/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite/"
rsync -a "$ADAPTER_ROOT/package/" \
    "$STAGED_PROJECT/addons/redcms/store-lite-stripe-checkout/"

ADMIN_DEFAULTS_FILE="$(mktemp "${TMPDIR:-/tmp}/redcms-b3c3b-admin.XXXXXX")"
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
red_stripe_b3c3b_admin_mysql --execute='SELECT 1;' >/dev/null

APP_ACCOUNT="$(red_stripe_b3c3b_app_mysql --execute='SELECT CURRENT_USER();')"
APP_ACCOUNT_USER="${APP_ACCOUNT%@*}"
APP_ACCOUNT_HOST="${APP_ACCOUNT#*@}"
if [[ ! "$APP_ACCOUNT_USER" =~ ^[A-Za-z0-9_.-]+$
    || ! "$APP_ACCOUNT_HOST" =~ ^[A-Za-z0-9_.:%-]+$
]]; then
    printf 'Unsafe application account: %s\n' "$APP_ACCOUNT" >&2
    exit 65
fi
database_count="$(red_stripe_b3c3b_admin_mysql --execute="
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA
    WHERE SCHEMA_NAME='$DATABASE_NAME';
")"
if [[ "$database_count" != '0' ]]; then
    printf 'Refusing to reuse database: %s\n' "$DATABASE_NAME" >&2
    exit 65
fi
PRIMARY_SNAPSHOT_BEFORE="$(red_stripe_b3c3b_primary_snapshot)"
if [[ -z "$PRIMARY_SNAPSHOT_BEFORE" ]]; then
    printf '%s\n' 'Could not fingerprint configured primary.' >&2
    exit 67
fi

red_stripe_b3c3b_admin_mysql --execute="
    CREATE DATABASE \`$DATABASE_NAME\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
DATABASE_CREATED=1
red_stripe_b3c3b_admin_mysql --execute="
    GRANT ALL PRIVILEGES ON \`$DATABASE_NAME\`.*
    TO '$APP_ACCOUNT_USER'@'$APP_ACCOUNT_HOST';
"
GRANT_CREATED=1

printf 'Preparing fresh B3C3B database: %s\n' "$DATABASE_NAME"
"$RED_MYSQL_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    "$DATABASE_NAME" < "$STAGED_PROJECT/db-structure.sql"
RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
RED_DB_USER="$RED_DB_USER_RESOLVED" \
RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
RED_DB_NAME="$DATABASE_NAME" \
    "$STAGED_PROJECT/scripts/db-migrate.sh" \
    "--database=$DATABASE_NAME"

setup_environment=(
    "RED_DB_HOST=$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED"
    "RED_DB_USER=$RED_DB_USER_RESOLVED"
    "RED_DB_PASS=$RED_DB_PASS_RESOLVED"
    "RED_DB_NAME=$DATABASE_NAME"
    "RED_ADDON_SECRET_REFERENCES=$REFERENCE_LIST"
    "RED_STRIPE_B3C3B_PROJECT_ROOT=$STAGED_PROJECT"
    "RED_STRIPE_B3C3B_ADAPTER_ROOT=$ADAPTER_ROOT"
    "RED_STRIPE_B3C3B_EVIDENCE_PATH=$EVIDENCE_FILE"
    "RED_STRIPE_B3C3B_EXECUTE=$EXECUTION_TOKEN"
)
if [[ "$EXECUTION_REQUESTED" -eq 1 ]]; then
    env "${setup_environment[@]}" \
        "RED_ADDON_SECRET_VALUES_JSON=$RED_ADDON_SECRET_VALUES_JSON" \
        "$FRANKENPHP_BIN" php-cli \
        "$TEST_DIR/p3e8b3c3b-sandbox-rehearsal-setup.php" \
        > "$SETUP_REPORT"
else
    env -u RED_ADDON_SECRET_VALUES_JSON "${setup_environment[@]}" \
        "$FRANKENPHP_BIN" php-cli \
        "$TEST_DIR/p3e8b3c3b-sandbox-rehearsal-setup.php" \
        > "$SETUP_REPORT"
fi

"$RED_MYSQLDUMP_BIN" \
    "--defaults-extra-file=$RED_DB_DEFAULTS_FILE" \
    --single-transaction \
    --skip-lock-tables \
    --no-tablespaces \
    --hex-blob \
    "$DATABASE_NAME" > "$BACKUP_FILE"
BACKUP_SHA256="$(shasum -a 256 "$BACKUP_FILE" | awk '{print $1}')"
if [[ ! "$BACKUP_SHA256" =~ ^[a-f0-9]{64}$ ]]; then
    printf '%s\n' 'Could not verify pre-contact backup.' >&2
    exit 67
fi

actor_id="$(red_stripe_b3c3b_json_field "$SETUP_REPORT" actorAdminRecordId)"
plan_sha="$(red_stripe_b3c3b_json_field "$SETUP_REPORT" planSha256)"
authorization_sha="$(red_stripe_b3c3b_json_field "$SETUP_REPORT" authorizationSha256)"
claim_sha="$(red_stripe_b3c3b_json_field "$SETUP_REPORT" claimStateSha256)"
start_sha="$(red_stripe_b3c3b_json_field "$SETUP_REPORT" executionStartStateSha256)"
secret_availability_sha="$(red_stripe_b3c3b_json_field "$SETUP_REPORT" secretAvailabilitySha256)"

(
    unset RED_ADDON_SECRET_VALUES_JSON
    env \
        RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
        RED_DB_USER="$RED_DB_USER_RESOLVED" \
        RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
        RED_DB_NAME="$DATABASE_NAME" \
        RED_ADDON_SECRET_REFERENCES="$REFERENCE_LIST" \
        "$FRANKENPHP_BIN" php-cli \
        "$STAGED_PROJECT/scripts/admin-provider-contact-sandbox-execute.php" \
        "--actor-admin=$actor_id" \
        "--evidence-file=$EVIDENCE_FILE"
) > "$DRY_RUN_REPORT"
if ! grep -Fq 'No credential value was resolved' "$DRY_RUN_REPORT" \
    || ! grep -Fq "$start_sha" "$DRY_RUN_REPORT"; then
    printf '%s\n' 'B3C3B dry-run evidence mismatch.' >&2
    exit 1
fi

printf '%s backup:%s\n' \
    'B3C3B preflight passed: adapter:0.1.4 store-lite:0.1.35 migrations:46' \
    "$BACKUP_SHA256"
if [[ "$EXECUTION_REQUESTED" -eq 0 ]]; then
    printf '%s\n' 'B3C3B stopped before credential resolution or provider contact.'
    exit 0
fi

ARCHIVE_DIR="${RED_STRIPE_B3C3B_EVIDENCE_DIR:-}"
if [[ ! "$ARCHIVE_DIR" == /* || -e "$ARCHIVE_DIR" ]]; then
    printf '%s\n' 'Execution requires one new absolute evidence directory.' >&2
    exit 65
fi
mkdir -p "$ARCHIVE_DIR"
chmod 700 "$ARCHIVE_DIR"

set +e
env \
    RED_DB_HOST="$RED_DB_HOST_RESOLVED:$RED_DB_PORT_RESOLVED" \
    RED_DB_USER="$RED_DB_USER_RESOLVED" \
    RED_DB_PASS="$RED_DB_PASS_RESOLVED" \
    RED_DB_NAME="$DATABASE_NAME" \
    RED_ADDON_SECRET_REFERENCES="$REFERENCE_LIST" \
    RED_ADDON_SECRET_VALUES_JSON="$RED_ADDON_SECRET_VALUES_JSON" \
    "$FRANKENPHP_BIN" php-cli \
    "$STAGED_PROJECT/scripts/admin-provider-contact-sandbox-execute.php" \
    "--actor-admin=$actor_id" \
    "--evidence-file=$EVIDENCE_FILE" \
    "--confirm-database=$DATABASE_NAME" \
    '--confirm-package=redcms.store-lite-stripe-checkout' \
    '--confirm-version=0.1.4' \
    '--confirm-state=enabled' \
    "--confirm-plan-sha256=$plan_sha" \
    "--confirm-authorization-sha256=$authorization_sha" \
    "--confirm-claim-state-sha256=$claim_sha" \
    "--confirm-execution-start-sha256=$start_sha" \
    "--confirm-secret-availability-sha256=$secret_availability_sha" \
    "--confirm-backup-sha256=$BACKUP_SHA256" \
    '--confirm-operation=provider-contact.read-only-probe-sandbox' \
    '--confirm-target=stripe-sandbox' \
    '--confirm-credential-mode=restricted_test' \
    '--confirm-maximum-attempts=1' \
    '--confirm-retry-authorized=no' \
    '--confirm-mutation-authorized=no' \
    --apply > "$EXECUTION_REPORT" 2>&1
execution_status=$?
set -e

cp "$SETUP_REPORT" "$ARCHIVE_DIR/setup.json"
cp "$DRY_RUN_REPORT" "$ARCHIVE_DIR/dry-run.txt"
cp "$EXECUTION_REPORT" "$ARCHIVE_DIR/execution.txt"
cp "$BACKUP_FILE" "$ARCHIVE_DIR/pre-contact.sql"
chmod 600 "$ARCHIVE_DIR"/*
shasum -a 256 "$ARCHIVE_DIR"/* > "$ARCHIVE_DIR/SHA256SUMS"
chmod 600 "$ARCHIVE_DIR/SHA256SUMS"
printf 'B3C3B non-secret evidence archived: %s\n' "$ARCHIVE_DIR"
sed -n '1,80p' "$EXECUTION_REPORT"
exit "$execution_status"
