# P3E-14 Subscription Event Replay Ledger

Status: adopted offline in Stripe adapter `0.1.13`.

The fourth package migration adds one hash-only subscription-event receipt
table. A unique event-reference hash and signature-evidence hash claim a
verified delivery. Completion is closed to `applied` or `refused` and requires
an opaque Store Lite intent reference plus event- and lifecycle-result hashes.

The pure planner validates deterministic claim and completion records, refuses
expanded, stale, malformed, foreign, or already-completed inputs, and returns
no raw body, signature, or secret material. The focused fixture passes 14
assertions and the full adapter suite remains green.

This gate adds no database caller, request reader, operational route, secret
resolution, Stripe contact, payment, browser action, or deployment. Core must
later add a transactional journal helper and a non-operational package-handler
rehearsal before any endpoint can be considered.
