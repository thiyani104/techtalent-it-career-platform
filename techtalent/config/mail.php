<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Load e-mail settings once. Order of priority:
 *   1. environment variables (TECHTALENT_SMTP_PASSWORD, ...)
 *   2. config/mail_settings.php
 *   3. config/mail_settings.example.php (fallback so nothing crashes)
 */
function techtalent_mail_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $file = is_file(__DIR__ . '/mail_settings.php') ? __DIR__ . '/mail_settings.php' : __DIR__ . '/mail_settings.example.php';
    $settings = is_file($file) ? (array)(require $file) : [];

    $defaults = [
        'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
        'smtp_username' => '', 'smtp_password' => '', 'from_email' => '', 'from_name' => 'TechTalent Solutions',
        'reply_to_email' => '', 'admin_notify_email' => '', 'send_acknowledgement' => true,
        'verify_ssl' => false, 'timeout' => 10,
    ];
    $settings = array_merge($defaults, $settings);

    $env = [
        'smtp_host' => 'TECHTALENT_SMTP_HOST', 'smtp_port' => 'TECHTALENT_SMTP_PORT', 'smtp_encryption' => 'TECHTALENT_SMTP_ENCRYPTION',
        'smtp_username' => 'TECHTALENT_SMTP_USERNAME', 'smtp_password' => 'TECHTALENT_SMTP_PASSWORD',
        'from_email' => 'TECHTALENT_FROM_EMAIL', 'admin_notify_email' => 'TECHTALENT_ADMIN_NOTIFY_EMAIL',
    ];
    foreach ($env as $key => $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            $settings[$key] = $value;
        }
    }
    return $settings;
}

/** True when a real SMTP password has been supplied. */
function techtalent_mail_is_configured(): bool
{
    $s = techtalent_mail_settings();
    $pass = trim((string)$s['smtp_password']);
    return $pass !== '' && $pass !== 'YOUR_16_CHAR_APP_PASSWORD' && trim((string)$s['smtp_username']) !== '';
}

/**
 * Build a ready-to-use PHPMailer instance from the settings file.
 * Throws \RuntimeException when SMTP has not been configured yet.
 */
function techtalent_create_mailer(): PHPMailer
{
    if (!techtalent_mail_is_configured()) {
        throw new \RuntimeException('SMTP is not configured. Add your Gmail App Password in config/mail_settings.php.');
    }
    $s = techtalent_mail_settings();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $s['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $s['smtp_username'];
    $mail->Password   = $s['smtp_password'];
    $enc = strtolower((string)$s['smtp_encryption']);
    if ($enc === 'none') {            // plain SMTP, only for local test servers such as MailHog
        $mail->SMTPSecure  = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port       = (int)$s['smtp_port'];
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = max(3, (int)$s['timeout']);
    $mail->isHTML(true);

    if (empty($s['verify_ssl'])) {
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
        ]];
    }

    $fromEmail = trim((string)$s['from_email']) !== '' ? $s['from_email'] : $s['smtp_username'];
    $mail->setFrom($fromEmail, $s['from_name']);
    $replyTo = trim((string)$s['reply_to_email']) !== '' ? $s['reply_to_email'] : $fromEmail;
    $mail->addReplyTo($replyTo, $s['from_name'] . ' Support');

    return $mail;
}

function techtalent_mail_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Remove line breaks so a user-supplied subject cannot inject mail headers. */
function techtalent_mail_clean_subject($subject, string $fallback): string
{
    $clean = trim(preg_replace('/[\r\n]+/', ' ', (string)$subject));
    return $clean !== '' ? mb_substr($clean, 0, 180) : $fallback;
}

/** Shared HTML wrapper used by every outgoing message. */
function techtalent_mail_layout(string $innerHtml): string
{
    return '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#1f2937;line-height:1.65">'
        . '<h2 style="color:#123b70;margin-bottom:4px">TechTalent Solutions</h2>'
        . '<hr style="border:none;border-top:2px solid #2563eb;margin:0 0 18px">'
        . $innerHtml
        . '<p style="margin-top:26px">Regards,<br><strong>TechTalent Solutions Support Team</strong></p>'
        . '<p style="font-size:12px;color:#94a3b8;margin-top:22px">This message was sent by TechTalent Solutions.</p></div>';
}

/**
 * Run a sending routine and convert every failure into a readable string.
 * Returns true on success, otherwise an error message.
 */
function techtalent_mail_run(callable $build)
{
    $mail = null;
    try {
        $mail = techtalent_create_mailer();
        $build($mail);
        $mail->send();
        return true;
    } catch (Exception $e) {
        $detail = ($mail && $mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
        error_log('TechTalent mail failed: ' . $detail);
        return 'SMTP error: ' . $detail;
    } catch (\Throwable $e) {
        error_log('TechTalent mail failed: ' . $e->getMessage());
        return $e->getMessage();
    }
}

/** Password-reset OTP. Returns true or an error string. */
function sendOtpEmail($recipientEmail, $recipientName, $otpCode)
{
    return techtalent_mail_run(function (PHPMailer $mail) use ($recipientEmail, $recipientName, $otpCode) {
        $mail->addAddress($recipientEmail, $recipientName ?: 'TechTalent User');
        $mail->Subject = 'Your Password Reset OTP - TechTalent';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 25px; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #0d1b2a;'>Password Reset Verification</h2>
                <p>Hello <strong>" . techtalent_mail_h($recipientName) . "</strong>,</p>
                <p>Use the 6-digit code below to reset your password:</p>
                <div style='font-size: 30px; font-weight: bold; letter-spacing: 6px; color: #16a34a; padding: 12px 0;'>"
                    . techtalent_mail_h($otpCode) . "</div>
                <p style='color: #64748b;'>Expires in 10 minutes. If you did not request this, please ignore.</p>
            </div>";
        $mail->AltBody = "Your TechTalent password reset code is {$otpCode}. It expires in 10 minutes.";
    });
}

/**
 * Admin's reply to a Contact Us message.
 * $originalMessage (optional) is quoted underneath so the sender has context.
 * Returns true or an error string.
 */
function sendContactReplyEmail($recipientEmail, $recipientName, $subject, $replyMessage, $originalMessage = null)
{
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return 'The sender email address is invalid.';
    }
    return techtalent_mail_run(function (PHPMailer $mail) use ($recipientEmail, $recipientName, $subject, $replyMessage, $originalMessage) {
        $mail->addAddress($recipientEmail, $recipientName ?: 'TechTalent User');
        $mail->Subject = 'Re: ' . techtalent_mail_clean_subject($subject, 'Your TechTalent inquiry');

        $name  = techtalent_mail_h($recipientName ?: 'there');
        $reply = nl2br(techtalent_mail_h($replyMessage));
        $html  = "<p>Hello {$name},</p>"
               . '<p>Thank you for contacting TechTalent Solutions. Please find our response below:</p>'
               . "<div style=\"padding:16px;border-left:4px solid #2563eb;background:#f3f6fb;border-radius:6px\">{$reply}</div>";
        if ($originalMessage !== null && trim((string)$originalMessage) !== '') {
            $html .= '<p style="margin-top:22px;font-size:13px;color:#64748b">Your original message:</p>'
                  . '<div style="padding:12px 14px;border-left:3px solid #cbd5e1;color:#64748b;font-size:13px">'
                  . nl2br(techtalent_mail_h($originalMessage)) . '</div>';
        }
        $html .= '<p>If you have further questions, simply reply to this email.</p>';
        $mail->Body = techtalent_mail_layout($html);
        $mail->AltBody = 'Hello ' . ($recipientName ?: 'there') . ",\n\nThank you for contacting TechTalent Solutions. Our response:\n\n"
            . (string)$replyMessage . "\n\nRegards,\nTechTalent Solutions Support Team";
    });
}

/** "We received your message" e-mail. Silent no-op (returns true) when disabled/unconfigured. */
function sendContactAcknowledgementEmail($recipientEmail, $recipientName, $subject)
{
    $s = techtalent_mail_settings();
    if (empty($s['send_acknowledgement']) || !techtalent_mail_is_configured()) {
        return true;
    }
    return techtalent_mail_run(function (PHPMailer $mail) use ($recipientEmail, $recipientName, $subject) {
        $mail->addAddress($recipientEmail, $recipientName ?: 'TechTalent User');
        $mail->Subject = 'We received your message - TechTalent Solutions';
        $html = '<p>Hello ' . techtalent_mail_h($recipientName ?: 'there') . ',</p>'
              . '<p>Thank you for contacting us about <strong>' . techtalent_mail_h(techtalent_mail_clean_subject($subject, 'your inquiry')) . '</strong>. '
              . 'Our team has received your message and will reply to this email address as soon as possible.</p>';
        $mail->Body = techtalent_mail_layout($html);
        $mail->AltBody = "Hello,\n\nWe received your message and will reply to this email address soon.\n\nTechTalent Solutions Support Team";
    });
}

/** Alert the administrator about a new Contact Us message. Silent no-op when not configured. */
function sendNewInquiryAdminNotification(array $inquiry)
{
    $s = techtalent_mail_settings();
    $to = trim((string)$s['admin_notify_email']);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !techtalent_mail_is_configured()) {
        return true;
    }
    return techtalent_mail_run(function (PHPMailer $mail) use ($to, $inquiry) {
        $mail->addAddress($to);
        if (!empty($inquiry['email']) && filter_var($inquiry['email'], FILTER_VALIDATE_EMAIL)) {
            $mail->clearReplyTos();
            $mail->addReplyTo($inquiry['email'], $inquiry['name'] ?? '');
        }
        $mail->Subject = 'New contact message: ' . techtalent_mail_clean_subject($inquiry['subject'] ?? '', 'General Inquiry');
        $html = '<p>A new message was submitted on the Contact Us page.</p><table style="font-size:14px">'
              . '<tr><td><strong>Name</strong></td><td>' . techtalent_mail_h($inquiry['name'] ?? '') . '</td></tr>'
              . '<tr><td><strong>Email</strong></td><td>' . techtalent_mail_h($inquiry['email'] ?? '') . '</td></tr>'
              . '<tr><td><strong>Type</strong></td><td>' . techtalent_mail_h($inquiry['sender_type'] ?? '') . '</td></tr></table>'
              . '<div style="margin-top:12px;padding:14px;background:#f3f6fb;border-radius:6px">' . nl2br(techtalent_mail_h($inquiry['message'] ?? '')) . '</div>'
              . '<p>Log in to the admin portal &rarr; Contact Inquiries to reply.</p>';
        $mail->Body = techtalent_mail_layout($html);
        $mail->AltBody = 'New contact message from ' . ($inquiry['name'] ?? '') . ' <' . ($inquiry['email'] ?? '') . ">\n\n" . ($inquiry['message'] ?? '');
    });
}
