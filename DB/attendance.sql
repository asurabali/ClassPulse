-- ============================================================
-- ClassPulse — Classroom Analytics System
-- Enhanced Database Schema
-- Version: 2.0
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ------------------------------------------------------------
-- Database: `attendance`
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `attendance`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `attendance`;

-- ============================================================
-- TABLE: admin
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin` (
  `admin_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(70)  NOT NULL,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `date_added` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: username=admin, password=admin123
INSERT INTO `admin` (`name`, `username`, `password`) VALUES
('ClassPulse Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- TABLE: school_year
-- ============================================================
CREATE TABLE IF NOT EXISTS `school_year` (
  `sy_id`        INT(11)     NOT NULL AUTO_INCREMENT,
  `school_year`  VARCHAR(60) NOT NULL,
  `status`       INT(11)     NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `date_added`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`sy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `school_year` (`school_year`, `status`) VALUES
('2023-2024', 1),
('2022-2023', 0),
('2021-2022', 0);

-- ============================================================
-- TABLE: course_list
-- ============================================================
CREATE TABLE IF NOT EXISTS `course_list` (
  `course_id`   INT(11)     NOT NULL AUTO_INCREMENT,
  `course_code` VARCHAR(60) NOT NULL,
  `status`      INT(11)     NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `date_added`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `course_list` (`course_code`, `status`) VALUES
('BSCS',  1),
('BSED',  1),
('BSIS',  1),
('BSITT', 1),
('BSIT',  1);

-- ============================================================
-- TABLE: year_level
-- ============================================================
CREATE TABLE IF NOT EXISTS `year_level` (
  `yl_id`      INT(11)     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(55) NOT NULL,
  `date_added` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`yl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `year_level` (`name`) VALUES
('First Year'),
('Second Year'),
('Third Year'),
('Fourth Year'),
('Fifth Year');

-- ============================================================
-- TABLE: subjects  (NEW — subject tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id`   INT(11)     NOT NULL AUTO_INCREMENT,
  `subject_code` VARCHAR(30) NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `course_id`    INT(11)     NOT NULL,
  `yl_id`        INT(11)     NOT NULL,
  `sy_id`        INT(11)     NOT NULL,
  `total_classes` INT(11)    NOT NULL DEFAULT 0 COMMENT 'Total scheduled class sessions',
  `status`       INT(11)     NOT NULL DEFAULT 1,
  `date_added`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`subject_id`),
  FOREIGN KEY (`course_id`) REFERENCES `course_list`(`course_id`) ON DELETE CASCADE,
  FOREIGN KEY (`yl_id`)     REFERENCES `year_level`(`yl_id`) ON DELETE CASCADE,
  FOREIGN KEY (`sy_id`)     REFERENCES `school_year`(`sy_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `subjects` (`subject_code`, `subject_name`, `course_id`, `yl_id`, `sy_id`, `total_classes`) VALUES
('CS101', 'Introduction to Computer Science', 1, 1, 1, 30),
('CS201', 'Data Structures & Algorithms',     1, 2, 1, 28),
('CS301', 'Operating Systems',                1, 3, 1, 25),
('CS401', 'Software Engineering',             1, 4, 1, 26),
('IT101', 'IT Fundamentals',                  5, 1, 1, 30),
('IT201', 'Web Technologies',                 5, 2, 1, 28);

-- ============================================================
-- TABLE: students
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
  `student_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `student_code` VARCHAR(50)  NOT NULL UNIQUE,
  `fname`        VARCHAR(60)  NOT NULL,
  `mname`        VARCHAR(60)  NOT NULL DEFAULT '',
  `lname`        VARCHAR(60)  NOT NULL,
  `gender`       VARCHAR(20)  NOT NULL,
  `email`        VARCHAR(100) NOT NULL,
  `contact`      VARCHAR(50)  NOT NULL,
  `course_id`    INT(11)      NOT NULL,
  `yl_id`        INT(11)      NOT NULL,
  `sy_id`        INT(11)      NOT NULL,
  `status`       INT(11)      NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `date_added`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`student_id`),
  FOREIGN KEY (`course_id`) REFERENCES `course_list`(`course_id`),
  FOREIGN KEY (`yl_id`)     REFERENCES `year_level`(`yl_id`),
  FOREIGN KEY (`sy_id`)     REFERENCES `school_year`(`sy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample students
INSERT INTO `students` (`student_code`, `fname`, `mname`, `lname`, `gender`, `email`, `contact`, `course_id`, `yl_id`, `sy_id`) VALUES
('2024-CS-001', 'Alice',   'Marie',    'Johnson',   'female', 'alice.johnson@edu.com',   '09171234567', 1, 1, 1),
('2024-CS-002', 'Bob',     'James',    'Smith',     'male',   'bob.smith@edu.com',       '09182345678', 1, 1, 1),
('2024-CS-003', 'Carol',   'Ann',      'Williams',  'female', 'carol.williams@edu.com',  '09193456789', 1, 1, 1),
('2024-CS-004', 'David',   'Lee',      'Brown',     'male',   'david.brown@edu.com',     '09174567890', 1, 1, 1),
('2024-CS-005', 'Emma',    'Grace',    'Jones',     'female', 'emma.jones@edu.com',      '09185678901', 1, 1, 1),
('2023-IT-001', 'Frank',   'Carl',     'Davis',     'male',   'frank.davis@edu.com',     '09196789012', 5, 2, 1),
('2023-IT-002', 'Grace',   'Lin',      'Miller',    'female', 'grace.miller@edu.com',    '09177890123', 5, 2, 1),
('2023-IT-003', 'Henry',   'Paul',     'Wilson',    'male',   'henry.wilson@edu.com',    '09188901234', 5, 2, 1);

-- ============================================================
-- TABLE: attendance_list   (Enhanced — subject-aware)
-- ============================================================
CREATE TABLE IF NOT EXISTS `attendance_list` (
  `attendance_id` INT(11)      NOT NULL AUTO_INCREMENT,
  `student_id`    INT(11)      NOT NULL,
  `subject_id`    INT(11)      DEFAULT NULL COMMENT 'NULL = general attendance',
  `type`          INT(11)      NOT NULL DEFAULT 1 COMMENT '1=Present/TimeIn, 2=Absent/TimeOut',
  `date_updated`  VARCHAR(255) DEFAULT NULL,
  `date_created`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`attendance_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE SET NULL,
  INDEX `idx_student_date` (`student_id`, `date_created`),
  INDEX `idx_subject_date` (`subject_id`, `date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample attendance records (spread across dates for chart demos)
INSERT INTO `attendance_list` (`student_id`, `subject_id`, `type`, `date_updated`, `date_created`) VALUES
(1, 1, 1, 'Oct 01, 2024 08:00 AM', '2024-10-01 08:00:00'),
(2, 1, 1, 'Oct 01, 2024 08:01 AM', '2024-10-01 08:01:00'),
(3, 1, 1, 'Oct 01, 2024 08:02 AM', '2024-10-01 08:02:00'),
(4, 1, 2, 'Oct 01, 2024 08:03 AM', '2024-10-01 08:03:00'),
(5, 1, 2, 'Oct 01, 2024 08:04 AM', '2024-10-01 08:04:00'),
(1, 1, 1, 'Oct 03, 2024 08:00 AM', '2024-10-03 08:00:00'),
(2, 1, 2, 'Oct 03, 2024 08:01 AM', '2024-10-03 08:01:00'),
(3, 1, 1, 'Oct 03, 2024 08:02 AM', '2024-10-03 08:02:00'),
(4, 1, 1, 'Oct 03, 2024 08:03 AM', '2024-10-03 08:03:00'),
(5, 1, 2, 'Oct 03, 2024 08:04 AM', '2024-10-03 08:04:00'),
(1, 1, 1, 'Oct 08, 2024 08:00 AM', '2024-10-08 08:00:00'),
(2, 1, 1, 'Oct 08, 2024 08:01 AM', '2024-10-08 08:01:00'),
(3, 1, 2, 'Oct 08, 2024 08:02 AM', '2024-10-08 08:02:00'),
(4, 1, 1, 'Oct 08, 2024 08:03 AM', '2024-10-08 08:03:00'),
(5, 1, 1, 'Oct 08, 2024 08:04 AM', '2024-10-08 08:04:00'),
(6, 5, 1, 'Oct 01, 2024 10:00 AM', '2024-10-01 10:00:00'),
(7, 5, 1, 'Oct 01, 2024 10:01 AM', '2024-10-01 10:01:00'),
(8, 5, 2, 'Oct 01, 2024 10:02 AM', '2024-10-01 10:02:00'),
(6, 5, 1, 'Oct 03, 2024 10:00 AM', '2024-10-03 10:00:00'),
(7, 5, 2, 'Oct 03, 2024 10:01 AM', '2024-10-03 10:01:00'),
(8, 5, 1, 'Oct 03, 2024 10:02 AM', '2024-10-03 10:02:00');

-- ============================================================
-- TABLE: user_list  (instructor/staff accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_list` (
  `user_id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `fullname`   VARCHAR(255) NOT NULL,
  `username`   VARCHAR(70)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `type`       INT(11)      NOT NULL DEFAULT 1 COMMENT '1=instructor, 2=staff',
  `status`     INT(11)      NOT NULL DEFAULT 1,
  `date_added` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- VIEWS for convenience
-- ============================================================

-- View: attendance percentage per student per subject
CREATE OR REPLACE VIEW `v_attendance_summary` AS
SELECT
    s.student_id,
    s.student_code,
    CONCAT(s.fname, ' ', s.mname, ' ', s.lname) AS student_name,
    sub.subject_id,
    sub.subject_code,
    sub.subject_name,
    sub.total_classes,
    COUNT(al.attendance_id) AS attended,
    ROUND((COUNT(al.attendance_id) / NULLIF(sub.total_classes, 0)) * 100, 2) AS attendance_pct,
    c.course_code,
    yl.name AS year_level
FROM students s
INNER JOIN course_list c   ON s.course_id = c.course_id
INNER JOIN year_level  yl  ON s.yl_id     = yl.yl_id
CROSS JOIN subjects sub
LEFT JOIN attendance_list al
    ON al.student_id  = s.student_id
    AND al.subject_id = sub.subject_id
    AND al.type       = 1
WHERE s.status    = 1
    AND sub.status = 1
    AND sub.course_id = s.course_id
    AND sub.yl_id     = s.yl_id
    AND sub.sy_id     = s.sy_id
GROUP BY s.student_id, sub.subject_id;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
