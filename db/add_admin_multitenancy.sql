-- ============================================================
-- Stay Management: admin roles, tenant ownership and property isolation
--
-- Target: MariaDB 10.4+ / MySQL-compatible installations.
-- Run this against the selected Stay Management database. This script does
-- not issue USE, CREATE DATABASE, or DROP DATABASE statements.
--
-- The migration is rerunnable. Existing operational rows are assigned to the
-- single Legacy Account / Legacy Property because the old schema contains no
-- trustworthy ownership key. booking_details.property_name is deliberately
-- preserved as legacy display data and is never used as an authorization key.
--
-- Back up the database and secure_uploads/ before applying in production.
-- ============================================================

SET @sm_mt_database = DATABASE();

-- A small migration-local helper keeps constraint/index creation rerunnable
-- on MariaDB versions that do not support IF [NOT] EXISTS for every ALTER.
DELIMITER //
DROP PROCEDURE IF EXISTS `_sm_mt_exec`//
DROP PROCEDURE IF EXISTS `_sm_mt_assert_zero`//
CREATE PROCEDURE `_sm_mt_exec`(IN should_execute BOOLEAN, IN statement_text LONGTEXT)
BEGIN
    IF should_execute THEN
        SET @sm_mt_statement = statement_text;
        PREPARE sm_mt_prepared FROM @sm_mt_statement;
        EXECUTE sm_mt_prepared;
        DEALLOCATE PREPARE sm_mt_prepared;
    END IF;
END//
CREATE PROCEDURE `_sm_mt_assert_zero`(IN violation_count BIGINT, IN failure_message VARCHAR(255))
BEGIN
    IF violation_count <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = failure_message;
    END IF;
END//
DELIMITER ;

-- -------------------------------------------------------------------------
-- 1. Authentication ownership hierarchy
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tenants` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(150) NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_by` bigint(20) unsigned DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_active_name` (`is_active`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `name` varchar(150) DEFAULT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `role` enum('super_admin','admin','user') DEFAULT NULL AFTER `mobile_no`,
    ADD COLUMN IF NOT EXISTS `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `role`,
    ADD COLUMN IF NOT EXISTS `created_by` bigint(20) unsigned DEFAULT NULL AFTER `last_login`,
    ADD COLUMN IF NOT EXISTS `updated_at` datetime DEFAULT NULL AFTER `created_at`;

-- Deterministic bootstrap tenant. ON DUPLICATE intentionally preserves any
-- later administrator edits when this migration is rerun.
INSERT INTO `tenants` (`id`, `name`, `is_active`, `created_by`, `created_at`)
VALUES (1, 'Legacy Account', 1, NULL, current_timestamp())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- Preserve every previously authorized login. The three known seed users get
-- the locked roles; any additional pre-migration user becomes legacy staff.
UPDATE `users`
SET `name` = COALESCE(NULLIF(`name`, ''), CONCAT('User ', `id`)),
    `role` = COALESCE(`role`, 'user'),
    `tenant_id` = CASE
        WHEN `role` = 'super_admin' THEN NULL
        ELSE COALESCE(`tenant_id`, 1)
    END;

UPDATE `users`
SET `name` = CASE WHEN `name` IS NULL OR `name` = '' OR `name` LIKE 'User %' THEN 'Super Admin' ELSE `name` END,
    `role` = 'super_admin',
    `tenant_id` = NULL,
    `created_by` = NULL
WHERE `mobile_no` = '9876543210';

UPDATE `users`
SET `name` = CASE WHEN `name` IS NULL OR `name` = '' OR `name` LIKE 'User %' THEN 'Legacy Admin' ELSE `name` END,
    `role` = 'admin',
    `tenant_id` = 1,
    `created_by` = COALESCE(`created_by`, 1)
WHERE `mobile_no` = '9988776655';

UPDATE `users`
SET `name` = CASE WHEN `name` IS NULL OR `name` = '' OR `name` LIKE 'User %' THEN 'Legacy User' ELSE `name` END,
    `role` = 'user',
    `tenant_id` = 1,
    `created_by` = COALESCE(`created_by`, 2)
WHERE `mobile_no` = '9123456789';

ALTER TABLE `users`
    MODIFY `name` varchar(150) NOT NULL,
    MODIFY `role` enum('super_admin','admin','user') NOT NULL DEFAULT 'user';

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_id_tenant'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_id_tenant` (`id`,`tenant_id`)');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_tenant_role_active'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `users` ADD KEY `idx_users_tenant_role_active` (`tenant_id`,`role`,`is_active`)');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_user_tenant'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `users` ADD CONSTRAINT `fk_user_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_user_created_by'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `users` ADD CONSTRAINT `fk_user_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'chk_users_role_tenant'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `users` ADD CONSTRAINT `chk_users_role_tenant` CHECK ((`role` = ''super_admin'' AND `tenant_id` IS NULL) OR (`role` IN (''admin'',''user'') AND `tenant_id` IS NOT NULL))');

UPDATE `tenants`
SET `created_by` = 1
WHERE `id` = 1 AND `created_by` IS NULL
  AND EXISTS (SELECT 1 FROM `users` WHERE `id` = 1);

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'tenants'
      AND CONSTRAINT_NAME = 'fk_tenant_created_by'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `tenants` ADD CONSTRAINT `fk_tenant_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

CREATE TABLE IF NOT EXISTS `properties` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `tenant_id` bigint(20) unsigned NOT NULL,
    `property_code` varchar(30) NOT NULL,
    `property_name` varchar(150) NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_by` bigint(20) unsigned DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_property_tenant_code` (`tenant_id`, `property_code`),
    UNIQUE KEY `uq_property_id_tenant` (`id`, `tenant_id`),
    KEY `idx_property_tenant_active_name` (`tenant_id`, `is_active`, `property_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'properties'
      AND CONSTRAINT_NAME = 'fk_property_tenant'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `properties` ADD CONSTRAINT `fk_property_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'properties'
      AND CONSTRAINT_NAME = 'fk_property_created_by'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `properties` ADD CONSTRAINT `fk_property_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

INSERT INTO `properties`
    (`id`, `tenant_id`, `property_code`, `property_name`, `is_active`, `created_by`, `created_at`)
VALUES
    (1, 1, 'LEGACY', 'Legacy Property', 1, 2, current_timestamp())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

CREATE TABLE IF NOT EXISTS `user_property_access` (
    `tenant_id` bigint(20) unsigned NOT NULL,
    `user_id` bigint(20) unsigned NOT NULL,
    `property_id` bigint(20) unsigned NOT NULL,
    `assigned_by` bigint(20) unsigned DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`user_id`, `property_id`),
    KEY `idx_upa_tenant_property` (`tenant_id`, `property_id`),
    KEY `idx_upa_assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'user_property_access'
      AND CONSTRAINT_NAME = 'fk_upa_user_tenant'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `user_property_access` ADD CONSTRAINT `fk_upa_user_tenant` FOREIGN KEY (`user_id`,`tenant_id`) REFERENCES `users` (`id`,`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'user_property_access'
      AND CONSTRAINT_NAME = 'fk_upa_property_tenant'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `user_property_access` ADD CONSTRAINT `fk_upa_property_tenant` FOREIGN KEY (`property_id`,`tenant_id`) REFERENCES `properties` (`id`,`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE');

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @sm_mt_database AND TABLE_NAME = 'user_property_access'
      AND CONSTRAINT_NAME = 'fk_upa_assigned_by'
);
CALL `_sm_mt_exec`(@sm_mt_exists = 0,
    'ALTER TABLE `user_property_access` ADD CONSTRAINT `fk_upa_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

INSERT INTO `user_property_access`
    (`tenant_id`, `user_id`, `property_id`, `assigned_by`, `created_at`)
SELECT 1, `id`, 1, 2, current_timestamp()
FROM `users`
WHERE `mobile_no` = '9123456789'
ON DUPLICATE KEY UPDATE `tenant_id` = VALUES(`tenant_id`);

-- -------------------------------------------------------------------------
-- 2. Add nullable scope columns, backfill, and fail fast before schema changes
-- -------------------------------------------------------------------------

ALTER TABLE `customers`
    ADD COLUMN IF NOT EXISTS `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `id`;

ALTER TABLE `room_categories`
    ADD COLUMN IF NOT EXISTS `property_id` bigint(20) unsigned DEFAULT NULL AFTER `category_id`;

ALTER TABLE `rooms`
    ADD COLUMN IF NOT EXISTS `property_id` bigint(20) unsigned DEFAULT NULL AFTER `id`;

ALTER TABLE `booking_details`
    ADD COLUMN IF NOT EXISTS `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `property_id` bigint(20) unsigned DEFAULT NULL AFTER `tenant_id`;

ALTER TABLE `customer_identities`
    ADD COLUMN IF NOT EXISTS `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `property_id` bigint(20) unsigned DEFAULT NULL AFTER `tenant_id`;

UPDATE `customers` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
UPDATE `room_categories` SET `property_id` = 1 WHERE `property_id` IS NULL;
UPDATE `rooms` SET `property_id` = 1 WHERE `property_id` IS NULL;
UPDATE `booking_details`
SET `tenant_id` = 1, `property_id` = 1
WHERE `tenant_id` IS NULL OR `property_id` IS NULL;
UPDATE `customer_identities`
SET `tenant_id` = 1, `property_id` = 1
WHERE `tenant_id` IS NULL OR `property_id` IS NULL;

SET @sm_mt_scope_nulls =
      (SELECT COUNT(*) FROM `customers` WHERE `tenant_id` IS NULL)
    + (SELECT COUNT(*) FROM `room_categories` WHERE `property_id` IS NULL)
    + (SELECT COUNT(*) FROM `rooms` WHERE `property_id` IS NULL)
    + (SELECT COUNT(*) FROM `booking_details` WHERE `tenant_id` IS NULL OR `property_id` IS NULL)
    + (SELECT COUNT(*) FROM `customer_identities` WHERE `tenant_id` IS NULL OR `property_id` IS NULL);
CALL `_sm_mt_assert_zero`(
    @sm_mt_scope_nulls,
    'Multitenancy backfill stopped: NULL tenant/property scope remains'
);

SET @sm_mt_relation_violations =
      (
          SELECT COUNT(*)
          FROM `rooms` r
          LEFT JOIN `properties` p ON p.id = r.property_id
          LEFT JOIN `room_categories` c
            ON c.category_id = r.category_id AND c.property_id = r.property_id
          WHERE p.id IS NULL OR c.category_id IS NULL
      )
    + (
          SELECT COUNT(*)
          FROM `booking_details` b
          LEFT JOIN `properties` p
            ON p.id = b.property_id AND p.tenant_id = b.tenant_id
          LEFT JOIN `customers` c
            ON c.id = b.customer_id AND c.tenant_id = b.tenant_id
          LEFT JOIN `rooms` r
            ON r.id = b.room_id AND r.property_id = b.property_id
          LEFT JOIN `room_categories` rc
            ON rc.category_id = b.room_category_id AND rc.property_id = b.property_id
          WHERE p.id IS NULL OR c.id IS NULL
             OR (b.room_id IS NOT NULL AND r.id IS NULL)
             OR (b.room_category_id IS NOT NULL AND rc.category_id IS NULL)
      )
    + (
          SELECT COUNT(*)
          FROM `customer_identities` ci
          LEFT JOIN `properties` p
            ON p.id = ci.property_id AND p.tenant_id = ci.tenant_id
          LEFT JOIN `customers` c
            ON c.id = ci.customer_id AND c.tenant_id = ci.tenant_id
          LEFT JOIN `booking_details` b
            ON b.id = ci.booking_id
           AND b.property_id = ci.property_id
           AND b.customer_id = ci.customer_id
           AND b.tenant_id = ci.tenant_id
          WHERE p.id IS NULL OR c.id IS NULL
             OR (ci.booking_id IS NOT NULL AND b.id IS NULL)
      );
CALL `_sm_mt_assert_zero`(
    @sm_mt_relation_violations,
    'Multitenancy backfill stopped: cross-scope/orphan relation found'
);

-- -------------------------------------------------------------------------
-- 3. Replace global uniqueness with tenant/property-scoped uniqueness
-- -------------------------------------------------------------------------

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'customers'
      AND INDEX_NAME = 'uq_customer_code'
);
CALL `_sm_mt_exec`(@sm_mt_exists > 0,
    'ALTER TABLE `customers` DROP INDEX `uq_customer_code`');

-- Earlier revisions created this as a lookup-only index. Replace it with the
-- locked tenant-scoped uniqueness rule while keeping upgrades/reruns safe.
SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'customers'
      AND INDEX_NAME = 'idx_customer_tenant_phone'
);
CALL `_sm_mt_exec`(@sm_mt_exists > 0,
    'ALTER TABLE `customers` DROP INDEX `idx_customer_tenant_phone`');
ALTER TABLE `customers`
    ADD UNIQUE KEY IF NOT EXISTS `uq_customer_code` (`tenant_id`, `customer_code`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_customer_id_tenant` (`id`, `tenant_id`),
    ADD KEY IF NOT EXISTS `idx_customer_tenant_active_name` (`tenant_id`, `is_active`, `customer_name`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_customer_tenant_phone` (`tenant_id`, `phone`);

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'room_categories'
      AND INDEX_NAME = 'uq_category_name'
);
CALL `_sm_mt_exec`(@sm_mt_exists > 0,
    'ALTER TABLE `room_categories` DROP INDEX `uq_category_name`');
ALTER TABLE `room_categories`
    ADD UNIQUE KEY IF NOT EXISTS `uq_category_name` (`property_id`, `category_name`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_category_short_code` (`property_id`, `short_code`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_category_id_property` (`category_id`, `property_id`),
    ADD KEY IF NOT EXISTS `idx_category_property_status` (`property_id`, `status`, `display_order`);

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'rooms'
      AND INDEX_NAME = 'uq_room_code'
);
CALL `_sm_mt_exec`(@sm_mt_exists > 0,
    'ALTER TABLE `rooms` DROP INDEX `uq_room_code`');
SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'rooms'
      AND INDEX_NAME = 'uq_room_no'
);
CALL `_sm_mt_exec`(@sm_mt_exists > 0,
    'ALTER TABLE `rooms` DROP INDEX `uq_room_no`');
ALTER TABLE `rooms`
    ADD UNIQUE KEY IF NOT EXISTS `uq_room_code` (`property_id`, `room_code`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_room_no` (`property_id`, `room_no`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_room_id_property` (`id`, `property_id`),
    ADD KEY IF NOT EXISTS `idx_room_property_active_category` (`property_id`, `is_active`, `category_id`),
    ADD KEY IF NOT EXISTS `idx_room_property_floor` (`property_id`, `floor_no`);

SET @sm_mt_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @sm_mt_database AND TABLE_NAME = 'booking_details'
      AND INDEX_NAME = 'uk_booking_number'
);
CALL `_sm_mt_exec`(@sm_mt_exists > 0,
    'ALTER TABLE `booking_details` DROP INDEX `uk_booking_number`');
ALTER TABLE `booking_details`
    ADD UNIQUE KEY IF NOT EXISTS `uk_booking_number` (`property_id`, `booking_number`),
    ADD UNIQUE KEY IF NOT EXISTS `uq_bd_identity_scope` (`id`, `property_id`, `customer_id`, `tenant_id`),
    ADD KEY IF NOT EXISTS `idx_bd_property_status_date` (`property_id`, `status_id`, `scheduled_check_in_date`),
    ADD KEY IF NOT EXISTS `idx_bd_property_room` (`property_id`, `room_id`),
    ADD KEY IF NOT EXISTS `idx_bd_tenant_customer` (`tenant_id`, `customer_id`);

ALTER TABLE `customer_identities`
    ADD KEY IF NOT EXISTS `idx_ci_property_customer_booking` (`property_id`, `customer_id`, `booking_id`),
    ADD KEY IF NOT EXISTS `idx_ci_tenant_customer` (`tenant_id`, `customer_id`);

-- -------------------------------------------------------------------------
-- 4. Replace permissive legacy FKs with same-scope, history-safe FKs
-- -------------------------------------------------------------------------

-- Drop every known old/new child constraint before rebuilding the exact rules.
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='customer_identities' AND CONSTRAINT_NAME='fk_ci_booking');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_booking`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='customer_identities' AND CONSTRAINT_NAME='fk_ci_customer');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_customer`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='customer_identities' AND CONSTRAINT_NAME='fk_ci_property_tenant');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_property_tenant`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='customer_identities' AND CONSTRAINT_NAME='fk_ci_customer_tenant');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_customer_tenant`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='customer_identities' AND CONSTRAINT_NAME='fk_ci_booking_scope');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_booking_scope`');

SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_customer');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_customer`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_room');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_room`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_status');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_status`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_property_tenant');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_property_tenant`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_customer_tenant');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_customer_tenant`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_room_property');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_room_property`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_room_category_property');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_room_category_property`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='booking_details' AND CONSTRAINT_NAME='fk_bd_channel');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_channel`');

SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='rooms' AND CONSTRAINT_NAME='fk_room_category');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `rooms` DROP FOREIGN KEY `fk_room_category`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='rooms' AND CONSTRAINT_NAME='fk_room_property');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `rooms` DROP FOREIGN KEY `fk_room_property`');
SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='rooms' AND CONSTRAINT_NAME='fk_room_category_scope');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `rooms` DROP FOREIGN KEY `fk_room_category_scope`');

SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='otp_requests' AND CONSTRAINT_NAME='fk_otp_user');
CALL `_sm_mt_exec`(@sm_mt_exists > 0, 'ALTER TABLE `otp_requests` DROP FOREIGN KEY `fk_otp_user`');

SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='customers' AND CONSTRAINT_NAME='fk_customer_tenant');
CALL `_sm_mt_exec`(@sm_mt_exists = 0, 'ALTER TABLE `customers` ADD CONSTRAINT `fk_customer_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

SET @sm_mt_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@sm_mt_database AND TABLE_NAME='room_categories' AND CONSTRAINT_NAME='fk_category_property');
CALL `_sm_mt_exec`(@sm_mt_exists = 0, 'ALTER TABLE `room_categories` ADD CONSTRAINT `fk_category_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

ALTER TABLE `rooms`
    ADD CONSTRAINT `fk_room_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_room_category_scope` FOREIGN KEY (`category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `booking_details`
    ADD CONSTRAINT `fk_bd_property_tenant` FOREIGN KEY (`property_id`, `tenant_id`) REFERENCES `properties` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bd_customer_tenant` FOREIGN KEY (`customer_id`, `tenant_id`) REFERENCES `customers` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bd_room_property` FOREIGN KEY (`room_id`, `property_id`) REFERENCES `rooms` (`id`, `property_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bd_room_category_property` FOREIGN KEY (`room_category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bd_channel` FOREIGN KEY (`booking_channel_id`) REFERENCES `booking_channels` (`channel_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_bd_status` FOREIGN KEY (`status_id`) REFERENCES `status_master` (`status_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `customer_identities`
    ADD CONSTRAINT `fk_ci_property_tenant` FOREIGN KEY (`property_id`, `tenant_id`) REFERENCES `properties` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_ci_customer_tenant` FOREIGN KEY (`customer_id`, `tenant_id`) REFERENCES `customers` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_ci_booking_scope` FOREIGN KEY (`booking_id`, `property_id`, `customer_id`, `tenant_id`) REFERENCES `booking_details` (`id`, `property_id`, `customer_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `otp_requests`
    ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Scope is verified and protected by composite keys/FKs. Make it mandatory as
-- the final schema phase so a bad legacy row fails before irreversible DDL.
ALTER TABLE `customers`
    MODIFY `tenant_id` bigint(20) unsigned NOT NULL;
ALTER TABLE `room_categories`
    MODIFY `property_id` bigint(20) unsigned NOT NULL;
ALTER TABLE `rooms`
    MODIFY `property_id` bigint(20) unsigned NOT NULL;
ALTER TABLE `booking_details`
    MODIFY `tenant_id` bigint(20) unsigned NOT NULL,
    MODIFY `property_id` bigint(20) unsigned NOT NULL;
ALTER TABLE `customer_identities`
    MODIFY `tenant_id` bigint(20) unsigned NOT NULL,
    MODIFY `property_id` bigint(20) unsigned NOT NULL;

-- -------------------------------------------------------------------------
-- 5. Verification (all violation counts must be zero)
-- -------------------------------------------------------------------------

SELECT 'multitenancy_users' AS verification,
       `id`, `mobile_no`, `name`, `role`, `tenant_id`, `is_active`
FROM `users`
WHERE `mobile_no` IN ('9876543210', '9988776655', '9123456789')
ORDER BY `id`;

SELECT 'legacy_assignment' AS verification,
       `tenant_id`, `user_id`, `property_id`, `assigned_by`
FROM `user_property_access`
WHERE `user_id` = 3 AND `property_id` = 1;

SELECT 'null_scope_rows' AS verification,
       (SELECT COUNT(*) FROM `customers` WHERE `tenant_id` IS NULL) AS customers,
       (SELECT COUNT(*) FROM `room_categories` WHERE `property_id` IS NULL) AS room_categories,
       (SELECT COUNT(*) FROM `rooms` WHERE `property_id` IS NULL) AS rooms,
       (SELECT COUNT(*) FROM `booking_details` WHERE `tenant_id` IS NULL OR `property_id` IS NULL) AS bookings,
       (SELECT COUNT(*) FROM `customer_identities` WHERE `tenant_id` IS NULL OR `property_id` IS NULL) AS identities;

SELECT 'scope_violations' AS verification,
       (
           SELECT COUNT(*)
           FROM `booking_details` b
           JOIN `properties` p ON p.id = b.property_id
           JOIN `customers` c ON c.id = b.customer_id
           WHERE p.tenant_id <> b.tenant_id OR c.tenant_id <> b.tenant_id
       ) AS bookings,
       (
           SELECT COUNT(*)
           FROM `customer_identities` ci
           JOIN `properties` p ON p.id = ci.property_id
           JOIN `customers` c ON c.id = ci.customer_id
           LEFT JOIN `booking_details` b ON b.id = ci.booking_id
           WHERE p.tenant_id <> ci.tenant_id
              OR c.tenant_id <> ci.tenant_id
              OR (ci.booking_id IS NOT NULL AND (
                     b.id IS NULL
                     OR b.property_id <> ci.property_id
                     OR b.customer_id <> ci.customer_id
                     OR b.tenant_id <> ci.tenant_id
                 ))
       ) AS identities,
       (
           SELECT COUNT(*)
           FROM `user_property_access` upa
           JOIN `users` u ON u.id = upa.user_id
           JOIN `properties` p ON p.id = upa.property_id
           WHERE u.tenant_id <> upa.tenant_id OR p.tenant_id <> upa.tenant_id
       ) AS access_rows;

DROP PROCEDURE IF EXISTS `_sm_mt_assert_zero`;
DROP PROCEDURE IF EXISTS `_sm_mt_exec`;
