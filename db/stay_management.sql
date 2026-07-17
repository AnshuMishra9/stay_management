-- ============================================================
--  Stay Management System — FULL DATABASE SNAPSHOT
--  Complete structure + dummy/demo data for the whole app.
--
--  This single file recreates the entire `stay_management`
--  database (all tables + demo data) in one import.
--
--  Usage
--    1) (optional) wipe any existing copy:
--         mysql -u root < db/drop_database.sql
--    2) import everything:
--         mysql -u root < db/stay_management.sql
--
--  Demo login (OTP is shown on screen — no SMS gateway):
--    9876543210  /  9988776655        (9123456789 = inactive)
--
--  NOTE: uploaded ID documents live in secure_uploads/ which is
--  git-ignored, so any document_path rows here point to files that
--  are not in the repo (re-upload from the customer form if needed).
-- ============================================================

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: stay_management
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

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `stay_management` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `stay_management`;

--
-- Table structure for table `amenities`
--

DROP TABLE IF EXISTS `amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `amenities` (
  `amenity_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `amenity_name` varchar(60) NOT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`amenity_id`),
  UNIQUE KEY `uq_amenity_name` (`amenity_name`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `amenities`
--

LOCK TABLES `amenities` WRITE;
/*!40000 ALTER TABLE `amenities` DISABLE KEYS */;
INSERT INTO `amenities` VALUES (16,'WiFi','wifi.png',1),(17,'Television','television.png',1),(18,'Smart TV','smart-tv.png',1),(19,'Mini Bar','mini-bar.png',1),(20,'Coffee Machine','coffee-machine.png',1),(21,'Hair Dryer','hair-dryer.png',1),(22,'Safe Locker','safe.png',1),(23,'Iron','iron.png',1),(24,'Bathtub','bathtub.png',1),(25,'Shower','shower.png',1),(26,'Microwave','microwave.png',1),(27,'Air Conditioner','air-conditioner.png',1),(28,'Refrigerator','refrigerator.png',1),(29,'Balcony','balcony-window.png',1),(30,'Work Desk','desk-table.png',1);
/*!40000 ALTER TABLE `amenities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_channels`
--

DROP TABLE IF EXISTS `booking_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_channels` (
  `channel_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_channels`
--

LOCK TABLES `booking_channels` WRITE;
/*!40000 ALTER TABLE `booking_channels` DISABLE KEYS */;
INSERT INTO `booking_channels` VALUES (1,'Walk-in','Direct',0.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(2,'MakeMyTrip','OTA',18.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(3,'Goibibo','OTA',18.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(4,'Booking.com','OTA',15.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(5,'Agoda','OTA',18.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(6,'Expedia','OTA',18.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(7,'Hotels.com','OTA',18.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(8,'Ixigo','OTA',15.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(9,'Cleartrip','OTA',15.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(10,'Yatra','OTA',15.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(11,'Airbnb','OTA',15.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(12,'Trip.com','OTA',15.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(13,'Company','Corporate',0.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(14,'Government','Corporate',0.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43'),(15,'Travel Agent','Agent',10.00,1,'2026-07-03 18:04:43','2026-07-03 18:04:43');
/*!40000 ALTER TABLE `booking_channels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_details`
--

DROP TABLE IF EXISTS `booking_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(20) NOT NULL COMMENT 'human-facing unique, e.g. BKG00001',
  `customer_id` bigint(20) unsigned NOT NULL COMMENT 'FK -> customers.id (whose booking)',
  `booking_channel_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK booking_channels.channel_id',
  `booking_status` enum('enquiry','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT NULL,
  `property_name` varchar(150) DEFAULT NULL,
  `scheduled_check_in_date` date DEFAULT NULL,
  `scheduled_check_out_date` date DEFAULT NULL,
  `length_of_stay` int(11) DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `total_guest` int(11) DEFAULT NULL,
  `room_category_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK room_categories.category_id',
  `room_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK rooms.id ÔÇö allotted room',
  `room_quantity` int(11) DEFAULT NULL,
  `total_unit` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT NULL,
  `remaining_amount` decimal(12,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_booking_number` (`booking_number`),
  KEY `idx_bd_customer` (`customer_id`),
  KEY `idx_bd_status` (`booking_status`),
  KEY `idx_bd_checkin` (`scheduled_check_in_date`),
  KEY `idx_bd_room` (`room_id`),
  CONSTRAINT `fk_bd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bd_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_details`
--

LOCK TABLES `booking_details` WRITE;
/*!40000 ALTER TABLE `booking_details` DISABLE KEYS */;
INSERT INTO `booking_details` VALUES (1,'BKG00001',14,1,'checked_out','Grand Palace Inn','2026-06-20','2026-06-23',3,'2026-06-20 13:00:00','2026-06-23 11:00:00',2,1,NULL,1,NULL,6000.00,6000.00,0.00,'2026-06-18 09:00:00','2026-07-10 21:43:16'),(2,'BKG00002',15,2,'checked_out','Pink City Residency','2026-06-28','2026-07-02',4,'2026-06-28 14:00:00','2026-07-02 10:30:00',3,2,NULL,1,NULL,12000.00,12000.00,0.00,'2026-06-25 09:00:00','2026-07-10 21:43:16'),(3,'BKG00003',16,9,'checked_out','Kongu Comforts','2026-07-01','2026-07-04',3,'2026-07-01 13:30:00','2026-07-04 11:00:00',2,3,NULL,1,NULL,15000.00,15000.00,0.00,'2026-06-29 09:00:00','2026-07-10 21:43:16'),(4,'BKG00004',17,4,'checked_in','Sea Breeze Resort','2026-07-06','2026-07-12',6,'2026-07-06 15:00:00',NULL,2,4,NULL,1,NULL,24000.00,10000.00,14000.00,'2026-07-02 09:00:00','2026-07-10 21:43:16'),(5,'BKG00005',18,5,'checked_in','Backwater Suites','2026-07-08','2026-07-16',8,'2026-07-08 12:30:00',NULL,4,3,NULL,2,NULL,32000.00,16000.00,16000.00,'2026-07-03 09:00:00','2026-07-10 21:43:16'),(6,'BKG00006',19,15,'checked_in','Lake View Haveli','2026-07-09','2026-07-14',5,'2026-07-09 12:00:00',NULL,4,4,NULL,2,NULL,45000.00,15000.00,30000.00,'2026-07-04 09:00:00','2026-07-10 21:43:16'),(7,'BKG00007',20,3,'confirmed','Capital Stay','2026-07-11','2026-07-13',2,NULL,NULL,2,1,NULL,1,NULL,5000.00,2000.00,3000.00,'2026-07-05 09:00:00','2026-07-10 21:43:16'),(8,'BKG00008',21,11,'confirmed','Marina Comforts','2026-07-14','2026-07-18',4,NULL,NULL,2,2,NULL,1,NULL,14000.00,5000.00,9000.00,'2026-07-06 09:00:00','2026-07-10 21:43:16'),(9,'BKG00009',22,6,'confirmed','Charminar Grand','2026-07-20','2026-07-25',5,NULL,NULL,3,5,NULL,1,NULL,40000.00,20000.00,20000.00,'2026-07-07 09:00:00','2026-07-10 21:43:16'),(10,'BKG00010',23,14,'confirmed','Beachfront Executive','2026-07-22','2026-07-24',2,NULL,NULL,2,2,NULL,1,NULL,10000.00,3000.00,7000.00,'2026-07-08 09:00:00','2026-07-10 21:43:16'),(11,'BKG00011',24,2,'enquiry','Hillside Retreat','2026-08-01','2026-08-05',4,NULL,NULL,2,2,NULL,1,NULL,15000.00,0.00,15000.00,'2026-07-08 09:00:00','2026-07-10 21:43:16'),(12,'BKG00012',25,13,'enquiry','Tech Park Stay','2026-08-10','2026-08-12',2,NULL,NULL,1,1,NULL,1,NULL,6000.00,0.00,6000.00,'2026-07-08 09:00:00','2026-07-10 21:43:16'),(13,'BKG00013',26,10,'cancelled','Capital Comforts','2026-07-15','2026-07-17',2,NULL,NULL,2,1,NULL,1,NULL,5000.00,1000.00,4000.00,'2026-07-06 09:00:00','2026-07-10 21:43:16'),(14,'BKG00014',27,1,'no_show','Taj Nagari Inn','2026-07-05','2026-07-07',2,NULL,NULL,2,2,NULL,1,NULL,8000.00,8000.00,0.00,'2026-07-03 09:00:00','2026-07-10 21:43:16'),(21,'BKG00015',27,NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-13 18:00:59','2026-07-13 18:00:59'),(23,'BKG00016',27,1,'checked_in','',NULL,NULL,NULL,'2026-07-15 18:43:00',NULL,NULL,NULL,9,NULL,NULL,20000.00,10000.00,10000.00,'2026-07-15 18:44:08','2026-07-15 18:44:08');
/*!40000 ALTER TABLE `booking_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_identities`
--

DROP TABLE IF EXISTS `customer_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_identities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `identity_type` varchar(30) NOT NULL,
  `identity_number` varchar(50) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ci_customer` (`customer_id`),
  CONSTRAINT `fk_ci_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_identities`
--

LOCK TABLES `customer_identities` WRITE;
/*!40000 ALTER TABLE `customer_identities` DISABLE KEYS */;
INSERT INTO `customer_identities` VALUES (1,10,'aadhar','234567890','customers/CUST00006/aadhar_card.png','2026-07-15 15:22:32',NULL),(2,10,'pan','234567890',NULL,'2026-07-15 15:22:32',NULL);
/*!40000 ALTER TABLE `customer_identities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `pincode` varchar(15) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_code` (`customer_code`),
  KEY `idx_name` (`customer_name`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'CUST00001','Sunrise Residency','9483700451','571107','India',1,'2026-07-01 13:40:42',NULL),(2,'CUST00002','Blue Orchid Hotel','9123212345','560001','India',1,'2026-07-01 13:40:42',NULL),(3,'CUST00003','Green Valley Inn','9944456772','641018','India',1,'2026-07-01 13:40:42',NULL),(4,'CUST00004','Seaside Comforts','9822222222','600001','India',0,'2026-07-01 13:40:42',NULL),(5,'CUST00005','Hilltop Stays','9999999991','629001','India',1,'2026-07-01 13:40:42',NULL),(10,'CUST00006','user6','1234567891','110084','India',1,'2026-07-01 14:54:01','2026-07-08 12:41:47'),(14,'CUST00007','Aarav Sharma','9810012341',NULL,'India',1,'2026-06-18 09:00:00','2026-07-13 17:50:12'),(15,'CUST00008','Isha Verma','9820022342',NULL,'India',1,'2026-06-25 09:00:00',NULL),(16,'CUST00018','Divya Menon','9840042343',NULL,'India',1,'2026-06-29 09:00:00',NULL),(17,'CUST00009','Rohan Mehta','9830032341',NULL,'India',1,'2026-07-02 09:00:00',NULL),(18,'CUST00010','Priya Nair','9840012344',NULL,'India',1,'2026-07-03 09:00:00',NULL),(19,'CUST00019','Aditya Nanda','9850052341',NULL,'India',1,'2026-07-04 09:00:00',NULL),(20,'CUST00011','Karan Singh','9811012345',NULL,'India',1,'2026-07-05 09:00:00',NULL),(21,'CUST00012','Ananya Iyer','9822012346',NULL,'India',1,'2026-07-06 09:00:00',NULL),(22,'CUST00013','Vikram Rao','9833012347',NULL,'India',1,'2026-07-07 09:00:00',NULL),(23,'CUST00020','Nisha Reddy','9860062341',NULL,'India',1,'2026-07-08 09:00:00',NULL),(24,'CUST00014','Sneha Joshi','9844012348',NULL,'India',1,'2026-07-08 09:00:00',NULL),(25,'CUST00015','Arjun Kumar','9855012349',NULL,'India',1,'2026-07-08 09:00:00',NULL),(26,'CUST00016','Meera Pillai','9846012350',NULL,'India',1,'2026-07-06 09:00:00',NULL),(27,'CUST00017','Rahul Gupta','9812012351','','India',1,'2026-07-03 09:00:00','2026-07-15 18:44:08');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_requests`
--

DROP TABLE IF EXISTS `otp_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`user_id`,`is_verified`,`expires_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_requests`
--

LOCK TABLES `otp_requests` WRITE;
/*!40000 ALTER TABLE `otp_requests` DISABLE KEYS */;
INSERT INTO `otp_requests` VALUES (1,1,'842569','2026-07-01 08:04:45',1,0,'2026-07-01 08:02:45'),(2,1,'729160','2026-07-01 08:04:59',0,0,'2026-07-01 08:02:59'),(3,1,'861949','2026-07-01 08:04:59',1,1,'2026-07-01 08:02:59'),(6,1,'235619','2026-07-01 08:06:07',1,0,'2026-07-01 08:04:07'),(8,1,'714770','2026-07-01 08:10:18',1,0,'2026-07-01 08:08:18'),(9,1,'277606','2026-07-01 08:11:42',1,0,'2026-07-01 08:09:42'),(10,1,'330690','2026-07-01 11:46:45',0,0,'2026-07-01 11:44:45'),(11,1,'732898','2026-07-01 11:49:49',1,0,'2026-07-01 11:47:49'),(12,1,'847589','2026-07-01 11:52:08',1,0,'2026-07-01 11:50:08'),(13,1,'429611','2026-07-01 11:53:19',1,0,'2026-07-01 11:51:19'),(14,1,'192648','2026-07-01 11:54:41',0,2,'2026-07-01 11:52:41'),(15,1,'413960','2026-07-01 12:58:10',1,0,'2026-07-01 12:56:10'),(16,1,'791291','2026-07-01 13:53:02',1,0,'2026-07-01 13:51:02'),(17,1,'850357','2026-07-01 13:53:30',1,0,'2026-07-01 13:51:30'),(18,1,'329009','2026-07-01 13:55:31',1,0,'2026-07-01 13:53:31'),(19,1,'800260','2026-07-01 13:57:48',1,0,'2026-07-01 13:55:48'),(20,1,'190690','2026-07-01 14:38:05',1,0,'2026-07-01 14:36:05'),(21,1,'537763','2026-07-01 14:46:49',1,0,'2026-07-01 14:44:49'),(22,1,'536564','2026-07-01 14:48:32',1,0,'2026-07-01 14:46:32'),(23,1,'299497','2026-07-01 14:59:36',1,0,'2026-07-01 14:57:36'),(24,1,'809875','2026-07-01 15:01:34',1,0,'2026-07-01 14:59:34'),(25,1,'669092','2026-07-01 15:12:31',1,0,'2026-07-01 15:10:31'),(26,1,'768842','2026-07-01 15:19:02',1,0,'2026-07-01 15:17:02'),(27,1,'822089','2026-07-01 15:54:54',1,0,'2026-07-01 15:52:54'),(28,1,'786402','2026-07-01 16:16:12',1,0,'2026-07-01 16:14:12'),(29,1,'224762','2026-07-02 10:28:36',1,0,'2026-07-02 10:26:36'),(30,1,'274048','2026-07-02 12:29:55',1,0,'2026-07-02 12:27:55'),(31,1,'656759','2026-07-02 12:30:30',1,0,'2026-07-02 12:28:30'),(32,1,'701352','2026-07-02 12:48:34',1,0,'2026-07-02 12:46:34'),(33,1,'316400','2026-07-02 13:21:15',1,0,'2026-07-02 13:19:15'),(34,1,'298346','2026-07-02 14:10:17',1,0,'2026-07-02 14:08:17'),(35,1,'148878','2026-07-02 14:29:28',1,0,'2026-07-02 14:27:28'),(36,1,'613563','2026-07-02 16:38:49',1,0,'2026-07-02 16:36:49'),(37,1,'393799','2026-07-02 16:43:41',1,0,'2026-07-02 16:41:41'),(38,1,'729918','2026-07-02 16:46:10',1,0,'2026-07-02 16:44:10'),(39,1,'426225','2026-07-02 16:50:39',1,0,'2026-07-02 16:48:39'),(40,1,'300350','2026-07-03 09:49:03',1,0,'2026-07-03 09:47:03'),(41,1,'905742','2026-07-03 10:27:14',1,0,'2026-07-03 10:25:14'),(42,1,'467795','2026-07-03 10:39:54',1,0,'2026-07-03 10:37:54'),(43,1,'639213','2026-07-03 11:17:17',1,0,'2026-07-03 11:15:17'),(44,1,'365594','2026-07-03 11:27:08',1,0,'2026-07-03 11:25:08'),(45,1,'264102','2026-07-03 11:30:50',1,0,'2026-07-03 11:28:50'),(46,1,'161323','2026-07-03 11:47:45',1,0,'2026-07-03 11:45:45'),(47,1,'970502','2026-07-03 12:22:15',1,0,'2026-07-03 12:20:15'),(48,1,'184640','2026-07-03 12:28:49',1,0,'2026-07-03 12:26:49'),(49,1,'767671','2026-07-03 12:32:07',1,0,'2026-07-03 12:30:07'),(50,1,'819625','2026-07-03 17:09:27',1,0,'2026-07-03 17:07:27'),(51,1,'588845','2026-07-03 17:10:13',1,0,'2026-07-03 17:08:13'),(52,1,'401047','2026-07-03 17:10:44',1,0,'2026-07-03 17:08:44'),(53,1,'457909','2026-07-03 17:11:35',1,0,'2026-07-03 17:09:35'),(54,1,'823312','2026-07-03 17:25:34',1,0,'2026-07-03 17:23:34'),(55,1,'274289','2026-07-03 17:26:07',1,0,'2026-07-03 17:24:07'),(56,1,'205092','2026-07-03 18:30:53',1,0,'2026-07-03 18:28:53'),(57,1,'326978','2026-07-03 18:31:47',1,0,'2026-07-03 18:29:47'),(58,1,'114993','2026-07-08 10:53:45',1,0,'2026-07-08 10:51:45'),(59,1,'190268','2026-07-08 11:56:52',1,0,'2026-07-08 11:54:52'),(60,1,'105934','2026-07-08 12:41:14',1,0,'2026-07-08 12:39:14'),(61,1,'746458','2026-07-08 13:38:23',1,0,'2026-07-08 13:36:23'),(62,1,'515690','2026-07-08 13:47:39',1,0,'2026-07-08 13:45:39'),(63,1,'948909','2026-07-08 14:13:38',1,0,'2026-07-08 14:11:38'),(64,1,'194386','2026-07-08 14:30:59',1,0,'2026-07-08 14:28:59'),(65,1,'378205','2026-07-08 14:39:36',1,0,'2026-07-08 14:37:36'),(66,1,'824202','2026-07-09 10:46:12',1,0,'2026-07-09 10:44:12'),(67,1,'527068','2026-07-09 11:01:34',1,0,'2026-07-09 10:59:34'),(68,1,'146779','2026-07-09 11:08:01',1,0,'2026-07-09 11:06:01'),(69,1,'605447','2026-07-09 11:12:46',1,0,'2026-07-09 11:10:46'),(70,1,'824943','2026-07-09 11:15:18',1,0,'2026-07-09 11:13:18'),(71,1,'409621','2026-07-09 12:13:03',1,0,'2026-07-09 12:11:03'),(72,1,'111084','2026-07-09 12:41:04',1,0,'2026-07-09 12:39:04'),(73,1,'666717','2026-07-09 14:02:24',1,0,'2026-07-09 14:00:24'),(74,1,'438378','2026-07-10 12:34:05',1,0,'2026-07-10 12:32:05'),(75,1,'537006','2026-07-10 12:37:16',1,0,'2026-07-10 12:35:16'),(76,1,'391002','2026-07-10 12:42:12',1,0,'2026-07-10 12:40:12'),(77,1,'585258','2026-07-10 13:22:35',1,0,'2026-07-10 13:20:35'),(78,1,'510490','2026-07-10 13:52:57',1,0,'2026-07-10 13:50:57'),(79,1,'490652','2026-07-10 13:54:06',1,0,'2026-07-10 13:52:06'),(80,1,'950977','2026-07-10 14:23:55',1,0,'2026-07-10 14:21:55'),(81,1,'944809','2026-07-10 14:26:39',1,0,'2026-07-10 14:24:39'),(82,1,'415641','2026-07-10 14:30:20',1,0,'2026-07-10 14:28:20'),(83,1,'250772','2026-07-10 14:48:43',1,0,'2026-07-10 14:46:43'),(84,1,'799516','2026-07-10 14:49:39',1,0,'2026-07-10 14:47:39'),(85,1,'484438','2026-07-10 14:53:48',1,0,'2026-07-10 14:51:48'),(86,1,'864579','2026-07-10 15:12:04',1,0,'2026-07-10 15:10:04'),(87,1,'993384','2026-07-10 21:50:46',1,0,'2026-07-10 21:48:46'),(88,1,'185144','2026-07-10 21:57:37',1,0,'2026-07-10 21:55:37'),(89,1,'235784','2026-07-13 11:27:48',1,0,'2026-07-13 11:25:48'),(90,1,'286114','2026-07-13 11:46:43',1,0,'2026-07-13 11:44:43'),(91,1,'986814','2026-07-13 11:47:49',1,0,'2026-07-13 11:45:49'),(92,1,'625538','2026-07-13 17:43:41',1,0,'2026-07-13 17:41:41'),(93,1,'540426','2026-07-13 17:56:32',1,0,'2026-07-13 17:54:32'),(94,1,'694099','2026-07-15 14:16:21',1,0,'2026-07-15 14:14:21'),(95,1,'714164','2026-07-15 15:02:47',1,0,'2026-07-15 15:00:47'),(96,1,'162102','2026-07-15 15:34:43',1,0,'2026-07-15 15:32:43'),(97,1,'499946','2026-07-15 18:41:29',1,0,'2026-07-15 18:39:29');
/*!40000 ALTER TABLE `otp_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `room_amenities`
--

DROP TABLE IF EXISTS `room_amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `room_amenities` (
  `room_id` bigint(20) unsigned NOT NULL,
  `amenity_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`room_id`,`amenity_id`),
  KEY `idx_ra_amenity` (`amenity_id`),
  CONSTRAINT `fk_ra_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`amenity_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `room_amenities`
--

LOCK TABLES `room_amenities` WRITE;
/*!40000 ALTER TABLE `room_amenities` DISABLE KEYS */;
INSERT INTO `room_amenities` VALUES (6,23),(6,25),(6,27);
/*!40000 ALTER TABLE `room_amenities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `room_categories`
--

DROP TABLE IF EXISTS `room_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `room_categories` (
  `category_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
  `base_price` decimal(12,2) DEFAULT NULL,
  `default_tax_id` bigint(20) unsigned DEFAULT NULL,
  `default_sac_code` varchar(20) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `room_categories`
--

LOCK TABLES `room_categories` WRITE;
/*!40000 ALTER TABLE `room_categories` DISABLE KEYS */;
INSERT INTO `room_categories` VALUES (1,'Standard','STD','Comfortable standard room',2,1,'180','sq.ft','Double',1,0,2000.00,3,'996311',NULL,1,1,'2026-07-02 16:25:10',NULL),(2,'Deluxe','DLX','Spacious deluxe room',2,2,'250','sq.ft','Queen',1,0,3200.00,4,'996311',NULL,2,1,'2026-07-02 16:25:10',NULL),(3,'Super Deluxe','SDLX','Premium super deluxe room',3,2,'320','sq.ft','King',1,0,4500.00,4,'996311',NULL,3,1,'2026-07-02 16:25:10',NULL),(4,'Suite','STE','Luxury suite with living area',3,2,'480','sq.ft','King',1,0,7000.00,4,'996311',NULL,4,1,'2026-07-02 16:25:10',NULL),(5,'Executive Suite','EXE','Top-tier executive suite',4,2,'650','sq.ft','King',2,0,9500.00,4,'996311',NULL,5,1,'2026-07-02 16:25:10',NULL);
/*!40000 ALTER TABLE `room_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_code` varchar(20) NOT NULL,
  `room_no` varchar(30) NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `floor_no` varchar(20) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `extra_bed_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `selling_price` decimal(12,2) DEFAULT NULL,
  `housekeeping_status` varchar(30) DEFAULT 'Available',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(20) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`room_code`),
  UNIQUE KEY `uq_room_no` (`room_no`),
  KEY `idx_category` (`category_id`),
  KEY `idx_floor` (`floor_no`),
  KEY `idx_hk` (`housekeeping_status`),
  CONSTRAINT `fk_room_category` FOREIGN KEY (`category_id`) REFERENCES `room_categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,'ROOM00001','101',1,'1','Cosy standard room overlooking the garden.',NULL,0,2200.00,'Available',1,'9876543210','2026-07-02 16:25:10',NULL,NULL),(2,'ROOM00002','102',1,'1','Standard room with a city-facing window.',NULL,1,2200.00,'Not Available',1,'9876543210','2026-07-02 16:25:10',NULL,NULL),(3,'ROOM00003','201',2,'2','Spacious deluxe room with queen bed.',NULL,1,3500.00,'Available',1,'9876543210','2026-07-02 16:25:10',NULL,NULL),(4,'ROOM00004','202',2,'2','Deluxe room, connects to 201.','Family friendly',1,3500.00,'Available',0,'9876543210','2026-07-02 16:25:10',NULL,NULL),(5,'ROOM00005','301',3,'3','Premium super deluxe with king bed.',NULL,1,4900.00,'Available',1,'9876543210','2026-07-02 16:25:10',NULL,NULL),(6,'ROOM00006','401',4,'4','Luxury suite with separate living area.','VIP',1,7800.00,'Not Available',1,'9876543210','2026-07-02 16:25:10','9876543210','2026-07-03 11:45:17'),(9,'ROOM00007','501',3,'','','',0,19999.00,'Available',1,'9876543210','2026-07-15 18:42:22',NULL,NULL);
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `state_details`
--

LOCK TABLES `state_details` WRITE;
/*!40000 ALTER TABLE `state_details` DISABLE KEYS */;
INSERT INTO `state_details` VALUES (1,'JK','Jammu and Kashmir',1,1001,'2020-10-18 15:15:25'),(2,'HP','Himachal Pradesh',1,1001,'2020-10-18 15:15:25'),(3,'PU','Punjab',1,1001,'2020-10-18 15:15:25'),(4,'CG','Chandigarh',1,1001,'2020-10-18 15:15:25'),(5,'UT','Uttarakhand',1,1001,'2020-10-18 15:15:25'),(6,'HA','Haryana',1,1001,'2020-10-18 15:15:25'),(7,'ND','Delhi',1,1001,'2020-10-18 15:15:25'),(8,'RA','Rajasthan',1,1001,'2020-10-18 15:15:25'),(9,'UP','Uttar Pradesh',1,1001,'2020-10-18 15:15:25'),(10,'BI','Bihar',1,1001,'2020-10-18 15:15:25'),(11,'SI','Sikkim',1,1001,'2020-10-18 15:15:25'),(12,'AL','Arunachal Pradesh',1,1001,'2020-10-18 15:15:25'),(13,'NG','Nagaland',1,1001,'2020-10-18 15:15:25'),(14,'MA','Manipur',1,1001,'2020-10-18 15:15:25'),(15,'MI','Mizoram',1,1001,'2020-10-18 15:15:25'),(16,'TI','Tripura',1,1001,'2020-10-18 15:15:25'),(17,'ME','Meghalaya',1,1001,'2020-10-18 15:15:25'),(18,'AS','Assam',1,1001,'2020-10-18 15:15:25'),(19,'WB','West Bengal',1,1001,'2020-10-18 15:15:25'),(20,'JH','Jharkhand',1,1001,'2020-10-18 15:15:25'),(21,'OR','Odisha',1,1001,'2020-10-18 15:15:25'),(22,'CH','Chhattisgarh',1,1001,'2020-10-18 15:15:25'),(23,'MP','Madhya Pradesh',1,1001,'2020-10-18 15:15:25'),(24,'GU','Gujarat',1,1001,'2020-10-18 15:15:25'),(25,'DD','Daman & Diu',1,1001,'2020-10-18 15:15:25'),(26,'DN','Dadra & Nagar Haveli',1,1001,'2020-10-18 15:15:25'),(27,'MH','Maharashtra',1,1001,'2020-10-18 15:15:25'),(29,'KA','Karnataka',1,1001,'2020-10-18 15:15:25'),(30,'GA','Goa',1,1001,'2020-10-18 15:15:25'),(31,'LA','Lakshdweep',1,1001,'2020-10-18 15:15:25'),(32,'KL','Kerala',1,1001,'2020-10-18 15:15:25'),(33,'TN','Tamil Nadu',1,1001,'2020-10-18 15:15:25'),(34,'PO','Puducherry',1,1001,'2020-10-18 15:15:25'),(35,'AN','Andaman & Nicobar Islands',1,1001,'2020-10-18 15:15:25'),(36,'TG','Telangana',1,1001,'2020-10-18 15:15:25'),(37,'AP','Andhra Pradesh',1,1001,'2020-10-18 15:15:25'),(96,'','Others',1,1001,'2020-10-18 15:15:25'),(38,'LA','Ladakh',1,1001,'2021-06-15 15:15:25');
/*!40000 ALTER TABLE `state_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `taxes`
--

DROP TABLE IF EXISTS `taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taxes` (
  `tax_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(60) NOT NULL,
  `tax_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`tax_id`),
  UNIQUE KEY `uq_tax_name` (`tax_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taxes`
--

LOCK TABLES `taxes` WRITE;
/*!40000 ALTER TABLE `taxes` DISABLE KEYS */;
INSERT INTO `taxes` VALUES (1,'GST 0%',0.00,1),(2,'GST 5%',5.00,1),(3,'GST 12%',12.00,1),(4,'GST 18%',18.00,1),(5,'GST 28%',28.00,1);
/*!40000 ALTER TABLE `taxes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mobile_no` varchar(15) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_mobile` (`mobile_no`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'9876543210',1,'2026-07-15 18:39:38','2026-07-01 11:30:40'),(2,'9988776655',1,'2026-07-01 08:03:54','2026-07-01 11:30:40'),(3,'9123456789',0,NULL,'2026-07-01 11:30:40');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

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

-- Dump completed on 2026-07-17 10:50:54
