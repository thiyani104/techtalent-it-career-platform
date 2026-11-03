<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/cv-functions.php';

$user_id = cv_require_job_seeker();
$cv = cv_fetch_owned($pdo, (int)($_GET['id'] ?? 0), $user_id);
if (!$cv) {
    http_response_code(404);
    exit('CV not found.');
}

$print_title = $cv['title'];
$print_content = cv_decode($cv['content']);
$print_template = $cv['template'];
$print_accent = $cv['accent_color'];
$print_back_url = 'cv-editor.php?id=' . (int)$cv['cv_id'];
$print_autoprint = isset($_GET['autoprint']);
require __DIR__ . '/../includes/cv-print-view.php';
