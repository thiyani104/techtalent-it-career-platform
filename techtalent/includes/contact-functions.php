<?php
/**
 * Shared helpers for the Contact Us form and the admin inquiry manager.
 */

function contact_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Create / upgrade the contact tables. Safe to call on every request.
 *  - contact_messages : what visitors submit (+ status, read time, private admin note)
 *  - contact_replies  : full reply history, one row per reply, with e-mail delivery status
 */
function contact_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(180) NOT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        sender_type VARCHAR(30) NOT NULL DEFAULT 'General Inquiry',
        subject VARCHAR(180) NOT NULL DEFAULT 'General Inquiry',
        message TEXT NOT NULL,
        reply_message TEXT DEFAULT NULL,
        replied_at DATETIME DEFAULT NULL,
        replied_by INT DEFAULT NULL,
        status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
        read_at DATETIME DEFAULT NULL,
        admin_note TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_contact_status (status),
        INDEX idx_contact_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Upgrade databases created by earlier versions of the project.
    $existing = $pdo->query('SHOW COLUMNS FROM contact_messages')->fetchAll(PDO::FETCH_COLUMN);
    $required = [
        'sender_type'   => "ALTER TABLE contact_messages ADD COLUMN sender_type VARCHAR(30) NOT NULL DEFAULT 'General Inquiry'",
        'subject'       => "ALTER TABLE contact_messages ADD COLUMN subject VARCHAR(180) NOT NULL DEFAULT 'General Inquiry'",
        'reply_message' => 'ALTER TABLE contact_messages ADD COLUMN reply_message TEXT DEFAULT NULL',
        'replied_at'    => 'ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME DEFAULT NULL',
        'replied_by'    => 'ALTER TABLE contact_messages ADD COLUMN replied_by INT DEFAULT NULL',
        'status'        => "ALTER TABLE contact_messages ADD COLUMN status ENUM('new','read','replied') NOT NULL DEFAULT 'new'",
        'read_at'       => 'ALTER TABLE contact_messages ADD COLUMN read_at DATETIME DEFAULT NULL',
        'admin_note'    => 'ALTER TABLE contact_messages ADD COLUMN admin_note TEXT DEFAULT NULL',
    ];
    foreach ($required as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec($sql);
        }
    }

    $repliesExisted = (bool)$pdo->query("SHOW TABLES LIKE 'contact_replies'")->fetchColumn();
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_replies (
        reply_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id INT UNSIGNED NOT NULL,
        admin_id INT DEFAULT NULL,
        reply_text TEXT NOT NULL,
        email_status ENUM('sent','failed') NOT NULL DEFAULT 'failed',
        email_error VARCHAR(500) DEFAULT NULL,
        sent_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reply_message (message_id),
        CONSTRAINT fk_reply_message FOREIGN KEY (message_id) REFERENCES contact_messages (message_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // One-time import of replies saved by the old single-reply version.
    if (!$repliesExisted) {
        $pdo->exec("INSERT INTO contact_replies (message_id, admin_id, reply_text, email_status, email_error, sent_at, created_at)
            SELECT message_id, replied_by, reply_message, 'sent', 'Imported from earlier version', replied_at, COALESCE(replied_at, NOW())
            FROM contact_messages
            WHERE reply_message IS NOT NULL AND reply_message <> ''");
    }

    $done = true;
}

/** Escape % and _ so user search text is matched literally by LIKE. */
function contact_like(string $text): string
{
    return '%' . addcslashes($text, '\\%_') . '%';
}

/** One-time flash message stored in the session (used by the redirect-after-POST pattern). */
function contact_flash_set(string $type, string $message): void
{
    $_SESSION['contact_flash'] = ['type' => $type, 'message' => $message];
}

function contact_flash_get(): ?array
{
    $flash = $_SESSION['contact_flash'] ?? null;
    unset($_SESSION['contact_flash']);
    return $flash;
}

/** Reply templates for the admin's quick-reply dropdown. {name} is replaced by the sender's name. */
function contact_reply_templates(): array
{
    return [
        'Thank you / received' => "Hello {name},\n\nThank you for contacting TechTalent Solutions. We have received your message and our team is looking into it. We will update you shortly.",
        'Account / login help' => "Hello {name},\n\nSorry you are having trouble with your account. Please try the \"Forgot password\" option on the login page. If the problem continues, reply with the email address you registered with and a short description of the error, and we will fix it for you.",
        'Employer: posting a job' => "Hello {name},\n\nThank you for your interest in posting a vacancy. Please log in to your employer account, open \"Post Job\" and complete the form. Your listing will appear once submitted. If you need help with any field, let us know.",
        'Job seeker: application help' => "Hello {name},\n\nThank you for your message. You can track every application from \"My Applications\" in your dashboard. If an application is not showing, please send us the job title and the date you applied and we will check it.",
        'Need more information' => "Hello {name},\n\nThank you for reaching out. To help you properly, could you please share a few more details (screenshots or the exact steps you took are very helpful)? We will respond as soon as we hear back.",
    ];
}
