# P3C-3 Event Receipt And Replay Storage Contract

Status: P3C-3 adds one immutable adapter-owned event-receipt schema and one
pure receipt-record planner. The package remains non-installable and no
migration or receipt write is executed by this slice.

## Purpose

P3C-1 normalizes only an already-signature-verified, reconciled, non-live
provider event into Store Lite's exact provider-neutral vocabulary. P3C-3
defines the smaller immutable receipt that the adapter may later retain before
a separately reviewed transaction invokes Store Lite.

The migration creates exactly one table:
`RED_Addon_StoreLite_Stripe_Event_Receipts`. It links only to the adapter-owned
P3C-2 attempt table with restrictive foreign-key behavior.

## Stored facts

One planned receipt contains only:

- the adapter attempt record and SHA-256 client-scope relation;
- fixed `sandbox` provider environment;
- opaque provider event and Checkout Session references;
- opaque event, transport-body, and verification evidence SHA-256 values;
- Store Lite order id and immutable snapshot SHA-256;
- one reviewed provider event type/status and normalized P0 outcome;
- integer amount, uppercase currency, and bounded occurrence/receipt times;
  and
- initial `unseen` replay and `normalized` processing facts.

Unique client/event-reference and client/event-evidence relations fail closed
when the same event is presented again. P3C-3 does not claim that a receipt was
applied to Store Lite and does not mutate the attempt or order.

## Closed event projection

Only these exact combinations are admissible:

| Stripe event | Provider status | Normalized outcome |
| --- | --- | --- |
| `checkout.session.completed` | `complete_paid` | `paid` |
| `checkout.session.async_payment_failed` | `failed` | `failed` |
| `checkout.session.expired` | `expired` | `expired` |
| `charge.refunded` | `refunded` | `refund_confirmed` |
| `charge.dispute.created` | `disputed` | `reversal_reported` |

The pure planner reruns P3C-1 verification-state, replay, client/order,
snapshot, Session, amount, currency, outcome, live-mode, and timestamp checks
before returning a deterministic record plan.

## Forbidden storage and runtime

The schema and planner contain no raw body, payload, signature, secret,
Checkout URL, provider error, customer identity/contact data, card, security
code, wallet detail, bank account, access token, browser query, or unredacted
provider response. Only the SHA-256 of future verified transport material may
enter the record.

Both migrations pass the current RED-CMS migration guard, but the package still
has no `addon.json` or `addon.php`. Core cannot discover, apply, register, or
invoke them. P3C-3 adds no database connection, transaction/writer, event
endpoint, request parsing, signature verification, secret reference, SDK, HTTP
client, outbound connection, provider lookup, Store Lite invocation, order
transition, browser handler, Stripe object, simulated/live payment, client
deployment, or production capability.
