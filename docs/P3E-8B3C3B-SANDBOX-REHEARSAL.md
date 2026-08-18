# P3E-8B3C3B Restricted-Key Sandbox Rehearsal

Status: the isolated no-key preflight and one explicit restricted-key GET have
passed. The execution evidence and cleanup are recorded in
[`P3E-8B3C3B-EXECUTION-CLOSEOUT.md`](P3E-8B3C3B-EXECUTION-CLOSEOUT.md).

## Exact staged boundary

The wrapper stages fresh copies of:

- merged RED-CMS core containing the B3C3A server-local operator command;
- Store Lite `0.1.35`; and
- Stripe checkout adapter `0.1.4`.

It refuses a core checkout containing an `addons` directory, excludes local
configuration and Git state, and places both packages only in a temporary
project. It never targets a hosted path, retained client installation, or
retained database.

## Disposable lifecycle

Each run creates one uniquely named `redcms_stripe_p3e8b3c3b_*` database and
one scoped application-account grant. It imports the clean installer, applies
all 46 core migrations, then:

1. grants the isolated seeded administrator Owner plus install/enable
   capabilities;
2. installs and enables Store Lite `0.1.35` with synthetic USD/pickup settings;
3. installs and enables adapter `0.1.4` with fixed opaque secret references;
4. generates a value-free `provider_read_only` plan from actual staged package
   evidence;
5. prepares a fresh nonce-bound authorization with a ten-minute window;
6. commits the P3E-7 authorization and P3E-8A claim;
7. computes the B3C2 execution-start and secret-availability hashes;
8. writes only non-secret readiness/prepared evidence to a mode-0600 file;
9. captures and SHA-256 verifies a pre-contact database dump; and
10. runs the merged B3C3A command in dry-run mode with secret values explicitly
    removed.

Preflight then stops successfully without resolving a credential, invoking a
package handler, writing an execution start, or contacting Stripe.

## External execution lock

The one real GET remains unreachable unless the operator supplies all three
external prerequisites:

- `RED_STRIPE_B3C3B_EXECUTE=YES_ONE_READ_ONLY_GET`;
- `RED_ADDON_SECRET_VALUES_JSON` containing exactly one value under
  `config:b3c3b-stripe-secret-key`; and
- `RED_STRIPE_B3C3B_EVIDENCE_DIR` naming one new absolute archive directory.

The setup process accepts only an `rk_test_` restricted-key shape. It rejects
standard test keys, live keys, webhook values, extra JSON keys, malformed JSON,
unknown execution tokens, mismatched ambient references, and any secret value
during ordinary preflight. No key can be passed as a command-line argument and
no fixture prints or hashes the key.

After a successful dry run and backup, the wrapper passes every exact B3C3A
confirmation and calls the operator command once with `--apply`. The command
and B3C2 runner retain the immutable start-before-secret rule and permanent
no-retry behavior. Only the exact bounded 404 resource miss succeeds.

## Evidence and cleanup

Execution mode creates the new private evidence directory only after all
preconditions and dry-run checks pass. It retains only:

- value-free setup JSON;
- dry-run output;
- bounded operator output;
- the pre-contact database dump; and
- SHA-256 checksums.

The cleanup trap always revokes the scoped grant, drops the disposable
database, removes the staged project and temporary evidence, stops its own
sleep-prevention process, and compares the configured-primary database dump
hash before and after. Expected cleanup is:

`database:0 grant:0 staged-project:0 process:0 primary:unchanged`

## Contract acceptance

The pure 42-assertion contract verifies exact versions, token, key/reference
policy, every B3C3A confirmation, one apply site, short-lived nonce evidence,
backup and archive requirements, staged-client exclusion, cleanup ownership,
absence of orchestration network/shell/request primitives, and zero hosted or
client target names.

The no-key disposable preflight passed with adapter `0.1.4`, Store Lite
`0.1.35`, 46 core migrations, a verified pre-contact backup, and cleanup
`database:0 grant:0 staged-project:0 process:0 primary:unchanged`. Independent
post-run verification also returned `database:0 grant:0` while the retained
primary table count remained unchanged.

The separately authorized provider request completed exactly once with bounded
`404 resource_miss_observed` evidence and no retry. Merge approval alone did
not trigger it. After evidence review, the operator explicitly expired the
restricted key; it no longer appears in the active restricted-key list.
