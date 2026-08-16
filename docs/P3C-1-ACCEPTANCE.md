# P3C-1 Acceptance Record

Date: 2026-08-16

## Result

The dependency-free local P3C-1 foundation passed 51 assertions with PHP
8.5.8. All three PHP files passed syntax validation and `git diff --check`
reported no errors.

The proof covers:

- exact package, repository, adapter, Store Lite dependency, and outbound-host
  identity;
- explicit absence of an installable manifest, entrypoint, migration,
  Composer/vendor dependency, route, secret, database, and network path;
- strict non-live Checkout Session response validation;
- exact order, snapshot, amount, currency, and idempotency matching;
- strict hosted URL host/path validation with no query, fragment, port, or
  alternate host;
- exact Store Lite 0.1.35 event vocabulary for paid, failed, expired, refund,
  and reversal outcomes; and
- refusal of extra, raw-body, signature, customer, secret-shaped, live-mode,
  replayed, stale, mismatched, unsupported, and malformed inputs with no
  partial normalized value.

## Isolation

The fixture includes only its own test and the two local normalizer classes. It
does not load RED-CMS core or Store Lite runtime code. The RED-CMS and Store
Lite repositories remain unchanged. No database, process, provider object,
credential, client installation, or network connection is created.

## Command

```sh
PHP_CLI=/Users/oscarrojas/Documents/red-cms-dev/php-8.5.8/bin/php scripts/test.sh
```

This result closes only local P3C-1 implementation evidence. GitHub repository
creation and visibility remain separately gated, and later P3C runtime slices,
P3D offline lifecycle rehearsal, P3E Stripe Sandbox access, and P4 client
deployment remain unauthorized.
