-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 02, 2026 at 05:40 AM
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
-- Database: `brand_care`
--

-- --------------------------------------------------------

--
-- Table structure for table `state_details`
--

CREATE TABLE `state_details` (
  `state_code_number` int(11) NOT NULL,
  `state_code` varchar(2) NOT NULL,
  `state_name` varchar(255) NOT NULL,
  `state_status` int(11) NOT NULL,
  `state_ad_by` int(11) NOT NULL,
  `state_ad_dt` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `state_details`
--

INSERT INTO `state_details` (`state_code_number`, `state_code`, `state_name`, `state_status`, `state_ad_by`, `state_ad_dt`) VALUES
(1, 'JK', 'Jammu and Kashmir', 1, 1001, '2020-10-18 15:15:25'),
(2, 'HP', 'Himachal Pradesh', 1, 1001, '2020-10-18 15:15:25'),
(3, 'PU', 'Punjab', 1, 1001, '2020-10-18 15:15:25'),
(4, 'CG', 'Chandigarh', 1, 1001, '2020-10-18 15:15:25'),
(5, 'UT', 'Uttarakhand', 1, 1001, '2020-10-18 15:15:25'),
(6, 'HA', 'Haryana', 1, 1001, '2020-10-18 15:15:25'),
(7, 'ND', 'Delhi', 1, 1001, '2020-10-18 15:15:25'),
(8, 'RA', 'Rajasthan', 1, 1001, '2020-10-18 15:15:25'),
(9, 'UP', 'Uttar Pradesh', 1, 1001, '2020-10-18 15:15:25'),
(10, 'BI', 'Bihar', 1, 1001, '2020-10-18 15:15:25'),
(11, 'SI', 'Sikkim', 1, 1001, '2020-10-18 15:15:25'),
(12, 'AL', 'Arunachal Pradesh', 1, 1001, '2020-10-18 15:15:25'),
(13, 'NG', 'Nagaland', 1, 1001, '2020-10-18 15:15:25'),
(14, 'MA', 'Manipur', 1, 1001, '2020-10-18 15:15:25'),
(15, 'MI', 'Mizoram', 1, 1001, '2020-10-18 15:15:25'),
(16, 'TI', 'Tripura', 1, 1001, '2020-10-18 15:15:25'),
(17, 'ME', 'Meghalaya', 1, 1001, '2020-10-18 15:15:25'),
(18, 'AS', 'Assam', 1, 1001, '2020-10-18 15:15:25'),
(19, 'WB', 'West Bengal', 1, 1001, '2020-10-18 15:15:25'),
(20, 'JH', 'Jharkhand', 1, 1001, '2020-10-18 15:15:25'),
(21, 'OR', 'Odisha', 1, 1001, '2020-10-18 15:15:25'),
(22, 'CH', 'Chhattisgarh', 1, 1001, '2020-10-18 15:15:25'),
(23, 'MP', 'Madhya Pradesh', 1, 1001, '2020-10-18 15:15:25'),
(24, 'GU', 'Gujarat', 1, 1001, '2020-10-18 15:15:25'),
(25, 'DD', 'Daman & Diu', 1, 1001, '2020-10-18 15:15:25'),
(26, 'DN', 'Dadra & Nagar Haveli', 1, 1001, '2020-10-18 15:15:25'),
(27, 'MH', 'Maharashtra', 1, 1001, '2020-10-18 15:15:25'),
(29, 'KA', 'Karnataka', 1, 1001, '2020-10-18 15:15:25'),
(30, 'GA', 'Goa', 1, 1001, '2020-10-18 15:15:25'),
(31, 'LA', 'Lakshdweep', 1, 1001, '2020-10-18 15:15:25'),
(32, 'KL', 'Kerala', 1, 1001, '2020-10-18 15:15:25'),
(33, 'TN', 'Tamil Nadu', 1, 1001, '2020-10-18 15:15:25'),
(34, 'PO', 'Puducherry', 1, 1001, '2020-10-18 15:15:25'),
(35, 'AN', 'Andaman & Nicobar Islands', 1, 1001, '2020-10-18 15:15:25'),
(36, 'TG', 'Telangana', 1, 1001, '2020-10-18 15:15:25'),
(37, 'AP', 'Andhra Pradesh', 1, 1001, '2020-10-18 15:15:25'),
(96, '', 'Others', 1, 1001, '2020-10-18 15:15:25'),
(38, 'LA', 'Ladakh', 1, 1001, '2021-06-15 15:15:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `state_details`
--
ALTER TABLE `state_details`
  ADD PRIMARY KEY (`state_code_number`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
