# P3D-2 Value-Free Enable Dry-Run

Status: P3D-2 proves complete atomic-enable readiness for the exact installed-
disabled adapter without calling the atomic apply path. It uses only non-secret
placeholder `config:` reference names in a fresh disposable database.

## Purpose

P3D-1 proved real installation, migration, database evidence, and contained
registrar validation. P3D-2 adds the remaining read-only evidence required by
the existing RED-CMS P3A-5 planner:

- one typed `checkout.return-origin` value;
- two typed `secret-reference` rows;
- value-free local declarations that both references are provisioned;
- fresh database and registration-only evidence;
- the closed server-event ingress declaration; and
- current Owner enable authority and enabled Store Lite dependency evidence.

The `config:` strings used by the fixture are names only. They do not point to
or contain a Stripe secret, webhook signing secret, token, credential, or
provider object.

## Rehearsed sequence

The P3D-2 wrapper reuses the P3D-1 exact-cleanup harness with a separate
`redcms_stripe_p3d2_*` database and fixture. It:

1. reproduces the trusted Store Lite 0.1.35 enabled dependency and adapter
   0.1.0 installed-disabled state;
2. proves enable planning refuses when adapter settings are absent;
3. stores one ordinary URL and two opaque placeholder references in their
   distinct typed columns;
4. proves both declarations are available through counts and SHA-256 evidence
   without returning their reference strings;
5. proves a partial declaration reports only the missing setting key and
   blocks enable readiness;
6. creates one complete deterministic enable-ready plan joining the exact
   adapter, database, registrar, ingress, settings, secret-availability,
   authority, and dependency evidence;
7. proves the serialized plan contains none of the configured URL or reference
   strings;
8. refuses tampered evidence;
9. proves configuration changes alter both settings and complete plan hashes
   while the adapter remains disabled, then restores the exact original plan;
10. proves revoked Owner enable authority and disabled Store Lite each block a
    fresh dry run; and
11. confirms there is no atomic apply call, enable audit, runtime adapter/route
    owner, handler invocation, secret resolution, route exposure, or network
    access.

## Explicit stop

`enableReady: true` is evidence, not activation. P3D-2 does not call
`red_addon_payment_adapter_enable_package()`. The adapter remains
`installed_disabled`, and no `addon.enable.completed` audit fact is written.

P3D-2 also performs no secret lookup, provider request, ingress capture,
signature verification, SDK loading, HTTP client operation, Store Lite payment
event invocation, browser redirect, payment, Stripe Sandbox access, client
configuration, or deployment.

## Run the rehearsal

From the adapter repository:

```sh
tests/p3d2-enable-dry-run-rehearsal.sh
```

The exit trap owns the scoped grant, disposable database, staged project, and
rehearsal-only keep-awake process. It also requires the configured primary
database fingerprint to remain unchanged.
