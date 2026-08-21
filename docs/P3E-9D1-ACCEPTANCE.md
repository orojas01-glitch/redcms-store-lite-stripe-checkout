# P3E-9D1 Acceptance

P3E-9D1 is complete only when the focused and aggregate adapter suites prove:

- exact adapter `0.1.7` identity and fifteen-file integrity inventory;
- byte-identical source/package preflight adoption;
- unchanged migration checksums and manifest capability surface;
- exact D0 endpoint, form, expiry, line-item, idempotency, and hash
  recomputation;
- byte-identical canonical source-input SHA-256 with RED-CMS core regardless
  of associative insertion order, while preserving the fixed D0 request hash;
- failure-closed handling of altered, extra, malformed, read-only, or
  effect-bearing evidence;
- typed preflight invocation without package secret access;
- continued unsupported status for `checkout.create-sandbox-real-post`; and
- zero credential, database, DNS, TLS, HTTP, Stripe, Checkout Session,
  payment, Store Lite, demo, client, or deployment effects.

Current evidence: 64 focused P3E-9D1 assertions and 1,063 aggregate adapter
assertions pass.

Run:

```sh
RED_CMS_CORE=/path/to/redcms \
PHP_CLI=/path/to/php \
scripts/test.sh
```
