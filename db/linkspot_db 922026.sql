-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 09, 2026 at 09:27 AM
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
-- Database: `linkspot_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `activity_date` date NOT NULL,
  `activity_time` time NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` varchar(500) NOT NULL,
  `details` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `activity_date`, `activity_time`, `activity_type`, `description`, `details`, `user_id`, `created_at`) VALUES
(18, '2026-01-25', '22:00:07', 'Linkspot Members', 'Added new member: Gilbert Takudzwa Mashawi', 'Code: LS2601001, Package: monthly, Rate: $65', 1, '2026-01-26 06:00:07'),
(19, '2026-01-25', '23:50:59', 'Summarcity Tenants', 'Added new tenant: Gilbert Takudzwa Mashawi', 'Business: uz, Shop: S1, Rent: $32, Member Code: SM2601001', 1, '2026-01-26 07:50:59'),
(20, '2026-01-26', '02:25:00', 'Meeting Rooms', 'Booked Board Room 1 for ghasajs', 'Hours: 1, Cost: $10', 1, '2026-01-26 10:25:00'),
(21, '2026-01-26', '02:40:37', 'Meeting Rooms', 'Deleted booking for Board Room 1 by ghasajs', NULL, 1, '2026-01-26 10:40:37'),
(22, '2026-01-26', '02:40:42', 'Meeting Rooms', 'Booked Meeting Room A for Gilbert', 'Hours: 1, Cost: $10', 1, '2026-01-26 10:40:42'),
(23, '2026-01-26', '02:44:00', 'Meeting Rooms', 'Deleted booking for Meeting Room A by Gilbert', NULL, 1, '2026-01-26 10:44:00'),
(24, '2026-01-26', '02:45:24', 'Meeting Rooms', 'Booked Meeting Room A for Gilbert', 'Hours: 0.083333333333333, Cost: $10', 1, '2026-01-26 10:45:24'),
(25, '2026-01-26', '03:22:41', 'Vouchers Import', 'Imported 10 vouchers for batch: Import 2026-01-26 12:22:33', 'Voucher Type ID: 1', 1, '2026-01-26 11:22:41'),
(26, '2026-01-26', '03:35:40', 'Vouchers Import', 'Imported 10 vouchers for batch: Import 2026-01-26 12:35:30', 'Voucher Type ID: 2', 1, '2026-01-26 11:35:40'),
(27, '2026-01-26', '03:35:56', 'Vouchers Import', 'Imported 3 vouchers for batch: Import 2026-01-26 12:35:46', 'Voucher Type ID: 3', 1, '2026-01-26 11:35:56'),
(28, '2026-01-26', '03:36:56', 'Vouchers Import', 'Imported 6 vouchers for batch: Import 2026-01-26 12:36:00', 'Voucher Type ID: 4', 1, '2026-01-26 11:36:56'),
(29, '2026-01-26', '03:42:22', 'Vouchers Import', 'Imported 29 vouchers for batch: Import 2026-01-26 12:42:04', 'Voucher Type ID: 5', 1, '2026-01-26 11:42:22'),
(30, '2026-01-26', '03:45:28', 'Linkspot Spaces', 'Recorded payment from Walter wale for january 2026', 'Amount: $65, Station ID: 51', 1, '2026-01-26 11:45:28'),
(31, '2026-01-26', '19:26:39', 'Vouchers Import', 'Imported 29 vouchers for batch: Import 2026-01-26 18:26:23', 'Voucher Type ID: 5', 1, '2026-01-26 17:26:39'),
(32, '2026-01-26', '19:28:31', 'Linkspot Members', 'Added new member: rdtfygh', 'Code: LS2601002, Package: monthly, Rate: $65', 1, '2026-01-26 17:28:31'),
(33, '2026-01-26', '19:33:43', 'Tasks', 'Added task: 77', NULL, 1, '2026-01-26 17:33:43'),
(34, '2026-01-26', '19:34:05', 'Tasks', 'Updated task \'77\' to complete', NULL, 1, '2026-01-26 17:34:05'),
(35, '2026-01-26', '19:39:10', 'Internet Vouchers', 'Sold vouchers for $4.00', '1x 4 Hours', 1, '2026-01-26 17:39:10'),
(36, '2026-01-27', '08:27:53', 'Linkspot Spaces', 'Released station ID 1', NULL, 1, '2026-01-27 06:27:53'),
(37, '2026-01-27', '09:41:03', 'Linkspot Spaces', 'Recorded payment from xx xx for jan 2312', 'Amount: $45, Station ID: 22', 1, '2026-01-27 07:41:03'),
(38, '2026-01-28', '10:04:46', 'Vouchers Import', 'Imported 15 vouchers for batch: Import 2026-01-28 09:04:26', 'Voucher Type ID: 1', 1, '2026-01-28 08:04:46'),
(39, '2026-01-28', '17:44:16', 'Internet Vouchers', 'Sold vouchers for $1.00', '1x 1 Hour', 1, '2026-01-28 15:44:16'),
(40, '2026-01-29', '08:27:38', 'Meeting Rooms', 'Booked Meeting Room A for Pastor', 'Hours: 0.16666666666667, Cost: $10', 1, '2026-01-29 06:27:38'),
(41, '2026-01-29', '08:34:36', 'Meeting Rooms', 'Deleted booking for Meeting Room A by Pastor', NULL, 1, '2026-01-29 06:34:36'),
(42, '2026-01-29', '08:34:40', 'Meeting Rooms', 'Booked Meeting Room A for Gilbert', 'Hours: 1, Cost: $10', 1, '2026-01-29 06:34:40'),
(43, '2026-01-29', '09:24:25', 'Meeting Rooms', 'Deleted booking for Meeting Room A by Gilbert', NULL, 1, '2026-01-29 07:24:25'),
(44, '2026-01-29', '09:24:28', 'Meeting Rooms', 'Booked Meeting Room A for Gilbert', 'Hours: 1, Cost: $10', 1, '2026-01-29 07:24:28'),
(45, '2026-01-29', '10:34:50', 'Meeting Rooms', 'Booked Meeting Room B for nk', 'Hours: 1, Cost: $10', 1, '2026-01-29 08:34:50'),
(46, '2026-01-29', '11:37:31', 'Meeting Rooms', 'Booked Meeting Room A for Gilbert', 'Hours: 1, Cost: $10', 1, '2026-01-29 09:37:31'),
(47, '2026-01-29', '11:38:13', 'Meeting Rooms', 'Deleted booking for Meeting Room A by Gilbert', NULL, 1, '2026-01-29 09:38:13'),
(48, '2026-01-29', '11:38:16', 'Meeting Rooms', 'Deleted booking for Meeting Room A by Gilbert', NULL, 1, '2026-01-29 09:38:16'),
(49, '2026-01-29', '11:38:20', 'Meeting Rooms', 'Deleted booking for Meeting Room B by nk', NULL, 1, '2026-01-29 09:38:20'),
(50, '2026-01-29', '23:16:21', 'Internet Vouchers', 'Sold vouchers for $5.00', '1x 1 Day', 1, '2026-01-29 21:16:21'),
(51, '2026-01-30', '09:19:28', 'Summarcity Shops', 'Added new shop: wq', 'Shop Name: qw', 1, '2026-01-30 07:19:28'),
(52, '2026-01-30', '09:20:07', 'Summarcity Tenants', 'Added new tenant: qqq', 'Business: lkjhnb, Shop: wq, Rent: $67, Member Code: SM2601002', 1, '2026-01-30 07:20:07'),
(53, '2026-01-31', '12:57:17', 'Internet Vouchers', 'Sold vouchers for $2.00', '1x Day Laptop', 2, '2026-01-31 10:57:17'),
(54, '2026-01-31', '13:01:31', 'Internet Vouchers', 'Sold vouchers for $1.00', '1x 1 Hour', 2, '2026-01-31 11:01:31'),
(55, '2026-01-31', '13:11:06', 'Internet Vouchers', 'Sold vouchers for $1.00', '1x 1 Hour', 2, '2026-01-31 11:11:06'),
(56, '2026-02-02', '08:29:22', 'Summarcity Tenants', 'Deleted tenant: qqq', 'Business: lkjhnb', 1, '2026-02-02 06:29:22'),
(57, '2026-02-02', '08:29:27', 'Summarcity Tenants', 'Deleted tenant: Gilbert Takudzwa Mashawi', 'Business: uz', 1, '2026-02-02 06:29:27'),
(58, '2026-02-02', '08:36:31', 'Summarcity Shops', 'Added new shop: 1', 'Shop Name: Mega Shop', 1, '2026-02-02 06:36:31'),
(59, '2026-02-02', '08:38:16', 'Summarcity Shops', 'Added new shop: 2', 'Shop Name: Nkosie', 1, '2026-02-02 06:38:16'),
(60, '2026-02-02', '08:38:38', 'Summarcity Shops', 'Added new shop: 3', 'Shop Name: Mrs Vee', 1, '2026-02-02 06:38:38'),
(61, '2026-02-02', '08:49:00', 'Summarcity Shops', 'Added new shop: 4', 'Shop Name: Privy', 1, '2026-02-02 06:49:00'),
(62, '2026-02-02', '08:49:17', 'Summarcity Shops', 'Added new shop: 5', 'Shop Name: Fredd', 1, '2026-02-02 06:49:17'),
(63, '2026-02-02', '08:49:39', 'Summarcity Shops', 'Added new shop: 6', 'Shop Name: Trysups', 1, '2026-02-02 06:49:39'),
(64, '2026-02-02', '08:51:25', 'Summarcity Shops', 'Added new shop: 7', 'Shop Name: Latac Inv Printing', 1, '2026-02-02 06:51:25'),
(65, '2026-02-02', '08:51:58', 'Summarcity Shops', 'Added new shop: 8', 'Shop Name: Mai Chuma', 1, '2026-02-02 06:51:58'),
(66, '2026-02-02', '08:54:53', 'Summarcity Shops', 'Added new shop: 9', 'Shop Name: Andy', 1, '2026-02-02 06:54:53'),
(67, '2026-02-02', '08:55:31', 'Summarcity Shops', 'Added new shop: 10', 'Shop Name: Nyarai', 1, '2026-02-02 06:55:31'),
(68, '2026-02-02', '08:56:10', 'Summarcity Shops', 'Added new shop: 11', 'Shop Name: Chidaushe', 1, '2026-02-02 06:56:10'),
(69, '2026-02-02', '08:56:37', 'Summarcity Shops', 'Added new shop: 12', 'Shop Name: ', 1, '2026-02-02 06:56:37'),
(70, '2026-02-02', '08:57:05', 'Summarcity Shops', 'Added new shop: 13A', 'Shop Name: Wendy', 1, '2026-02-02 06:57:05'),
(71, '2026-02-02', '08:57:35', 'Summarcity Shops', 'Added new shop: 13B', 'Shop Name: Prior', 1, '2026-02-02 06:57:35'),
(72, '2026-02-02', '09:41:52', 'Summarcity Shops', 'Added new shop: 14', 'Shop Name: Mr Mawere', 1, '2026-02-02 07:41:52'),
(73, '2026-02-02', '09:42:30', 'Summarcity Shops', 'Added new shop: 15', 'Shop Name: Mr Muchi', 1, '2026-02-02 07:42:30'),
(74, '2026-02-02', '09:43:25', 'Summarcity Shops', 'Added new shop: 16', 'Shop Name: Mrs Musambo', 1, '2026-02-02 07:43:25'),
(75, '2026-02-05', '11:56:08', 'Daily Voucher Cleanup', 'Daily voucher cleanup ran', NULL, NULL, '2026-02-05 09:56:08'),
(76, '2026-02-05', '11:58:06', 'Internet Vouchers', 'Sold vouchers for $5.00', '1x 1 Day', 1, '2026-02-05 09:58:06'),
(77, '2026-02-05', '14:19:34', 'Batch Cleanup', 'Deleted 2 old voucher batches and 44 vouchers', 'Automated cleanup of batches from before 2026-02-05', 1, '2026-02-05 12:19:34'),
(78, '2026-02-05', '14:19:55', 'Vouchers Import', 'Imported 10 vouchers for batch: Import 2026-02-05 13:19:45', 'Voucher Type ID: 1', 1, '2026-02-05 12:19:55'),
(79, '2026-02-05', '14:22:09', 'Internet Vouchers', 'Sold vouchers for $1.00', '1x 1 Hour', 1, '2026-02-05 12:22:09'),
(80, '2026-02-05', '14:27:03', 'Vouchers Import', 'Imported 10 vouchers for batch: Import 2026-02-05 13:26:50', 'Voucher Type ID: 2', 1, '2026-02-05 12:27:03'),
(81, '2026-02-05', '14:28:27', 'Linkspot Members', 'Added new member: xx', 'Code: LS2602001, Package: monthly, Rate: $95', 1, '2026-02-05 12:28:27'),
(82, '2026-02-05', '14:28:59', 'Internet Vouchers', 'Sold vouchers for $2.00', '1x 2 Hours', 1, '2026-02-05 12:28:59'),
(83, '2026-02-05', '14:30:05', 'Internet Vouchers', 'Sold vouchers for $3.00', '1x Day Laptop, 1x Laptop', 1, '2026-02-05 12:30:05'),
(84, '2026-02-05', '14:33:01', 'Internet Vouchers', 'Sold vouchers for $2.00', '1x 2 Hours', 1, '2026-02-05 12:33:01'),
(85, '2026-02-05', '14:34:38', 'Meeting Rooms', 'Booked Meeting Room A for ][poiuytlkjh', 'Hours: 1, Cost: $10', 1, '2026-02-05 12:34:38'),
(86, '2026-02-05', '14:46:57', 'Internet Vouchers', 'Sold vouchers for $1.00', '1x 1 Hour', 1, '2026-02-05 12:46:57'),
(87, '2026-02-09', '09:18:18', 'Batch Cleanup', 'Deleted 2 old voucher batches and 20 vouchers', 'Automated cleanup of batches from before 2026-02-09', 1, '2026-02-09 07:18:18'),
(88, '2026-02-09', '09:19:54', 'Linkspot Spaces', 'Released station ID 1', NULL, 1, '2026-02-09 07:19:54'),
(89, '2026-02-09', '09:20:02', 'Linkspot Spaces', 'Released station ID 2', NULL, 1, '2026-02-09 07:20:02'),
(90, '2026-02-09', '09:20:05', 'Linkspot Spaces', 'Released station ID 3', NULL, 1, '2026-02-09 07:20:05'),
(91, '2026-02-09', '09:20:08', 'Linkspot Spaces', 'Released station ID 4', NULL, 1, '2026-02-09 07:20:08'),
(92, '2026-02-09', '09:20:11', 'Linkspot Spaces', 'Released station ID 5', NULL, 1, '2026-02-09 07:20:11'),
(93, '2026-02-09', '09:20:14', 'Linkspot Spaces', 'Released station ID 6', NULL, 1, '2026-02-09 07:20:14'),
(94, '2026-02-09', '09:20:18', 'Linkspot Spaces', 'Released station ID 8', NULL, 1, '2026-02-09 07:20:18'),
(95, '2026-02-09', '09:20:20', 'Linkspot Spaces', 'Released station ID 10', NULL, 1, '2026-02-09 07:20:20'),
(96, '2026-02-09', '09:20:24', 'Linkspot Spaces', 'Released station ID 27', NULL, 1, '2026-02-09 07:20:24'),
(97, '2026-02-09', '09:20:28', 'Linkspot Spaces', 'Released station ID 30', NULL, 1, '2026-02-09 07:20:28'),
(98, '2026-02-09', '09:20:32', 'Linkspot Spaces', 'Released station ID 20', NULL, 1, '2026-02-09 07:20:32'),
(99, '2026-02-09', '09:20:35', 'Linkspot Spaces', 'Released station ID 22', NULL, 1, '2026-02-09 07:20:35'),
(100, '2026-02-09', '09:20:39', 'Linkspot Spaces', 'Released station ID 46', NULL, 1, '2026-02-09 07:20:39'),
(101, '2026-02-09', '09:20:42', 'Linkspot Spaces', 'Released station ID 49', NULL, 1, '2026-02-09 07:20:42'),
(102, '2026-02-09', '09:20:45', 'Linkspot Spaces', 'Released station ID 51', NULL, 1, '2026-02-09 07:20:45'),
(103, '2026-02-09', '09:20:49', 'Linkspot Spaces', 'Released station ID 67', NULL, 1, '2026-02-09 07:20:49'),
(104, '2026-02-09', '09:44:59', 'Vouchers Import', 'Imported 9 vouchers for batch: Import 2026-02-09 08:44:52', 'Voucher Type ID: 1', 1, '2026-02-09 07:44:59'),
(105, '2026-02-09', '09:45:12', 'Internet Vouchers', 'Sold vouchers for $1.00', '1x 1 Hour', 1, '2026-02-09 07:45:12');

-- --------------------------------------------------------

--
-- Table structure for table `cashup_submissions`
--

CREATE TABLE `cashup_submissions` (
  `id` int(11) NOT NULL,
  `submission_date` date NOT NULL,
  `submitted_by_user_id` int(11) DEFAULT NULL,
  `submitted_by_name` varchar(100) NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `daily_vouchers_revenue` decimal(12,2) DEFAULT 0.00,
  `daily_vouchers_cash_received` decimal(12,2) DEFAULT 0.00,
  `daily_vouchers_change_given` decimal(12,2) DEFAULT 0.00,
  `daily_vouchers_transactions` int(11) DEFAULT 0,
  `daily_linkspot_revenue` decimal(12,2) DEFAULT 0.00,
  `daily_linkspot_cash` decimal(12,2) DEFAULT 0.00,
  `daily_linkspot_transactions` int(11) DEFAULT 0,
  `daily_mall_revenue` decimal(12,2) DEFAULT 0.00,
  `daily_mall_cash` decimal(12,2) DEFAULT 0.00,
  `daily_mall_transactions` int(11) DEFAULT 0,
  `grand_total_revenue` decimal(12,2) DEFAULT 0.00,
  `grand_total_cash_in` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('new','seen','resolved') DEFAULT 'new'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cashup_submissions`
--

INSERT INTO `cashup_submissions` (`id`, `submission_date`, `submitted_by_user_id`, `submitted_by_name`, `submitted_at`, `daily_vouchers_revenue`, `daily_vouchers_cash_received`, `daily_vouchers_change_given`, `daily_vouchers_transactions`, `daily_linkspot_revenue`, `daily_linkspot_cash`, `daily_linkspot_transactions`, `daily_mall_revenue`, `daily_mall_cash`, `daily_mall_transactions`, `grand_total_revenue`, `grand_total_cash_in`, `notes`, `status`) VALUES
(1, '2026-02-05', 1, 'Gilbert', '2026-02-05 17:04:13', 14.00, 25.00, 11.00, 6, 0.00, 0.00, 0, 0.00, 0.00, 0, 14.00, 25.00, '[poj', 'new');

-- --------------------------------------------------------

--
-- Table structure for table `customer_changes`
--

CREATE TABLE `customer_changes` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','given') DEFAULT 'pending',
  `created_date` date NOT NULL DEFAULT curdate(),
  `created_time` time NOT NULL DEFAULT curtime(),
  `given_date` date DEFAULT NULL,
  `given_time` time DEFAULT NULL,
  `given_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_changes`
--

INSERT INTO `customer_changes` (`id`, `customer_name`, `amount`, `phone_number`, `notes`, `status`, `created_date`, `created_time`, `given_date`, `given_time`, `given_by`) VALUES
(1, 'Gibson', 35.00, '', 'for summarcity', 'given', '2026-01-22', '02:37:01', '2026-01-22', '07:47:07', 'gilbert');

-- --------------------------------------------------------

--
-- Table structure for table `linkspot_members`
--

CREATE TABLE `linkspot_members` (
  `id` int(11) NOT NULL,
  `member_code` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `package_type` enum('daily','weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
  `monthly_rate` decimal(10,2) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `next_due_date` date DEFAULT NULL,
  `station_address_id` int(11) DEFAULT NULL,
  `station_code` varchar(10) DEFAULT NULL,
  `desk_number` varchar(10) DEFAULT NULL,
  `status` enum('active','inactive','suspended','pending') DEFAULT 'active',
  `is_new` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `linkspot_members`
--

INSERT INTO `linkspot_members` (`id`, `member_code`, `full_name`, `email`, `phone`, `id_number`, `address`, `package_type`, `monthly_rate`, `balance`, `next_due_date`, `station_address_id`, `station_code`, `desk_number`, `status`, `is_new`, `notes`, `created_at`, `updated_at`) VALUES
(2, 'LS2601001', 'Gilbert Takudzwa Mashawi', 'gidzaboy3054@gmail.com', '0781012420', '', NULL, 'monthly', 65.00, 0.00, '0000-00-00', 1, 'A', '1', 'active', 1, '0', '2026-01-26 06:00:07', '2026-01-26 06:00:07'),
(3, 'LS2601002', 'rdtfygh', 'gidzaboy3054@gmail.com', '0781012420', '', NULL, 'monthly', 65.00, 0.00, '0000-00-00', 8, 'A', '8', 'active', 1, '0', '2026-01-26 17:28:31', '2026-01-26 17:28:31'),
(4, 'LS2602001', 'xx', 'xxx@gmail.com', '0987', '', NULL, 'monthly', 95.00, 0.00, '0000-00-00', 2, 'A', '2', 'active', 1, '0', '2026-02-05 12:28:27', '2026-02-05 12:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `linkspot_payments`
--

CREATE TABLE `linkspot_payments` (
  `id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `month_paid` varchar(50) NOT NULL,
  `payer_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `station_address_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `linkspot_payments`
--

INSERT INTO `linkspot_payments` (`id`, `payment_date`, `month_paid`, `payer_name`, `amount`, `payment_method`, `description`, `created_at`, `station_address_id`) VALUES
(2, '2026-01-21', 'January', 'tagwireyi', 20.00, 'Cash', 'weekly spaces', '2026-01-22 10:38:32', NULL),
(4, '2026-01-27', 'jan 2312', '0', 45.00, 'Cash', '0', '2026-01-27 07:41:03', 22);

-- --------------------------------------------------------

--
-- Table structure for table `linkspot_station_addresses`
--

CREATE TABLE `linkspot_station_addresses` (
  `id` int(11) NOT NULL,
  `station_code` varchar(10) NOT NULL,
  `desk_number` varchar(10) NOT NULL,
  `status` enum('available','occupied','reserved','maintenance') DEFAULT 'available',
  `current_user_id` int(11) DEFAULT NULL,
  `current_user_name` varchar(100) DEFAULT NULL,
  `occupation_start` datetime DEFAULT NULL,
  `occupation_end` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `linkspot_station_addresses`
--

INSERT INTO `linkspot_station_addresses` (`id`, `station_code`, `desk_number`, `status`, `current_user_id`, `current_user_name`, `occupation_start`, `occupation_end`, `created_at`, `updated_at`) VALUES
(1, 'A', '1', 'occupied', NULL, 'Walk-in Customer', '2026-02-09 09:45:12', NULL, '2026-01-24 10:26:48', '2026-02-09 07:45:12'),
(2, 'A', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:02'),
(3, 'A', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:05'),
(4, 'A', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:08'),
(5, 'A', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:11'),
(6, 'A', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:14'),
(7, 'A', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(8, 'A', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:18'),
(9, 'A', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(10, 'A', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:20'),
(11, 'B', '1', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(12, 'B', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(13, 'B', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(14, 'B', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(15, 'B', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(16, 'B', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(17, 'B', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(18, 'B', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(19, 'B', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(20, 'B', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:32'),
(21, 'C', '1', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(22, 'C', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:35'),
(23, 'C', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(24, 'C', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(25, 'C', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(26, 'C', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(27, 'C', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:24'),
(28, 'C', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(29, 'C', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(30, 'C', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:28'),
(31, 'D', '1', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(32, 'D', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(33, 'D', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(34, 'D', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(35, 'D', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(36, 'D', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(37, 'D', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(38, 'D', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(39, 'D', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(40, 'D', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(41, 'E', '1', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(42, 'E', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(43, 'E', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(44, 'E', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(45, 'E', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(46, 'E', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:39'),
(47, 'E', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(48, 'E', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(49, 'E', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:42'),
(50, 'E', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(51, 'F', '1', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:45'),
(52, 'F', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(53, 'F', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(54, 'F', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(55, 'F', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(56, 'F', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(57, 'F', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(58, 'F', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(59, 'F', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(60, 'F', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(61, 'G', '1', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(62, 'G', '2', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(63, 'G', '3', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(64, 'G', '4', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(65, 'G', '5', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(66, 'G', '6', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(67, 'G', '7', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-02-09 07:20:49'),
(68, 'G', '8', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(69, 'G', '9', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48'),
(70, 'G', '10', 'available', NULL, NULL, NULL, NULL, '2026-01-24 10:26:48', '2026-01-24 10:26:48');

-- --------------------------------------------------------

--
-- Table structure for table `mall_payments`
--

CREATE TABLE `mall_payments` (
  `id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `month_paid` varchar(50) NOT NULL,
  `payer_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `shop_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meeting_rooms`
--

CREATE TABLE `meeting_rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `booked_by` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_date` date NOT NULL,
  `end_time` time NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_rooms`
--

INSERT INTO `meeting_rooms` (`id`, `room_name`, `booked_by`, `start_date`, `start_time`, `end_date`, `end_time`, `hours`, `cost`, `created_at`) VALUES
(20, 'Meeting Room A', '][poiuytlkjh', '2026-02-05', '14:30:00', '2026-02-05', '15:30:00', 1.00, 10.00, '2026-02-05 12:34:38');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_type` varchar(50) DEFAULT 'all',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `notification_type`, `title`, `message`, `recipient_id`, `recipient_type`, `is_read`, `created_at`) VALUES
(9, 'new_member', 'New Member Added', 'New member Gilbert Takudzwa Mashawi (LS2601001) has been added to LinkSpot Spaces', NULL, 'all', 0, '2026-01-26 06:00:07'),
(10, 'new_tenant', 'New Tenant Added', 'New tenant Gilbert Takudzwa Mashawi (uz) has been added to Summarcity Mall. Shop: S1', NULL, 'all', 0, '2026-01-26 07:50:59'),
(11, 'room_booking', 'New Room Booking', 'Board Room 1 booked by ghasajs from 2026-01-26T11:24 to 2026-01-26T12:24', NULL, 'all', 0, '2026-01-26 10:25:00'),
(12, 'room_booking', 'New Room Booking', 'Meeting Room A booked by Gilbert from 2026-01-26T11:00 to 2026-01-26T12:00', NULL, 'all', 0, '2026-01-26 10:40:42'),
(13, 'room_booking', 'New Room Booking', 'Meeting Room A booked by Gilbert from 2026-01-26T12:44 to 2026-01-26T12:49', NULL, 'all', 0, '2026-01-26 10:45:24'),
(14, 'voucher_import', 'Vouchers Imported', '10 vouchers imported by Gilbert for batch: Import 2026-01-26 12:22:33', NULL, 'all', 0, '2026-01-26 11:22:41'),
(15, 'voucher_import', 'Vouchers Imported', '10 vouchers imported by Gilbert for batch: Import 2026-01-26 12:35:30', NULL, 'all', 0, '2026-01-26 11:35:40'),
(16, 'voucher_import', 'Vouchers Imported', '3 vouchers imported by Gilbert for batch: Import 2026-01-26 12:35:46', NULL, 'all', 0, '2026-01-26 11:35:56'),
(17, 'voucher_import', 'Vouchers Imported', '6 vouchers imported by Gilbert for batch: Import 2026-01-26 12:36:00', NULL, 'all', 0, '2026-01-26 11:36:56'),
(18, 'voucher_import', 'Vouchers Imported', '29 vouchers imported by Gilbert for batch: Import 2026-01-26 12:42:04', NULL, 'all', 0, '2026-01-26 11:42:22'),
(19, 'linkspot_payment', 'New Payment Recorded', 'Payment of $65 from Walter wale for january 2026', NULL, 'all', 0, '2026-01-26 11:45:28'),
(20, 'voucher_import', 'Vouchers Imported', '29 vouchers imported by Gilbert for batch: Import 2026-01-26 18:26:23', NULL, 'all', 0, '2026-01-26 17:26:39'),
(21, 'new_member', 'New Member Added', 'New member rdtfygh (LS2601002) has been added to LinkSpot Spaces', NULL, 'all', 0, '2026-01-26 17:28:31'),
(22, 'linkspot_payment', 'New Payment Recorded', 'Payment of $45 from xx xx for jan 2312', NULL, 'all', 0, '2026-01-27 07:41:03'),
(23, 'voucher_import', 'Vouchers Imported', '15 vouchers imported by Gilbert for batch: Import 2026-01-28 09:04:26', NULL, 'all', 0, '2026-01-28 08:04:46'),
(24, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #23 for $1.00 processed by Gilbert', NULL, 'all', 0, '2026-01-28 15:44:16'),
(25, 'room_booking', 'New Room Booking', 'Meeting Room A booked by Pastor from 2026-01-29T08:30 to 2026-01-29T08:40', NULL, 'all', 0, '2026-01-29 06:27:38'),
(26, 'room_booking', 'New Room Booking', 'Meeting Room A booked by Gilbert from 2026-01-29T07:00 to 2026-01-29T08:00', NULL, 'all', 0, '2026-01-29 06:34:40'),
(27, 'room_booking', 'New Room Booking', 'Meeting Room A booked by Gilbert from 2026-01-29 09:30:00 to 2026-01-29 10:30:00', NULL, 'all', 0, '2026-01-29 07:24:28'),
(28, 'room_booking', 'New Room Booking', 'Meeting Room B booked by nk from 2026-01-29 10:30:00 to 2026-01-29 11:30:00', NULL, 'all', 0, '2026-01-29 08:34:50'),
(29, 'room_booking', 'New Room Booking', 'Meeting Room A booked by Gilbert from 2026-01-29 11:30:00 to 2026-01-29 12:30:00', NULL, 'all', 0, '2026-01-29 09:37:31'),
(30, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #24 for $5.00 processed by Gilbert', NULL, 'all', 1, '2026-01-29 21:16:21'),
(31, 'new_tenant', 'New Tenant Added', 'New tenant qqq (lkjhnb) has been added to Summarcity Mall. Shop: wq', NULL, 'all', 0, '2026-01-30 07:20:07'),
(32, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #25 for $2.00 processed by Teddy', NULL, 'all', 0, '2026-01-31 10:57:17'),
(33, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #26 for $1.00 processed by Teddy', NULL, 'all', 0, '2026-01-31 11:01:31'),
(34, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #27 for $1.00 processed by Teddy', NULL, 'all', 0, '2026-01-31 11:11:06'),
(35, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #28 for $5.00 processed by Gilbert', NULL, 'all', 0, '2026-02-05 09:58:06'),
(36, 'voucher_import', 'Vouchers Imported', '10 vouchers imported by Gilbert for batch: Import 2026-02-05 13:19:45', NULL, 'all', 0, '2026-02-05 12:19:55'),
(37, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #29 for $1.00 processed by Gilbert', NULL, 'all', 0, '2026-02-05 12:22:09'),
(38, 'voucher_import', 'Vouchers Imported', '10 vouchers imported by Gilbert for batch: Import 2026-02-05 13:26:50', NULL, 'all', 0, '2026-02-05 12:27:03'),
(39, 'new_member', 'New Member Added', 'New member xx (LS2602001) has been added to LinkSpot Spaces', NULL, 'all', 0, '2026-02-05 12:28:27'),
(40, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #30 for $2.00 processed by Gilbert', NULL, 'all', 0, '2026-02-05 12:28:59'),
(41, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #33 for $3.00 processed by Gilbert', NULL, 'all', 0, '2026-02-05 12:30:05'),
(42, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #34 for $2.00 processed by Gilbert', NULL, 'all', 0, '2026-02-05 12:33:01'),
(43, 'room_booking', 'New Room Booking', 'Meeting Room A booked by ][poiuytlkjh from 2026-02-05 14:30:00 to 2026-02-05 15:30:00', NULL, 'all', 0, '2026-02-05 12:34:38'),
(44, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #36 for $1.00 processed by Gilbert', NULL, 'all', 0, '2026-02-05 12:46:57'),
(45, 'voucher_import', 'Vouchers Imported', '9 vouchers imported by Gilbert for batch: Import 2026-02-09 08:44:52', NULL, 'all', 0, '2026-02-09 07:44:59'),
(46, 'voucher_sale', 'New Voucher Sale', 'Voucher sale #37 for $1.00 processed by Gilbert', NULL, 'all', 0, '2026-02-09 07:45:12');

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` int(11) NOT NULL,
  `reminder_type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `reminder_time` datetime NOT NULL,
  `status` enum('pending','sent','dismissed') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summarcity_members`
--

CREATE TABLE `summarcity_members` (
  `id` int(11) NOT NULL,
  `member_code` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `shop_number` varchar(20) DEFAULT NULL,
  `rent_amount` decimal(10,2) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `next_due_date` date DEFAULT NULL,
  `status` enum('active','inactive','suspended','pending') DEFAULT 'active',
  `is_new` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summarcity_shops`
--

CREATE TABLE `summarcity_shops` (
  `id` int(11) NOT NULL,
  `shop_number` varchar(20) NOT NULL,
  `shop_name` varchar(100) DEFAULT NULL,
  `status` enum('available','occupied','reserved','maintenance') DEFAULT 'available',
  `current_tenant_id` int(11) DEFAULT NULL,
  `current_tenant_name` varchar(100) DEFAULT NULL,
  `rent_amount` decimal(10,2) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `summarcity_shops`
--

INSERT INTO `summarcity_shops` (`id`, `shop_number`, `shop_name`, `status`, `current_tenant_id`, `current_tenant_name`, `rent_amount`, `next_due_date`, `created_at`, `updated_at`) VALUES
(7, '1', 'Mega Shop', 'occupied', NULL, NULL, 1670.00, NULL, '2026-02-02 06:36:31', '2026-02-02 06:37:31'),
(8, '2', 'Nkosie', 'occupied', NULL, NULL, 1060.00, NULL, '2026-02-02 06:38:16', '2026-02-02 08:04:25'),
(9, '3', 'Mrs Vee', 'occupied', NULL, NULL, 630.00, NULL, '2026-02-02 06:38:38', '2026-02-02 08:04:25'),
(10, '4', 'Privy', 'occupied', NULL, NULL, 640.00, NULL, '2026-02-02 06:49:00', '2026-02-02 08:04:25'),
(11, '5', 'Fredd', 'occupied', NULL, NULL, 340.00, NULL, '2026-02-02 06:49:17', '2026-02-02 08:04:25'),
(12, '6', 'Trysups', 'occupied', NULL, NULL, 600.00, NULL, '2026-02-02 06:49:39', '2026-02-02 08:04:25'),
(13, '7', 'Latac Inv Printing', 'occupied', NULL, NULL, 540.00, NULL, '2026-02-02 06:51:25', '2026-02-02 08:04:25'),
(14, '8', 'Mai Chuma', 'occupied', NULL, NULL, 530.00, NULL, '2026-02-02 06:51:58', '2026-02-02 08:04:25'),
(15, '9', 'Andy', 'occupied', NULL, NULL, 510.00, NULL, '2026-02-02 06:54:53', '2026-02-02 08:04:25'),
(16, '10', 'Nyarai', 'occupied', NULL, NULL, 540.00, NULL, '2026-02-02 06:55:31', '2026-02-02 08:04:25'),
(17, '11', 'Chidaushe', 'occupied', NULL, NULL, 440.00, NULL, '2026-02-02 06:56:10', '2026-02-02 08:04:25'),
(18, '12', '', 'occupied', NULL, NULL, 0.00, NULL, '2026-02-02 06:56:37', '2026-02-02 08:04:25'),
(19, '13A', 'Wendy', 'occupied', NULL, NULL, 290.00, NULL, '2026-02-02 06:57:05', '2026-02-02 08:04:25'),
(20, '13B', 'Prior', 'occupied', NULL, NULL, 295.00, NULL, '2026-02-02 06:57:35', '2026-02-02 08:04:25'),
(21, '14', 'Mr Mawere', 'occupied', NULL, NULL, 14.00, NULL, '2026-02-02 07:41:52', '2026-02-02 08:04:25'),
(22, '15', 'Mr Muchi', 'occupied', NULL, NULL, 510.00, NULL, '2026-02-02 07:42:30', '2026-02-02 08:04:25'),
(23, '16', 'Mrs Musambo', 'occupied', NULL, NULL, 1050.00, NULL, '2026-02-02 07:43:25', '2026-02-02 08:04:25');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `task_date` date NOT NULL,
  `task_time` time NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','complete','halt') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_date`, `task_time`, `title`, `description`, `status`, `created_at`) VALUES
(1, '2026-01-22', '07:50:00', 'Project Requirements', 'Project Requirements,,  and alternatives', 'pending', '2026-01-22 15:47:48'),
(2, '2026-01-22', '07:50:00', 'install software', '', 'pending', '2026-01-22 15:48:03'),
(3, '2026-01-22', '07:50:00', 'Visualize schematics', '', 'pending', '2026-01-22 15:48:16'),
(4, '2026-01-22', '07:50:00', 'Check video', '', 'pending', '2026-01-22 15:48:29'),
(5, '2026-01-22', '07:50:00', 'Inventory adding and system', '', 'pending', '2026-01-22 15:48:46'),
(6, '2026-01-22', '07:50:00', 'Developed Linkspot vouchers system and Tasker', '', 'pending', '2026-01-22 15:49:18'),
(7, '2026-01-22', '07:50:00', 'Fetching categories,, ie Adding/editing…', '', 'pending', '2026-01-22 15:49:48'),
(8, '2026-01-22', '07:50:00', 'Suppliers api…  and  Cruds', '', 'pending', '2026-01-22 15:50:07'),
(9, '2026-01-22', '07:50:00', 'Fetching a supplier  ', '', 'pending', '2026-01-22 15:50:17'),
(10, '2026-01-22', '07:50:00', 'Add a supplier', '', 'pending', '2026-01-22 15:50:24'),
(11, '2026-01-22', '07:50:00', 'Push to remote', '', 'pending', '2026-01-22 15:50:32'),
(12, '2026-01-22', '07:50:00', 'Inventory type,, table', '', 'pending', '2026-01-22 15:50:41'),
(13, '2026-01-22', '07:50:00', 'Fetching units', '', 'pending', '2026-01-22 15:50:49'),
(14, '2026-01-22', '07:50:00', 'Adding /editing units ', '', 'pending', '2026-01-22 15:50:56'),
(15, '2026-01-26', '19:37:00', '77', 'wwer', 'complete', '2026-01-26 17:33:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'reception',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `created_at`) VALUES
(1, 'gilbert', 'reception', 'Gilbert', 'reception', '2026-01-22 08:58:14'),
(2, 'teddy', 'reception', 'Teddy', 'reception', '2026-01-22 08:58:14'),
(3, 'walter', 'reception', 'Walter', 'reception', '2026-01-22 08:58:14'),
(4, 'tafadzwa', 'reception', 'Tafadzwa', 'reception', '2026-01-22 08:58:14'),
(5, 'admin', 'admin123', 'Administrator', 'admin', '2026-01-22 08:58:14');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `voucher_code` varchar(11) NOT NULL,
  `voucher_type_id` int(11) NOT NULL,
  `status` enum('available','used','expired') DEFAULT 'available',
  `used_date` datetime DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `station_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `batch_id`, `voucher_code`, `voucher_type_id`, `status`, `used_date`, `sale_id`, `station_id`, `created_at`) VALUES
(145, 14, 'yZkhzjxcRNq', 1, 'used', '2026-02-09 09:45:12', 37, 1, '2026-02-09 07:44:59'),
(146, 14, 'hFiQHpKtnEK', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(147, 14, 'nuSYKaf8E4h', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(148, 14, '4NvWsZPELdq', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(149, 14, 'yjN36vsJVza', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(150, 14, 'PGSj8k4Hd5z', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(151, 14, 'as4AMzxf3RD', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(152, 14, 'GLs7pHKaYmd', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59'),
(153, 14, 'uSAMRmCK7in', 1, 'available', NULL, NULL, NULL, '2026-02-09 07:44:59');

-- --------------------------------------------------------

--
-- Table structure for table `voucher_batches`
--

CREATE TABLE `voucher_batches` (
  `id` int(11) NOT NULL,
  `batch_name` varchar(100) NOT NULL,
  `import_date` date NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `total_vouchers` int(11) NOT NULL,
  `voucher_type_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_batches`
--

INSERT INTO `voucher_batches` (`id`, `batch_name`, `import_date`, `file_name`, `total_vouchers`, `voucher_type_id`, `created_by`, `created_at`) VALUES
(14, 'Import 2026-02-09 08:44:52', '2026-02-09', 'vouchers_zone1_roll2.csv', 9, 1, 1, '2026-02-09 07:44:59');

-- --------------------------------------------------------

--
-- Table structure for table `voucher_sales`
--

CREATE TABLE `voucher_sales` (
  `id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `sale_time` time NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `station_address_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `amount_received` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `customer_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_sales`
--

INSERT INTO `voucher_sales` (`id`, `sale_date`, `sale_time`, `total_amount`, `created_at`, `station_address_id`, `created_by`, `amount_received`, `change_amount`, `customer_name`) VALUES
(3, '2026-01-22', '01:39:49', 1.00, '2026-01-22 09:39:49', NULL, NULL, NULL, 0.00, NULL),
(4, '2026-01-22', '01:39:57', 4.00, '2026-01-22 09:39:57', NULL, NULL, NULL, 0.00, NULL),
(5, '2026-01-22', '01:52:09', 1.00, '2026-01-22 09:52:09', NULL, NULL, NULL, 0.00, NULL),
(6, '2026-01-22', '01:52:15', 1.00, '2026-01-22 09:52:15', NULL, NULL, NULL, 0.00, NULL),
(7, '2026-01-22', '01:52:24', 5.00, '2026-01-22 09:52:24', NULL, NULL, NULL, 0.00, NULL),
(8, '2026-01-22', '01:52:39', 2.00, '2026-01-22 09:52:39', NULL, NULL, NULL, 0.00, NULL),
(9, '2026-01-22', '01:52:46', 1.00, '2026-01-22 09:52:46', NULL, NULL, NULL, 0.00, NULL),
(10, '2026-01-22', '02:36:33', 1.00, '2026-01-22 10:36:33', NULL, NULL, NULL, 0.00, NULL),
(11, '2026-01-22', '02:58:27', 1.00, '2026-01-22 10:58:27', NULL, NULL, NULL, 0.00, NULL),
(12, '2026-01-22', '02:58:30', 1.00, '2026-01-22 10:58:30', NULL, NULL, NULL, 0.00, NULL),
(13, '2026-01-22', '03:36:09', 1.00, '2026-01-22 11:36:09', NULL, NULL, NULL, 0.00, NULL),
(14, '2026-01-22', '03:36:15', 1.00, '2026-01-22 11:36:15', NULL, NULL, NULL, 0.00, NULL),
(15, '2026-01-22', '07:45:40', 2.00, '2026-01-22 15:45:40', NULL, NULL, NULL, 0.00, NULL),
(16, '2026-01-22', '07:45:44', 1.00, '2026-01-22 15:45:44', NULL, NULL, NULL, 0.00, NULL),
(17, '2026-01-22', '07:46:18', 1.00, '2026-01-22 15:46:18', NULL, NULL, NULL, 0.00, NULL),
(18, '2026-01-22', '07:46:27', 1.00, '2026-01-22 15:46:27', NULL, NULL, NULL, 0.00, NULL),
(19, '2026-01-22', '07:46:29', 1.00, '2026-01-22 15:46:29', NULL, NULL, NULL, 0.00, NULL),
(20, '2026-01-22', '07:46:31', 1.00, '2026-01-22 15:46:31', NULL, NULL, NULL, 0.00, NULL),
(21, '2026-01-22', '07:46:39', 1.00, '2026-01-22 15:46:39', NULL, NULL, NULL, 0.00, NULL),
(22, '2026-01-26', '19:39:10', 4.00, '2026-01-26 17:39:10', NULL, NULL, NULL, 0.00, NULL),
(23, '2026-01-28', '17:44:16', 1.00, '2026-01-28 15:44:16', 1, 1, 3.00, 2.00, ''),
(24, '2026-01-29', '23:16:21', 5.00, '2026-01-29 21:16:21', 4, 1, 8.00, 3.00, ''),
(25, '2026-01-31', '12:57:17', 2.00, '2026-01-31 10:57:17', 67, 2, 5.00, 3.00, ''),
(26, '2026-01-31', '13:01:31', 1.00, '2026-01-31 11:01:31', 46, 2, 9.00, 8.00, ''),
(27, '2026-01-31', '13:11:06', 1.00, '2026-01-31 11:11:06', 27, 2, 8.00, 7.00, ''),
(28, '2026-02-05', '11:58:06', 5.00, '2026-02-05 09:58:06', 3, 1, 6.00, 1.00, ''),
(29, '2026-02-05', '14:22:09', 1.00, '2026-02-05 12:22:09', 10, 1, 7.00, 6.00, ''),
(30, '2026-02-05', '14:28:59', 2.00, '2026-02-05 12:28:59', 6, 1, 2.00, 0.00, ''),
(33, '2026-02-05', '14:30:05', 3.00, '2026-02-05 12:30:05', 49, 1, 6.00, 3.00, ''),
(34, '2026-02-05', '14:33:01', 2.00, '2026-02-05 12:33:01', 20, 1, 2.00, 0.00, ''),
(36, '2026-02-05', '14:46:57', 1.00, '2026-02-05 12:46:57', 5, 1, 2.00, 1.00, ''),
(37, '2026-02-09', '09:45:12', 1.00, '2026-02-09 07:45:12', 1, 1, 6.00, 5.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `voucher_sale_items`
--

CREATE TABLE `voucher_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `voucher_type_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_sale_items`
--

INSERT INTO `voucher_sale_items` (`id`, `sale_id`, `voucher_type_id`, `quantity`, `unit_price`) VALUES
(3, 3, 1, 1, 1.00),
(4, 4, 4, 1, 4.00),
(5, 5, 1, 1, 1.00),
(6, 6, 1, 1, 1.00),
(7, 7, 5, 1, 5.00),
(8, 8, 2, 1, 2.00),
(9, 9, 1, 1, 1.00),
(10, 10, 1, 1, 1.00),
(11, 11, 1, 1, 1.00),
(12, 12, 1, 1, 1.00),
(13, 13, 1, 1, 1.00),
(14, 14, 1, 1, 1.00),
(15, 15, 2, 1, 2.00),
(16, 16, 1, 1, 1.00),
(17, 17, 6, 1, 1.00),
(18, 18, 1, 1, 1.00),
(19, 19, 1, 1, 1.00),
(20, 20, 1, 1, 1.00),
(21, 21, 1, 1, 1.00),
(22, 22, 4, 1, 4.00),
(23, 23, 1, 1, 1.00),
(24, 24, 5, 1, 5.00),
(25, 25, 7, 1, 2.00),
(26, 26, 1, 1, 1.00),
(27, 27, 1, 1, 1.00),
(28, 28, 5, 1, 5.00),
(29, 29, 1, 1, 1.00),
(30, 30, 2, 1, 2.00),
(31, 33, 7, 1, 2.00),
(32, 33, 6, 1, 1.00),
(33, 34, 2, 1, 2.00),
(34, 36, 1, 1, 1.00),
(35, 37, 1, 1, 1.00);

-- --------------------------------------------------------

--
-- Table structure for table `voucher_types`
--

CREATE TABLE `voucher_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_import_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher_types`
--

INSERT INTO `voucher_types` (`id`, `name`, `price`, `is_active`, `last_import_date`) VALUES
(1, '1 Hour', 1.00, 1, '2026-02-09 09:44:59'),
(2, '2 Hours', 2.00, 1, '2026-02-05 14:27:03'),
(3, '3 Hours', 3.00, 1, '2026-01-26 03:35:56'),
(4, '4 Hours', 4.00, 1, '2026-01-26 03:36:56'),
(5, '1 Day', 5.00, 1, '2026-01-26 19:26:39'),
(6, 'Laptop', 1.00, 1, NULL),
(7, 'Day Laptop', 2.00, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_date` (`activity_date`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cashup_submissions`
--
ALTER TABLE `cashup_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`submission_date`);

--
-- Indexes for table `customer_changes`
--
ALTER TABLE `customer_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_date` (`created_date`);

--
-- Indexes for table `linkspot_members`
--
ALTER TABLE `linkspot_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD KEY `status` (`status`),
  ADD KEY `station_address_id` (`station_address_id`);

--
-- Indexes for table `linkspot_payments`
--
ALTER TABLE `linkspot_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `station_address_id` (`station_address_id`);

--
-- Indexes for table `linkspot_station_addresses`
--
ALTER TABLE `linkspot_station_addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_station` (`station_code`,`desk_number`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `mall_payments`
--
ALTER TABLE `mall_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `meeting_rooms`
--
ALTER TABLE `meeting_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `start_date` (`start_date`),
  ADD KEY `booked_by` (`booked_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_type` (`recipient_type`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reminder_time` (`reminder_time`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `summarcity_members`
--
ALTER TABLE `summarcity_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD KEY `status` (`status`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `summarcity_shops`
--
ALTER TABLE `summarcity_shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_number` (`shop_number`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_date` (`task_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_code` (`voucher_code`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `voucher_type_id` (`voucher_type_id`),
  ADD KEY `status` (`status`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `station_id` (`station_id`);

--
-- Indexes for table `voucher_batches`
--
ALTER TABLE `voucher_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_type_id` (`voucher_type_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `voucher_sales`
--
ALTER TABLE `voucher_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_date` (`sale_date`),
  ADD KEY `station_address_id` (`station_address_id`);

--
-- Indexes for table `voucher_sale_items`
--
ALTER TABLE `voucher_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_type_id` (`voucher_type_id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `voucher_types`
--
ALTER TABLE `voucher_types`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `cashup_submissions`
--
ALTER TABLE `cashup_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_changes`
--
ALTER TABLE `customer_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `linkspot_members`
--
ALTER TABLE `linkspot_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `linkspot_payments`
--
ALTER TABLE `linkspot_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `linkspot_station_addresses`
--
ALTER TABLE `linkspot_station_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `mall_payments`
--
ALTER TABLE `mall_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meeting_rooms`
--
ALTER TABLE `meeting_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `summarcity_members`
--
ALTER TABLE `summarcity_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `summarcity_shops`
--
ALTER TABLE `summarcity_shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT for table `voucher_batches`
--
ALTER TABLE `voucher_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `voucher_sales`
--
ALTER TABLE `voucher_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `voucher_sale_items`
--
ALTER TABLE `voucher_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `voucher_types`
--
ALTER TABLE `voucher_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `linkspot_members`
--
ALTER TABLE `linkspot_members`
  ADD CONSTRAINT `linkspot_members_ibfk_1` FOREIGN KEY (`station_address_id`) REFERENCES `linkspot_station_addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `linkspot_payments`
--
ALTER TABLE `linkspot_payments`
  ADD CONSTRAINT `linkspot_payments_ibfk_1` FOREIGN KEY (`station_address_id`) REFERENCES `linkspot_station_addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mall_payments`
--
ALTER TABLE `mall_payments`
  ADD CONSTRAINT `mall_payments_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `summarcity_shops` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `summarcity_members`
--
ALTER TABLE `summarcity_members`
  ADD CONSTRAINT `summarcity_members_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `summarcity_shops` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `vouchers_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `voucher_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vouchers_ibfk_2` FOREIGN KEY (`voucher_type_id`) REFERENCES `voucher_types` (`id`),
  ADD CONSTRAINT `vouchers_ibfk_3` FOREIGN KEY (`sale_id`) REFERENCES `voucher_sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vouchers_ibfk_4` FOREIGN KEY (`station_id`) REFERENCES `linkspot_station_addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `voucher_batches`
--
ALTER TABLE `voucher_batches`
  ADD CONSTRAINT `voucher_batches_ibfk_1` FOREIGN KEY (`voucher_type_id`) REFERENCES `voucher_types` (`id`),
  ADD CONSTRAINT `voucher_batches_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `voucher_sales`
--
ALTER TABLE `voucher_sales`
  ADD CONSTRAINT `voucher_sales_ibfk_1` FOREIGN KEY (`station_address_id`) REFERENCES `linkspot_station_addresses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `voucher_sale_items`
--
ALTER TABLE `voucher_sale_items`
  ADD CONSTRAINT `voucher_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `voucher_sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `voucher_sale_items_ibfk_2` FOREIGN KEY (`voucher_type_id`) REFERENCES `voucher_types` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
