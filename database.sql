-- ============================================================================
--  OCT Feedback Board System — Clean Schema & 5 Baseline End-User Role Accounts
-- ============================================================================

CREATE DATABASE IF NOT EXISTS oct_feedback
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oct_feedback;

-- Disable foreign key checks for clean recreation
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS feedback_attachments;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS feedbacks;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- TABLES
-- ---------------------------------------------------------------------------

-- Users (5 Roles: super_admin, admin, staff, faculty, student)
CREATE TABLE users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_number   VARCHAR(30)  NOT NULL UNIQUE,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(120) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  department  VARCHAR(100) NOT NULL DEFAULT '',
  role        ENUM('student','faculty','staff','admin','super_admin') NOT NULL DEFAULT 'student',
  field_scope VARCHAR(30)  DEFAULT NULL, -- dept head (admin): maintenance|academic|safety|social|others
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Feedback / Concerns (tickets)
CREATE TABLE feedbacks (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no     VARCHAR(20)  NOT NULL UNIQUE,
  user_id       INT UNSIGNED NOT NULL,
  category      VARCHAR(40)  NOT NULL,
  title         VARCHAR(160) NOT NULL,
  description   TEXT         NOT NULL,
  image_path    VARCHAR(255) DEFAULT NULL,
  is_anonymous  TINYINT(1)   NOT NULL DEFAULT 0,
  status        ENUM('pending','under_review','resolved','rejected') NOT NULL DEFAULT 'pending',
  assigned_dept VARCHAR(60)  DEFAULT NULL,
  is_public     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments & Nested Replies
CREATE TABLE comments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED DEFAULT NULL,
  user_id     INT UNSIGNED NOT NULL,
  message     TEXT         NOT NULL,
  visibility  ENUM('public','private') NOT NULL DEFAULT 'public',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_feedback FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_parent   FOREIGN KEY (parent_id)   REFERENCES comments(id)  ON DELETE CASCADE,
  CONSTRAINT fk_comment_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Feedback Support / Endorsements ("Support" vote feature)
CREATE TABLE feedback_endorsements (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_feedback_user (feedback_id, user_id),
  CONSTRAINT fk_endorse_feedback FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
  CONSTRAINT fk_endorse_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens
CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(120) NOT NULL,
  token      VARCHAR(64)  NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_resets_email (email),
  INDEX idx_resets_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- In-app notifications
CREATE TABLE notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  feedback_id INT UNSIGNED DEFAULT NULL,
  message     VARCHAR(255) NOT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Media attachments per feedback
CREATE TABLE feedback_attachments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  media_kind  ENUM('image','video','audio') NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attach_feedback FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campus Announcements (posted by Super Admin, visible to all users)
CREATE TABLE announcements (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(180) NOT NULL,
  content     TEXT         NOT NULL,
  badge       VARCHAR(40)  DEFAULT 'Campus Announcement',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcement_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5 BASELINE USER ACCOUNTS (One clean account for each of the 5 roles)
--
-- 1. Super Admin:
--    Email: admin@oct.edu.ph
--    Password: superadmin123
--    Role: super_admin (Overall oversight across all fields)
--
-- 2. Department Head / Admin:
--    Email: depthead.maintenance@oct.edu.ph
--    Password: superadmin123
--    Role: admin (Head of Maintenance / Facilities)
--
-- 3. Staff / Maintenance:
--    Email: staff.maintenance@oct.edu.ph
--    Password: student123
--    Role: staff (Handles canteen, facilities, repairs, non-academic tasks)
--
-- 4. Faculty / Professor:
--    Email: prof.reyes@oct.edu.ph
--    Password: student123
--    Role: faculty (Instructor/Professor)
--
-- 5. Student:
--    Email: yel.mendoza@oct.edu.ph
--    Password: student123
--    Role: student (Student body)
-- ---------------------------------------------------------------------------

INSERT INTO users (id, id_number, name, email, password, department, role, field_scope) VALUES
(1, 'ADM-0001', 'System Administrator', 'admin@oct.edu.ph',
 '$2y$12$7hIPNB5r6zT4SHO.UHpguOiGuLWspWmkPky.2ocSiE0s/p/sbq0Le',
 'Office of the President', 'super_admin', NULL),

(2, 'DPT-0001', 'Engr. Grace Mendez (Dept Head)', 'depthead.maintenance@oct.edu.ph',
 '$2y$12$7hIPNB5r6zT4SHO.UHpguOiGuLWspWmkPky.2ocSiE0s/p/sbq0Le',
 'Maintenance Office', 'admin', 'maintenance'),

(3, 'STF-0001', 'Marco Torres (Maintenance Staff)', 'staff.maintenance@oct.edu.ph',
 '$2y$12$iC7mTDUGRo/gSJpBbziPg.faZ13d2UVAFVCdjP49vfbVoGTh/EjPK',
 'Maintenance Department', 'staff', NULL),

(4, 'FAC-0001', 'Prof. Pedro Reyes', 'prof.reyes@oct.edu.ph',
 '$2y$12$iC7mTDUGRo/gSJpBbziPg.faZ13d2UVAFVCdjP49vfbVoGTh/EjPK',
 'College of Engineering', 'faculty', NULL),

(5, 'STD-0001', 'Yel Mendoza', 'yel.mendoza@oct.edu.ph',
 '$2y$12$iC7mTDUGRo/gSJpBbziPg.faZ13d2UVAFVCdjP49vfbVoGTh/EjPK',
 'BS Information Technology', 'student', NULL);
