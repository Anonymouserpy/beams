-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 28, 2026 at 10:24 AM
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
('11-1111-111', 'asdasdasdsad', '$2y$10$05PSTJOg/cbFpltFX0QlsOAjmk20j873pknf.9Q.bSJrrlxPot2t2', 'Admin', '2026-03-28 08:16:26'),
('11-1123-123', 'sadfaf', '$2y$10$c.UT2Xe6IpSscrR/Vc/sKO9QjWPHLQLfe492ZV5gzgugPP82/26D.', 'Officer', '2026-03-28 08:22:54'),
('11-2314-123', 'asdfsadf', '$2y$10$7to9shZiWB/u0mrr7Rb6OecErSu95xKznCmHKDUMQAPianb741gkK', 'Admin', '2026-03-28 07:48:39'),
('11-3333-332', 'asdasdasdsad', '$2y$10$8DSBQCBxFxw6grDMHHY47OYfj4Hh7MQKy./d7DXlNihnU7RArB8We', 'Admin', '2026-03-28 08:17:15'),
('11-3333-333', 'asdasdasdsad', '$2y$10$QwvIBeJQ2tQrK8NZYsro2eaz8xC3wPkL.ldYqWABSddees8b2roPW', 'Admin', '2026-03-28 08:16:51'),
('11-3334-223', 'sadfsadf', '$2y$10$PNehbPtL6y89R1SyuyVbIelMDCg4GDIAZIuRTfOfl.jIU2hO7DaNG', 'Officer', '2026-03-28 08:25:41'),
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
('00-1091-191', 'Logan Agbayani', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('01-1092-192', 'Alan Basco', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('10-1001-101', 'Juan Dela Cruz', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('11-1002-102', 'Maria Santos', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('11-1111-111', '213123123123213', '$2y$10$W5o4sJ0XofhZApqw6/8MiukBYqfOpmLygclsqfn0nfAhxn0r4l/Ti', 2, 'B', '2026-03-28 08:10:29'),
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
('27-1018-118', 'Robert Dimagiba', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('28-1019-119', 'Patricia Estrada', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('29-1020-120', 'Jennifer Flores', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('30-1021-121', 'Charles Gutierrez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('31-1022-122', 'Daniel Hernandez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('32-1023-123', 'Matthew Ignacio', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('33-1024-124', 'Elizabeth Jimenez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('34-1025-125', 'Christopher Luna', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
('35-1026-126', 'Joshua Marquez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 2, 'B', '2026-03-28 09:14:42'),
('36-1027-127', 'Andrew Navarro', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 3, 'A', '2026-03-28 09:14:42'),
('37-1028-128', 'Kevin Ortega', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 4, 'B', '2026-03-28 09:14:42'),
('38-1029-129', 'Brian Perez', '$2y$10$wH8Kq8YzYk8zQ0Q1x3p1he8Z9mRk1J7Wv7lZzYk0kR1Zl8vZ7yT1G', 1, 'A', '2026-03-28 09:14:42'),
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
(141, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-28 09:23:50');

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
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `event_fines`
--
ALTER TABLE `event_fines`
  MODIFY `fine_setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fines`
--
ALTER TABLE `student_fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3136;

--
-- AUTO_INCREMENT for table `websocket_messages`
--
ALTER TABLE `websocket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

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
