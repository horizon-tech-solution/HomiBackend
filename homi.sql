-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 23, 2026 at 10:37 PM
-- Server version: 8.0.27
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `homi`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `actor` varchar(100) NOT NULL,
  `target` varchar(255) NOT NULL,
  `detail` text,
  `category` enum('listing','agent','user','report','settings') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','superadmin') DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `name`, `email`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$kiSXfGksWNlJX.5Q6DfrOe7SGaPLvcVpZeEYrsp9I.qoYBzcwA0Zq', 'Admin', 'admin@propty.cm', 'superadmin', '2026-02-21 13:17:07', '2026-02-21 15:17:18');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `listing_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('image','pdf') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','verified','flagged') DEFAULT 'pending',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `user_id` int NOT NULL,
  `listing_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int NOT NULL,
  `listing_id` int NOT NULL,
  `from_user_id` int NOT NULL,
  `to_user_id` int NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE `listings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('pending','approved','rejected','flagged') DEFAULT 'pending',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `property_type` enum('apartment','house','villa','commercial','land','duplex','other') NOT NULL,
  `transaction_type` enum('rent','sale') NOT NULL,
  `price` int UNSIGNED NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `region` varchar(100) NOT NULL,
  `coordinates` varchar(50) DEFAULT NULL,
  `bedrooms` tinyint UNSIGNED DEFAULT NULL,
  `bathrooms` tinyint UNSIGNED DEFAULT NULL,
  `area` smallint UNSIGNED NOT NULL,
  `floor` tinyint UNSIGNED DEFAULT NULL,
  `total_floors` tinyint UNSIGNED DEFAULT NULL,
  `year_built` year DEFAULT NULL,
  `furnished` enum('unfurnished','semi-furnished','furnished') DEFAULT NULL,
  `parking` tinyint(1) DEFAULT '0',
  `generator` tinyint(1) DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_reason` text,
  `admin_notes` text,
  `fraud_signals` json DEFAULT NULL,
  `requested_changes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `listing_photos`
--

CREATE TABLE `listing_photos` (
  `id` int NOT NULL,
  `listing_id` int NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `is_cover` tinyint(1) DEFAULT '0',
  `sort_order` tinyint UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int NOT NULL,
  `type` enum('fraud','fake_listing','spam','harassment','misleading','inappropriate') NOT NULL,
  `priority` enum('high','medium','low') DEFAULT 'medium',
  `status` enum('open','under_review','resolved','dismissed') DEFAULT 'open',
  `subject_type` enum('listing','user') NOT NULL,
  `subject_id` int NOT NULL,
  `reported_by_user_id` int NOT NULL,
  `description` text NOT NULL,
  `evidence` json DEFAULT NULL,
  `messages` json DEFAULT NULL,
  `linked_listing_id` int DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution` text,
  `admin_notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `agent_id` int NOT NULL,
  `reviewer_id` int NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `type` enum('boolean','number','string','json') DEFAULT 'string',
  `description` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `type`, `description`, `updated_at`) VALUES
(1, 'require_approval', '1', 'boolean', 'Require manual approval for new listings', '2026-02-21 13:17:08'),
(2, 'min_photos', '4', 'number', 'Minimum photos per listing', '2026-02-21 13:17:08'),
(3, 'max_photos', '25', 'number', 'Maximum photos per listing', '2026-02-21 13:17:08'),
(4, 'listing_expiry_days', '90', 'number', 'Days until listing expires', '2026-02-21 13:17:08'),
(5, 'max_listings_user', '3', 'number', 'Max active listings for regular users', '2026-02-21 13:17:08'),
(6, 'max_listings_agent', '50', 'number', 'Max active listings for verified agents', '2026-02-21 13:17:08'),
(7, 'allow_commercial', '1', 'boolean', 'Allow commercial property listings', '2026-02-21 13:17:08'),
(8, 'allow_land', '1', 'boolean', 'Allow land listings', '2026-02-21 13:17:08'),
(9, 'allow_user_listings', '1', 'boolean', 'Allow regular users to list properties', '2026-02-21 13:17:08'),
(10, 'require_verification', '1', 'boolean', 'Require agent verification before publishing', '2026-02-21 13:17:08'),
(11, 'require_license', '1', 'boolean', 'Require professional license for agents', '2026-02-21 13:17:08'),
(12, 'require_agency_proof', '0', 'boolean', 'Require agency registration proof', '2026-02-21 13:17:08'),
(13, 'trial_listings', '5', 'number', 'Trial listings allowed before verification', '2026-02-21 13:17:08'),
(14, 'require_email_verification', '1', 'boolean', 'Require email verification on registration', '2026-02-21 13:17:08'),
(15, 'require_phone_verification', '0', 'boolean', 'Require phone verification', '2026-02-21 13:17:08'),
(16, 'auto_block_threshold', '5', 'number', 'Auto-block after this many reports', '2026-02-21 13:17:08'),
(17, 'allow_new_registrations', '1', 'boolean', 'Allow new user registrations', '2026-02-21 13:17:08'),
(18, 'notify_new_listing', '1', 'boolean', 'Send email on new listing submission', '2026-02-21 13:17:08'),
(19, 'notify_new_agent', '1', 'boolean', 'Send email on new agent application', '2026-02-21 13:17:08'),
(20, 'notify_new_report', '1', 'boolean', 'Send email on new report', '2026-02-21 13:17:08'),
(21, 'notify_fraud_alert', '1', 'boolean', 'Send email on fraud alert', '2026-02-21 13:17:08'),
(22, 'admin_email', 'admin@propty.cm', 'string', 'Admin notification email', '2026-02-21 13:17:08'),
(23, 'platform_name', 'Propty Cameroon', 'string', 'Platform display name', '2026-02-21 13:17:08'),
(24, 'support_email', 'support@propty.cm', 'string', 'Customer support email', '2026-02-21 13:17:08'),
(25, 'currency', 'XAF', 'string', 'Default currency', '2026-02-21 13:17:08'),
(26, 'maintenance_mode', '0', 'boolean', 'Maintenance mode flag', '2026-02-21 13:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('user','agent') NOT NULL DEFAULT 'user',
  `status` enum('active','blocked') DEFAULT 'active',
  `verified` tinyint(1) DEFAULT '0',
  `agency_name` varchar(100) DEFAULT NULL,
  `agency_type` enum('agency','individual') DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `years_experience` tinyint UNSIGNED DEFAULT NULL,
  `bio` text,
  `verification_status` enum('pending','verified','rejected','suspended') DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejected_reason` text,
  `suspended_reason` text,
  `listings_count` int DEFAULT '0',
  `reports_count` int DEFAULT '0',
  `favorites_count` int DEFAULT '0',
  `inquiries_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `blocked_at` timestamp NULL DEFAULT NULL,
  `block_reason` text,
  `last_active` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_listing` (`listing_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`user_id`,`listing_id`),
  ADD KEY `idx_listing` (`listing_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_listing` (`listing_id`),
  ADD KEY `idx_from` (`from_user_id`),
  ADD KEY `idx_to` (`to_user_id`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `listing_photos`
--
ALTER TABLE `listing_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_listing` (`listing_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_subject` (`subject_type`,`subject_id`),
  ADD KEY `idx_reporter` (`reported_by_user_id`),
  ADD KEY `fk_report_linked_listing` (`linked_listing_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`agent_id`,`reviewer_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `fk_rev_reviewer` (`reviewer_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_verification` (`verification_status`);

-- --------------------------------------------------------

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `inquiries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `listings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `listing_photos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Constraints for dumped tables
--

ALTER TABLE `documents`
  ADD CONSTRAINT `fk_doc_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_fav_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `inquiries`
  ADD CONSTRAINT `fk_inq_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inq_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inq_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `listings`
  ADD CONSTRAINT `fk_listing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `listing_photos`
  ADD CONSTRAINT `fk_photo_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

ALTER TABLE `reports`
  ADD CONSTRAINT `fk_report_linked_listing` FOREIGN KEY (`linked_listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_rev_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rev_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;