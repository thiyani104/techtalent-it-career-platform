<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$preset_email = $_SESSION['reset_email'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($otp) || empty($new_password)) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'The passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            // Verify OTP and expiry
            $stmt = $pdo->prepare('SELECT id FROM password_resets WHERE email = :email AND otp_code = :otp AND expires_at >= NOW() LIMIT 1');
            $stmt->execute([':email' => $email, ':otp' => $otp]);
            $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resetRecord) {
                // Hash new password
                $newHash = password_hash($new_password, PASSWORD_BCRYPT);

                // Update user's password
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE email = :email');
                $update->execute([':hash' => $newHash, ':email' => $email]);

                // Delete used OTP
                $del = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
                $del->execute([':email' => $email]);

                unset($_SESSION['reset_email']);
                header('Location: login.php?reset=success');
                exit();
            } else {
                $error = 'Invalid or expired OTP code. Please request a new one.';
            }
        } catch (PDOException $e) {
            error_log('Reset Error: ' . $e->getMessage());
            $error = 'An error occurred while resetting your password.';
        }
    }
}

$page_title = 'Reset Password | TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    body { background: #0d1b2a; color: #fff; }
    .auth-wrapper {
        min-height: calc(100vh - 160px);
        display: flex;
        align-items: center;
        padding: 40px 15px;
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
        padding: 12px 14px;
        border-radius: 10px;
    }
    .btn-brand-green {
        background-color: #22c55e;
        color: #ffffff;
        font-weight: 700;
        padding: 13px;
        border-radius: 10px;
        border: none;
        width: 100%;
    }
    .btn-brand-green:hover { background-color: #16a34a; }
</style>

<div class="auth-wrapper">
    <div class="container d-flex justify-content-center">
        <div class="card-auth p-4 p-md-5">
            <h3 class="fw-bold mb-1">Verify OTP & Reset</h3>
            <p class="text-muted small mb-4">Enter the 6-digit OTP sent to your email and your new password.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Registered Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string)$preset_email, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">6-Digit OTP Code</label>
                    <input type="text" name="otp" class="form-control text-center fw-bold fs-4" maxlength="6" placeholder="000000" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-brand-green text-uppercase">UPDATE PASSWORD</button>
            </form>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>