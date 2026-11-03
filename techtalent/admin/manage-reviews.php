
<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --------------------------------------------------
// 1. ADMIN AUTHENTICATION
// --------------------------------------------------
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit();
}

// --------------------------------------------------
// 2. CSRF PROTECTION
// --------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

function reviewEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// --------------------------------------------------
// 3. DATABASE CONNECTION
// --------------------------------------------------
require_once __DIR__ . '/../config/db.php';

// --------------------------------------------------
// 4. PROCESS REVIEW ACTIONS
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrf_token, $postedToken)
    ) {
        http_response_code(403);
        exit('Security validation failed. Please refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $reviewId = filter_var(
        $_POST['review_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$reviewId || $reviewId < 1) {
        $_SESSION['review_flash'] = [
            'type' => 'danger',
            'message' => 'Invalid review selected.'
        ];

        header('Location: manage-reviews.php');
        exit();
    }

    try {
        if ($action === 'approve' || $action === 'reject') {
            $newStatus = $action === 'approve'
                ? 'approved'
                : 'rejected';

            // Only pending reviews can be approved or rejected.
            $stmt = $pdo->prepare(
                "UPDATE reviews
                 SET status = :status
                 WHERE review_id = :id
                 AND status = 'pending'"
            );

            $stmt->execute([
                ':status' => $newStatus,
                ':id' => $reviewId
            ]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['review_flash'] = [
                    'type' => 'success',
                    'message' => $action === 'approve'
                        ? 'Review approved successfully.'
                        : 'Review rejected successfully.'
                ];
            } else {
                $_SESSION['review_flash'] = [
                    'type' => 'warning',
                    'message' => 'The review was not pending or no longer exists.'
                ];
            }

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare(
                "DELETE FROM reviews WHERE review_id = :id"
            );

            $stmt->execute([':id' => $reviewId]);

            $_SESSION['review_flash'] = [
                'type' => $stmt->rowCount() > 0 ? 'success' : 'warning',
                'message' => $stmt->rowCount() > 0
                    ? 'Review deleted successfully.'
                    : 'Review not found.'
            ];

        } else {
            $_SESSION['review_flash'] = [
                'type' => 'danger',
                'message' => 'Invalid action.'
            ];
        }

    } catch (PDOException $e) {
        error_log('Review action failed: ' . $e->getMessage());

        $_SESSION['review_flash'] = [
            'type' => 'danger',
            'message' => 'The action could not be completed. Please try again.'
        ];
    }

    header('Location: manage-reviews.php');
    exit();
}

// --------------------------------------------------
// 5. FETCH REVIEWS
// --------------------------------------------------
$reviews = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        "SELECT
            r.review_id,
            r.rating,
            r.review_text,
            r.status,
            r.created_at,
            u.full_name,
            u.email
         FROM reviews r
         JOIN users u ON r.user_id = u.user_id
         ORDER BY
            CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
            r.created_at DESC"
    );

    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Failed to fetch reviews: ' . $e->getMessage());
    $loadError = true;
}

// --------------------------------------------------
// 6. CALCULATE STATISTICS
// --------------------------------------------------
$totalReviews = count($reviews);
$pendingReviews = 0;
$approvedReviews = 0;
$rejectedReviews = 0;
$totalRating = 0;

foreach ($reviews as $review) {
    $status = strtolower((string)$review['status']);

    if ($status === 'pending') {
        $pendingReviews++;
    } elseif ($status === 'approved') {
        $approvedReviews++;
    } elseif ($status === 'rejected') {
        $rejectedReviews++;
    }

    $totalRating += max(0, min(5, (int)$review['rating']));
}

$averageRating = $totalReviews > 0
    ? number_format($totalRating / $totalReviews, 1)
    : '0.0';

// --------------------------------------------------
// 7. PAGE HEADER
// --------------------------------------------------
$page_title = 'Manage Reviews - TechTalent Admin';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$flash = $_SESSION['review_flash'] ?? null;
unset($_SESSION['review_flash']);
?>

<style>
/* ==========================================
   TECHTALENT REVIEW MANAGEMENT
   All styles are scoped to this page.
========================================== */

.review-admin {
    --review-primary: #3157d5;
    --review-primary-dark: #2343b4;
    --review-text: #182338;
    --review-muted: #728096;
    --review-border: #e6ebf2;
    --review-bg: #f5f7fb;

    color: var(--review-text);
    padding-top: 30px;
    padding-bottom: 55px;
}

.review-admin .review-hero {
    position: relative;
    overflow: hidden;
    padding: 30px;
    border-radius: 22px;
    background: linear-gradient(120deg, #172b62 0%, #3157d5 62%, #637ef0 100%);
    color: #fff;
    box-shadow: 0 12px 35px rgba(39, 72, 160, .16);
    margin-bottom: 26px;
}

.review-admin .review-hero::after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    border: 38px solid rgba(255,255,255,.08);
    border-radius: 50%;
    right: -55px;
    top: -95px;
    pointer-events: none;
}

.review-admin .hero-content {
    position: relative;
    z-index: 1;
}

.review-admin .hero-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.16);
    padding: 7px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    margin-bottom: 14px;
}

.review-admin .review-hero h1 {
    font-size: clamp(26px, 3vw, 36px);
    font-weight: 800;
    letter-spacing: -.8px;
    margin: 0 0 10px;
}

.review-admin .review-hero p {
    color: rgba(255,255,255,.82);
    margin: 0;
    max-width: 650px;
    line-height: 1.7;
}

.review-admin .hero-date {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 10px 13px;
    border-radius: 12px;
    background: rgba(255,255,255,.12);
    color: #fff;
    font-size: 13px;
    white-space: nowrap;
}

.review-admin .review-stat {
    height: 100%;
    padding: 22px;
    background: #fff;
    border: 1px solid var(--review-border);
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(23, 39, 75, .035);
    transition: transform .2s ease, box-shadow .2s ease;
}

.review-admin .review-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(23, 39, 75, .08);
}

.review-admin .stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 18px;
}

.review-admin .stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.review-admin .icon-blue {
    color: #3157d5;
    background: #edf1ff;
}

.review-admin .icon-orange {
    color: #b96a08;
    background: #fff3df;
}

.review-admin .icon-green {
    color: #148052;
    background: #e5f8ef;
}

.review-admin .icon-purple {
    color: #7851c8;
    background: #f1eaff;
}

.review-admin .stat-label {
    color: var(--review-muted);
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 5px;
}

.review-admin .stat-value {
    font-size: 30px;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -.7px;
}

.review-admin .stat-note {
    font-size: 12px;
    color: var(--review-muted);
    margin-top: 7px;
}

.review-admin .review-panel {
    background: #fff;
    border: 1px solid var(--review-border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 22px rgba(23, 39, 75, .035);
}

.review-admin .panel-heading {
    padding: 23px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    border-bottom: 1px solid var(--review-border);
}

.review-admin .panel-heading h2 {
    font-size: 19px;
    font-weight: 800;
    margin: 0 0 5px;
}

.review-admin .panel-heading p {
    color: var(--review-muted);
    font-size: 13px;
    margin: 0;
}

.review-admin .review-count {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border-radius: 30px;
    padding: 8px 12px;
    background: #edf1ff;
    color: #3157d5;
    font-size: 12px;
    font-weight: 700;
}

.review-admin .review-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.review-admin .review-table {
    width: 100%;
    min-width: 900px;
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.review-admin .review-table thead th {
    background: #f8faff;
    color: #68758a;
    padding: 15px 18px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .65px;
    font-weight: 800;
    border-bottom: 1px solid var(--review-border);
    white-space: nowrap;
}

.review-admin .review-table tbody td,
.review-admin .review-table tbody th {
    padding: 18px;
    border-bottom: 1px solid #edf0f5;
    vertical-align: middle;
}

.review-admin .review-table tbody tr:last-child td,
.review-admin .review-table tbody tr:last-child th {
    border-bottom: 0;
}

.review-admin .review-table tbody tr {
    transition: background .15s ease;
}

.review-admin .review-table tbody tr:hover {
    background: #fafbff;
}

.review-admin .review-number {
    color: #8893a5;
    font-size: 13px;
    font-weight: 700;
}

.review-admin .candidate-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 190px;
}

.review-admin .candidate-avatar {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: linear-gradient(135deg, #e9eeff, #dce5ff);
    color: #3157d5;
    font-size: 15px;
    font-weight: 800;
    text-transform: uppercase;
}

.review-admin .candidate-name {
    font-weight: 750;
    font-size: 13px;
    color: #202d43;
    overflow-wrap: anywhere;
}

.review-admin .candidate-email {
    font-size: 12px;
    color: #7a879a;
    margin-top: 4px;
    overflow-wrap: anywhere;
}

.review-admin .rating-stars {
    white-space: nowrap;
    color: #f4ad27;
    font-size: 14px;
    letter-spacing: 1px;
}

.review-admin .rating-number {
    display: block;
    color: #6d7a8e;
    font-size: 11px;
    margin-top: 5px;
}

.review-admin .review-message {
    max-width: 280px;
    min-width: 180px;
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.65;
    color: #566378;
    font-size: 13px;
}

.review-admin .review-date {
    color: #69768a;
    font-size: 12px;
    white-space: nowrap;
}

.review-admin .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 30px;
    padding: 7px 10px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.review-admin .status-pending {
    color: #96600b;
    background: #fff3d8;
}

.review-admin .status-approved {
    color: #13764b;
    background: #e1f7eb;
}

.review-admin .status-rejected {
    color: #b42335;
    background: #ffeaed;
}

.review-admin .status-other {
    color: #5e6878;
    background: #edf0f4;
}

.review-admin .action-buttons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.review-admin .action-btn {
    width: 34px;
    height: 34px;
    padding: 0;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    transition: all .18s ease;
}

.review-admin .action-approve {
    color: #13824e;
    background: #e5f8ee;
}

.review-admin .action-approve:hover {
    color: #fff;
    background: #13824e;
}

.review-admin .action-reject {
    color: #bd3342;
    background: #ffebee;
}

.review-admin .action-reject:hover {
    color: #fff;
    background: #bd3342;
}

.review-admin .action-delete {
    color: #677386;
    background: #f0f2f6;
}

.review-admin .action-delete:hover {
    color: #fff;
    background: #536174;
}

.review-admin .empty-state {
    text-align: center;
    padding: 65px 20px;
}

.review-admin .empty-icon {
    width: 68px;
    height: 68px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 22px;
    background: #edf1ff;
    color: #3157d5;
    font-size: 28px;
    margin-bottom: 18px;
}

.review-admin .empty-state h3 {
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 8px;
}

.review-admin .empty-state p {
    color: var(--review-muted);
    font-size: 13px;
    margin: 0;
}

.review-admin .review-alert {
    border-radius: 12px;
    border: 0;
    padding: 14px 17px;
    margin-bottom: 20px;
    font-size: 13px;
}

.review-admin .review-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding: 15px 22px;
    border-top: 1px solid var(--review-border);
    color: var(--review-muted);
    font-size: 12px;
}

.review-admin .review-footer strong {
    color: #344158;
}

@media (max-width: 767px) {
    .review-admin {
        padding-top: 18px;
    }

    .review-admin .review-hero {
        padding: 23px 20px;
        border-radius: 17px;
    }

    .review-admin .review-hero h1 {
        font-size: 27px;
    }

    .review-admin .hero-date {
        margin-top: 17px;
    }

    .review-admin .review-stat {
        padding: 18px;
    }

    .review-admin .stat-value {
        font-size: 26px;
    }

    .review-admin .panel-heading {
        padding: 20px;
    }
}
</style>

<div class="container review-admin">

    <!-- PAGE HEADER -->
    <section class="review-hero">
        <div class="hero-content">
            <div class="row align-items-center">
                <div class="col-lg-9">
                    <div class="hero-label">
                        <i class="bi bi-shield-check"></i>
                        Admin Management
                    </div>

                    <h1>Candidate Reviews</h1>

                    <p>
                        Review candidate feedback, approve trusted reviews,
                        and keep the public TechTalent community helpful and professional.
                    </p>
                </div>

                <div class="col-lg-3 text-lg-end">
                    <div class="hero-date">
                        <i class="bi bi-calendar3"></i>
                        <?= date('d M Y') ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FLASH MESSAGE -->
    <?php if ($flash && is_array($flash)): ?>
        <?php
        $flashTypes = ['success', 'danger', 'warning', 'info'];
        $flashType = in_array($flash['type'] ?? '', $flashTypes, true)
            ? $flash['type']
            : 'info';
        ?>
        <div class="alert alert-<?= reviewEscape($flashType) ?> review-alert alert-dismissible fade show"
             role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <?= reviewEscape($flash['message'] ?? '') ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($loadError): ?>
        <div class="alert alert-danger review-alert" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Reviews could not be loaded. Please check the database connection and review table.
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-xl-3">
            <div class="review-stat">
                <div class="stat-top">
                    <div class="stat-icon icon-blue">
                        <i class="bi bi-chat-square-text"></i>
                    </div>
                    <i class="bi bi-graph-up-arrow text-primary"></i>
                </div>
                <div class="stat-label">Total Reviews</div>
                <div class="stat-value"><?= $totalReviews ?></div>
                <div class="stat-note">All submitted reviews</div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="review-stat">
                <div class="stat-top">
                    <div class="stat-icon icon-orange">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <span class="status-pill status-pending">Pending</span>
                </div>
                <div class="stat-label">Awaiting Review</div>
                <div class="stat-value"><?= $pendingReviews ?></div>
                <div class="stat-note">Require admin attention</div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="review-stat">
                <div class="stat-top">
                    <div class="stat-icon icon-green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <span class="status-pill status-approved">Approved</span>
                </div>
                <div class="stat-label">Approved Reviews</div>
                <div class="stat-value"><?= $approvedReviews ?></div>
                <div class="stat-note">Approved for display</div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="review-stat">
                <div class="stat-top">
                    <div class="stat-icon icon-purple">
                        <i class="bi bi-star-half"></i>
                    </div>
                    <i class="bi bi-star-fill text-warning"></i>
                </div>
                <div class="stat-label">Average Rating</div>
                <div class="stat-value"><?= reviewEscape($averageRating) ?><span
                    style="font-size:16px;color:#7a879a;"> / 5</span></div>
                <div class="stat-note">Across all reviews</div>
            </div>
        </div>

    </div>

    <!-- REVIEWS TABLE -->
    <section class="review-panel">

        <div class="panel-heading">
            <div>
                <h2><i class="bi bi-list-check me-2 text-primary"></i>All Reviews</h2>
                <p>Manage, moderate, and review candidate feedback.</p>
            </div>

            <div class="review-count">
                <i class="bi bi-collection"></i>
                <?= $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if (empty($reviews)): ?>

            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-chat-square-heart"></i>
                </div>
                <h3>No reviews yet</h3>
                <p>Candidate reviews will appear here when they are submitted.</p>
            </div>

        <?php else: ?>

            <div class="review-table-wrap">
                <table class="review-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Candidate</th>
                            <th>Rating</th>
                            <th>Review Message</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($reviews as $idx => $rev): ?>
                        <?php
                        $rating = max(0, min(5, (int)$rev['rating']));
                        $status = strtolower((string)$rev['status']);

                        $statusClass = 'status-other';
                        $statusLabel = ucfirst($status);

                        if ($status === 'pending') {
                            $statusClass = 'status-pending';
                            $statusLabel = 'Pending';
                        } elseif ($status === 'approved') {
                            $statusClass = 'status-approved';
                            $statusLabel = 'Approved';
                        } elseif ($status === 'rejected') {
                            $statusClass = 'status-rejected';
                            $statusLabel = 'Rejected';
                        }

                        $candidateName = trim((string)($rev['full_name'] ?? ''));
                        $candidateInitial = $candidateName !== ''
                            ? function_exists('mb_substr')
                                ? mb_substr($candidateName, 0, 1, 'UTF-8')
                                : substr($candidateName, 0, 1)
                            : '?';

                        $createdTimestamp = !empty($rev['created_at'])
                            ? strtotime($rev['created_at'])
                            : false;
                        ?>

                        <tr>
                            <th scope="row" class="review-number">
                                <?= $idx + 1 ?>
                            </th>

                            <td>
                                <div class="candidate-cell">
                                    <div class="candidate-avatar">
                                        <?= reviewEscape($candidateInitial) ?>
                                    </div>

                                    <div>
                                        <div class="candidate-name">
                                            <?= reviewEscape($candidateName ?: 'Unknown Candidate') ?>
                                        </div>
                                        <div class="candidate-email">
                                            <?= reviewEscape($rev['email'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="rating-stars"
                                     aria-label="<?= $rating ?> out of 5 stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi <?= $i <= $rating
                                            ? 'bi-star-fill'
                                            : 'bi-star' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-number">
                                    <?= $rating ?>.0 / 5
                                </span>
                            </td>

                            <td>
                                <div class="review-message">
                                    <?= nl2br(reviewEscape($rev['review_text'] ?? '')) ?>
                                </div>
                            </td>

                            <td>
                                <div class="review-date">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= $createdTimestamp
                                        ? date('d M Y', $createdTimestamp)
                                        : 'Unknown date' ?>
                                </div>
                            </td>

                            <td>
                                <span class="status-pill <?= reviewEscape($statusClass) ?>">
                                    <i class="bi <?= $status === 'approved'
                                        ? 'bi-check-circle-fill'
                                        : ($status === 'rejected'
                                            ? 'bi-x-circle-fill'
                                            : ($status === 'pending'
                                                ? 'bi-clock-fill'
                                                : 'bi-info-circle-fill')) ?>"></i>
                                    <?= reviewEscape($statusLabel) ?>
                                </span>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <?php if ($status === 'pending'): ?>

                                        <form method="POST"
                                              action="manage-reviews.php"
                                              class="d-inline"
                                              onsubmit="return confirm('Approve this review?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= reviewEscape($csrf_token) ?>">
                                            <input type="hidden" name="review_id"
                                                   value="<?= (int)$rev['review_id'] ?>">
                                            <input type="hidden" name="action"
                                                   value="approve">

                                            <button type="submit"
                                                    class="action-btn action-approve"
                                                    title="Approve review"
                                                    aria-label="Approve review">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="manage-reviews.php"
                                              class="d-inline"
                                              onsubmit="return confirm('Reject this review?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= reviewEscape($csrf_token) ?>">
                                            <input type="hidden" name="review_id"
                                                   value="<?= (int)$rev['review_id'] ?>">
                                            <input type="hidden" name="action"
                                                   value="reject">

                                            <button type="submit"
                                                    class="action-btn action-reject"
                                                    title="Reject review"
                                                    aria-label="Reject review">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <form method="POST"
                                          action="manage-reviews.php"
                                          class="d-inline"
                                          onsubmit="return confirm('Delete this review permanently? This action cannot be undone.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= reviewEscape($csrf_token) ?>">
                                        <input type="hidden" name="review_id"
                                               value="<?= (int)$rev['review_id'] ?>">
                                        <input type="hidden" name="action"
                                               value="delete">

                                        <button type="submit"
                                                class="action-btn action-delete"
                                                title="Delete review"
                                                aria-label="Delete review">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="review-footer">
                <span>
                    Showing <strong><?= $totalReviews ?></strong>
                    review<?= $totalReviews === 1 ? '' : 's' ?>
                </span>
                <span>
                    <i class="bi bi-shield-check me-1"></i>
                    Review moderation is restricted to administrators.
                </span>
            </div>

        <?php endif; ?>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
