-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 10, 2026 at 08:15 PM
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
(80, '123123123', '2026-03-28', 'whole_day', NULL, '123123', '123123', '11', '2026-03-28 14:32:50', 0),
(81, 'asdfsadfsdf', '2026-03-28', 'whole_day', NULL, '123123123', '123123', '24', '2026-03-28 15:44:06', 0);

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
(70, 81, 5.00, 5.00, 5.00, 5.00);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`officer_id`, `full_name`, `password`, `position`, `created_at`) VALUES
('11-1111-111', 'asdasdasdsad', '$2y$10$hCIFkf4fYxOPMZYnsP5X7O0I8vdILeNz.APnb7n.cfbCwq2wuKG0m', 'Admin', '2026-03-28 08:16:26'),
('11-2314-123', 'asdfsadf', '$2y$10$7to9shZiWB/u0mrr7Rb6OecErSu95xKznCmHKDUMQAPianb741gkK', 'Admin', '2026-03-28 07:48:39'),
('11-3333-333', 'asdasdasdsad', '$2y$10$QwvIBeJQ2tQrK8NZYsro2eaz8xC3wPkL.ldYqWABSddees8b2roPW', 'Admin', '2026-03-28 08:16:51'),
('23-2321-123', 'asdfsadf', '$2y$10$JzNGcXyuHqx/N3Pzq7ID9e6okuHRpeI.abwPzNzRnp.vgyWrLs8i2', 'Officer', '2026-03-28 15:45:09'),
('23-2321-423', 'Japhet Bongbong', '$2y$10$NUcT8NUaNECiWbN3oDh8mOjkncqQsajbebfqZAnpvgM.mFIJxL/cO', 'Officer', '2026-03-11 17:07:23'),
('24-0187-667', 'Jomarie M, Alcaria', '$2y$10$Wma6TW4lXKnY7eD3mijJP.Kf1I7QDzF2phMD9ttAPd5QcMT7h1LMu', 'Officer', '2026-03-11 17:00:20'),
('24-5454-222', 'Jomarie M, Alcaria', '$2y$10$y1f9yT4YA8v8P4G.mrF8Re3GyL6asQ4fHrY7YMp7RuQpP1CXl2puq', 'Admin', '2026-03-11 16:56:57'),
('55-5555-888', 'Admin', '$2y$10$Y/BZrhDbEywibQ7.ucfGkOnBkXZ07kOiRbYK/L8.c1wFMhMQyg3tu', 'Admin', '2026-05-10 17:31:41');

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
('11-3213-412', '12323123', '$2y$10$jxfvuhlkNC6eecLDUkKxC.ry3H1cTN2OQr9x4V.xeTcYE4Sp/m8d6', 2, 'A', '2026-03-28 12:02:20'),
('11-3231-321', 'asdfasdf', '$2y$10$9M/mxXu2dR4Rv56Fp.o.y.3DHkp4S3JOrBr6tcoeAJQh/CfR63xJ2', 2, 'B', '2026-03-28 14:37:32'),
('12-1003-103', 'Jose Reyes', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('12-3212-321', 'asdfsadf', '$2y$10$72ePjw.gb.m8P1XjqsJHd.MfVet5D2EpmXSjf2HCS1EmTZjE4Farm', 2, 'B', '2026-03-28 09:23:17'),
('12-3213-546', 'ian sonio', '$2y$10$zBMtVyOAxkgsCuDNEqdoaOIshSEs6.McBx4fPdzI9Zj/f99rxAR5C', 2, 'B', '2026-03-28 09:21:06'),
('12-3231-123', 'Grace Rojas', '$2y$10$kpOFZ3/JTXihfujKl3vFr.ZA1qS8fm9ONgDR8aGIKqpePM4ITaX4i', 3, 'A', '2026-03-28 09:22:52'),
('12-3231-412', 'Jomare', '$2y$10$5Qmv9/NAMl/8di0eZDRrruK.gDMPxBHL4ywLNRcpeg9tHSqhBhbdK', 1, 'B', '2026-03-28 14:12:56'),
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
('27-1018-118', 'Robert Dimagiba', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('28-1019-119', 'Patricia Estrada', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('29-1020-120', 'Jennifer Flores', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('30-1021-121', 'Charles Gutierrez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'A', '2026-03-28 09:14:42'),
('31-1022-122', 'Daniel Hernandez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('32-1023-123', 'Matthew Ignacio', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('33-1024-124', 'Elizabeth Jimenez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('34-1025-125', 'Christopher Luna', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('35-1026-126', 'Joshua Marquez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('36-1027-127', 'Andrew Navarro', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('37-1028-128', 'Kevin Ortega', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('39-1030-130', 'George Quiambao', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('40-1031-131', 'Edward Ramirez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('41-1032-132', 'Ronald Soriano', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('42-1033-133', 'Timothy Tan', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('43-1034-134', 'Jason Uy', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('44-1035-135', 'Jeffrey Valdez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('45-1036-136', 'Ryan Villanueva', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('46-1037-137', 'Jacob Yap', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
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
('88-8888-777', 'wael', '$2y$10$Qcq/dUvOjUO09Pwa6RNSX.btbKCZ9btHVtMfIfr5kDBYYABCZyIGC', 2, 'A', '2026-05-10 17:34:00'),
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
(3136, '00-1091-191', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3137, '01-1092-192', 80, 'Missing AM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3138, '10-1001-101', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3139, '11-1002-102', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3140, '11-1111-111', 80, 'Missing AM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3141, '11-3213-412', 80, 'Missing AM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3142, '12-1003-103', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3143, '12-3212-321', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3144, '12-3213-546', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3145, '12-3231-123', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3146, '12-3231-412', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3147, '12-3232-444', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3148, '13-1004-104', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3149, '14-1005-105', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3150, '15-1006-106', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3151, '16-1007-107', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3152, '17-1008-108', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3153, '18-1009-109', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3154, '19-1010-110', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3155, '20-1011-111', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3156, '21-1012-112', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3157, '21-3212-321', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3158, '22-1013-113', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3159, '23-1014-114', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3160, '24-0154-877', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3161, '24-0187-667', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3162, '24-1015-115', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3163, '25-1016-116', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3164, '26-1017-117', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3165, '27-1018-118', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3166, '28-1019-119', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3167, '29-1020-120', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3168, '30-1021-121', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3169, '31-1022-122', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3170, '32-1023-123', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3171, '33-1024-124', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3172, '34-1025-125', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3173, '35-1026-126', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3174, '36-1027-127', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3175, '37-1028-128', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3177, '39-1030-130', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3178, '40-1031-131', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3179, '41-1032-132', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3180, '42-1033-133', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3181, '43-1034-134', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3182, '44-1035-135', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3183, '45-1036-136', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3184, '46-1037-137', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3185, '47-1038-138', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3186, '48-1039-139', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3187, '49-1040-140', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3188, '50-1041-141', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3189, '51-1042-142', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3190, '52-1043-143', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3191, '53-1044-144', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3192, '54-1045-145', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3193, '55-1046-146', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3194, '56-1047-147', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3195, '57-1048-148', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3196, '58-1049-149', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3197, '59-1050-150', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3198, '60-1051-151', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3199, '61-1052-152', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3200, '62-1053-153', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3201, '63-1054-154', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3202, '64-1055-155', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3203, '65-1056-156', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3204, '66-1057-157', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3205, '67-1058-158', 80, 'Missing AM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3206, '68-1059-159', 80, 'Missing AM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3207, '69-1060-160', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3208, '70-1061-161', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3209, '71-1062-162', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3210, '72-1063-163', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3211, '73-1064-164', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3212, '74-1065-165', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3213, '75-1066-166', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3214, '76-1067-167', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3215, '77-1068-168', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3216, '78-1069-169', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3217, '79-1070-170', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3218, '80-1071-171', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3219, '81-1072-172', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3220, '82-1073-173', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3221, '83-1074-174', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3222, '84-1075-175', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3223, '85-1076-176', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3224, '86-1077-177', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3225, '87-1078-178', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3226, '88-1079-179', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3227, '89-1080-180', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3228, '90-1081-181', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3229, '91-1082-182', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3230, '92-1083-183', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3231, '93-1084-184', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3232, '94-1085-185', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3233, '95-1086-186', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3234, '96-1087-187', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3235, '97-1088-188', 80, 'Missing AM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3236, '98-1089-189', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3237, '99-1090-190', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3263, '00-1091-191', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3264, '01-1092-192', 80, 'Missing AM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3265, '10-1001-101', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3266, '11-1002-102', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3267, '11-1111-111', 80, 'Missing AM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3268, '11-3213-412', 80, 'Missing AM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3269, '12-1003-103', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3270, '12-3212-321', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3271, '12-3213-546', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3272, '12-3231-123', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3273, '12-3231-412', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3274, '12-3232-444', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3275, '13-1004-104', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3276, '14-1005-105', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3277, '15-1006-106', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3278, '16-1007-107', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3279, '17-1008-108', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3280, '18-1009-109', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3281, '19-1010-110', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3282, '20-1011-111', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3283, '21-1012-112', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3284, '21-3212-321', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3285, '22-1013-113', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3286, '23-1014-114', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3287, '24-0154-877', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3288, '24-0187-667', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3289, '24-1015-115', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3290, '25-1016-116', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3291, '26-1017-117', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3292, '27-1018-118', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3293, '28-1019-119', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3294, '29-1020-120', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3295, '30-1021-121', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3296, '31-1022-122', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3297, '32-1023-123', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3298, '33-1024-124', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3299, '34-1025-125', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3300, '35-1026-126', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3301, '36-1027-127', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3302, '37-1028-128', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3304, '39-1030-130', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3305, '40-1031-131', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3306, '41-1032-132', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3307, '42-1033-133', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3308, '43-1034-134', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3309, '44-1035-135', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3310, '45-1036-136', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3311, '46-1037-137', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3312, '47-1038-138', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3313, '48-1039-139', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3314, '49-1040-140', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3315, '50-1041-141', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3316, '51-1042-142', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3317, '52-1043-143', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3318, '53-1044-144', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3319, '54-1045-145', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3320, '55-1046-146', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3321, '56-1047-147', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3322, '57-1048-148', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3323, '58-1049-149', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3324, '59-1050-150', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3325, '60-1051-151', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3326, '61-1052-152', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3327, '62-1053-153', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3328, '63-1054-154', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3329, '64-1055-155', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3330, '65-1056-156', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3331, '66-1057-157', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3332, '67-1058-158', 80, 'Missing AM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3333, '68-1059-159', 80, 'Missing AM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3334, '69-1060-160', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3335, '70-1061-161', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3336, '71-1062-162', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3337, '72-1063-163', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3338, '73-1064-164', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3339, '74-1065-165', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3340, '75-1066-166', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3341, '76-1067-167', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3342, '77-1068-168', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3343, '78-1069-169', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3344, '79-1070-170', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3345, '80-1071-171', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3346, '81-1072-172', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3347, '82-1073-173', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3348, '83-1074-174', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3349, '84-1075-175', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3350, '85-1076-176', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3351, '86-1077-177', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3352, '87-1078-178', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3353, '88-1079-179', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3354, '89-1080-180', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3355, '90-1081-181', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3356, '91-1082-182', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3357, '92-1083-183', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3358, '93-1084-184', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3359, '94-1085-185', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3360, '95-1086-186', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3361, '96-1087-187', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3362, '97-1088-188', 80, 'Missing AM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3363, '98-1089-189', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3364, '99-1090-190', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3390, '00-1091-191', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3391, '01-1092-192', 80, 'Missing PM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3392, '10-1001-101', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3393, '11-1002-102', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3394, '11-1111-111', 80, 'Missing PM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3395, '11-3213-412', 80, 'Missing PM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3396, '12-1003-103', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3397, '12-3212-321', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3398, '12-3213-546', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3399, '12-3231-123', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3400, '12-3231-412', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3401, '12-3232-444', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3402, '13-1004-104', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3403, '14-1005-105', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3404, '15-1006-106', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3405, '16-1007-107', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3406, '17-1008-108', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3407, '18-1009-109', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3408, '19-1010-110', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3409, '20-1011-111', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3410, '21-1012-112', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3411, '21-3212-321', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3412, '22-1013-113', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3413, '23-1014-114', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3414, '24-0154-877', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3415, '24-0187-667', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3416, '24-1015-115', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3417, '25-1016-116', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3418, '26-1017-117', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3419, '27-1018-118', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3420, '28-1019-119', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3421, '29-1020-120', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3422, '30-1021-121', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3423, '31-1022-122', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3424, '32-1023-123', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3425, '33-1024-124', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3426, '34-1025-125', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3427, '35-1026-126', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3428, '36-1027-127', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3429, '37-1028-128', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3431, '39-1030-130', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3432, '40-1031-131', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3433, '41-1032-132', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3434, '42-1033-133', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3435, '43-1034-134', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3436, '44-1035-135', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3437, '45-1036-136', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3438, '46-1037-137', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3439, '47-1038-138', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3440, '48-1039-139', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3441, '49-1040-140', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3442, '50-1041-141', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3443, '51-1042-142', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3444, '52-1043-143', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3445, '53-1044-144', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3446, '54-1045-145', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3447, '55-1046-146', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3448, '56-1047-147', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3449, '57-1048-148', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3450, '58-1049-149', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3451, '59-1050-150', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3452, '60-1051-151', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3453, '61-1052-152', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3454, '62-1053-153', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3455, '63-1054-154', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3456, '64-1055-155', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3457, '65-1056-156', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3458, '66-1057-157', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3459, '67-1058-158', 80, 'Missing PM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3460, '68-1059-159', 80, 'Missing PM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3461, '69-1060-160', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3462, '70-1061-161', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3463, '71-1062-162', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3464, '72-1063-163', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3465, '73-1064-164', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3466, '74-1065-165', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3467, '75-1066-166', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3468, '76-1067-167', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3469, '77-1068-168', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3470, '78-1069-169', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3471, '79-1070-170', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3472, '80-1071-171', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3473, '81-1072-172', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3474, '82-1073-173', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3475, '83-1074-174', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3476, '84-1075-175', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3477, '85-1076-176', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3478, '86-1077-177', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3479, '87-1078-178', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3480, '88-1079-179', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3481, '89-1080-180', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3482, '90-1081-181', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3483, '91-1082-182', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3484, '92-1083-183', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3485, '93-1084-184', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3486, '94-1085-185', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3487, '95-1086-186', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3488, '96-1087-187', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3489, '97-1088-188', 80, 'Missing PM login', 5.00, 'paid', '2026-03-28 14:32:59'),
(3490, '98-1089-189', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3491, '99-1090-190', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3517, '00-1091-191', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3518, '01-1092-192', 80, 'Missing PM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3519, '10-1001-101', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3520, '11-1002-102', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3521, '11-1111-111', 80, 'Missing PM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3522, '11-3213-412', 80, 'Missing PM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3523, '12-1003-103', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3524, '12-3212-321', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3525, '12-3213-546', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3526, '12-3231-123', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3527, '12-3231-412', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3528, '12-3232-444', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3529, '13-1004-104', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3530, '14-1005-105', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3531, '15-1006-106', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3532, '16-1007-107', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3533, '17-1008-108', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3534, '18-1009-109', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3535, '19-1010-110', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3536, '20-1011-111', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3537, '21-1012-112', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3538, '21-3212-321', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3539, '22-1013-113', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3540, '23-1014-114', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3541, '24-0154-877', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3542, '24-0187-667', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3543, '24-1015-115', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3544, '25-1016-116', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3545, '26-1017-117', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3546, '27-1018-118', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3547, '28-1019-119', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3548, '29-1020-120', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3549, '30-1021-121', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3550, '31-1022-122', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3551, '32-1023-123', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3552, '33-1024-124', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3553, '34-1025-125', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3554, '35-1026-126', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3555, '36-1027-127', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3556, '37-1028-128', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3558, '39-1030-130', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3559, '40-1031-131', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3560, '41-1032-132', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3561, '42-1033-133', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3562, '43-1034-134', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3563, '44-1035-135', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3564, '45-1036-136', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3565, '46-1037-137', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3566, '47-1038-138', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3567, '48-1039-139', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3568, '49-1040-140', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3569, '50-1041-141', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3570, '51-1042-142', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3571, '52-1043-143', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3572, '53-1044-144', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3573, '54-1045-145', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3574, '55-1046-146', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3575, '56-1047-147', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3576, '57-1048-148', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3577, '58-1049-149', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3578, '59-1050-150', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3579, '60-1051-151', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3580, '61-1052-152', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3581, '62-1053-153', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3582, '63-1054-154', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3583, '64-1055-155', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3584, '65-1056-156', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3585, '66-1057-157', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3586, '67-1058-158', 80, 'Missing PM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3587, '68-1059-159', 80, 'Missing PM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3588, '69-1060-160', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3589, '70-1061-161', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3590, '71-1062-162', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3591, '72-1063-163', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3592, '73-1064-164', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3593, '74-1065-165', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3594, '75-1066-166', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3595, '76-1067-167', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3596, '77-1068-168', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3597, '78-1069-169', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3598, '79-1070-170', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3599, '80-1071-171', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3600, '81-1072-172', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3601, '82-1073-173', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3602, '83-1074-174', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3603, '84-1075-175', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3604, '85-1076-176', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3605, '86-1077-177', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3606, '87-1078-178', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3607, '88-1079-179', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3608, '89-1080-180', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3609, '90-1081-181', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3610, '91-1082-182', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3611, '92-1083-183', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3612, '93-1084-184', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3613, '94-1085-185', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3614, '95-1086-186', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3615, '96-1087-187', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3616, '97-1088-188', 80, 'Missing PM logout', 5.00, 'paid', '2026-03-28 14:32:59'),
(3617, '98-1089-189', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3618, '99-1090-190', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:32:59'),
(3644, '11-3231-321', 80, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 14:37:35'),
(3645, '11-3231-321', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 14:37:35'),
(3646, '11-3231-321', 80, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 14:37:35'),
(3647, '11-3231-321', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 14:37:35'),
(3648, '00-1091-191', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3649, '01-1092-192', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3650, '10-1001-101', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3651, '11-1002-102', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3652, '11-1111-111', 81, 'Missing AM login', 5.00, 'paid', '2026-03-28 15:44:10'),
(3653, '11-3213-412', 81, 'Missing AM login', 5.00, 'paid', '2026-03-28 15:44:10'),
(3654, '11-3231-321', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3655, '12-1003-103', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3656, '12-3212-321', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3657, '12-3213-546', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3658, '12-3231-123', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3659, '12-3231-412', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3660, '12-3232-444', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3661, '13-1004-104', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3662, '14-1005-105', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3663, '15-1006-106', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3664, '16-1007-107', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3665, '17-1008-108', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3666, '18-1009-109', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3667, '19-1010-110', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3668, '20-1011-111', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3669, '21-1012-112', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3670, '21-3212-321', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3671, '22-1013-113', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3672, '23-1014-114', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3673, '24-0154-877', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3674, '24-0187-667', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3675, '24-1015-115', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3676, '25-1016-116', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3677, '26-1017-117', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3678, '27-1018-118', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3679, '28-1019-119', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3680, '29-1020-120', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3681, '30-1021-121', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3682, '31-1022-122', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3683, '32-1023-123', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3684, '33-1024-124', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3685, '34-1025-125', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3686, '35-1026-126', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3687, '36-1027-127', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3688, '37-1028-128', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3689, '39-1030-130', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3690, '40-1031-131', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3691, '41-1032-132', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3692, '42-1033-133', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3693, '43-1034-134', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3694, '44-1035-135', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3695, '45-1036-136', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3696, '46-1037-137', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3697, '47-1038-138', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3698, '48-1039-139', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3699, '49-1040-140', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3700, '50-1041-141', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3701, '51-1042-142', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3702, '52-1043-143', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3703, '53-1044-144', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3704, '54-1045-145', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3705, '55-1046-146', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3706, '56-1047-147', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3707, '57-1048-148', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3708, '58-1049-149', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3709, '59-1050-150', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3710, '60-1051-151', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3711, '61-1052-152', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3712, '62-1053-153', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3713, '63-1054-154', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3714, '64-1055-155', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3715, '65-1056-156', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3716, '66-1057-157', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3717, '67-1058-158', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3718, '68-1059-159', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3719, '69-1060-160', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3720, '70-1061-161', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3721, '71-1062-162', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3722, '72-1063-163', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3723, '73-1064-164', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3724, '74-1065-165', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3725, '75-1066-166', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3726, '76-1067-167', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3727, '77-1068-168', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3728, '78-1069-169', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3729, '79-1070-170', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3730, '80-1071-171', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3731, '81-1072-172', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3732, '82-1073-173', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3733, '83-1074-174', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3734, '84-1075-175', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3735, '85-1076-176', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3736, '86-1077-177', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3737, '87-1078-178', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3738, '88-1079-179', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3739, '89-1080-180', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3740, '90-1081-181', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3741, '91-1082-182', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3742, '92-1083-183', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3743, '93-1084-184', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3744, '94-1085-185', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3745, '95-1086-186', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3746, '96-1087-187', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3747, '97-1088-188', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3748, '98-1089-189', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3749, '99-1090-190', 81, 'Missing AM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3775, '00-1091-191', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3776, '01-1092-192', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3777, '10-1001-101', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3778, '11-1002-102', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3779, '11-1111-111', 81, 'Missing AM logout', 5.00, 'paid', '2026-03-28 15:44:10'),
(3780, '11-3213-412', 81, 'Missing AM logout', 5.00, 'paid', '2026-03-28 15:44:10'),
(3781, '11-3231-321', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3782, '12-1003-103', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3783, '12-3212-321', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3784, '12-3213-546', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3785, '12-3231-123', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3786, '12-3231-412', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3787, '12-3232-444', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3788, '13-1004-104', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3789, '14-1005-105', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3790, '15-1006-106', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3791, '16-1007-107', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3792, '17-1008-108', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3793, '18-1009-109', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3794, '19-1010-110', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3795, '20-1011-111', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3796, '21-1012-112', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3797, '21-3212-321', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3798, '22-1013-113', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3799, '23-1014-114', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3800, '24-0154-877', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3801, '24-0187-667', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3802, '24-1015-115', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3803, '25-1016-116', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3804, '26-1017-117', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3805, '27-1018-118', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3806, '28-1019-119', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3807, '29-1020-120', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3808, '30-1021-121', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3809, '31-1022-122', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3810, '32-1023-123', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3811, '33-1024-124', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3812, '34-1025-125', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3813, '35-1026-126', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3814, '36-1027-127', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3815, '37-1028-128', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3816, '39-1030-130', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3817, '40-1031-131', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3818, '41-1032-132', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3819, '42-1033-133', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3820, '43-1034-134', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3821, '44-1035-135', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3822, '45-1036-136', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3823, '46-1037-137', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3824, '47-1038-138', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3825, '48-1039-139', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3826, '49-1040-140', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3827, '50-1041-141', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3828, '51-1042-142', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3829, '52-1043-143', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3830, '53-1044-144', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3831, '54-1045-145', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3832, '55-1046-146', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3833, '56-1047-147', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3834, '57-1048-148', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3835, '58-1049-149', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3836, '59-1050-150', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3837, '60-1051-151', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3838, '61-1052-152', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3839, '62-1053-153', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3840, '63-1054-154', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3841, '64-1055-155', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3842, '65-1056-156', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3843, '66-1057-157', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3844, '67-1058-158', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3845, '68-1059-159', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3846, '69-1060-160', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3847, '70-1061-161', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3848, '71-1062-162', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3849, '72-1063-163', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3850, '73-1064-164', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3851, '74-1065-165', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3852, '75-1066-166', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3853, '76-1067-167', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3854, '77-1068-168', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3855, '78-1069-169', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10');
INSERT INTO `student_fines` (`fine_id`, `student_id`, `event_id`, `fine_reason`, `amount`, `status`, `recorded_at`) VALUES
(3856, '79-1070-170', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3857, '80-1071-171', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3858, '81-1072-172', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3859, '82-1073-173', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3860, '83-1074-174', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3861, '84-1075-175', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3862, '85-1076-176', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3863, '86-1077-177', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3864, '87-1078-178', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3865, '88-1079-179', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3866, '89-1080-180', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3867, '90-1081-181', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3868, '91-1082-182', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3869, '92-1083-183', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3870, '93-1084-184', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3871, '94-1085-185', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3872, '95-1086-186', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3873, '96-1087-187', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3874, '97-1088-188', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3875, '98-1089-189', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3876, '99-1090-190', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3902, '00-1091-191', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3903, '01-1092-192', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3904, '10-1001-101', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3905, '11-1002-102', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3906, '11-1111-111', 81, 'Missing PM login', 5.00, 'paid', '2026-03-28 15:44:10'),
(3907, '11-3213-412', 81, 'Missing PM login', 5.00, 'paid', '2026-03-28 15:44:10'),
(3908, '11-3231-321', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3909, '12-1003-103', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3910, '12-3212-321', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3911, '12-3213-546', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3912, '12-3231-123', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3913, '12-3231-412', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3914, '12-3232-444', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3915, '13-1004-104', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3916, '14-1005-105', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3917, '15-1006-106', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3918, '16-1007-107', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3919, '17-1008-108', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3920, '18-1009-109', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3921, '19-1010-110', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3922, '20-1011-111', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3923, '21-1012-112', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3924, '21-3212-321', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3925, '22-1013-113', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3926, '23-1014-114', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3927, '24-0154-877', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3928, '24-0187-667', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3929, '24-1015-115', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3930, '25-1016-116', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3931, '26-1017-117', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3932, '27-1018-118', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3933, '28-1019-119', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3934, '29-1020-120', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3935, '30-1021-121', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3936, '31-1022-122', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3937, '32-1023-123', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3938, '33-1024-124', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3939, '34-1025-125', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3940, '35-1026-126', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3941, '36-1027-127', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3942, '37-1028-128', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3943, '39-1030-130', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3944, '40-1031-131', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3945, '41-1032-132', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3946, '42-1033-133', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3947, '43-1034-134', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3948, '44-1035-135', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3949, '45-1036-136', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3950, '46-1037-137', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3951, '47-1038-138', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3952, '48-1039-139', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3953, '49-1040-140', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3954, '50-1041-141', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3955, '51-1042-142', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3956, '52-1043-143', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3957, '53-1044-144', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3958, '54-1045-145', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3959, '55-1046-146', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3960, '56-1047-147', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3961, '57-1048-148', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3962, '58-1049-149', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3963, '59-1050-150', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3964, '60-1051-151', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3965, '61-1052-152', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3966, '62-1053-153', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3967, '63-1054-154', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3968, '64-1055-155', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3969, '65-1056-156', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3970, '66-1057-157', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3971, '67-1058-158', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3972, '68-1059-159', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3973, '69-1060-160', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3974, '70-1061-161', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3975, '71-1062-162', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3976, '72-1063-163', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3977, '73-1064-164', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3978, '74-1065-165', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3979, '75-1066-166', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3980, '76-1067-167', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3981, '77-1068-168', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3982, '78-1069-169', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3983, '79-1070-170', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3984, '80-1071-171', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3985, '81-1072-172', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3986, '82-1073-173', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3987, '83-1074-174', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3988, '84-1075-175', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3989, '85-1076-176', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3990, '86-1077-177', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3991, '87-1078-178', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3992, '88-1079-179', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3993, '89-1080-180', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3994, '90-1081-181', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3995, '91-1082-182', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3996, '92-1083-183', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3997, '93-1084-184', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3998, '94-1085-185', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(3999, '95-1086-186', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4000, '96-1087-187', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4001, '97-1088-188', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4002, '98-1089-189', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4003, '99-1090-190', 81, 'Missing PM login', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4029, '00-1091-191', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4030, '01-1092-192', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4031, '10-1001-101', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4032, '11-1002-102', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4033, '11-1111-111', 81, 'Missing PM logout', 5.00, 'paid', '2026-03-28 15:44:10'),
(4034, '11-3213-412', 81, 'Missing PM logout', 5.00, 'paid', '2026-03-28 15:44:10'),
(4035, '11-3231-321', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4036, '12-1003-103', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4037, '12-3212-321', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4038, '12-3213-546', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4039, '12-3231-123', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4040, '12-3231-412', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4041, '12-3232-444', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4042, '13-1004-104', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4043, '14-1005-105', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4044, '15-1006-106', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4045, '16-1007-107', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4046, '17-1008-108', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4047, '18-1009-109', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4048, '19-1010-110', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4049, '20-1011-111', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4050, '21-1012-112', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4051, '21-3212-321', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4052, '22-1013-113', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4053, '23-1014-114', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4054, '24-0154-877', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4055, '24-0187-667', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4056, '24-1015-115', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4057, '25-1016-116', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4058, '26-1017-117', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4059, '27-1018-118', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4060, '28-1019-119', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4061, '29-1020-120', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4062, '30-1021-121', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4063, '31-1022-122', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4064, '32-1023-123', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4065, '33-1024-124', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4066, '34-1025-125', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4067, '35-1026-126', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4068, '36-1027-127', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4069, '37-1028-128', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4070, '39-1030-130', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4071, '40-1031-131', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4072, '41-1032-132', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4073, '42-1033-133', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4074, '43-1034-134', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4075, '44-1035-135', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4076, '45-1036-136', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4077, '46-1037-137', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4078, '47-1038-138', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4079, '48-1039-139', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4080, '49-1040-140', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4081, '50-1041-141', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4082, '51-1042-142', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4083, '52-1043-143', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4084, '53-1044-144', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4085, '54-1045-145', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4086, '55-1046-146', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4087, '56-1047-147', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4088, '57-1048-148', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4089, '58-1049-149', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4090, '59-1050-150', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4091, '60-1051-151', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4092, '61-1052-152', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4093, '62-1053-153', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4094, '63-1054-154', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4095, '64-1055-155', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4096, '65-1056-156', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4097, '66-1057-157', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4098, '67-1058-158', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4099, '68-1059-159', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4100, '69-1060-160', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4101, '70-1061-161', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4102, '71-1062-162', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4103, '72-1063-163', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4104, '73-1064-164', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4105, '74-1065-165', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4106, '75-1066-166', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4107, '76-1067-167', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4108, '77-1068-168', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4109, '78-1069-169', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4110, '79-1070-170', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4111, '80-1071-171', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4112, '81-1072-172', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4113, '82-1073-173', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4114, '83-1074-174', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4115, '84-1075-175', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4116, '85-1076-176', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4117, '86-1077-177', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4118, '87-1078-178', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4119, '88-1079-179', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4120, '89-1080-180', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4121, '90-1081-181', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4122, '91-1082-182', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4123, '92-1083-183', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4124, '93-1084-184', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4125, '94-1085-185', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4126, '95-1086-186', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4127, '96-1087-187', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4128, '97-1088-188', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4129, '98-1089-189', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4130, '99-1090-190', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-03-28 15:44:10'),
(4156, '88-8888-777', 80, 'Missing AM login', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4157, '88-8888-777', 81, 'Missing AM login', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4159, '88-8888-777', 80, 'Missing AM logout', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4160, '88-8888-777', 81, 'Missing AM logout', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4162, '88-8888-777', 80, 'Missing PM login', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4163, '88-8888-777', 81, 'Missing PM login', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4165, '88-8888-777', 80, 'Missing PM logout', 5.00, 'unpaid', '2026-05-10 17:34:01'),
(4166, '88-8888-777', 81, 'Missing PM logout', 5.00, 'unpaid', '2026-05-10 17:34:01');

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
(142, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 11:22:53'),
(143, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 12:47:52'),
(144, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 12:50:15'),
(145, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 14:24:31'),
(146, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 14:33:00'),
(147, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 14:33:00'),
(148, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-3213-412\",\"count\":4},\"officer_id\":\"11-1111-111\"}', '2026-03-28 14:33:17'),
(149, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-1111-111\",\"count\":4},\"officer_id\":\"11-1111-111\"}', '2026-03-28 14:33:20'),
(150, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"67-1058-158\",\"count\":4},\"officer_id\":\"11-1111-111\"}', '2026-03-28 14:33:26'),
(151, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"68-1059-159\",\"count\":4},\"officer_id\":\"11-1111-111\"}', '2026-03-28 14:33:34'),
(152, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"01-1092-192\",\"count\":4},\"officer_id\":\"11-1111-111\"}', '2026-03-28 14:33:45'),
(153, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 14:35:15'),
(154, '{\"type\":\"student_deleted\",\"student_id\":\"38-1029-129\",\"timestamp\":1774708576}', '2026-03-28 14:36:16'),
(155, '{\"type\":\"student_deleted\",\"student_id\":\"38-1029-129\",\"timestamp\":1774708576}', '2026-03-28 14:36:16'),
(156, '{\"type\":\"student_created\",\"student\":{\"student_id\":\"11-3231-321\",\"full_name\":\"asdfasdf\",\"year_level\":2,\"section\":\"B\"},\"timestamp\":1774708652}', '2026-03-28 14:37:32'),
(157, '{\"type\":\"student_created\",\"student\":{\"student_id\":\"11-3231-321\",\"full_name\":\"asdfasdf\",\"year_level\":2,\"section\":\"B\"},\"timestamp\":1774708652}', '2026-03-28 14:37:32'),
(158, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-1123-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"sadfadasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:44:08'),
(159, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-1123-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"sadfadasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:44:11'),
(160, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-1123-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"sadfadasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:44:11'),
(161, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-1123-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"sadfadasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:44:11'),
(162, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-1123-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"sadfadasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:44:11'),
(163, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-1111-111\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 14:44:18'),
(164, '{\"type\":\"OFFICER_DELETED\",\"payload\":{\"officer_id\":\"11-1123-123\"}}', '2026-03-28 14:44:24'),
(165, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"full_name\":\"asdasdsadas\",\"position\":\"Officer\"}}', '2026-03-28 14:47:02'),
(166, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadas\",\"position\":\"Officer\"}}}', '2026-03-28 14:51:24'),
(167, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadas\",\"position\":\"Officer\"}}}', '2026-03-28 14:51:25'),
(168, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadas\",\"position\":\"Officer\"}}}', '2026-03-28 14:51:26'),
(169, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadas\",\"position\":\"Officer\"}}}', '2026-03-28 14:51:26'),
(170, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadas\",\"position\":\"Officer\"}}}', '2026-03-28 14:51:33'),
(171, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:10'),
(172, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:12'),
(173, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:12'),
(174, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:12'),
(175, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:12'),
(176, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:12'),
(177, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasd\",\"position\":\"Officer\"}}}', '2026-03-28 14:57:13'),
(178, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdsadasasasdasdsdfsadf\",\"position\":\"Officer\"}}}', '2026-03-28 15:00:46'),
(179, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:00:52'),
(180, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:00:52'),
(181, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:00:53'),
(182, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:00:53'),
(183, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:00:53'),
(184, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3334-223\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:00:53'),
(185, '{\"type\":\"OFFICER_DELETED\",\"payload\":{\"officer_id\":\"11-3334-223\"}}', '2026-03-28 15:00:58'),
(186, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asd123\",\"position\":\"Officer\"}}}', '2026-03-28 15:01:06'),
(187, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asd1231231233\",\"position\":\"Officer\"}}}', '2026-03-28 15:03:52'),
(188, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asd123123123312321\",\"position\":\"Officer\"}}}', '2026-03-28 15:07:30'),
(189, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asd1231\",\"position\":\"Officer\"}}}', '2026-03-28 15:07:42'),
(190, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"password\",\"value\":\"[RESET]\"}}', '2026-03-28 15:08:02'),
(191, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"11-3333-332\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asdasdasdsadasd\",\"position\":\"Admin\"}}}', '2026-03-28 15:14:02'),
(192, '{\"type\":\"OFFICER_DELETED\",\"payload\":{\"officer_id\":\"11-3333-332\"}}', '2026-03-28 15:14:18'),
(193, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:21:55'),
(194, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:22:13'),
(195, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:22:14'),
(196, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:22:16'),
(197, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:22:19'),
(198, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:22:22'),
(199, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:22:27'),
(200, '{\"type\":\"student_updated\",\"student_id\":\"24-0187-667\",\"timestamp\":1774711356}', '2026-03-28 15:22:36'),
(201, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 15:23:07'),
(202, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"97-1088-188\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-28 15:23:14'),
(203, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 15:23:25'),
(204, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 15:25:01'),
(205, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:42:56'),
(206, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:42:58'),
(207, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:42:59'),
(208, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:43:00'),
(209, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:43:00'),
(210, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:43:03'),
(211, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:43:03'),
(212, '{\"incoming\":{\"type\":\"subscribe\",\"student_id\":\"24-0187-667\"}}', '2026-03-28 15:43:06'),
(213, '{\"type\":\"student_updated\",\"student_id\":\"24-0187-667\",\"timestamp\":1774712592}', '2026-03-28 15:43:12'),
(214, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 15:43:29'),
(215, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"30-1021-121\",\"full_name\":\"Charles Gutierrez\",\"year_level\":2,\"section\":\"A\"},\"timestamp\":1774712619}', '2026-03-28 15:43:39'),
(216, '{\"type\":\"student_updated\",\"student\":{\"student_id\":\"30-1021-121\",\"full_name\":\"Charles Gutierrez\",\"year_level\":2,\"section\":\"A\"},\"timestamp\":1774712619}', '2026-03-28 15:43:39'),
(217, '{\"type\":\"EVENT_CREATED\",\"payload\":{\"event_id\":81,\"event_name\":\"asdfsadfsdf\",\"event_date\":\"2026-03-28\",\"event_type\":\"whole_day\",\"half_day_period\":null,\"description\":\"123123123\",\"location\":\"123123\",\"created_by\":\"24-5454-222\",\"am_login_start\":\"08:00\",\"am_login_end\":\"09:00\",\"am_logout_start\":\"12:00\",\"am_logout_end\":\"13:00\",\"pm_login_start\":\"13:00\",\"pm_login_end\":\"14:00\",\"pm_logout_start\":\"17:00\",\"pm_logout_end\":\"18:00\",\"miss_am_login\":5,\"miss_am_logout\":5,\"miss_pm_login\":5,\"miss_pm_logout\":5}}', '2026-03-28 15:44:06'),
(218, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 15:44:11'),
(219, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-3213-412\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-28 15:44:22'),
(220, '{\"action\":\"pay_all_unpaid\",\"details\":{\"student_id\":\"11-1111-111\",\"count\":4},\"officer_id\":\"24-5454-222\"}', '2026-03-28 15:44:31'),
(221, '{\"type\":\"OFFICER_UPDATED\",\"payload\":{\"officer_id\":\"22-3231-123\",\"field\":\"full_name_position\",\"value\":{\"full_name\":\"asd1231123123\",\"position\":\"Officer\"}}}', '2026-03-28 15:44:45'),
(222, '{\"type\":\"OFFICER_DELETED\",\"payload\":{\"officer_id\":\"22-3231-123\"}}', '2026-03-28 15:44:50'),
(223, '{\"type\":\"OFFICER_CREATED\",\"payload\":{\"officer_id\":\"23-2321-123\",\"full_name\":\"asdfsadf\",\"position\":\"Officer\"}}', '2026-03-28 15:45:09');

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
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

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
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fines`
--
ALTER TABLE `student_fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4168;

--
-- AUTO_INCREMENT for table `websocket_messages`
--
ALTER TABLE `websocket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=224;

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
