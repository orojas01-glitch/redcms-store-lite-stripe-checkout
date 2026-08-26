# P3E-12 Subscription Verified-Event Contract

Status: adopted offline in installable Stripe adapter `0.1.11`.

This gate accepts only a bounded event projection that a future ingress layer
has already signature-verified. It does not read a request, resolve the Stripe
webhook secret, parse a raw body, expose the declared provider-event route, or
contact Stripe.

The contract joins the event to Store Lite's authoritative current lifecycle
using the intent reference, offer-state hash, Checkout-reference hash, and
provider Subscription-reference hash. It maps only:

- `checkout.session.completed` to activation;
- `invoice.paid` to initial activation or renewal;
- `invoice.payment_failed` to past due and revoked access;
- `customer.subscription.deleted` to canceled and revoked access; and
- `checkout.session.expired` to an inactive expired pending Checkout.

Stripe does not guarantee event delivery order, so a first `invoice.paid`
event can activate a matching pending lifecycle. Replays, live-mode events,
foreign references, stale offer state, impossible transitions, and deliveries
outside the bounded thirty-day window fail closed.

Only provider-neutral lifecycle facts and hashes leave the contract. Raw
Checkout and Subscription references, customer data, request material,
credentials, and signatures do not.

The focused fixture passes 37 assertions. The full adapter suite also retains
all previous storage, package, transport, Checkout, and one-attempt operation
coverage. No network, webhook route, database, payment, or deployment occurs.

## Remaining gates

1. Core must load the authoritative Store Lite lifecycle and sequence this
   typed adapter projection into `subscription.event.apply`.
2. A sealed cross-package rehearsal must prove activation, replay, renewal,
   past-due, cancellation, and expiry behavior.
3. Raw-body parsing and Stripe signature verification require a separate
   reviewed ingress gate and owner-controlled webhook secret.
4. Route activation and demo deployment remain separately authorized actions.
