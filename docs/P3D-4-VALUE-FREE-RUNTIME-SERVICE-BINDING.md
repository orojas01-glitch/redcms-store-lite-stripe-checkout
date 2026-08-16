# P3D-4 Value-Free Runtime Service Binding

Status: P3D-4 proves the exact enabled Store Lite and Stripe adapter artifacts
can form one deterministic request-local ownership context without resolving a
secret or invoking a registered capability.

## Rehearsed sequence

The P3D-4 wrapper creates one fresh `redcms_stripe_p3d4_*` database and staged
project. It runs the complete P3D-2 readiness and P3D-3 atomic-enable fixtures,
then a separate P3D-4 process:

1. proves Store Lite 0.1.35 and adapter 0.1.0 are both enabled and registry-
   current;
2. derives the exact Store Lite-before-adapter dependency order and refuses
   namespace conflicts;
3. confirms no capability owner exists before request context installation;
4. executes only each integrity-checked registrar and checks the adapter owns
   exactly one declared adapter plus one declared route;
5. identifies the Store Lite `commerce.orders` handler as
   `RED_CMS_Store_Lite_Payment_Event_Service::handle()` without invoking it;
6. creates the core request-local context with no secret-access object;
7. proves exact adapter, route, and payment-service ownership plus manifest
   agreement;
8. removes the context and proves every owner disappears immediately; and
9. reproduces an identical second isolated context without database writes.

The shared exit trap removes the scoped grant, disposable database, staged
project, and rehearsal-only keep-awake process. It also requires the configured
primary database fingerprint to remain unchanged.

## Explicit stop

P3D-4 deliberately does not call `red_addon_runtime_bootstrap()` because the
production bootstrap resolves configured secret references for enabled secret-
capable packages. It instead uses the same integrity-checked registrar and
runtime-context classes only after proving current enabled registry evidence,
dependency order, and namespace safety.

No secret access, service invocation, adapter invocation, route dispatch,
provider request, network access, Store Lite business-row mutation, browser
state, client configuration, or deployment occurs. The next separately
reviewed gate may exercise a full request bootstrap only with explicitly
synthetic disposable secret values and still no handler or provider access.

## Run the rehearsal

```sh
tests/p3d4-runtime-service-binding-rehearsal.sh
```
