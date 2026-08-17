# P3E-2 Sealed Transport Executor

Status: P3E-2 is a dependency-free, proof-only executor around one sealed
in-memory transport double. It adds no production transport and does not alter
the installable adapter `0.1.1` under `package/`.

## Purpose

P3E-1 fixed the exact non-secret request plan and bounded response gate. P3E-2
proves how one future call is sequenced without providing any implementation
capable of DNS, TLS, sockets, HTTP, credentials, Stripe access, or persistence.

The executor:

1. invokes the P3E-1 planner from immutable server-derived checkout facts;
2. refuses invalid checkout or policy input before calling the double;
3. passes the double only the deterministic non-secret request plan;
4. invokes the double exactly once;
5. accepts only one closed response or indeterminate transcript;
6. reuses the P3E-1 response gate for a synthetic successful response; and
7. returns no transcript, request id, raw-body hash, provider body, exception
   message, or credential fact.

The proof-only interface is deliberately named
`RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Sealed_Transport_Double`. A future
production HTTP transport must not implement it. Production transport and
credential resolution require a separate gate and package-version review.

## Closed outcomes

Successful synthetic transport returns `checkout_ready` with only the opaque
Checkout Session reference, validated hosted URL, request-plan SHA-256, and
response-evidence SHA-256. It never claims the order is paid.

A definite provider `4xx` response returns `refused`. The body is not parsed or
returned, no checkout/evidence is emitted, and no retry is authorized.

These outcomes remain `indeterminate`:

- connection or total timeout;
- premature connection close;
- DNS or TLS failure;
- oversized response;
- provider `5xx`;
- a nominal response whose HTTP/TLS/projection evidence is unusable; or
- any exception thrown by the sealed double.

An unusable `200`, mismatched amount or metadata, and unexpected live-mode
Session remain indeterminate because the create request might already have
produced a provider object. No partial checkout or response evidence is
returned.

Every result contains `retryAuthorized=false`. Stripe documents that some
network failures may be retried safely with the same idempotency key and exact
parameters. P3E-2 intentionally does not exercise or authorize that behavior;
reconciliation and retry ownership require later event, attempt-storage, and
provider-transport gates.

## Sealed test double

The focused test supplies one final in-memory class. It stores a synthetic
transcript, counts calls, and returns that transcript. It contains no dynamic
callable, SDK, filesystem, environment, request-global, database, timer, or
network primitive.

The executor receives no secret value. Its double sees the P3E-1 plan's fixed
package setting key and `valueIncluded=false`, but no Authorization header or
test/live/restricted Stripe key shape.

## Explicit stop

P3E-2 adds no real transport, cURL, stream, socket, DNS, TLS handshake, HTTP
request, credential resolver, API-version selection, Stripe Sandbox contact,
automatic/manual retry, Checkout Session, attempt write, webhook, Store Lite
service invocation, order transition, browser redirect, return route, client
configuration, deployment, or payment.

The next gate may design a dependency-free bounded response decoder and HTTP
adapter, but actual Stripe Sandbox contact still requires explicit separate
authorization and synthetic client-isolated configuration.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The P3E-2 self-test proves one-call success, pre-call refusal, closed transcript
shape, response refusal, indeterminate failure handling, exception-message
suppression, no retries, credential exclusion, and absence of executable
transport primitives.

## Official references reviewed

- [Stripe error handling](https://docs.stripe.com/error-handling)
- [Stripe advanced error handling](https://docs.stripe.com/error-low-level)
- [Stripe idempotent requests](https://docs.stripe.com/api/idempotent_requests)
