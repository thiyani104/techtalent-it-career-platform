<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: ../auth/login.php');
    exit();
}
$page_title = 'Candidate Applications - TechTalent Recruiter';
// Handle status update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $app_id = (int)($_POST['application_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $allowed = ['Applied','Under Review','Shortlisted','Interview Scheduled','Interview Completed','Selected','Rejected'];
    $uid = (int)$_SESSION['user_id'];
    $done = false;
    if (in_array($new_status, $allowed, true)) {
        // The application must belong to a vacancy posted by THIS employer
        $own = $pdo->prepare('SELECT a.application_id FROM applications a JOIN jobs j ON j.job_id = a.job_id WHERE a.application_id = :app_id AND j.employer_id = :uid LIMIT 1');
        $own->execute([':app_id' => $app_id, ':uid' => $uid]);
        if ($own->fetchColumn()) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE applications SET current_status = :status WHERE application_id = :app_id');
                $stmt->execute([':status' => $new_status, ':app_id' => $app_id]);
                $stmt = $pdo->prepare('INSERT INTO application_status_history (application_id, status, updated_by, notes) VALUES (:app_id, :status, :uid, :notes)');
                $stmt->execute([':app_id' => $app_id, ':status' => $new_status, ':uid' => $uid, ':notes' => 'Status updated by recruiter']);
                $pdo->commit();
                $done = true;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Applicant status update error: ' . $e->getMessage());
            }
        }
    }
    // Preserve job_id in redirect if present
    $flag = $done ? 'updated=1' : 'error=1';
    $redirect = 'applicants.php';
    if (!empty($_GET['job_id'])) {
        $redirect .= '?job_id=' . (int)$_GET['job_id'] . '&' . $flag;
    } else {
        $redirect .= '?' . $flag;
    }
    header('Location: ' . $redirect);
    exit();
}
// Build query for applicants
$uid = (int)$_SESSION['user_id'];
$jobIdFilter = null;
if (!empty($_GET['job_id'])) {
    $jobIdFilter = (int)$_GET['job_id'];
}
$sql = "SELECT a.application_id, a.job_seeker_id, u.full_name, u.email, p.phone, p.location,
               p.headline, a.match_score, a.current_status, a.applied_at, j.job_title, j.job_id,
               j.employer_id, s.snapshot_id
        FROM applications a
        JOIN users u ON a.job_seeker_id = u.user_id
        LEFT JOIN job_seeker_profiles p ON u.user_id = p.user_id
        JOIN jobs j ON a.job_id = j.job_id
        LEFT JOIN application_cv_snapshots s ON s.application_id = a.application_id
        WHERE j.employer_id = :uid";
$params = [':uid' => $uid];
if ($jobIdFilter !== null) {
    $sql .= ' AND j.job_id = :job_id';
    $params[':job_id'] = $jobIdFilter;
}
$sql .= " ORDER BY a.match_score DESC, a.applied_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="container my-5">
    <?php if ($jobIdFilter !== null): ?>
        <?php
        // Get job title for banner
        $stmt = $pdo->prepare('SELECT job_title FROM jobs WHERE job_id = :jid AND employer_id = :uid');
        $stmt->execute([':jid' => $jobIdFilter, ':uid' => $uid]);
        $jobTitle = $stmt->fetchColumn();
        ?>
        <?php if ($jobTitle === false): ?>
            <div class="alert alert-warning">That vacancy was not found among your company's vacancies.</div>
        <?php else: ?>
            <h4 class="mb-3">Applicants for: <strong><?=htmlspecialchars($jobTitle) ?></strong> (<?=count($applicants)?>)</h4>
        <?php endif; ?>
    <?php else: ?>
        <h4 class="mb-3">All Applicants for Your Vacancies (<?=count($applicants)?>)</h4>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Application status updated.</div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger">That application could not be updated.</div>
    <?php endif; ?>
    <?php if (empty($applicants)): ?>
        <div class="alert alert-info">No applicants found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Headline</th>
                        <th>Match Score</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th>Skills</th>
                        <th>CV</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applicants as $app): ?>
                        <tr>
                            <td><?=htmlspecialchars($app['full_name'])?></td>
                            <td><?=htmlspecialchars($app['email'])?></td>
                            <td><?=htmlspecialchars($app['phone'] ?? 'N/A')?></td>
                            <td><?=htmlspecialchars($app['location'] ?? 'N/A')?></td>
                            <td><?=htmlspecialchars($app['headline'] ?? '-')?></td>
                            <td>
                                <?php
                                $score = (float)$app['match_score'];
                                $badge = 'secondary';
                                if ($score >= 80) $badge = 'success';
                                elseif ($score >= 60) $badge = 'info';
                                elseif ($score >= 40) $badge = 'warning';
                                else $badge = 'danger';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= number_format($score, 1) ?>%</span>
                            </td>
                            <td>
                                <form method="post" class="d-flex align-items-center">
                                    <input type="hidden" name="application_id" value="<?= (int)$app['application_id'] ?>">
                                    <select name="new_status" class="form-select form-select-sm me-2" required>
                                        <?php
                                        $statusOptions = ['Applied','Under Review','Shortlisted','Interview Scheduled','Interview Completed','Selected','Rejected'];
                                        foreach ($statusOptions as $opt) {
                                            $selected = ($opt === $app['current_status']) ? 'selected' : '';
                                            echo "<option value=\"$opt\" $selected>$opt</option>";
                                        }
                                        ?>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                            <td><?=date('Y-m-d', strtotime($app['applied_at']))?></td>
                            <td>
                                <!-- Skills accordion -->
                                <div class="accordion" id="skillsAccordion<?= (int)$app['application_id'] ?>">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?= (int)$app['application_id'] ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= (int)$app['application_id'] ?>" aria-expanded="false" aria-controls="collapse<?= (int)$app['application_id'] ?>">
                                                View Skills
                                            </button>
                                        </h2>
                                        <div id="collapse<?= (int)$app['application_id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= (int)$app['application_id'] ?>" data-bs-parent="#skillsAccordion<?= (int)$app['application_id'] ?>">
                                            <div class="accordion-body p-2">
                                                <?php
                                                $stmt = $pdo->prepare('SELECT s.skill_name FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE us.user_id = :uid');
                                                $stmt->execute([':uid' => $app['job_seeker_id']]);
                                                $skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                                if ($skills) {
                                                    echo '<ul class="list-unstyled mb-0">';
                                                    foreach ($skills as $skill) {
                                                        echo '<li><span class="badge bg-secondary me-1">' . htmlspecialchars($skill) . '</span></li>';
                                                    }
                                                    echo '</ul>';
                                                } else {
                                                    echo 'No skills listed.';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($app['snapshot_id'] && (int)$app['employer_id'] === $uid): ?>
                                    <a href="cv-snapshot.php?application_id=<?= (int)$app['application_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-file-earmark-person"></i> View CV</a>
                                <?php elseif ($app['snapshot_id']): ?>
                                    <span class="small text-muted" title="Only the employer who posted this vacancy can view the CV">Restricted</span>
                                <?php else: ?>
                                    <span class="small text-muted">No CV</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
