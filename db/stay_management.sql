-- Stay Management destructive zero-data installer
-- WARNING: Running this file permanently drops the existing stay_management
-- database before recreating its current tables, indexes, constraints, and
-- database options. It intentionally contains no application data.
--
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: stay_management
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `stay_management`
--

DROP DATABASE IF EXISTS `stay_management`;

CREATE DATABASE `stay_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `stay_management`;

--
-- Table structure for table `amenities`
--

DROP TABLE IF EXISTS `amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `amenities` (
  `amenity_id` int(11) NOT NULL AUTO_INCREMENT,
  `amenity_name` varchar(60) NOT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`amenity_id`),
  UNIQUE KEY `uq_amenity_name` (`amenity_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `booking_channels`
--

DROP TABLE IF EXISTS `booking_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_channels` (
  `channel_id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_name` varchar(100) NOT NULL COMMENT 'Booking Channel Name',
  `channel_category` enum('Direct','OTA','Corporate','Agent','Referral','Internal','Other') NOT NULL COMMENT 'Booking Channel Category',
  `commission_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Default Commission Percentage',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`channel_id`),
  UNIQUE KEY `uk_channel_name` (`channel_name`),
  KEY `idx_channel_category` (`channel_category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `booking_details`
--

DROP TABLE IF EXISTS `booking_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_plant` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `booking_number` varchar(20) NOT NULL COMMENT 'human-facing unique, e.g. BKG00001',
  `customer_id` int(11) NOT NULL,
  `booking_channel_id` int(11) DEFAULT NULL,
  `sd_id` int(11) NOT NULL DEFAULT 1 COMMENT 'FK status_details.sd_id',
  `property_name` varchar(150) DEFAULT NULL,
  `scheduled_check_in_date` date DEFAULT NULL,
  `scheduled_check_out_date` date DEFAULT NULL,
  `length_of_stay` int(11) DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `total_guest` int(11) DEFAULT NULL,
  `room_category_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `room_quantity` int(11) DEFAULT NULL,
  `total_unit` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `remaining_amount` decimal(10,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_booking_number` (`property_id`,`booking_number`),
  UNIQUE KEY `uq_bd_identity_scope` (`id`,`property_id`,`customer_id`,`fk_plant`),
  KEY `idx_bd_customer` (`customer_id`),
  KEY `idx_bd_checkin` (`scheduled_check_in_date`),
  KEY `idx_bd_room` (`room_id`),
  KEY `idx_bd_property_room` (`property_id`,`room_id`),
  KEY `idx_bd_sd` (`sd_id`),
  KEY `idx_bd_property_sd_date` (`property_id`,`sd_id`,`scheduled_check_in_date`),
  KEY `idx_bd_plant_customer` (`fk_plant`,`customer_id`),
  KEY `fk_bd_property_plant` (`property_id`,`fk_plant`),
  KEY `fk_bd_customer_plant` (`customer_id`,`fk_plant`),
  KEY `fk_bd_room_property` (`room_id`,`property_id`),
  KEY `fk_bd_room_category_property` (`room_category_id`,`property_id`),
  KEY `fk_bd_channel` (`booking_channel_id`),
  CONSTRAINT `fk_bd_channel` FOREIGN KEY (`booking_channel_id`) REFERENCES `booking_channels` (`channel_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_customer_plant` FOREIGN KEY (`customer_id`, `fk_plant`) REFERENCES `customers` (`id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_room_category_property` FOREIGN KEY (`room_category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_room_property` FOREIGN KEY (`room_id`, `property_id`) REFERENCES `rooms` (`id`, `property_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_sd` FOREIGN KEY (`sd_id`) REFERENCES `status_details` (`sd_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customer_identities`
--

DROP TABLE IF EXISTS `customer_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_identities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_plant` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `identity_type` varchar(30) NOT NULL,
  `identity_number` varchar(50) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `document_path_2` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 : Removed (soft-deleted), 1 : Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ci_customer` (`customer_id`),
  KEY `idx_ci_booking_customer` (`booking_id`,`customer_id`),
  KEY `idx_ci_property_customer_booking` (`property_id`,`customer_id`,`booking_id`),
  KEY `idx_ci_plant_customer` (`fk_plant`,`customer_id`),
  KEY `fk_ci_property_plant` (`property_id`,`fk_plant`),
  KEY `fk_ci_customer_plant` (`customer_id`,`fk_plant`),
  KEY `fk_ci_booking_scope` (`booking_id`,`property_id`,`customer_id`,`fk_plant`),
  CONSTRAINT `fk_ci_booking_scope` FOREIGN KEY (`booking_id`, `property_id`, `customer_id`, `fk_plant`) REFERENCES `booking_details` (`id`, `property_id`, `customer_id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ci_customer_plant` FOREIGN KEY (`customer_id`, `fk_plant`) REFERENCES `customers` (`id`, `fk_plant`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ci_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_plant` int(11) NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `pin_code` varchar(15) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `active_flag` tinyint(1) GENERATED ALWAYS AS (if(`status` = 1,1,NULL)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_id_tenant` (`id`,`fk_plant`),
  UNIQUE KEY `uq_customer_plant_phone` (`fk_plant`,`phone`,`active_flag`),
  UNIQUE KEY `uq_customer_code` (`fk_plant`,`customer_code`,`active_flag`),
  KEY `idx_name` (`customer_name`),
  KEY `idx_phone` (`phone`),
  KEY `idx_customer_plant_status_name` (`fk_plant`,`status`,`customer_name`),
  CONSTRAINT `fk_customer_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gst_rates`
--

DROP TABLE IF EXISTS `gst_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gst_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(60) NOT NULL,
  `rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_name` (`tax_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mobile_otp`
--

DROP TABLE IF EXISTS `mobile_otp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mobile_otp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 : Not verified, 1 : Verified',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`user_id`,`status`,`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plants`
--

DROP TABLE IF EXISTS `plants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plants` (
  `plant_id` int(11) NOT NULL AUTO_INCREMENT,
  `plant_name` varchar(255) NOT NULL,
  `plant_status` int(11) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `plant_ad_by` int(11) DEFAULT NULL,
  `plant_ad_dt` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`plant_id`),
  KEY `idx_plant_status_name` (`plant_status`,`plant_name`),
  KEY `idx_plant_ad_by` (`plant_ad_by`),
  CONSTRAINT `fk_plant_ad_by` FOREIGN KEY (`plant_ad_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_plant` int(11) NOT NULL,
  `property_code` varchar(30) NOT NULL,
  `property_name` varchar(150) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `active_flag` tinyint(1) GENERATED ALWAYS AS (if(`status` = 1,1,NULL)) VIRTUAL,
  PRIMARY KEY (`property_id`),
  UNIQUE KEY `uq_property_id_plant` (`property_id`,`fk_plant`),
  UNIQUE KEY `uq_property_plant_code` (`fk_plant`,`property_code`,`active_flag`),
  KEY `idx_property_plant_status_name` (`fk_plant`,`status`,`property_name`),
  KEY `fk_property_added_by` (`added_by`),
  CONSTRAINT `fk_property_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_property_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `room_amenities`
--

DROP TABLE IF EXISTS `room_amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `room_amenities` (
  `room_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL,
  PRIMARY KEY (`room_id`,`amenity_id`),
  KEY `idx_ra_amenity` (`amenity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `room_categories`
--

DROP TABLE IF EXISTS `room_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `room_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `short_code` varchar(20) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `max_adults` int(11) DEFAULT NULL,
  `max_children` int(11) DEFAULT NULL,
  `room_size` varchar(30) DEFAULT NULL,
  `room_size_unit` varchar(15) DEFAULT 'sq.ft',
  `bed_type` varchar(40) DEFAULT NULL,
  `bed_count` int(11) DEFAULT NULL,
  `smoking_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `default_tax_id` int(11) DEFAULT NULL COMMENT 'ref gst_rates.id',
  `default_sac_code` varchar(20) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `active_flag` tinyint(1) GENERATED ALWAYS AS (if(`status` = 1,1,NULL)) VIRTUAL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_id_property` (`category_id`,`property_id`),
  UNIQUE KEY `uq_category_name` (`property_id`,`category_name`,`active_flag`),
  UNIQUE KEY `uq_category_short_code` (`property_id`,`short_code`,`active_flag`),
  KEY `idx_category_property_status` (`property_id`,`status`,`display_order`),
  CONSTRAINT `fk_category_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `room_code` varchar(20) NOT NULL,
  `room_no` varchar(30) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `floor_no` varchar(20) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `extra_bed_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `housekeeping_status` varchar(30) DEFAULT 'Available',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `active_flag` tinyint(1) GENERATED ALWAYS AS (if(`status` = 1,1,NULL)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_id_property` (`id`,`property_id`),
  UNIQUE KEY `uq_room_code` (`property_id`,`room_code`,`active_flag`),
  UNIQUE KEY `uq_room_no` (`property_id`,`room_no`,`active_flag`),
  KEY `idx_category` (`category_id`),
  KEY `idx_floor` (`floor_no`),
  KEY `idx_hk` (`housekeeping_status`),
  KEY `idx_room_property_floor` (`property_id`,`floor_no`),
  KEY `idx_room_property_status_category` (`property_id`,`status`,`category_id`),
  KEY `fk_room_category_scope` (`category_id`,`property_id`),
  CONSTRAINT `fk_room_category_scope` FOREIGN KEY (`category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_room_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `state_details`
--

DROP TABLE IF EXISTS `state_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `state_details` (
  `state_code_number` int(11) NOT NULL,
  `state_code` varchar(2) NOT NULL,
  `state_name` varchar(255) NOT NULL,
  `state_status` int(11) NOT NULL,
  `state_ad_by` int(11) NOT NULL,
  `state_ad_dt` datetime NOT NULL,
  PRIMARY KEY (`state_code_number`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `status_details`
--

DROP TABLE IF EXISTS `status_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `status_details` (
  `sd_id` int(11) NOT NULL AUTO_INCREMENT,
  `status_code` varchar(30) NOT NULL,
  `sd_name` varchar(50) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `sd_status` int(11) NOT NULL DEFAULT 1 COMMENT '0 : Inactive, 1 : Active',
  `sd_ad_by` int(11) DEFAULT NULL,
  `sd_ad_dt` datetime DEFAULT NULL,
  PRIMARY KEY (`sd_id`),
  UNIQUE KEY `uq_status_code` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_property_access`
--

DROP TABLE IF EXISTS `user_property_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_property_access` (
  `fk_plant` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`property_id`),
  KEY `idx_upa_assigned_by` (`assigned_by`),
  KEY `idx_upa_plant_property` (`fk_plant`,`property_id`),
  KEY `fk_upa_user_plant` (`user_id`,`fk_plant`),
  KEY `fk_upa_property_plant` (`property_id`,`fk_plant`),
  CONSTRAINT `fk_upa_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_upa_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_upa_user_plant` FOREIGN KEY (`user_id`, `fk_plant`) REFERENCES `users` (`user_id`, `fk_plant`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `mobile_no` varchar(15) NOT NULL,
  `role` enum('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `fk_plant` int(11) DEFAULT NULL COMMENT 'NULL only for super_admin',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `active_flag` tinyint(1) GENERATED ALWAYS AS (if(`status` = 1,1,NULL)) VIRTUAL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_id_plant` (`user_id`,`fk_plant`),
  UNIQUE KEY `uq_users_mobile` (`mobile_no`,`active_flag`),
  KEY `idx_users_plant_role_status` (`fk_plant`,`role`,`status`),
  KEY `fk_user_added_by` (`added_by`),
  CONSTRAINT `fk_user_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_user_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_users_role_plant` CHECK (`role` = 'super_admin' and `fk_plant` is null or `role` in ('admin','user') and `fk_plant` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'stay_management'
--

--
-- Dumping routines for database 'stay_management'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- End of zero-data installer.
