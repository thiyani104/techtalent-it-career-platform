<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $dest = '../guest/index.php';
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'admin':
                $dest = '../admin/dashboard.php';
                break;
            case 'employer':
                $dest = '../employer/dashboard.php';
                break;
            case 'job_seeker':
            case 'jobseeker':
                $dest = '../jobseeker/dashboard.php';
                break;
        }
    }
    header("Location: $dest");
    exit();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please provide both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (strtolower($user['status']) === 'inactive') {
                    $error = 'Your account has been deactivated. Please contact support.';
                } else {
                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['full_name'];

                    // Role redirect
                    $dest = '../guest/index.php';
                    switch ($user['role']) {
                        case 'admin':
                            $dest = '../admin/dashboard.php';
                            break;
                        case 'employer':
                            $dest = '../employer/dashboard.php';
                            break;
                        default:
                            $dest = '../jobseeker/dashboard.php';
                            break;
                    }
                    header("Location: $dest");
                    exit();
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Server error. Please try again later.';
        }
    }
}

$page_title = 'Login | TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    body {
        background: #07172d;
        color: #fff;
    }
    .auth-wrapper {
        min-height: calc(100vh - 160px);
        display: flex;
        align-items: center;
        padding: 40px 15px;
        background:
            radial-gradient(ellipse at 12% 18%, rgba(37, 99, 235, 0.22) 0%, transparent 42%),
            radial-gradient(ellipse at 88% 78%, rgba(14, 165, 233, 0.19) 0%, transparent 40%),
            linear-gradient(125deg, #07172d 0%, #102b4b 58%, #0c3548 100%);
        background-color: #07172d;
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
    .brand-tab-group {
        display: flex;
        border-radius: 10px;
        background: #f1f5f9;
        padding: 4px;
        margin-bottom: 24px;
    }
    .brand-tab {
        flex: 1;
        text-align: center;
        padding: 10px 0;
        font-weight: 700;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    .brand-tab.active {
        background-color: #0f172a;
        color: #ffffff;
    }
    .brand-tab.inactive {
        color: #64748b;
    }
    .brand-tab.inactive:hover {
        color: #0f172a;
    }
    .form-control {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 12px 14px;
        border-radius: 10px;
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
        padding: 13px;
        border-radius: 10px;
        border: none;
        width: 100%;
        transition: all 0.2s ease;
    }
    .btn-brand-green:hover {
        background-color: #16a34a;
        color: #ffffff;
    }
    .btn-reset-now {
        color: #0284c7;
        border: 1.5px solid #0284c7;
        font-weight: 700;
        font-size: 0.78rem;
        padding: 4px 12px;
        border-radius: 6px;
        text-decoration: none;
        text-transform: uppercase;
        display: inline-block;
    }
    .btn-reset-now:hover {
        background-color: #0284c7;
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
            
            <!-- Left Hero Illustration / Feature List -->
            <div class="col-lg-6 d-none d-lg-block">
                <div class="pe-lg-4">
                    <span class="badge bg-primary bg-opacity-25 text-info px-3 py-2 rounded-pill mb-3">
                        <i class="bi bi-shield-check me-1"></i> Verified Tech Network
                    </span>
                    <h1 class="fw-bold display-5  mb-3" style="color: #0b2a52;">Connecting Visionary Companies & Elite Tech Talent.</h1>
                    <p class="text-secondary fs-5 mb-4">Access ATS screening, match scoring, and direct IT recruitment pipelines.</p>

                    <div class="d-flex flex-column gap-3 mt-4">
                        <div class="preview-card" style="width: 290px;">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 fs-4">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div>
                                <div class="fw-bold small">Verified Job Seekers</div>
                                <small class="text-muted">ATS Resume Profiles</small>
                            </div>
                            <span class="badge bg-success ms-auto"><i class="bi bi-check2"></i></span>
                        </div>

                        <div class="preview-card ms-4" style="width: 320px;">
                            <div class="rounded-circle bg-info bg-opacity-10 text-info p-2 fs-4">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <div class="fw-bold small">Verified Recruiters</div>
                                <small class="text-muted">Direct Technical Interviews</small>
                            </div>
                            <span class="badge bg-primary ms-auto">Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Login Card -->
            <div class="col-lg-6 d-flex justify-content-center">
                <div class="card-auth p-4 p-md-5">
                    
                    <!-- Tab Switcher -->
                    <div class="brand-tab-group">
                        <a href="login.php" class="brand-tab active">Login</a>
                        <a href="register.php" class="brand-tab inactive">Register</a>
                    </div>

                    <h4 class="fw-bold mb-1">Account Login</h4>
                    <p class="text-muted small mb-4">Please enter your credentials to access your portal.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') ?>" placeholder="name@domain.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="pwd" class="form-control" placeholder="••••••••" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePwd()">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="small text-muted">Forgot Password?</span>
                            <a href="forgot-password.php" class="btn-reset-now">Reset Now</a>
                        </div>

                        <button type="submit" class="btn btn-brand-green">LOGIN</button>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">Don’t have an account? <a href="register.php" class="text-dark fw-bold text-decoration-none">Register Now</a></small>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function togglePwd(){
    const fld = document.getElementById('pwd');
    const ic = document.getElementById('eyeIcon');
    if (fld.type === 'password') {
        fld.type = 'text';
        ic.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        fld.type = 'password';
        ic.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>