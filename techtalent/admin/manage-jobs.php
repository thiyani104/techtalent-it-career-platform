<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

if (empty($_SESSION['manage_jobs_csrf'])) {
    $_SESSION['manage_jobs_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['manage_jobs_csrf'];
$allowedStatuses = ['Draft', 'Pending', 'Published', 'Closed'];
$flash = ['type' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $flash = ['type' => 'danger', 'message' => 'Your session token is invalid. Refresh the page and try again.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $jobId = filter_var($_POST['job_id'] ?? null, FILTER_VALIDATE_INT);
        try {
            if (!$jobId || $jobId < 1) {
                throw new RuntimeException('Please select a valid job.');
            }
            if ($action === 'status') {
                $status = (string)($_POST['status'] ?? '');
                if (!in_array($status, $allowedStatuses, true)) {
                    throw new RuntimeException('The selected job status is not valid.');
                }
                $stmt = $pdo->prepare('UPDATE jobs SET status = :status WHERE job_id = :job_id');
                $stmt->execute([':status' => $status, ':job_id' => $jobId]);
                $flash = ['type' => 'success', 'message' => $stmt->rowCount() ? 'Job status updated successfully.' : 'No changes were made to this job.'];
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM jobs WHERE job_id = :job_id');
                $stmt->execute([':job_id' => $jobId]);
                $flash = ['type' => $stmt->rowCount() ? 'success' : 'warning', 'message' => $stmt->rowCount() ? 'Job vacancy deleted successfully.' : 'The selected job was not found.'];
            } else {
                throw new RuntimeException('Unknown action requested.');
            }
        } catch (RuntimeException $e) {
            $flash = ['type' => 'danger', 'message' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('Admin manage jobs error: ' . $e->getMessage());
            $flash = ['type' => 'danger', 'message' => 'The operation could not be completed. Check whether this job has related applications and try again.'];
        }
    }
}

$statusFilter = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
if (!in_array($statusFilter, $allowedStatuses, true)) { $statusFilter = ''; }

try {
    $counts = array_fill_keys($allowedStatuses, 0);
    $countStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM jobs GROUP BY status');
    foreach ($countStmt->fetchAll() as $row) {
        if (isset($counts[$row['status']])) { $counts[$row['status']] = (int)$row['total']; }
    }
    $totalJobs = array_sum($counts);

    $where = [];
    $params = [];
    if ($statusFilter !== '') { $where[] = 'j.status = :status'; $params[':status'] = $statusFilter; }
    if ($search !== '') {
        $where[] = '(j.job_title LIKE :search OR j.company_name LIKE :search OR j.location LIKE :search OR u.full_name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    $sql = "SELECT j.job_id, j.job_title, j.company_name, j.work_arrangement, j.job_type,
                   j.experience_level, j.location, j.status, j.created_at,
                   c.category_name, u.full_name AS employer_name
            FROM jobs j
            LEFT JOIN career_categories c ON c.category_id = j.category_id
            LEFT JOIN users u ON u.user_id = j.employer_id";
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY j.created_at DESC, j.job_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin manage jobs load error: ' . $e->getMessage());
    $counts = array_fill_keys($allowedStatuses, 0); $totalJobs = 0; $jobs = [];
    $flash = ['type' => 'danger', 'message' => 'Could not load job vacancies. Please check your database schema and connection.'];
}
$page_title = 'Manage Jobs - TechTalent Admin';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/admin-dashboard.css">
<style>
.manage-jobs-page{padding:28px;max-width:1600px;margin:auto}.mj-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:24px}.mj-eyebrow{font-size:.76rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1479ee}.mj-title{font-size:clamp(1.7rem,3vw,2.25rem);font-weight:800;color:#13253d;margin:.25rem 0}.mj-subtitle{color:#68778b;margin:0}.mj-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px}.mj-stat{background:#fff;border:1px solid #e6ebf2;border-radius:16px;padding:20px;box-shadow:0 6px 22px rgba(17,38,67,.04);text-decoration:none;color:inherit;transition:.18s}.mj-stat:hover{transform:translateY(-2px);border-color:#9bc9ff}.mj-stat-label{color:#65758a;font-size:.9rem;font-weight:600}.mj-stat-number{font-size:2rem;font-weight:800;color:#14263e;margin-top:5px}.mj-stat-icon{float:right;width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:#eaf3ff;color:#1479ee;font-size:1.2rem}.mj-panel{background:#fff;border:1px solid #e6ebf2;border-radius:18px;box-shadow:0 8px 28px rgba(17,38,67,.045);overflow:hidden}.mj-panel-head{padding:22px 24px;border-bottom:1px solid #edf0f5;display:flex;justify-content:space-between;align-items:center;gap:12px}.mj-panel-head h2{font-size:1.15rem;font-weight:800;margin:0;color:#182a42}.mj-filters{padding:18px 24px;display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(150px,.8fr) minmax(180px,1fr) auto;gap:12px;border-bottom:1px solid #edf0f5}.mj-control{width:100%;border:1px solid #dce4ee;border-radius:10px;padding:11px 13px;background:#fff;color:#20344d;min-height:44px}.mj-control:focus{outline:0;border-color:#1680ff;box-shadow:0 0 0 3px rgba(22,128,255,.12)}.mj-table-wrap{overflow-x:auto}.mj-table{width:100%;border-collapse:separate;border-spacing:0;min-width:980px}.mj-table th{background:#f8fafc;color:#637188;font-size:.75rem;text-transform:uppercase;letter-spacing:.055em;padding:14px 18px;text-align:left;white-space:nowrap}.mj-table td{padding:17px 18px;border-top:1px solid #eef1f5;vertical-align:middle;color:#314158;font-size:.9rem}.mj-table tbody tr:hover{background:#fbfdff}.mj-job-title{font-weight:750;color:#172b45;margin-bottom:4px}.mj-muted{color:#78869a;font-size:.8rem}.mj-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:30px;font-size:.76rem;font-weight:750;white-space:nowrap}.mj-published{background:#e5f8ed;color:#167847}.mj-pending{background:#fff4d9;color:#926000}.mj-draft{background:#edf1f6;color:#59677a}.mj-closed{background:#ffe8e8;color:#b4232d}.mj-actions{display:flex;align-items:center;gap:7px}.mj-btn{border:1px solid #dbe3ed;border-radius:9px;padding:8px 10px;background:#fff;color:#34465d;font-size:.82rem;font-weight:700;cursor:pointer;text-decoration:none;white-space:nowrap}.mj-btn:hover{background:#f4f8fd}.mj-btn-primary{background:#087cff;color:#fff;border-color:#087cff}.mj-btn-danger{color:#bd2834;border-color:#f0c9cd}.mj-inline-status{display:flex;gap:6px;align-items:center}.mj-inline-status select{max-width:125px;padding:7px 8px;font-size:.8rem}.mj-empty{text-align:center;padding:60px 20px;color:#748196}.mj-empty i{font-size:2.5rem;display:block;margin-bottom:10px;color:#a8b7ca}.mj-alert{margin-bottom:20px;border-radius:12px;padding:13px 16px}.mj-footer{padding:16px 24px;color:#758398;font-size:.85rem;border-top:1px solid #edf0f5}.mj-sidebar .admin-nav a.active{background:rgba(20,126,255,.13);color:#1680ff}@media(max-width:1000px){.mj-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.mj-filters{grid-template-columns:1fr 1fr}.manage-jobs-page{padding:20px}}@media(max-width:600px){.mj-heading{display:block}.mj-stats{grid-template-columns:1fr 1fr;gap:10px}.mj-stat{padding:14px}.mj-stat-number{font-size:1.6rem}.mj-filters{grid-template-columns:1fr}.manage-jobs-page{padding:14px}}
</style>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="admin-dashboard">
 <div class="admin-layout">
  <aside class="admin-sidebar mj-sidebar">
   <div class="admin-brand"><strong>TechTalent Admin</strong><small>Platform Control Center</small></div>
   <div class="admin-sidebar-label">Main Menu</div>
   <nav class="admin-nav">
    <a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
    <a href="../guest/jobs.php"><i class="bi bi-briefcase-fill"></i><span>Vacancies</span></a>
    <a href="manage-jobs.php" class="active"><i class="bi bi-list-check"></i><span>Manage Jobs</span></a>
   </nav>
   <div class="admin-sidebar-label mt-3">Management</div>
   <nav class="admin-nav">
    <a href="manage-inquiries.php"><i class="bi bi-envelope-paper-fill"></i><span>Contact Inquiries</span></a>
    <a href="manage-reviews.php"><i class="bi bi-star-fill"></i><span>Reviews</span></a>
    <a href="../jobseeker/skills.php"><i class="bi bi-tools"></i><span>Skills</span></a>
   </nav>
   <div class="admin-sidebar-label mt-3">Account</div><nav class="admin-nav">
    <a href="../guest/index.php"><i class="bi bi-house"></i><span>View Website</span></a>
    <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
   </nav>
   <div class="admin-sidebar-footer"><i class="bi bi-shield-check me-1"></i> Administrator access enabled</div>
  </aside>
  <main class="admin-main"><div class="manage-jobs-page">
   <div class="mj-heading"><div><div class="mj-eyebrow">Management / Vacancies</div><h1 class="mj-title">Manage Jobs</h1><p class="mj-subtitle">Review, publish, close, or remove vacancies posted on TechTalent.</p></div><a href="../guest/jobs.php" class="mj-btn mj-btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i> View Job Board</a></div>
   <?php if ($flash['message'] !== ''): ?><div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> mj-alert" role="alert"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
   <section class="mj-stats">
    <?php foreach ([['All Vacancies',$totalJobs,'bi-briefcase-fill',''],['Published',$counts['Published'],'bi-check-circle-fill','Published'],['Pending Review',$counts['Pending'],'bi-hourglass-split','Pending'],['Closed',$counts['Closed'],'bi-lock-fill','Closed']] as $card): ?>
     <a class="mj-stat" href="manage-jobs.php<?= $card[3] !== '' ? '?status=' . urlencode($card[3]) : '' ?>"><span class="mj-stat-icon"><i class="bi <?= htmlspecialchars($card[2]) ?>"></i></span><div class="mj-stat-label"><?= htmlspecialchars($card[0]) ?></div><div class="mj-stat-number"><?= (int)$card[1] ?></div></a>
    <?php endforeach; ?>
   </section>
   <section class="mj-panel">
    <div class="mj-panel-head"><h2><i class="bi bi-list-ul me-2"></i>All Vacancies</h2><span class="mj-muted"><?= count($jobs) ?> result<?= count($jobs) === 1 ? '' : 's' ?></span></div>
    <form class="mj-filters" method="get" action="manage-jobs.php">
     <input class="mj-control" type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search job, company, location, or employer..." aria-label="Search vacancies">
     <select class="mj-control" name="status" aria-label="Filter by status"><option value="">All statuses</option><?php foreach ($allowedStatuses as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select>
     <button class="mj-btn mj-btn-primary" type="submit"><i class="bi bi-search me-1"></i> Filter</button>
    </form>
    <div class="mj-table-wrap"><table class="mj-table"><thead><tr><th>Job</th><th>Category</th><th>Details</th><th>Posted</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php if (!$jobs): ?><tr><td colspan="6"><div class="mj-empty"><i class="bi bi-briefcase"></i><strong>No vacancies found</strong><div>Try changing the search term or filters.</div></div></td></tr><?php else: foreach ($jobs as $job): $st = (string)$job['status']; $statusClass = ['Published'=>'mj-published','Pending'=>'mj-pending','Draft'=>'mj-draft','Closed'=>'mj-closed'][$st] ?? 'mj-draft'; ?>
     <tr>
      <td><div class="mj-job-title"><?= htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8') ?></div><div class="mj-muted"><?= htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($job['location'], ENT_QUOTES, 'UTF-8') ?></div><div class="mj-muted">Posted by <?= htmlspecialchars($job['employer_name'] ?: ('Employer #' . $job['job_id']), ENT_QUOTES, 'UTF-8') ?></div></td>
      <td><?= htmlspecialchars($job['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($job['work_arrangement'], ENT_QUOTES, 'UTF-8') ?><br><span class="mj-muted"><?= htmlspecialchars($job['job_type'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($job['experience_level'], ENT_QUOTES, 'UTF-8') ?></span></td>
      <td><?= !empty($job['created_at']) ? date('d M Y', strtotime($job['created_at'])) : '—' ?></td>
      <td><span class="mj-pill <?= $statusClass ?>"><i class="bi bi-circle-fill" style="font-size:6px"></i><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span></td>
      <td><div class="mj-actions">
       <form method="post" class="mj-inline-status" action="manage-jobs.php<?= $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING'], ENT_QUOTES, 'UTF-8') : '' ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="job_id" value="<?= (int)$job['job_id'] ?>"><select class="mj-control" name="status" aria-label="Change status for <?= htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8') ?>"><?php foreach ($allowedStatuses as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $st === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select><button class="mj-btn" type="submit" title="Save status"><i class="bi bi-check-lg"></i></button></form>
       <form method="post" action="manage-jobs.php<?= $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING'], ENT_QUOTES, 'UTF-8') : '' ?>" onsubmit="return confirm('Permanently delete this job vacancy? If related records prevent deletion, close the vacancy instead.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="job_id" value="<?= (int)$job['job_id'] ?>"><button class="mj-btn mj-btn-danger" type="submit" title="Delete vacancy"><i class="bi bi-trash3"></i></button></form>
      </div></td>
     </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div><div class="mj-footer">Showing <?= count($jobs) ?> vacancy record<?= count($jobs) === 1 ? '' : 's' ?>. Status changes are saved to the database immediately.</div>
   </section>
  </div></main>
 </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
