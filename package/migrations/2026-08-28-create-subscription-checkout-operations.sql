CREATE TABLE IF NOT EXISTS `RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations` (
  `IntentReference` char(37) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `SubjectRecordID` int unsigned NOT NULL,
  `OfferID` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `PlanSHA256` binary(32) NOT NULL,
  `ClaimStateSHA256` binary(32) NOT NULL,
  `ExecutionStartStateSHA256` binary(32) NOT NULL,
  `ResultSHA256` binary(32) DEFAULT NULL,
  `CheckoutSessionRefSHA256` binary(32) DEFAULT NULL,
  `AttemptStatus` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `StartedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CompletedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`IntentReference`),
  UNIQUE KEY `uq_stripe_subscription_operation_subject_offer`
    (`SubjectRecordID`, `OfferID`),
  UNIQUE KEY `uq_stripe_subscription_operation_start`
    (`ExecutionStartStateSHA256`),
  UNIQUE KEY `uq_stripe_subscription_operation_result`
    (`ResultSHA256`),
  UNIQUE KEY `uq_stripe_subscription_operation_session`
    (`CheckoutSessionRefSHA256`),
  CONSTRAINT `chk_stripe_subscription_operation_intent` CHECK (
    `IntentReference` REGEXP '^sint_[a-f0-9]{32}$'
  ),
  CONSTRAINT `chk_stripe_subscription_operation_subject` CHECK (
    `SubjectRecordID` BETWEEN 1 AND 4294967295
  ),
  CONSTRAINT `chk_stripe_subscription_operation_offer` CHECK (
    `OfferID` REGEXP '^[a-z0-9][a-z0-9._-]{0,63}$'
  ),
  CONSTRAINT `chk_stripe_subscription_operation_status` CHECK (
    (`AttemptStatus` = 'started'
      AND `ResultSHA256` IS NULL
      AND `CheckoutSessionRefSHA256` IS NULL
      AND `CompletedAt` IS NULL)
    OR (`AttemptStatus` = 'completed'
      AND `ResultSHA256` IS NOT NULL
      AND `CheckoutSessionRefSHA256` IS NOT NULL
      AND `CompletedAt` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
