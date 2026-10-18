-- ============================================================================
--  OCT Feedback Board System — UPGRADE script (existing databases)
--  Adds: super_admin role, field_scope column, feedback_attachments table,
--        seeded demo accounts for the new features.
--
--  HOW TO RUN:  http://localhost/phpmyadmin -> oct_feedback -> SQL -> paste this
--               (or:  mysql -u root oct_feedback < database_upgrade.sql)
--
--  Safe to run once; running it twice errors harmlessly on the ALTERs
--  ("Duplicate column name") — the INSERTs use their explicit IDs.
-- ============================================================================

USE oct_feedback;

-- 1. Widen the role enum to include super_admin ------------------------------
ALTER TABLE users
  MODIFY COLUMN role ENUM('student','faculty','staff','admin','super_admin')
  NOT NULL DEFAULT 'student';

-- 2. Field scope for departmental (field) admins ------------------------------
ALTER TABLE users
  ADD COLUMN field_scope VARCHAR(30) DEFAULT NULL AFTER role;

-- 3. Media attachments (img / vid / audio) per feedback -----------------------
CREATE TABLE IF NOT EXISTS feedback_attachments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  media_kind  ENUM('image','video','audio') NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attach_feedback FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Migrate existing image_path rows into the attachments table -------------
INSERT INTO feedback_attachments (feedback_id, file_path, media_kind)
SELECT id, image_path, 'image'
FROM feedbacks
WHERE image_path IS NOT NULL AND image_path <> '';

-- 5. Roles for the seeded demo users ------------------------------------------
UPDATE users SET role = 'super_admin',
                 department = 'Office of the President'
WHERE id = 1;
UPDATE users SET role = 'staff', department = 'Maintenance Department'
WHERE id = 5;
INSERT INTO users (id, id_number, name, email, password, department, role, field_scope) VALUES
(6, 'STA-0011', 'Marco Torres', 'marco.torres@oct.edu.ph',
 '$2y$12$TW9Z0kGn4BJO5x.aDuuthOv.665SGzKxb7ycZZna6xT5pRNOKOh0e',   -- student123
 'Maintenance Department', 'staff', NULL),
(7, 'ADM-0002', 'Grace Mendez', 'grace.mendez@oct.edu.ph',
 '$2y$12$LwFftRIDMFbp0RUh7kD3EOgSNPZTQMfyxg99zv3Qr9v8mnTDjkg6K',   -- superadmin123
 'Maintenance Office', 'admin', 'maintenance');

-- 6. Default admin password -> superadmin123 ----------------------------------
UPDATE users SET password = '$2y$12$3U1if8uMAeI4n.6ag5OjVO8cZ021RlAMb9JJuy19zPf7MOsfpar4m'
WHERE id = 1;
