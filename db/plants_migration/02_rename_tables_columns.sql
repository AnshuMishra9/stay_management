-- ============================================================================
-- 02_rename_tables_columns.sql
-- STEP 2/4 : Tables RENAME + columns CHANGE (naam + data types, dream style)
-- PRE      : 01_drop_constraints.sql chal chuka ho
-- NOTE     : Data preserve rehta hai — sirf naam/types badalte hain.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- A) TABLE RENAMES
-- ---------------------------------------------------------------------------
RENAME TABLE
  `tenants`       TO `plants`,
  `status_master` TO `status_details`,
  `taxes`         TO `gst_rates`,
  `otp_requests`  TO `mobile_otp`;

-- ---------------------------------------------------------------------------
-- B) plants  (ex tenants)
-- ---------------------------------------------------------------------------
ALTER TABLE `plants`
  CHANGE `id`         `plant_id`     int NOT NULL AUTO_INCREMENT,
  CHANGE `name`       `plant_name`   varchar(255) NOT NULL,
  CHANGE `is_active`  `plant_status` int(11) NOT NULL DEFAULT '1' COMMENT '0 : Inactive, 1 : Active',
  CHANGE `created_by` `plant_ad_by`  int DEFAULT NULL,
  CHANGE `created_at` `plant_ad_dt`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;
  -- updated_at retained as-is

-- ---------------------------------------------------------------------------
-- C) users
-- ---------------------------------------------------------------------------
ALTER TABLE `users`
  CHANGE `id`         `user_id` int NOT NULL AUTO_INCREMENT,
  CHANGE `name`       `name`    varchar(200) NOT NULL,
  CHANGE `tenant_id`  `fk_plant` int DEFAULT NULL COMMENT 'NULL only for super_admin',
  CHANGE `is_active`  `status`  tinyint(1) NOT NULL DEFAULT '1',
  CHANGE `created_by` `added_by` int DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- D) properties
-- ---------------------------------------------------------------------------
ALTER TABLE `properties`
  CHANGE `id`         `property_id` int NOT NULL AUTO_INCREMENT,
  CHANGE `tenant_id`  `fk_plant`    int NOT NULL,
  CHANGE `is_active`  `status`      tinyint(1) NOT NULL DEFAULT '1',
  CHANGE `created_by` `added_by`    int DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- E) user_property_access
-- ---------------------------------------------------------------------------
ALTER TABLE `user_property_access`
  CHANGE `tenant_id`    `fk_plant`    int NOT NULL,
  CHANGE `user_id`      `user_id`     int NOT NULL,
  CHANGE `property_id`  `property_id` int NOT NULL,
  CHANGE `assigned_by`  `assigned_by` int DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- F) customers
-- ---------------------------------------------------------------------------
ALTER TABLE `customers`
  CHANGE `id`            `id`            int NOT NULL AUTO_INCREMENT,
  CHANGE `tenant_id`     `fk_plant`      int NOT NULL,
  CHANGE `customer_name` `customer_name` varchar(200) NOT NULL,
  CHANGE `pincode`       `pin_code`      varchar(15) DEFAULT NULL,
  CHANGE `is_active`     `status`        tinyint(1) NOT NULL DEFAULT '1';

-- ---------------------------------------------------------------------------
-- G) customer_identities
-- ---------------------------------------------------------------------------
ALTER TABLE `customer_identities`
  CHANGE `id`           `id`            int NOT NULL AUTO_INCREMENT,
  CHANGE `tenant_id`    `fk_plant`      int NOT NULL,
  CHANGE `property_id`  `property_id`   int NOT NULL,
  CHANGE `customer_id`  `customer_id`   int NOT NULL,
  CHANGE `booking_id`   `booking_id`    int DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- H) rooms
--    created_by/updated_by varchar(20) -> int : pehle non-numeric values NULL karo
-- ---------------------------------------------------------------------------
UPDATE `rooms` SET `created_by` = NULL WHERE `created_by` IS NOT NULL AND `created_by` NOT REGEXP '^[0-9]+$';
UPDATE `rooms` SET `updated_by` = NULL WHERE `updated_by` IS NOT NULL AND `updated_by` NOT REGEXP '^[0-9]+$';

ALTER TABLE `rooms`
  CHANGE `id`             `id`             int NOT NULL AUTO_INCREMENT,
  CHANGE `property_id`    `property_id`    int NOT NULL,
  CHANGE `category_id`    `category_id`    int DEFAULT NULL,
  CHANGE `selling_price`  `selling_price`  decimal(10,2) DEFAULT NULL,
  CHANGE `is_active`      `status`         tinyint(1) NOT NULL DEFAULT '1',
  CHANGE `created_by`     `added_by`       int DEFAULT NULL,
  CHANGE `updated_by`     `updated_by`     int DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- I) room_categories
-- ---------------------------------------------------------------------------
ALTER TABLE `room_categories`
  CHANGE `category_id`    `category_id`     int NOT NULL AUTO_INCREMENT,
  CHANGE `property_id`    `property_id`     int NOT NULL,
  CHANGE `max_adults`     `max_adults`      int DEFAULT NULL,
  CHANGE `max_children`   `max_children`    int DEFAULT NULL,
  CHANGE `bed_count`      `bed_count`       int DEFAULT NULL,
  CHANGE `default_tax_id` `default_tax_id`  int DEFAULT NULL COMMENT 'ref gst_rates.id',
  CHANGE `display_order`  `display_order`   int NOT NULL DEFAULT '0';
  -- default_tax_id naam same rakha (semantic clarity), sirf type int hua

-- ---------------------------------------------------------------------------
-- J) booking_details
-- ---------------------------------------------------------------------------
ALTER TABLE `booking_details`
  CHANGE `id`                `id`                int NOT NULL AUTO_INCREMENT,
  CHANGE `tenant_id`         `fk_plant`          int NOT NULL,
  CHANGE `property_id`       `property_id`       int NOT NULL,
  CHANGE `customer_id`       `customer_id`       int NOT NULL,
  CHANGE `booking_channel_id` `booking_channel_id` int DEFAULT NULL,
  CHANGE `status_id`         `sd_id`             int NOT NULL DEFAULT '1' COMMENT 'FK status_details.sd_id',
  CHANGE `length_of_stay`    `length_of_stay`    int DEFAULT NULL,
  CHANGE `total_guest`       `total_guest`       int DEFAULT NULL,
  CHANGE `room_category_id`  `room_category_id`  int DEFAULT NULL,
  CHANGE `room_id`           `room_id`           int DEFAULT NULL,
  CHANGE `room_quantity`     `room_quantity`     int DEFAULT NULL,
  CHANGE `total_unit`        `total_unit`        int DEFAULT NULL,
  CHANGE `total_amount`      `total_amount`      decimal(10,2) DEFAULT NULL,
  CHANGE `amount_paid`       `amount_paid`       decimal(10,2) DEFAULT NULL,
  CHANGE `remaining_amount`  `remaining_amount`  decimal(10,2) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- K) booking_channels / amenities / room_amenities  (type alignment)
-- ---------------------------------------------------------------------------
ALTER TABLE `booking_channels` CHANGE `channel_id` `channel_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `amenities`        CHANGE `amenity_id` `amenity_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `room_amenities`
  CHANGE `room_id`    `room_id`    int NOT NULL,
  CHANGE `amenity_id` `amenity_id` int NOT NULL;

-- ---------------------------------------------------------------------------
-- L) status_details  (ex status_master) — dream exact naming
-- ---------------------------------------------------------------------------
ALTER TABLE `status_details`
  CHANGE `status_id`   `sd_id`     int NOT NULL AUTO_INCREMENT,
  CHANGE `status_name` `sd_name`   varchar(50) NOT NULL,
  CHANGE `is_active`   `sd_status` int(11) NOT NULL DEFAULT '1' COMMENT '0 : Inactive, 1 : Active',
  ADD COLUMN `sd_ad_by` int DEFAULT NULL AFTER `sd_status`,
  ADD COLUMN `sd_ad_dt` datetime DEFAULT NULL AFTER `sd_ad_by`;

-- ---------------------------------------------------------------------------
-- M) gst_rates  (ex taxes) — dream exact: id / rate / status (+tax_name retained)
-- ---------------------------------------------------------------------------
ALTER TABLE `gst_rates`
  CHANGE `tax_id`        `id`   int NOT NULL AUTO_INCREMENT,
  CHANGE `tax_percentage` `rate` decimal(5,2) NOT NULL DEFAULT '0.00';

-- ---------------------------------------------------------------------------
-- N) mobile_otp  (ex otp_requests)
-- ---------------------------------------------------------------------------
ALTER TABLE `mobile_otp`
  CHANGE `id`          `id`      int NOT NULL AUTO_INCREMENT,
  CHANGE `user_id`     `user_id` int NOT NULL,
  CHANGE `is_verified` `status`  tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 : Not verified, 1 : Verified';
