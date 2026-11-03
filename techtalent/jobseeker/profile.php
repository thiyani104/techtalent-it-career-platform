<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true)) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$updated_success = false;

// Fetch categories for target category select dropdown
$categories = [];
try {
    $stmt_cat = $pdo->query("SELECT category_id, category_name FROM career_categories ORDER BY category_id ASC");
    $categories = $stmt_cat->fetchAll();
} catch (PDOException $e) {
    error_log("Categories Fetch Error: " . $e->getMessage());
}

// POST Profile Update Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $headline = trim($_POST['headline'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $preferred_work_arrangement = trim($_POST['preferred_work_arrangement'] ?? 'Any');
    $target_career_category_id = filter_input(INPUT_POST, 'target_career_category_id', FILTER_VALIDATE_INT) ?: null;

    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update users table
            $stmt_u = $pdo->prepare("UPDATE users SET full_name = :full_name WHERE user_id = :uid");
            $stmt_u->execute([
                ':full_name' => $full_name,
                ':uid' => $user_id
            ]);

            // Upsert job_seeker_profiles table
            $stmt_p = $pdo->prepare("
                INSERT INTO job_seeker_profiles 
                (user_id, headline, phone, location, bio, preferred_work_arrangement, target_career_category_id) 
                VALUES (:uid, :headline, :phone, :location, :bio, :pref_work, :target_cat)
                ON DUPLICATE KEY UPDATE 
                headline = VALUES(headline),
                phone = VALUES(phone),
                location = VALUES(location),
                bio = VALUES(bio),
                preferred_work_arrangement = VALUES(preferred_work_arrangement),
                target_career_category_id = VALUES(target_career_category_id)
            ");
            $stmt_p->execute([
                ':uid' => $user_id,
                ':headline' => $headline,
                ':phone' => $phone,
                ':location' => $location,
                ':bio' => $bio,
                ':pref_work' => $preferred_work_arrangement,
                ':target_cat' => $target_career_category_id
            ]);

            $pdo->commit();

            // Update session
            $_SESSION['full_name'] = $full_name;

            header("Location: profile.php?updated=1");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Profile Update Error: " . $e->getMessage());
            $errors[] = "Failed to update profile details. Please try again.";
        }
    }
}

// Fetch current user and profile data
$user_data = [];
$profile_data = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.email, p.* 
        FROM users u 
        LEFT JOIN job_seeker_profiles p ON u.user_id = p.user_id 
        WHERE u.user_id = :uid 
        LIMIT 1
    ");
    $stmt->execute([':uid' => $user_id]);
    $user_data = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log("Fetch Profile Error: " . $e->getMessage());
}

$page_title = 'My Profile - TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$updated_flag = isset($_GET['updated']) && $_GET['updated'] === '1';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header text-white p-4" style="background: linear-gradient(135deg, #0f2137 0%, #1a365d 100%);">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-25 p-3 rounded-circle text-white fs-3">
                            <i class="bi bi-person-vcard-fill"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1">Candidate Profile</h3>
                            <p class="text-white-50 mb-0 small">Manage your professional bio, contact info, and target tech role preferences</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5 bg-white">
                    <?php if ($updated_flag): ?>
                        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Profile Updated!</strong> Your candidate details have been saved successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <div class="row g-3 mb-4">
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label for="full_name" class="form-label fw-semibold text-dark small">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-start-0 py-2" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>

                            <!-- Email (Read only) -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold text-dark small">Email Address (Read-only)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 py-2 bg-light text-muted" id="email" value="<?php echo htmlspecialchars($user_data['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>

                            <!-- Professional Headline -->
                            <div class="col-md-12">
                                <label for="headline" class="form-label fw-semibold text-dark small">Professional Headline</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-briefcase"></i></span>
                                    <input type="text" class="form-control border-start-0 py-2" id="headline" name="headline" value="<?php echo htmlspecialchars($user_data['headline'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Senior Full-Stack PHP & React Developer | Cloud Architecture Enthusiast">
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold text-dark small">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control border-start-0 py-2" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. +94 77 123 4567">
                                </div>
                            </div>

                            <!-- Location -->
                            <div class="col-md-6">
                                <label for="location" class="form-label fw-semibold text-dark small">Location / City</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control border-start-0 py-2" id="location" name="location" value="<?php echo htmlspecialchars($user_data['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Colombo, Sri Lanka">
                                </div>
                            </div>

                            <!-- Target Career Category -->
                            <div class="col-md-6">
                                <label for="target_career_category_id" class="form-label fw-semibold text-dark small">Target Career Field</label>
                                <select class="form-select py-2" id="target_career_category_id" name="target_career_category_id">
                                    <option value="">Select Primary Field</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <?php $sel = ($user_data['target_career_category_id'] ?? null) == $cat['category_id'] ? 'selected' : ''; ?>
                                        <option value="<?php echo (int)$cat['category_id']; ?>" <?php echo $sel; ?>>
                                            <?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Preferred Work Arrangement -->
                            <div class="col-md-6">
                                <label for="preferred_work_arrangement" class="form-label fw-semibold text-dark small">Preferred Work Mode</label>
                                <select class="form-select py-2" id="preferred_work_arrangement" name="preferred_work_arrangement">
                                    <?php 
                                    $modes = ['Any', 'Remote', 'Hybrid', 'On-site'];
                                    foreach ($modes as $m):
                                        $msel = ($user_data['preferred_work_arrangement'] ?? 'Any') === $m ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $msel; ?>>
                                            <?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Professional Bio -->
                            <div class="col-md-12">
                                <label for="bio" class="form-label fw-semibold text-dark small">Professional Summary / Bio</label>
                                <textarea class="form-control p-3" id="bio" name="bio" rows="4" placeholder="Briefly describe your software engineering background, key technical achievements, and career aspirations..."><?php echo htmlspecialchars($user_data['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2">
                                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-accent px-4 py-2 fw-bold rounded-3">
                                <i class="bi bi-check-circle-fill me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
