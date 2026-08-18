# P3E-8B3A Provider Transport Package Adoption

Status: adapter version `0.1.2` now carries the already-reviewed P3E-8B1
read-only transport and bounded outcome classes in its integrity-checked
payload. The package handler remains refusal-only and cannot invoke either
class.

## Purpose

RED-CMS core P3E-8B2 proved the full claim, durable-start, scoped-secret,
typed-invocation, bounded-outcome, and permanent no-retry lifecycle against a
sealed in-process double. P3E-8B3A makes the reviewed adapter-side transport
source distributable without joining it to that lifecycle.

This separation ensures that installing, upgrading, discovering, validating,
enabling, registering, or invoking the legacy `contract.probe` operation on
`0.1.2` cannot contact Stripe.

## Package delta

Version `0.1.2` changes only package identity and code inventory:

- `StripeSandboxReadOnlyProbeTransport.php` is a byte-identical copy of the
  reviewed P3E-8B1 one-use GET transport;
- `StripeSandboxReadOnlyProbeOutcomeGate.php` is a byte-identical copy of the
  reviewed closed status projection;
- `addon.php` loads both final classes during contained registration;
- the integrity inventory expands from five to seven exact payload files; and
- `identity.json` records provider transport as adopted but provider-contact
  execution and network contact as excluded.

Both existing migration paths and SHA-256 values remain unchanged. No schema,
setting, dependency, route, permission, public mutation, job, asset, outbound
host, or uninstall policy changes.

## Still disconnected

The typed adapter accepts only `contract.probe` with empty input. It privately
checks its two configured secret references and returns the unchanged
`provider_transport_disabled` refusal. It does not name or accept a sandbox
provider-contact operation, construct the transport, project an outcome, or
read Store Lite state.

The historical P3E-6 readiness plan remains bound to package `0.1.1` with
runtime provider transport `disabled`. Therefore it cannot authorize or
describe `0.1.2`. Core P3E-7, P3E-8A, and P3E-8B2 also remain bound to the old
identity and refuse this package for contact execution.

## Acceptance

The focused 37-assertion fixture proves:

- exact `0.1.2` manifest and non-executing identity;
- the unchanged two migration checksums;
- the exact seven-file integrity inventory and every file hash;
- byte-identical source/package transport and outcome classes;
- one declared outbound host with no permission, mutation, job, or asset;
- the entrypoint inventories both classes while the handler remains
  refusal-only;
- absence of environment, database, request-global, shell, logging, and
  credential-literal access in the typed handler;
- unchanged non-executing P3E-6 plan behavior;
- construction without transport exchange; and
- closed outcome projection using synthetic evidence only.

The complete adapter suite must also preserve installation, enablement,
runtime registration, secret bootstrap, typed offline invocation, readiness,
and P3E-8B1 regressions.

No DNS lookup, TLS handshake, HTTP request, Stripe contact, secret resolution,
handler invocation, database mutation, Checkout creation, payment, webhook,
Store Lite mutation, browser route, client activation, or deployment occurs.

## Next stop

P3E-8B3B may define a new package identity and exact handler operation plus a
matching core runner. It must require the still-active immutable authorization
and claim, commit the durable start before secret or handler access, preserve
the one-attempt/no-retry rule, and pass synthetic transport evidence before
any real provider request is approved. P3E-8B3A itself authorizes no contact.
