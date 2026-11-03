<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';

$user_id = cv_require_job_seeker();
$cv = cv_fetch_owned($pdo, (int)($_GET['id'] ?? 0), $user_id);
if (!$cv) {
    header('Location: cv-studio.php?msg=notfound');
    exit();
}

$editor_mode = 'edit';
$editor_config = [
    'mode' => 'edit',
    'cvId' => (int)$cv['cv_id'],
    'csrf' => cv_csrf_token(),
    'api' => 'cv-api.php',
    'title' => $cv['title'],
    'template' => $cv['template'],
    'accent' => $cv['accent_color'],
    'data' => cv_decode($cv['content']),
    'preselectJob' => (int)($_GET['job'] ?? 0) ?: null,
];

$page_title = 'Edit CV – ' . $cv['title'] . ' | TechTalent';
$extra_css = ['../assets/css/cv-studio.css'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid px-lg-4 py-4 cv-print-root">
    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
        <a href="cv-studio.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to CV Studio</a>
        <span class="small text-muted">Tip: changes are saved automatically a few seconds after you stop typing (Ctrl+S to save now).</span>
    </div>
    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success alert-dismissible fade show no-print py-2" role="alert">
            <?= $_GET['created'] === 'import' ? 'Imported CV saved. Keep refining it below.' : 'CV created. Fill in the sections on the left; the preview updates as you type.' ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../includes/cv-editor-ui.php'; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
