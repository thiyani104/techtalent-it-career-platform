
<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------
// Admin authentication
// ---------------------------------------------------------
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = 'Admin Dashboard - TechTalent Platform';

// ---------------------------------------------------------
// Database statistics
// Keep the existing database table and column names.
// ---------------------------------------------------------
$user_counts = [];
$vacancy_counts = [];
$recent_users = [];
$total_skills = 0;
$pending_reviews = 0;
$pending_inquiries = 0;
$dashboard_error = false;

try {
    // User statistics
    $stmt = $pdo->query(
        "SELECT role, COUNT(*) AS cnt
         FROM users
         GROUP BY role"
    );

    $user_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $job_seekers = (int)($user_counts['job_seeker'] ?? 0);
    $employers = (int)($user_counts['employer'] ?? 0);
    $admins = (int)($user_counts['admin'] ?? 0);

    $total_users = $job_seekers + $employers + $admins;

    // Vacancy statistics
    $stmt = $pdo->query(
        "SELECT status, COUNT(*) AS cnt
         FROM jobs
         GROUP BY status"
    );

    $vacancy_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $active_vacancies = (int)($vacancy_counts['Published'] ?? 0);
    $closed_vacancies = (int)($vacancy_counts['Closed'] ?? 0);
    $pending_vacancies = (int)($vacancy_counts['Pending'] ?? 0);
    $draft_vacancies = (int)($vacancy_counts['Draft'] ?? 0);

    $total_vacancies = array_sum(
        array_map('intval', $vacancy_counts)
    );

    // Skills
    $total_skills = (int)$pdo
        ->query("SELECT COUNT(*) FROM skills")
        ->fetchColumn();

    // Pending reviews
    $pending_reviews = (int)$pdo
        ->query(
            "SELECT COUNT(*)
             FROM reviews
             WHERE status = 'pending'"
        )
        ->fetchColumn();

    // Contact inquiries
    // A missing contact_messages table will not stop the dashboard.
    try {
        $pending_inquiries = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM contact_messages
                 WHERE status = 'new'"
            )
            ->fetchColumn();
    } catch (PDOException $e) {
        $pending_inquiries = 0;
    }

    // Recent registrations
    $stmt = $pdo->query(
        "SELECT user_id, full_name, email, role, created_at
         FROM users
         ORDER BY created_at DESC
         LIMIT 6"
    );

    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log details privately; do not expose database credentials or
    // database errors to visitors.
    error_log('TechTalent dashboard database error: ' . $e->getMessage());
    $dashboard_error = true;

    $job_seekers = $job_seekers ?? 0;
    $employers = $employers ?? 0;
    $admins = $admins ?? 0;
    $total_users = $total_users ?? 0;

    $active_vacancies = $active_vacancies ?? 0;
    $closed_vacancies = $closed_vacancies ?? 0;
    $pending_vacancies = $pending_vacancies ?? 0;
    $draft_vacancies = $draft_vacancies ?? 0;
    $total_vacancies = $total_vacancies ?? 0;
}

$admin_name = $_SESSION['full_name'] ?? 'Administrator';

function dashboard_escape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function dashboard_date($value): string
{
    if (empty($value)) {
        return 'Not available';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp !== false
        ? date('d M Y, H:i', $timestamp)
        : 'Not available';
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<link
    rel="stylesheet"
    href="../assets/css/admin-dashboard.css"
>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="admin-dashboard">
    <div class="admin-layout">

        <!-- Sidebar -->
        <aside class="admin-sidebar">

            <a href="dashboard.php" class="admin-brand">
                <span class="admin-brand-icon">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>

                <span class="admin-brand-text">
                    <strong>TechTalent</strong>
                    <small>ADMIN CONTROL CENTER</small>
                </span>
            </a>

            <div class="admin-sidebar-label">Main Menu</div>

            <nav class="admin-nav">
                <a href="dashboard.php" class="active">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>

                <a href="../guest/jobs.php">
                    <i class="bi bi-briefcase-fill"></i>
                    <span>Vacancies</span>
                </a>

                <a href="manage-jobs.php">
                    <i class="bi bi-list-check"></i>
                    <span>Manage Jobs</span>
                </a>
            </nav>

            <div class="admin-sidebar-label">Management</div>

            <nav class="admin-nav">
                <a href="manage-inquiries.php">
                    <i class="bi bi-envelope-paper-fill"></i>
                    <span>Contact Inquiries</span>

                    <?php if ($pending_inquiries > 0): ?>
                        <span class="admin-nav-badge">
                            <?= $pending_inquiries ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="manage-reviews.php">
                    <i class="bi bi-star-fill"></i>
                    <span>Reviews</span>

                    <?php if ($pending_reviews > 0): ?>
                        <span class="admin-nav-badge">
                            <?= $pending_reviews ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="skills.php">
                    <i class="bi bi-tools"></i>
                    <span>Skills</span>
                </a>
            </nav>

            <div class="admin-sidebar-label">Account</div>

            <nav class="admin-nav">
                <a href="../guest/index.php">
                    <i class="bi bi-house-door-fill"></i>
                    <span>View Website</span>
                </a>

                <a href="../auth/logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <span class="admin-security-icon">
                    <i class="bi bi-shield-check"></i>
                </span>

                <span>
                    <strong>Administrator</strong>
                    <small>Secure access enabled</small>
                </span>
            </div>

        </aside>

        <!-- Main dashboard -->
        <main class="admin-main">

            <header class="admin-topbar">
                <div class="admin-heading">
                    <div class="admin-eyebrow">
                        TECHTALENT SOLUTIONS
                    </div>

                    <h1 class="admin-title">
                        Good day, <?= dashboard_escape($admin_name) ?>!
                        <span aria-hidden="true">👋</span>
                    </h1>

                    <p class="admin-subtitle">
                        Welcome to your control center. Monitor platform
                        activity, manage vacancies and review users.
                    </p>
                </div>

                <div class="admin-date">
                    <i class="bi bi-calendar3"></i>
                    <span><?= date('d M Y') ?></span>
                </div>
            </header>

            <?php if ($dashboard_error): ?>
                <div class="admin-notice admin-notice-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>Some dashboard information could not load.</strong>
                        <p>
                            Check the PHP error log for the database error.
                            The original database tables have not been changed.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <section class="admin-kpis" aria-label="Platform statistics">

                <article class="admin-kpi">
                    <div class="admin-kpi-top">
                        <span class="admin-kpi-icon blue">
                            <i class="bi bi-people-fill"></i>
                        </span>
                        <span class="admin-kpi-tag">USERS</span>
                    </div>

                    <div class="admin-kpi-label">Total Users</div>

                    <div class="admin-kpi-value">
                        <?= $total_users ?>
                    </div>

                    <div class="admin-kpi-meta">
                        <?= $job_seekers ?> job seekers
                        <span class="admin-meta-dot">·</span>
                        <?= $employers ?> employers
                    </div>
                </article>

                <article class="admin-kpi">
                    <div class="admin-kpi-top">
                        <span class="admin-kpi-icon green">
                            <i class="bi bi-briefcase-fill"></i>
                        </span>
                        <span class="admin-kpi-tag">JOBS</span>
                    </div>

                    <div class="admin-kpi-label">Total Vacancies</div>

                    <div class="admin-kpi-value">
                        <?= $total_vacancies ?>
                    </div>

                    <div class="admin-kpi-meta">
                        <?= $active_vacancies ?> published
                        <span class="admin-meta-dot">·</span>
                        <?= $pending_vacancies ?> pending
                    </div>
                </article>

                <article class="admin-kpi">
                    <div class="admin-kpi-top">
                        <span class="admin-kpi-icon purple">
                            <i class="bi bi-star-fill"></i>
                        </span>
                        <span class="admin-kpi-tag">MODERATION</span>
                    </div>

                    <div class="admin-kpi-label">Pending Reviews</div>

                    <div class="admin-kpi-value">
                        <?= $pending_reviews ?>
                    </div>

                    <div class="admin-kpi-meta">
                        <?= $total_skills ?> skills available
                    </div>
                </article>

            </section>

            <!-- Contact inquiry alert -->
            <?php if ($pending_inquiries > 0): ?>
                <section class="admin-alert">
                    <span class="admin-alert-icon">
                        <i class="bi bi-envelope-exclamation-fill"></i>
                    </span>

                    <div class="admin-alert-content">
                        <h2>
                            <?= $pending_inquiries ?>
                            new contact message<?= $pending_inquiries === 1 ? '' : 's' ?>
                        </h2>

                        <p>
                            Check your Contact Inquiries page for messages
                            that need a response.
                        </p>
                    </div>

                    <a href="manage-inquiries.php?status=new"
                       class="admin-alert-button">
                        Open Inquiries
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </section>
            <?php endif; ?>

            <!-- Review alert -->
            <?php if ($pending_reviews > 0): ?>
                <section class="admin-alert admin-alert-review">
                    <span class="admin-alert-icon">
                        <i class="bi bi-bell-fill"></i>
                    </span>

                    <div class="admin-alert-content">
                        <h2>
                            <?= $pending_reviews ?>
                            review<?= $pending_reviews === 1 ? '' : 's' ?>
                            need attention
                        </h2>

                        <p>
                            Review pending submissions in the review
                            management section.
                        </p>
                    </div>

                    <a href="manage-reviews.php"
                       class="admin-alert-button">
                        Review Now
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </section>
            <?php endif; ?>

            <!-- Vacancy status panel -->
            <section class="admin-panel admin-vacancy-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-section-kicker">
                            PLATFORM ACTIVITY
                        </span>

                        <h2>Vacancy Overview</h2>

                        <p>
                            Current status of job postings on TechTalent.
                        </p>
                    </div>

                    <span class="admin-panel-heading-icon">
                        <i class="bi bi-bar-chart-line-fill"></i>
                    </span>
                </div>

                <div class="admin-panel-body">
                    <?php
                    $max_vac = max(1, $total_vacancies);

                    $vacancy_rows = [
                        [
                            'label' => 'Published',
                            'value' => $active_vacancies,
                            'class' => 'published'
                        ],
                        [
                            'label' => 'Pending',
                            'value' => $pending_vacancies,
                            'class' => 'pending'
                        ],
                        [
                            'label' => 'Draft',
                            'value' => $draft_vacancies,
                            'class' => 'draft'
                        ],
                        [
                            'label' => 'Closed',
                            'value' => $closed_vacancies,
                            'class' => 'closed'
                        ]
                    ];
                    ?>

                    <div class="admin-bars">
                        <?php foreach ($vacancy_rows as $row): ?>
                            <?php
                            $percentage = min(
                                100,
                                ($row['value'] / $max_vac) * 100
                            );
                            ?>

                            <div class="admin-bar-row">
                                <div class="admin-bar-label">
                                    <span class="admin-status-dot <?= dashboard_escape($row['class']) ?>"></span>
                                    <?= dashboard_escape($row['label']) ?>
                                </div>

                                <div
                                    class="admin-bar-track"
                                    role="progressbar"
                                    aria-label="<?= dashboard_escape($row['label']) ?> vacancies"
                                    aria-valuemin="0"
                                    aria-valuemax="<?= $max_vac ?>"
                                    aria-valuenow="<?= $row['value'] ?>"
                                >
                                    <div
                                        class="admin-bar-fill <?= dashboard_escape($row['class']) ?>"
                                        style="width: <?= $percentage ?>%;"
                                    ></div>
                                </div>

                                <div class="admin-bar-number">
                                    <?= $row['value'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-vacancy-footer">
                        <span>
                            <i class="bi bi-briefcase-fill"></i>
                            Total job postings
                        </span>

                        <strong><?= $total_vacancies ?></strong>
                    </div>
                </div>
            </section>

            <!-- Quick management -->
            <section class="admin-section">
                <div class="admin-section-heading">
                    <div>
                        <span class="admin-section-kicker">
                            QUICK ACCESS
                        </span>

                        <h2>Quick Management</h2>

                        <p>Open commonly used administrator tools.</p>
                    </div>
                </div>

                <div class="admin-actions">

                    <a class="admin-action" href="manage-jobs.php">
                        <span class="admin-action-icon blue">
                            <i class="bi bi-pencil-square"></i>
                        </span>

                        <strong>Manage Jobs</strong>
                        <span>Edit and manage vacancies</span>

                        <i class="bi bi-arrow-up-right admin-action-arrow"></i>
                    </a>

                    <a class="admin-action" href="../guest/jobs.php">
                        <span class="admin-action-icon green">
                            <i class="bi bi-search"></i>
                        </span>

                        <strong>View Vacancies</strong>
                        <span><?= $total_vacancies ?> total job postings</span>

                        <i class="bi bi-arrow-up-right admin-action-arrow"></i>
                    </a>

                    <a class="admin-action" href="manage-reviews.php">
                        <span class="admin-action-icon purple">
                            <i class="bi bi-chat-square-heart"></i>
                        </span>

                        <strong>Manage Reviews</strong>
                        <span><?= $pending_reviews ?> pending reviews</span>

                        <i class="bi bi-arrow-up-right admin-action-arrow"></i>
                    </a>

                    <a class="admin-action" href="manage-inquiries.php">
                        <span class="admin-action-icon orange">
                            <i class="bi bi-envelope-paper-fill"></i>
                        </span>

                        <strong>Contact Inquiries</strong>
                        <span><?= $pending_inquiries ?> new messages</span>

                        <i class="bi bi-arrow-up-right admin-action-arrow"></i>
                    </a>

                </div>
            </section>

            <!-- Recent users -->
            <section class="admin-panel admin-recent-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-section-kicker">
                            USER ACTIVITY
                        </span>

                        <h2>Recent Registrations</h2>

                        <p>
                            The latest users who joined the platform.
                        </p>
                    </div>

                    <span class="admin-latest-badge">
                        Latest 6
                    </span>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Registered</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (empty($recent_users)): ?>
                            <tr>
                                <td colspan="4" class="admin-empty">
                                    <i class="bi bi-people"></i>
                                    <strong>No registrations found</strong>
                                    <span>New registrations will appear here.</span>
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($recent_users as $u): ?>
                                <?php
                                $role = (string)($u['role'] ?? '');
                                $full_name = (string)($u['full_name'] ?? 'User');
                                $email = (string)($u['email'] ?? '');

                                $role_label = $role === 'job_seeker'
                                    ? 'Job Seeker'
                                    : ucfirst(str_replace('_', ' ', $role));

                                $initial = strtoupper(
                                    function_exists('mb_substr')
                                        ? mb_substr(trim($full_name), 0, 1, 'UTF-8')
                                        : substr(trim($full_name), 0, 1)
                                );

                                if ($initial === '') {
                                    $initial = 'U';
                                }
                                ?>

                                <tr>
                                    <td>
                                        <div class="admin-user">
                                            <div class="admin-avatar">
                                                <?= dashboard_escape($initial) ?>
                                            </div>

                                            <div class="admin-user-details">
                                                <strong class="admin-user-name">
                                                    <?= dashboard_escape($full_name) ?>
                                                </strong>

                                                <span class="admin-user-email">
                                                    <?= dashboard_escape($email) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="admin-role <?= dashboard_escape($role) ?>">
                                            <?= dashboard_escape($role_label) ?>
                                        </span>
                                    </td>

                                    <td class="admin-registered-date">
                                        <?= dashboard_date($u['created_at'] ?? null) ?>
                                    </td>

                                    <td>
                                        <span class="admin-registered-status">
                                            <i class="bi bi-check-circle-fill"></i>
                                            Registered
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <footer class="admin-page-footer">
                <span>
                    <i class="bi bi-shield-check"></i>
                    TechTalent Administrator Dashboard
                </span>

                <span>
                    &copy; <?= date('Y') ?> TechTalent Solutions (Pvt) Ltd
                </span>
            </footer>

        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php ob_end_flush(); ?>
