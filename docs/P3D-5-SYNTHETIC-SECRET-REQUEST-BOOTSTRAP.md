# P3D-5 Synthetic-Secret Request Bootstrap

Status: P3D-5 proves the exact enabled Store Lite and Stripe adapter artifacts
can pass the full production request-bootstrap path with two explicitly
synthetic server-local values and no capability invocation.

## Rehearsed sequence

The P3D-5 wrapper creates one fresh `redcms_stripe_p3d5_*` database and staged
project. It runs the complete P3D-2 readiness and P3D-3 atomic-enable fixtures,
then a separate P3D-5 process:

1. proves Store Lite 0.1.35 and adapter 0.1.0 are both enabled and registry-
   current;
2. refuses to replace any ambient add-on secret environment;
3. creates two random process-local synthetic values for the exact stored
   placeholder references;
4. calls the production `red_addon_runtime_request_bootstrap()` path;
5. proves Store Lite-before-adapter order and exact adapter, provider-event
   route, and `commerce.orders` service ownership;
6. verifies the registered handler identities without invoking them;
7. proves only the adapter receives a private package-bound two-setting secret
   access object;
8. resolves each synthetic setting privately and compares only SHA-256 facts;
9. refuses an undeclared setting without returning a value;
10. proves snapshots and debug evidence contain neither value nor reference;
11. proves repeated request bootstrap is idempotent and database-free;
12. removes the request context and both synthetic environment entries; and
13. proves a later bootstrap without those values fails closed without a
    context, disclosure, or database write.

The shared exit trap removes the scoped grant, disposable database, staged
project, and rehearsal-only keep-awake process. It also requires the configured
primary database fingerprint to remain unchanged.

## Explicit stop

The synthetic bytes exist only in the P3D-5 PHP process. They are randomized at
runtime, never committed, logged, printed, audited, persisted, sent to a
package handler, or copied into the clean starter. The fixture removes the
environment entries before its negative proof and again on every exit path.

No adapter invocation, service invocation, route dispatch, provider request,
network access, Store Lite business-row mutation, browser state, real client
credential, client configuration, or deployment occurs. A later explicit gate
must define and validate an operational adapter invocation contract before any
provider transport can be considered.

## Run the rehearsal

```sh
tests/p3d5-synthetic-secret-bootstrap-rehearsal.sh
```
