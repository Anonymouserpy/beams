-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 17, 2026 at 01:07 PM
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
(69, 80, '08:00:00', '09:00:00', '12:00:00', '13:00:00', '13:00:00', '14:00:00', '17:00:00', '18:00:00'),
(70, 81, '08:00:00', '09:00:00', '12:00:00', '13:00:00', '13:00:00', '14:00:00', '17:00:00', '18:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `officer_id` varchar(11) NOT NULL,
  `action` enum('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','VIEW') NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` varchar(50) DEFAULT NULL,
  `old_data` text DEFAULT NULL,
  `new_data` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `officer_id`, `action`, `table_name`, `record_id`, `old_data`, `new_data`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:29'),
(2, '99-6666-888', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-15 22:15:37\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:37'),
(3, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-15 22:15:38\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:38'),
(4, '99-6666-888', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-15 22:15:39\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:39'),
(5, '99-6666-888', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-15 22:15:44\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:44'),
(6, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-15 22:15:47\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:47'),
(7, '99-6666-888', 'VIEW', 'students', '38-1029-129', NULL, '{\"action\":\"view_student_details\",\"timestamp\":\"2026-05-15 22:15:49\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:49'),
(8, '99-6666-888', 'UPDATE', 'students', '38-1029-129', '{\"old\":{\"student_id\":\"38-1029-129\",\"full_name\":\"Brian Perez\",\"password\":\"$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G\",\"year_level\":1,\"section\":\"A\",\"created_at\":\"2026-03-28 17:14:42\"},\"changes\":{\"year_level\":{\"old\":1,\"new\":2}}}', '{\"student_id\":\"38-1029-129\",\"full_name\":\"Brian Perez\",\"password\":\"$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G\",\"year_level\":2,\"section\":\"A\",\"created_at\":\"2026-03-28 17:14:42\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-15 20:15:51'),
(9, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:21'),
(10, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:44\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:44'),
(11, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:48\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:48'),
(12, '99-6666-888', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:51\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:51'),
(13, '99-6666-888', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:53\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:53'),
(14, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:56\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:56'),
(15, '99-6666-888', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:58\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:58'),
(16, '99-6666-888', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:42:59\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:42:59'),
(17, '99-6666-888', 'VIEW', 'manage_officers_page', NULL, NULL, '{\"action\":\"VIEW\",\"timestamp\":\"2026-05-17 05:43:10\",\"details\":\"Accessed Manage Officers page\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:43:10'),
(18, '99-6666-888', 'UPDATE', 'officers', '24-0187-667', '{\"old\":{\"full_name\":\"Jomarie M, Alcaria\",\"position\":\"Officer\"},\"changes\":[]}', '{\"full_name\":\"Jomarie M, Alcaria\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:43:45'),
(19, '99-6666-888', 'UPDATE', 'officers', '24-0187-667', '{\"action\":\"password_change\",\"officer_name\":\"Jomarie M, Alcaria\"}', '{\"action\":\"password_changed\",\"changed_by\":\"99-6666-888\",\"timestamp\":\"2026-05-17 05:43:45\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:43:45'),
(20, '24-0187-667', 'LOGIN', 'authentication', '24-0187-667', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"Jomarie M, Alcaria\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:43:56'),
(21, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:06\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:06'),
(22, '24-0187-667', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:06\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:06'),
(23, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:08\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:08'),
(24, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:09\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:09'),
(25, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:13\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:13'),
(26, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:13\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:13'),
(27, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:44:14\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:44:14'),
(28, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:45:07\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:45:07'),
(29, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:45:10\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:45:10'),
(30, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:45:19\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:45:19'),
(31, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:45:20\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:45:20'),
(32, '24-0187-667', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:14\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:14'),
(33, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:15\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:15'),
(34, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:17\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:17'),
(35, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:27\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:27'),
(36, '24-0187-667', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:28\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:28'),
(37, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:29\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:29'),
(38, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:57:32\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:32'),
(39, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:57:49'),
(40, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:59:06\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:59:06'),
(41, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:59:06\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:59:06'),
(42, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:59:06\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:59:06'),
(43, '24-0187-667', 'LOGIN', 'authentication', '24-0187-667', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"Jomarie M, Alcaria\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:59:21'),
(44, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 05:59:36\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 03:59:36'),
(45, '99-6666-888', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 06:00:50\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 04:00:50'),
(46, '99-6666-888', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 06:00:50\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 04:00:50'),
(47, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 07:22:58\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:22:58'),
(48, '99-6666-888', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 07:22:58\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:22:58'),
(49, '99-6666-888', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 07:23:00\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:00'),
(50, '99-6666-888', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 07:23:04\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:04'),
(51, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 07:23:07\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:07'),
(52, '99-6666-888', 'VIEW', 'students', '46-1037-137', NULL, '{\"action\":\"view_student_details\",\"timestamp\":\"2026-05-17 07:23:12\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:12'),
(53, '99-6666-888', 'VIEW', 'students', '46-1037-137', NULL, '{\"action\":\"view_student_details\",\"timestamp\":\"2026-05-17 07:23:23\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:23'),
(54, '99-6666-888', 'VIEW', 'students', '46-1037-137', NULL, '{\"action\":\"view_student_details\",\"timestamp\":\"2026-05-17 07:23:24\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:24'),
(55, '99-6666-888', 'UPDATE', 'students', '46-1037-137', '{\"old\":{\"student_id\":\"46-1037-137\",\"full_name\":\"Jacob Yap\",\"password\":\"$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G\",\"year_level\":1,\"section\":\"A\",\"created_at\":\"2026-03-28 17:14:42\"},\"changes\":{\"year_level\":{\"old\":1,\"new\":2}}}', '{\"student_id\":\"46-1037-137\",\"full_name\":\"Jacob Yap\",\"password\":\"$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G\",\"year_level\":2,\"section\":\"A\",\"created_at\":\"2026-03-28 17:14:42\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:23:26'),
(56, '99-6666-888', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 07:26:13\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 05:26:13'),
(57, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:39:25\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:39:25'),
(58, '24-0187-667', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:39:47\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:39:47'),
(59, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:39:50\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:39:50'),
(60, '24-0187-667', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:39:58\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:39:58'),
(61, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:40:07\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:40:07'),
(62, '24-0187-667', 'VIEW', 'manage_students_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:40:17\",\"page\":\"Manage Students\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:40:17'),
(63, '24-0187-667', 'VIEW', 'manage_events_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:40:26\",\"page\":\"Manage Events\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:40:26'),
(64, '24-0187-667', 'VIEW', 'create_event_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:40:28\",\"page\":\"Create Event\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:40:28'),
(65, '24-0187-667', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 08:40:30\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 06:40:30'),
(66, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:02:55'),
(67, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:03:32'),
(68, '99-6666-888', 'VIEW', 'manage_officers_page', NULL, NULL, '{\"action\":\"VIEW\",\"timestamp\":\"2026-05-17 13:03:40\",\"details\":\"Accessed Manage Officers page\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:03:40'),
(69, '99-6666-888', 'UPDATE', 'officers', '23-2321-423', '{\"old\":{\"full_name\":\"Japhet Bongbong\",\"position\":\"Admin\"},\"changes\":{\"position\":{\"old\":\"Admin\",\"new\":\"Officer\"}}}', '{\"full_name\":\"Japhet Bongbong\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:04:00'),
(70, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:04:54'),
(71, '99-6666-888', 'VIEW', 'student_fines_page', NULL, NULL, '{\"action\":\"page_access\",\"timestamp\":\"2026-05-17 13:04:56\",\"page\":\"Student Fines Management\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:04:56'),
(72, '99-6666-888', 'VIEW', 'manage_officers_page', NULL, NULL, '{\"action\":\"VIEW\",\"timestamp\":\"2026-05-17 13:05:00\",\"details\":\"Accessed Manage Officers page\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:05:00'),
(73, '99-6666-888', 'UPDATE', 'officers', '23-2321-423', '{\"old\":{\"full_name\":\"Japhet Bongbong\",\"position\":\"Officer\"},\"changes\":[]}', '{\"full_name\":\"Japhet Bongbong\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:05:15'),
(74, '99-6666-888', 'UPDATE', 'officers', '23-2321-423', '{\"action\":\"password_change\",\"officer_name\":\"Japhet Bongbong\"}', '{\"action\":\"password_changed\",\"changed_by\":\"99-6666-888\",\"timestamp\":\"2026-05-17 13:05:15\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:05:15'),
(75, '23-2321-423', 'LOGIN', 'authentication', '23-2321-423', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"Japhet Bongbong\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:05:28'),
(77, '23-2321-423', 'LOGIN', 'authentication', '23-2321-423', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"Japhet Bongbong\",\"position\":\"Officer\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:07:03'),
(78, '99-6666-888', 'LOGIN', 'authentication', '99-6666-888', NULL, '{\"status\":\"SUCCESS\",\"full_name\":\"hello\",\"position\":\"Admin\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-17 11:07:14');

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
(80, 'sadsdasd', '2026-05-12', 'whole_day', NULL, '', '', '99', '2026-05-12 07:45:59', 0),
(81, 'sczxczcz', '2026-05-12', 'whole_day', NULL, 'addasdas', 'cc', '99', '2026-05-12 09:33:33', 0);

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
(69, 80, 5.00, 5.00, 5.00, 5.00),
(70, 81, 20.00, 19.99, 20.00, 20.00);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`officer_id`, `full_name`, `password`, `position`, `created_at`) VALUES
('11-1111-111', 'asdasdasdsad', '$2y$10$05PSTJOg/cbFpltFX0QlsOAjmk20j873pknf.9Q.bSJrrlxPot2t2', 'Admin', '2026-03-28 08:16:26'),
('11-1123-123', 'sadfaf', '$2y$10$c.UT2Xe6IpSscrR/Vc/sKO9QjWPHLQLfe492ZV5gzgugPP82/26D.', 'Officer', '2026-03-28 08:22:54'),
('11-2314-123', 'asdfsadf', '$2y$10$7to9shZiWB/u0mrr7Rb6OecErSu95xKznCmHKDUMQAPianb741gkK', 'Admin', '2026-03-28 07:48:39'),
('11-3333-332', 'asdasdasdsad', '$2y$10$8DSBQCBxFxw6grDMHHY47OYfj4Hh7MQKy./d7DXlNihnU7RArB8We', 'Admin', '2026-03-28 08:17:15'),
('11-3333-333', 'asdasdasdsad', '$2y$10$QwvIBeJQ2tQrK8NZYsro2eaz8xC3wPkL.ldYqWABSddees8b2roPW', 'Admin', '2026-03-28 08:16:51'),
('11-3334-223', 'sadfsadf', '$2y$10$PNehbPtL6y89R1SyuyVbIelMDCg4GDIAZIuRTfOfl.jIU2hO7DaNG', 'Officer', '2026-03-28 08:25:41'),
('23-2321-423', 'Japhet Bongbong', '$2y$10$9W2ejuq1sLCg7yC7Xrj6dusSW9v42ACl.iHruTqaITfTQa.2VToMK', 'Officer', '2026-03-11 17:07:23'),
('24-0187-667', 'Jomarie M, Alcaria', '$2y$10$TRGED9QwUfFh630NlZ.bP.E4yN0Z535fIrm3MfHSTO78JOLyCcqjW', 'Officer', '2026-03-11 17:00:20'),
('24-5454-222', 'Jomarie M, Alcaria', '$2y$10$y1f9yT4YA8v8P4G.mrF8Re3GyL6asQ4fHrY7YMp7RuQpP1CXl2puq', 'Officer', '2026-03-11 16:56:57'),
('99-6666-888', 'hello', '$2y$10$Wtp8dYvO0EM8cQJADLigZOengZY3F4dUhqripMsJkm6P/ivaRAi42', 'Admin', '2026-05-12 07:42:44');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `full_name`, `password`, `year_level`, `section`, `created_at`) VALUES
('00-1091-191', 'Logan Agbayani', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('01-1092-192', 'Alan Basco', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('10-1001-101', 'Juan Dela Cruz', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('11-1002-102', 'Maria Santos', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('11-1111-111', '213123123123213', '$2y$10$W5o4sJ0XofhZApqw6/8MiukBYqfOpmLygclsqfn0nfAhxn0r4l/Ti', 2, 'B', '2026-03-28 08:10:29'),
('11-5555-444', 'john yin pogoy', '$2y$10$yh.DEqvJMHydBGlolXoZTuJnmI2A2O0I9JBJYoqEEiVoKnrq8Sg/W', 2, 'B', '2026-05-12 08:12:21'),
('12-1003-103', 'Jose Reyes', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('12-3212-321', 'asdfsadf', '$2y$10$72ePjw.gb.m8P1XjqsJHd.MfVet5D2EpmXSjf2HCS1EmTZjE4Farm', 2, 'B', '2026-03-28 09:23:17'),
('12-3213-546', 'ian sonio', '$2y$10$zBMtVyOAxkgsCuDNEqdoaOIshSEs6.McBx4fPdzI9Zj/f99rxAR5C', 2, 'B', '2026-03-28 09:21:06'),
('12-3231-123', 'Grace Rojas', '$2y$10$kpOFZ3/JTXihfujKl3vFr.ZA1qS8fm9ONgDR8aGIKqpePM4ITaX4i', 3, 'A', '2026-03-28 09:22:52'),
('12-3232-444', 'Japhet Bongbong', '$2y$10$ntp/5PfQoqUnJDrObypsoeaVDCm0551QtKgOhK0AkQ4dNm41fN.Ji', 2, 'B', '2026-03-28 09:20:26'),
('13-1004-104', 'Ana Garcia', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('14-1005-105', 'Mark Torres', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('15-1006-106', 'Paul Ramos', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('16-1007-107', 'Chris Mendoza', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('17-1008-108', 'Angel Castro', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('18-1009-109', 'Leo Lopez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('19-1010-110', 'John Rivera', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('20-1011-111', 'Jane Fernandez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('21-1012-112', 'Michael Aguilar', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('21-3212-321', 'skibidi', '$2y$10$1pc2oTZikCq6RhX74ku8oucTPP/QH9dtwVmgbH09.8nVXrQE89A4m', 3, 'A', '2026-03-28 09:21:30'),
('22-1013-113', 'Sarah Santiago', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('23-1014-114', 'David Alcantara', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('24-0154-877', 'yin pogoy', '$2y$10$NjkwREzWRv7XONFlj7Rlr.PX.9W.gKC2KmakXgsVa40z1AYBzXkKK', 2, 'B', '2026-03-28 09:22:01'),
('24-0187-667', 'Alcaria, Jomarie M.', '$2y$10$nO9DIkCyJCPS8QR/uyH0SeCtxpk4K9jhBbZu/MdtLntXNozjta1YG', 2, 'A', '2026-03-28 09:19:05'),
('24-1015-115', 'Catherine Bautista', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('25-1016-116', 'James Castillo', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('26-1017-117', 'Mary Cruz', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('26-5566-888', 'yin the pogoy', '$2y$10$wAZG5q8egNEXmG3HwEJ7TeyKyJIdlHEeCKi9z6U6VCD8JspHUDdYC', 1, 'A', '2026-05-12 09:19:02'),
('27-1018-118', 'Robert Dimagiba', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('28-1019-119', 'Patricia Estrada', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('29-1020-120', 'Jennifer Flores', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('30-1021-121', 'Charles Gutierrez', '$2y$10$Sr9x8a0D3CRPyQJ.6DX0Gu4JCNL1.9atTUU4wIm3PNZQQurXugn6a', 1, 'A', '2026-03-28 09:14:42'),
('31-1022-122', 'Daniel Hernandez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('32-1023-123', 'Matthew Ignacio', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('33-1024-124', 'Elizabeth Jimenez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('34-1025-125', 'Christopher Luna', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('35-1026-126', 'Joshua Marquez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('36-1027-127', 'Andrew Navarro', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('37-1028-128', 'Kevin Ortega', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('38-1029-129', 'Brian Perez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'A', '2026-03-28 09:14:42'),
('39-1030-130', 'George Quiambao', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('40-1031-131', 'Edward Ramirez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('41-1032-132', 'Ronald Soriano', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('42-1033-133', 'Timothy Tan', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('43-1034-134', 'Jason Uy', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('44-1035-135', 'Jeffrey Valdez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('45-1036-136', 'Ryan Villanueva', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('46-1037-137', 'Jacob Yap', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'A', '2026-03-28 09:14:42'),
('47-1038-138', 'Gary Zulueta', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('48-1039-139', 'Nicholas Abad', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('49-1040-140', 'Eric Bautista', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('50-1041-141', 'Jonathan Castro', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('51-1042-142', 'Stephen Delos Reyes', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('52-1043-143', 'Larry Encarnacion', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('53-1044-144', 'Justin Fajardo', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('54-1045-145', 'Scott Gomez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('55-1046-146', 'Brandon Herrera', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('56-1047-147', 'Benjamin Ilagan', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('57-1048-148', 'Samuel Jacinto', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('58-1049-149', 'Frank Kalaw', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('59-1050-150', 'Gregory Lacson', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('60-1051-151', 'Raymond Magtanggol', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('61-1052-152', 'Alexander Nacario', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('62-1053-153', 'Patrick Ocampo', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('63-1054-154', 'Jack Panganiban', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('64-1055-155', 'Dennis Quezon', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('65-1056-156', 'Jerry Roxas', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('66-1057-157', 'Tyler Sandoval', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('67-1058-158', 'Aaron Tecson', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('68-1059-159', 'Adam Ubaldo', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('69-1060-160', 'Nathan Velasco', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('70-1061-161', 'Henry Williams', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('71-1062-162', 'Douglas Xavier', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('72-1063-163', 'Zachary Yalong', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('73-1064-164', 'Peter Zamora', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('74-1065-165', 'Kyle Aguinaldo', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('75-1066-166', 'Walter Baltazar', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('76-1067-167', 'Ethan Calma', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('77-1068-168', 'Jeremy Dizon', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('78-1069-169', 'Harold Espiritu', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('79-1070-170', 'Keith Fernandez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('80-1071-171', 'Christian Gonzales', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('81-1072-172', 'Roger Hizon', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('82-1073-173', 'Noel Ignacio', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('83-1074-174', 'Gerald Jacinto', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('84-1075-175', 'Carl Kintanar', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('85-1076-176', 'Terry Luna', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('86-1077-177', 'Sean Manalo', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('87-1078-178', 'Austin Natividad', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('88-1079-179', 'Arthur Olaes', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('89-1080-180', 'Lawrence Ponce', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('90-1081-181', 'Jesse Quintos', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('91-1082-182', 'Dylan Ramirez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('92-1083-183', 'Bryan Sarmiento', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('93-1084-184', 'Joe Tolentino', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('94-1085-185', 'Jordan Uy', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('95-1086-186', 'Billy Vergara', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('96-1087-187', 'Bruce Wong', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('97-1088-188', 'Albert Xavi', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('98-1089-189', 'Willie Yba?ez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('98-8887-444', 'hello', '$2y$10$owgXBcZ1M2/8oqd0bkIii.NTyK0ggNFc.BBhHMAa2gl3TboqgQpgW', 1, 'A', '2026-05-12 09:24:22'),
('99-1090-190', 'Gabriel Zamora', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42');

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
(3136, '00-1091-191', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3137, '01-1092-192', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3138, '10-1001-101', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3139, '11-1002-102', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3140, '11-1111-111', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3141, '12-1003-103', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3142, '12-3212-321', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3143, '12-3213-546', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3144, '12-3231-123', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3145, '12-3232-444', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3146, '13-1004-104', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3147, '14-1005-105', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3148, '15-1006-106', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3149, '16-1007-107', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3150, '17-1008-108', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3151, '18-1009-109', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3152, '19-1010-110', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3153, '20-1011-111', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3154, '21-1012-112', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3155, '21-3212-321', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3156, '22-1013-113', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3157, '23-1014-114', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3158, '24-0154-877', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3159, '24-0187-667', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3160, '24-1015-115', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3161, '25-1016-116', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3162, '26-1017-117', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3163, '27-1018-118', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3164, '28-1019-119', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3165, '29-1020-120', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3166, '30-1021-121', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3167, '31-1022-122', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3168, '32-1023-123', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3169, '33-1024-124', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3170, '34-1025-125', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3171, '35-1026-126', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3172, '36-1027-127', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3173, '37-1028-128', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3174, '38-1029-129', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3175, '39-1030-130', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3176, '40-1031-131', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3177, '41-1032-132', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3178, '42-1033-133', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3179, '43-1034-134', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3180, '44-1035-135', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3181, '45-1036-136', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3182, '46-1037-137', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3183, '47-1038-138', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3184, '48-1039-139', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3185, '49-1040-140', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3186, '50-1041-141', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3187, '51-1042-142', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3188, '52-1043-143', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3189, '53-1044-144', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3190, '54-1045-145', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3191, '55-1046-146', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3192, '56-1047-147', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3193, '57-1048-148', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3194, '58-1049-149', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3195, '59-1050-150', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3196, '60-1051-151', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3197, '61-1052-152', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3198, '62-1053-153', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3199, '63-1054-154', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3200, '64-1055-155', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3201, '65-1056-156', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3202, '66-1057-157', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3203, '67-1058-158', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3204, '68-1059-159', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3205, '69-1060-160', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3206, '70-1061-161', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3207, '71-1062-162', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3208, '72-1063-163', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3209, '73-1064-164', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3210, '74-1065-165', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3211, '75-1066-166', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3212, '76-1067-167', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3213, '77-1068-168', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3214, '78-1069-169', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3215, '79-1070-170', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3216, '80-1071-171', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3217, '81-1072-172', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3218, '82-1073-173', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3219, '83-1074-174', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3220, '84-1075-175', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3221, '85-1076-176', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3222, '86-1077-177', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3223, '87-1078-178', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3224, '88-1079-179', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3225, '89-1080-180', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3226, '90-1081-181', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3227, '91-1082-182', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3228, '92-1083-183', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3229, '93-1084-184', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3230, '94-1085-185', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3231, '95-1086-186', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3232, '96-1087-187', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3233, '97-1088-188', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3234, '98-1089-189', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3235, '99-1090-190', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3263, '00-1091-191', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3264, '01-1092-192', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 07:46:05'),
(3265, '10-1001-101', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3266, '11-1002-102', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3267, '11-1111-111', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 07:46:05'),
(3268, '12-1003-103', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3269, '12-3212-321', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 07:46:05'),
(3270, '12-3213-546', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3271, '12-3231-123', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3272, '12-3232-444', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3273, '13-1004-104', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3274, '14-1005-105', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3275, '15-1006-106', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3276, '16-1007-107', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3277, '17-1008-108', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3278, '18-1009-109', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3279, '19-1010-110', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3280, '20-1011-111', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3281, '21-1012-112', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3282, '21-3212-321', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3283, '22-1013-113', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3284, '23-1014-114', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3285, '24-0154-877', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3286, '24-0187-667', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 07:46:05'),
(3287, '24-1015-115', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3288, '25-1016-116', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3289, '26-1017-117', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3290, '27-1018-118', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3291, '28-1019-119', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3292, '29-1020-120', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3293, '30-1021-121', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3294, '31-1022-122', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3295, '32-1023-123', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3296, '33-1024-124', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3297, '34-1025-125', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3298, '35-1026-126', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3299, '36-1027-127', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3300, '37-1028-128', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3301, '38-1029-129', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3302, '39-1030-130', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3303, '40-1031-131', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3304, '41-1032-132', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3305, '42-1033-133', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3306, '43-1034-134', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3307, '44-1035-135', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3308, '45-1036-136', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3309, '46-1037-137', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3310, '47-1038-138', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3311, '48-1039-139', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3312, '49-1040-140', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3313, '50-1041-141', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3314, '51-1042-142', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3315, '52-1043-143', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3316, '53-1044-144', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3317, '54-1045-145', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3318, '55-1046-146', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3319, '56-1047-147', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3320, '57-1048-148', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3321, '58-1049-149', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3322, '59-1050-150', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3323, '60-1051-151', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3324, '61-1052-152', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3325, '62-1053-153', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3326, '63-1054-154', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3327, '64-1055-155', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3328, '65-1056-156', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3329, '66-1057-157', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3330, '67-1058-158', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 07:46:05'),
(3331, '68-1059-159', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 07:46:05'),
(3332, '69-1060-160', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3333, '70-1061-161', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3334, '71-1062-162', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3335, '72-1063-163', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3336, '73-1064-164', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3337, '74-1065-165', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3338, '75-1066-166', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3339, '76-1067-167', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3340, '77-1068-168', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3341, '78-1069-169', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3342, '79-1070-170', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3343, '80-1071-171', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3344, '81-1072-172', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3345, '82-1073-173', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3346, '83-1074-174', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3347, '84-1075-175', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3348, '85-1076-176', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3349, '86-1077-177', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3350, '87-1078-178', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3351, '88-1079-179', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3352, '89-1080-180', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3353, '90-1081-181', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3354, '91-1082-182', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3355, '92-1083-183', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3356, '93-1084-184', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3357, '94-1085-185', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3358, '95-1086-186', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3359, '96-1087-187', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3360, '97-1088-188', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3361, '98-1089-189', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3362, '99-1090-190', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3390, '00-1091-191', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3391, '01-1092-192', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3392, '10-1001-101', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3393, '11-1002-102', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3395, '12-1003-103', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3396, '12-3212-321', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3397, '12-3213-546', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3398, '12-3231-123', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3399, '12-3232-444', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3400, '13-1004-104', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3401, '14-1005-105', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3402, '15-1006-106', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3403, '16-1007-107', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3404, '17-1008-108', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3405, '18-1009-109', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3406, '19-1010-110', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3407, '20-1011-111', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3408, '21-1012-112', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3409, '21-3212-321', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3410, '22-1013-113', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3411, '23-1014-114', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3412, '24-0154-877', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3413, '24-0187-667', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3414, '24-1015-115', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3415, '25-1016-116', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3416, '26-1017-117', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3417, '27-1018-118', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3418, '28-1019-119', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3419, '29-1020-120', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3420, '30-1021-121', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3421, '31-1022-122', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3422, '32-1023-123', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3423, '33-1024-124', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3424, '34-1025-125', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3425, '35-1026-126', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3426, '36-1027-127', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3427, '37-1028-128', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3428, '38-1029-129', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3429, '39-1030-130', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3430, '40-1031-131', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3431, '41-1032-132', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3432, '42-1033-133', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3433, '43-1034-134', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3434, '44-1035-135', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3435, '45-1036-136', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3436, '46-1037-137', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3437, '47-1038-138', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3438, '48-1039-139', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3439, '49-1040-140', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3440, '50-1041-141', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3441, '51-1042-142', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3442, '52-1043-143', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3443, '53-1044-144', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3444, '54-1045-145', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3445, '55-1046-146', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3446, '56-1047-147', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3447, '57-1048-148', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3448, '58-1049-149', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3449, '59-1050-150', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3450, '60-1051-151', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3451, '61-1052-152', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3452, '62-1053-153', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3453, '63-1054-154', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3454, '64-1055-155', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3455, '65-1056-156', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3456, '66-1057-157', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3457, '67-1058-158', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3458, '68-1059-159', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 07:46:05'),
(3459, '69-1060-160', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3460, '70-1061-161', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3461, '71-1062-162', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3462, '72-1063-163', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3463, '73-1064-164', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3464, '74-1065-165', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3465, '75-1066-166', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3466, '76-1067-167', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3467, '77-1068-168', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3468, '78-1069-169', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3469, '79-1070-170', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3470, '80-1071-171', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3471, '81-1072-172', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3472, '82-1073-173', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3473, '83-1074-174', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3474, '84-1075-175', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3475, '85-1076-176', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3476, '86-1077-177', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3477, '87-1078-178', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3478, '88-1079-179', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3479, '89-1080-180', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3480, '90-1081-181', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3481, '91-1082-182', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3482, '92-1083-183', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3483, '93-1084-184', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3484, '94-1085-185', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3485, '95-1086-186', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3486, '96-1087-187', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3487, '97-1088-188', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3488, '98-1089-189', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3489, '99-1090-190', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 07:46:05'),
(3517, '11-1111-111', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 08:10:55'),
(3518, '11-5555-444', 80, 'Missing AM login', 5.00, 'paid', '2026-05-12 08:13:08'),
(3519, '11-5555-444', 80, 'Missing AM logout', 5.00, 'paid', '2026-05-12 08:13:08'),
(3520, '11-5555-444', 80, 'Missing PM login', 5.00, 'paid', '2026-05-12 08:13:08'),
(3521, '26-5566-888', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 09:20:54'),
(3522, '26-5566-888', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 09:20:54'),
(3523, '26-5566-888', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 09:20:54'),
(3524, '98-8887-444', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-12 09:24:31'),
(3525, '98-8887-444', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-12 09:24:31'),
(3526, '98-8887-444', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-12 09:24:31'),
(3527, '00-1091-191', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3528, '01-1092-192', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3529, '10-1001-101', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3530, '11-1002-102', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3531, '11-1111-111', 81, 'Missing AM login', 20.00, 'paid', '2026-05-12 09:33:38'),
(3532, '11-5555-444', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3533, '12-1003-103', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3534, '12-3212-321', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3535, '12-3213-546', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3536, '12-3231-123', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3537, '12-3232-444', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3538, '13-1004-104', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3539, '14-1005-105', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3540, '15-1006-106', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3541, '16-1007-107', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3542, '17-1008-108', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3543, '18-1009-109', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3544, '19-1010-110', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3545, '20-1011-111', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3546, '21-1012-112', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3547, '21-3212-321', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3548, '22-1013-113', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3549, '23-1014-114', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3550, '24-0154-877', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3551, '24-0187-667', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3552, '24-1015-115', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3553, '25-1016-116', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3554, '26-1017-117', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3555, '26-5566-888', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3556, '27-1018-118', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3557, '28-1019-119', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3558, '29-1020-120', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3559, '30-1021-121', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3560, '31-1022-122', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3561, '32-1023-123', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3562, '33-1024-124', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3563, '34-1025-125', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3564, '35-1026-126', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3565, '36-1027-127', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3566, '37-1028-128', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3567, '38-1029-129', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3568, '39-1030-130', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3569, '40-1031-131', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3570, '41-1032-132', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3571, '42-1033-133', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3572, '43-1034-134', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3573, '44-1035-135', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3574, '45-1036-136', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3575, '46-1037-137', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3576, '47-1038-138', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3577, '48-1039-139', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3578, '49-1040-140', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3579, '50-1041-141', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3580, '51-1042-142', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3581, '52-1043-143', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3582, '53-1044-144', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3583, '54-1045-145', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3584, '55-1046-146', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3585, '56-1047-147', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3586, '57-1048-148', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3587, '58-1049-149', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3588, '59-1050-150', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3589, '60-1051-151', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3590, '61-1052-152', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3591, '62-1053-153', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3592, '63-1054-154', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3593, '64-1055-155', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3594, '65-1056-156', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3595, '66-1057-157', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3596, '67-1058-158', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3597, '68-1059-159', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3598, '69-1060-160', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3599, '70-1061-161', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3600, '71-1062-162', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3601, '72-1063-163', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3602, '73-1064-164', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3603, '74-1065-165', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3604, '75-1066-166', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3605, '76-1067-167', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3606, '77-1068-168', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3607, '78-1069-169', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3608, '79-1070-170', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3609, '80-1071-171', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3610, '81-1072-172', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3611, '82-1073-173', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3612, '83-1074-174', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3613, '84-1075-175', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3614, '85-1076-176', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3615, '86-1077-177', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3616, '87-1078-178', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3617, '88-1079-179', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3618, '89-1080-180', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3619, '90-1081-181', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3620, '91-1082-182', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3621, '92-1083-183', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3622, '93-1084-184', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3623, '94-1085-185', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3624, '95-1086-186', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3625, '96-1087-187', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3626, '97-1088-188', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3627, '98-1089-189', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3628, '98-8887-444', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3629, '99-1090-190', 81, 'Missing AM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3654, '00-1091-191', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3655, '01-1092-192', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3656, '10-1001-101', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3657, '11-1002-102', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3658, '11-1111-111', 81, 'Missing AM logout', 19.99, 'paid', '2026-05-12 09:33:38'),
(3659, '11-5555-444', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3660, '12-1003-103', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3661, '12-3212-321', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3662, '12-3213-546', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3663, '12-3231-123', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3664, '12-3232-444', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3665, '13-1004-104', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3666, '14-1005-105', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3667, '15-1006-106', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3668, '16-1007-107', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3669, '17-1008-108', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3670, '18-1009-109', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3671, '19-1010-110', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3672, '20-1011-111', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3673, '21-1012-112', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3674, '21-3212-321', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3675, '22-1013-113', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3676, '23-1014-114', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3677, '24-0154-877', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3678, '24-0187-667', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3679, '24-1015-115', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3680, '25-1016-116', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3681, '26-1017-117', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3682, '26-5566-888', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3683, '27-1018-118', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3684, '28-1019-119', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3685, '29-1020-120', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3686, '30-1021-121', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3687, '31-1022-122', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3688, '32-1023-123', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3689, '33-1024-124', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3690, '34-1025-125', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3691, '35-1026-126', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3692, '36-1027-127', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3693, '37-1028-128', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3694, '38-1029-129', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3695, '39-1030-130', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3696, '40-1031-131', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3697, '41-1032-132', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3698, '42-1033-133', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3699, '43-1034-134', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3700, '44-1035-135', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3701, '45-1036-136', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3702, '46-1037-137', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3703, '47-1038-138', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3704, '48-1039-139', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3705, '49-1040-140', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3706, '50-1041-141', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3707, '51-1042-142', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3708, '52-1043-143', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3709, '53-1044-144', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3710, '54-1045-145', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3711, '55-1046-146', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3712, '56-1047-147', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3713, '57-1048-148', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3714, '58-1049-149', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3715, '59-1050-150', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3716, '60-1051-151', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3717, '61-1052-152', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3718, '62-1053-153', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3719, '63-1054-154', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3720, '64-1055-155', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3721, '65-1056-156', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3722, '66-1057-157', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3723, '67-1058-158', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3724, '68-1059-159', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3725, '69-1060-160', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3726, '70-1061-161', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3727, '71-1062-162', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3728, '72-1063-163', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3729, '73-1064-164', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3730, '74-1065-165', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3731, '75-1066-166', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3732, '76-1067-167', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3733, '77-1068-168', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3734, '78-1069-169', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3735, '79-1070-170', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3736, '80-1071-171', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3737, '81-1072-172', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3738, '82-1073-173', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3739, '83-1074-174', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3740, '84-1075-175', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3741, '85-1076-176', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3742, '86-1077-177', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3743, '87-1078-178', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3744, '88-1079-179', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3745, '89-1080-180', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3746, '90-1081-181', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3747, '91-1082-182', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3748, '92-1083-183', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3749, '93-1084-184', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3750, '94-1085-185', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3751, '95-1086-186', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3752, '96-1087-187', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3753, '97-1088-188', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3754, '98-1089-189', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3755, '98-8887-444', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3756, '99-1090-190', 81, 'Missing AM logout', 19.99, 'unpaid', '2026-05-12 09:33:38'),
(3781, '00-1091-191', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3782, '01-1092-192', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3783, '10-1001-101', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3784, '11-1002-102', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3785, '11-1111-111', 81, 'Missing PM login', 20.00, 'paid', '2026-05-12 09:33:38'),
(3786, '11-5555-444', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3787, '12-1003-103', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3788, '12-3212-321', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3789, '12-3213-546', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3790, '12-3231-123', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3791, '12-3232-444', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3792, '13-1004-104', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3793, '14-1005-105', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3794, '15-1006-106', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3795, '16-1007-107', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3796, '17-1008-108', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3797, '18-1009-109', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3798, '19-1010-110', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3799, '20-1011-111', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3800, '21-1012-112', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3801, '21-3212-321', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3802, '22-1013-113', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3803, '23-1014-114', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3804, '24-0154-877', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3805, '24-0187-667', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3806, '24-1015-115', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3807, '25-1016-116', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3808, '26-1017-117', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3809, '26-5566-888', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3810, '27-1018-118', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3811, '28-1019-119', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3812, '29-1020-120', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3813, '30-1021-121', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3814, '31-1022-122', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3815, '32-1023-123', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3816, '33-1024-124', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3817, '34-1025-125', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3818, '35-1026-126', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3819, '36-1027-127', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3820, '37-1028-128', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3821, '38-1029-129', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3822, '39-1030-130', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3823, '40-1031-131', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3824, '41-1032-132', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3825, '42-1033-133', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3826, '43-1034-134', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3827, '44-1035-135', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3828, '45-1036-136', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3829, '46-1037-137', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3830, '47-1038-138', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3831, '48-1039-139', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3832, '49-1040-140', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3833, '50-1041-141', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3834, '51-1042-142', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3835, '52-1043-143', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3836, '53-1044-144', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3837, '54-1045-145', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3838, '55-1046-146', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3839, '56-1047-147', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3840, '57-1048-148', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3841, '58-1049-149', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3842, '59-1050-150', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3843, '60-1051-151', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3844, '61-1052-152', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3845, '62-1053-153', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3846, '63-1054-154', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3847, '64-1055-155', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3848, '65-1056-156', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3849, '66-1057-157', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3850, '67-1058-158', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3851, '68-1059-159', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3852, '69-1060-160', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3853, '70-1061-161', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38');
INSERT INTO `student_fines` (`fine_id`, `student_id`, `event_id`, `fine_reason`, `amount`, `status`, `recorded_at`) VALUES
(3854, '71-1062-162', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3855, '72-1063-163', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3856, '73-1064-164', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3857, '74-1065-165', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3858, '75-1066-166', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3859, '76-1067-167', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3860, '77-1068-168', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3861, '78-1069-169', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3862, '79-1070-170', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3863, '80-1071-171', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3864, '81-1072-172', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3865, '82-1073-173', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3866, '83-1074-174', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3867, '84-1075-175', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3868, '85-1076-176', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3869, '86-1077-177', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3870, '87-1078-178', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3871, '88-1079-179', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3872, '89-1080-180', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3873, '90-1081-181', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3874, '91-1082-182', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3875, '92-1083-183', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3876, '93-1084-184', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3877, '94-1085-185', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3878, '95-1086-186', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3879, '96-1087-187', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3880, '97-1088-188', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3881, '98-1089-189', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3882, '98-8887-444', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3883, '99-1090-190', 81, 'Missing PM login', 20.00, 'unpaid', '2026-05-12 09:33:38'),
(3908, '00-1091-191', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3909, '01-1092-192', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3910, '10-1001-101', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3911, '11-1002-102', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3912, '11-1111-111', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3913, '11-5555-444', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3914, '12-1003-103', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3915, '12-3212-321', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3916, '12-3213-546', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3917, '12-3231-123', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3918, '12-3232-444', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3919, '13-1004-104', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3920, '14-1005-105', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3921, '15-1006-106', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3922, '16-1007-107', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3923, '17-1008-108', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3924, '18-1009-109', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3925, '19-1010-110', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3926, '20-1011-111', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3927, '21-1012-112', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3928, '21-3212-321', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3929, '22-1013-113', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3930, '23-1014-114', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3931, '24-0154-877', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3932, '24-0187-667', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3933, '24-1015-115', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3934, '25-1016-116', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3935, '26-1017-117', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3936, '26-5566-888', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3937, '27-1018-118', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3938, '28-1019-119', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3939, '29-1020-120', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3940, '30-1021-121', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3941, '31-1022-122', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3942, '32-1023-123', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3943, '33-1024-124', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3944, '34-1025-125', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3945, '35-1026-126', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3946, '36-1027-127', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3947, '37-1028-128', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3948, '38-1029-129', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3949, '39-1030-130', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3950, '40-1031-131', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3951, '41-1032-132', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3952, '42-1033-133', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3953, '43-1034-134', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3954, '44-1035-135', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3955, '45-1036-136', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3956, '46-1037-137', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3957, '47-1038-138', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3958, '48-1039-139', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3959, '49-1040-140', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3960, '50-1041-141', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3961, '51-1042-142', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3962, '52-1043-143', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3963, '53-1044-144', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3964, '54-1045-145', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3965, '55-1046-146', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3966, '56-1047-147', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3967, '57-1048-148', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3968, '58-1049-149', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3969, '59-1050-150', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3970, '60-1051-151', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3971, '61-1052-152', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3972, '62-1053-153', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3973, '63-1054-154', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3974, '64-1055-155', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3975, '65-1056-156', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3976, '66-1057-157', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3977, '67-1058-158', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3978, '68-1059-159', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3979, '69-1060-160', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3980, '70-1061-161', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3981, '71-1062-162', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3982, '72-1063-163', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3983, '73-1064-164', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3984, '74-1065-165', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3985, '75-1066-166', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3986, '76-1067-167', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3987, '77-1068-168', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3988, '78-1069-169', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3989, '79-1070-170', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3990, '80-1071-171', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3991, '81-1072-172', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3992, '82-1073-173', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3993, '83-1074-174', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3994, '84-1075-175', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3995, '85-1076-176', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3996, '86-1077-177', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3997, '87-1078-178', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3998, '88-1079-179', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(3999, '89-1080-180', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4000, '90-1081-181', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4001, '91-1082-182', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4002, '92-1083-183', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4003, '93-1084-184', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4004, '94-1085-185', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4005, '95-1086-186', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4006, '96-1087-187', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4007, '97-1088-188', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4008, '98-1089-189', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4009, '98-8887-444', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4010, '99-1090-190', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-15 20:15:38'),
(4011, '00-1091-191', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4012, '01-1092-192', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4013, '10-1001-101', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4014, '11-1002-102', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4015, '11-1111-111', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4016, '11-5555-444', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4017, '12-1003-103', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4018, '12-3212-321', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4019, '12-3213-546', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4020, '12-3231-123', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4021, '12-3232-444', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4022, '13-1004-104', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4023, '14-1005-105', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4024, '15-1006-106', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4025, '16-1007-107', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4026, '17-1008-108', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4027, '18-1009-109', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4028, '19-1010-110', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4029, '20-1011-111', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4030, '21-1012-112', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4031, '21-3212-321', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4032, '22-1013-113', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4033, '23-1014-114', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4034, '24-0154-877', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4035, '24-0187-667', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4036, '24-1015-115', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4037, '25-1016-116', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4038, '26-1017-117', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4039, '26-5566-888', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4040, '27-1018-118', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4041, '28-1019-119', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4042, '29-1020-120', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4043, '30-1021-121', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4044, '31-1022-122', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4045, '32-1023-123', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4046, '33-1024-124', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4047, '34-1025-125', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4048, '35-1026-126', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4049, '36-1027-127', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4050, '37-1028-128', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4051, '38-1029-129', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4052, '39-1030-130', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4053, '40-1031-131', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4054, '41-1032-132', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4055, '42-1033-133', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4056, '43-1034-134', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4057, '44-1035-135', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4058, '45-1036-136', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4059, '46-1037-137', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4060, '47-1038-138', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4061, '48-1039-139', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4062, '49-1040-140', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4063, '50-1041-141', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4064, '51-1042-142', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4065, '52-1043-143', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4066, '53-1044-144', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4067, '54-1045-145', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4068, '55-1046-146', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4069, '56-1047-147', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4070, '57-1048-148', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4071, '58-1049-149', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4072, '59-1050-150', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4073, '60-1051-151', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4074, '61-1052-152', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4075, '62-1053-153', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4076, '63-1054-154', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4077, '64-1055-155', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4078, '65-1056-156', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4079, '66-1057-157', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4080, '67-1058-158', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4081, '68-1059-159', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4082, '69-1060-160', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4083, '70-1061-161', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4084, '71-1062-162', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4085, '72-1063-163', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4086, '73-1064-164', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4087, '74-1065-165', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4088, '75-1066-166', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4089, '76-1067-167', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4090, '77-1068-168', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4091, '78-1069-169', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4092, '79-1070-170', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4093, '80-1071-171', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4094, '81-1072-172', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4095, '82-1073-173', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4096, '83-1074-174', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4097, '84-1075-175', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4098, '85-1076-176', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4099, '86-1077-177', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4100, '87-1078-178', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4101, '88-1079-179', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4102, '89-1080-180', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4103, '90-1081-181', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4104, '91-1082-182', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4105, '92-1083-183', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4106, '93-1084-184', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4107, '94-1085-185', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4108, '95-1086-186', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4109, '96-1087-187', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4110, '97-1088-188', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4111, '98-1089-189', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4112, '98-8887-444', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38'),
(4113, '99-1090-190', 81, 'Missing PM logout', 20.00, 'unpaid', '2026-05-15 20:15:38');

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
(2, '{\"type\":\"student_created\",\"student\":{\"student_id\":\"24-0235-659\",\"full_name\":\"asdfsadf\",\"year_level\":2,\"section\":\"B\"},\"timestamp\":1773421281}', '2026-03-13 17:01:21'),
(3, '{\"type\":\"student_deleted\",\"student_id\":\"24-5461-876\",\"timestamp\":1773660088}', '2026-03-16 11:21:28'),
(4, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-0235-659\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-17 14:20:07'),
(5, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-5487-654\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-17 14:24:59'),
(6, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-9999-452\",\"count\":2},\"officer_id\":\"24-5454-222\"}', '2026-03-17 14:25:03'),
(7, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"32-5421-351\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:01:33'),
(8, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-0187-667\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:01:53'),
(9, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-0235-659\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:03:49'),
(10, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-1548-653\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:05:46'),
(11, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1138,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:06:24'),
(12, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1117,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:06:34'),
(13, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1141,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:07:48'),
(14, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1141,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:07:55'),
(15, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1124,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:11:53'),
(16, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1139,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:12:06'),
(17, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1131,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:12:42'),
(18, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1131,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:12:46'),
(19, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1141,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:23:29'),
(20, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1141,\"new_status\":\"paid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:23:33'),
(21, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1141,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:24:37'),
(22, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1123,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:28:20'),
(23, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-5487-651\",\"count\":1},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:28:24'),
(24, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-1548-653\",\"count\":1},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:28:31'),
(25, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"32-5421-351\",\"count\":1},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:34:44'),
(26, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-5487-654\",\"count\":3},\"officer_id\":\"24-5454-222\"}', '2026-03-20 08:34:49'),
(27, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-9999-452\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:15'),
(28, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1141,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:43'),
(29, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1134,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:46'),
(30, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1127,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:48'),
(31, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1135,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:50'),
(32, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1120,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:52'),
(33, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1128,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:55'),
(34, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1121,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:57'),
(35, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1136,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:59'),
(36, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1129,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:05:59'),
(37, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1115,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:01'),
(38, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1122,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:03'),
(39, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1137,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:03'),
(40, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1116,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:04'),
(41, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1123,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:05'),
(42, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1138,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:05'),
(43, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1131,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:06'),
(44, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1117,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:07'),
(45, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1130,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:07'),
(46, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1124,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:09'),
(47, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1139,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:10'),
(48, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1132,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:10'),
(49, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1118,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:12'),
(50, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1125,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:13'),
(51, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1140,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:14'),
(52, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1133,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:16'),
(53, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1119,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:19'),
(54, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1126,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:19'),
(55, '{\"action\":\"toggle_status\",\"details\":{\"fine_id\":1114,\"new_status\":\"unpaid\"},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:06:22'),
(56, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"32-5421-351\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:13:49'),
(57, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-1548-653\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:15:49'),
(58, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-0235-659\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 09:32:04'),
(59, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-0187-667\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 10:23:12'),
(60, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-5487-651\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-20 10:25:27'),
(61, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 15:07:36'),
(62, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":71,\"event_name\":\"sadfasdf\",\"event_date\":\"2026-03-26\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"asdf\",\"location\":\"asdf\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 15:35:33'),
(63, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 15:35:35'),
(64, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 15:43:05'),
(65, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 15:45:52'),
(66, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-1548-653\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-26 15:46:28'),
(67, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 15:46:54'),
(68, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":72,\"event_name\":\"sadasdasd\",\"event_date\":\"2026-03-26\",\"event_type\":\"whole_day\",\"half_day_period\":\"pm\",\"description\":\"asdasdasd\",\"location\":\"asdasd\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 15:47:22'),
(69, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 15:47:40'),
(70, '{\"type\":\"EVENT_DELETED\",\"payload\":{\"event_id\":72,\"event_name\":\"sadasdasd\",\"event_date\":\"2026-03-26\",\"event_type\":\"whole_day\"}}', '2026-03-26 15:47:46'),
(71, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":73,\"event_name\":\"sadfasdfsadf\",\"event_date\":\"2026-03-27\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"asdfsadfasdf\",\"location\":\"asdf\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 15:57:48'),
(72, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":74,\"event_name\":\"sadfasdfsadf\",\"event_date\":\"2026-03-27\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"asdfsadfasdf\",\"location\":\"asdf\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 15:58:44'),
(73, '{\"type\":\"EVENT_DELETED\",\"payload\":{\"event_id\":73,\"event_name\":\"sadfasdfsadf\",\"event_date\":\"2026-03-27\",\"event_type\":\"whole_day\",\"half_day_period\":null}}', '2026-03-26 15:59:44'),
(74, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 16:02:08'),
(75, '{\"type\":\"EVENT_DELETED\",\"payload\":{\"event_id\":71,\"event_name\":\"sadfasdf\",\"event_date\":\"2026-03-26\",\"event_type\":\"whole_day\",\"half_day_period\":null}}', '2026-03-26 16:02:40'),
(76, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":75,\"event_name\":\"asdasdasd\",\"event_date\":\"2026-03-27\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"asdasdas\",\"location\":\"asd\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 16:02:55'),
(77, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":76,\"event_name\":\"asdasdasd\",\"event_date\":\"2026-03-27\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"asdasdas\",\"location\":\"asd\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 16:07:16'),
(78, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":77,\"event_name\":\"asdfasdf\",\"event_date\":\"2026-03-29\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"asdasdasda\",\"location\":\"asdads\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-26 16:07:33'),
(79, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 16:08:10'),
(80, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-27 15:48:00'),
(81, '{\"type\":\"EVENT_DELETED\",\"payload\":{\"event_id\":78,\"event_name\":\"asdfasdf\",\"event_date\":\"2026-03-27\",\"event_type\":\"whole_day\",\"half_day_period\":null}}', '2026-03-27 15:55:48'),
(82, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-27 15:57:22'),
(83, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-27 15:58:31'),
(84, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-27 16:06:33'),
(85, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:06:51'),
(86, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:08:13'),
(87, '{\"type\":\"student_updated\",\"student_id\":\"03-0003-027\",\"timestamp\":1774627725}', '2026-03-27 16:08:45'),
(88, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:08:58'),
(89, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:00'),
(90, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:00'),
(91, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:04'),
(92, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:05'),
(93, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:06'),
(94, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:07'),
(95, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:08'),
(96, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:13:25'),
(97, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:13:38'),
(98, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":79,\"event_name\":\"12312312323213\",\"event_date\":\"2026-03-28\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"123123213\",\"location\":\"3123123\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-28 07:13:51'),
(99, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:17:46'),
(100, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:17:48'),
(101, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:19:14'),
(102, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"12-3223-421\"}}', '2026-03-28 07:24:18'),
(103, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"12-3223-421\"}}', '2026-03-28 07:24:23'),
(104, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"12-3223-421\"}}', '2026-03-28 07:24:24'),
(105, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"12-3223-421\"}}', '2026-03-28 07:24:25'),
(106, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"12-3223-421\"}}', '2026-03-28 07:24:25'),
(107, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"12-3223-421\"}}', '2026-03-28 07:24:26'),
(108, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:24:47'),
(109, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:24:59'),
(110, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:25:39'),
(111, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:27:08'),
(112, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:27:46'),
(113, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:32:24'),
(114, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 07:39:01'),
(115, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"11-1111-111\",\"full_name\":\"asdasdasdsad\",\"position\":\"Admin\"}}', '2026-03-28 08:16:26'),
(116, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"11-3333-333\",\"full_name\":\"asdasdasdsad\",\"position\":\"Admin\"}}', '2026-03-28 08:16:51'),
(117, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"11-3333-332\",\"full_name\":\"asdasdasdsad\",\"position\":\"Admin\"}}', '2026-03-28 08:17:15'),
(118, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:21:02'),
(119, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:21:07'),
(120, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:21:08'),
(121, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"11-1123-123\",\"full_name\":\"sadfaf\",\"position\":\"Officer\"}}', '2026-03-28 08:22:54'),
(122, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"full_name\":\"sadfsadf\",\"position\":\"Officer\"}}', '2026-03-28 08:25:41'),
(123, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:26:06'),
(124, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:26:53'),
(125, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:30:23'),
(126, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:30:28'),
(127, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:33:26'),
(128, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:33:27'),
(129, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:33:28'),
(130, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:33:30'),
(131, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:33:30'),
(132, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:50:36'),
(133, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:53:09'),
(134, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:54:52'),
(135, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:54:58'),
(136, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 08:56:14'),
(137, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 09:14:50'),
(138, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 09:19:19'),
(139, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 09:19:36'),
(140, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 09:21:33'),
(141, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 09:23:50'),
(142, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 07:45:19'),
(143, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":80,\"event_name\":\"sadsdasd\",\"event_date\":\"2026-05-12\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"\",\"location\":\"\",\"created_by\":\"99-6666-888\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-05-12 07:45:59'),
(144, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 07:46:05'),
(145, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-1111-111\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 07:50:20'),
(146, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"67-1058-158\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 07:50:25'),
(147, '{\"action\":\"delete\",\"details\":{\"fine_id\":3394},\"officer_id\":\"99-6666-888\"}', '2026-05-12 07:50:31'),
(148, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 08:10:55'),
(149, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:34'),
(150, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:36'),
(151, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:38'),
(152, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:40'),
(153, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:48'),
(154, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:50'),
(155, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:57'),
(156, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:57'),
(157, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:12:58'),
(158, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:00'),
(159, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:02'),
(160, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:03'),
(161, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 08:13:09'),
(162, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:27'),
(163, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:31'),
(164, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:32'),
(165, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:33'),
(166, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:35'),
(167, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:13:42'),
(168, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:14:51'),
(169, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 08:17:09'),
(170, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-1111-111\",\"count\":1},\"officer_id\":\"99-6666-888\"}', '2026-05-12 08:17:24'),
(171, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"12-3212-321\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 08:17:46'),
(172, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"68-1059-159\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 08:17:51'),
(173, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"01-1092-192\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 08:17:59'),
(174, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"24-0187-667\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 08:20:38'),
(175, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 08:21:52'),
(176, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:24:49'),
(177, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:24:53'),
(178, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:24:56'),
(179, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:00'),
(180, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:01'),
(181, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:12'),
(182, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-5555-444\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 08:25:25'),
(183, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:31'),
(184, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:33'),
(185, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:34'),
(186, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:25:43'),
(187, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 08:29:57'),
(188, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:36:51'),
(189, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:36:52'),
(190, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:38:27'),
(191, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:38:30'),
(192, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:38:31'),
(193, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:38:35'),
(194, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"11-5555-444\"}}', '2026-05-12 08:38:41'),
(195, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 08:43:38'),
(196, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:00:38'),
(197, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:05:47'),
(198, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:11:37'),
(199, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:11:49'),
(200, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:11:53'),
(201, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:11:59'),
(202, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:12:05'),
(203, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:12:11'),
(204, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:12:15'),
(205, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:12:26'),
(206, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:00'),
(207, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:09'),
(208, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:23'),
(209, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:28'),
(210, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:38'),
(211, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:44'),
(212, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:51'),
(213, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:13:58'),
(214, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:14:04'),
(215, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:14:09'),
(216, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:14:23'),
(217, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:15:17'),
(218, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:20:54'),
(219, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:21:00'),
(220, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"26-5566-888\",\"full_name\":\"yin the pogoy\",\"year_level\":1,\"section\":\"A\"},\"timestamp\":1778577695}', '2026-05-12 09:21:35'),
(221, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:22:04'),
(222, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"30-1021-121\",\"full_name\":\"Charles Gutierrez\",\"year_level\":1,\"section\":\"A\"},\"timestamp\":1778577752}', '2026-05-12 09:22:32'),
(223, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"30-1021-121\",\"full_name\":\"Charles Gutierrez\",\"year_level\":1,\"section\":\"A\"},\"timestamp\":1778577752}', '2026-05-12 09:22:32'),
(224, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"30-1021-121\"}}', '2026-05-12 09:22:44'),
(225, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"30-1021-121\"}}', '2026-05-12 09:22:52'),
(226, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"30-1021-121\"}}', '2026-05-12 09:23:41'),
(227, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:24:31'),
(228, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:31:54'),
(229, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:32:14'),
(230, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:33:38'),
(231, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:34:05'),
(232, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:34:22'),
(233, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:34:25'),
(234, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:34:32'),
(235, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:34:35'),
(236, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:34:39'),
(237, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:34:40'),
(238, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-1111-111\",\"count\":3},\"officer_id\":\"99-6666-888\"}', '2026-05-12 09:34:49'),
(239, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:35:18'),
(240, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:35:40'),
(241, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:35:48'),
(242, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:35:53'),
(243, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:36:01'),
(244, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:36:30'),
(245, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:37:07'),
(246, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:37:17'),
(247, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:42:59'),
(248, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:43:02'),
(249, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:48:03'),
(250, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:48:35'),
(251, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:48:37'),
(252, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:48:41'),
(253, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:48:48'),
(254, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"23-2321-423\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"Japhet Bongbong\",\"position\":\"Admin\"}}}', '2026-05-12 09:49:24'),
(255, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:54:43'),
(256, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"98-8887-444\"}}', '2026-05-12 09:54:45'),
(257, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:56:23'),
(258, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-05-12 09:57:58'),
(259, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"38-1029-129\",\"full_name\":\"Brian Perez\",\"year_level\":2,\"section\":\"A\"},\"timestamp\":1778876151}', '2026-05-15 20:15:51'),
(260, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"46-1037-137\",\"full_name\":\"Jacob Yap\",\"year_level\":2,\"section\":\"A\"},\"timestamp\":1778995406}', '2026-05-17 05:23:26');

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
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `officer_id` (`officer_id`);

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
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attendance_schedule`
--
ALTER TABLE `attendance_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `event_fines`
--
ALTER TABLE `event_fines`
  MODIFY `fine_setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `student_fines`
--
ALTER TABLE `student_fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4163;

--
-- AUTO_INCREMENT for table `websocket_messages`
--
ALTER TABLE `websocket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=261;

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
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`officer_id`) REFERENCES `officers` (`officer_id`) ON DELETE CASCADE;

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
