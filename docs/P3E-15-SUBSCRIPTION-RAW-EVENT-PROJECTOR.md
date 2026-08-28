# P3E-15 Subscription Raw-Event Projector

Status: current Sandbox webhook API compatibility added in Stripe adapter
`0.1.16`.

The subscription Checkout contract now writes the opaque
`redcms_intent_reference` and the existing offer-state hash into Subscription
metadata. That correlation survives beyond the Checkout Session, allowing
later invoice and subscription events to identify the Store Lite intent
without copying customer or payment data into RED-CMS metadata.

The pure projector accepts only an already verified P3E-13 signature envelope
and the corresponding decoded Stripe Sandbox Event. It binds the event id,
type, creation time, API version, object type, and canonical object hash to the
envelope before projecting one of these five event types:

- `checkout.session.completed`;
- `checkout.session.expired`;
- `invoice.paid`;
- `invoice.payment_failed`; and
- `customer.subscription.deleted`.

Each accepted event must contain the expected intent reference, offer-state
hash, provider status, and bounded provider references. Completed Checkout
requires an expanded Subscription with an exact current-period end; the
projector will not invent entitlement time. The result is the bounded P3E-12
verified-event shape plus hash-only signature evidence. Raw event, raw body,
customer, address, and payment-method values are excluded.

The focused fixture covers all five projections, private-field exclusion,
object drift, invalid correlation metadata, byte-identical source/package
copies, and exact envelope/event binding under both the historical
`2024-09-30.acacia` and current Dashboard `2026-07-29.dahlia` API versions.
The complete adapter suite also verifies the `0.1.16` integrity inventory and
all earlier gates.

This gate adds no request reader, operational webhook route, endpoint secret
resolution, database caller, Store Lite lifecycle mutation, Stripe contact,
payment, browser action, or deployment. The next release gate is a restartable
core coordinator that joins envelope verification, transactional receipt
claim, projection, and Store Lite lifecycle application without widening
those boundaries.
