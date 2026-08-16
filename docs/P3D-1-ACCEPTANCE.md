# P3D-1 Acceptance Record

Date: 2026-08-16

## Result

The offline install-disabled lifecycle rehearsal passed 24 focused assertions
against a fresh disposable database. The retained P3C suites passed 236
assertions, for 260 adapter assertions across the current unit and lifecycle
gates. Ten PHP files passed syntax validation, the wrapper passed Bash syntax
validation, and `git diff --check` passed.

The accepted run used:

- adapter 0.1.0 at the exact P3C-4 integrity hashes;
- Store Lite 0.1.35 as the separately staged and enabled dependency;
- all 46 current RED-CMS core migrations;
- all eleven Store Lite migrations; and
- both adapter migrations.

It proved:

- dependency refusal before Store Lite enablement without database drift;
- exact package discovery, versions, migration counts, and payment-adapter
  profile;
- Owner-authorized Store Lite installation and enablement in the disposable
  database only;
- exact dependency evidence in the adapter install plan;
- changed-plan refusal before mutation;
- real adapter installation ending `installed_disabled`;
- exact immutable migration paths and SHA-256 ledger values;
- exactly two adapter-owned InnoDB tables;
- zero adapter setting rows and zero secret values/references;
- only bounded install-started and install-completed audit facts;
- no adapter PHP load during installation;
- repeat-install refusal without drift;
- real read-only P3A database evidence for one dependency, two migrations, and
  two InnoDB tables;
- real registration-only evidence for exactly one adapter and one event route;
- no handler invocation, runtime publication, state mutation, secret
  resolution, network access, or route exposure during registrar validation;
  and
- an ending state of enabled Store Lite plus disabled adapter before cleanup.

## Cleanup and isolation

The accepted wrapper reported:

```text
Stripe P3D-1 cleanup passed: database:0 grant:0 staged-project:0 process:0 primary:unchanged
```

An independent post-run query found zero schemas matching
`redcms_stripe_p3d1_%`. RED-CMS core and Store Lite remained clean on their
respective `main` branches. No source checkout received an `addons/` directory.

No credential, Stripe object, provider request, payment, Store Lite order,
browser state, public route, client package, or deployment was created. The
demo installation and every other client installation/database were outside
the rehearsal.

This closes only P3D-1. The next separately reviewed gate is value-free
adapter configuration and atomic-enable dry-run readiness. Real secret values,
secret resolution, ingress, handler invocation, provider communication,
Stripe Sandbox access, and client deployment remain blocked.
