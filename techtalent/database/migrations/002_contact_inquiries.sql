-- Contact inquiry improvements: read tracking, private admin notes, reply history.
-- (The PHP code also applies these automatically; run this only if you prefer manual migrations.)

ALTER TABLE `contact_messages`
    ADD COLUMN `read_at` DATETIME DEFAULT NULL,
    ADD COLUMN `admin_note` TEXT DEFAULT NULL;

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

-- Import replies saved by the old single-reply version (run once).
INSERT INTO `contact_replies` (`message_id`, `admin_id`, `reply_text`, `email_status`, `email_error`, `sent_at`, `created_at`)
SELECT `message_id`, `replied_by`, `reply_message`, 'sent', 'Imported from earlier version', `replied_at`, COALESCE(`replied_at`, NOW())
FROM `contact_messages`
WHERE `reply_message` IS NOT NULL AND `reply_message` <> '';
