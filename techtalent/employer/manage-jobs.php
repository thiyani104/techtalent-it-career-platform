<?php
session_start();
require_once __DIR__ . '/../config/db.php';
// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: ../auth/login.php');
    exit();
}
$page_title = 'Manage Vacancies - TechTalent Recruiter';
require_once __DIR__ . '/../includes/employer-helpers.php';
$profile = employer_profile($pdo, (int)$_SESSION['user_id']);
$company_logo = employer_logo_url($profile['company_logo']);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="container my-5">
    <?php if (isset($_GET['posted']) && $_GET['posted'] === '1'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <strong><i class="bi bi-check-circle-fill me-2"></i>Job posted successfully!</strong>
            Your vacancy has been saved and published.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Job updated successfully.</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <?php if ($company_logo): ?>
                <img src="<?= htmlspecialchars($company_logo, ENT_QUOTES, 'UTF-8') ?>" alt="Company logo" style="height:48px;max-width:120px;object-fit:contain;">
            <?php endif; ?>
            <div>
                <h2 class="mb-0">My Vacancies</h2>
                <?php if ($profile['company_name'] !== ''): ?>
                    <div class="text-muted small">Posted by <?= htmlspecialchars($profile['company_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <a href="post-job.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Post New Vacancy</a>
    </div>
    <?php
    // Fetch vacancies
    $uid = (int)$_SESSION['user_id'];
    $sql = "SELECT j.job_id, j.job_title, j.work_arrangement, j.experience_level, j.status, j.created_at, c.category_name,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) AS applicant_count
            FROM jobs j
            JOIN career_categories c ON j.category_id = c.category_id
            WHERE j.employer_id = :uid
            ORDER BY j.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $uid]);
    $jobs = $stmt->fetchAll();
    ?>
    <?php if (count($jobs) === 0): ?>
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">No vacancies posted yet</h5>
                <p class="card-text">Click the button above to post your first job vacancy.</p>
                <a href="post-job.php" class="btn btn-primary">Post Vacancy</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Job Title</th>
                        <th>Category</th>
                        <th>Work Arrangement</th>
                        <th>Experience</th>
                        <th>Date Posted</th>
                        <th>Status</th>
                        <th>Applicants</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?=htmlspecialchars($job['job_title'])?></td>
                            <td><?=htmlspecialchars($job['category_name'])?></td>
                            <td><?=htmlspecialchars($job['work_arrangement'])?></td>
                            <td><?=htmlspecialchars($job['experience_level'])?></td>
                            <td><?=date('M d, Y', strtotime($job['created_at']))?></td>
                            <td>
                                <?php
                                $status = $job['status'];
                                $badgeClass = 'secondary';
                                if ($status === 'Published') $badgeClass = 'success';
                                elseif ($status === 'Closed') $badgeClass = 'danger';
                                elseif ($status === 'Draft') $badgeClass = 'warning';
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?=htmlspecialchars($status)?></span>
                            </td>
                            <td>
                                <a href="applicants.php?job_id=<?= (int)$job['job_id'] ?>" class="badge bg-info text-decoration-none">
                                    <?= (int)$job['applicant_count'] ?> Applicants
                                </a>
                            </td>
                            <td>
                                <a href="applicants.php?job_id=<?= (int)$job['job_id'] ?>" class="btn btn-sm btn-outline-primary">View Applicants</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
