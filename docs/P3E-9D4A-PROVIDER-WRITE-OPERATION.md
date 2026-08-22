# P3E-9D4A Provider-Write Operation

Status: adapter `0.1.8` adds one exact provider-capable
`checkout.create-sandbox-real-post` operation. D4A does not invoke that typed
handler or the production exchange, resolve a real credential, contact Stripe,
or create a Checkout Session.

## Package Boundary

The installable package advances from `0.1.7` to `0.1.8` and adds four reviewed
payloads:

- the existing bounded duplicate-key-rejecting JSON decoder;
- a one-use real-POST exchange interface;
- a production transport fixed to
  `https://api.stripe.com/v1/checkout/sessions`; and
- a bounded operation wrapper that revalidates D1 preflight evidence before
  crossing the exchange boundary.

The package contains 19 integrity-checked files. Its two migration paths and
checksums, Store Lite `>=0.1.35 <1.0` dependency, settings, route, permissions,
jobs, assets, public-mutation declarations, and uninstall policy remain
unchanged.

## Exact Typed Input

The new handler accepts only:

- `contactTarget=stripe-sandbox-real-post`;
- the exact checkout, policy, profile, contract SHA-256, and typed D1
  preflight evidence;
- one D4-specific plan SHA-256;
- one fresh claim-state SHA-256; and
- one future durable execution-start SHA-256.

It resolves only `stripe.secret-key` through the owning package's core-supplied
secret boundary and requires `stripe.webhook-secret` to remain unavailable.
The restricted key value never enters a request/result payload.

## Future Production Transport

The transport is inert until `exchange()` is called. It is one-use and accepts
only the exact package-generated wire request. It fixes POST, endpoint, form
content type, API version, idempotency key, 30-minute expiry, no recovery,
HTTPS, TLS 1.2, peer/host verification, disabled proxy/redirects, fresh
connection, 5-second connect and 15-second total timeouts, 16 KiB header and
256 KiB body bounds, and no retry. Customer, email, phone, billing, and shipping
fields are refused.

The restricted test key is passed privately to cURL HTTP Basic handling and
cleared before return. Transport failures collapse to one generic error. Raw
response body/header material exists only long enough for bounded decoding and
is cleared after projection.

## Bounded Outcome

An exact synthetic response can produce only the validated opaque Session
reference plus open, unpaid, non-live, amount, currency, and expiry facts. The
Checkout URL is validated but discarded. The typed result excludes the key,
Authorization header, request/response bodies, response headers, request id,
provider object, and customer fields.

Any post-boundary exception, malformed response, provider error, live-mode
response, wrong identity/amount/currency/expiry, or unusable result becomes one
conservative `indeterminate` outcome. Provider mutation and Checkout creation
are then treated as possibly having occurred, and retry remains false.

## Unreachable In D4A

Core `37c623f` accepts only adapter `0.1.7` for its non-executing D2/D3 path and
has no D4B runner. No core route, CLI command, job, browser action, or automatic
caller can invoke adapter `0.1.8` at this gate. The clean RED-CMS starter and
every installed client remain unchanged.

P3E-9D4B remains separate: fresh operation/version-bound authorization,
one-attempt claim, durable start/result, and core runner acceptance must be
implemented before any operator command. D4D remains the first real Stripe
POST and requires separate explicit approvals.

## Official References Rechecked

- [Stripe API keys](https://docs.stripe.com/keys)
- [Stripe key-management best practices](https://docs.stripe.com/keys-best-practices)
- [Create a Checkout Session](https://docs.stripe.com/api/checkout/sessions/create)
- [Stripe API v1 idempotent requests](https://docs.stripe.com/api/idempotent_requests)
