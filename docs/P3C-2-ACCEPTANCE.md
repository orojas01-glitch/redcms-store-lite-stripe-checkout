# P3C-2 Acceptance Record

Date: 2026-08-16

## Result

The checkout-attempt storage contract passed 61 focused assertions with PHP
8.5.8. The retained P3C-1 suite still passed all 51 assertions. All five PHP
files passed syntax validation, `git diff --check` passed, and the current
RED-CMS `red_addon_install_sql_guard()` accepted the immutable P3C-2 SQL.

The P3C-2 proof covers:

- exact schema-only, non-installable package status;
- exactly one package-namespaced InnoDB migration and table;
- bounded attempt columns, idempotency/session uniqueness, initial state, and
  maximum lifetime;
- explicit forbidden-storage vocabulary and absence of cross-package foreign
  keys or data writes;
- deterministic record planning from exact P3C-1 response validation;
- complete removal of the transient Checkout URL from the record;
- refusal of malformed scope/evidence hashes, invalid timestamps, extra input,
  live mode, mismatched amounts, and browser URL ambiguity with no partial
  record; and
- absence of RED-CMS, Store Lite, SDK, database, request-global, secret, and
  network dependencies from the focused fixture.

## Isolation and cleanup

No database or grant was created, so no retained database cleanup was needed.
No process, provider object, credential, request, client package, or deployment
was created. RED-CMS core, Store Lite, and every client installation/database
remain unchanged.

This record closes only P3C-2 design and focused proof. Migration execution,
attempt persistence, event/replay storage, an installable manifest, runtime
registration, secrets, provider communication, P3D rehearsal, P3E Stripe
Sandbox access, and P4 deployment remain separately gated.
