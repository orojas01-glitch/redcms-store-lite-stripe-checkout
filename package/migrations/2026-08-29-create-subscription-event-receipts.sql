CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `EventRefSHA256` binary(32) NOT NULL,
  `RawBodySHA256` binary(32) NOT NULL,
  `SignatureEvidenceSHA256` binary(32) NOT NULL,
  `ProviderEventType` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ClaimStateSHA256` binary(32) NOT NULL,
  `ReceiptStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `IntentReference` char(37) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `EventEvidenceSHA256` binary(32) DEFAULT NULL,
  `LifecycleResultSHA256` binary(32) DEFAULT NULL,
  `SignedAtEpoch` int unsigned NOT NULL,
  `ReceivedAtEpoch` int unsigned NOT NULL,
  `CompletedAtEpoch` int unsigned DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_stripe_subscription_event_ref` (`EventRefSHA256`),
  UNIQUE KEY `uq_stripe_subscription_event_signature` (`SignatureEvidenceSHA256`),
  UNIQUE KEY `uq_stripe_subscription_event_evidence` (`EventEvidenceSHA256`),
  CONSTRAINT `chk_stripe_subscription_event_type` CHECK (
    `ProviderEventType` IN (
      'checkout.session.completed','invoice.paid','invoice.payment_failed',
      'customer.subscription.deleted','checkout.session.expired'
    )
  ),
  CONSTRAINT `chk_stripe_subscription_event_status` CHECK (
    `ReceiptStatus` IN ('verified','applied','refused')
  ),
  CONSTRAINT `chk_stripe_subscription_event_state` CHECK (
    (`ReceiptStatus` = 'verified'
      AND `IntentReference` IS NULL
      AND `EventEvidenceSHA256` IS NULL
      AND `LifecycleResultSHA256` IS NULL
      AND `CompletedAtEpoch` IS NULL)
    OR
    (`ReceiptStatus` IN ('applied','refused')
      AND `IntentReference` REGEXP '^sint_[a-f0-9]{32}$'
      AND `EventEvidenceSHA256` IS NOT NULL
      AND `LifecycleResultSHA256` IS NOT NULL
      AND `CompletedAtEpoch` IS NOT NULL)
  ),
  CONSTRAINT `chk_stripe_subscription_event_times` CHECK (
    `SignedAtEpoch` BETWEEN 1 AND 4102444800
    AND `ReceivedAtEpoch` BETWEEN `SignedAtEpoch` - 300
      AND `SignedAtEpoch` + 300
    AND (`CompletedAtEpoch` IS NULL
      OR `CompletedAtEpoch` BETWEEN `ReceivedAtEpoch` AND 4102444800)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
