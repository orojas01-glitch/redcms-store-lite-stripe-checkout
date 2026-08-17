# P3E-1 Non-Executing Stripe Sandbox Transport Contract

Status: P3E-1 is a dependency-free, non-executing transport design. The
installable adapter remains version `0.1.1`, and its only runtime operation
remains the fixed offline `contract.probe`. No HTTP client or provider access
is added.

## Purpose

P3D-7 proved that RED-CMS can invoke the separately installed adapter through
the typed boundary while the adapter privately resolves synthetic settings and
still refuses transport. P3E-1 now fixes the data contract that a later,
separately reviewed transport may consume.

The pure planner accepts only:

- immutable order id, snapshot SHA-256, Stripe payment method, integer total,
  uppercase currency, and server-derived idempotency SHA-256;
- one to 24 closed server-derived line items whose integer totals must equal
  the exact order total; and
- an explicitly pinned dated Stripe API version plus same-origin HTTPS success
  and cancel URLs.

It produces a deterministic hashed plan for `POST
https://api.stripe.com/v1/checkout/sessions`. The form fixes hosted one-time
payment mode, lowercase currency, integer unit amounts and quantities, the
order id as `client_reference_id`, and only the order-snapshot and idempotency
hashes as metadata.

## Official Stripe requirements and local policy

Stripe documents HTTPS authentication, form-encoded API v1 requests, JSON
responses, `POST /v1/checkout/sessions`, hosted Checkout return URLs, and
idempotency keys on POST requests. Stripe also requires TLS 1.2 or newer.

P3E-1 adds stricter RED-CMS policy: exact `api.stripe.com` host and v1 path,
peer and host verification, zero redirects, a 5-second connect timeout,
15-second total timeout, 256-KiB response limit, exact HTTP 200, and a bounded
JSON content type. These timeout and size limits are RED-CMS choices, not
claims about Stripe requirements.

The API version is mandatory input rather than a hard-coded "latest" value.
The test fixture uses the historical `2024-09-30.acacia` version only to prove
the dated-version syntax. A later sandbox-access gate must review and pin the
client's chosen supported version before sending any request.

## Credential boundary

The plan records only that future transport must use the package-owned
`stripe.secret-key` setting as the HTTP Basic username. It never resolves or
returns that setting, creates an `Authorization` header, accepts a key, or
contains a test/live/restricted key shape. The installable runtime handler is
unchanged and transport remains disabled.

## Response gate and refusal

The pure response gate accepts only transport evidence with:

- exact HTTP 200 and bounded JSON content type/body size;
- a lowercase SHA-256 of the separately bounded raw body;
- a bounded opaque Stripe request id;
- TLS 1.2 or 1.3; and
- zero redirects.

It then reuses the P3C-1 closed Checkout Session projection normalizer. Only a
non-live, open, unpaid sandbox Session whose order, amount, currency, and two
metadata hashes match exactly can return the opaque Session reference and
hosted URL. The gate emits a new evidence hash, not the request id, raw-body
hash, raw body, provider error, or customer/payment data.

Stripe's official create-Session example includes a fragment in the hosted
Checkout URL. P3E-1 therefore corrects the earlier normalizer to accept one
bounded control-free fragment while retaining exact HTTPS, host, path, Session
id, and no-userinfo/port/query checks.

Any transport-envelope failure returns `transport_response_refused`; any
Session mismatch returns `checkout_projection_refused`. Neither returns a
partial checkout or evidence hash. Provider `4xx` and `5xx` bodies are not
interpreted and cannot authorize retry, payment, or order state.

## Explicit stop

P3E-1 adds no cURL, stream, socket, SDK, DNS, TLS connection, API credential
lookup, provider request, retry, raw-body decoder, Checkout Session, attempt
write, event receipt, Store Lite invocation, order mutation, browser redirect,
return route, webhook verifier, client configuration, deployment, or payment.

The next gate must separately decide whether to build a sealed transport test
double or request one explicitly authorized Stripe Sandbox connection. Live
mode remains excluded.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The focused P3E-1 test proves deterministic planning, exact endpoint/headers,
secret exclusion, line-total reconciliation, return-origin policy, response
envelope bounds, hosted-fragment compatibility, live-mode refusal, mismatch
refusal, and the absence of network/database/request primitives.

## Official references reviewed

- [Stripe API authentication](https://docs.stripe.com/api/authentication)
- [Create a Checkout Session](https://docs.stripe.com/api/checkout/sessions/create)
- [Idempotent requests](https://docs.stripe.com/api/idempotent_requests)
- [Stripe integration security guide](https://docs.stripe.com/security/guide)
- [Stripe API errors](https://docs.stripe.com/api/errors)
