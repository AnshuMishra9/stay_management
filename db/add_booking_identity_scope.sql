-- Scope identity documents to the booking where they were collected.
-- NULL remains the customer-level scope used by Customer Master.
ALTER TABLE `customer_identities`
    ADD COLUMN IF NOT EXISTS `booking_id` bigint(20) unsigned DEFAULT NULL
    AFTER `customer_id`;

ALTER TABLE `customer_identities`
    ADD INDEX IF NOT EXISTS `idx_ci_booking_customer` (`booking_id`, `customer_id`);

SET @fk_ci_booking_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customer_identities'
      AND CONSTRAINT_NAME IN ('fk_ci_booking', 'fk_ci_booking_scope')
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @fk_ci_booking_sql = IF(
    @fk_ci_booking_exists = 0,
    'ALTER TABLE `customer_identities` ADD CONSTRAINT `fk_ci_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE fk_ci_booking_stmt FROM @fk_ci_booking_sql;
EXECUTE fk_ci_booking_stmt;
DEALLOCATE PREPARE fk_ci_booking_stmt;

-- Legacy rows had only customer_id. Associate one only when a same-customer
-- booking update is the unique nearest match within five minutes. Once the
-- multitenancy migration has added property scope this historical inference is
-- deliberately disabled: rerunning an old migration must never attach a new
-- property-level Customer Master document to an unrelated booking.
SET @ci_has_property_scope = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customer_identities'
      AND COLUMN_NAME IN ('tenant_id', 'property_id')
);
SET @ci_legacy_backfill_sql = IF(
    @ci_has_property_scope = 0,
    'UPDATE `customer_identities` ci
     JOIN `booking_details` nearest ON nearest.customer_id = ci.customer_id
     LEFT JOIN `booking_details` closer
       ON closer.customer_id = ci.customer_id
      AND (
           ABS(TIMESTAMPDIFF(SECOND, closer.updated_at, COALESCE(ci.updated_at, ci.created_at)))
             < ABS(TIMESTAMPDIFF(SECOND, nearest.updated_at, COALESCE(ci.updated_at, ci.created_at)))
           OR (
               ABS(TIMESTAMPDIFF(SECOND, closer.updated_at, COALESCE(ci.updated_at, ci.created_at)))
                 = ABS(TIMESTAMPDIFF(SECOND, nearest.updated_at, COALESCE(ci.updated_at, ci.created_at)))
               AND closer.id > nearest.id
           )
      )
     SET ci.booking_id = nearest.id
     WHERE ci.booking_id IS NULL
       AND closer.id IS NULL
       AND ABS(TIMESTAMPDIFF(SECOND, nearest.updated_at, COALESCE(ci.updated_at, ci.created_at))) <= 300',
    'SELECT 1'
);
PREPARE ci_legacy_backfill_stmt FROM @ci_legacy_backfill_sql;
EXECUTE ci_legacy_backfill_stmt;
DEALLOCATE PREPARE ci_legacy_backfill_stmt;
