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
ownership. P3D-5 then proved the full production bootstrap with two disposable
process-local values. Core P3D-6 added the reusable typed invocation boundary.
P3D-7 adopted that boundary in external adapter version `0.1.1`. The current
**P3E-1 non-executing sandbox transport contract** fixes a dependency-free
request/response plan while leaving the installable adapter unchanged and
offline. P3E-2 adds a **sealed transport executor proof** that invokes only an
in-memory test double once and never authorizes retry. P3E-3 adds a **bounded
wire codec** for canonical synthetic form bytes and duplicate-key-rejecting
JSON decoding, still without transport. P3E-4 adds a **one-use synthetic
credential byte transport** that privately constructs and verifies HTTP Basic
authorization, discards credential material, and returns only preloaded
synthetic response bytes. P3E-5 adds the first executable transport proof: a
**one-use loopback-only HTTPS transport** against a disposable TLS fixture.
It cannot address Stripe or any non-loopback host.

P3D-7 enables the adapter only inside its disposable database, injects two
random synthetic values into that PHP process, and invokes only the exact
value-free `contract.probe` operation through the core typed boundary. The
class-based handler resolves its own two settings privately and returns the
fixed refusal `provider_transport_disabled`. Unsupported operations and any
caller input also fail closed. The provider-event route remains non-
operational. No HTTP client, SDK, provider request, Checkout Session, Store
Lite service call, database write, order transition, browser return, client
deployment, or payment is created.

P3E-1 adds two pure source contracts outside the installable payload. One
validates immutable server-derived checkout/line facts and returns a hashed
sandbox request plan with a fixed HTTPS endpoint, pinned API-version input,
idempotency, TLS verification, no redirects, hard time/size bounds, and no
credential value. The second validates only bounded HTTP/TLS/body evidence,
then reuses the closed non-live Checkout Session normalizer. It does not open a
connection or change the runtime handler.

P3E-2 sequences that plan through one proof-only sealed double. It distinguishes
definite provider refusal from indeterminate network, server, or unusable-
response outcomes; suppresses transport exceptions; and always returns
`retryAuthorized=false`. It still contains no HTTP or credential implementation.

P3E-3 deterministically serializes the reviewed request form, bounds and hashes
the exact bytes, parses synthetic UTF-8 JSON with strict depth/value/duplicate-
key rules, and projects only the eleven reviewed Checkout fields. Provider
error bodies are not interpreted, and unusable results remain indeterminate.

P3E-4 connects those synthetic byte contracts through a final concrete
one-attempt in-memory transport. Only an exact random `synthetic_p3e4_`
fixture can enter it; Stripe test/live key prefixes are refused. The transport
constructs `Basic base64(secret + ":")` privately, checks its precommitted
SHA-256, clears the secret and commitment from object state before exchange,
and returns only a preloaded synthetic wire response. No credential-derived
fact reaches the adapter result, and retry remains unauthorized.

P3E-5 replaces the preloaded response with one real cURL exchange to an exact
`https://127.0.0.1:<ephemeral-port>` fixture. It pins HTTPS, TLS 1.2, peer and
host verification, a fixture-only in-memory CA certificate, no proxy, no
redirect, fixed time/size limits, a fresh connection, and no retry. The
disposable server observes the exact canonical request and persists only
method/path/header names plus authorization/body hashes. An untrusted
certificate fails closed. The installable adapter still contains no transport.

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
- `RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter` accepts only a
  core-owned typed request for `contract.probe` with empty input. It privately
  checks both declared settings and always keeps provider transport disabled.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Planner` produces only
  a deterministic non-executable sandbox request plan from exact order facts.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Transport_Response_Gate` accepts
  only bounded transport evidence and the already-decoded P3C-1 projection.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Executor` invokes only the
  P3E-2 proof interface once and emits no transcript or secret-derived fact.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Wire_Codec` converts only between
  reviewed plans, synthetic bytes, and closed P3E-2 transcripts.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Byte_Transport` owns
  one exact synthetic fixture, privately assembles HTTP Basic authorization,
  discards credential state, and returns preloaded bytes once.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Synthetic_Transport_Adapter`
  sequences P3E-3 bytes through that transport and the P3E-2 executor without
  accepting or returning credential material.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Transport` makes
  one TLS-verified cURL request only to numeric IPv4 loopback and returns the
  bounded wire response.
- `RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Loopback_Https_Adapter` sequences
  that response through the existing codec and sealed executor.

The pure normalization and record-planning contracts read no request global,
secret, database, RED-CMS core, Store Lite runtime code, or network. The typed
offline handler depends only on the RED-CMS request/result types and its
package-bound secret lookup; it returns no secret-derived fact and has no
provider or business-data path.

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
tests/p3d7-typed-offline-adapter-invocation-rehearsal.sh
tests/p3e5-loopback-https-transport-rehearsal.sh
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
and
[`docs/P3D-7-TYPED-OFFLINE-ADAPTER-INVOCATION.md`](docs/P3D-7-TYPED-OFFLINE-ADAPTER-INVOCATION.md)
and
[`docs/P3E-1-NON-EXECUTING-SANDBOX-TRANSPORT-CONTRACT.md`](docs/P3E-1-NON-EXECUTING-SANDBOX-TRANSPORT-CONTRACT.md)
and
[`docs/P3E-2-SEALED-TRANSPORT-EXECUTOR.md`](docs/P3E-2-SEALED-TRANSPORT-EXECUTOR.md)
and
[`docs/P3E-3-BOUNDED-WIRE-CODEC.md`](docs/P3E-3-BOUNDED-WIRE-CODEC.md)
and
[`docs/P3E-4-SYNTHETIC-CREDENTIAL-TRANSPORT.md`](docs/P3E-4-SYNTHETIC-CREDENTIAL-TRANSPORT.md)
and
[`docs/P3E-5-LOOPBACK-HTTPS-TRANSPORT.md`](docs/P3E-5-LOOPBACK-HTTPS-TRANSPORT.md)
for the complete boundaries and later-gate exclusions.
