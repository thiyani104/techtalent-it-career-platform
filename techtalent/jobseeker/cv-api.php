<?php
/**
 * CV Studio JSON endpoint (job seekers only).
 * Actions: save, ats, gap, create_import. Every write is CSRF- and ownership-checked.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';
require_once __DIR__ . '/../ai/ats-checker.php';
require_once __DIR__ . '/../ai/skill-gap-analyzer.php';

header('Content-Type: application/json; charset=utf-8');
cv_private_headers();

function cv_api_reply(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true)) {
    cv_api_reply(['ok' => false, 'error' => 'Your session has expired. Please log in again.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cv_api_reply(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
$raw = file_get_contents('php://input', false, null, 0, 600000);
$in = json_decode((string)$raw, true);
if (!is_array($in)) {
    cv_api_reply(['ok' => false, 'error' => 'Invalid request.'], 400);
}
if (!cv_csrf_valid($in['csrf'] ?? null)) {
    cv_api_reply(['ok' => false, 'error' => 'Security token expired. Reload the page and try again.'], 403);
}

$user_id = (int)$_SESSION['user_id'];
$action = (string)($in['action'] ?? '');
$content = cv_normalize_content($in['content'] ?? []);
$template = in_array($in['template'] ?? '', CV_TEMPLATES, true) ? $in['template'] : 'classic';

try {
    switch ($action) {
        case 'save':
            $cv = cv_fetch_owned($pdo, (int)($in['cv_id'] ?? 0), $user_id);
            if (!$cv) {
                cv_api_reply(['ok' => false, 'error' => 'CV not found.'], 404);
            }
            $title = cv_clean_string($in['title'] ?? '', 150);
            if ($title === '') {
                cv_api_reply(['ok' => false, 'error' => 'Please give this CV a name.'], 422);
            }
            // Keep import diagnostics that the client does not edit.
            $existing = cv_decode($cv['content']);
            if (!empty($existing['meta'])) {
                $content['meta'] = $existing['meta'];
            }
            $stmt = $pdo->prepare('UPDATE cvs SET title = :t, template = :tpl, accent_color = :a, content = :c WHERE cv_id = :id AND user_id = :uid');
            $stmt->execute([
                ':t' => $title, ':tpl' => $template, ':a' => cv_valid_color($in['accent'] ?? ''),
                ':c' => cv_encode($content), ':id' => $cv['cv_id'], ':uid' => $user_id,
            ]);
            cv_api_reply(['ok' => true, 'saved_at' => date('H:i:s')]);

        case 'ats':
            cv_api_reply(['ok' => true, 'result' => ats_check_cv($content, $template)]);

        case 'gap':
            $stmt = $pdo->prepare("SELECT job_id, job_title, company_name, required_skills, optional_skills FROM jobs WHERE job_id = :id AND status = 'Published' LIMIT 1");
            $stmt->execute([':id' => (int)($in['job_id'] ?? 0)]);
            $job = $stmt->fetch();
            if (!$job) {
                cv_api_reply(['ok' => false, 'error' => 'This vacancy is no longer available.'], 404);
            }
            cv_api_reply(['ok' => true, 'job' => ['job_title' => $job['job_title'], 'company_name' => $job['company_name']], 'result' => cv_skill_gap($pdo, $content, $job)]);

        case 'create_import':
            $token = (string)($in['token'] ?? '');
            $pending = $_SESSION['cv_import'][$token] ?? null;
            if ($token === '' || !$pending || (int)$pending['user_id'] !== $user_id) {
                cv_api_reply(['ok' => false, 'error' => 'This import has expired. Please upload the file again.'], 410);
            }
            $title = cv_clean_string($in['title'] ?? '', 150);
            if ($title === '') {
                cv_api_reply(['ok' => false, 'error' => 'Please give this CV a name.'], 422);
            }
            $content['meta']['import'] = $pending['meta'];
            $cv_id = cv_create($pdo, $user_id, $title, $content, 'import', $template, (string)($in['accent'] ?? ''));
            $stmt = $pdo->prepare('UPDATE cv_uploads SET cv_id = :cv WHERE upload_id = :up AND user_id = :uid');
            $stmt->execute([':cv' => $cv_id, ':up' => (int)$pending['upload_id'], ':uid' => $user_id]);
            unset($_SESSION['cv_import'][$token]);
            cv_api_reply(['ok' => true, 'redirect' => 'cv-editor.php?id=' . $cv_id . '&created=import']);

        default:
            cv_api_reply(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (PDOException $e) {
    error_log('CV API error (' . $action . '): ' . $e->getCode());
    cv_api_reply(['ok' => false, 'error' => 'A database error occurred. Please try again.'], 500);
}
