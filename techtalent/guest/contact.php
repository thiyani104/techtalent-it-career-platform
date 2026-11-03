<?php
ob_start();
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/contact-functions.php';
require_once __DIR__ . '/../config/mail.php';

$page_title = 'Contact Us | TechTalent Solutions';

$success_message = '';
$error_message = '';
$name = $email = $phone = $message = '';
$subject = 'General Inquiry';
$sender_type = 'General Inquiry';

// Create / upgrade the contact tables (shared with the admin inquiry manager).
try {
    contact_ensure_schema($pdo);
} catch (PDOException $e) {
    error_log('Contact table creation error: ' . $e->getMessage());
}

if (empty($_SESSION['contact_csrf_token'])) {
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $sender_type = trim($_POST['sender_type'] ?? 'General Inquiry');
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $allowedSenderTypes = ['Job Seeker', 'Employer', 'General Inquiry'];
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['contact_csrf_token'], $csrf_token)) {
        $error_message = 'Your session has expired. Please refresh the page and try again.';
    } elseif (trim($_POST['website'] ?? '') !== '') {
        // Honeypot field filled in: this is a bot. Pretend success and store nothing.
        $success_message = 'Thank you for contacting TechTalent Solutions. Our team will get back to you soon.';
        $name = $email = $phone = $message = '';
    } elseif ($name === '' || $email === '' || $subject === '' || $message === '') {
        $error_message = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!in_array($sender_type, $allowedSenderTypes, true)) {
        $error_message = 'Please select a valid contact type.';
    } elseif (mb_strlen($subject) > 180) {
        $error_message = 'Subject is too long. Please use 180 characters or fewer.';
    } elseif (mb_strlen($name) > 150) {
        $error_message = 'Name is too long. Please use 150 characters or fewer.';
    } elseif (mb_strlen($message) > 5000) {
        $error_message = 'Message is too long. Please use 5,000 characters or fewer.';
    } else {
        try {
            // Simple flood protection: at most 5 messages per e-mail address per hour.
            $recent = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE email = :email AND created_at > (NOW() - INTERVAL 1 HOUR)');
            $recent->execute([':email' => $email]);
            if ((int)$recent->fetchColumn() >= 5) {
                throw new RuntimeException('rate_limited');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages (name, email, phone, sender_type, subject, message) VALUES (:name, :email, :phone, :sender_type, :subject, :message)'
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone !== '' ? $phone : null,
                ':sender_type' => $sender_type,
                ':subject' => $subject,
                ':message' => $message
            ]);

            // Best-effort e-mails: a mail problem must never block the visitor's submission.
            sendContactAcknowledgementEmail($email, $name, $subject);
            sendNewInquiryAdminNotification([
                'name' => $name, 'email' => $email, 'sender_type' => $sender_type,
                'subject' => $subject, 'message' => $message,
            ]);

            $success_message = 'Thank you for contacting TechTalent Solutions. Our team will get back to you soon.';
            $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
            $name = $email = $phone = $message = '';
            $subject = 'General Inquiry';
            $sender_type = 'General Inquiry';
        } catch (RuntimeException $e) {
            $error_message = 'You have sent several messages recently. Please wait a little while before sending another.';
        } catch (PDOException $e) {
            error_log('Contact form submission error: ' . $e->getMessage());
            $error_message = 'We could not send your message right now. Please try again later.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    .contact-page {
        background: linear-gradient(180deg, #f7faff 0%, #ffffff 48%, #f8fafc 100%);
        padding: 70px 0 80px;
        min-height: calc(100vh - 80px);
    }

    .contact-heading .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 999px;
        background: rgba(0, 123, 255, 0.09);
        color: #007bff;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 14px;
    }

    .contact-heading h1 {
        color: #0f2137;
        font-weight: 800;
        font-size: clamp(2.1rem, 4vw, 3.2rem);
        margin-bottom: 12px;
        letter-spacing: -1px;
    }

    .contact-heading p {
        color: #64748b;
        max-width: 720px;
        margin: 0 auto;
        font-size: 1.05rem;
        line-height: 1.7;
    }

    .contact-card {
        max-width: 1180px;
        margin: 42px auto 0;
        background: #ffffff;
        border-radius: 26px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 33, 55, 0.12);
        border: 1px solid #e8eef5;
    }

    .contact-info-panel {
        height: 100%;
        min-height: 650px;
        padding: 46px 42px 38px;
        color: #ffffff;
        background:
            radial-gradient(circle at 20% 18%, rgba(0, 123, 255, 0.35), transparent 30%),
            linear-gradient(145deg, #0f2137 0%, #123b69 62%, #007bff 150%);
        position: relative;
        overflow: hidden;
    }

    .contact-info-panel::after {
        content: '';
        position: absolute;
        width: 280px;
        height: 280px;
        right: -120px;
        bottom: -120px;
        border: 45px solid rgba(255,255,255,0.07);
        border-radius: 50%;
    }

    .contact-info-panel h2 {
        font-weight: 800;
        font-size: 2rem;
        margin-bottom: 12px;
        position: relative;
        z-index: 1;
    }

    .contact-info-panel > p {
        color: rgba(255,255,255,0.78);
        line-height: 1.7;
        position: relative;
        z-index: 1;
    }

    .support-illustration {
        height: 230px;
        margin: 28px 0 30px;
        border-radius: 22px;
        background: linear-gradient(135deg, rgba(255,255,255,0.14), rgba(255,255,255,0.04));
        border: 1px solid rgba(255,255,255,0.14);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .support-illustration .circle {
        width: 135px;
        height: 135px;
        border-radius: 50%;
        background: rgba(255,255,255,0.14);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .support-illustration .circle i {
        font-size: 4.6rem;
        color: #ffffff;
    }

    .support-badge {
        position: absolute;
        background: #ffffff;
        color: #0f2137;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 0.82rem;
        font-weight: 700;
        box-shadow: 0 10px 25px rgba(0,0,0,0.16);
    }

    .support-badge.one { top: 25px; left: 22px; }
    .support-badge.two { right: 22px; bottom: 25px; }

    .contact-details {
        position: relative;
        z-index: 1;
    }

    .contact-detail {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        margin-top: 22px;
    }

    .contact-detail .icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.12);
        color: #ffffff;
        font-size: 1.15rem;
    }

    .contact-detail strong {
        display: block;
        margin-bottom: 3px;
        font-size: 0.92rem;
    }

    .contact-detail span {
        color: rgba(255,255,255,0.74);
        font-size: 0.9rem;
        line-height: 1.55;
    }

    .contact-form-panel {
        padding: 48px 48px 42px;
    }

    .contact-form-panel h2 {
        color: #0f2137;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .form-intro {
        color: #64748b;
        margin-bottom: 28px;
        line-height: 1.6;
    }

    .contact-form .form-label {
        color: #1e293b;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .contact-form .form-control {
        min-height: 54px;
        border: 1px solid #dfe7f0;
        border-radius: 12px;
        padding: 13px 15px;
        color: #1e293b;
        background: #fbfcfe;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .contact-form textarea.form-control {
        min-height: 150px;
        resize: vertical;
    }

    .contact-form .form-control:focus {
        background: #ffffff;
        border-color: #007bff;
        box-shadow: 0 0 0 4px rgba(0,123,255,0.11);
    }

    .required-mark { color: #dc3545; }

    .btn-send-message {
        min-height: 54px;
        padding: 0 30px;
        border: 0;
        border-radius: 12px;
        background: #007bff;
        color: #ffffff;
        font-weight: 700;
        box-shadow: 0 10px 22px rgba(0,123,255,0.22);
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .btn-send-message:hover {
        background: #0056b3;
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 14px 28px rgba(0,123,255,0.28);
    }

    .response-alert {
        border: 0;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 24px;
    }

    .contact-note {
        margin-top: 18px;
        color: #94a3b8;
        font-size: 0.82rem;
    }

    .contact-note i { color: #007bff; }

    @media (max-width: 991.98px) {
        .contact-info-panel { min-height: auto; }
        .contact-form-panel { padding: 38px 30px; }
    }

    @media (max-width: 575.98px) {
        .contact-page { padding: 45px 0 60px; }
        .contact-card { border-radius: 18px; }
        .contact-info-panel { padding: 34px 24px; }
        .contact-form-panel { padding: 32px 22px; }
        .support-illustration { height: 190px; }
    }
</style>

<main class="contact-page">
    <div class="container">
        <div class="contact-heading text-center">
            <span class="eyebrow"><i class="bi bi-headset"></i> We're here to help</span>
            <h1>Contact TechTalent Solutions</h1>
            <p>
                Have a question about a job, your application, hiring, or our platform?
                Send us a message and our support team will be happy to help.
            </p>
        </div>

        <div class="contact-card">
            <div class="row g-0">
                <div class="col-lg-5">
                    <section class="contact-info-panel">
                        <h2>Let's talk.</h2>
                        <p>
                            We connect talented IT professionals with great career opportunities.
                            If you need support, reach out to us using any of the options below.
                        </p>

                        <div class="support-illustration" aria-hidden="true">
                            <span class="support-badge one"><i class="bi bi-check-circle-fill text-success me-1"></i> Quick support</span>
                            <div class="circle">
                                <i class="bi bi-headset"></i>
                            </div>
                            <span class="support-badge two"><i class="bi bi-chat-dots-fill text-primary me-1"></i> We're listening</span>
                        </div>

                        <div class="contact-details">
                            <div class="contact-detail">
                                <div class="icon"><i class="bi bi-geo-alt-fill"></i></div>
                                <div>
                                    <strong>Office Address</strong>
                                    <span>100 Tech Plaza, Innovation Way,<br>Tech Hub Suite 400</span>
                                </div>
                            </div>

                            <div class="contact-detail">
                                <div class="icon"><i class="bi bi-telephone-fill"></i></div>
                                <div>
                                    <strong>Phone</strong>
                                    <span>+1 (800) 555-TECH</span>
                                </div>
                            </div>

                            <div class="contact-detail">
                                <div class="icon"><i class="bi bi-envelope-fill"></i></div>
                                <div>
                                    <strong>Email</strong>
                                    <span>support@techtalent.com</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-7">
                    <section class="contact-form-panel">
                        <h2>Send us a message</h2>
                        <p class="form-intro">
                            Complete the form below. Fields marked with <span class="required-mark">*</span> are required.
                        </p>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success response-alert" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger response-alert" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="contact-form" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['contact_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <div style="position:absolute;left:-9999px;" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Full Name <span class="required-mark">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control" maxlength="120"
                                           value="<?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="Enter your full name" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address <span class="required-mark">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" maxlength="180"
                                           value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="you@example.com" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="sender_type" class="form-label">I am a <span class="required-mark">*</span></label>
                                    <select id="sender_type" name="sender_type" class="form-control" required>
                                        <?php foreach (['Job Seeker', 'Employer', 'General Inquiry'] as $senderOption): ?>
                                            <option value="<?= htmlspecialchars($senderOption, ENT_QUOTES, 'UTF-8') ?>" <?= (($sender_type ?? 'General Inquiry') === $senderOption) ? 'selected' : '' ?>><?= htmlspecialchars($senderOption, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label for="subject" class="form-label">Subject <span class="required-mark">*</span></label>
                                    <input type="text" id="subject" name="subject" class="form-control" maxlength="180"
                                           value="<?= htmlspecialchars($subject ?? 'General Inquiry', ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="What do you need help with?" required>
                                </div>

                                <div class="col-12">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="30"
                                           value="<?= htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="Enter your phone number">
                                </div>

                                <div class="col-12">
                                    <label for="message" class="form-label">Message <span class="required-mark">*</span></label>
                                    <textarea id="message" name="message" class="form-control" maxlength="5000"
                                              placeholder="How can we help you?" required><?= htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>

                                <div class="col-12 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pt-1">
                                    <div class="contact-note">
                                        <i class="bi bi-shield-check me-1"></i>
                                        Your information is handled securely.
                                    </div>
                                    <button type="submit" class="btn btn-send-message">
                                        <i class="bi bi-send-fill me-2"></i> Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>