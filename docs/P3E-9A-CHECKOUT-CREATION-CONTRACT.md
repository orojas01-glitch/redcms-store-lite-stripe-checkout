# P3E-9A Non-Executing Checkout-Creation Contract

Status: complete as a dependency-free, source-only contract. It does not alter
the installable adapter `0.1.4` payload or integrity inventory.

## Boundary

P3E-9A adopts the already-reviewed P3E-1 request planner, P3E-3 canonical form
codec, and P3E-1 response gate for the next Checkout-creation track without
adding a caller or transport.

`RED_CMS_Store_Lite_Stripe_Sandbox_Checkout_Creation_Contract` accepts only:

- the existing closed synthetic Store Lite checkout projection;
- exact USD currency;
- the existing API version and same-origin HTTPS return policy;
- an explicit creation and expiry epoch separated by 1,800 through 86,400
  seconds; and
- the exact `p3e9a-v1` mutation-aware operation profile.

The profile names future Checkout creation honestly: provider contact,
provider mutation, and Checkout creation are requested effects. Payment,
webhook, browser navigation, Store Lite order mutation, client deployment, and
automatic retry remain false. The returned current-execution state keeps every
runtime effect false and `authorized=false`.

## Request Adoption

The contract first calls the retained P3E-3 codec, which itself calls the
P3E-1 planner. It then adds only the reviewed `expires_at` field to the already
canonical form bytes and recomputes the exact byte count and SHA-256.

The prepared contract fixes:

- package `redcms.store-lite-stripe-checkout`;
- contract `p3e9a-v1` and operation `checkout.create-sandbox`;
- target `stripe-sandbox`;
- credential mode `restricted_test_write` with no value;
- one `POST https://api.stripe.com/v1/checkout/sessions` future request;
- the existing internal idempotency relation;
- 30-minute through 24-hour expiry;
- recovery disabled by omitting `after_expiration`;
- one attempt and no automatic retry; and
- a deterministic contract SHA-256.

The exact checkout and policy shapes reject customer identity, browser fields,
extra provider parameters, non-USD currency, short or long expiry, and secret
material before returning a contract.

## Synthetic Response Adoption

The response side accepts only the existing eleven-field closed Checkout
projection plus exact `expires_at` and `after_expiration=null`. It strips those
two fields and calls the retained P3E-1 response gate for exact sandbox,
Session-id, URL, mode, open/unpaid state, amount, currency, order, metadata,
and live-mode validation.

The new result retains only the opaque Session reference, validated-URL fact,
closed open/unpaid facts, amount/currency, expiry, and hashes. The validated
Checkout URL is discarded. The result states that provider contact, provider
mutation, Checkout creation, payment, webhook, browser navigation, order
mutation, retry, and client deployment are all false.

Wrong expiry, recovery-enabled, live, paid, customer-bearing, extra-field,
provider-error, amount, currency, order, or metadata projections produce no
partial result.

## Explicit Exclusions

P3E-9A adds no:

- file under `package/` or package-version change;
- manifest, migration, table, registrar, runtime operation, or handler;
- credential resolver or value;
- DNS, TLS, HTTP, cURL, SDK, Stripe request, or Checkout Session;
- authorization, claim, start, result, or audit row;
- payment, webhook, browser route, Store Lite transition, retry, demo change,
  client deployment, or P4 work.

P3E-9B remains the next gate. It may separately adopt this source into a new
synthetic-only package/core operation after exact package identity, version,
integrity, and cross-profile refusal are reviewed.
