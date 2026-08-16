# P3C-1 Dependency-Free Package Foundation

Status: P3C-1 is a local, non-installable package foundation. It fixes the
external adapter identity and implements only pure checkout-response and
verified-event normalization contracts. No GitHub repository visibility,
provider dependency, provider access, or deployment is implied.

## Ownership and isolation

The package identity is `redcms.store-lite-stripe-checkout`; its reserved
repository name is `redcms-store-lite-stripe-checkout`. It remains outside the
clean RED-CMS starter and outside the Store Lite base package. Every future
installation, database, package state, setting, secret, attempt, and event must
belong to one client only.

`package/identity.json` is intentionally not named `addon.json`. P3C-1 cannot
be discovered, installed, enabled, registered, or invoked by RED-CMS. A later
gate must generate and validate the installable manifest only when its
migration, entrypoint, route, settings, integrity, and lifecycle contracts are
implemented together.

## Checkout response normalization

The checkout normalizer accepts two exact data-only values:

1. immutable server-derived expected order facts: order id, snapshot hash,
   `stripe_checkout`, integer amount, uppercase currency, and internal
   idempotency hash; and
2. a reviewed non-live Checkout Session projection containing only its opaque
   id, hosted URL, mode, state, amount, currency, client reference, two exact
   metadata hashes, and `livemode=false`.

The values must match exactly. The URL must use HTTPS, the exact
`checkout.stripe.com` host, and the exact opaque Session path with no userinfo,
port, query, fragment, or alternate host. Success returns only
`checkoutSessionRef` and `checkoutUrl`; failure returns no partial checkout.
The URL is transient browser-navigation material and is not persistence
authorization.

## Verified event normalization

The event normalizer accepts one expected immutable order/attempt projection
and one bounded event projection produced only after future signature
verification and server-side reconciliation. It does not accept a raw body,
signature, secret, provider error, browser query, or customer/payment-method
data. Extra keys fail closed.

Only these reviewed pairs normalize:

| Stripe event | Reconciled provider status | Store Lite outcome |
| --- | --- | --- |
| `checkout.session.completed` | `complete_paid` | `paid` |
| `checkout.session.async_payment_failed` | `failed` | `failed` |
| `checkout.session.expired` | `expired` | `expired` |
| `charge.refunded` | `refunded` | `refund_confirmed` |
| `charge.dispute.created` | `disputed` | `reversal_reported` |

Success emits exactly Store Lite 0.1.35's ten provider-neutral event fields:
`verification`, `replayStatus`, `outcome`, `orderId`,
`orderSnapshotSha256`, `paymentMethod`, `amountMinor`, `currency`,
`eventEvidenceSha256`, and `occurredAt`. It never changes an order or invokes
the Store Lite service. Replayed, live-mode, stale, mismatched, unsupported, or
malformed evidence returns no partial event.

## Explicit exclusions

P3C-1 adds no migration, table, attempt persistence, event persistence,
registrar, route, webhook verifier, raw-body parser, signature verification,
secret reference, configuration surface, SDK, HTTP client, outbound host
connection, redirect handling, browser return, service invocation, order
transition, client installation, Stripe object, simulated payment, live
payment, refund, or deployment.

The next P3C slice requires a separate review. P3D offline lifecycle rehearsal
must still pass before P3E may request isolated Stripe Sandbox access.
