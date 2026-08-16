# RED-CMS Store Lite Stripe Checkout Adapter

This repository is the separately distributed Stripe Checkout adapter for
RED-CMS Store Lite. Its current state is **P3C-1 foundation only**: package
identity plus dependency-free pure normalization contracts.

P3C-1 is deliberately non-installable. It contains no `addon.json`,
`addon.php`, migration, registrar, route, webhook verifier, secret reference,
HTTP client, Stripe SDK, database writer, Store Lite invocation, browser return
handler, client installation, or payment path.

## Current contracts

- `RED_CMS_Store_Lite_Stripe_Checkout_Response_Normalizer` validates a closed,
  reviewed sandbox Checkout Session response against immutable server-derived
  order facts. It returns only the opaque Checkout Session reference and the
  strictly validated hosted URL.
- `RED_CMS_Store_Lite_Stripe_Verified_Event_Normalizer` accepts only a bounded,
  already-signature-verified and reconciled Stripe event projection. It emits
  the exact provider-neutral event vocabulary consumed by Store Lite 0.1.35.

Neither contract reads request globals, a secret, the filesystem, a database,
RED-CMS core, Store Lite runtime code, or the network.

## Run the isolated proof

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

See [`docs/P3C-1-FOUNDATION-CONTRACT.md`](docs/P3C-1-FOUNDATION-CONTRACT.md)
for the complete boundary and later-gate exclusions.
