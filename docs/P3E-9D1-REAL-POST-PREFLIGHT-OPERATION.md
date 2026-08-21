# P3E-9D1 Real-POST Preflight Operation Adoption

Status: adapter `0.1.6` introduced one pure typed operation that adopts the
non-secret request evidence produced by RED-CMS core P3E-9D0. Adapter `0.1.7`
corrects the source-input SHA-256 to use the same recursive canonical ordering
as core. It does not add or expose the real provider operation.

## Exact Operation

The typed adapter adds only:

`checkout.create-sandbox-real-post-preflight`

It accepts the complete closed Checkout projection, bounded policy,
mutation-aware P3E-9A profile, exact contract SHA-256, and exact scalar/hash
projection of the D0 preflight. The adapter independently reconstructs the
complete form-field map because RED-CMS typed payloads deliberately reject
provider-style associative keys containing brackets.
The input target is fixed to `stripe-sandbox-real-post-preflight` so it cannot
be confused with the synthetic or future provider execution targets.

The adopted class recomputes:

- the complete P3E-9A contract and contract SHA-256;
- the core synthetic input SHA-256;
- `POST api.stripe.com/v1/checkout/sessions`;
- the reviewed API version and form content type;
- payment mode, return URLs, expiry, order reference, and two hash-only
  metadata fields, returned as a bounded typed list of name/value pairs;
- every deterministic USD line item and integer quantity;
- the bounded `redcms-checkout-<sha256>` idempotency key; and
- the canonical request SHA-256.

Any added field, changed input, read-only profile, changed package identity,
changed endpoint, mismatched form field/hash, current-effect flag, or malformed
evidence fails closed without a partial request result.

## Package Delta

Version `0.1.6` added byte-identical source and package copies of
`StripeSandboxCheckoutRealPostPreflight.php`, loads the package copy during
contained registration, and expands the integrity inventory from fourteen to
fifteen exact files. The two historical migration paths and checksums remain
unchanged. No manifest permission, setting, dependency, route, public
mutation, job, asset, outbound host, or uninstall policy changes.

Version `0.1.7` changes only the pure source/package hashing implementation,
package identity, integrity hashes, documentation, and regression evidence.
It recursively sorts associative input keys while preserving list order before
computing the source-input hash. The provider-request hash deliberately keeps
the existing fixed insertion order because that is the exact D0 request
contract. No migration or manifest capability surface changes.

The existing synthetic Checkout operation, read-only provider operation, and
all earlier package contracts remain separately named and unchanged.

## Deliberate Stop

The new preflight operation never calls `RED_Addon_Adapter_Request::secret()`.
It contains no cURL, socket, stream transport, SDK, request global, environment
reader, database client, shell call, wait/retry primitive, or authorization
header. Supplying visible secret references cannot alter its output.

Its successful result still states:

- `executionReady=false`;
- `credentialValueIncluded=false` and
  `authorizationHeaderIncluded=false`;
- network, provider contact/mutation, and Checkout creation false;
- payment, webhook, browser navigation, and Store Lite mutation false; and
- retry, live mode, client deployment, and execution false.

The actual `checkout.create-sandbox-real-post` operation remains unsupported.
No key, DNS lookup, TLS handshake, HTTP request, Stripe object, Checkout URL,
payment, order change, browser flow, demo change, client deployment, or P4
work is authorized or performed.

## Acceptance

[`tests/p3e9d1-real-post-preflight-operation-self-test.php`](../tests/p3e9d1-real-post-preflight-operation-self-test.php)
passes 64 focused assertions proving exact package identity/integrity,
byte-identical adoption, request
recomputation, changed-evidence refusal, typed invocation without secret
access, secret-visibility invariance, and continued refusal of the real
provider operation. The aggregate adapter suite passes 1,063 assertions while
preserving all earlier contracts.

P3E-9D2 remains separate: core may add bounded response containment and a new
real-operation start/result identity, but no provider contact is implied by
this adapter preflight adoption. P3E-9D3 remains the one-shot command and
disposable rehearsal. P3E-9D4 remains the separately approved single real
Stripe Sandbox POST.
