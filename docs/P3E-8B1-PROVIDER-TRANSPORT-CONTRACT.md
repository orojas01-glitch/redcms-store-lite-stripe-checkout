# P3E-8B1 Provider Transport Contract

Status: P3E-8B1 adds a provider-capable but unconnected source primitive for
the one exact read-only Stripe Sandbox resource-miss probe. It does not execute
that primitive, resolve a credential, alter installable adapter `0.1.1`, or
contact Stripe.

## Purpose

P3E-8A in RED-CMS core can atomically claim one still-active P3E-7
authorization. P3E-8B1 defines the next adapter-side boundary without joining
it to that claim. This split makes the future network mechanism reviewable
before any operational invocation path exists.

The source-only transport accepts only the unchanged exact P3E-6 contact plan
for package `redcms.store-lite-stripe-checkout` version `0.1.1`. It fixes:

- one `GET` to the exact synthetic missing Checkout Session URL at
  `api.stripe.com`;
- an externally supplied restricted sandbox credential at the final
  constructor boundary, with no environment or secret-reference reader;
- HTTP Basic handled privately by cURL;
- HTTPS only with peer and host verification and TLS 1.2 minimum;
- proxy disabled, redirects disabled, and a fresh non-reused connection;
- 5-second connect and 15-second total time limits;
- 16 KiB discarded-header and 64 KiB discarded-body limits; and
- one object call, with no retry.

The provider response body and headers are counted only to enforce bounds and
are never retained or returned. The transport returns status and enforcement
facts only. All failures collapse to one generic error without returning cURL,
provider, response, or credential detail.

## Bounded outcome gate

The separate pure outcome gate accepts only the exact transport evidence
shape. It maps synthetic status evidence to these closed outcomes:

- `404`: `resource_miss_observed`;
- `401`: `credential_refused`;
- `403`: `permission_refused`;
- `429`: `rate_limited`;
- `5xx`: `provider_unavailable`;
- `2xx` other than the expected miss: `unexpected_success_status`; and
- all other bounded statuses: `unexpected_provider_status`.

The output keeps retry and mutation authorization false and includes no body,
headers, request identifier, credential, Checkout object, or payment fact. A
404 means only that the expected read-only missing-resource effect was
observed; it does not authorize a later mutation or retry.

## Non-executing boundary

Both classes remain under `src/`, outside `package/`. The package entrypoint,
handler, manifest, identity, migrations, integrity hashes, and version are
byte-identical. No core script, adapter handler, route, browser flow, client
installation, or deployment loads or calls the transport.

The acceptance fixture uses reflection, source inspection, a synthetic
restricted-test credential shape, and synthetic transport evidence. It never
calls `exchange()`. Therefore P3E-8B1 performs no DNS lookup, TLS handshake,
HTTP request, Stripe contact, credential resolution, database write, Store
Lite mutation, Checkout creation, payment, webhook, live-mode operation, or
client deployment.

## Next stop

P3E-8B2 may define a core-owned bridge that revalidates the exact still-active
P3E-8A claim and resolves only the owning package's secret reference at the
last possible boundary. It must first prove the complete orchestration against
an isolated loopback double. Provider contact remains a separate explicit
operator decision after that proof.

P3E-8B3A later adopted byte-identical copies of these two reviewed classes
into installable adapter `0.1.2` while keeping its typed handler refusal-only.
That later inventory change does not alter the P3E-8B1 proof or authorize
provider contact.

P3E-8B3C1 later rebinds the transport's accepted plan to exact adapter
`0.1.4/provider_read_only` and adds a separately named typed handler branch.
That gate inspects but never invokes the branch or transport.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The focused regression proves exact plan acceptance, plan-tamper refusal,
credential-shape refusal, required cURL safety options, closed outcome
classification, evidence bounds, and deterministic hashes. At the original
P3E-8B1 gate it also proved package absence; after P3E-8B3A it instead proves
the two package copies are byte-identical to the reviewed source and the
remaining current package payload matches its reviewed hashes.

## Official references reviewed

- [Stripe API authentication](https://docs.stripe.com/api/authentication)
- [Stripe key-management best practices](https://docs.stripe.com/keys-best-practices)
- [Stripe sandboxes](https://docs.stripe.com/sandboxes)
- [Retrieve a Checkout Session](https://docs.stripe.com/api/checkout/sessions/retrieve)
