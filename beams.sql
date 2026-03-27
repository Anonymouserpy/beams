-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2026 at 04:45 PM
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
(67, 78, '08:00:00', '09:00:00', '12:00:00', '13:00:00', '13:00:00', '14:00:00', '17:00:00', '18:00:00');

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
(78, 'asdfasdf', '2026-03-27', 'whole_day', NULL, 'asdfsadf', 'asdfsadf', '24', '2026-03-27 15:42:35', 0);

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
(67, 78, 5.00, 5.00, 5.00, 5.00);

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
('23-1001-101', 'Juan Dela Cruz', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1002-102', 'Maria Santos', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1003-103', 'Pedro Reyes', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1004-104', 'Ana Lopez', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1005-105', 'Jose Garcia', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1006-106', 'Carla Mendoza', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1007-107', 'Mark Bautista', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1008-108', 'Angela Ramos', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1009-109', 'Paul Aquino', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1010-110', 'Liza Flores', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1011-111', 'Kevin Torres', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1012-112', 'Nina Castillo', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1013-113', 'Ryan Herrera', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1014-114', 'Joyce Navarro', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1015-115', 'Chris Villanueva', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1016-116', 'Ella Gomez', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1017-117', 'Daniel Cruz', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1018-118', 'Sophia Diaz', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1019-119', 'Joshua Perez', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1020-120', 'Mika Tan', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1021-121', 'Andre Lim', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1022-122', 'Patricia Ong', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1023-123', 'Brian Co', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1024-124', 'Catherine Sy', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1025-125', 'Adrian Go', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1026-126', 'Vanessa Yu', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1027-127', 'Leo Chua', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1028-128', 'Grace Ang', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1029-129', 'Noel Uy', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1030-130', 'Ivy Dy', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1031-131', 'Carl Abad', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1032-132', 'Jasmine Velasco', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1033-133', 'Rico Salazar', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1034-134', 'Paula Estrada', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1035-135', 'Miguel Dominguez', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1036-136', 'Kimberly Pineda', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1037-137', 'Allen Rosales', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1038-138', 'Bea Gutierrez', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1039-139', 'Oscar Delos Santos', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1040-140', 'Janine Mercado', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1041-141', 'Victor Alonzo', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1042-142', 'Sheila Cabrera', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1043-143', 'Dennis Fajardo', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1044-144', 'Rhea Soriano', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1045-145', 'Patrick Evangelista', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1046-146', 'Trisha Valdez', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1047-147', 'Harold Zamora', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1048-148', 'Camille Natividad', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1049-149', 'Gerald Padilla', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1050-150', 'Monica Robles', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1051-151', 'Edgar Galvez', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1052-152', 'Clarisse Malonzo', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1053-153', 'Alvin Sarmiento', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1054-154', 'Bianca Francisco', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1055-155', 'Ronald Dizon', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1056-156', 'Katrina Sevilla', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1057-157', 'Emmanuel Tolentino', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1058-158', 'Diane Vergara', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1059-159', 'Francis Arellano', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1060-160', 'Jessa Ballesteros', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1061-161', 'Marco Bustos', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1062-162', 'Shane Carreon', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1063-163', 'Arnold David', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1064-164', 'Lovely Enriquez', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1065-165', 'Gilbert Ferrer', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1066-166', 'Hazel Ignacio', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1067-167', 'Ivan Jimenez', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1068-168', 'Kristine Labra', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1069-169', 'Lawrence Manalo', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1070-170', 'Nico Ocampo', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1071-171', 'Oliver Quinto', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1072-172', 'Princess Reyes', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1073-173', 'Quincy Santos', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1074-174', 'Ralph Tadeo', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1075-175', 'Samuel Ubaldo', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1076-176', 'Therese Velez', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1077-177', 'Ulrich Wong', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1078-178', 'Vince Xavier', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1079-179', 'Wendy Yap', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1080-180', 'Xander Zulueta', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1081-181', 'Yuri Abella', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1082-182', 'Zoe Buenaventura', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1083-183', 'Aaron Cortez', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1084-184', 'Bella Duran', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1085-185', 'Cedric Estrella', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1086-186', 'Darla Fuentes', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1087-187', 'Ethan Gonzales', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1088-188', 'Faith Hidalgo', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1089-189', 'Gian Ibarra', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1090-190', 'Hannah Jacinto', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1091-191', 'Ian Katigbak', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1092-192', 'Jade Lacsamana', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1093-193', 'Karl Magno', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1094-194', 'Lara Nunez', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1095-195', 'Mason Orellana', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1096-196', 'Nadine Pascual', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('23-1097-197', 'Owen Quiambao', '482c811da5d5b4bc6d497ffa98491e38', 1, 'A', '2026-03-27 15:40:14'),
('23-1098-198', 'Paolo Ramos', '482c811da5d5b4bc6d497ffa98491e38', 2, 'B', '2026-03-27 15:40:14'),
('23-1099-199', 'Queenie Soriano', '482c811da5d5b4bc6d497ffa98491e38', 3, 'A', '2026-03-27 15:40:14'),
('23-1100-200', 'Trent Villareal', '482c811da5d5b4bc6d497ffa98491e38', 4, 'B', '2026-03-27 15:40:14'),
('24-0187-667', 'Jomarie Alcaria', '$2y$10$7sSuEMh1CxsGxFaMieE4H.0lOBv1bgrfPyGv7GZ6HvYeCqo0QCYX6', 2, 'B', '2026-03-13 09:48:19'),
('24-0235-659', 'asdfsadf', '$2y$10$1gQjdp63ALxpIIIig/QZh.o49o59OyeUyEVaXGKvliUySQqj3mQ8u', 2, 'B', '2026-03-13 17:01:21'),
('24-1548-653', '123', '$2y$10$ig8xlPhS1VundSKtmM4QjuFRl1HpSv3ZGUdhbNXG9PsldCjb/ehMK', 3, 'B', '2026-03-20 18:12:40'),
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
(2261, '23-1001-101', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2262, '23-1002-102', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2263, '23-1003-103', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2264, '23-1004-104', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2265, '23-1005-105', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2266, '23-1006-106', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2267, '23-1007-107', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2268, '23-1008-108', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2269, '23-1009-109', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2270, '23-1010-110', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2271, '23-1011-111', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2272, '23-1012-112', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2273, '23-1013-113', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2274, '23-1014-114', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2275, '23-1015-115', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2276, '23-1016-116', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2277, '23-1017-117', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2278, '23-1018-118', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2279, '23-1019-119', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2280, '23-1020-120', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2281, '23-1021-121', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2282, '23-1022-122', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2283, '23-1023-123', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2284, '23-1024-124', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2285, '23-1025-125', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2286, '23-1026-126', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2287, '23-1027-127', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2288, '23-1028-128', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2289, '23-1029-129', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2290, '23-1030-130', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2291, '23-1031-131', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2292, '23-1032-132', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2293, '23-1033-133', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2294, '23-1034-134', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2295, '23-1035-135', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2296, '23-1036-136', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2297, '23-1037-137', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2298, '23-1038-138', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2299, '23-1039-139', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2300, '23-1040-140', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2301, '23-1041-141', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2302, '23-1042-142', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2303, '23-1043-143', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2304, '23-1044-144', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2305, '23-1045-145', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2306, '23-1046-146', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2307, '23-1047-147', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2308, '23-1048-148', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2309, '23-1049-149', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2310, '23-1050-150', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2311, '23-1051-151', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2312, '23-1052-152', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2313, '23-1053-153', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2314, '23-1054-154', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2315, '23-1055-155', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2316, '23-1056-156', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2317, '23-1057-157', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2318, '23-1058-158', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2319, '23-1059-159', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2320, '23-1060-160', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2321, '23-1061-161', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2322, '23-1062-162', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2323, '23-1063-163', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2324, '23-1064-164', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2325, '23-1065-165', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2326, '23-1066-166', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2327, '23-1067-167', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2328, '23-1068-168', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2329, '23-1069-169', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2330, '23-1070-170', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2331, '23-1071-171', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2332, '23-1072-172', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2333, '23-1073-173', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2334, '23-1074-174', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2335, '23-1075-175', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2336, '23-1076-176', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2337, '23-1077-177', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2338, '23-1078-178', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2339, '23-1079-179', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2340, '23-1080-180', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2341, '23-1081-181', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2342, '23-1082-182', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2343, '23-1083-183', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2344, '23-1084-184', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2345, '23-1085-185', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2346, '23-1086-186', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2347, '23-1087-187', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2348, '23-1088-188', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2349, '23-1089-189', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2350, '23-1090-190', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2351, '23-1091-191', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2352, '23-1092-192', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2353, '23-1093-193', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2354, '23-1094-194', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2355, '23-1095-195', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2356, '23-1096-196', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2357, '23-1097-197', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2358, '23-1098-198', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2359, '23-1099-199', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2360, '23-1100-200', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2361, '24-0187-667', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2362, '24-0235-659', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2363, '24-1548-653', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2364, '24-5487-651', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2365, '24-5487-654', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2366, '24-9999-452', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2367, '32-5421-351', 78, 'Missing AM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2388, '23-1001-101', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2389, '23-1002-102', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2390, '23-1003-103', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2391, '23-1004-104', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2392, '23-1005-105', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2393, '23-1006-106', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2394, '23-1007-107', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2395, '23-1008-108', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2396, '23-1009-109', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2397, '23-1010-110', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2398, '23-1011-111', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2399, '23-1012-112', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2400, '23-1013-113', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2401, '23-1014-114', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2402, '23-1015-115', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2403, '23-1016-116', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2404, '23-1017-117', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2405, '23-1018-118', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2406, '23-1019-119', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2407, '23-1020-120', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2408, '23-1021-121', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2409, '23-1022-122', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2410, '23-1023-123', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2411, '23-1024-124', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2412, '23-1025-125', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2413, '23-1026-126', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2414, '23-1027-127', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2415, '23-1028-128', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2416, '23-1029-129', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2417, '23-1030-130', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2418, '23-1031-131', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2419, '23-1032-132', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2420, '23-1033-133', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2421, '23-1034-134', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2422, '23-1035-135', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2423, '23-1036-136', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2424, '23-1037-137', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2425, '23-1038-138', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2426, '23-1039-139', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2427, '23-1040-140', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2428, '23-1041-141', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2429, '23-1042-142', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2430, '23-1043-143', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2431, '23-1044-144', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2432, '23-1045-145', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2433, '23-1046-146', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2434, '23-1047-147', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2435, '23-1048-148', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2436, '23-1049-149', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2437, '23-1050-150', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2438, '23-1051-151', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2439, '23-1052-152', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2440, '23-1053-153', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2441, '23-1054-154', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2442, '23-1055-155', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2443, '23-1056-156', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2444, '23-1057-157', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2445, '23-1058-158', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2446, '23-1059-159', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2447, '23-1060-160', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2448, '23-1061-161', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2449, '23-1062-162', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2450, '23-1063-163', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2451, '23-1064-164', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2452, '23-1065-165', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2453, '23-1066-166', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2454, '23-1067-167', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2455, '23-1068-168', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2456, '23-1069-169', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2457, '23-1070-170', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2458, '23-1071-171', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2459, '23-1072-172', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2460, '23-1073-173', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2461, '23-1074-174', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2462, '23-1075-175', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2463, '23-1076-176', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2464, '23-1077-177', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2465, '23-1078-178', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2466, '23-1079-179', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2467, '23-1080-180', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2468, '23-1081-181', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2469, '23-1082-182', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2470, '23-1083-183', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2471, '23-1084-184', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2472, '23-1085-185', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2473, '23-1086-186', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2474, '23-1087-187', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2475, '23-1088-188', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2476, '23-1089-189', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2477, '23-1090-190', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2478, '23-1091-191', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2479, '23-1092-192', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2480, '23-1093-193', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2481, '23-1094-194', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2482, '23-1095-195', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2483, '23-1096-196', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2484, '23-1097-197', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2485, '23-1098-198', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2486, '23-1099-199', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2487, '23-1100-200', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2488, '24-0187-667', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2489, '24-0235-659', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2490, '24-1548-653', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2491, '24-5487-651', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2492, '24-5487-654', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2493, '24-9999-452', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2494, '32-5421-351', 78, 'Missing AM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2515, '23-1001-101', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2516, '23-1002-102', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2517, '23-1003-103', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2518, '23-1004-104', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2519, '23-1005-105', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2520, '23-1006-106', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2521, '23-1007-107', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2522, '23-1008-108', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2523, '23-1009-109', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2524, '23-1010-110', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2525, '23-1011-111', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2526, '23-1012-112', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2527, '23-1013-113', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2528, '23-1014-114', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2529, '23-1015-115', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2530, '23-1016-116', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2531, '23-1017-117', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2532, '23-1018-118', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2533, '23-1019-119', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2534, '23-1020-120', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2535, '23-1021-121', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2536, '23-1022-122', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2537, '23-1023-123', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2538, '23-1024-124', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2539, '23-1025-125', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2540, '23-1026-126', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2541, '23-1027-127', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2542, '23-1028-128', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2543, '23-1029-129', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2544, '23-1030-130', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2545, '23-1031-131', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2546, '23-1032-132', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2547, '23-1033-133', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2548, '23-1034-134', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2549, '23-1035-135', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2550, '23-1036-136', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2551, '23-1037-137', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2552, '23-1038-138', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2553, '23-1039-139', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2554, '23-1040-140', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2555, '23-1041-141', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2556, '23-1042-142', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2557, '23-1043-143', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2558, '23-1044-144', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2559, '23-1045-145', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2560, '23-1046-146', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2561, '23-1047-147', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2562, '23-1048-148', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2563, '23-1049-149', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2564, '23-1050-150', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2565, '23-1051-151', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2566, '23-1052-152', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2567, '23-1053-153', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2568, '23-1054-154', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2569, '23-1055-155', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2570, '23-1056-156', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2571, '23-1057-157', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2572, '23-1058-158', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2573, '23-1059-159', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2574, '23-1060-160', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2575, '23-1061-161', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2576, '23-1062-162', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2577, '23-1063-163', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2578, '23-1064-164', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2579, '23-1065-165', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2580, '23-1066-166', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2581, '23-1067-167', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2582, '23-1068-168', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2583, '23-1069-169', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2584, '23-1070-170', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2585, '23-1071-171', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2586, '23-1072-172', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2587, '23-1073-173', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2588, '23-1074-174', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2589, '23-1075-175', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2590, '23-1076-176', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2591, '23-1077-177', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2592, '23-1078-178', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2593, '23-1079-179', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2594, '23-1080-180', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2595, '23-1081-181', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2596, '23-1082-182', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2597, '23-1083-183', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2598, '23-1084-184', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2599, '23-1085-185', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2600, '23-1086-186', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2601, '23-1087-187', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2602, '23-1088-188', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2603, '23-1089-189', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2604, '23-1090-190', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2605, '23-1091-191', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2606, '23-1092-192', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2607, '23-1093-193', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2608, '23-1094-194', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2609, '23-1095-195', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2610, '23-1096-196', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2611, '23-1097-197', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2612, '23-1098-198', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2613, '23-1099-199', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2614, '23-1100-200', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2615, '24-0187-667', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2616, '24-0235-659', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2617, '24-1548-653', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2618, '24-5487-651', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2619, '24-5487-654', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2620, '24-9999-452', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2621, '32-5421-351', 78, 'Missing PM login', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2642, '23-1001-101', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2643, '23-1002-102', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2644, '23-1003-103', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2645, '23-1004-104', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2646, '23-1005-105', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2647, '23-1006-106', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2648, '23-1007-107', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2649, '23-1008-108', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2650, '23-1009-109', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2651, '23-1010-110', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2652, '23-1011-111', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2653, '23-1012-112', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2654, '23-1013-113', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2655, '23-1014-114', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2656, '23-1015-115', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2657, '23-1016-116', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2658, '23-1017-117', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2659, '23-1018-118', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2660, '23-1019-119', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2661, '23-1020-120', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2662, '23-1021-121', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2663, '23-1022-122', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2664, '23-1023-123', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2665, '23-1024-124', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2666, '23-1025-125', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2667, '23-1026-126', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2668, '23-1027-127', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2669, '23-1028-128', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2670, '23-1029-129', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2671, '23-1030-130', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2672, '23-1031-131', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2673, '23-1032-132', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2674, '23-1033-133', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2675, '23-1034-134', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2676, '23-1035-135', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2677, '23-1036-136', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2678, '23-1037-137', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2679, '23-1038-138', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2680, '23-1039-139', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2681, '23-1040-140', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2682, '23-1041-141', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2683, '23-1042-142', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2684, '23-1043-143', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2685, '23-1044-144', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2686, '23-1045-145', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2687, '23-1046-146', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2688, '23-1047-147', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2689, '23-1048-148', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2690, '23-1049-149', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2691, '23-1050-150', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2692, '23-1051-151', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2693, '23-1052-152', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2694, '23-1053-153', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2695, '23-1054-154', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2696, '23-1055-155', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2697, '23-1056-156', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2698, '23-1057-157', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2699, '23-1058-158', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2700, '23-1059-159', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2701, '23-1060-160', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2702, '23-1061-161', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2703, '23-1062-162', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2704, '23-1063-163', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2705, '23-1064-164', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2706, '23-1065-165', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2707, '23-1066-166', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2708, '23-1067-167', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2709, '23-1068-168', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2710, '23-1069-169', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2711, '23-1070-170', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2712, '23-1071-171', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2713, '23-1072-172', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2714, '23-1073-173', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2715, '23-1074-174', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2716, '23-1075-175', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2717, '23-1076-176', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2718, '23-1077-177', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2719, '23-1078-178', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2720, '23-1079-179', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2721, '23-1080-180', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2722, '23-1081-181', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2723, '23-1082-182', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2724, '23-1083-183', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2725, '23-1084-184', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2726, '23-1085-185', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2727, '23-1086-186', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2728, '23-1087-187', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2729, '23-1088-188', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2730, '23-1089-189', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2731, '23-1090-190', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2732, '23-1091-191', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2733, '23-1092-192', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2734, '23-1093-193', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2735, '23-1094-194', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2736, '23-1095-195', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2737, '23-1096-196', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2738, '23-1097-197', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2739, '23-1098-198', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2740, '23-1099-199', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2741, '23-1100-200', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2742, '24-0187-667', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2743, '24-0235-659', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2744, '24-1548-653', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2745, '24-5487-651', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2746, '24-5487-654', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2747, '24-9999-452', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00'),
(2748, '32-5421-351', 78, 'Missing PM logout', 5.00, 'unpaid', '2026-03-27 15:44:00');

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
(79, '{\"incoming\":{\"type\":\"subscribe\",\"channel\":\"student_updates\"}}', '2026-03-26 16:08:10');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

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
