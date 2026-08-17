# P3E-6 Stripe Sandbox Contact Readiness

Status: P3E-6 is a pure, non-contact readiness plan and expiring
operator-confirmation envelope for one possible future read-only Stripe
Sandbox probe. It contains no credential resolver or transport and does not
alter installable adapter `0.1.1` under `package/`.

## Purpose

P3E-5 proved actual HTTPS mechanics only against numeric loopback. P3E-6 does
not broaden that transport. Instead, it defines the exact non-secret evidence
and operator confirmations that must exist before a later core-owned runner
could even consider provider contact.

Stripe currently recommends sandbox keys for development, restricted API keys
for new server-side use cases, least privilege, vault or process-environment
delivery, source/configuration exclusion, rotation, revocation, and avoiding
chat or email sharing. P3E-6 converts those requirements into closed
value-free fields without accepting a key value or key hash.

## Readiness evidence

The planner requires exact evidence for:

- package `redcms.store-lite-stripe-checkout` version `0.1.1`, a bounded
  package-artifact SHA-256, and runtime provider transport still `disabled`;
- setting `stripe.secret-key` classified externally as `restricted_test`;
- process-environment delivery with availability true but both value and value
  SHA-256 absent;
- clean repository, configuration, and log scans;
- reviewed read-only Checkout Sessions privilege;
- ready rotation and revocation procedures plus a non-secret evidence hash;
  and
- the fixed future network shape: `GET`, Stripe API host and port, one
  synthetic missing Checkout Session path, HTTPS/TLS verification, no proxy,
  no redirect, and bounded time/response limits.

Unknown, expanded, missing, weakened, or value-bearing evidence is refused
without a partial plan.

## Closed contact plan

The deterministic plan fixes only
`stripe.sandbox.read-only-resource-miss-probe`. Its expected effect is a
read-only missing-resource response, and it accepts no response-body
projection. It binds the exact package and credential-readiness evidence but
contains no Authorization value.

The plan explicitly fixes:

- maximum attempts: one;
- one-time authorization required;
- retry, mutation, Checkout creation, payment, webhook, live mode, and client
  deployment: false; and
- execution performed: false.

The plan SHA-256 covers the exact canonical plan bytes.

## Prepared operator envelope

The second contract accepts the exact readiness result plus a closed
confirmation containing opaque SHA-256 identifiers for the operator subject
and authorization nonce. The UTC window must be valid, active at evaluation,
and no longer than 15 minutes. Every prohibition must be affirmatively
confirmed.

The result is a hash-bound prepared envelope, not proof of identity and not a
cryptographic signature. It therefore returns:

- `ownerAuthorityRevalidationRequired=true`;
- `nonceConsumptionRequired=true`;
- `contactAuthorized=false`; and
- `executionPerformed=false`.

A later core-owned, stateful runner must revalidate an authenticated owner,
revalidate the unchanged plan and package, atomically consume the nonce, and
write an audit fact before contact could be authorized. P3E-6 cannot do any of
those things.

## Explicit stop

P3E-6 adds no cURL, stream, socket, DNS, TLS handshake, HTTP client, credential
value, credential hash, environment read, Stripe SDK, Stripe contact, owner
session read, nonce ledger, database, automatic retry, Checkout Session,
payment, webhook, Store Lite mutation, browser route, client configuration, or
deployment.

The source remains outside `package/`; package identity, manifest, migrations,
runtime handler, and version remain unchanged. P3E-7 may define owner
revalidation and atomic nonce consumption, still without provider contact.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The focused fixture covers deterministic hashing, strict exact-key evidence,
all credential/network/package substitutions, every negative authorization,
UTC format and 15-minute bounds, plan tampering, expansion attempts, and the
closed non-executing output.

## Official references reviewed

- [Stripe API keys](https://docs.stripe.com/keys)
- [Stripe key-management best practices](https://docs.stripe.com/keys-best-practices)
- [Stripe sandboxes](https://docs.stripe.com/sandboxes)
- [Retrieve a Checkout Session](https://docs.stripe.com/api/checkout/sessions/retrieve)
