# P3E-4 Synthetic Credential Byte Transport

Status: P3E-4 is a final one-use, in-memory byte transport for an exact
synthetic credential fixture. It opens no connection and does not alter the
installable adapter `0.1.1` under `package/`.

## Purpose

P3E-1 fixed a non-secret Stripe Sandbox request plan, P3E-2 sealed one-attempt
outcome handling, and P3E-3 proved canonical request and bounded response
bytes. P3E-4 connects those proofs while containing the credential operation
inside one final concrete transport. The surrounding adapter cannot accept,
read, hash, return, or log credential material.

## Synthetic-only credential boundary

The constructor accepts only the exact test-fixture shape
`synthetic_p3e4_` followed by 64 lowercase hexadecimal characters. Stripe
test/live key prefixes and all other token shapes fail before an exchange.
PHP's `SensitiveParameter` attribute also prevents ordinary exception traces
from exposing the constructor argument.

The test prepares a SHA-256 commitment to the expected HTTP Basic value. On
the first and only exchange, the transport:

1. validates the exact P3E-3 wire request and rejects caller-provided
   `Authorization`;
2. removes the secret and commitment from object state;
3. privately constructs `Basic base64(secret + ":")`;
4. compares only its SHA-256 with the precommitted value using
   `hash_equals`; and
5. returns the preloaded synthetic response record.

The local variables are replaced with empty strings in a `finally` block.
This is containment and short lifetime, not a claim of guaranteed memory
zeroization: PHP strings are immutable values managed by the runtime.

## One-attempt orchestration

The synthetic adapter runs P3E-3 encoding before touching the transport. An
invalid checkout is therefore refused with zero transport calls. A valid
request may invoke the concrete transport exactly once; reuse fails closed.
The returned synthetic bytes pass through the P3E-3 decoder, a proof-only
decoded-transcript bridge, and the P3E-2 sealed executor.

The final result retains the closed P3E-2 shape. It exposes no Authorization
header, credential, commitment, raw response, exception message, or provider
error body. Transport exceptions and unusable response bytes are
indeterminate and never authorize retry. Provider `4xx` remains a definite
refusal; provider `5xx` remains indeterminate because the external outcome
could be uncertain.

## Explicit stop

P3E-4 adds no cURL, stream, socket, DNS, TLS handshake, HTTP client,
environment/request-global resolver, real or test Stripe key, Stripe Sandbox
contact, retry, Checkout Session, persistence, webhook, Store Lite service
invocation, order transition, browser redirect, client configuration,
deployment, or payment.

The source remains outside `package/`; package identity and version stay
unchanged. A later gate may prove an HTTP implementation only against a local
loopback fixture before any separate, explicitly authorized Stripe Sandbox
contact.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The focused test covers synthetic-only configuration, provider-key-prefix
refusal, exact one-use Basic construction, commitment mismatch, credential
discard on success and failure, forged Authorization refusal, preflight
short-circuit, closed output, response-status semantics, malformed raw bytes,
no retry, and prohibited network/database/output primitives.

## Official references reviewed

- [Stripe API authentication](https://docs.stripe.com/api/authentication)
- [Stripe error handling](https://docs.stripe.com/error-handling)
- [Stripe advanced error handling](https://docs.stripe.com/error-low-level)
