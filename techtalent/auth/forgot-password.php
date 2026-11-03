<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your registered email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT user_id, full_name, email FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate secure 6-digit OTP
                $otp = (string)random_int(100000, 999999);
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Invalidate old tokens for this email
                $del = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
                $del->execute([':email' => $email]);

                // Store new OTP
                $ins = $pdo->prepare('INSERT INTO password_resets (email, otp_code, expires_at) VALUES (:email, :otp, :exp)');
                $ins->execute([
                    ':email' => $email,
                    ':otp'   => $otp,
                    ':exp'   => $expires_at
                ]);

                // Send real email via PHPMailer
                $mailSent = sendOtpEmail($user['email'], $user['full_name'], $otp);

                // sendOtpEmail() returns true on success or an error string on failure,
                // so it must be compared strictly (a non-empty string is "truthy").
                if ($mailSent === true) {
                    $_SESSION['reset_email'] = $email;
                    header('Location: reset-password.php');
                    exit();
                } else {
                    error_log('OTP email failed for ' . $email . ': ' . (string)$mailSent);
                    $error = 'We could not send the verification email right now. Please try again later or contact support.';
                }
            } else {
                // Security generic message or notification
                $error = 'No registered account found with that email address.';
            }
        } catch (PDOException $e) {
            error_log('Forgot Password Error: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again later.';
        }
    }
}

$page_title = 'Forgot Password | TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    body {
        background: #0d1b2a;
        color: #fff;
    }
    .auth-wrapper {
        min-height: calc(100vh - 160px);
        display: flex;
        align-items: center;
        padding: 40px 15px;
        background: radial-gradient(circle at 15% 25%, rgba(30, 58, 138, 0.3) 0%, transparent 45%),
                    radial-gradient(circle at 85% 75%, rgba(14, 165, 233, 0.2) 0%, transparent 45%);
    }
    .card-auth {
        background: #ffffff;
        border-radius: 1.25rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        color: #1e293b;
        max-width: 440px;
        width: 100%;
        margin: 0 auto;
    }
    .form-control {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 13px 15px;
        border-radius: 10px;
        font-size: 0.95rem;
    }
    .form-control:focus {
        background-color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .btn-brand-green {
        background-color: #22c55e;
        color: #ffffff;
        font-weight: 700;
        letter-spacing: 0.5px;
        padding: 14px;
        border-radius: 10px;
        border: none;
        width: 100%;
        transition: all 0.2s ease;
    }
    .btn-brand-green:hover {
        background-color: #16a34a;
        color: #ffffff;
    }
    .preview-card {
        background: #ffffff;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        display: inline-flex;
        align-items: center;
        gap: 14px;
        color: #1e293b;
        border: 1px solid rgba(255,255,255,0.2);
    }
</style>

<div class="auth-wrapper">
    <div class="container" style="max-width: 1100px;">
        <div class="row align-items-center g-5">
            
            <!-- Left Column: Graphic Showcase -->
            <div class="col-lg-6 d-none d-lg-block">
                <div class="pe-lg-4">
                    <span class="badge bg-primary bg-opacity-25 text-info px-3 py-2 rounded-pill mb-3">
                        <i class="bi bi-shield-lock me-1"></i> Account Security
                    </span>
                    <h1 class="fw-bold display-5 text-white mb-3">Recover Your TechTalent Portal Access.</h1>
                    <p class="text-secondary fs-5 mb-4">We will send a 6-digit real-time verification code directly to your email address.</p>

                    <div class="d-flex flex-column gap-3 mt-4">
                        <div class="preview-card" style="width: 300px;">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 fs-4">
                                <i class="bi bi-key-fill"></i>
                            </div>
                            <div>
                                <div class="fw-bold small">Instant OTP Verification</div>
                                <small class="text-muted">10-Minute Expiring Code</small>
                            </div>
                            <span class="badge bg-success ms-auto"><i class="bi bi-check2"></i></span>
                        </div>

                        <div class="preview-card ms-4" style="width: 320px;">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 fs-4">
                                <i class="bi bi-envelope-check"></i>
                            </div>
                            <div>
                                <div class="fw-bold small">Direct Inbox Delivery</div>
                                <small class="text-muted">Secured via SMTP</small>
                            </div>
                            <span class="badge bg-primary ms-auto">SSL</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Forgot Password Card -->
            <div class="col-lg-6 d-flex justify-content-center">
                <div class="card-auth p-4 p-md-5">
                    
                    <h3 class="fw-bold mb-1">Lost Your Password?</h3>
                    <p class="text-muted small mb-4">Please enter your email address to receive a verification OTP.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-secondary mb-1">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter your email address">
                        </div>

                        <button type="submit" class="btn btn-brand-green text-uppercase fw-bold">SEND ME THE CODE</button>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">Remember your password? <a href="login.php" class="text-primary fw-bold text-decoration-none">Back to Login</a></small>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>