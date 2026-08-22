# P3E-9D4A Acceptance

The focused D4A fixture passes 89 assertions and the full adapter suite passes
1,172 assertions.

Acceptance proves:

- exact adapter/identity version `0.1.8` and 19-file package integrity;
- byte-identical source/package copies of the bounded decoder, exchange,
  transport, and operation classes;
- unchanged migrations, dependencies, settings, route, permissions, jobs,
  assets, mutations, and uninstall policy;
- one fixed POST endpoint plus required HTTPS/TLS/proxy/redirect/timeout/size
  cURL controls;
- exact key shape, one-use state, private HTTP Basic handling, and no
  environment/configuration/database/request-global/shell/logging reader;
- exact D1 preflight adoption before exchange;
- one in-memory exchange double call producing only the bounded open, unpaid,
  non-live Session projection;
- changed preflight refusal before the exchange;
- conservative indeterminate/no-retry handling after a thrown exchange or
  live-mode response;
- no Checkout URL, body, headers, request id, credential, or provider object in
  the typed result; and
- an actual production transport object with zero calls.

The historical P3E-3 wire contract remains unchanged at 60 assertions. The
existing local TLS loopback rehearsal separately passes 11 assertions and
cleans `process:0 temp:0 credential:absent provider:untouched`.

No focused or aggregate D4A test invokes the typed provider-write handler or
production exchange. No DNS, external TLS, HTTP, Stripe, real key, Checkout
Session, payment, database, Store Lite, browser, hosted-demo, client, or
deployment effect occurs.

Run:

```sh
PHP_CLI=/path/to/php RED_CMS_CORE=/path/to/redcms scripts/test.sh
PHP_CLI=/path/to/php tests/p3e5-loopback-https-transport-rehearsal.sh
```
