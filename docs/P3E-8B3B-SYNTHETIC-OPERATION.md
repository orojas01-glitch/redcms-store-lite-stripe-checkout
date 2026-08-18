# P3E-8B3B Synthetic Package Operation

Status: adapter version `0.1.3` adds one typed synthetic-only
provider-contact operation. It validates the exact readiness plan and scoped
restricted-test key, executes a one-use in-memory proof, and returns the same
bounded outcome shape required by core. It cannot construct or call provider
transport.

## Purpose

P3E-8B3A made the reviewed provider-capable transport source distributable
while leaving the package handler refusal-only. Core P3E-8B2 proved its
authorization, claim, durable-start, scoped-secret, typed-invocation, bounded
outcome, and permanent no-retry lifecycle only against a temporary loopback
handler.

P3E-8B3B provides the missing real-package integration target without
authorizing network contact. A following core gate can invoke the distributed
adapter and prove its existing ledger lifecycle before a provider transport
operation exists.

## Exact operation

The typed handler adds only:

`provider-contact.read-only-probe-synthetic`

It requires an exact input containing:

- `contactTarget=synthetic-package`;
- the complete value-free contact plan;
- the canonical plan SHA-256;
- the exact claim-state SHA-256; and
- the exact execution-start-state SHA-256.

Input expansion, changed target, malformed hashes, or a plan/hash mismatch
fails before secret access.

## Scoped secret rule

The operation requires `stripe.secret-key` to resolve and explicitly requires
`stripe.webhook-secret` to remain unavailable. Normal unscoped package secret
access therefore fails closed. Only the core boundary that supplies access
restricted to the one key can invoke the proof.

The synthetic executor accepts only the restricted-test key shape. It does not
store, hash, log, serialize, or return the value. The handler and executor
contain no environment reader or secret-reference resolver.

## Synthetic execution

The one-use final executor validates the complete plan for:

- package `redcms.store-lite-stripe-checkout` version `0.1.3`;
- runtime provider transport `synthetic_only`;
- the fixed read-only GET target and expected resource miss;
- exact TLS, proxy, redirect, timeout, and response bounds; and
- one attempt with retry, mutation, Checkout creation, payment, webhook, live
  mode, and client deployment false.

It then creates fixed in-memory 404 evidence and passes that evidence through
the reviewed bounded outcome gate. The result reports
`contactTarget=synthetic-package`, `networkAccess=false`,
`providerContact=false`, and `retryAuthorized=false`. A used executor cannot
run again.

The existing `contract.probe` operation remains unchanged. The actual
`StripeSandboxReadOnlyProbeTransport` is still present in the package but is
not referenced or constructed by the handler or synthetic executor.

## Acceptance

The focused 40-assertion fixture proves:

- provider/network/environment/database/request/shell primitives are absent
  from the synthetic path;
- credential-shaped literals are absent;
- the actual provider transport class is unreachable from the synthetic path;
- exact `0.1.3` / `synthetic_only` readiness;
- one bounded synthetic resource-miss outcome;
- no credential bytes in executor state or output;
- permanent one-use behavior;
- changed plan-field refusal;
- successful typed invocation only with access scoped to
  `stripe.secret-key`;
- refusal when the webhook secret is visible; and
- changed-target refusal.

The package keeps both migration paths and checksums unchanged. No database,
route, permission, public mutation, job, asset, Store Lite business row,
provider account, browser flow, client installation, or deployment is added.

## Next stop

Core P3E-8B3B may rebind its exact authorization/claim evidence to adapter
`0.1.3`, invoke this operation only after the durable execution-start commit,
and persist the bounded synthetic outcome. The real provider transport and any
Stripe request remain a separately approved P3E-8B3C gate.
