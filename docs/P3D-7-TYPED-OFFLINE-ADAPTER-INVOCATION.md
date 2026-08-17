# P3D-7 Typed Offline Adapter Invocation

Status: P3D-7 updates the separately distributed adapter to version `0.1.1`
and adopts the RED-CMS P3D-6 typed adapter boundary. It deliberately exposes
only one value-free contract probe and keeps provider transport disabled.

## Purpose

P3D-5 proved that a fully bootstrapped request can bind the enabled adapter to
two synthetic package-local values without invoking a handler. Core P3D-6 then
provided the reusable typed request/result boundary. P3D-7 connects those two
contracts without introducing a provider client or Store Lite business call.

`RED_CMS_Store_Lite_Stripe_Typed_Offline_Checkout_Adapter::handle()` accepts
only:

- adapter id `redcms.store-lite-stripe-checkout/checkout`;
- operation `contract.probe`; and
- an empty input object.

The handler privately resolves `stripe.secret-key` and
`stripe.webhook-secret` through the owning package's request object. It returns
no value, reference, length, hash, provider fact, or readiness flag. Complete
synthetic configuration returns the fixed typed failure
`provider_transport_disabled`; missing configuration returns
`configuration_unavailable`; every other operation or non-empty input returns
`unsupported_operation`.

## Rehearsed sequence

The wrapper creates a fresh `redcms_stripe_p3d7_*` database and staged project,
then:

1. reproduces the complete value-free P3D-2 readiness plan;
2. commits the P3D-3 enabled state and bounded audit fact atomically;
3. proves invocation is unavailable before request bootstrap;
4. creates two random process-local synthetic values;
5. runs the production request bootstrap and verifies exact Store Lite-before-
   adapter ownership;
6. invokes only `contract.probe` through `red_addon_adapter_invoke()`;
7. proves the exact `provider_transport_disabled` typed result;
8. refuses `checkout.prepare` and non-empty probe input;
9. proves results and runtime evidence disclose no value or reference;
10. proves lifecycle, audit, settings, Store Lite orders/history, adapter
    attempts, and adapter receipts remain unchanged; and
11. removes the context, synthetic environment, scoped grant, database,
    staged project, and keep-awake process exactly.

## Explicit stop

The typed handler contains no HTTP or database client, provider SDK, outbound
host, Checkout Session construction, response parser, Store Lite service call,
order data, browser state, or route dispatcher. The provider-event route still
throws its fixed non-operational refusal if called directly.

P3D-7 does not authorize Stripe Sandbox access, provider transport, signature
verification, checkout-attempt persistence, event receipt persistence, Store
Lite mutation, browser redirect, client configuration, deployment, or payment.
Any provider-transport design and any sandbox contact require their own
separately reviewed gate.

The dated P3C-4 through P3D-5 acceptance records retain their original
`0.1.0` artifact evidence. Their executable regression fixtures now replay the
same boundaries against current package `0.1.1` before P3D-7 may pass.

## Run the rehearsal

```sh
tests/p3d7-typed-offline-adapter-invocation-rehearsal.sh
```
