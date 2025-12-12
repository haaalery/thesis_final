-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 28, 2025 at 12:31 PM
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
-- Database: `thesis_final`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defense_schedules`
--

CREATE TABLE `defense_schedules` (
  `schedule_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `defense_date` date NOT NULL,
  `defense_time` time NOT NULL,
  `venue_id` int(11) NOT NULL,
  `status` enum('available','requested','approved','completed','cancelled') DEFAULT 'available',
  `duration_minutes` int(11) DEFAULT 60,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `panelist_id` int(11) NOT NULL,
  `comments` text NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `verdict` enum('passed','revisions_required','failed') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `evaluated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('leader','member') DEFAULT 'member',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`member_id`, `group_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 1, 3, 'leader', '2025-11-28 19:11:47'),
(2, 1, 4, 'member', '2025-11-28 19:11:47'),
(3, 1, 5, 'member', '2025-11-28 19:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('general','schedule','evaluation','assignment','document') DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 3, 'Welcome!', 'Your account has been created successfully. Please complete your profile and create your thesis group.', 'general', 0, '2025-11-28 19:11:47'),
(2, 6, 'New Panel Assignment', 'You have been assigned as a panelist for the upcoming thesis defense. Please review the details.', 'assignment', 0, '2025-11-28 19:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `panelist_details`
--

CREATE TABLE `panelist_details` (
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `panelist_details`
--

INSERT INTO `panelist_details` (`user_id`, `specialization`, `title`, `bio`) VALUES
(6, 'Software Engineering', 'PhD in Computer Science', NULL),
(7, 'Data Science', 'Professor of Information Systems', NULL),
(8, 'Artificial Intelligence', 'Associate Professor', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `panel_assignments`
--

CREATE TABLE `panel_assignments` (
  `assignment_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `panelist_id` int(11) NOT NULL,
  `role` enum('chair','member','adviser') NOT NULL,
  `status` enum('pending','accepted','declined','completed') DEFAULT 'pending',
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_requests`
--

CREATE TABLE `schedule_requests` (
  `request_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `requested_schedule_id` int(11) NOT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_details`
--

CREATE TABLE `student_details` (
  `user_id` int(11) NOT NULL,
  `course` varchar(100) NOT NULL,
  `year` varchar(50) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_details`
--

INSERT INTO `student_details` (`user_id`, `course`, `year`, `student_id`) VALUES
(3, 'Computer Science', '4th Year', '2021-00001'),
(4, 'Computer Science', '4th Year', '2021-00002'),
(5, 'Information Technology', '4th Year', '2021-00003');

-- --------------------------------------------------------

--
-- Table structure for table `thesis_documents`
--

CREATE TABLE `thesis_documents` (
  `document_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `thesis_groups`
--

CREATE TABLE `thesis_groups` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `thesis_title` varchar(500) DEFAULT NULL,
  `abstract` text DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `status` enum('pending','pending_approval','approved','rejected','completed') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `thesis_groups`
--

INSERT INTO `thesis_groups` (`group_id`, `group_name`, `thesis_title`, `abstract`, `course`, `specialization`, `status`, `created_by`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 'Team Alpha', 'Machine Learning Approach to Student Performance Prediction', 'This study explores the use of machine learning algorithms to predict student academic performance based on various factors including attendance, assignment scores, and demographic data.', 'Computer Science', 'Data Science', 'pending_approval', 3, NULL, NULL, NULL, '2025-11-28 19:11:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','student','panelist') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@college.edu', '$2y$10$YourHashedPasswordHere', 'admin', 'active', '2025-11-28 19:11:46', NULL),
(2, 'John Admin', 'john.admin@college.edu', '$2y$10$YourHashedPasswordHere', 'admin', 'active', '2025-11-28 19:11:46', NULL),
(3, 'Alice Johnson', 'alice.j@student.edu', '$2y$10$YourHashedPasswordHere', 'student', 'active', '2025-11-28 19:11:46', NULL),
(4, 'Bob Smith', 'bob.s@student.edu', '$2y$10$YourHashedPasswordHere', 'student', 'active', '2025-11-28 19:11:46', NULL),
(5, 'Charlie Brown', 'charlie.b@student.edu', '$2y$10$YourHashedPasswordHere', 'student', 'active', '2025-11-28 19:11:46', NULL),
(6, 'Dr. Emily Davis', 'emily.davis@college.edu', '$2y$10$YourHashedPasswordHere', 'panelist', 'active', '2025-11-28 19:11:46', NULL),
(7, 'Prof. Michael Chen', 'michael.chen@college.edu', '$2y$10$YourHashedPasswordHere', 'panelist', 'active', '2025-11-28 19:11:46', NULL),
(8, 'Dr. Sarah Wilson', 'sarah.wilson@college.edu', '$2y$10$YourHashedPasswordHere', 'panelist', 'active', '2025-11-28 19:11:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `venue_id` int(11) NOT NULL,
  `venue_name` varchar(255) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 20,
  `facilities` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`venue_id`, `venue_name`, `building`, `room_number`, `capacity`, `facilities`, `status`, `created_at`) VALUES
(1, 'Computer Lab 1', 'Engineering Building', 'E-301', 30, 'Projector, Whiteboard, AC', 'active', '2025-11-28 19:11:46'),
(2, 'Conference Room A', 'Admin Building', 'A-205', 20, 'Video Conferencing, Projector', 'active', '2025-11-28 19:11:46'),
(3, 'Lecture Hall 2', 'Main Building', 'M-102', 50, 'Sound System, Projector, AC', 'active', '2025-11-28 19:11:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `defense_schedules`
--
ALTER TABLE `defense_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `unique_schedule` (`defense_date`,`defense_time`,`venue_id`),
  ADD KEY `idx_group_id` (`group_id`),
  ADD KEY `idx_defense_date` (`defense_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_venue_id` (`venue_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD UNIQUE KEY `unique_evaluation` (`schedule_id`,`group_id`,`panelist_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_group_id` (`group_id`),
  ADD KEY `idx_panelist_id` (`panelist_id`),
  ADD KEY `idx_verdict` (`verdict`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `unique_member` (`group_id`,`user_id`),
  ADD KEY `idx_group_id` (`group_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `panelist_details`
--
ALTER TABLE `panelist_details`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_specialization` (`specialization`);

--
-- Indexes for table `panel_assignments`
--
ALTER TABLE `panel_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_assignment` (`schedule_id`,`panelist_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_panelist_id` (`panelist_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `schedule_requests`
--
ALTER TABLE `schedule_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `requested_schedule_id` (`requested_schedule_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_group_id` (`group_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `student_details`
--
ALTER TABLE `student_details`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_course` (`course`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `thesis_documents`
--
ALTER TABLE `thesis_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_group_id` (`group_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `thesis_groups`
--
ALTER TABLE `thesis_groups`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_specialization` (`specialization`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`venue_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `defense_schedules`
--
ALTER TABLE `defense_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `panel_assignments`
--
ALTER TABLE `panel_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_requests`
--
ALTER TABLE `schedule_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thesis_documents`
--
ALTER TABLE `thesis_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thesis_groups`
--
ALTER TABLE `thesis_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `defense_schedules`
--
ALTER TABLE `defense_schedules`
  ADD CONSTRAINT `defense_schedules_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `thesis_groups` (`group_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `defense_schedules_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `defense_schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `thesis_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`panelist_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `thesis_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `panelist_details`
--
ALTER TABLE `panelist_details`
  ADD CONSTRAINT `panelist_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `panel_assignments`
--
ALTER TABLE `panel_assignments`
  ADD CONSTRAINT `panel_assignments_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `defense_schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `panel_assignments_ibfk_2` FOREIGN KEY (`panelist_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_requests`
--
ALTER TABLE `schedule_requests`
  ADD CONSTRAINT `schedule_requests_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `thesis_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_requests_ibfk_2` FOREIGN KEY (`requested_schedule_id`) REFERENCES `defense_schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_details`
--
ALTER TABLE `student_details`
  ADD CONSTRAINT `student_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `thesis_documents`
--
ALTER TABLE `thesis_documents`
  ADD CONSTRAINT `thesis_documents_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `thesis_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `thesis_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `thesis_groups`
--
ALTER TABLE `thesis_groups`
  ADD CONSTRAINT `thesis_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `thesis_groups_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
