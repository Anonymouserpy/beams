-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 12:26 PM
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
-- Database: `beams`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_event_fines` ()   BEGIN
    
    INSERT INTO student_fines (student_id, event_id, fine_reason, amount, status, recorded_at)
    SELECT s.student_id, e.event_id, 'Missing AM login', ef.miss_am_login, 'unpaid', NOW()
    FROM events e
    INNER JOIN attendance_schedule sch ON e.event_id = sch.event_id
    INNER JOIN event_fines ef ON e.event_id = ef.event_id
    CROSS JOIN students s
    LEFT JOIN attendance a ON e.event_id = a.event_id AND s.student_id = a.student_id
    LEFT JOIN student_fines sf ON sf.student_id = s.student_id 
                              AND sf.event_id = e.event_id 
                              AND sf.fine_reason = 'Missing AM login'
    WHERE e.event_type IN ('whole_day', 'half_dayam')
      AND TIMESTAMP(e.event_date, sch.am_login_end) < NOW()
      AND (a.attendance_id IS NULL OR a.am_login_time IS NULL)
      AND ef.miss_am_login > 0
      AND sf.fine_id IS NULL;

    
    INSERT INTO student_fines (student_id, event_id, fine_reason, amount, status, recorded_at)
    SELECT s.student_id, e.event_id, 'Missing AM logout', ef.miss_am_logout, 'unpaid', NOW()
    FROM events e
    INNER JOIN attendance_schedule sch ON e.event_id = sch.event_id
    INNER JOIN event_fines ef ON e.event_id = ef.event_id
    CROSS JOIN students s
    LEFT JOIN attendance a ON e.event_id = a.event_id AND s.student_id = a.student_id
    LEFT JOIN student_fines sf ON sf.student_id = s.student_id 
                              AND sf.event_id = e.event_id 
                              AND sf.fine_reason = 'Missing AM logout'
    WHERE e.event_type IN ('whole_day', 'half_dayam')
      AND TIMESTAMP(e.event_date, sch.am_logout_end) < NOW()
      AND (a.attendance_id IS NULL OR a.am_logout_time IS NULL)
      AND ef.miss_am_logout > 0
      AND sf.fine_id IS NULL;

    
    INSERT INTO student_fines (student_id, event_id, fine_reason, amount, status, recorded_at)
    SELECT s.student_id, e.event_id, 'Missing PM login', ef.miss_pm_login, 'unpaid', NOW()
    FROM events e
    INNER JOIN attendance_schedule sch ON e.event_id = sch.event_id
    INNER JOIN event_fines ef ON e.event_id = ef.event_id
    CROSS JOIN students s
    LEFT JOIN attendance a ON e.event_id = a.event_id AND s.student_id = a.student_id
    LEFT JOIN student_fines sf ON sf.student_id = s.student_id 
                              AND sf.event_id = e.event_id 
                              AND sf.fine_reason = 'Missing PM login'
    WHERE e.event_type IN ('whole_day', 'half_daypm')
      AND TIMESTAMP(e.event_date, sch.pm_login_end) < NOW()
      AND (a.attendance_id IS NULL OR a.pm_login_time IS NULL)
      AND ef.miss_pm_login > 0
      AND sf.fine_id IS NULL;

    
    INSERT INTO student_fines (student_id, event_id, fine_reason, amount, status, recorded_at)
    SELECT s.student_id, e.event_id, 'Missing PM logout', ef.miss_pm_logout, 'unpaid', NOW()
    FROM events e
    INNER JOIN attendance_schedule sch ON e.event_id = sch.event_id
    INNER JOIN event_fines ef ON e.event_id = ef.event_id
    CROSS JOIN students s
    LEFT JOIN attendance a ON e.event_id = a.event_id AND s.student_id = a.student_id
    LEFT JOIN student_fines sf ON sf.student_id = s.student_id 
                              AND sf.event_id = e.event_id 
                              AND sf.fine_reason = 'Missing PM logout'
    WHERE e.event_type IN ('whole_day', 'half_daypm')
      AND TIMESTAMP(e.event_date, sch.pm_logout_end) < NOW()
      AND (a.attendance_id IS NULL OR a.pm_logout_time IS NULL)
      AND ef.miss_pm_logout > 0
      AND sf.fine_id IS NULL;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `am_login_time` datetime DEFAULT NULL,
  `am_logout_time` datetime DEFAULT NULL,
  `pm_login_time` datetime DEFAULT NULL,
  `pm_logout_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_id`, `event_id`, `am_login_time`, `am_logout_time`, `pm_login_time`, `pm_logout_time`, `created_at`) VALUES
(5, '32-5421-351', 62, '2026-03-16 08:09:18', '2026-03-16 12:11:14', '2026-03-16 13:12:28', '2026-03-16 17:16:57', '2026-03-16 00:09:18'),
(6, '24-0187-667', 62, NULL, NULL, NULL, '2026-03-16 17:15:10', '2026-03-16 09:15:10');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_schedule`
--

CREATE TABLE `attendance_schedule` (
  `schedule_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `am_login_start` time DEFAULT NULL,
  `am_login_end` time DEFAULT NULL,
  `am_logout_start` time DEFAULT NULL,
  `am_logout_end` time DEFAULT NULL,
  `pm_login_start` time DEFAULT NULL,
  `pm_login_end` time DEFAULT NULL,
  `pm_logout_start` time DEFAULT NULL,
  `pm_logout_end` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_schedule`
--

INSERT INTO `attendance_schedule` (`schedule_id`, `event_id`, `am_login_start`, `am_login_end`, `am_logout_start`, `am_logout_end`, `pm_login_start`, `pm_login_end`, `pm_logout_start`, `pm_logout_end`) VALUES
(51, 62, '08:00:00', '09:00:00', '12:00:00', '13:00:00', '13:00:00', '14:00:00', '17:00:00', '18:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('whole_day','half_day_am','half_day_pm') NOT NULL DEFAULT 'whole_day',
  `half_day_period` enum('am','pm') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fines_generated` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_name`, `event_date`, `event_type`, `half_day_period`, `description`, `location`, `created_by`, `created_at`, `fines_generated`) VALUES
(62, 'asdfasdf', '2026-03-16', 'whole_day', NULL, 'asdf', 'asdf', '24', '2026-03-15 23:08:47', 0);

-- --------------------------------------------------------

--
-- Table structure for table `event_fines`
--

CREATE TABLE `event_fines` (
  `fine_setting_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `miss_am_login` decimal(10,2) DEFAULT 0.00,
  `miss_am_logout` decimal(10,2) DEFAULT 0.00,
  `miss_pm_login` decimal(10,2) DEFAULT 0.00,
  `miss_pm_logout` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_fines`
--

INSERT INTO `event_fines` (`fine_setting_id`, `event_id`, `miss_am_login`, `miss_am_logout`, `miss_pm_login`, `miss_pm_logout`) VALUES
(51, 62, 5.00, 5.00, 5.00, 5.00);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` varchar(11) DEFAULT NULL,
  `user_type` enum('student','officer') DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `officer_id` varchar(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`officer_id`, `full_name`, `password`, `position`, `created_at`) VALUES
('23-2321-423', 'Japhet Bongbong', '$2y$10$NUcT8NUaNECiWbN3oDh8mOjkncqQsajbebfqZAnpvgM.mFIJxL/cO', 'Officer', '2026-03-11 17:07:23'),
('24-0187-667', 'Jomarie M, Alcaria', '$2y$10$Wma6TW4lXKnY7eD3mijJP.Kf1I7QDzF2phMD9ttAPd5QcMT7h1LMu', 'Officer', '2026-03-11 17:00:20'),
('24-5454-222', 'Jomarie M, Alcaria', '$2y$10$y1f9yT4YA8v8P4G.mrF8Re3GyL6asQ4fHrY7YMp7RuQpP1CXl2puq', 'Officer', '2026-03-11 16:56:57');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `year_level` int(11) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `full_name`, `password`, `year_level`, `section`, `created_at`) VALUES
('24-0187-667', 'Jomarie Alcaria', '$2y$10$7sSuEMh1CxsGxFaMieE4H.0lOBv1bgrfPyGv7GZ6HvYeCqo0QCYX6', 2, 'B', '2026-03-13 09:48:19'),
('24-0235-659', 'asdfsadf', '$2y$10$1gQjdp63ALxpIIIig/QZh.o49o59OyeUyEVaXGKvliUySQqj3mQ8u', 2, 'B', '2026-03-13 17:01:21'),
('24-1548-653', '123', '$2y$10$ig8xlPhS1VundSKtmM4QjuFRl1HpSv3ZGUdhbNXG9PsldCjb/ehMK', 3, 'B', '2026-03-20 18:12:40'),
('24-5461-876', 'jsdahf', '$2y$10$UNaSD9eXZnG5fwlkihqREO8qUBmjlWJpf8cGghmqmszAv8hxyiGlK', 1, 'A', '2026-03-20 18:14:55'),
('24-5487-651', 'Ian Larido Sonio', '$2y$10$5/8vg4w1FKiLiruTSNYYUe0J9dF/24KbHJJnn6n9Cm5TY6wBk9joC', 2, 'A', '2026-03-20 17:57:01'),
('24-5487-654', 'John Laurence Pogou', '$2y$10$362DmnNxfkdnpZk5IhKp1OwROs0beeFqAXZROQqsRlJ9B9Iu0p7Me', 4, 'B', '2026-03-20 18:05:40'),
('24-9999-452', 'Japhet Bongbong', '$2y$10$g.HWBf/z5B6lNPmZH/QaM.xfZYXMzFChen.ggSdom7srlSwqiwB8u', 3, 'A', '2026-03-20 17:55:51'),
('32-5421-351', 'asdfsadf', '$2y$10$5h5FnfHd2q8S9R7FobNnH.mUgwGe3OQTEyV8F8d9kHqp/ssn1poJ.', 1, 'A', '2026-03-20 18:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `student_fines`
--

CREATE TABLE `student_fines` (
  `fine_id` int(11) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `fine_reason` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_fines`
--

INSERT INTO `student_fines` (`fine_id`, `student_id`, `event_id`, `fine_reason`, `amount`, `status`, `recorded_at`) VALUES
(901, '24-0187-667', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(902, '24-0235-659', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(903, '24-1548-653', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(904, '24-5461-876', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(905, '24-5487-651', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(906, '24-5487-654', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(907, '24-9999-452', 62, 'Missing AM login', 5.00, 'unpaid', '2026-03-16 02:09:46'),
(908, '24-0187-667', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(909, '24-0235-659', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(910, '24-1548-653', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(911, '24-5461-876', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(912, '24-5487-651', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(913, '24-5487-654', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(914, '24-9999-452', 62, 'Missing AM logout', 5.00, 'unpaid', '2026-03-16 05:11:44'),
(915, '24-0187-667', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(916, '24-0235-659', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(917, '24-1548-653', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(918, '24-5461-876', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(919, '24-5487-651', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(920, '24-5487-654', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(921, '24-9999-452', 62, 'Missing PM login', 5.00, 'unpaid', '2026-03-16 08:12:49'),
(922, '24-0235-659', 62, 'Missing PM logout', 5.00, 'unpaid', '2026-03-16 10:18:02'),
(923, '24-1548-653', 62, 'Missing PM logout', 5.00, 'unpaid', '2026-03-16 10:18:02'),
(924, '24-5461-876', 62, 'Missing PM logout', 5.00, 'unpaid', '2026-03-16 10:18:02'),
(925, '24-5487-651', 62, 'Missing PM logout', 5.00, 'unpaid', '2026-03-16 10:18:02'),
(926, '24-5487-654', 62, 'Missing PM logout', 5.00, 'unpaid', '2026-03-16 10:18:02'),
(927, '24-9999-452', 62, 'Missing PM logout', 5.00, 'unpaid', '2026-03-16 10:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `websocket_messages`
--

CREATE TABLE `websocket_messages` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `websocket_messages`
--

INSERT INTO `websocket_messages` (`id`, `message`, `created_at`) VALUES
(1, '{\"type\":\"student_created\",\"student\":{\"student_id\":\"24-0187-667\",\"full_name\":\"Jomarie Alcaria\",\"year_level\":2,\"section\":\"B\"},\"timestamp\":1773395299}', '2026-03-13 09:48:19'),
(2, '{\"type\":\"student_created\",\"student\":{\"student_id\":\"24-0235-659\",\"full_name\":\"asdfsadf\",\"year_level\":2,\"section\":\"B\"},\"timestamp\":1773421281}', '2026-03-13 17:01:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `attendance_schedule`
--
ALTER TABLE `attendance_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `attendance_schedule_ibfk_1` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `events_ibfk_1` (`created_by`);

--
-- Indexes for table `event_fines`
--
ALTER TABLE `event_fines`
  ADD PRIMARY KEY (`fine_setting_id`),
  ADD KEY `event_fines_ibfk_1` (`event_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`officer_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `student_fines`
--
ALTER TABLE `student_fines`
  ADD PRIMARY KEY (`fine_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `student_fines_ibfk_2` (`event_id`);

--
-- Indexes for table `websocket_messages`
--
ALTER TABLE `websocket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `attendance_schedule`
--
ALTER TABLE `attendance_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `event_fines`
--
ALTER TABLE `event_fines`
  MODIFY `fine_setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fines`
--
ALTER TABLE `student_fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=929;

--
-- AUTO_INCREMENT for table `websocket_messages`
--
ALTER TABLE `websocket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);

--
-- Constraints for table `attendance_schedule`
--
ALTER TABLE `attendance_schedule`
  ADD CONSTRAINT `attendance_schedule_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_fines`
--
ALTER TABLE `event_fines`
  ADD CONSTRAINT `event_fines_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_fines`
--
ALTER TABLE `student_fines`
  ADD CONSTRAINT `student_fines_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `student_fines_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `daily_fine_generation` ON SCHEDULE EVERY 1 DAY STARTS '2025-01-01 01:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    CALL generate_event_fines();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
