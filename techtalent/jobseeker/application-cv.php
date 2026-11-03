<?php
/** Job seeker view of the CV snapshot they submitted with an application. */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';

$user_id = cv_require_job_seeker();
$stmt = $pdo->prepare(
    'SELECT s.*, j.job_title
     FROM application_cv_snapshots s
     JOIN applications a ON a.application_id = s.application_id
     JOIN jobs j ON j.job_id = a.job_id
     WHERE s.application_id = :app AND a.job_seeker_id = :uid
     LIMIT 1'
);
$stmt->execute([':app' => (int)($_GET['application_id'] ?? 0), ':uid' => $user_id]);
$snap = $stmt->fetch();
if (!$snap) {
    http_response_code(404);
    exit('CV not found.');
}
cv_snapshot_render($snap, 'applications.php', 'Submitted for ' . $snap['job_title']);
