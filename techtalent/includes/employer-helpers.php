<?php
/**
 * Shared helpers for the employer (company) side.
 *  - makes sure the `employers` table has a `company_logo` column
 *  - loads the logged-in company's profile
 *  - validates / stores / resolves company logos
 */

const EMPLOYER_LOGO_DIR_NAME = 'uploads/logos';

/** Create the employers table / logo column if they are missing (safe to call on every request). */
function employer_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS employers (
                employer_id      INT NOT NULL PRIMARY KEY,
                company_name     VARCHAR(150) NOT NULL DEFAULT '',
                company_location VARCHAR(150) NULL,
                website          VARCHAR(255) NULL,
                contact_email    VARCHAR(150) NULL,
                company_bio      TEXT NULL,
                company_logo     VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $has = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employers' AND COLUMN_NAME = 'company_logo'"
        )->fetchColumn();
        if ($has === 0) {
            $pdo->exec("ALTER TABLE employers ADD COLUMN company_logo VARCHAR(255) NULL");
        }
    } catch (PDOException $e) {
        error_log('employer_ensure_schema: ' . $e->getMessage());
    }
}

/**
 * Profile of one employer account.
 * company_name is '' when the company has not been named yet.
 */
function employer_profile(PDO $pdo, int $uid): array
{
    employer_ensure_schema($pdo);
    $profile = [
        'company_name'     => '',
        'company_location' => '',
        'website'          => '',
        'contact_email'    => '',
        'company_bio'      => '',
        'company_logo'     => null,
        'exists'           => false,
    ];
    try {
        $stmt = $pdo->prepare('SELECT * FROM employers WHERE employer_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($profile as $k => $_) {
                if (array_key_exists($k, $row) && $row[$k] !== null) {
                    $profile[$k] = $row[$k];
                }
            }
            $profile['exists'] = true;
        }
    } catch (PDOException $e) {
        error_log('employer_profile: ' . $e->getMessage());
    }
    if (trim((string)$profile['company_name']) === '') {
        // Fall back to what was typed at registration (kept in the session)
        $profile['company_name'] = trim((string)($_SESSION['company_name'] ?? $_SESSION['company'] ?? ''));
    }
    $profile['company_name'] = trim((string)$profile['company_name']);
    return $profile;
}

/** Only ever accept filenames we generated ourselves. */
function employer_logo_filename_ok(?string $file): bool
{
    return $file !== null && (bool)preg_match('/^logo_[A-Za-z0-9_]+\.(png|jpe?g)$/', $file);
}

/** Absolute folder where logos live. */
function employer_logo_dir(): string
{
    return dirname(__DIR__) . '/' . EMPLOYER_LOGO_DIR_NAME . '/';
}

/** Browser URL (relative to employer/ and auth/ pages) of a stored logo, or null if there is none. */
function employer_logo_url(?string $file): ?string
{
    if (!employer_logo_filename_ok($file)) {
        return null;
    }
    $path = employer_logo_dir() . $file;
    if (!is_file($path)) {
        return null;
    }
    return '../' . EMPLOYER_LOGO_DIR_NAME . '/' . rawurlencode($file) . '?v=' . filemtime($path);
}

/** Delete a stored logo file (ignores anything that is not one of our generated names). */
function employer_delete_logo_file(?string $file): void
{
    if (employer_logo_filename_ok($file)) {
        $path = employer_logo_dir() . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Validate and store an uploaded logo.
 * @return array [filename|null, error|null]  - [null, null] means "no file was uploaded".
 */
function employer_store_logo(?array $file): array
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The logo could not be uploaded. Please try again with a smaller PNG or JPG file.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return [null, 'Logo file size exceeds the 2MB limit.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        return [null, 'Invalid logo format. Only PNG, JPG and JPEG are allowed.'];
    }
    // Check the real content, not just the file name
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
        return [null, 'The selected file is not a valid PNG or JPG image.'];
    }

    $dir = employer_logo_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [null, 'The logo folder could not be created on the server.'];
    }
    // Never let anything in this folder run as a script
    $ht = $dir . '.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n<FilesMatch \"\\.(php|phtml|php[0-9])$\">\n  Require all denied\n</FilesMatch>\n");
    }

    $name = 'logo_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return [null, 'The logo could not be saved on the server.'];
    }
    return [$name, null];
}
