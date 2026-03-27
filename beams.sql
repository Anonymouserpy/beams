-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2026 at 05:09 PM
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
('01-0001-001', 'James Smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('01-0001-002', 'Mary Johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('01-0001-003', 'John Williams', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('01-0001-004', 'Patricia Brown', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('01-0001-005', 'Robert Jones', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('01-0001-006', 'Jennifer Garcia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('01-0001-007', 'Michael Miller', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('01-0001-008', 'Linda Davis', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('01-0001-009', 'William Rodriguez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('01-0001-010', 'Elizabeth Martinez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('02-0002-011', 'David Hernandez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('02-0002-012', 'Barbara Lopez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('02-0002-013', 'Richard Gonzalez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('02-0002-014', 'Susan Wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('02-0002-015', 'Joseph Anderson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('02-0002-016', 'Jessica Thomas', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('02-0002-017', 'Thomas Taylor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('02-0002-018', 'Sarah Moore', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('02-0002-019', 'Charles Jackson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('02-0002-020', 'Karen Martin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('03-0003-021', 'Christopher Lee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('03-0003-022', 'Nancy Perez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('03-0003-023', 'Daniel Thompson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('03-0003-024', 'Lisa White', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('03-0003-025', 'Matthew Harris', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('03-0003-026', 'Betty Sanchez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('03-0003-027', 'Anthony Clark', '$2y$10$ApL0fg.uc1R.AC9hi1cz7Oao/ZIKPHmCnVC7qmeXJvaTJcmOuiE1C', 1, 'A', '2026-03-27 16:06:26'),
('03-0003-028', 'Helen Ramirez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('03-0003-029', 'Mark Lewis', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('03-0003-030', 'Sandra Robinson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('04-0004-031', 'Donald Walker', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('04-0004-032', 'Donna Young', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('04-0004-033', 'Steven Allen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('04-0004-034', 'Carol King', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('04-0004-035', 'Paul Wright', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('04-0004-036', 'Ruth Scott', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('04-0004-037', 'Andrew Torres', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('04-0004-038', 'Sharon Nguyen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('04-0004-039', 'Joshua Hill', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('04-0004-040', 'Michelle Flores', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('05-0005-041', 'Kenneth Green', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('05-0005-042', 'Laura Adams', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('05-0005-043', 'Kevin Nelson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('05-0005-044', 'Sarah Baker', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('05-0005-045', 'Brian Hall', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('05-0005-046', 'Kimberly Rivera', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('05-0005-047', 'George Campbell', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('05-0005-048', 'Deborah Mitchell', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('05-0005-049', 'Edward Carter', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('05-0005-050', 'Linda Roberts', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('06-0006-051', 'Jason Phillips', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('06-0006-052', 'Megan Evans', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('06-0006-053', 'Ronald Turner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('06-0006-054', 'Ashley Parker', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('06-0006-055', 'Frank Collins', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('06-0006-056', 'Emily Edwards', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('06-0006-057', 'Raymond Stewart', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('06-0006-058', 'Amanda Morris', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('06-0006-059', 'Jerry Rogers', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('06-0006-060', 'Melissa Reed', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('07-0007-061', 'Dennis Cook', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('07-0007-062', 'Stephanie Morgan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('07-0007-063', 'Larry Bell', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('07-0007-064', 'Christine Murphy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('07-0007-065', 'Peter Bailey', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('07-0007-066', 'Kathleen Rivera', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('07-0007-067', 'Walter Cooper', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('07-0007-068', 'Rebecca Richardson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('07-0007-069', 'Harold Cox', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('07-0007-070', 'Shirley Howard', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('08-0008-071', 'Ralph Ward', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('08-0008-072', 'Judy Torres', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('08-0008-073', 'Roy Peterson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('08-0008-074', 'Diane Gray', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('08-0008-075', 'Billy Ramirez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('08-0008-076', 'Martha James', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('08-0008-077', 'Joe Watson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('08-0008-078', 'Julie Brooks', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('08-0008-079', 'Carl Kelly', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('08-0008-080', 'Heather Sanders', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('09-0009-081', 'Albert Price', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('09-0009-082', 'Frances Bennett', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('09-0009-083', 'Wayne Wood', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('09-0009-084', 'Joyce Barnes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('09-0009-085', 'Eugene Ross', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('09-0009-086', 'Gloria Henderson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('09-0009-087', 'Randy Coleman', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('09-0009-088', 'Janet Jenkins', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('09-0009-089', 'Louis Perry', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('09-0009-090', 'Catherine Powell', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('10-0010-091', 'Bruce Long', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('10-0010-092', 'Doris Patterson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('10-0010-093', 'Russell Hughes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('10-0010-094', 'Ann Flores', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('10-0010-095', 'Gerald Washington', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('10-0010-096', 'Katherine Butler', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26'),
('10-0010-097', 'Lawrence Simmons', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'A', '2026-03-27 16:06:26'),
('10-0010-098', 'Virginia Foster', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'B', '2026-03-27 16:06:26'),
('10-0010-099', 'Jeffrey Gonzales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'A', '2026-03-27 16:06:26'),
('10-0010-100', 'Carolyn Bryant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'B', '2026-03-27 16:06:26');

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
(95, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"03-0003-027\"}}', '2026-03-27 16:09:08');

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
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attendance_schedule`
--
ALTER TABLE `attendance_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `event_fines`
--
ALTER TABLE `event_fines`
  MODIFY `fine_setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fines`
--
ALTER TABLE `student_fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2769;

--
-- AUTO_INCREMENT for table `websocket_messages`
--
ALTER TABLE `websocket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

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
