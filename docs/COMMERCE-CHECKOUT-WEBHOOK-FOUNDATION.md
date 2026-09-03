# Commerce Checkout and webhook foundation

## Release boundary

Adapter `0.1.21` is a package-level foundation for an isolated RED-CMS commerce
installation. It does not activate the declared public provider-event route,
install dependencies, resolve credentials, call Stripe, migrate a database, or
change any live resource. The existing single-offer sandbox path remains
available for compatibility.

The installable payload adds:

- `StripeSdkCatalogResolver.php`: resolves server-owned `rs_ai_...` lookup keys
  and rejects inactive, live/test-mode-mismatched, amount-drifted, metadata-
  mismatched, non-monthly, missing, or duplicate Price responses.
- `StripeCommerceCheckoutContract.php`: builds one hosted
  `mode=subscription` Checkout request containing monthly Prices and any
  one-time setup Prices. It omits `payment_method_types` and automatic tax,
  binds the request to one immutable cart snapshot, and produces a stable
  idempotency key.
- `StripeSdkCommerceGateway.php`: instantiates `StripeClient` with the exact
  `2026-08-26.dahlia` API version, accepts only restricted test keys, performs
  at most one Checkout creation call, and returns a bounded non-live response.
- `StripeSdkWebhookVerifier.php`: verifies Stripe signatures through the
  official SDK with a five-minute tolerance and never persists the raw body,
  signature header, or endpoint secret.
- `StripeCommerceWebhookEventContract.php`: accepts only the reviewed Checkout,
  invoice, payment-failure, subscription-update, and cancellation event set.
  A paid invoice is payment authority; a Checkout success page or merely
  completed Checkout is not.
- `2026-09-02-create-commerce-checkout-receipts.sql`: creates separate,
  append-only checkout-attempt and verified-event receipt tables containing
  hashes and bounded provider references, not raw event bodies or customer PII.

## SDK dependency

`package/composer.json` and `package/composer.lock` pin
`stripe/stripe-php` `21.3.1`. A client release must run a locked, no-development
Composer install in its isolated build and load that autoloader before invoking
the SDK-backed classes. The `vendor/` directory is intentionally absent from
this repository and from the generic package integrity inventory.

## Site-specific work still required

The commerce installation must provide authenticated cart services, database
transactions, secret-reference resolution, a real route controller, replay and
rate-limit enforcement, event receipt persistence, out-of-order reconciliation,
and the Store Lite cart-state transition call. These services must fail closed
until the exact sandbox account, restricted key, endpoint signing secret,
return origin, and lookup-key mapping are configured in that installation.

Tax remains explicitly outside this release. Automatic tax must remain absent
until Red Sphere has an approved tax classification, head-office decision, and
registration/collection instruction.
