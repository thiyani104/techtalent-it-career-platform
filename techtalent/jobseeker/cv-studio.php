<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';

$user_id = cv_require_job_seeker();
$csrf = cv_csrf_token();

// ---------------------------------------------------------------------
// Actions (POST + CSRF): create from profile, create blank, duplicate, delete
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cv_csrf_valid($_POST['csrf_token'] ?? null)) {
        header('Location: cv-studio.php?msg=csrf');
        exit();
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_profile' || $action === 'create_blank') {
            $title = cv_clean_string($_POST['title'] ?? '', 150);
            if ($action === 'create_profile') {
                $id = cv_create($pdo, $user_id, $title ?: 'My CV (from profile)', cv_content_from_profile($pdo, $user_id), 'profile');
            } else {
                $id = cv_create($pdo, $user_id, $title ?: 'Untitled CV', cv_empty_content(), 'blank');
            }
            header('Location: cv-editor.php?id=' . $id . '&created=1');
            exit();
        }
        $cv = cv_fetch_owned($pdo, (int)($_POST['cv_id'] ?? 0), $user_id);
        if (!$cv) {
            header('Location: cv-studio.php?msg=notfound');
            exit();
        }
        if ($action === 'duplicate') {
            $copyTitle = mb_substr('Copy of ' . $cv['title'], 0, 150);
            cv_create($pdo, $user_id, $copyTitle, cv_decode($cv['content']), 'duplicate', $cv['template'], $cv['accent_color']);
            header('Location: cv-studio.php?msg=duplicated');
            exit();
        }
        if ($action === 'delete') {
            // Application snapshots are separate copies and are not affected (FK sets cv_id to NULL).
            $stmt = $pdo->prepare('DELETE FROM cvs WHERE cv_id = :id AND user_id = :uid');
            $stmt->execute([':id' => $cv['cv_id'], ':uid' => $user_id]);
            header('Location: cv-studio.php?msg=deleted');
            exit();
        }
    } catch (PDOException $e) {
        error_log('CV Studio action error: ' . $e->getCode());
        header('Location: cv-studio.php?msg=error');
        exit();
    }
}

$cvs = cv_list_for_user($pdo, $user_id);

// Which CVs have been used for applications (snapshots preserved).
$usage = [];
$stmt = $pdo->prepare('SELECT s.cv_id, COUNT(*) AS n FROM application_cv_snapshots s JOIN applications a ON a.application_id = s.application_id WHERE a.job_seeker_id = :uid AND s.cv_id IS NOT NULL GROUP BY s.cv_id');
$stmt->execute([':uid' => $user_id]);
foreach ($stmt->fetchAll() as $r) {
    $usage[(int)$r['cv_id']] = (int)$r['n'];
}

$messages = [
    'duplicated' => ['success', 'CV duplicated.'],
    'deleted' => ['success', 'CV deleted. Copies already submitted with applications are kept.'],
    'notfound' => ['danger', 'That CV could not be found.'],
    'csrf' => ['danger', 'Your session token expired. Please try again.'],
    'error' => ['danger', 'Something went wrong. Please try again.'],
];
$msg = $messages[$_GET['msg'] ?? ''] ?? null;
$templateNames = ['classic' => 'Classic', 'modern' => 'Modern', 'minimal' => 'Minimal'];
$sourceNames = ['profile' => 'From profile', 'blank' => 'Blank', 'import' => 'Imported', 'duplicate' => 'Duplicate'];

$page_title = 'CV Studio | TechTalent';
$extra_css = ['../assets/css/cv-studio.css'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container py-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4 text-white" style="background: linear-gradient(135deg, #0f2137 0%, #1a365d 100%);">
        <div class="card-body p-4 p-md-5 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <span class="badge bg-primary text-white mb-2 px-3 py-1.5 rounded-pill">Candidate Portal</span>
                <h2 class="fw-bold mb-1">CV Studio</h2>
                <p class="text-white-50 mb-0">Build, import and tailor CVs for each job. Check ATS readiness and skill gaps before you apply.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-navy px-4 py-2 fw-semibold"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show" role="alert">
            <?= cv_e($msg[1]) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h5 class="fw-bold mb-3">Create a new CV</h5>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <form method="post" class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <input type="hidden" name="csrf_token" value="<?= cv_e($csrf) ?>">
                <input type="hidden" name="action" value="create_profile">
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary fs-3 mb-3 align-self-start"><i class="bi bi-person-badge"></i></div>
                <h5 class="fw-bold">From My Profile</h5>
                <p class="text-muted small flex-grow-1">Start with your name, contact details, headline, bio and skills already filled in from your TechTalent profile.</p>
                <input type="text" name="title" class="form-control form-control-sm mb-2" maxlength="150" placeholder="CV name (optional)">
                <button class="btn btn-primary fw-semibold"><i class="bi bi-magic me-1"></i> Create from Profile</button>
            </form>
        </div>
        <div class="col-md-4">
            <form method="post" class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <input type="hidden" name="csrf_token" value="<?= cv_e($csrf) ?>">
                <input type="hidden" name="action" value="create_blank">
                <div class="bg-secondary bg-opacity-10 p-3 rounded-circle text-secondary fs-3 mb-3 align-self-start"><i class="bi bi-file-earmark-plus"></i></div>
                <h5 class="fw-bold">Start Blank</h5>
                <p class="text-muted small flex-grow-1">Fill in every section yourself. Useful when you want a CV that differs from your profile.</p>
                <input type="text" name="title" class="form-control form-control-sm mb-2" maxlength="150" placeholder="CV name (optional)">
                <button class="btn btn-outline-secondary fw-semibold"><i class="bi bi-pencil me-1"></i> Start Blank CV</button>
            </form>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success fs-3 mb-3 align-self-start"><i class="bi bi-cloud-arrow-up"></i></div>
                <h5 class="fw-bold">Import Existing CV</h5>
                <p class="text-muted small flex-grow-1">Upload a PDF or TXT CV. We extract the text, fill in the sections, and let you review everything before saving.</p>
                <a href="cv-import.php" class="btn btn-success fw-semibold"><i class="bi bi-upload me-1"></i> Import PDF / TXT</a>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold mb-0">My CVs <span class="text-muted fw-normal">(<?= count($cvs) ?>)</span></h5>
    </div>

    <?php if (!$cvs): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
            <i class="bi bi-file-earmark-person fs-1 mb-2"></i>
            <p class="mb-0">You have no saved CVs yet. Create one above to get started.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-white" style="background-color:#0f2137;">
                        <tr>
                            <th class="ps-4 py-3">CV name</th>
                            <th class="py-3">Template</th>
                            <th class="py-3">Created</th>
                            <th class="py-3">Last saved</th>
                            <th class="pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cvs as $cv): $id = (int)$cv['cv_id']; ?>
                        <tr>
                            <td class="ps-4">
                                <a href="cv-editor.php?id=<?= $id ?>" class="fw-semibold text-decoration-none"><?= cv_e($cv['title']) ?></a>
                                <div class="small text-muted">
                                    <?= cv_e($sourceNames[$cv['source']] ?? '') ?>
                                    <?php if (!empty($usage[$id])): ?>
                                        · <span class="text-success"><i class="bi bi-send-check"></i> used in <?= $usage[$id] ?> application<?= $usage[$id] > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= cv_e($templateNames[$cv['template']] ?? $cv['template']) ?></span></td>
                            <td class="small text-muted"><?= cv_e(date('d M Y', strtotime($cv['created_at']))) ?></td>
                            <td class="small text-muted"><?= cv_e(date('d M Y, H:i', strtotime($cv['updated_at']))) ?></td>
                            <td class="pe-4 text-end text-nowrap">
                                <a href="cv-editor.php?id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Edit</a>
                                <a href="cv-print.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Preview / download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= cv_e($csrf) ?>">
                                    <input type="hidden" name="action" value="duplicate">
                                    <input type="hidden" name="cv_id" value="<?= $id ?>">
                                    <button class="btn btn-sm btn-outline-secondary" title="Duplicate"><i class="bi bi-copy"></i></button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this CV? Copies already submitted with job applications will be kept.');">
                                    <input type="hidden" name="csrf_token" value="<?= cv_e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="cv_id" value="<?= $id ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-2"><i class="bi bi-shield-lock me-1"></i>When you apply for a job, a read-only copy of the selected CV is stored with the application. Later edits never change what an employer received.</p>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
