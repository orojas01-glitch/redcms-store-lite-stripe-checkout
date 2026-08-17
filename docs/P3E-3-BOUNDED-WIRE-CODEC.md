# P3E-3 Bounded Stripe Wire Codec

Status: P3E-3 is a dependency-free canonical form encoder and strict synthetic
JSON response decoder. It provides no HTTP implementation and does not alter
the installable adapter `0.1.1` under `package/`.

## Purpose

P3E-1 fixed the non-secret request plan and bounded response projection. P3E-2
proved one-attempt outcome handling through a sealed in-memory double. P3E-3
now proves the byte boundary between those contracts without opening a socket
or resolving a credential.

## Request encoding

The encoder calls the P3E-1 planner and accepts only its validated form map. It
serializes fields in the planner's deterministic insertion order using
RFC-1738 form encoding, the encoding PHP uses for
`application/x-www-form-urlencoded` bodies. The result includes:

- exact `POST` method and Stripe API v1 Checkout URL;
- the reviewed non-secret headers and authorization-setting descriptor;
- at most 65,536 canonical body bytes;
- exact body byte count and SHA-256;
- the P3E-1 plan SHA-256; and
- the fixed transport policy.

No Authorization header or test/live/restricted Stripe key shape can enter the
wire request. Invalid order arithmetic or policy produces no partial body.

## Strict bounded JSON

The decoder accepts at most 262,144 UTF-8 bytes and uses a small recursive-
descent parser rather than permissive object decoding. It enforces:

- at most 16 nested levels and 4,096 values;
- JSON grammar for objects, arrays, strings, numbers, booleans, and null;
- valid escapes and Unicode surrogate pairs through the PHP JSON primitive;
- no unescaped control characters, non-finite numbers, or trailing bytes; and
- no duplicate decoded key at any object depth, including escape-equivalent
  keys such as `a` and `\u0061`.

Every parsing failure is uniformly `response_unusable`; no parser detail or
partial object escapes.

## Response headers and projection

The codec accepts a closed synthetic wire record containing status, normalized
lowercase header pairs, raw body, TLS version, and redirect count. It bounds
header count, names, values, and total bytes. Duplicate `content-type` or
`request-id` is ambiguous and fails; repeated noncritical headers are ignored
after validation. HTTP transport remains responsible for normalizing header
names before this future boundary.

For exact HTTP 200, the decoder projects only the eleven fields consumed by
the P3E-1 response gate. Realistic extra Stripe Session fields are ignored.
Missing, malformed, mismatched, or live-mode fields are later rejected by the
existing response gate and remain indeterminate through P3E-2.

Provider `4xx` bodies are never decoded or returned; only the bounded envelope
and an empty projection reach the P3E-2 definite-refusal path. Provider `5xx`,
oversized data, invalid TLS, redirects, unexpected status, ambiguous headers,
or unusable JSON produce an indeterminate transcript with no envelope or
projection.

## Explicit stop

P3E-3 adds no cURL, stream, socket, DNS, TLS handshake, HTTP client, credential
resolver, environment/request global, API-version selection, Stripe Sandbox
contact, retry, Checkout Session, attempt persistence, webhook, Store Lite
service invocation, order transition, browser redirect, client configuration,
deployment, or payment.

The next gate may define a synthetic credential-containment and byte-transport
adapter around this codec. Actual Stripe Sandbox contact still requires a
separate explicitly authorized gate and isolated test configuration.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
```

The focused test covers deterministic request bytes, exact hashes, extra Stripe
fields, nested duplicate keys, escape and number ambiguity, invalid UTF-8,
depth/token/body/header bounds, `4xx` refusal, indeterminate outcomes, and the
complete codec-to-sealed-executor success path.

## Official references reviewed

- [Stripe API v1 form and JSON encoding](https://docs.stripe.com/api-v2-overview)
- [Create a Checkout Session](https://docs.stripe.com/api/checkout/sessions/create)
- [Stripe advanced error handling](https://docs.stripe.com/error-low-level)
