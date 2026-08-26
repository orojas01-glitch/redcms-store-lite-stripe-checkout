# P3E-11 Subscription Real-POST Operation

Status: adopted offline in installable Stripe adapter `0.1.10`.

The adapter now contains one provider-capable operation for the exact P3E-10
subscription Checkout contract:

`subscription.checkout.create-sandbox-real-post`

The operation accepts only a current provider-neutral intent, its exact
published monthly/yearly offer, the fixed Sandbox policy, and three core-owned
execution hashes. It regenerates the canonical `mode=subscription` request,
derives one deterministic Stripe idempotency key, and supplies the request to
the existing one-use HTTPS transport. The transport now admits either its
historical one-time `mode=payment` form or this exact recurring form; ambiguous
or mixed modes fail closed.

After one attempted exchange, the operation decodes the bounded wire response,
restores the expiry fields, and reuses the P3E-10 response validator. A valid
result returns the Session reference and Checkout URL only as transient memory
data with browser authorization still false. Raw response bytes/headers,
credentials, authorization headers, customer data, and payment facts are not
returned or stored.

Any failure after the exchange begins is `indeterminate` and never authorizes a
retry. Invalid execution evidence fails before the exchange. The typed adapter
resolves only `stripe.secret-key`, requires the webhook secret to remain absent
from that scoped operation, and cannot proceed without owner-entered secret
availability.

The 12-assertion fixture uses only a sealed in-memory exchange. It proves the
exact recurring request, one call, deterministic idempotency, transient
handoff, redacted result, pre-attempt refusal, malformed/throwing containment,
no retry, and inert typed behavior without a secret. It performs no DNS, TLS,
HTTP, Stripe contact, secret resolution, Checkout creation, payment, webhook,
browser action, client change, or deployment.

## Remaining gates

1. Core must add a durable, restartable subscription provider-attempt claim and
   result coordinator for adapter `0.1.10`.
2. A network-disabled disposable rehearsal must prove success and indeterminate
   recovery without exposing a URL or credential.
3. One fresh owner authorization may then permit one Stripe Sandbox attempt.
4. Browser navigation, return handling, signed webhooks, entitlement activation,
   and demo deployment remain separately gated.
