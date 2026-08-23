-- ============================================================================
-- 05_soft_delete.sql
-- SOFT-DELETE SUPPORT: delete = status inactive, data kabhi hard-delete nahi.
-- ----------------------------------------------------------------------------
-- A) customer_identities ko status column (pehle tha hi nahi)
-- B) Unique keys ko "active-only" banana (virtual flag trick):
--    active_flag = IF(status=1, 1, NULL)  ->  NULL unique me multiple allowed,
--    isliye inactive rows same phone/code/room_no reuse kar sakte hain,
--    lekin do ACTIVE rows kabhi duplicate nahi ho sakte.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- A) customer_identities.status
-- ---------------------------------------------------------------------------
ALTER TABLE `customer_identities`
  ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Removed (soft-deleted), 1 : Active' AFTER `document_path_2`;

-- ---------------------------------------------------------------------------
-- B1) customers
-- ---------------------------------------------------------------------------
ALTER TABLE `customers`
  ADD COLUMN `active_flag` tinyint(1) GENERATED ALWAYS AS (IF(`status` = 1, 1, NULL)) VIRTUAL;
ALTER TABLE `customers` DROP KEY `uq_customer_plant_phone`;
ALTER TABLE `customers` ADD UNIQUE KEY `uq_customer_plant_phone` (`fk_plant`,`phone`,`active_flag`);
ALTER TABLE `customers` DROP KEY `uq_customer_code`;
ALTER TABLE `customers` ADD UNIQUE KEY `uq_customer_code` (`fk_plant`,`customer_code`,`active_flag`);

-- ---------------------------------------------------------------------------
-- B2) properties
-- ---------------------------------------------------------------------------
ALTER TABLE `properties`
  ADD COLUMN `active_flag` tinyint(1) GENERATED ALWAYS AS (IF(`status` = 1, 1, NULL)) VIRTUAL;
ALTER TABLE `properties` DROP KEY `uq_property_plant_code`;
ALTER TABLE `properties` ADD UNIQUE KEY `uq_property_plant_code` (`fk_plant`,`property_code`,`active_flag`);

-- ---------------------------------------------------------------------------
-- B3) rooms
-- ---------------------------------------------------------------------------
ALTER TABLE `rooms`
  ADD COLUMN `active_flag` tinyint(1) GENERATED ALWAYS AS (IF(`status` = 1, 1, NULL)) VIRTUAL;
ALTER TABLE `rooms` DROP KEY `uq_room_code`;
ALTER TABLE `rooms` ADD UNIQUE KEY `uq_room_code` (`property_id`,`room_code`,`active_flag`);
ALTER TABLE `rooms` DROP KEY `uq_room_no`;
ALTER TABLE `rooms` ADD UNIQUE KEY `uq_room_no` (`property_id`,`room_no`,`active_flag`);

-- ---------------------------------------------------------------------------
-- B4) room_categories
-- ---------------------------------------------------------------------------
ALTER TABLE `room_categories`
  ADD COLUMN `active_flag` tinyint(1) GENERATED ALWAYS AS (IF(`status` = 1, 1, NULL)) VIRTUAL;
ALTER TABLE `room_categories` DROP KEY `uq_category_name`;
ALTER TABLE `room_categories` ADD UNIQUE KEY `uq_category_name` (`property_id`,`category_name`,`active_flag`);
ALTER TABLE `room_categories` DROP KEY `uq_category_short_code`;
ALTER TABLE `room_categories` ADD UNIQUE KEY `uq_category_short_code` (`property_id`,`short_code`,`active_flag`);

-- ---------------------------------------------------------------------------
-- B5) users (mobile reuse after deactivation)
-- ---------------------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN `active_flag` tinyint(1) GENERATED ALWAYS AS (IF(`status` = 1, 1, NULL)) VIRTUAL;
ALTER TABLE `users` DROP KEY `uq_users_mobile`;
ALTER TABLE `users` ADD UNIQUE KEY `uq_users_mobile` (`mobile_no`,`active_flag`);
