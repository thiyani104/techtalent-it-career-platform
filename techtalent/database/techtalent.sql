-- ========================================================
-- TechTalent Solutions Complete Database Schema & Seed Data
-- Database: `techtalent_db`
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- Engine: InnoDB
-- ========================================================

CREATE DATABASE IF NOT EXISTS `techtalent_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `techtalent_db`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('job_seeker', 'employer', 'admin') NOT NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `career_categories`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `career_categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `icon_class` VARCHAR(50),
    `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `jobs`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `jobs` (
    `job_id` INT AUTO_INCREMENT PRIMARY KEY,
    `employer_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `job_title` VARCHAR(150) NOT NULL,
    `company_name` VARCHAR(150) NOT NULL,
    `work_arrangement` ENUM('Remote', 'Hybrid', 'On-site') NOT NULL,
    `job_type` ENUM('Full-time', 'Part-time', 'Internship', 'Freelance') NOT NULL,
    `experience_level` ENUM('Internship', 'Entry Level', 'Junior', 'Mid-Level', 'Senior') NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `salary_range` VARCHAR(100),
    `description` TEXT NOT NULL,
    `required_skills` VARCHAR(255) NOT NULL,
    `status` ENUM('Draft', 'Pending', 'Published', 'Closed') DEFAULT 'Published',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `career_categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `job_seeker_profiles`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `job_seeker_profiles` (
    `profile_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `headline` VARCHAR(200),
    `phone` VARCHAR(20),
    `location` VARCHAR(100),
    `bio` TEXT,
    `preferred_work_arrangement` ENUM('Remote', 'Hybrid', 'On-site', 'Any') DEFAULT 'Any',
    `target_career_category_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`target_career_category_id`) REFERENCES `career_categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `skills`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `skills` (
    `skill_id` INT AUTO_INCREMENT PRIMARY KEY,
    `skill_name` VARCHAR(100) NOT NULL UNIQUE,
    `category_id` INT,
    FOREIGN KEY (`category_id`) REFERENCES `career_categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `user_skills`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `user_skills` (
    `user_skill_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    `proficiency_level` ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `skills`(`skill_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_skill` (`user_id`, `skill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `applications`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `applications` (
    `application_id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT NOT NULL,
    `job_seeker_id` INT NOT NULL,
    `match_score` DECIMAL(5,2) DEFAULT 0.00,
    `current_status` ENUM('Applied', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Interview Completed', 'Selected', 'Rejected') DEFAULT 'Applied',
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`job_id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_seeker_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_job_application` (`job_id`, `job_seeker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `application_status_history`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `application_status_history` (
    `history_id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `status` ENUM('Applied', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Interview Completed', 'Selected', 'Rejected') NOT NULL,
    `updated_by` INT NOT NULL,
    `notes` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`application_id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Initial seed data for table `career_categories`
-- --------------------------------------------------------

INSERT IGNORE INTO `career_categories` (`category_name`, `slug`, `icon_class`) VALUES
('Software Engineering & Development', 'software-engineering', 'bi-code-square'),
('Artificial Intelligence & Machine Learning', 'ai-ml', 'bi-cpu-fill'),
('Data Science & Analytics', 'data-science', 'bi-bar-chart-line-fill'),
('Cybersecurity', 'cybersecurity', 'bi-shield-lock-fill'),
('Cloud Computing & DevOps', 'cloud-devops', 'bi-cloud-arrow-up-fill'),
('Networking & Systems', 'networking-systems', 'bi-diagram-3-fill'),
('UI/UX & Software Quality Assurance', 'ui-ux-qa', 'bi-palette-fill'),
('IT Project Management & Business Analysis', 'pm-ba', 'bi-kanban-fill');

-- --------------------------------------------------------
-- Initial seed data for table `jobs`
-- --------------------------------------------------------

INSERT IGNORE INTO `jobs` (`job_id`, `employer_id`, `category_id`, `job_title`, `company_name`, `work_arrangement`, `job_type`, `experience_level`, `location`, `salary_range`, `description`, `required_skills`, `status`) VALUES
(1, 1, 1, 'Junior Software Engineer', 'SysTech Global', 'Hybrid', 'Full-time', 'Entry Level', 'Colombo', 'LKR 90,000 - 140,000', 'Exciting opportunity for fresh graduates to work on scalable web and microservices architectures.', 'PHP, MySQL, JavaScript, Git', 'Published'),
(2, 1, 2, 'Associate AI / NLP Engineer', 'Cognitive Labs', 'Remote', 'Full-time', 'Junior', 'Colombo', 'LKR 120,000 - 180,000', 'Join our AI team building recommendation engines, natural language parsers, and machine learning pipelines.', 'Python, PyTorch, Scikit-learn, NLP', 'Published'),
(3, 1, 3, 'Junior Data Analyst', 'FinMetrics Analytics', 'On-site', 'Full-time', 'Entry Level', 'Colombo', 'LKR 85,000 - 125,000', 'Perform exploratory data analysis, build executive dashboards, and maintain ETL data pipelines.', 'SQL, Python, Pandas, PowerBI', 'Published'),
(4, 1, 4, 'Cybersecurity Associate (SOC)', 'SecureNet Defense', 'Hybrid', 'Full-time', 'Entry Level', 'Colombo', 'LKR 100,000 - 150,000', 'Monitor security logs, assess vulnerabilities, and support incident response operations.', 'Network Security, Linux, Wireshark, SIEM', 'Published'),
(5, 1, 5, 'Cloud & DevOps Intern', 'CloudScale Systems', 'Remote', 'Internship', 'Internship', 'Colombo', 'LKR 45,000 - 60,000', 'Learn containerization, CI/CD automation, and cloud infrastructure management.', 'Docker, AWS, Linux, Git', 'Published'),
(6, 1, 7, 'UI/UX & QA Trainee', 'PixelCraft Studios', 'Hybrid', 'Internship', 'Internship', 'Colombo', 'LKR 50,000 - 70,000', 'Create intuitive web interfaces in Figma and conduct cross-browser and automated QA testing.', 'Figma, HTML/CSS, Manual Testing, Selenium', 'Published');

-- --------------------------------------------------------
-- Initial seed data for table `skills`
-- --------------------------------------------------------

INSERT IGNORE INTO `skills` (`skill_name`, `category_id`) VALUES
('PHP', 1), ('JavaScript', 1), ('Python', 1), ('C++', 1), ('React', 1), ('MySQL', 1), ('Git', 1),
('Machine Learning', 2), ('Deep Learning', 2), ('NLP', 2), ('PyTorch', 2), ('Scikit-learn', 2),
('Pandas', 3), ('Data Visualization', 3), ('PowerBI', 3), ('SQL', 3), ('ETL', 3),
('Network Security', 4), ('Linux', 4), ('Wireshark', 4), ('SIEM', 4),
('AWS', 5), ('Docker', 5), ('Kubernetes', 5), ('CI/CD', 5),
('Cisco Routing', 6), ('System Administration', 6),
('Figma', 7), ('UI/UX Design', 7), ('Manual Testing', 7), ('Selenium', 7),
('Agile Project Management', 8), ('Scrum', 8), ('Business Analysis', 8), ('Jira', 8);

-- --------------------------------------------------------
-- Table structure for table `reviews`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `reviews` (
    `review_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `rating` TINYINT NOT NULL,
    `review_text` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `password_resets`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `otp_code` VARCHAR(10) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `contact_messages`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `message_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(180) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `sender_type` VARCHAR(30) NOT NULL DEFAULT 'General Inquiry',
    `subject` VARCHAR(180) NOT NULL DEFAULT 'General Inquiry',
    `message` TEXT NOT NULL,
    `reply_message` TEXT DEFAULT NULL,
    `replied_at` DATETIME DEFAULT NULL,
    `replied_by` INT DEFAULT NULL,
    `status` ENUM('new','read','replied') NOT NULL DEFAULT 'new',
    `read_at` DATETIME DEFAULT NULL,
    `admin_note` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_status` (`status`),
    INDEX `idx_contact_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `contact_replies` (reply history)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `contact_replies` (
    `reply_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT UNSIGNED NOT NULL,
    `admin_id` INT DEFAULT NULL,
    `reply_text` TEXT NOT NULL,
    `email_status` ENUM('sent','failed') NOT NULL DEFAULT 'failed',
    `email_error` VARCHAR(500) DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reply_message` (`message_id`),
    CONSTRAINT `fk_reply_message` FOREIGN KEY (`message_id`) REFERENCES `contact_messages` (`message_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

