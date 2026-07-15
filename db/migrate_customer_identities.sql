-- ============================================================
--  Migration: move the fixed Aadhar / PAN identity fields off the
--  `customers` table into the flexible `customer_identities` table,
--  then drop the old columns.
--
--  Safe to re-run:
--    - table create is IF NOT EXISTS
--    - data migration skips customers that already have an identity row
--    - column drops are guarded (missing column = no-op)
-- ============================================================
USE `stay_management`;

-- 1) Ensure the target table exists.
CREATE TABLE IF NOT EXISTS `customer_identities` (
    `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`     BIGINT(20) UNSIGNED NOT NULL,
    `identity_type`   VARCHAR(30)  NOT NULL,
    `identity_number` VARCHAR(50)  DEFAULT NULL,
    `document_path`   VARCHAR(255) DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ci_customer` (`customer_id`),
    CONSTRAINT `fk_ci_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Migrate existing data (only when the old columns still exist and the
--    customer has no identity row yet).
SET @has_aadhar := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'aadhar_number');

SET @sql := IF(@has_aadhar > 0,
    'INSERT INTO `customer_identities` (`customer_id`, `identity_type`, `identity_number`, `document_path`)
     SELECT c.`id`, ''aadhar'', NULLIF(c.`aadhar_number`, ''''), c.`aadhar_card_path`
     FROM `customers` c
     WHERE (COALESCE(c.`aadhar_number`, '''') <> '''' OR c.`aadhar_card_path` IS NOT NULL)
       AND NOT EXISTS (SELECT 1 FROM `customer_identities` ci
                       WHERE ci.`customer_id` = c.`id` AND ci.`identity_type` = ''aadhar'')',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_aadhar > 0,
    'INSERT INTO `customer_identities` (`customer_id`, `identity_type`, `identity_number`, `document_path`)
     SELECT c.`id`, ''pan'', NULLIF(c.`pan_number`, ''''), c.`pan_card_path`
     FROM `customers` c
     WHERE (COALESCE(c.`pan_number`, '''') <> '''' OR c.`pan_card_path` IS NOT NULL)
       AND NOT EXISTS (SELECT 1 FROM `customer_identities` ci
                       WHERE ci.`customer_id` = c.`id` AND ci.`identity_type` = ''pan'')',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Drop the old fixed identity columns from `customers`.
DROP PROCEDURE IF EXISTS `_drop_col`;
DELIMITER //
CREATE PROCEDURE `_drop_col`(IN tbl VARCHAR(64), IN col VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` DROP COLUMN `', col, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL `_drop_col`('customers', 'aadhar_number');
CALL `_drop_col`('customers', 'aadhar_name');
CALL `_drop_col`('customers', 'aadhar_card_path');
CALL `_drop_col`('customers', 'pan_number');
CALL `_drop_col`('customers', 'pan_name');
CALL `_drop_col`('customers', 'pan_card_path');

DROP PROCEDURE IF EXISTS `_drop_col`;
