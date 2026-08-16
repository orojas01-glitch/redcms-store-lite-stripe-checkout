# RED-CMS Store Lite Stripe Checkout Adapter

This repository is the separately distributed Stripe Checkout adapter for
RED-CMS Store Lite. P3C-1 established package identity plus dependency-free
pure normalization contracts, and P3C-2 added checkout-attempt storage. The
current **P3C-3 schema-only** slice adds immutable provider-event receipt/replay
storage and one pure receipt-record planner.

P3C-3 remains deliberately non-installable. It contains no `addon.json`,
`addon.php`, registrar, route, webhook verifier, secret reference, HTTP client,
Stripe SDK, database connection/writer, Store Lite invocation, browser return
handler, client installation, or payment path. The migration cannot be applied
through RED-CMS until a later reviewed installable-package gate.

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

## Run the isolated proof

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

See [`docs/P3C-1-FOUNDATION-CONTRACT.md`](docs/P3C-1-FOUNDATION-CONTRACT.md)
and
[`docs/P3C-2-CHECKOUT-ATTEMPT-STORAGE-CONTRACT.md`](docs/P3C-2-CHECKOUT-ATTEMPT-STORAGE-CONTRACT.md)
and
[`docs/P3C-3-EVENT-REPLAY-STORAGE-CONTRACT.md`](docs/P3C-3-EVENT-REPLAY-STORAGE-CONTRACT.md)
for the complete boundaries and later-gate exclusions.
