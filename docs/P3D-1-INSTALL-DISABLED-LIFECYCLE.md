# P3D-1 Install-Disabled Lifecycle Rehearsal

Status: P3D-1 proves that the exact P3C-4 adapter artifact can be installed by
current RED-CMS into `installed_disabled` on one fresh disposable database.
It does not configure or enable the adapter.

## Purpose

P3C-4 validated discovery, integrity, the closed payment-adapter profile, and
the registrar with synthetic prior-gate evidence. P3D-1 crosses only the next
boundary: real core and package migrations in a disposable client-shaped
project.

The wrapper stages local copies of:

- clean RED-CMS core;
- Store Lite 0.1.35 as the separately installed dependency; and
- Stripe Checkout adapter 0.1.0.

Nothing is copied into the clean starter or either source package checkout.

## Rehearsed sequence

The fixture:

1. creates a uniquely named `redcms_stripe_p3d1_*` database and grants the
   existing application account access only to that schema;
2. imports the clean starter schema and applies all 46 current core migrations;
3. discovers the two staged packages and requires exact manifest integrity;
4. proves adapter installation is blocked while Store Lite is not enabled;
5. installs Store Lite with its eleven migrations, stores only its five
   ordinary settings, and enables that dependency;
6. derives an adapter plan bound to the exact Store Lite version, manifest,
   inventory, enabled state, and two pending adapter migrations;
7. proves changed plan evidence is refused without database drift;
8. installs the adapter and requires `installed_disabled` with two exact
   migration-ledger entries and two adapter-owned InnoDB tables;
9. proves repeat installation planning is refused without drift;
10. derives real database-bound payment-adapter readiness evidence;
11. validates the exact adapter and event-route registrations in the discarded
    request-local registrar; and
12. revokes the scoped grant, drops the disposable database, removes the staged
    project, stops the rehearsal-only keep-awake process, and verifies the
    configured primary database fingerprint is unchanged.

## Disabled-state boundary

Installation does not load `package/addon.php`. The later registration-only
check may load the registrar, but it invokes neither handler and publishes no
adapter or route ownership. The adapter ends disabled and has zero stored
settings, including zero secret references or secret values.

P3D-1 performs no adapter enablement or enablement plan, secret availability
check, secret resolution, server ingress, request parsing, webhook signature
verification, HTTP request, Stripe SDK/provider access, Store Lite payment
event invocation, browser redirect, payment, or client deployment.

## Run the rehearsal

From the adapter repository:

```sh
tests/p3d1-install-disabled-rehearsal.sh
```

The wrapper requires the local RED-CMS core and Store Lite repositories, the
configured local MySQL development service, and the reviewed FrankenPHP CLI.
It refuses unsafe or pre-existing database names and owns cleanup through an
exit trap.
