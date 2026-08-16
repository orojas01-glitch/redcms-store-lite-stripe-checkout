# RED-CMS Store Lite Stripe Checkout Adapter

This repository is the separately distributed Stripe Checkout adapter for
RED-CMS Store Lite. P3C-1 established package identity plus dependency-free
pure normalization contracts, P3C-2 added checkout-attempt storage, and P3C-3
added immutable provider-event receipt/replay storage. P3C-4 assembled those
contracts as a RED-CMS-discoverable, integrity-checked adapter package. P3D-1
proved that exact package installs into `installed_disabled` on a fresh
disposable database. P3D-2 added complete value-free atomic-enable readiness
evidence. P3D-3 proved the exact adapter state and bounded audit fact commit
together or both roll back. P3D-4 added exact value-free request-local
ownership. The current **P3D-5 synthetic-secret request bootstrap** gate runs
the full production bootstrap with two disposable process-local values.

P3D-5 enables the adapter only inside its disposable database, injects two
random synthetic values into that PHP process, resolves them into the private
package-bound access object, then removes both environment entries and the
database exactly. It does not invoke a handler or service, dispatch a route,
access the network, contact Stripe, change Store Lite behavior, handle a
browser return, deploy to a client, or create a payment. Both adapter handlers
still explicitly refuse invocation until a later reviewed operational gate.

## Current contracts

- `RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer` validates a closed,
  reviewed sandbox Checkout Session response against immutable server-derived
  order facts. It returns only the opaque Checkout Session reference and the
  strictly validated hosted URL.
- `RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer` accepts only a bounded,
  already-signature-verified and reconciled Stripe event projection. It emits
  the exact provider-neutral event vocabulary consumed by Store Lite 0.1.35.
- `RED_CMS_Store_Lite_Stripe_Checkout_Attempt_Record_Planner` revalidates the
  reviewed Checkout response and returns only the bounded adapter-owned record
  allowed by the P3C-2 schema. The transient hosted URL is dropped.
- `RED_CMS_Store_Lite_Stripe_Event_Receipt_Record_Planner` revalidates an
  already-verified event projection and returns only the bounded immutable
  P3C-3 receipt. Raw bodies and signatures are never accepted or returned.

No contract reads request globals, a secret, a database, RED-CMS core, Store
Lite runtime code, or the network.

The installable payload under `package/` declares one adapter, one Store Lite
dependency, one server-signature event route, two value-free secret-reference
settings, one ordinary return-origin setting, and the two existing migrations.
Current RED-CMS core validates that manifest without executing it, then may
execute only the registrar in a discarded request-local registry after prior
database-readiness evidence is supplied.

## Run the isolated proof

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The separate disposable lifecycle proof requires the local RED-CMS, Store Lite,
MySQL, and FrankenPHP development environment:

```sh
tests/p3d1-install-disabled-rehearsal.sh
tests/p3d2-enable-dry-run-rehearsal.sh
tests/p3d3-atomic-enable-rollback-rehearsal.sh
tests/p3d4-runtime-service-binding-rehearsal.sh
tests/p3d5-synthetic-secret-bootstrap-rehearsal.sh
```

See [`docs/P3C-1-FOUNDATION-CONTRACT.md`](docs/P3C-1-FOUNDATION-CONTRACT.md)
and
[`docs/P3C-2-CHECKOUT-ATTEMPT-STORAGE-CONTRACT.md`](docs/P3C-2-CHECKOUT-ATTEMPT-STORAGE-CONTRACT.md)
and
[`docs/P3C-3-EVENT-REPLAY-STORAGE-CONTRACT.md`](docs/P3C-3-EVENT-REPLAY-STORAGE-CONTRACT.md)
and
[`docs/P3C-4-REGISTRATION-ONLY-PACKAGE-CONTRACT.md`](docs/P3C-4-REGISTRATION-ONLY-PACKAGE-CONTRACT.md)
and
[`docs/P3D-1-INSTALL-DISABLED-LIFECYCLE.md`](docs/P3D-1-INSTALL-DISABLED-LIFECYCLE.md)
and
[`docs/P3D-2-VALUE-FREE-ENABLE-DRY-RUN.md`](docs/P3D-2-VALUE-FREE-ENABLE-DRY-RUN.md)
and
[`docs/P3D-3-ATOMIC-ENABLE-ROLLBACK.md`](docs/P3D-3-ATOMIC-ENABLE-ROLLBACK.md)
and
[`docs/P3D-4-VALUE-FREE-RUNTIME-SERVICE-BINDING.md`](docs/P3D-4-VALUE-FREE-RUNTIME-SERVICE-BINDING.md)
and
[`docs/P3D-5-SYNTHETIC-SECRET-REQUEST-BOOTSTRAP.md`](docs/P3D-5-SYNTHETIC-SECRET-REQUEST-BOOTSTRAP.md)
for the complete boundaries and later-gate exclusions.
