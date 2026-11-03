<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auth Guard: Ensure employer
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['employer'], true)) {
    header('Location: ../auth/login.php');
    exit();
}

$uid = (int)$_SESSION['user_id'];
$page_title = 'Manage Company Profile - TechTalent';

$success_msg = '';
$error_msg = '';

require_once __DIR__ . '/../includes/employer-helpers.php';

// Fetch current employer details (also makes sure the logo column exists)
$employer = employer_profile($pdo, $uid);
$existing = $employer['exists'];
$logo_file = $employer['company_logo'];

if (isset($_GET['needs_company'])) {
    $error_msg = 'Please set your company name first. Vacancies are posted under your company profile.';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name     = trim($_POST['company_name'] ?? '');
    $company_location = trim($_POST['company_location'] ?? '');
    $website          = trim($_POST['website'] ?? '');
    $contact_email    = trim($_POST['contact_email'] ?? '');
    $company_bio      = trim($_POST['company_bio'] ?? '');

    // Optional logo upload / removal
    [$new_logo, $logo_error] = employer_store_logo($_FILES['company_logo'] ?? null);
    $remove_logo = !empty($_POST['remove_logo']);

    if (empty($company_name)) {
        $error_msg = 'Company Name is required.';
    } elseif (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid contact email address.';
    } elseif ($logo_error !== null) {
        $error_msg = $logo_error;
    } else {
        $old_logo  = $logo_file;
        $save_logo = $logo_file;
        if ($new_logo !== null) {
            $save_logo = $new_logo;
        } elseif ($remove_logo) {
            $save_logo = null;
        }
        try {
            // Check if record exists, if yes UPDATE, else INSERT
            if ($existing) {
                $sql = "UPDATE employers
                        SET company_name = :cname,
                            company_location = :cloc,
                            website = :web,
                            contact_email = :cemail,
                            company_bio = :bio,
                            company_logo = :logo
                        WHERE employer_id = :uid";
            } else {
                $sql = "INSERT INTO employers (employer_id, company_name, company_location, website, contact_email, company_bio, company_logo)
                        VALUES (:uid, :cname, :cloc, :web, :cemail, :bio, :logo)";
            }

            $stmt_save = $pdo->prepare($sql);
            $stmt_save->execute([
                ':cname'  => $company_name,
                ':cloc'   => $company_location,
                ':web'    => $website,
                ':cemail' => $contact_email,
                ':bio'    => $company_bio,
                ':logo'   => $save_logo,
                ':uid'    => $uid
            ]);

            // Keep this company's existing vacancies in step with the company name
            $pdo->prepare("UPDATE jobs SET company_name = :cname WHERE employer_id = :uid")
                ->execute([':cname' => $company_name, ':uid' => $uid]);

            // Remove the replaced / removed logo file only after the database was updated
            if ($old_logo !== $save_logo) {
                employer_delete_logo_file($old_logo);
            }

            // Update session so greeting updates immediately across all pages
            $_SESSION['company_name'] = $company_name;

            // Refresh current state
            $existing = true;
            $logo_file = $save_logo;
            $employer['company_name']     = $company_name;
            $employer['company_location'] = $company_location;
            $employer['website']          = $website;
            $employer['contact_email']    = $contact_email;
            $employer['company_bio']      = $company_bio;
            $employer['company_logo']     = $save_logo;

            $success_msg = 'Company profile updated successfully!';
        } catch (PDOException $e) {
            if ($new_logo !== null) {
                employer_delete_logo_file($new_logo);
            }
            error_log("Profile Update Error: " . $e->getMessage());
            $error_msg = 'Database error while saving profile. Please try again.';
        }
    }
}

$logo_url = employer_logo_url($logo_file);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">

    <!-- Top Breadcrumb / Return -->
    <div class="mb-3">
        <a href="dashboard.php" class="text-decoration-none text-muted small fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <div class="d-flex align-items-center gap-3 border-bottom pb-4 mb-4">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 fs-3">
                        <i class="bi bi-building-gear"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold text-dark mb-1">Company Profile</h3>
                        <p class="text-muted small mb-0">Update your company branding, contact channels, and candidate overview.</p>
                    </div>
                </div>

                <form method="POST" action="company-profile.php" enctype="multipart/form-data">
                    
                    <!-- Company Logo -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark small d-block">Company Logo</label>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center" style="width:110px;height:110px;overflow:hidden;">
                                <?php if ($logo_url): ?>
                                    <img id="logoPreview" src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Company logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                                <?php else: ?>
                                    <img id="logoPreview" src="" alt="" style="max-width:100%;max-height:100%;object-fit:contain;display:none;">
                                    <i id="logoPlaceholder" class="bi bi-building text-muted fs-1"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" class="form-control form-control-sm" id="company_logo" name="company_logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                                <div class="form-text">PNG or JPG, up to 2MB. Choose a new file to replace the current logo.</div>
                                <?php if ($logo_url): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="remove_logo" name="remove_logo">
                                        <label class="form-check-label small" for="remove_logo">Remove current logo</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Company Name -->
                    <div class="mb-3">
                        <label for="company_name" class="form-label fw-semibold text-dark small">Company Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                            <input type="text" class="form-control border-start-0 py-2" id="company_name" name="company_name" 
                                   value="<?= htmlspecialchars($employer['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g. WSO2, Virtusa, IFS">
                        </div>
                    </div>

                    <!-- Location & Website Grid -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="company_location" class="form-label fw-semibold text-dark small">Headquarters / Location</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" class="form-control border-start-0 py-2" id="company_location" name="company_location" 
                                       value="<?= htmlspecialchars($employer['company_location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Colombo, Sri Lanka / Remote">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="website" class="form-label fw-semibold text-dark small">Company Website URL</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-globe"></i></span>
                                <input type="url" class="form-control border-start-0 py-2" id="website" name="website" 
                                       value="<?= htmlspecialchars($employer['website'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://example.com">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Email -->
                    <div class="mb-3">
                        <label for="contact_email" class="form-label fw-semibold text-dark small">Hiring / Contact Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control border-start-0 py-2" id="contact_email" name="contact_email" 
                                   value="<?= htmlspecialchars($employer['contact_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="careers@company.com">
                        </div>
                    </div>

                    <!-- Company Bio / Description -->
                    <div class="mb-4">
                        <label for="company_bio" class="form-label fw-semibold text-dark small">About Company</label>
                        <textarea class="form-control py-2" id="company_bio" name="company_bio" rows="4" 
                                  placeholder="Brief overview of your company culture, technology stack, and engineering mission..."><?= htmlspecialchars($employer['company_bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- Save Buttons -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2 rounded-3 fw-semibold">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('company_logo')?.addEventListener('change', function () {
    var f = this.files && this.files[0];
    if (!f) { return; }
    var img = document.getElementById('logoPreview');
    var ph = document.getElementById('logoPlaceholder');
    img.src = URL.createObjectURL(f);
    img.style.display = '';
    if (ph) { ph.style.display = 'none'; }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>