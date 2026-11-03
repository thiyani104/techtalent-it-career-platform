<?php
/**
 * Employer view of the CV snapshot submitted with an application.
 * Only the employer who owns the vacancy can open it.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
    header('Location: ../auth/login.php');
    exit();
}
cv_private_headers();

$stmt = $pdo->prepare(
    'SELECT s.*, j.job_title, u.full_name
     FROM application_cv_snapshots s
     JOIN applications a ON a.application_id = s.application_id
     JOIN jobs j ON j.job_id = a.job_id
     JOIN users u ON u.user_id = a.job_seeker_id
     WHERE s.application_id = :app AND j.employer_id = :emp
     LIMIT 1'
);
$stmt->execute([':app' => (int)($_GET['application_id'] ?? 0), ':emp' => (int)$_SESSION['user_id']]);
$snap = $stmt->fetch();
if (!$snap) {
    http_response_code(404);
    exit('CV not found or you do not have access to it.');
}
cv_snapshot_render($snap, 'applicants.php', $snap['full_name'] . ' – ' . $snap['job_title']);
