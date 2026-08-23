-- ============================================================================
-- 02b_continue_after_room_amenities_fix.sql
-- CONTINUATION: 02 me rooms par fail hua tha (fk_ra_* abhi bhi the).
-- PRE: fk_ra_room / fk_ra_amenity drop ho chuke hon (02a fix dekho)
-- Yeh file 02 ke section H→N repeat karti hai (idempotent-safe nahi — sirf ek baar).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- H) rooms
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
-- K) booking_channels / amenities / room_amenities
-- ---------------------------------------------------------------------------
ALTER TABLE `booking_channels` CHANGE `channel_id` `channel_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `amenities`        CHANGE `amenity_id` `amenity_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `room_amenities`
  CHANGE `room_id`    `room_id`    int NOT NULL,
  CHANGE `amenity_id` `amenity_id` int NOT NULL;

-- ---------------------------------------------------------------------------
-- L) status_details
-- ---------------------------------------------------------------------------
ALTER TABLE `status_details`
  CHANGE `status_id`   `sd_id`     int NOT NULL AUTO_INCREMENT,
  CHANGE `status_name` `sd_name`   varchar(50) NOT NULL,
  CHANGE `is_active`   `sd_status` int(11) NOT NULL DEFAULT '1' COMMENT '0 : Inactive, 1 : Active',
  ADD COLUMN `sd_ad_by` int DEFAULT NULL AFTER `sd_status`,
  ADD COLUMN `sd_ad_dt` datetime DEFAULT NULL AFTER `sd_ad_by`;

-- ---------------------------------------------------------------------------
-- M) gst_rates
-- ---------------------------------------------------------------------------
ALTER TABLE `gst_rates`
  CHANGE `tax_id`         `id`   int NOT NULL AUTO_INCREMENT,
  CHANGE `tax_percentage` `rate` decimal(5,2) NOT NULL DEFAULT '0.00';

-- ---------------------------------------------------------------------------
-- N) mobile_otp
-- ---------------------------------------------------------------------------
ALTER TABLE `mobile_otp`
  CHANGE `id`          `id`      int NOT NULL AUTO_INCREMENT,
  CHANGE `user_id`     `user_id` int NOT NULL,
  CHANGE `is_verified` `status`  tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 : Not verified, 1 : Verified';
