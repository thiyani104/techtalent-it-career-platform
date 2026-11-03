<?php
/**
 * CV Studio core helpers: data model, sanitisation, CSRF, ownership-checked CRUD.
 */

require_once __DIR__ . '/../config/cv_studio.php';

const CV_TEMPLATES = ['classic', 'modern', 'minimal'];
const CV_LEVELS = ['', 'Beginner', 'Intermediate', 'Advanced', 'Expert'];
const CV_SECTION_KEYS = ['summary', 'experience', 'education', 'skills', 'projects', 'certifications', 'languages'];
const CV_OPTIONAL_SECTIONS = ['projects', 'certifications', 'languages'];

// Field schema per repeatable section: field => max length.
const CV_ENTRY_SCHEMA = [
    'education' => ['institution' => 200, 'qualification' => 200, 'field' => 200, 'start' => 30, 'end' => 30, 'description' => 2000],
    'experience' => ['company' => 200, 'position' => 200, 'location' => 150, 'start' => 30, 'end' => 30, 'responsibilities' => 4000, 'achievements' => 3000],
    'projects' => ['name' => 200, 'role' => 150, 'link' => 300, 'start' => 30, 'end' => 30, 'description' => 3000],
    'certifications' => ['name' => 200, 'issuer' => 200, 'date' => 30, 'link' => 300],
    'languages' => ['name' => 100, 'level' => 60],
];
const CV_PERSONAL_SCHEMA = [
    'full_name' => 150, 'title' => 200, 'email' => 150, 'phone' => 40, 'location' => 150,
    'linkedin' => 300, 'github' => 300, 'portfolio' => 300, 'summary' => 3000,
];

function cv_require_job_seeker(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true)) {
        header('Location: ../auth/login.php');
        exit();
    }
    cv_private_headers();
    return (int)$_SESSION['user_id'];
}

/** Keep CV pages out of shared caches and search engines. */
function cv_private_headers(): void
{
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
}

function cv_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function cv_csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function cv_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Clean a user string: valid UTF-8, no control chars (except newlines/tabs), length-capped. */
function cv_clean_string($value, int $max): string
{
    if (!is_scalar($value)) {
        return '';
    }
    $value = (string)$value;
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

function cv_empty_content(): array
{
    $personal = array_fill_keys(array_keys(CV_PERSONAL_SCHEMA), '');
    return [
        'personal' => $personal,
        'education' => [],
        'experience' => [],
        'skills' => ['technical' => [], 'soft' => []],
        'projects' => [],
        'certifications' => [],
        'languages' => [],
        'hidden' => array_fill_keys(CV_OPTIONAL_SECTIONS, false),
        'order' => CV_SECTION_KEYS,
        'meta' => [],
    ];
}

/** Whitelist and sanitise arbitrary decoded JSON into the canonical CV structure. */
function cv_normalize_content($data): array
{
    $out = cv_empty_content();
    if (!is_array($data)) {
        return $out;
    }
    $personal = is_array($data['personal'] ?? null) ? $data['personal'] : [];
    foreach (CV_PERSONAL_SCHEMA as $field => $max) {
        $out['personal'][$field] = cv_clean_string($personal[$field] ?? '', $max);
    }
    foreach (CV_ENTRY_SCHEMA as $section => $fields) {
        $entries = is_array($data[$section] ?? null) ? array_slice(array_values($data[$section]), 0, CV_MAX_ENTRIES) : [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $clean = [];
            foreach ($fields as $field => $max) {
                $clean[$field] = cv_clean_string($entry[$field] ?? '', $max);
            }
            if (implode('', $clean) !== '') {
                $out[$section][] = $clean;
            }
        }
    }
    foreach (['technical', 'soft'] as $type) {
        $list = is_array($data['skills'][$type] ?? null) ? array_slice(array_values($data['skills'][$type]), 0, 60) : [];
        $seen = [];
        foreach ($list as $skill) {
            $name = cv_clean_string(is_array($skill) ? ($skill['name'] ?? '') : $skill, 80);
            $level = is_array($skill) ? (string)($skill['level'] ?? '') : '';
            $key = mb_strtolower($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out['skills'][$type][] = ['name' => $name, 'level' => in_array($level, CV_LEVELS, true) ? $level : ''];
        }
    }
    foreach (CV_OPTIONAL_SECTIONS as $section) {
        $out['hidden'][$section] = !empty($data['hidden'][$section]);
    }
    if (is_array($data['order'] ?? null)) {
        $order = array_values(array_unique(array_intersect($data['order'], CV_SECTION_KEYS)));
        $out['order'] = array_merge($order, array_values(array_diff(CV_SECTION_KEYS, $order)));
    }
    // Import metadata is informational only (used by the ATS checker).
    if (is_array($data['meta']['import'] ?? null)) {
        $imp = $data['meta']['import'];
        $out['meta']['import'] = [
            'method' => cv_clean_string($imp['method'] ?? '', 30),
            'chars' => (int)($imp['chars'] ?? 0),
            'garbled_ratio' => (float)($imp['garbled_ratio'] ?? 0),
        ];
    }
    return $out;
}

function cv_encode(array $content): string
{
    return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cv_decode(?string $json): array
{
    return cv_normalize_content(json_decode((string)$json, true));
}

/** Build a CV pre-filled from the existing profile tables (users, job_seeker_profiles, user_skills). */
function cv_content_from_profile(PDO $pdo, int $user_id): array
{
    $content = cv_empty_content();
    $stmt = $pdo->prepare(
        'SELECT u.full_name, u.email, p.headline, p.phone, p.location, p.bio
         FROM users u LEFT JOIN job_seeker_profiles p ON p.user_id = u.user_id
         WHERE u.user_id = :uid LIMIT 1'
    );
    $stmt->execute([':uid' => $user_id]);
    $row = $stmt->fetch() ?: [];
    $content['personal']['full_name'] = (string)($row['full_name'] ?? '');
    $content['personal']['email'] = (string)($row['email'] ?? '');
    $content['personal']['title'] = (string)($row['headline'] ?? '');
    $content['personal']['phone'] = (string)($row['phone'] ?? '');
    $content['personal']['location'] = (string)($row['location'] ?? '');
    $content['personal']['summary'] = (string)($row['bio'] ?? '');

    $stmt = $pdo->prepare(
        'SELECT s.skill_name, us.proficiency_level FROM user_skills us
         JOIN skills s ON s.skill_id = us.skill_id WHERE us.user_id = :uid ORDER BY s.skill_name'
    );
    $stmt->execute([':uid' => $user_id]);
    foreach ($stmt->fetchAll() as $s) {
        $content['skills']['technical'][] = ['name' => $s['skill_name'], 'level' => $s['proficiency_level']];
    }
    return cv_normalize_content($content);
}

/** Fetch a CV only if it belongs to $user_id; returns null otherwise. */
function cv_fetch_owned(PDO $pdo, int $cv_id, int $user_id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cvs WHERE cv_id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $cv_id, ':uid' => $user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cv_list_for_user(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare('SELECT cv_id, title, template, source, created_at, updated_at FROM cvs WHERE user_id = :uid ORDER BY updated_at DESC, cv_id DESC');
    $stmt->execute([':uid' => $user_id]);
    return $stmt->fetchAll();
}

function cv_create(PDO $pdo, int $user_id, string $title, array $content, string $source, string $template = 'classic', string $accent = '#0f2137'): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO cvs (user_id, title, template, accent_color, content, source)
         VALUES (:uid, :title, :tpl, :accent, :content, :source)'
    );
    $stmt->execute([
        ':uid' => $user_id,
        ':title' => cv_clean_string($title, 150) ?: 'Untitled CV',
        ':tpl' => in_array($template, CV_TEMPLATES, true) ? $template : 'classic',
        ':accent' => cv_valid_color($accent),
        ':content' => cv_encode(cv_normalize_content($content)),
        ':source' => $source,
    ]);
    return (int)$pdo->lastInsertId();
}

function cv_valid_color($color): string
{
    return (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) ? strtolower($color) : '#0f2137';
}

/** Flatten CV content to plain text (used by the ATS checker and skill detection). */
function cv_plain_text(array $c): string
{
    $parts = array_values($c['personal']);
    foreach (array_keys(CV_ENTRY_SCHEMA) as $section) {
        foreach ($c[$section] as $entry) {
            $parts = array_merge($parts, array_values($entry));
        }
    }
    foreach (['technical', 'soft'] as $t) {
        foreach ($c['skills'][$t] as $s) {
            $parts[] = $s['name'];
        }
    }
    return trim(implode("\n", array_filter($parts, 'strlen')));
}

/* ---------------------------------------------------------------------
 * Application CV snapshots (immutable copies submitted with applications)
 * ------------------------------------------------------------------- */

/** Store a frozen copy of $cv for an application. Call inside the application transaction. */
function cv_snapshot_create(PDO $pdo, int $application_id, array $cv): void
{
    $content = cv_encode(cv_decode($cv['content']));
    $stmt = $pdo->prepare(
        'INSERT INTO application_cv_snapshots (application_id, cv_id, cv_title, template, accent_color, content, content_hash)
         VALUES (:app, :cv, :title, :tpl, :accent, :content, :hash)'
    );
    $stmt->execute([
        ':app' => $application_id, ':cv' => (int)$cv['cv_id'], ':title' => $cv['title'],
        ':tpl' => $cv['template'], ':accent' => $cv['accent_color'],
        ':content' => $content, ':hash' => hash('sha256', $content),
    ]);
}

/** Render a snapshot row with the print view; verifies the stored integrity hash. */
function cv_snapshot_render(array $snap, string $back_url, string $context): void
{
    $verified = hash_equals($snap['content_hash'], hash('sha256', $snap['content']));
    $print_title = $snap['cv_title'];
    $print_content = cv_decode($snap['content']);
    $print_template = $snap['template'];
    $print_accent = $snap['accent_color'];
    $print_back_url = $back_url;
    $print_note = $context . ' · submitted ' . date('d M Y H:i', strtotime($snap['created_at']))
        . ($verified ? ' · unchanged since submission' : ' · WARNING: integrity check failed');
    $print_autoprint = false;
    require __DIR__ . '/cv-print-view.php';
}
