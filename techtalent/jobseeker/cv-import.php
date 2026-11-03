<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';
require_once __DIR__ . '/../ai/cv-parser.php';

$user_id = cv_require_job_seeker();
$csrf = cv_csrf_token();
$error = null;
$notice = null;

// ---------------------------------------------------------------------
// Step 1: upload → validate → store privately → extract → parse
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cv_csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session token expired. Please try again.';
    } else {
        $check = cv_validate_upload($_FILES['cv_file'] ?? null);
        if (!$check['ok']) {
            $error = $check['error'];
        } else {
            $file = $_FILES['cv_file'];
            $sha = hash_file('sha256', $file['tmp_name']);
            $original = cv_clean_string(basename((string)$file['name']), 255);
            try {
                $stored = cv_store_upload($file['tmp_name']);
                $path = CV_PRIVATE_STORAGE . DIRECTORY_SEPARATOR . $stored;
                $result = cv_extract_text($path, $check['ext']);

                $stmt = $pdo->prepare(
                    'INSERT INTO cv_uploads (user_id, original_name, stored_name, mime_type, file_size, sha256, extraction_status, extracted_chars)
                     VALUES (:uid, :orig, :stored, :mime, :size, :sha, :status, :chars)'
                );
                $stmt->execute([
                    ':uid' => $user_id, ':orig' => $original, ':stored' => $stored, ':mime' => $check['mime'],
                    ':size' => (int)$file['size'], ':sha' => $sha, ':status' => $result['status'], ':chars' => mb_strlen($result['text']),
                ]);
                $upload_id = (int)$pdo->lastInsertId();

                if (in_array($result['status'], ['no_text', 'failed'], true)) {
                    $error = $result['message'] ?: 'No readable text could be extracted from this file.';
                } else {
                    $parsed = cv_parse_text($pdo, $result['text']);
                    $token = bin2hex(random_bytes(16));
                    // Keep at most a few pending imports per session.
                    $_SESSION['cv_import'] = array_slice($_SESSION['cv_import'] ?? [], -2, null, true);
                    $_SESSION['cv_import'][$token] = [
                        'user_id' => $user_id,
                        'upload_id' => $upload_id,
                        'file' => $original,
                        'content' => $parsed['content'],
                        'report' => $parsed['report'],
                        'text' => mb_substr($result['text'], 0, 30000),
                        'message' => $result['message'],
                        'meta' => ['method' => $result['method'], 'chars' => mb_strlen($result['text']), 'garbled_ratio' => $result['garbled_ratio']],
                    ];
                    header('Location: cv-import.php?review=' . $token);
                    exit();
                }
            } catch (Throwable $e) {
                error_log('CV import failed: ' . get_class($e));
                $error = 'The file could not be processed. Please try another file.';
            }
        }
    }
}

// ---------------------------------------------------------------------
// Step 2: review screen
// ---------------------------------------------------------------------
$review = null;
$token = (string)($_GET['review'] ?? '');
if ($token !== '') {
    $review = $_SESSION['cv_import'][$token] ?? null;
    if (!$review || (int)$review['user_id'] !== $user_id) {
        $review = null;
        $error = 'This import session has expired. Please upload the file again.';
    }
}

$page_title = ($review ? 'Review Imported CV' : 'Import CV') . ' | TechTalent';
$extra_css = ['../assets/css/cv-studio.css'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<?php if (!$review): ?>
<div class="container py-5" style="max-width: 760px;">
    <a href="cv-studio.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to CV Studio</a>
    <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-body p-4 p-md-5">
            <h3 class="fw-bold mb-1">Import an existing CV</h3>
            <p class="text-muted">Upload a <strong>PDF</strong> or <strong>TXT</strong> file (max <?= CV_MAX_UPLOAD_BYTES / 1048576 ?> MB). We read the text on our server (no external services), detect sections and skills, and show you everything to review before anything is saved.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex gap-2"><i class="bi bi-exclamation-octagon-fill"></i><div><?= cv_e($error) ?></div></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="importForm">
                <input type="hidden" name="csrf_token" value="<?= cv_e($csrf) ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= CV_MAX_UPLOAD_BYTES ?>">
                <label for="cvFile" class="form-label fw-semibold">CV file</label>
                <input type="file" class="form-control mb-3" id="cvFile" name="cv_file" accept=".pdf,.txt,application/pdf,text/plain" required>
                <button class="btn btn-success fw-semibold px-4" id="importBtn"><i class="bi bi-upload me-1"></i> Upload &amp; extract</button>
            </form>
            <hr class="my-4">
            <ul class="small text-muted mb-0">
                <li>Works best with text-based PDFs exported from Word, Google Docs or similar.</li>
                <li>Scanned/photographed CVs contain no text layer. OCR is not supported, so you will see a message instead of guessed content.</li>
                <li>Details that cannot be found are left empty for you to fill in; nothing is invented.</li>
                <li>Your file is stored privately and is never publicly accessible.</li>
            </ul>
        </div>
    </div>
</div>
<script>
document.getElementById('importForm').addEventListener('submit', function (e) {
    var f = document.getElementById('cvFile').files[0];
    if (f && f.size > <?= CV_MAX_UPLOAD_BYTES ?>) { e.preventDefault(); alert('The file is larger than <?= CV_MAX_UPLOAD_BYTES / 1048576 ?> MB.'); return; }
    var b = document.getElementById('importBtn');
    b.disabled = true;
    b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Extracting…';
});
</script>
<?php else: ?>
<?php
$editor_mode = 'import';
$baseName = preg_replace('/\.(pdf|txt)$/i', '', $review['file']);
$editor_config = [
    'mode' => 'import',
    'cvId' => null,
    'csrf' => $csrf,
    'api' => 'cv-api.php',
    'importToken' => $token,
    'title' => mb_substr('Imported – ' . $baseName, 0, 150),
    'template' => 'classic',
    'accent' => '#0f2137',
    'data' => $review['content'],
    'preselectJob' => null,
];
$report = $review['report'];
?>
<div class="container-fluid px-lg-4 py-4 cv-print-root">
    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
        <a href="cv-import.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Upload a different file</a>
        <span class="badge bg-warning text-dark">Review step: nothing is saved until you click “Save as new CV”</span>
    </div>
    <div class="card border-0 shadow-sm rounded-4 mb-3 no-print">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-7">
                    <h5 class="fw-bold mb-2"><i class="bi bi-clipboard-data me-2 text-primary"></i>Extraction results for “<?= cv_e($review['file']) ?>”</h5>
                    <?php if ($review['message']): ?>
                        <div class="alert alert-warning small py-2"><?= cv_e($review['message']) ?></div>
                    <?php endif; ?>
                    <div class="small mb-2"><strong class="text-success"><i class="bi bi-check-circle me-1"></i>Found:</strong>
                        <?= $report['found'] ? cv_e(implode(', ', $report['found'])) : '<span class="text-muted">nothing</span>' ?></div>
                    <div class="small mb-2"><strong class="text-danger"><i class="bi bi-question-circle me-1"></i>Not found (please add if relevant):</strong>
                        <?= $report['missing'] ? cv_e(implode(', ', $report['missing'])) : '<span class="text-muted">none</span>' ?></div>
                    <?php if (!empty($report['taxonomy_skills'])): ?>
                        <div class="small mb-2"><strong><i class="bi bi-tags me-1"></i>Recognised platform skills:</strong> <?= cv_e(implode(', ', $report['taxonomy_skills'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($report['unassigned'])): ?>
                        <div class="small text-muted">Text under sections we do not map (references, interests, awards…) was not imported: <?= count($report['unassigned']) ?> line(s). See the extracted text if you want to copy any of it.</div>
                    <?php endif; ?>
                    <p class="small text-muted mt-2 mb-0">Section detection is rule-based. Check names, dates and the split between position and company carefully, then correct anything below.</p>
                </div>
                <div class="col-lg-5">
                    <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#rawText"><i class="bi bi-file-text me-1"></i>Show extracted text</button>
                    <div class="collapse" id="rawText"><div class="cv-raw-text"><?= cv_e($review['text']) ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/cv-editor-ui.php'; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
