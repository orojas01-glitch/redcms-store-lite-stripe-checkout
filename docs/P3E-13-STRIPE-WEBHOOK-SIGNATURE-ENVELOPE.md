# P3E-13 Stripe Webhook Signature Envelope

Status: adopted inertly in Stripe adapter `0.1.12`.

This gate verifies the authentication envelope around one Stripe Sandbox v1
webhook body. A future dedicated core ingress boundary must supply the exact
untouched UTF-8 request bytes, the `Stripe-Signature` header, one request-local
endpoint secret, and the server receipt timestamp.

The verifier:

- bounds the raw body to 262,144 bytes and the header to 4,096 bytes;
- accepts one timestamp and at most eight distinct lowercase-hex `v1`
  signatures, allowing bounded secret rotation;
- computes HMAC-SHA256 over `timestamp.raw-body` and uses constant-time
  comparison;
- requires the signature timestamp within five minutes of server receipt;
- strictly decodes UTF-8 JSON with duplicate-key, depth, and value limits;
- requires a non-live v1 Event rendered as either the historical
  `2024-09-30.acacia` contract or the current Sandbox Dashboard
  `2026-07-29.dahlia` contract;
- admits only the five P3E-12 subscription event and object-type pairs; and
- returns only hashes, timing, size, API version, event type, and object type.

The result contains no raw body, signature header, endpoint secret, decoded
event, provider id, or customer data. Invalid signatures, changed bytes,
malformed or ambiguous headers, stale or future timestamps, duplicate JSON
keys, live-mode events, unallowlisted API drift, object drift, and unsupported
events fail closed.

The package entrypoint loads the reviewed class only for integrity validation.
The declared provider-event route remains the existing throwing placeholder;
there is no typed runtime operation, request reader, secret resolver, response,
network access, Stripe contact, database write, payment, or deployment.

The focused synthetic fixture covers valid and rotated signatures plus the
closed failure cases. No real webhook secret or provider data is used.

## Next gate

RED-CMS needs a dedicated bounded webhook request object that preserves up to
262,144 raw bytes without expanding the normal 16 KB adapter payload contract.
Only after that core boundary and a replay ledger pass offline review can a
non-operational route rehearsal be considered.
