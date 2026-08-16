CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Stripe_Checkout_Attempts` (
  `RecordID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ClientScopeSHA256` binary(32) NOT NULL,
  `OrderID` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `OrderSnapshotSHA256` binary(32) NOT NULL,
  `IdempotencySHA256` binary(32) NOT NULL,
  `CheckoutSessionRef` varchar(192) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `AmountMinor` bigint unsigned NOT NULL,
  `Currency` char(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `AttemptStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ResponseEvidenceSHA256` binary(32) NOT NULL,
  `CreatedAt` int unsigned NOT NULL,
  `ExpiresAt` int unsigned NOT NULL,
  `RecordedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  UNIQUE KEY `uq_stripe_attempt_idempotency` (
    `ClientScopeSHA256`, `OrderID`, `IdempotencySHA256`
  ),
  UNIQUE KEY `uq_stripe_attempt_session` (
    `ClientScopeSHA256`, `CheckoutSessionRef`
  ),
  KEY `idx_stripe_attempt_order` (`ClientScopeSHA256`, `OrderID`, `RecordID`),
  CONSTRAINT `chk_stripe_attempt_order_id` CHECK (
    `OrderID` REGEXP '^ord_[a-f0-9]{32}$'
  ),
  CONSTRAINT `chk_stripe_attempt_session_ref` CHECK (
    `CheckoutSessionRef` REGEXP '^cs_test_[A-Za-z0-9_]{16,160}$'
  ),
  CONSTRAINT `chk_stripe_attempt_amount` CHECK (
    `AmountMinor` <= 2400999997599
  ),
  CONSTRAINT `chk_stripe_attempt_currency` CHECK (
    `Currency` REGEXP '^[A-Z]{3}$'
  ),
  CONSTRAINT `chk_stripe_attempt_initial_status` CHECK (
    `AttemptStatus` = 'created'
  ),
  CONSTRAINT `chk_stripe_attempt_times` CHECK (
    `CreatedAt` BETWEEN 1 AND 4102444800
    AND `ExpiresAt` > `CreatedAt`
    AND `ExpiresAt` <= `CreatedAt` + 86400
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
