-- ============================================================================
-- 00_full_target_schema.sql
-- stay_management FINAL TARGET STRUCTURE (dream_project conventions applied)
-- ----------------------------------------------------------------------------
-- PURPOSE: Review / fresh-install reference. Yeh final state dikhata hai jo
--          01→03 migration scripts chalne ke baad milega.
--          Existing DB par ise DIRECTLY RUN NA KARNA (duplicate tables banengi).
-- Engine : InnoDB, utf8mb4_general_ci (dream_project modern style)
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- plants  (ex: tenants) — admin account = plant; plant creates users & properties
-- dream exact style: plant_id / plant_name / plant_status / plant_ad_by / plant_ad_dt
-- ---------------------------------------------------------------------------
CREATE TABLE `plants` (
  `plant_id` int NOT NULL AUTO_INCREMENT,
  `plant_name` varchar(255) NOT NULL,
  `plant_status` int NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `plant_ad_by` int DEFAULT NULL COMMENT 'user_id who added this plant',
  `plant_ad_dt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`plant_id`),
  KEY `idx_plant_status_name` (`plant_status`,`plant_name`),
  KEY `idx_plant_ad_by` (`plant_ad_by`),
  CONSTRAINT `fk_plant_ad_by` FOREIGN KEY (`plant_ad_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- users — role enum unchanged; fk_plant NULL only for super_admin
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `mobile_no` varchar(15) NOT NULL,
  `role` enum('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `fk_plant` int DEFAULT NULL COMMENT 'NULL only for super_admin',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `last_login` datetime DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_mobile` (`mobile_no`),
  UNIQUE KEY `uq_users_id_plant` (`user_id`,`fk_plant`),
  KEY `idx_users_plant_role_status` (`fk_plant`,`role`,`status`),
  KEY `idx_users_added_by` (`added_by`),
  CONSTRAINT `chk_users_role_plant` CHECK (`role` = 'super_admin' and `fk_plant` is null or `role` in ('admin','user') and `fk_plant` is not null),
  CONSTRAINT `fk_user_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_user_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- properties — hotel units under a plant
-- ---------------------------------------------------------------------------
CREATE TABLE `properties` (
  `property_id` int NOT NULL AUTO_INCREMENT,
  `fk_plant` int NOT NULL,
  `property_code` varchar(30) NOT NULL,
  `property_name` varchar(150) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `added_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`property_id`),
  UNIQUE KEY `uq_property_plant_code` (`fk_plant`,`property_code`),
  UNIQUE KEY `uq_property_id_plant` (`property_id`,`fk_plant`),
  KEY `idx_property_plant_status_name` (`fk_plant`,`status`,`property_name`),
  KEY `idx_property_added_by` (`added_by`),
  CONSTRAINT `fk_property_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_property_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- user_property_access — which user can operate on which property
-- ---------------------------------------------------------------------------
CREATE TABLE `user_property_access` (
  `fk_plant` int NOT NULL,
  `user_id` int NOT NULL,
  `property_id` int NOT NULL,
  `assigned_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`property_id`),
  KEY `idx_upa_plant_property` (`fk_plant`,`property_id`),
  KEY `idx_upa_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_upa_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_upa_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_upa_user_plant` FOREIGN KEY (`user_id`, `fk_plant`) REFERENCES `users` (`user_id`, `fk_plant`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- customers — hotel guests
-- ---------------------------------------------------------------------------
CREATE TABLE `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fk_plant` int NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `pin_code` varchar(15) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_code` (`fk_plant`,`customer_code`),
  UNIQUE KEY `uq_customer_id_plant` (`id`,`fk_plant`),
  UNIQUE KEY `uq_customer_plant_phone` (`fk_plant`,`phone`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_customer_phone` (`phone`),
  KEY `idx_customer_plant_status_name` (`fk_plant`,`status`,`customer_name`),
  CONSTRAINT `fk_customer_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- customer_identities — guest ID proofs
-- ---------------------------------------------------------------------------
CREATE TABLE `customer_identities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fk_plant` int NOT NULL,
  `property_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `identity_type` varchar(30) NOT NULL,
  `identity_number` varchar(50) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `document_path_2` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ci_customer` (`customer_id`),
  KEY `idx_ci_booking_customer` (`booking_id`,`customer_id`),
  KEY `idx_ci_property_customer_booking` (`property_id`,`customer_id`,`booking_id`),
  KEY `idx_ci_plant_customer` (`fk_plant`,`customer_id`),
  CONSTRAINT `fk_ci_booking_scope` FOREIGN KEY (`booking_id`, `property_id`, `customer_id`, `fk_plant`) REFERENCES `booking_details` (`id`, `property_id`, `customer_id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ci_customer_plant` FOREIGN KEY (`customer_id`, `fk_plant`) REFERENCES `customers` (`id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ci_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- rooms
-- ---------------------------------------------------------------------------
CREATE TABLE `rooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `property_id` int NOT NULL,
  `room_code` varchar(20) NOT NULL,
  `room_no` varchar(30) NOT NULL,
  `category_id` int DEFAULT NULL,
  `floor_no` varchar(20) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `extra_bed_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `housekeeping_status` varchar(30) DEFAULT 'Available',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `added_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`property_id`,`room_code`),
  UNIQUE KEY `uq_room_no` (`property_id`,`room_no`),
  UNIQUE KEY `uq_room_id_property` (`id`,`property_id`),
  KEY `idx_room_category` (`category_id`),
  KEY `idx_room_floor` (`floor_no`),
  KEY `idx_room_hk` (`housekeeping_status`),
  KEY `idx_room_property_status_category` (`property_id`,`status`,`category_id`),
  KEY `idx_room_property_floor` (`property_id`,`floor_no`),
  CONSTRAINT `fk_room_category_scope` FOREIGN KEY (`category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_room_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- room_categories
-- ---------------------------------------------------------------------------
CREATE TABLE `room_categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `property_id` int NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `short_code` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `max_adults` int DEFAULT NULL,
  `max_children` int DEFAULT NULL,
  `room_size` varchar(30) DEFAULT NULL,
  `room_size_unit` varchar(15) DEFAULT 'sq.ft',
  `bed_type` varchar(40) DEFAULT NULL,
  `bed_count` int DEFAULT NULL,
  `smoking_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `default_tax_id` int DEFAULT NULL COMMENT 'ref gst_rates.id',
  `default_sac_code` varchar(20) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_name` (`property_id`,`category_name`),
  UNIQUE KEY `uq_category_id_property` (`category_id`,`property_id`),
  UNIQUE KEY `uq_category_short_code` (`property_id`,`short_code`),
  KEY `idx_category_property_status` (`property_id`,`status`,`display_order`),
  CONSTRAINT `fk_category_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- amenities / room_amenities
-- ---------------------------------------------------------------------------
CREATE TABLE `amenities` (
  `amenity_id` int NOT NULL AUTO_INCREMENT,
  `amenity_name` varchar(60) NOT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  PRIMARY KEY (`amenity_id`),
  UNIQUE KEY `uq_amenity_name` (`amenity_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `room_amenities` (
  `room_id` int NOT NULL,
  `amenity_id` int NOT NULL,
  PRIMARY KEY (`room_id`,`amenity_id`),
  KEY `idx_ra_amenity` (`amenity_id`),
  CONSTRAINT `fk_ra_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`amenity_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- booking_details — status_id ab status_details.sd_id ko reference karta hai
-- ---------------------------------------------------------------------------
CREATE TABLE `booking_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fk_plant` int NOT NULL,
  `property_id` int NOT NULL,
  `booking_number` varchar(20) NOT NULL COMMENT 'human-facing unique, e.g. BKG00001',
  `customer_id` int NOT NULL COMMENT 'FK customers.id',
  `booking_channel_id` int DEFAULT NULL COMMENT 'FK booking_channels.channel_id',
  `sd_id` int NOT NULL DEFAULT 1 COMMENT 'FK status_details.sd_id',
  `property_name` varchar(150) DEFAULT NULL,
  `scheduled_check_in_date` date DEFAULT NULL,
  `scheduled_check_out_date` date DEFAULT NULL,
  `length_of_stay` int DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `total_guest` int DEFAULT NULL,
  `room_category_id` int DEFAULT NULL COMMENT 'FK room_categories.category_id',
  `room_id` int DEFAULT NULL COMMENT 'FK rooms.id allotted room',
  `room_quantity` int DEFAULT NULL,
  `total_unit` int DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `remaining_amount` decimal(10,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_booking_number` (`property_id`,`booking_number`),
  UNIQUE KEY `uq_bd_identity_scope` (`id`,`property_id`,`customer_id`,`fk_plant`),
  KEY `idx_bd_customer` (`customer_id`),
  KEY `idx_bd_checkin` (`scheduled_check_in_date`),
  KEY `idx_bd_room` (`room_id`),
  KEY `idx_bd_sd` (`sd_id`),
  KEY `idx_bd_property_sd_date` (`property_id`,`sd_id`,`scheduled_check_in_date`),
  KEY `idx_bd_property_room` (`property_id`,`room_id`),
  KEY `idx_bd_plant_customer` (`fk_plant`,`customer_id`),
  CONSTRAINT `fk_bd_channel` FOREIGN KEY (`booking_channel_id`) REFERENCES `booking_channels` (`channel_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_customer_plant` FOREIGN KEY (`customer_id`, `fk_plant`) REFERENCES `customers` (`id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_room_category_property` FOREIGN KEY (`room_category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_room_property` FOREIGN KEY (`room_id`, `property_id`) REFERENCES `rooms` (`id`, `property_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_sd` FOREIGN KEY (`sd_id`) REFERENCES `status_details` (`sd_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- booking_channels
-- ---------------------------------------------------------------------------
CREATE TABLE `booking_channels` (
  `channel_id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `channel_name` varchar(100) NOT NULL COMMENT 'Booking Channel Name',
  `channel_category` enum('Direct','OTA','Corporate','Agent','Referral','Internal','Other') NOT NULL COMMENT 'Booking Channel Category',
  `commission_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Default Commission Percentage',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`channel_id`),
  UNIQUE KEY `uk_channel_name` (`channel_name`),
  KEY `idx_channel_category` (`channel_category`),
  KEY `idx_channel_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- status_details  (ex: status_master) — dream exact naming
-- ---------------------------------------------------------------------------
CREATE TABLE `status_details` (
  `sd_id` int NOT NULL AUTO_INCREMENT,
  `status_code` varchar(30) NOT NULL,
  `sd_name` varchar(50) NOT NULL,
  `display_order` int NOT NULL DEFAULT 0,
  `sd_status` int NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `sd_ad_by` int DEFAULT NULL,
  `sd_ad_dt` datetime DEFAULT NULL,
  PRIMARY KEY (`sd_id`),
  UNIQUE KEY `uq_status_code` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- gst_rates  (ex: taxes) — dream exact: id / rate / status (+tax_name retained)
-- ---------------------------------------------------------------------------
CREATE TABLE `gst_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(60) NOT NULL COMMENT 'retained for UI labels',
  `rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gst_rate_name` (`tax_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- mobile_otp  (ex: otp_requests) — security columns retained
-- ---------------------------------------------------------------------------
CREATE TABLE `mobile_otp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 : Not verified, 1 : Verified',
  `attempts` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`user_id`,`status`,`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- state_details — already dream-exact; engine modernization recommended
-- ---------------------------------------------------------------------------
CREATE TABLE `state_details` (
  `state_code_number` int NOT NULL,
  `state_code` varchar(2) NOT NULL,
  `state_name` varchar(255) NOT NULL,
  `state_status` int NOT NULL COMMENT '0 : Inactive, 1 : Active',
  `state_ad_by` int NOT NULL,
  `state_ad_dt` datetime NOT NULL,
  PRIMARY KEY (`state_code_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
