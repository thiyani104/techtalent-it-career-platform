<?php
// SMTP self-test. Admin only. Open: /config/test-mail.php?to=you@example.com
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrators only.');
}
require_once __DIR__ . '/mail.php';

$to = trim($_GET['to'] ?? '');
header('Content-Type: text/html; charset=UTF-8');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit('Add ?to=your-email@example.com to the URL.');
}
$result = sendOtpEmail($to, 'Test User', '749201');
echo $result === true
    ? "<h2 style='color:green'>Success! Test email delivered to " . htmlspecialchars($to) . "</h2>"
    : "<h2 style='color:red'>Failed</h2><p>" . htmlspecialchars((string)$result) . "</p>";
