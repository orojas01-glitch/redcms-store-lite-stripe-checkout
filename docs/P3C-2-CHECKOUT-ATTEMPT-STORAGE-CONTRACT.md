# P3C-2 Checkout-Attempt Storage Contract

Status: P3C-2 adds one immutable adapter-owned checkout-attempt schema and one
pure record planner. The package remains non-installable and no migration is
executed by this slice.

## Purpose

P3C-1 proves strict normalization of a reviewed non-live Checkout Session
response. P3C-2 defines the smaller subset that may survive as adapter-owned
attempt evidence. The planner reruns P3C-1 validation and deliberately drops
the transient hosted Checkout URL before it creates a deterministic record
plan.

The migration creates exactly one table:
`RED_Addon_StoreLite_Stripe_Checkout_Attempts`. It is package-namespaced,
InnoDB, and contains no cross-package foreign key or business/customer data.

## Stored facts

One planned attempt contains only:

- the SHA-256 client-scope relation;
- Store Lite order id and immutable snapshot SHA-256;
- the server-derived idempotency SHA-256;
- the opaque non-live Checkout Session reference;
- integer amount and uppercase currency;
- the initial bounded state `created`;
- one response-evidence SHA-256; and
- bounded creation and expiration timestamps, at most 24 hours apart.

Unique client/order/idempotency and client/Session relations prevent a second
current commercial attempt from being represented by the same reviewed facts.
No row is inserted by P3C-2.

## Forbidden storage

The table and planner contain no raw body, payload, signature, secret,
Checkout URL, provider error, customer identity, email, phone, address, card,
security code, wallet detail, bank account, access token, browser query, or
unredacted provider response. The URL returned transiently by P3C-1 is never a
P3C-2 record field.

## Lifecycle boundary

`package/migrations/2026-08-16-create-checkout-attempts.sql` passes the current
RED-CMS migration guard, but the package still has no `addon.json` or
`addon.php`. Core cannot discover, install, enable, register, invoke, or apply
this migration. A later reviewed manifest/lifecycle gate must bind the final
immutable checksum and prove disposable-database installation.

P3C-2 adds no database connection, transaction, writer, read model, event
receipt, replay ledger, status mutation, registrar, route, webhook verifier,
signature verification, secret reference, SDK, HTTP client, network request,
browser return, Store Lite invocation, order transition, Stripe object,
simulated payment, live payment, client deployment, or production capability.
