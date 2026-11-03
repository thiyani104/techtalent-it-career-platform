-- ========================================================
-- TechTalent CV Studio migration (additive, idempotent)
-- Safe to run on an existing techtalent_db: no tables are dropped
-- and no existing rows are modified.
-- ========================================================

USE `techtalent_db`;

-- Optional ("nice to have") skills for vacancies; NULL for existing jobs.
ALTER TABLE `jobs` ADD COLUMN IF NOT EXISTS `optional_skills` VARCHAR(255) NULL DEFAULT NULL AFTER `required_skills`;

-- Saved CV versions with structured JSON content.
CREATE TABLE IF NOT EXISTS `cvs` (
    `cv_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `template` ENUM('classic', 'modern', 'minimal') NOT NULL DEFAULT 'classic',
    `accent_color` CHAR(7) NOT NULL DEFAULT '#0f2137',
    `content` LONGTEXT NOT NULL,
    `source` ENUM('profile', 'blank', 'import', 'duplicate') NOT NULL DEFAULT 'blank',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cvs_user` (`user_id`, `updated_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Metadata for uploaded CV files (files live in private storage, not in /uploads).
CREATE TABLE IF NOT EXISTS `cv_uploads` (
    `upload_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` CHAR(40) NOT NULL UNIQUE,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT NOT NULL,
    `sha256` CHAR(64) NOT NULL,
    `extraction_status` ENUM('ok', 'partial', 'no_text', 'failed') NOT NULL,
    `extracted_chars` INT NOT NULL DEFAULT 0,
    `cv_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cv_uploads_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`cv_id`) REFERENCES `cvs`(`cv_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable copy of the CV submitted with an application.
CREATE TABLE IF NOT EXISTS `application_cv_snapshots` (
    `snapshot_id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL UNIQUE,
    `cv_id` INT NULL,
    `cv_title` VARCHAR(150) NOT NULL,
    `template` ENUM('classic', 'modern', 'minimal') NOT NULL,
    `accent_color` CHAR(7) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `content_hash` CHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_snapshot_cv` (`cv_id`),
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`application_id`) ON DELETE CASCADE,
    FOREIGN KEY (`cv_id`) REFERENCES `cvs`(`cv_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshots may never be edited. (FK ON DELETE SET NULL does not fire triggers,
-- so deleting the source CV still works.)
DROP TRIGGER IF EXISTS `trg_cv_snapshot_immutable`;
DELIMITER $$
CREATE TRIGGER `trg_cv_snapshot_immutable` BEFORE UPDATE ON `application_cv_snapshots`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Application CV snapshots are immutable';
END$$
DELIMITER ;

-- Approved alternative spellings for master skills, used for normalisation.
CREATE TABLE IF NOT EXISTS `skill_aliases` (
    `alias_id` INT AUTO_INCREMENT PRIMARY KEY,
    `skill_id` INT NOT NULL,
    `alias` VARCHAR(100) NOT NULL UNIQUE,
    FOREIGN KEY (`skill_id`) REFERENCES `skills`(`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `skill_aliases` (`skill_id`, `alias`)
SELECT s.skill_id, a.alias FROM `skills` s JOIN (
    SELECT 'JavaScript' AS skill_name, 'js' AS alias UNION ALL
    SELECT 'JavaScript', 'es6' UNION ALL
    SELECT 'JavaScript', 'ecmascript' UNION ALL
    SELECT 'Python', 'python3' UNION ALL
    SELECT 'C++', 'cpp' UNION ALL
    SELECT 'React', 'reactjs' UNION ALL
    SELECT 'React', 'react.js' UNION ALL
    SELECT 'MySQL', 'mysql database' UNION ALL
    SELECT 'Git', 'git version control' UNION ALL
    SELECT 'Machine Learning', 'ml' UNION ALL
    SELECT 'Deep Learning', 'dl' UNION ALL
    SELECT 'NLP', 'natural language processing' UNION ALL
    SELECT 'Scikit-learn', 'sklearn' UNION ALL
    SELECT 'Scikit-learn', 'scikit learn' UNION ALL
    SELECT 'PowerBI', 'power bi' UNION ALL
    SELECT 'Data Visualization', 'data visualisation' UNION ALL
    SELECT 'SQL', 'structured query language' UNION ALL
    SELECT 'ETL', 'extract transform load' UNION ALL
    SELECT 'SIEM', 'security information and event management' UNION ALL
    SELECT 'AWS', 'amazon web services' UNION ALL
    SELECT 'Kubernetes', 'k8s' UNION ALL
    SELECT 'CI/CD', 'ci cd' UNION ALL
    SELECT 'CI/CD', 'continuous integration' UNION ALL
    SELECT 'System Administration', 'sysadmin' UNION ALL
    SELECT 'UI/UX Design', 'ui/ux' UNION ALL
    SELECT 'UI/UX Design', 'ux design' UNION ALL
    SELECT 'UI/UX Design', 'ui design' UNION ALL
    SELECT 'Manual Testing', 'manual qa' UNION ALL
    SELECT 'Selenium', 'selenium webdriver' UNION ALL
    SELECT 'Agile Project Management', 'agile project mgmt' UNION ALL
    SELECT 'Scrum', 'scrum master' UNION ALL
    SELECT 'Jira', 'atlassian jira'
) a ON a.skill_name = s.skill_name;
