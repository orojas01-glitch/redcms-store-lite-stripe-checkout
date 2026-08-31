# P3E-18 Subscription Catalog Price Binding

Status: adapter `0.1.20` catalog Price lifecycle verified in the isolated
`demo.red-sphere.com` Stripe Sandbox; live mode remains unauthorized.

## Purpose

Store Lite subscription offers remain provider-neutral. This adapter layer can
now bind one exact offer to one existing Stripe Sandbox Product and recurring
Price without creating a second inline Stripe Product or Price for each
Checkout Session.

The closed binding contains only:

- Store Lite offer ID;
- Stripe Product ID and Price ID;
- currency, integer minor-unit price, and monthly/yearly period;
- `active=true`; and
- `livemode=false`.

The Product and Price identifiers are configuration, not credentials. They
belong in server-local configuration and bounded deployment evidence, not
browser fields. Stripe API and webhook secrets retain their existing private
owner-entered boundary.

## Request contract

The catalog path retains hosted `mode=subscription`, quantity one, same-origin
success/cancel URLs, Store Lite intent and offer hashes as Session and
Subscription metadata, one 30-minute expiry, deterministic idempotency, and no
retry. It sends `line_items[0][price]` and refuses all `price_data` fields.

A mismatched offer, Product/Price shape, currency, amount, period, active flag,
or live-mode flag fails before secret resolution or transport. The adapter does
not retrieve or mutate the Stripe catalog at this gate.

## Verified scope

- catalog contract: exact existing-Price request plus mismatch refusal;
- catalog provider operation: one sealed exchange, transient redirect, no
  inline Price, and foreign-offer refusal;
- real-post transport acceptance for the exact catalog Price request plus
  refusal of mixed inline/catalog Price bodies;
- original P3E-10 inline subscription and P3E-11 real-POST contract remain
  green; and
- source and installable package copies remain byte-identical where required.

The demo acceptance used the configured $59/month catalog Price. Checkout
creation returned 200, `checkout.session.completed` and `invoice.paid` were
delivered with 200 responses, Store Lite reached `active/active`, and immediate
Sandbox cancellation delivered `customer.subscription.deleted` with 200 and
reached `canceled/revoked`. The public offer remains available for repeat
Sandbox testing. Production credentials, live mode, refunds, disputes, and
customer provisioning remain separate release gates.
