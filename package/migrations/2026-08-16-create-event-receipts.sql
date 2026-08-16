CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Stripe_Event_Receipts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `AttemptRecordID` bigint unsigned NOT NULL,
  `ClientScopeSHA256` binary(32) NOT NULL,
  `ProviderEnvironment` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ProviderEventRef` varchar(192) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `EventEvidenceSHA256` binary(32) NOT NULL,
  `TransportBodySHA256` binary(32) NOT NULL,
  `VerificationEvidenceSHA256` binary(32) NOT NULL,
  `CheckoutSessionRef` varchar(192) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OrderID` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OrderSnapshotSHA256` binary(32) NOT NULL,
  `ProviderEventType` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ProviderStatus` varchar(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `NormalizedOutcome` varchar(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `AmountMinor` bigint unsigned NOT NULL,
  `Currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ReplayStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ProcessingStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OccurredAt` int unsigned NOT NULL,
  `ReceivedAt` int unsigned NOT NULL,
  `RecordedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_stripe_event_provider_ref` (
    `ClientScopeSHA256`, `ProviderEventRef`
  ),
  UNIQUE KEY `uq_stripe_event_evidence` (
    `ClientScopeSHA256`, `EventEvidenceSHA256`
  ),
  KEY `idx_stripe_event_attempt` (`AttemptRecordID`, `RecordID`),
  KEY `idx_stripe_event_order` (`ClientScopeSHA256`, `OrderID`, `RecordID`),
  CONSTRAINT `fk_stripe_event_attempt` FOREIGN KEY (`AttemptRecordID`)
    REFERENCES `RED_Addon_StoreLite_Stripe_Checkout_Attempts` (`RecordID`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_stripe_event_environment` CHECK (
    `ProviderEnvironment` = 'sandbox'
  ),
  CONSTRAINT `chk_stripe_event_ref` CHECK (
    `ProviderEventRef` REGEXP '^evt_[A-Za-z0-9_]{8,160}$'
  ),
  CONSTRAINT `chk_stripe_event_session_ref` CHECK (
    `CheckoutSessionRef` REGEXP '^cs_test_[A-Za-z0-9_]{16,160}$'
  ),
  CONSTRAINT `chk_stripe_event_order_id` CHECK (
    `OrderID` REGEXP '^ord_[a-f0-9]{32}$'
  ),
  CONSTRAINT `chk_stripe_event_projection` CHECK (
    (`ProviderEventType` = 'checkout.session.completed'
      AND `ProviderStatus` = 'complete_paid'
      AND `NormalizedOutcome` = 'paid')
    OR
    (`ProviderEventType` = 'checkout.session.async_payment_failed'
      AND `ProviderStatus` = 'failed'
      AND `NormalizedOutcome` = 'failed')
    OR
    (`ProviderEventType` = 'checkout.session.expired'
      AND `ProviderStatus` = 'expired'
      AND `NormalizedOutcome` = 'expired')
    OR
    (`ProviderEventType` = 'charge.refunded'
      AND `ProviderStatus` = 'refunded'
      AND `NormalizedOutcome` = 'refund_confirmed')
    OR
    (`ProviderEventType` = 'charge.dispute.created'
      AND `ProviderStatus` = 'disputed'
      AND `NormalizedOutcome` = 'reversal_reported')
  ),
  CONSTRAINT `chk_stripe_event_amount` CHECK (
    `AmountMinor` <= 2400999997599
  ),
  CONSTRAINT `chk_stripe_event_currency` CHECK (
    `Currency` REGEXP '^[A-Z]{3}$'
  ),
  CONSTRAINT `chk_stripe_event_initial_state` CHECK (
    `ReplayStatus` = 'unseen' AND `ProcessingStatus` = 'normalized'
  ),
  CONSTRAINT `chk_stripe_event_times` CHECK (
    `OccurredAt` BETWEEN 1 AND 4102444800
    AND `ReceivedAt` BETWEEN `OccurredAt` AND `OccurredAt` + 300
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
