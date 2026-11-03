<?php
ob_start();
?>
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: ../auth/login.php');
    exit();
}
$page_title = 'Post New Vacancy - TechTalent Recruiter';
require_once __DIR__ . '/../includes/employer-helpers.php';
// The company name always comes from the employer's own company profile
$profile = employer_profile($pdo, (int)$_SESSION['user_id']);
$companyName = $profile['company_name'];
if ($companyName === '') {
    header('Location: company-profile.php?needs_company=1');
    exit();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="container my-5">
    <h2 class="mb-4 text-center">Post a New Job Vacancy</h2>
    <?php
    // Fetch career categories for dropdown
    $stmt = $pdo->prepare('SELECT category_id, category_name FROM career_categories ORDER BY category_name');
    $stmt->execute();
    $categories = $stmt->fetchAll();

    // POST handling
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $required = ['job_title','category_id','work_arrangement','job_type','experience_level','location','salary_range','description','required_skills'];
        $errors = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $errors[] = $field . ' is required.';
            }
        }
        // Validate dropdown values on the server as well
        $validCategoryIds = array_map('intval', array_column($categories, 'category_id'));
        if (!empty($_POST['category_id']) && !in_array((int)$_POST['category_id'], $validCategoryIds, true)) {
            $errors[] = 'Please choose a valid career category.';
        }
        if (!empty($_POST['work_arrangement']) && !in_array($_POST['work_arrangement'], ['Remote','Hybrid','On-site'], true)) {
            $errors[] = 'Please choose a valid work arrangement.';
        }
        if (!empty($_POST['job_type']) && !in_array($_POST['job_type'], ['Full-time','Part-time','Internship','Freelance'], true)) {
            $errors[] = 'Please choose a valid job type.';
        }
        if (!empty($_POST['experience_level']) && !in_array($_POST['experience_level'], ['Internship','Entry Level','Junior','Mid-Level','Senior'], true)) {
            $errors[] = 'Please choose a valid experience level.';
        }
        if (empty($errors)) {
            $sql = "INSERT INTO jobs (employer_id, category_id, job_title, company_name, work_arrangement, job_type, experience_level, location, salary_range, description, required_skills, optional_skills, status) VALUES (:emp_id, :cat_id, :job_title, :company, :work, :type, :exp, :loc, :salary, :desc, :skills, :optional, 'Published')";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':emp_id' => $_SESSION['user_id'],
    ':cat_id' => $_POST['category_id'],
    ':job_title' => $_POST['job_title'],
    ':company' => $companyName,
    ':work' => $_POST['work_arrangement'],
    ':type' => $_POST['job_type'],
    ':exp' => $_POST['experience_level'],
    ':loc' => $_POST['location'],
    ':salary' => $_POST['salary_range'],
    ':desc' => $_POST['description'],
    ':skills' => $_POST['required_skills'],
    ':optional' => trim($_POST['optional_skills'] ?? '') !== '' ? mb_substr(trim($_POST['optional_skills']), 0, 255) : null,
]);
            header('Location: manage-jobs.php?posted=1');
            exit();
        }
    }
    ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?=htmlspecialchars($err)?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Job Title</label>
                    <input type="text" name="job_title" class="form-control" required value="<?=htmlspecialchars($_POST['job_title'] ?? '')?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company Name</label>
                    <input type="text" class="form-control bg-light" value="<?=htmlspecialchars($companyName)?>" readonly>
                    <div class="form-text">Set from your <a href="company-profile.php">company profile</a>. Vacancies are always posted under your company.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Target Career Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id']==$cat['category_id']) ? 'selected' : '' ?>><?=htmlspecialchars($cat['category_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Work Arrangement</label>
                    <select name="work_arrangement" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Remote" <?= (isset($_POST['work_arrangement']) && $_POST['work_arrangement']=='Remote') ? 'selected' : '' ?>>Remote</option>
                        <option value="Hybrid" <?= (isset($_POST['work_arrangement']) && $_POST['work_arrangement']=='Hybrid') ? 'selected' : '' ?>>Hybrid</option>
                        <option value="On-site" <?= (isset($_POST['work_arrangement']) && $_POST['work_arrangement']=='On-site') ? 'selected' : '' ?>>On-site</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Job Type</label>
                    <select name="job_type" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Full-time" <?= (isset($_POST['job_type']) && $_POST['job_type']=='Full-time') ? 'selected' : '' ?>>Full-time</option>
                        <option value="Part-time" <?= (isset($_POST['job_type']) && $_POST['job_type']=='Part-time') ? 'selected' : '' ?>>Part-time</option>
                        <option value="Internship" <?= (isset($_POST['job_type']) && $_POST['job_type']=='Internship') ? 'selected' : '' ?>>Internship</option>
                        <option value="Freelance" <?= (isset($_POST['job_type']) && $_POST['job_type']=='Freelance') ? 'selected' : '' ?>>Freelance</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Experience Level</label>
                    <select name="experience_level" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Internship" <?= (isset($_POST['experience_level']) && $_POST['experience_level']=='Internship') ? 'selected' : '' ?>>Internship</option>
                        <option value="Entry Level" <?= (isset($_POST['experience_level']) && $_POST['experience_level']=='Entry Level') ? 'selected' : '' ?>>Entry Level</option>
                        <option value="Junior" <?= (isset($_POST['experience_level']) && $_POST['experience_level']=='Junior') ? 'selected' : '' ?>>Junior</option>
                        <option value="Mid-Level" <?= (isset($_POST['experience_level']) && $_POST['experience_level']=='Mid-Level') ? 'selected' : '' ?>>Mid-Level</option>
                        <option value="Senior" <?= (isset($_POST['experience_level']) && $_POST['experience_level']=='Senior') ? 'selected' : '' ?>>Senior</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" required value="<?=htmlspecialchars($_POST['location'] ?? '')?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expected Salary Range</label>
                    <input type="text" name="salary_range" class="form-control" required placeholder="e.g., LKR 100,000 - 160,000" value="<?=htmlspecialchars($_POST['salary_range'] ?? '')?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Required Skills (comma separated)</label>
                    <input type="text" name="required_skills" class="form-control" required placeholder="PHP, MySQL, Docker, Git" value="<?=htmlspecialchars($_POST['required_skills'] ?? '')?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Nice-to-have Skills (optional, comma separated)</label>
                    <input type="text" name="optional_skills" class="form-control" maxlength="255" placeholder="Kubernetes, AWS" value="<?=htmlspecialchars($_POST['optional_skills'] ?? '')?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Detailed Job Description</label>
                    <textarea name="description" class="form-control" rows="5" required><?=htmlspecialchars($_POST['description'] ?? '')?></textarea>
                </div>
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary btn-lg">Publish Vacancy</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
