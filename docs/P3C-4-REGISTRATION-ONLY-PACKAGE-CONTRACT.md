# P3C-4 Registration-Only Package Contract

Status: P3C-4 assembles the first RED-CMS-discoverable Store Lite Stripe
Checkout adapter package. It validates the exact manifest, file inventory,
payment-adapter profile, and registrar shape without installing, enabling, or
publishing runtime behavior.

## Purpose

P3C-1 through P3C-3 established pure response/event normalization and two
adapter-owned storage schemas. P3C-4 packages only the minimum material that
RED-CMS must inspect before a later lifecycle rehearsal:

- one schema-version 1 `addon.json` manifest;
- one `addon.php` registrar;
- the package identity record; and
- the two immutable migrations from P3C-2 and P3C-3.

The four payload files other than `addon.json` are covered by exact SHA-256
inventory entries. RED-CMS performs manifest discovery and integrity checking
without loading package PHP.

## Closed manifest surface

The package declares exactly:

- package id `redcms.store-lite-stripe-checkout`, adapter type, and version
  `0.1.0`;
- RED-CMS `>=5.1 <6.0` and PHP `>=8.2 <9.0` compatibility;
- one adapter capability,
  `redcms.store-lite-stripe-checkout/checkout`;
- one required `redcms.store-lite` dependency at `>=0.1.35 <1.0`;
- one ordinary return-origin setting with a null default;
- two secret-reference setting declarations with no secret or default value;
- the two existing migrations;
- one public POST server-signature event-route declaration;
- one outbound-host declaration, `api.stripe.com`; and
- retain-by-default uninstall behavior with no explicit purge.

It declares no components, services, administrator tools, permissions, public
mutation contracts, jobs, or assets.

## Contained registrar validation

After separately supplied database-readiness evidence passes, RED-CMS may load
`addon.php` only through its registration-only validator. The registrar adds
the exact adapter id and event-route id to a request-local registry. Core
reduces that registry to identifiers, compares it with the manifest, records
hash evidence, and discards it.

Neither handler is invoked or published by this operation. Both P3C-4 handler
callables fail closed with a `LogicException` if any caller attempts to invoke
them before a later operational gate.

## Explicit exclusions

P3C-4 does not run an installation or migration, write a registry/database
row, change lifecycle state, enable the package, publish an adapter or route,
read request globals, resolve a secret reference, verify a webhook signature,
load an SDK, create an HTTP client, open a network connection, contact Stripe,
invoke Store Lite, change an order, handle a browser return, create a payment,
or deploy to any client.

The manifest's route and outbound host are declarations for later validation,
not active runtime capabilities. Atomic enablement and supported server-event
ingress remain hard blockers.
