# P3E-5 Loopback-only HTTPS Transport

Status: P3E-5 is the first executable HTTP transport proof. It makes one
genuine TLS-verified cURL exchange only to a disposable numeric IPv4 loopback
fixture. It cannot contact Stripe or another host and does not alter the
installable adapter `0.1.1` under `package/`.

## Purpose

P3E-1 through P3E-4 fixed the non-secret request plan, one-attempt outcome
semantics, bounded wire codec, and synthetic credential containment without a
connection. P3E-5 proves the next mechanical boundary: the reviewed bytes and
synthetic authorization survive a real HTTPS request and response while all
network destinations and trust inputs remain closed.

## Exact network boundary

The final transport constructor accepts only:

- a random `synthetic_p3e5_` fixture followed by 64 lowercase hexadecimal
  characters;
- its precommitted HTTP Basic SHA-256;
- an origin exactly shaped as `https://127.0.0.1:<port>`, with an unprivileged
  port and no path, query, fragment, user information, hostname, IPv6, or
  alternate loopback address; and
- one bounded PEM certificate passed directly to cURL as the fixture CA.

The P3E-3 wire request must still contain the fixed reviewed Stripe URL. The
proof transport substitutes only its fixed `/v1/checkout/sessions` path onto
the validated loopback origin. Neither caller input nor the wire URL selects
the actual network destination.

## cURL constraints

The one-use handle fixes:

- HTTPS as the only request and redirect protocol;
- TLS 1.2 as both minimum and maximum for this reproducible fixture;
- peer verification and host verification;
- the in-memory fixture CA certificate;
- redirects disabled with a maximum of zero;
- explicit proxy disablement and no-proxy for all destinations;
- HTTP/1.1, fresh connection, and connection reuse disabled;
- the reviewed 5-second connect and 15-second total timeouts;
- at most 16,384 response-header bytes and 262,144 response-body bytes; and
- a bounded normalized response record with actual TLS 1.2 evidence.

HTTP Basic authorization is assembled only after the exact request validates.
The credential and commitment leave object state before cURL executes and are
replaced locally after the attempt. The transport returns no cURL error text,
credential fact, Authorization header, CA path, or request body.

## Disposable TLS fixture

The rehearsal generates two one-day self-signed certificates in a temporary
directory. The success server accepts exactly one TLS 1.2 request, validates
the method, path, required headers, authorization commitment, and bounded
body, then returns the closed synthetic Checkout response. Its evidence file
contains only:

- method, path, loopback fact, and TLS version;
- received header names;
- authorization and body SHA-256 values; and
- body byte count.

It never writes the credential, Authorization value, or body. A second server
is contacted with the wrong CA certificate and proves that peer verification
fails closed as an indeterminate one-attempt result. The script checks all
temporary artifacts for the synthetic credential, terminates both servers,
clears process-local values, and removes its temporary directory.

## Error and retry semantics

The P3E-5 adapter preserves the P3E-2 rule: any connection, certificate,
timeout, bounded-write, or unusable-response failure is indeterminate and
`retryAuthorized` remains `false`. Provider-style `4xx` and `5xx` semantics
remain those already fixed by P3E-2 and P3E-3. P3E-5 implements no automatic
retry even though Stripe documents same-idempotency-key reconciliation for
some network failures; authorization of any retry remains a later stateful
policy gate.

## Explicit stop

P3E-5 adds no provider hostname transport, DNS lookup, system proxy, Stripe
SDK, real or test Stripe key, Stripe Sandbox contact, automatic retry,
Checkout Session, persistence, webhook, Store Lite service invocation, order
transition, browser redirect, client configuration, deployment, or payment.

The source remains outside `package/`; package identity, manifest, migrations,
runtime handler, and version remain unchanged. A future provider-contact gate
requires separate explicit authorization and isolated Stripe Sandbox
configuration.

## Verification

```sh
PHP_CLI=/path/to/php scripts/test.sh
PHP_CLI=/path/to/php tests/p3e5-loopback-https-transport-rehearsal.sh
```

## Official references reviewed

- [Stripe API authentication](https://docs.stripe.com/api/authentication)
- [Stripe advanced error handling](https://docs.stripe.com/error-low-level)
- [PHP cURL constants](https://www.php.net/manual/en/curl.constants.php)
