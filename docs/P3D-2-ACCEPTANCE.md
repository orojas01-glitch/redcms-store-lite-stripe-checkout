# P3D-2 Acceptance Record

Date: 2026-08-16

## Result

The value-free enable dry-run rehearsal passed 21 focused assertions against a
fresh disposable database. P3D-1 regressed green at 24 assertions, and the
retained P3C suites passed 236 assertions, for 281 assertions across the
current adapter gates. Eleven PHP files and both Bash wrappers passed syntax
validation, and `git diff --check` passed.

The accepted P3D-2 run proved:

- exact reproduction of the enabled Store Lite 0.1.35 dependency and
  installed-disabled adapter 0.1.0;
- refusal when adapter configuration rows are absent;
- exact typed storage separation between the ordinary return origin and two
  opaque placeholder `config:` references;
- two-of-two value-free availability with deterministic configuration and
  availability SHA-256 evidence;
- no configured URL or reference string in serialized availability evidence;
- precise missing-setting reporting for a partial declaration;
- incomplete availability refusal with no database drift;
- one fully valid, enable-ready, activation-supported dry-run plan;
- exact adapter, database, registrar, server-ingress, settings, secret-
  availability, and atomic-readiness gates;
- no state mutation, runtime publication, handler invocation, secret
  resolution, network access, or route exposure;
- no configured URL or reference string in the serialized plan;
- deterministic repeat planning and tampered-evidence refusal;
- changed configuration producing changed readiness hashes while lifecycle
  state remains disabled, followed by exact-plan restoration;
- fresh Owner enable-authority enforcement;
- enabled Store Lite dependency enforcement; and
- absence of an atomic apply call, adapter enable audit, or runtime ownership.

## Cleanup and isolation

The P3D-1 regression and P3D-2 run each reported exact cleanup. P3D-2 ended:

```text
Stripe P3D-2 cleanup passed: database:0 grant:0 staged-project:0 process:0 primary:unchanged
```

An independent post-run query found zero schemas matching either
`redcms_stripe_p3d1_%` or `redcms_stripe_p3d2_%`. RED-CMS core and Store Lite
remained clean on their respective `main` branches.

No real credential or secret reference, Stripe object, provider request,
payment, order change, browser state, route publication, client package, or
deployment was created. The demo installation and every other client
installation/database remained outside the rehearsal.

This closes only P3D-2. The next separately reviewed gate is P3D-3: atomic
enablement transaction and rollback evidence for this exact adapter artifact,
still without secret resolution, runtime handler invocation, provider access,
or client deployment.
