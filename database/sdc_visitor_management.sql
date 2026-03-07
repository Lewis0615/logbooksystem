-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 01, 2026 at 01:02 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sdc_visitor_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE `blacklist` (
  `id` int(11) NOT NULL,
  `visitor_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `reason` text NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_permanent` tinyint(1) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blacklist`
--

INSERT INTO `blacklist` (`id`, `visitor_id`, `phone`, `first_name`, `last_name`, `email`, `id_number`, `severity`, `reason`, `status`, `is_permanent`, `expiry_date`, `created_by`, `reported_by`, `approved_by`, `notes`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 3, '09235135456', 'John', 'Lloyd Delmo', '', '', 'medium', 'blocklist test', 'active', 0, '2026-03-07', 1, 1, 1, NULL, NULL, '2026-03-01 11:13:21', '2026-03-01 11:13:21');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_role` enum('admin','guard','supervisor') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_role`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 'admin', 'Hey admin', 1, '2026-03-01 09:33:24'),
(2, 3, 'admin', 'admin', 1, '2026-03-01 09:37:55'),
(4, 1, 'guard', 'what is the update?', 1, '2026-03-01 09:52:22'),
(5, 3, 'admin', 'how\'s your day?', 1, '2026-03-01 09:55:14');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_time`) VALUES
(1, 'admin123', '::1', '2026-03-01 12:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'default_duration', '480', '2026-03-01 11:01:46'),
(2, 'require_photo', '0', '2026-03-01 11:01:46'),
(3, 'require_id_upload', '0', '2026-03-01 11:01:46'),
(4, 'enforce_dress_code', '1', '2026-03-01 10:22:02'),
(5, 'photo_verification', '0', '2026-03-01 10:22:02'),
(6, 'require_proper_attire', '1', '2026-03-01 10:22:02'),
(7, 'require_closed_shoes', '1', '2026-03-01 10:22:02'),
(8, 'require_id_visible', '0', '2026-03-01 10:22:02'),
(9, 'violation_action', 'deny', '2026-03-01 10:22:02'),
(10, 'notification_method', 'system', '2026-03-01 10:22:02'),
(25, 'visit_purposes_list', '[\"Meeting\",\"Interview\",\"Student Inquiry\",\"Parent Conference\",\"Delivery\",\"Official Business\",\"Guest Speaker\",\"Event Attendance\",\"Other\",\"Maintenance\"]', '2026-03-01 10:24:41'),
(26, 'offices_list', '[\"Principal\'s Office\",\"Registrar\'s Office\",\"Accounting Office\",\"Student Affairs Office\",\"Library\",\"Computer Laboratory\",\"Science Laboratory\",\"Faculty Room\",\"Guidance Office\",\"Maintenance Office\",\"Canteen\",\"Other\"]', '2026-03-01 10:20:20'),
(39, 'dress_code_items', '[{\"title\":\"Proper Attire\",\"status\":\"allowed\",\"image\":\"proper-attire.svg\"},{\"title\":\"Closed Footwear\",\"status\":\"allowed\",\"image\":\"closed-footwear.svg\"},{\"title\":\"Sleeveless \\/ Tank Tops\",\"status\":\"not_allowed\",\"image\":\"sleeveless.svg\"},{\"title\":\"Short Skirts\\/Shorts\",\"status\":\"not_allowed\",\"image\":\"short-skirts.svg\"},{\"title\":\"Slippers \\/ Flip-flops\",\"status\":\"not_allowed\",\"image\":\"slippers.svg\"},{\"title\":\"Offensive Clothing\",\"status\":\"not_allowed\",\"image\":\"offensive-clothing.svg\"}]', '2026-03-01 10:51:44'),
(51, 'max_group_size', '10', '2026-03-01 11:01:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guard','supervisor') NOT NULL DEFAULT 'guard',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `first_name`, `last_name`, `email`, `phone`, `is_active`, `created_at`, `updated_at`, `last_login`, `created_by`) VALUES
(1, 'admin', '$2y$10$Eg7xfUFapxeg2/NzMQOcTOwx02fVSy5AKGTWhw9.P1N6OasAi/8DS', 'admin', 'System', 'Administrator', 'admin@sdc.edu', NULL, 1, '2026-02-23 08:04:12', '2026-03-01 12:00:00', '2026-03-01 12:00:00', NULL),
(2, 'superadmin', '$2y$10$3TRI32YE/BIgVoUh4zYHEO.hrWaHvLdmG8IBdQen82jB6/M3TYUZe', 'admin', 'Main', 'Administrator', 'admin@sdscollege.edu.ph', '+63912345678', 1, '2026-02-23 08:13:27', '2026-03-01 11:39:57', '2026-02-23 08:16:11', 1),
(3, 'guard', '$2y$10$pXIh0ElKQZgqmBJMxVOBK.zxjuSuGXDs8cPORJv0kOxmQKPoVoUYW', 'guard', 'Security', 'Guard', 'guard@sdsc.edu.ph', NULL, 1, '2026-02-24 04:45:34', '2026-03-01 11:59:32', '2026-03-01 11:59:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visitors`
--

INSERT INTO `visitors` (`id`, `first_name`, `last_name`, `phone`, `email`, `address`, `id_type`, `id_photo_path`, `created_at`, `updated_at`) VALUES
(1, 'John', 'Lewis Oliquiano', '09771304647', NULL, 'Caloocan City', NULL, 'national_id', NULL, '2026-03-01 03:12:18', '2026-03-01 03:14:45'),
(2, 'Shaira', 'Guadalupe', '09234134354', NULL, 'Navotas', NULL, 'national_id', NULL, '2026-03-01 04:34:15', '2026-03-01 04:34:15'),
(3, 'John', 'Lloyd Delmo', '09235135456', NULL, 'Bagong Silang', NULL, 'national_id', 'assets/uploads/visitor-ids/id_1772363166_a4468636f45b.jpg', '2026-03-01 11:06:06', '2026-03-01 11:06:06'),
(4, 'Kenjiro', 'Takada', '09434445244', 'kenjirotakada123@gmail.com', 'Quezon City', NULL, 'national_id', 'assets/uploads/visitor-ids/id_1772364631_0dc0709d38b2.jpg', '2026-03-01 11:30:31', '2026-03-01 11:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `person_to_visit` varchar(200) DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL,
  `purpose` varchar(200) DEFAULT NULL,
  `visit_pass` varchar(20) NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_checkout_time` timestamp NULL DEFAULT NULL,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `expected_duration` int(11) DEFAULT 60 COMMENT 'Expected duration in minutes',
  `actual_duration` int(11) DEFAULT NULL COMMENT 'Actual duration in minutes',
  `status` enum('checked_in','checked_out','no_show') NOT NULL DEFAULT 'checked_in',
  `is_group_visit` tinyint(1) DEFAULT 0,
  `group_size` int(11) DEFAULT NULL,
  `group_members` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `checked_in_by` int(11) NOT NULL,
  `checked_out_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `visitor_id`, `person_to_visit`, `department`, `purpose`, `visit_pass`, `check_in_time`, `expected_checkout_time`, `check_out_time`, `expected_duration`, `actual_duration`, `status`, `is_group_visit`, `group_size`, `group_members`, `additional_notes`, `checked_in_by`, `checked_out_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'test', 'registrar', 'academic', 'VP-334885-446', '2026-03-01 03:14:45', '2026-03-01 11:14:45', '2026-03-01 03:35:39', 480, 21, 'checked_out', 0, 1, NULL, 'test', 1, 3, NULL, '2026-03-01 03:14:45', '2026-03-01 03:35:39'),
(2, 1, NULL, NULL, NULL, 'VP-336154-691', '2026-03-01 03:35:54', '2026-03-01 11:35:54', '2026-03-01 10:00:56', 480, 385, 'checked_out', 0, NULL, NULL, NULL, 3, 3, NULL, '2026-03-01 03:35:54', '2026-03-01 10:00:56'),
(3, 2, 'sample', 'test', 'test', '3', '2026-03-01 04:34:15', '2026-03-01 12:34:15', '2026-03-01 11:33:55', 480, 420, 'checked_out', 0, 1, NULL, 'test', 1, 3, NULL, '2026-03-01 04:34:15', '2026-03-01 11:33:55'),
(4, 3, 'Sir Rean', 'Library', 'Student Inquiry', '4', '2026-03-01 11:06:06', '2026-03-01 19:06:06', '2026-03-01 11:23:17', 480, 17, 'checked_out', 0, 1, NULL, 'Sample note', 1, 3, NULL, '2026-03-01 11:06:06', '2026-03-01 11:23:17'),
(5, 4, 'Sir Samson', 'Computer Laboratory', 'Student Inquiry', '5', '2026-03-01 11:30:31', '2026-03-01 19:30:31', NULL, 480, NULL, 'checked_in', 0, 1, NULL, 'Banok test', 1, NULL, NULL, '2026-03-01 11:30:31', '2026-03-01 11:30:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visitor_id` (`visitor_id`),
  ADD KEY `phone` (`phone`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `expiry_date` (`expiry_date`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_role` (`receiver_role`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `attempt_time` (`attempt_time`),
  ADD KEY `ip_address` (`ip_address`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `email` (`email`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_pass` (`visit_pass`),
  ADD KEY `visitor_id` (`visitor_id`),
  ADD KEY `status` (`status`),
  ADD KEY `check_in_time` (`check_in_time`),
  ADD KEY `check_out_time` (`check_out_time`),
  ADD KEY `checked_in_by` (`checked_in_by`),
  ADD KEY `checked_out_by` (`checked_out_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
