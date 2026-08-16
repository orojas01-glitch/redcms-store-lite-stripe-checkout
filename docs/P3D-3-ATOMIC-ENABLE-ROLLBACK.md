# P3D-3 Atomic Enable And Rollback

Status: P3D-3 proves the exact installed-disabled Stripe Checkout adapter can
consume one fresh value-free P3D-2 plan and atomically commit its enabled state
with one bounded audit fact. Every contained failure restores the prior facts.

## Rehearsed sequence

The P3D-3 wrapper creates one fresh `redcms_stripe_p3d3_*` database and staged
project. It first runs the complete P3D-2 fixture to establish the reviewed
enabled Store Lite dependency, installed-disabled adapter, typed settings, and
opaque-reference availability. A separate process then:

1. reconstructs the exact enable-ready plan from current database, registrar,
   ingress, configuration, authority, and dependency evidence;
2. refuses an unrelated plan SHA-256 before lifecycle or audit mutation;
3. observes the compare-and-swap's temporary enabled state, injects a failure,
   and proves the complete database fingerprint returns to its baseline;
4. inserts the real bounded enable audit inside the transaction, reports audit
   failure, and proves both that row and the enabled state roll back;
5. revalidates the unchanged plan under the lifecycle and package locks;
6. commits exactly one `installed_disabled` to `enabled` transition and one
   `addon.enable.completed` / `payment_adapter_enabled` audit fact; and
7. refuses replay without adding a second audit or changing committed facts.

The shared exit trap removes the scoped grant, disposable database, staged
project, and rehearsal-only keep-awake process. It also requires the configured
primary database fingerprint to remain unchanged.

## Explicit stop

The enabled lifecycle row exists only in the disposable P3D-3 database, which
is deleted before the rehearsal exits. Enablement does not publish the adapter
or route into a later request, invoke either registered handler, resolve an
opaque reference, inspect a provider request, open a network connection, call
Stripe, change a Store Lite order, create a payment, or deploy any client.

P3D-3 therefore proves atomic persistence only. The next separately reviewed
offline gate is enabled request-local registration ownership plus exact Store
Lite service binding, still with inert handlers and no secret/provider access.

## Run the rehearsal

```sh
tests/p3d3-atomic-enable-rollback-rehearsal.sh
```
