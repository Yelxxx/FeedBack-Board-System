-- ============================================================================
--  OCT Feedback Board System — Database Schema & Seed Data
--  Compatible with MySQL 5.7+ / 8.x and MariaDB 10.x (XAMPP)
--
--  HOW TO IMPORT:
--    Option A (phpMyAdmin):  open http://localhost/phpmyadmin -> Import -> select this file
--    Option B (command line):  mysql -u root -p < database.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS oct_feedback
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oct_feedback;

-- ---------------------------------------------------------------------------
-- TABLES
-- ---------------------------------------------------------------------------

-- Users (Students / Faculty / Staff / Admins)
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_number   VARCHAR(30)  NOT NULL UNIQUE,           -- school ID number
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(120) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,                  -- bcrypt hash
  department  VARCHAR(100) NOT NULL DEFAULT '',       -- course / department / office
  role        ENUM('student','faculty','staff','admin') NOT NULL DEFAULT 'student',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Feedback / Concerns (tickets)
CREATE TABLE IF NOT EXISTS feedbacks (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no     VARCHAR(20)  NOT NULL UNIQUE,          -- e.g. TKT-001
  user_id       INT UNSIGNED NOT NULL,
  category      VARCHAR(40)  NOT NULL,
  title         VARCHAR(160) NOT NULL,
  description   TEXT         NOT NULL,
  image_path    VARCHAR(255) DEFAULT NULL,             -- optional photo evidence
  is_anonymous  TINYINT(1)   NOT NULL DEFAULT 0,       -- hide identity from admins
  status        ENUM('pending','under_review','resolved') NOT NULL DEFAULT 'pending',
  assigned_dept VARCHAR(60)  DEFAULT NULL,             -- e.g. Maintenance, OSA, HR...
  is_public     TINYINT(1)   NOT NULL DEFAULT 0,       -- shown on the Public Board
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments (two-way communication thread between user & admin)
CREATE TABLE IF NOT EXISTS comments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  message     TEXT         NOT NULL,
  visibility  ENUM('public','private') NOT NULL DEFAULT 'public',  -- private = admin-only note
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_feedback FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(120) NOT NULL,
  token      VARCHAR(64)  NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_resets_email  (email),
  INDEX idx_resets_token  (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- In-app notifications (Phase 4: Tracking & Notifications)
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  feedback_id INT UNSIGNED DEFAULT NULL,
  message     VARCHAR(255) NOT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- SEED DATA  (demo accounts + sample tickets so the app is demo-ready)
--
--  Default admin :  admin@oct.edu.ph      password: admin123
--  Demo students :  juan.delacruz@oct.edu.ph / maria.santos@oct.edu.ph / etc.
--                   password: student123
--
--  To start with a CLEAN database, just skip this section.
-- ---------------------------------------------------------------------------

INSERT INTO users (id, id_number, name, email, password, department, role) VALUES
(1, 'ADM-0001', 'System Administrator', 'admin@oct.edu.ph',
 '$2y$12$LwFftRIDMFbp0RUh7kD3EOgSNPZTQMfyxg99zv3Qr9v8mnTDjkg6K',   -- admin123
 'Office of Student Affairs', 'admin'),
(2, '2023-0142', 'Juan Dela Cruz', 'juan.delacruz@oct.edu.ph',
 '$2y$12$TW9Z0kGn4BJO5x.aDuuthOv.665SGzKxb7ycZZna6xT5pRNOKOh0e',   -- student123
 'BS Computer Science', 'student'),
(3, '2023-0871', 'Maria Santos', 'maria.santos@oct.edu.ph',
 '$2y$12$TW9Z0kGn4BJO5x.aDuuthOv.665SGzKxb7ycZZna6xT5pRNOKOh0e',   -- student123
 'BS Information Technology', 'student'),
(4, '2022-0056', 'Pedro Reyes', 'pedro.reyes@oct.edu.ph',
 '$2y$12$TW9Z0kGn4BJO5x.aDuuthOv.665SGzKxb7ycZZna6xT5pRNOKOh0e',   -- student123
 'College of Engineering', 'faculty'),
(5, '2023-0033', 'Ana Lopez', 'ana.lopez@oct.edu.ph',
 '$2y$12$TW9Z0kGn4BJO5x.aDuuthOv.665SGzKxb7ycZZna6xT5pRNOKOh0e',   -- student123
 'Registrar Office', 'staff');

INSERT INTO feedbacks (id, ticket_no, user_id, category, title, description, is_anonymous, status, assigned_dept, is_public, created_at) VALUES
(1, 'TKT-001', 2, 'Facility / Maintenance', 'Broken air conditioner in Room 204',
 'The air conditioner in Room 204 on the 2nd floor has been broken for almost a week. It makes loud noises and does not cool at all. Classes in that room are already unbearable, especially in the afternoon.',
 0, 'under_review', 'Maintenance', 1, NOW() - INTERVAL 12 DAY),
(2, 'TKT-002', 3, 'Academic', 'Conflicting schedule in CS 301',
 'The midterm exam schedule for CS 301 overlaps with our lab practical exam for IT 203. Several students in both sections are affected. Please review the schedule.',
 0, 'pending', 'Registrar', 1, NOW() - INTERVAL 9 DAY),
(3, 'TKT-003', 4, 'Environment / Safety', 'Unlit hallway near Building B',
 'The hallway connecting Building A and Building B has no working lights at night. This is a safety hazard for students attending evening classes.',
 0, 'resolved', 'Maintenance', 1, NOW() - INTERVAL 21 DAY),
(4, 'TKT-004', 3, 'Social / Behavioral', 'Loud noise disturbance in the library',
 'A group of students is consistently loud inside the library, disturbing everyone who is studying. Please remind them of the library rules.',
 0, 'under_review', 'OSA', 0, NOW() - INTERVAL 6 DAY),
(5, 'TKT-005', 2, 'Facility / Maintenance', 'Broken chairs in Lecture Hall 1',
 'Several chairs in Lecture Hall 1 are broken and some are about to collapse. A student almost fell during a class discussion.',
 0, 'pending', 'Maintenance', 1, NOW() - INTERVAL 4 DAY),
(6, 'TKT-006', 2, 'Facility / Maintenance', 'Restroom cleanliness on the 3rd floor',
 'The restroom on the 3rd floor of the main building has not been cleaned for days. Please have it attended to on a regular basis.',
 1, 'pending', NULL, 0, NOW() - INTERVAL 2 DAY),
(7, 'TKT-007', 3, 'Facility / Maintenance', 'Slow internet in the computer laboratory',
 'The internet connection in the computer laboratory is extremely slow, making online activities in class almost impossible. Around 5 Mbps shared by 40 computers.',
 0, 'resolved', 'IT Office', 1, NOW() - INTERVAL 15 DAY),
(8, 'TKT-008', 5, 'Others', 'Suggestion: add more motorcycle parking slots',
 'Many students arrive by motorcycle but the designated parking area is always full. Please consider adding more slots near the gymnasium.',
 0, 'pending', NULL, 1, NOW() - INTERVAL 1 DAY);

INSERT INTO comments (feedback_id, user_id, message, visibility, created_at) VALUES
(1, 1, 'Hi Juan, thank you for reporting. Could you confirm the exact room number and floor?', 'public', NOW() - INTERVAL 10 DAY),
(1, 2, 'It is Room 204 on the 2nd floor, beside the faculty lounge.', 'public', NOW() - INTERVAL 9 DAY),
(1, 1, 'Noted. Our maintenance team will inspect the unit today.', 'public', NOW() - INTERVAL 8 DAY),
(3, 1, 'The hallway lights were replaced this morning. Please report if you notice any other area.', 'public', NOW() - INTERVAL 20 DAY),
(7, 1, 'We upgraded the bandwidth for the computer laboratory. Please monitor and let us know if the speed improves.', 'public', NOW() - INTERVAL 14 DAY);

INSERT INTO notifications (user_id, feedback_id, message, is_read, created_at) VALUES
(1, 8, 'New feedback submitted: TKT-008', 0, NOW() - INTERVAL 1 DAY),
(1, 6, 'New anonymous feedback submitted: TKT-006', 0, NOW() - INTERVAL 2 DAY);
