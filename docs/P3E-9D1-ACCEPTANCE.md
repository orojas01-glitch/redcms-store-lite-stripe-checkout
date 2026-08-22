# P3E-9D1 Acceptance

P3E-9D1 is complete only when the focused and aggregate adapter suites prove:

- retention in later adapter `0.1.8` with nineteen-file integrity inventory;
- byte-identical source/package preflight adoption;
- unchanged migration checksums and manifest capability surface;
- exact D0 endpoint, form, expiry, line-item, idempotency, and hash
  recomputation;
- byte-identical canonical source-input SHA-256 with RED-CMS core regardless
  of associative insertion order, while preserving the fixed D0 request hash;
- failure-closed handling of altered, extra, malformed, read-only, or
  effect-bearing evidence;
- typed preflight invocation without package secret access;
- refusal of D1 preflight input at the separately named D4A provider operation;
- zero credential, database, DNS, TLS, HTTP, Stripe, Checkout Session,
  payment, Store Lite, demo, client, or deployment effects.

Current evidence: 68 focused P3E-9D1 assertions and 1,172 aggregate adapter
assertions pass.

Run:

```sh
RED_CMS_CORE=/path/to/redcms \
PHP_CLI=/path/to/php \
scripts/test.sh
```
