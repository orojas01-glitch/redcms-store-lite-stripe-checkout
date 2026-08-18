# P3E-9B1 Synthetic Checkout Package Adoption

Status: adapter `0.1.5` adopts the P3E-9A contract and adds one synthetic-only
typed operation. No provider transport or real Checkout operation is connected.

## Package Adoption

The installable package now contains byte-identical copies of the reviewed:

- Checkout response normalizer;
- P3E-1 transport planner;
- P3E-1 transport response gate;
- P3E-3 wire codec; and
- P3E-9A Checkout-creation contract.

The integrity inventory expands from eight to fourteen exact payload files.
The two applied migration paths and SHA-256 values remain unchanged. The
manifest still declares no permissions, public mutations, jobs, or assets.

## Exact Synthetic Operation

The typed adapter adds only:

`checkout.create-sandbox-synthetic`

It requires an exact input containing:

- `contactTarget=synthetic-checkout-package`;
- the complete closed checkout projection;
- the complete bounded creation policy;
- the exact `p3e9a-v1` mutation-aware profile; and
- the precomputed contract SHA-256.

The handler recomputes the complete P3E-9A contract and compares its hash
before secret access. A read-only credential mode, changed profile, changed
hash, extra input, non-USD checkout, customer field, or invalid expiry fails
closed before the synthetic executor runs.

## Scoped Secret Rule

The operation requires only `stripe.secret-key` and requires
`stripe.webhook-secret` to remain unavailable. The executor accepts only the
restricted-test key shape, clears the parameter before constructing synthetic
facts, and never stores, hashes, logs, serializes, or returns it.

This is a scope rehearsal, not credential provisioning. No real key is read,
created, or required by ordinary tests.

## Synthetic Execution

The one-use executor repeats the full contract and hash validation, constructs
fixed in-memory open/unpaid/non-live Session facts, and passes them back through
the adopted response gate and P3E-9A contract. The result includes only:

- the synthetic Session reference and exact expiry;
- contract, response-evidence, and result SHA-256 values;
- `checkout_contract_accepted`; and
- closed false facts for credential inclusion, Checkout URL inclusion,
  network, provider contact/mutation, Checkout creation, payment, webhook,
  browser navigation, order mutation, retry, and client deployment.

`executionPerformed=true` means only that the in-memory package proof ran. It
does not mean a provider request or mutation occurred. A used executor cannot
run again.

The handler does not expose `checkout.create-sandbox`; that real operation is
still unsupported.

## Preserved Historical Profiles

The existing contract probe, P3E-8B3B synthetic read-only probe, and
P3E-8B3C1 provider read-only operation remain separately named. The reviewed
read-only transport, outcome gate, and synthetic executor files remain
byte-identical. P3D and B3C3B rehearsal wrappers stay frozen at their historical
versions and are not rewritten by this package adoption.

## Next Stop

Core P3E-9B2 may add one non-networking runner for exact adapter `0.1.5` and
operation `checkout.create-sandbox-synthetic`. It must use an integrity-checked
in-memory package handler, prove the P3E-8 read-only runners reject the new
profile, resolve only the scoped synthetic key, and return the closed package
result without durable mutation authority. P3E-9C remains the separate gate
for new one-attempt authorization and persistent execution evidence.
