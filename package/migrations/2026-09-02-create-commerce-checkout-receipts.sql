CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Stripe_Commerce_Checkout_Attempts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `CartIDSHA256` binary(32) NOT NULL,
  `CartSnapshotSHA256` binary(32) NOT NULL,
  `ContractSHA256` binary(32) NOT NULL,
  `IdempotencyKeySHA256` binary(32) NOT NULL,
  `CheckoutSessionRefSHA256` binary(32) DEFAULT NULL,
  `Status` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `StartedAtEpoch` int unsigned NOT NULL,
  `CompletedAtEpoch` int unsigned DEFAULT NULL,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_stripe_commerce_checkout_cart_snapshot` (`CartSnapshotSHA256`),
  UNIQUE KEY `uq_stripe_commerce_checkout_contract` (`ContractSHA256`),
  UNIQUE KEY `uq_stripe_commerce_checkout_idempotency` (`IdempotencyKeySHA256`),
  UNIQUE KEY `uq_stripe_commerce_checkout_session` (`CheckoutSessionRefSHA256`),
  CONSTRAINT `chk_stripe_commerce_checkout_status` CHECK (
    `Status` IN ('started','completed','indeterminate','refused')
  ),
  CONSTRAINT `chk_stripe_commerce_checkout_time` CHECK (
    `StartedAtEpoch` BETWEEN 1 AND 4102444800
    AND (`CompletedAtEpoch` IS NULL OR `CompletedAtEpoch` >= `StartedAtEpoch`)
  ),
  CONSTRAINT `chk_stripe_commerce_checkout_completion` CHECK (
    (`Status` = 'completed' AND `CheckoutSessionRefSHA256` IS NOT NULL AND `CompletedAtEpoch` IS NOT NULL)
    OR (`Status` <> 'completed' AND `CheckoutSessionRefSHA256` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Stripe_Commerce_Event_Receipts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `EventReferenceSHA256` binary(32) NOT NULL,
  `EventType` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `PayloadSHA256` binary(32) NOT NULL,
  `CartIDSHA256` binary(32) NOT NULL,
  `CartSnapshotSHA256` binary(32) NOT NULL,
  `EventEvidenceSHA256` binary(32) DEFAULT NULL,
  `Status` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ReceivedAtEpoch` int unsigned NOT NULL,
  `ProcessedAtEpoch` int unsigned DEFAULT NULL,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_stripe_commerce_event_reference` (`EventReferenceSHA256`),
  UNIQUE KEY `uq_stripe_commerce_event_payload` (`PayloadSHA256`),
  UNIQUE KEY `uq_stripe_commerce_event_evidence` (`EventEvidenceSHA256`),
  KEY `idx_stripe_commerce_event_cart` (`CartIDSHA256`, `RecordID`),
  CONSTRAINT `chk_stripe_commerce_event_type` CHECK (
    `EventType` IN (
      'checkout.session.completed',
      'checkout.session.async_payment_succeeded',
      'checkout.session.async_payment_failed',
      'checkout.session.expired',
      'invoice.paid',
      'invoice.payment_failed',
      'invoice.payment_action_required',
      'invoice.finalization_failed',
      'customer.subscription.created',
      'customer.subscription.updated',
      'customer.subscription.deleted'
    )
  ),
  CONSTRAINT `chk_stripe_commerce_event_status` CHECK (
    `Status` IN ('received','processed','refused','operator_review')
  ),
  CONSTRAINT `chk_stripe_commerce_event_time` CHECK (
    `ReceivedAtEpoch` BETWEEN 1 AND 4102444800
    AND (`ProcessedAtEpoch` IS NULL OR `ProcessedAtEpoch` >= `ReceivedAtEpoch`)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
