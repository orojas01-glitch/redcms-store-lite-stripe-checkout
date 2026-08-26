# P3E-10 Subscription Checkout Source Contract

Status: retained in installable Stripe adapter `0.1.10`. The prior
`0.1.8` D4D diagnostic/recovery attempt remains expired and cannot authorize
this new package identity.

P3E-10 maps one current Store Lite `0.1.48` provider-neutral subscription
intent plus its exact published offer state into a canonical hosted Stripe
Checkout request. It performs no package registration, secret resolution,
database access, provider request, Checkout Session creation, customer or
subscription creation, browser navigation, Store Lite mutation, webhook
handling, or deployment.

The package exposes two typed offline-only operations: exact contract
preparation and synthetic response acceptance. Both return flattened bounded
evidence compatible with the core adapter result limit. Canonical form bytes
stay inside the adapter; only the synthetic accepted handoff may carry the
short-lived Checkout URL.

## Input boundary

The intent contains only:

- one opaque `sint_` reference derived by a future core coordinator;
- the current intent-state SHA-256;
- the exact Store Lite offer-state SHA-256; and
- status `requested`.

The offer must retain the exact Store Lite normalized shape and state hash. It
must be published and available, use the installation currency, and declare
only a monthly or yearly fixed amount. Draft, archived, unavailable, stale,
foreign, expanded, or malformed intent/offer data fails closed.

## Canonical Stripe request

The pure contract fixes:

- `POST https://api.stripe.com/v1/checkout/sessions`;
- API version `2024-09-30.acacia` and form encoding;
- hosted `mode=subscription` with `submit_type=subscribe`;
- one quantity-one inline recurring Price using `month` or `year`;
- same-origin HTTPS success and cancellation URLs;
- intent and offer state hashes as Session and Subscription metadata;
- one 30-minute expiry with no recovery and no retry; and
- TLS verification, no proxy, and no redirects.

No customer, email, phone, tax, discount, trial, provider Price ID, secret, or
browser-supplied amount can enter the form. Stripe Checkout may collect the
customer details required for a subscription when the future provider gate is
authorized.

## Transient redirect handoff

Synthetic acceptance requires an open, unpaid, non-live Stripe Checkout
Session in subscription mode with exact intent, offer, amount, currency,
metadata, and expiry agreement. Only `https://checkout.stripe.com/c/pay/...`
is accepted.

The resulting handoff carries the short-lived Checkout URL only in memory. It
fixes `location.assign`, `Cache-Control: no-store`, and
`persistCheckoutUrl=false`. Browser navigation remains unauthorized in this
gate; a later core coordinator must durably claim one attempt, invoke the
adopted package operation, consume the handoff, clear the URL, and return a
bounded redirect response.

## Next gates

1. Add the core durable, restartable provider-attempt coordinator for the new
   P3E-11 real-POST operation.
2. Add a network-disabled end-to-end provider-operation rehearsal.
3. Separately authorize one Stripe Sandbox subscription Checkout attempt.
4. Require signed webhook agreement before any entitlement becomes active.

## Official Stripe references

- [Create a Checkout Session](https://docs.stripe.com/api/checkout/sessions/create)
- [Checkout Session object](https://docs.stripe.com/api/checkout/sessions/object)
- [Checkout subscriptions](https://docs.stripe.com/payments/checkout/subscriptions)
