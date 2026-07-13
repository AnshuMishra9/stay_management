-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 13, 2026 at 01:23 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stay_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `booking_details`
--

CREATE TABLE `booking_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_number` varchar(20) NOT NULL COMMENT 'human-facing unique, e.g. BKG00001',
  `customer_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK -> customers.id (whose booking)',
  `booking_channel_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK booking_channels.channel_id',
  `booking_status` enum('enquiry','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT NULL,
  `booking_by` varchar(150) DEFAULT NULL,
  `guest_name` varchar(150) DEFAULT NULL,
  `guest_mobile_no` varchar(20) DEFAULT NULL,
  `guest_contact_no` varchar(20) DEFAULT NULL,
  `property_name` varchar(150) DEFAULT NULL,
  `scheduled_check_in_date` date DEFAULT NULL,
  `scheduled_check_out_date` date DEFAULT NULL,
  `length_of_stay` int(11) DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `total_guest` int(11) DEFAULT NULL,
  `room_category_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK room_categories.category_id',
  `room_quantity` int(11) DEFAULT NULL,
  `total_unit` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT NULL,
  `remaining_amount` decimal(12,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_details`
--

INSERT INTO `booking_details` (`id`, `booking_number`, `customer_id`, `booking_channel_id`, `booking_status`, `booking_by`, `guest_name`, `guest_mobile_no`, `guest_contact_no`, `property_name`, `scheduled_check_in_date`, `scheduled_check_out_date`, `length_of_stay`, `checked_in_at`, `checked_out_at`, `total_guest`, `room_category_id`, `room_quantity`, `total_unit`, `total_amount`, `amount_paid`, `remaining_amount`, `created_at`, `updated_at`) VALUES
(1, 'BKG00001', 14, 1, 'checked_out', 'Aarav Sharma', 'Aarav Sharma', '9810012341', NULL, 'Grand Palace Inn', '2026-06-20', '2026-06-23', 3, '2026-06-20 13:00:00', '2026-06-23 11:00:00', 2, 1, 1, NULL, 6000.00, 6000.00, 0.00, '2026-06-18 09:00:00', '2026-07-10 21:43:16'),
(2, 'BKG00002', 15, 2, 'checked_out', 'Isha Verma', 'Isha Verma', '9820022342', NULL, 'Pink City Residency', '2026-06-28', '2026-07-02', 4, '2026-06-28 14:00:00', '2026-07-02 10:30:00', 3, 2, 1, NULL, 12000.00, 12000.00, 0.00, '2026-06-25 09:00:00', '2026-07-10 21:43:16'),
(3, 'BKG00003', 16, 9, 'checked_out', 'Divya Menon', 'Divya Menon', '9840042343', NULL, 'Kongu Comforts', '2026-07-01', '2026-07-04', 3, '2026-07-01 13:30:00', '2026-07-04 11:00:00', 2, 3, 1, NULL, 15000.00, 15000.00, 0.00, '2026-06-29 09:00:00', '2026-07-10 21:43:16'),
(4, 'BKG00004', 17, 4, 'checked_in', 'Rohan Mehta', 'Rohan Mehta', '9830032341', NULL, 'Sea Breeze Resort', '2026-07-06', '2026-07-12', 6, '2026-07-06 15:00:00', NULL, 2, 4, 1, NULL, 24000.00, 10000.00, 14000.00, '2026-07-02 09:00:00', '2026-07-10 21:43:16'),
(5, 'BKG00005', 18, 5, 'checked_in', 'Priya Nair', 'Priya Nair', '9840012344', NULL, 'Backwater Suites', '2026-07-08', '2026-07-16', 8, '2026-07-08 12:30:00', NULL, 4, 3, 2, NULL, 32000.00, 16000.00, 16000.00, '2026-07-03 09:00:00', '2026-07-10 21:43:16'),
(6, 'BKG00006', 19, 15, 'checked_in', 'Aditya Nanda', 'Aditya Nanda', '9850052341', NULL, 'Lake View Haveli', '2026-07-09', '2026-07-14', 5, '2026-07-09 12:00:00', NULL, 4, 4, 2, NULL, 45000.00, 15000.00, 30000.00, '2026-07-04 09:00:00', '2026-07-10 21:43:16'),
(7, 'BKG00007', 20, 3, 'confirmed', 'Karan Singh', 'Karan Singh', '9811012345', NULL, 'Capital Stay', '2026-07-11', '2026-07-13', 2, NULL, NULL, 2, 1, 1, NULL, 5000.00, 2000.00, 3000.00, '2026-07-05 09:00:00', '2026-07-10 21:43:16'),
(8, 'BKG00008', 21, 11, 'confirmed', 'Ananya Iyer', 'Ananya Iyer', '9822012346', NULL, 'Marina Comforts', '2026-07-14', '2026-07-18', 4, NULL, NULL, 2, 2, 1, NULL, 14000.00, 5000.00, 9000.00, '2026-07-06 09:00:00', '2026-07-10 21:43:16'),
(9, 'BKG00009', 22, 6, 'confirmed', 'Vikram Rao', 'Vikram Rao', '9833012347', NULL, 'Charminar Grand', '2026-07-20', '2026-07-25', 5, NULL, NULL, 3, 5, 1, NULL, 40000.00, 20000.00, 20000.00, '2026-07-07 09:00:00', '2026-07-10 21:43:16'),
(10, 'BKG00010', 23, 14, 'confirmed', 'Govt Dept', 'Nisha Reddy', '9860062341', NULL, 'Beachfront Executive', '2026-07-22', '2026-07-24', 2, NULL, NULL, 2, 2, 1, NULL, 10000.00, 3000.00, 7000.00, '2026-07-08 09:00:00', '2026-07-10 21:43:16'),
(11, 'BKG00011', 24, 2, 'enquiry', 'Sneha Joshi', 'Sneha Joshi', '9844012348', NULL, 'Hillside Retreat', '2026-08-01', '2026-08-05', 4, NULL, NULL, 2, 2, 1, NULL, 15000.00, 0.00, 15000.00, '2026-07-08 09:00:00', '2026-07-10 21:43:16'),
(12, 'BKG00012', 25, 13, 'enquiry', 'ABC Pvt Ltd', 'Arjun Kumar', '9855012349', NULL, 'Tech Park Stay', '2026-08-10', '2026-08-12', 2, NULL, NULL, 1, 1, 1, NULL, 6000.00, 0.00, 6000.00, '2026-07-08 09:00:00', '2026-07-10 21:43:16'),
(13, 'BKG00013', 26, 10, 'cancelled', 'Meera Pillai', 'Meera Pillai', '9846012350', NULL, 'Capital Comforts', '2026-07-15', '2026-07-17', 2, NULL, NULL, 2, 1, 1, NULL, 5000.00, 1000.00, 4000.00, '2026-07-06 09:00:00', '2026-07-10 21:43:16'),
(14, 'BKG00014', 27, 1, 'no_show', 'Rahul Gupta', 'Rahul Gupta', '9812012351', NULL, 'Taj Nagari Inn', '2026-07-05', '2026-07-07', 2, NULL, NULL, 2, 2, 1, NULL, 8000.00, 8000.00, 0.00, '2026-07-03 09:00:00', '2026-07-10 21:43:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_booking_number` (`booking_number`),
  ADD KEY `idx_bd_customer` (`customer_id`),
  ADD KEY `idx_bd_status` (`booking_status`),
  ADD KEY `idx_bd_checkin` (`scheduled_check_in_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking_details`
--
ALTER TABLE `booking_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD CONSTRAINT `fk_bd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
