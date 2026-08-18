# P3E-8B3C1 Read-Only Provider Operation

Status: adapter version `0.1.4` contains one exact typed operation capable of
calling the already-reviewed one-use Stripe Sandbox transport. The operation
is not invoked by this gate, current core rejects the new package/profile, and
no provider request occurs.

## Purpose

P3E-8B3B proved the real package/core integration through a synthetic-only
typed operation. P3E-8B3C1 prepares the adapter half of the next boundary so
the provider-capable handler path can be inspected, hashed, installed, and
registered before core is permitted to call it.

## Exact operation

The typed handler adds only:

`provider-contact.read-only-probe-sandbox`

It requires:

- `contactTarget=stripe-sandbox`;
- the exact `0.1.4/provider_read_only` contact plan;
- a canonical plan SHA-256;
- claim-state and execution-start-state SHA-256 values;
- access scoped to `stripe.secret-key`; and
- `stripe.webhook-secret` unavailable.

Changed target, expanded input, malformed hashes, plan/hash mismatch, changed
version/mode, missing secret key, or visible webhook secret fails closed.

## Provider transport

After those checks, the latent handler would construct the final one-use
`StripeSandboxReadOnlyProbeTransport`, call its exact plan once, and pass only
the bounded transport facts to the reviewed outcome gate. The transport fixes:

- one `GET` to the synthetic missing Checkout Session at `api.stripe.com`;
- externally supplied `rk_test_`-shape restricted credential;
- private HTTP Basic handling;
- HTTPS only, peer/host verification, TLS 1.2 minimum;
- no proxy, redirect, reused connection, or retry;
- five-second connect and fifteen-second total limits;
- 16 KiB discarded-header and 64 KiB discarded-body bounds; and
- generic failure without cURL, provider, response, or credential detail.

The typed result contains only the closed status classification, numeric status
code, response byte count, transport evidence SHA-256, and fixed disclosure,
retry, and mutation flags. It marks network/provider contact true only after a
successful bounded exchange.

## Still unreachable

RED-CMS core `0aa6a83` accepts only `0.1.1/disabled` and
`0.1.3/synthetic_only`. It therefore refuses adapter `0.1.4` before
authorization or execution. This package adds no route, CLI runner, browser
control, scheduled job, public mutation, or automatic caller.

The P3E-8B3C1 fixture never constructs a typed request and never invokes the
handler or `exchange()`. It uses source inspection, reflection of pure/private
validators, value-free readiness planning, integrity hashes, and a transport
object whose call count remains zero.

## Acceptance

The focused fixture passes 61 assertions. It proves:

- exact `0.1.4` manifest and uninvoked identity;
- unchanged migrations, dependencies, settings, routes, permissions, jobs,
  mutations, assets, and uninstall policy;
- every package integrity hash;
- exact `0.1.4/provider_read_only` and retained
  `0.1.3/synthetic_only` planner profiles;
- refusal of every mismatched version/mode pair;
- actual transport acceptance of only the provider-read-only plan;
- exact hash-bound provider input and target/expansion refusal;
- required GET/TLS/proxy/redirect/timeout/size cURL options;
- absence of mutation-capable cURL options;
- absence of environment, database, request-global, shell, logging, and
  credential literals; and
- zero transport calls.

The complete suite must preserve all earlier pure, synthetic, loopback,
installation, enablement, registration, secret-bootstrap, and offline typed
invocation proofs. No provider credential, network request, core database,
Store Lite business row, browser, client installation, or deployment is used.

## Next stop

P3E-8B3C2 may add a core runner for exact adapter
`0.1.4/provider_read_only`, but must verify that runner only against an
in-memory provider handler. The one real restricted-key sandbox GET remains a
separate, explicit P3E-8B3C3 operator rehearsal.
