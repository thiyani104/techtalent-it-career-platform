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

// Fetch Applications
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.application_id, a.match_score, a.current_status, a.applied_at,
               j.job_id, j.job_title, j.company_name, j.location, j.work_arrangement, j.salary_range,
               c.category_name, s.snapshot_id
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        JOIN career_categories c ON j.category_id = c.category_id 
        LEFT JOIN application_cv_snapshots s ON s.application_id = a.application_id
        WHERE a.job_seeker_id = :uid 
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Applications Error: " . $e->getMessage());
}

$page_title = 'My Applications - TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$success_flag = isset($_GET['success']) && $_GET['success'] === 'applied';
?>

<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-navy mb-1"><i class="bi bi-send-check-fill text-primary me-2"></i>My Job Applications</h2>
            <p class="text-muted mb-0">Track application statuses, match scores, and interview updates in real time.</p>
        </div>
        <a href="../guest/jobs.php" class="btn btn-accent px-4 py-2 fw-bold">
            <i class="bi bi-plus-lg me-1"></i> Browse More Vacancies
        </a>
    </div>

    <?php if ($success_flag): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <strong>Application Submitted!</strong> Your candidate profile has been transmitted to the recruiter. Track your application status below.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($applications)): ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-navy text-white" style="background-color: #0f2137;">
                        <tr>
                            <th class="ps-4 py-3">Job Title & Company</th>
                            <th class="py-3">Category</th>
                            <th class="py-3">Work Mode</th>
                            <th class="py-3">Match Score</th>
                            <th class="py-3">Applied Date</th>
                            <th class="py-3">Status</th>
                            <th class="pe-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <?php
                                // Status badge colors
                                $status = $app['current_status'];
                                $status_badge = 'bg-primary bg-opacity-10 text-primary';
                                if ($status === 'Under Review') $status_badge = 'bg-info bg-opacity-10 text-info';
                                elseif ($status === 'Shortlisted') $status_badge = 'bg-warning bg-opacity-25 text-dark fw-bold';
                                elseif ($status === 'Interview Scheduled') $status_badge = 'bg-primary text-white fw-bold';
                                elseif ($status === 'Interview Completed') $status_badge = 'bg-indigo bg-opacity-25 text-indigo';
                                elseif ($status === 'Selected') $status_badge = 'bg-success text-white fw-bold';
                                elseif ($status === 'Rejected') $status_badge = 'bg-danger bg-opacity-10 text-danger';

                                // Match score badge
                                $score = (float)$app['match_score'];
                                $score_class = 'bg-secondary';
                                if ($score >= 80) $score_class = 'bg-success';
                                elseif ($score >= 60) $score_class = 'bg-primary';
                                elseif ($score >= 40) $score_class = 'bg-warning text-dark';
                            ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <h6 class="fw-bold text-navy mb-1">
                                        <a href="../guest/job-details.php?id=<?php echo (int)$app['job_id']; ?>" class="text-navy text-decoration-none hover-primary">
                                            <?php echo htmlspecialchars($app['job_title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </h6>
                                    <span class="text-muted small">
                                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($app['company_name'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($app['location'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($app['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                        <?php echo htmlspecialchars($app['work_arrangement'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $score_class; ?> px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-lightning-charge-fill me-1"></i><?php echo number_format($score, 0); ?>%
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_badge; ?> px-3 py-1.5 rounded-pill fs-7">
                                        <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end text-nowrap">
                                    <?php if (!empty($app['snapshot_id'])): ?>
                                        <a href="application-cv.php?application_id=<?php echo (int)$app['application_id']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3" title="CV submitted with this application">
                                            <i class="bi bi-file-earmark-person"></i> Submitted CV
                                        </a>
                                    <?php endif; ?>
                                    <a href="../guest/job-details.php?id=<?php echo (int)$app['job_id']; ?>" class="btn btn-outline-primary btn-sm rounded-3">
                                        View Job <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="my-4">
                <i class="bi bi-journal-x display-1 text-muted opacity-25 d-block mb-3"></i>
                <h4 class="fw-bold text-navy mb-2">No Applications Submitted Yet</h4>
                <p class="text-muted col-lg-6 mx-auto mb-4">
                    Explore available IT vacancies, match your skills, and submit candidate applications to top tech companies.
                </p>
                <a href="../guest/jobs.php" class="btn btn-accent px-4 py-2.5 fw-bold rounded-3">
                    <i class="bi bi-search me-1"></i> Explore IT Vacancies
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
