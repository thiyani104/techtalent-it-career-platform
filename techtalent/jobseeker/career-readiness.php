<?php
ob_start();
session_start();

// Auth Guard: only job seekers
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true)) {
    header('Location: ../auth/login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../ai/skill-gap-analyzer.php';

$user_id = $_SESSION['user_id'];

// Fetch candidate skills
$candidate_skills = [];
try {
    $stmt = $pdo->prepare('SELECT s.skill_name FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE us.user_id = :uid');
    $stmt->execute([':uid' => $user_id]);
    $candidate_skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Candidate skills fetch error: ' . $e->getMessage());
}

// Fetch all published jobs with required skills and related info
$jobs = [];
try {
    $stmt = $pdo->prepare('
        SELECT j.*, c.category_name, c.icon_class
        FROM jobs j
        LEFT JOIN career_categories c ON j.category_id = c.category_id
        ORDER BY j.created_at DESC
    ');
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Jobs fetch error: ' . $e->getMessage());
}

$page_title = 'Career Readiness & AI Match Scores | TechTalent';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container py-4">
    <h2 class="fw-bold text-navy mb-4">Career Readiness Dashboard</h2>
    <p class="text-muted mb-4">AI‑powered match scores for your profile against current vacancies.</p>
    <div class="row g-4">
        <?php if (empty($jobs)): ?>
            <div class="col-12"><div class="alert alert-info">No vacancies are currently published.</div></div>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <?php
                    $match = calculate_job_match($candidate_skills, $job['required_skills'] ?? '');
                    $score = $match['score'];
                    $matched = $match['matched'];
                    $gaps = $match['gaps'];
                    $recommendations = generate_recommendations($gaps);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold text-navy mb-2">
                                <?php echo htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <p class="card-text text-muted mb-2">
                                <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <p class="mb-1"><strong>Match Score:</strong> <span class="badge <?php echo $score >= 80 ? 'bg-success' : ($score >= 50 ? 'bg-warning' : 'bg-danger'); ?>"><?php echo $score; ?>%</span></p>
                            <?php if (!empty($matched)): ?>
                                <p class="mb-1">Matched Skills:</p>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($matched as $skill): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold"><?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($gaps)): ?>
                                <p class="mb-1">Skill Gaps:</p>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($gaps as $gap): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger fw-semibold"><?php echo htmlspecialchars($gap, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($recommendations)): ?>
                                <p class="mb-1">Recommendations:</p>
                                <ul class="list-unstyled">
                                    <?php foreach ($recommendations as $rec): ?>
                                        <li><a href="<?php echo htmlspecialchars($rec['link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-decoration-none"><i class="bi bi-link-45deg me-1"></i><?php echo htmlspecialchars($rec['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <a href="../guest/job-details.php?id=<?php echo (int)$job['job_id']; ?>" class="btn btn-accent btn-sm mt-3 w-100">View Vacancy</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>
