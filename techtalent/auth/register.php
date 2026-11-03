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
            default:
                $dest = '../jobseeker/dashboard.php';
                break;
        }
    }
    header("Location: $dest");
    exit();
}

// Determine active role mode from query string
$role = (isset($_GET['role']) && $_GET['role'] === 'employer') ? 'employer' : 'job_seeker';

$error = '';
$success = '';

// Retained input values
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$gender = '';
$hometown = '';
$organization_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postRole = $_POST['role_type'] ?? 'job_seeker';
    $role = ($postRole === 'employer') ? 'employer' : 'job_seeker';

    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms            = isset($_POST['terms']);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields (*).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'The passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!$terms) {
        $error = 'You must agree to the Privacy Policy & Terms/Conditions.';
    } else {
        try {
            // Check if email is already registered
            $check = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $check->execute([':email' => $email]);

            if ($check->fetch()) {
                $error = 'An account with this email address already exists.';
            } else {
                $fullName = $first_name . ' ' . $last_name;
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                // Fetch existing table columns dynamically to prevent SQL 1054 errors
                $cols_stmt = $pdo->query("SHOW COLUMNS FROM users");
                $existing_user_cols = $cols_stmt->fetchAll(PDO::FETCH_COLUMN);

                if ($role === 'employer') {
                    $organization_name = trim($_POST['organization_name'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $logo_rel_path = null;

                    if (empty($organization_name)) {
                        $error = 'Organization Name is required.';
                    } else {
                        // Logo Upload Handling for Recruiter
                        if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                            $fileTmp  = $_FILES['company_logo']['tmp_name'];
                            $fileSize = $_FILES['company_logo']['size'];
                            $fileExt  = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
                            $allowed  = ['png', 'jpg', 'jpeg', 'webp'];

                            if (!in_array($fileExt, $allowed, true)) {
                                $error = 'Invalid logo format. Only PNG, JPG, and WEBP allowed.';
                            } elseif ($fileSize > 2 * 1024 * 1024) {
                                $error = 'Logo file size exceeds 2MB limit.';
                            } else {
                                $upDir = __DIR__ . '/../uploads/logos/';
                                if (!is_dir($upDir)) {
                                    mkdir($upDir, 0777, true);
                                }
                                $fName = 'logo_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $fileExt;
                                if (move_uploaded_file($fileTmp, $upDir . $fName)) {
                                    $logo_rel_path = 'uploads/logos/' . $fName;
                                }
                            }
                        }

                        if (empty($error)) {
                            // Dynamically construct insert fields matching existing DB columns
                            $insert_cols = ['full_name', 'email', 'password_hash', 'role', 'status', 'created_at'];
                            $insert_vals = [':n', ':e', ':h', "'employer'", "'active'", 'NOW()'];
                            $params = [
                                ':n' => $fullName,
                                ':e' => $email,
                                ':h' => $passwordHash
                            ];

                            if (in_array('company_name', $existing_user_cols, true)) {
                                $insert_cols[] = 'company_name';
                                $insert_vals[] = ':cname';
                                $params[':cname'] = $organization_name;
                            }

                            if (in_array('phone', $existing_user_cols, true) && !empty($phone)) {
                                $insert_cols[] = 'phone';
                                $insert_vals[] = ':phone';
                                $params[':phone'] = $phone;
                            }

                            if ($logo_rel_path) {
                                if (in_array('profile_image', $existing_user_cols, true)) {
                                    $insert_cols[] = 'profile_image';
                                    $insert_vals[] = ':pimg';
                                    $params[':pimg'] = $logo_rel_path;
                                }
                                if (in_array('logo_url', $existing_user_cols, true)) {
                                    $insert_cols[] = 'logo_url';
                                    $insert_vals[] = ':lurl';
                                    $params[':lurl'] = $logo_rel_path;
                                }
                                if (in_array('avatar', $existing_user_cols, true)) {
                                    $insert_cols[] = 'avatar';
                                    $insert_vals[] = ':avt';
                                    $params[':avt'] = $logo_rel_path;
                                }
                            }

                            $sql = "INSERT INTO users (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_vals) . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $userId = (int)$pdo->lastInsertId();

                            $_SESSION['user_id'] = $userId;
                            $_SESSION['role']    = 'employer';
                            $_SESSION['name']    = $fullName;
                            $_SESSION['company'] = $organization_name;

                            header("Location: ../employer/dashboard.php?registered=1");
                            exit();
                        }
                    }
                } else {
                    // Job Seeker flow
                    $gender   = trim($_POST['gender'] ?? '');
                    $hometown = trim($_POST['hometown'] ?? '');

                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status, created_at) VALUES (:n, :e, :h, 'job_seeker', 'active', NOW())");
                        $stmt->execute([':n' => $fullName, ':e' => $email, ':h' => $passwordHash]);
                        $assigned_role = 'job_seeker';
                    } catch (PDOException $ex) {
                        // Fallback if DB enum is 'jobseeker'
                        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status, created_at) VALUES (:n, :e, :h, 'jobseeker', 'active', NOW())");
                        $stmt->execute([':n' => $fullName, ':e' => $email, ':h' => $passwordHash]);
                        $assigned_role = 'jobseeker';
                    }

                    $userId = (int)$pdo->lastInsertId();

                    // Optional matching job_seeker_profiles record
                    try {
                        $pStmt = $pdo->prepare("INSERT INTO job_seeker_profiles (user_id, headline, location) VALUES (:uid, :headline, :loc)");
                        $pStmt->execute([
                            ':uid'      => $userId,
                            ':headline' => 'Tech Candidate',
                            ':loc'      => $hometown
                        ]);
                    } catch (PDOException $pe) {
                        // Silently continue if table is structured differently
                    }

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role']    = $assigned_role;
                    $_SESSION['name']    = $fullName;

                    header("Location: ../jobseeker/dashboard.php?registered=1");
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log('Register Error: ' . $e->getMessage());
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$page_title = ($role === 'employer' ? 'Recruiter Register' : 'Job Seeker Register') . ' | TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    body { background: #07172d; color: #fff; }
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
    .register-tagline-blue { color: #0b2f6b !important; }
    .card-auth {
        background: #ffffff;
        border-radius: 1.25rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        color: #1e293b;
        max-width: 630px;
        width: 100%;
        margin: 0 auto;
    }
    .brand-tab-group {
        display: flex;
        border-radius: 10px;
        background: #f1f5f9;
        padding: 4px;
        margin-bottom: 20px;
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
    .brand-tab.active { background-color: #0f172a; color: #ffffff; }
    .brand-tab.inactive { color: #64748b; }
    .brand-tab.inactive:hover { color: #0f172a; }

    .role-toggle-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 22px;
    }
    .role-btn {
        flex: 1;
        padding: 8px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        text-align: center;
        text-decoration: none;
        color: #475569;
        background: #f8fafc;
    }
    .role-btn.active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    .form-control {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 11px 14px;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    .form-control:focus {
        background-color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .file-input-wrapper { position: relative; overflow: hidden; display: block; }
    .file-input-wrapper input[type="file"] {
        position: absolute; top: 0; right: 0; min-width: 100%; min-height: 100%;
        font-size: 100px; text-align: right; opacity: 0; outline: none; cursor: pointer;
    }
    .file-upload-placeholder {
        display: flex; justify-content: space-between; align-items: center;
        background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
        padding: 11px 14px; color: #64748b; font-size: 0.9rem;
    }
    .btn-brand-green {
        background-color: #22c55e;
        color: #ffffff;
        font-weight: 700;
        letter-spacing: 0.5px;
        padding: 12px 28px;
        border-radius: 8px;
        border: none;
    }
    .btn-brand-green:hover { background-color: #16a34a; }

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
    <div class="container" style="max-width: 1200px;">
        <div class="row align-items-center g-5">
            
            <!-- Left Column: Visual Showcase -->
            <div class="col-lg-5 d-none d-lg-block">
                <div class="pe-lg-3">
                    <span class="badge bg-primary bg-opacity-25 text-info px-3 py-2 rounded-pill mb-3">
                        <i class="bi <?= $role === 'employer' ? 'bi-building-check' : 'bi-person-workspace' ?> me-1"></i> 
                        <?= $role === 'employer' ? 'Recruiter Hub' : 'Job Seeker Portal' ?>
                    </span>
                    <h1 class="fw-bold display-6 mb-3 <?= $role === 'employer' ? 'text-white' : 'register-tagline-blue' ?>">
                        <?= $role === 'employer' ? 'Hire Elite IT Engineers & Teams.' : 'Land High-Impact Tech Vacancies.' ?>
                    </h1>
                    <p class="text-secondary fs-6 mb-4">
                        <?= $role === 'employer' ? 'Post opportunities, run ATS match analytics, and hire verified software talent.' : 'Create your verified profile and connect directly with hiring managers.' ?>
                    </p>

                    <div class="d-flex flex-column gap-3 mt-4">
                        <div class="preview-card" style="width: 290px;">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 fs-4">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div>
                                <div class="fw-bold small">Direct Application</div>
                                <small class="text-muted">Instant profile sharing</small>
                            </div>
                            <span class="badge bg-success ms-auto"><i class="bi bi-check2"></i></span>
                        </div>

                        <div class="preview-card ms-4" style="width: 310px;">
                            <div class="rounded-circle bg-info bg-opacity-10 text-info p-2 fs-4">
                                <i class="bi bi-briefcase"></i>
                            </div>
                            <div>
                                <div class="fw-bold small">Top Tech Roles</div>
                                <small class="text-muted">High-priority vacancies</small>
                            </div>
                            <span class="badge bg-primary ms-auto">Live</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Registration Card -->
            <div class="col-lg-7 d-flex justify-content-center">
                <div class="card-auth p-4 p-md-5">
                    
                    <!-- Tabs: Login / Register -->
                    <div class="brand-tab-group">
                        <a href="login.php" class="brand-tab inactive">Login</a>
                        <a href="register.php?role=<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" class="brand-tab active">Register</a>
                    </div>

                    <!-- Role Switcher -->
                    <div class="role-toggle-bar">
                        <a href="register.php?role=jobseeker" class="role-btn <?= $role === 'job_seeker' ? 'active' : '' ?>">
                            <i class="bi bi-person me-1"></i> Job Seeker
                        </a>
                        <a href="register.php?role=employer" class="role-btn <?= $role === 'employer' ? 'active' : '' ?>">
                            <i class="bi bi-building me-1"></i> Recruiter / Employer
                        </a>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark"><?= $role === 'employer' ? 'Recruiter Register' : 'Job Seeker Register' ?></h5>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="role_type" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Employer Organization & Logo -->
                        <?php if ($role === 'employer'): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <input type="text" name="organization_name" class="form-control" placeholder="Organization Name *" value="<?= htmlspecialchars((string)$organization_name, ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <div class="file-input-wrapper">
                                        <div class="file-upload-placeholder">
                                            <span id="uploadLabel">Choose Logo</span>
                                            <i class="bi bi-upload"></i>
                                        </div>
                                        <input type="file" name="company_logo" accept=".png,.jpg,.jpeg,.webp" onchange="showFilename(this)">
                                    </div>
                                    <small class="text-muted" style="font-size: 0.72rem; display: block; margin-top: 4px;">Supported: png, jpg, jpeg, webp | Max: 2MB</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Row: First Name & Last Name -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <input type="text" name="first_name" class="form-control" placeholder="First Name *" value="<?= htmlspecialchars((string)$first_name, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="last_name" class="form-control" placeholder="Last Name *" value="<?= htmlspecialchars((string)$last_name, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <!-- Row: Email & Optional Phone -->
                        <div class="row g-3 mb-3">
                            <div class="<?= $role === 'employer' ? 'col-md-6' : 'col-12' ?>">
                                <input type="email" name="email" class="form-control" placeholder="Email *" value="<?= htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <?php if ($role === 'employer'): ?>
                                <div class="col-md-6">
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars((string)$phone, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Row: Password & Confirm Password -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6 position-relative">
                                <input type="password" name="password" id="p1" class="form-control pe-5" placeholder="Password *" required>
                                <span class="position-absolute end-0 top-50 translate-middle-y me-3 text-muted" style="cursor: pointer;" onclick="togglePass('p1', 'eye1')">
                                    <i class="bi bi-eye" id="eye1"></i>
                                </span>
                            </div>
                            <div class="col-md-6 position-relative">
                                <input type="password" name="confirm_password" id="p2" class="form-control pe-5" placeholder="Confirm Password *" required>
                                <span class="position-absolute end-0 top-50 translate-middle-y me-3 text-muted" style="cursor: pointer;" onclick="togglePass('p2', 'eye2')">
                                    <i class="bi bi-eye" id="eye2"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Row: Gender & Home Town (Job Seeker) -->
                        <?php if ($role === 'job_seeker'): ?>
                            <div class="row g-3 mb-4 align-items-center">
                                <div class="col-md-6 d-flex align-items-center gap-3">
                                    <span class="small text-muted fw-semibold">Your Gender:</span>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="gender" id="gMale" value="Male" <?= $gender === 'Male' ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="gMale">Male</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="radio" name="gender" id="gFemale" value="Female" <?= $gender === 'Female' ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="gFemale">Female</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="hometown" class="form-control" placeholder="Home Town" value="<?= htmlspecialchars((string)$hometown, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Terms & Consent Checkboxes -->
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="terms" id="termsBox" checked required>
                            <label class="form-check-label small text-muted" for="termsBox">
                                I agree to <a href="#" class="text-dark fw-semibold text-decoration-none">Privacy Policy</a> &amp; <a href="#" class="text-dark fw-semibold text-decoration-none">Terms/Conditions</a>.
                            </label>
                        </div>
                        <?php if ($role === 'job_seeker'): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="consentBox" checked>
                                <label class="form-check-label small text-muted" for="consentBox">
                                    I consent to receive relevant job opportunities via email, phone or WhatsApp.
                                </label>
                            </div>
                        <?php endif; ?>

                        <!-- Submit Button -->
                        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 mt-4">
                            <button type="submit" class="btn btn-brand-green w-100 w-sm-auto text-uppercase">REGISTER</button>
                            <span class="small text-muted">Already have an account? <a href="login.php" class="text-dark fw-bold text-decoration-none">Login Now</a></span>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function showFilename(fileInput) {
    const label = document.getElementById('uploadLabel');
    if (fileInput.files && fileInput.files[0]) {
        label.textContent = fileInput.files[0].name;
        label.classList.add('text-dark', 'fw-semibold');
    }
}
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>