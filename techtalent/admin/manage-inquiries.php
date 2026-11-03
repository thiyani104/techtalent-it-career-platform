<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/contact-functions.php';

// Only authenticated administrators may view or reply to contact messages.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = 'Manage Contact Inquiries | TechTalent Admin';
$schema_error = '';
$perPage = 10;
$statusList = ['all', 'new', 'read', 'replied'];
$typeList = ['all', 'Job Seeker', 'Employer', 'General Inquiry'];

try {
    contact_ensure_schema($pdo);
} catch (PDOException $e) {
    error_log('Contact inquiry setup failed: ' . $e->getMessage());
    $schema_error = 'The contact inquiry tables could not be prepared. Please check the database permissions and schema.';
}

if (empty($_SESSION['inquiries_csrf_token'])) {
    $_SESSION['inquiries_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['inquiries_csrf_token'];

/* ---------- small helpers ---------- */
function inquiry_url(array $p = []): string
{
    $q = [];
    if (($p['status'] ?? 'all') !== 'all') $q['status'] = $p['status'];
    if (($p['type'] ?? 'all') !== 'all') $q['type'] = $p['type'];
    if (trim((string)($p['q'] ?? '')) !== '') $q['q'] = trim((string)$p['q']);
    if ((int)($p['page'] ?? 1) > 1) $q['page'] = (int)$p['page'];
    if (!empty($p['open'])) $q['open'] = (int)$p['open'];
    return 'manage-inquiries.php' . ($q ? '?' . http_build_query($q) : '');
}

function inquiry_redirect(array $ctx, ?int $open = null): void
{
    if ($open) $ctx['open'] = $open;
    header('Location: ' . inquiry_url($ctx) . ($open ? '#inquiry-' . $open : ''));
    exit();
}

function inquiry_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit();
}

/** Send a saved reply by e-mail and record the result. Returns true or an error string. */
function inquiry_deliver(PDO $pdo, int $replyId, array $contact, string $text, int $adminId)
{
    $result = sendContactReplyEmail($contact['email'], $contact['name'], $contact['subject'] ?: 'Contact inquiry', $text, $contact['message']);
    if ($result === true) {
        $pdo->prepare("UPDATE contact_replies SET email_status = 'sent', email_error = NULL, sent_at = NOW() WHERE reply_id = :id")
            ->execute([':id' => $replyId]);
        $pdo->prepare("UPDATE contact_messages SET status = 'replied', reply_message = :t, replied_at = NOW(), replied_by = :a,
                       read_at = COALESCE(read_at, NOW()) WHERE message_id = :m")
            ->execute([':t' => $text, ':a' => $adminId, ':m' => (int)$contact['message_id']]);
    } else {
        $pdo->prepare("UPDATE contact_replies SET email_status = 'failed', email_error = :e WHERE reply_id = :id")
            ->execute([':e' => mb_substr((string)$result, 0, 500), ':id' => $replyId]);
        // The reply is saved, but the sender has not received it: keep the message out of "replied".
        $pdo->prepare("UPDATE contact_messages SET status = IF(status = 'new', 'read', status), read_at = COALESCE(read_at, NOW()) WHERE message_id = :m")
            ->execute([':m' => (int)$contact['message_id']]);
    }
    return $result;
}

/* ---------- current list context (filters) ---------- */
$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$reqStatus = (string)($src['status'] ?? 'all');
$reqType   = (string)($src['type'] ?? 'all');
$ctx = [
    'status' => in_array($reqStatus, $statusList, true) ? $reqStatus : 'all',
    'type'   => in_array($reqType, $typeList, true) ? $reqType : 'all',
    'q'      => mb_substr(trim((string)($src['q'] ?? '')), 0, 100),
    'page'   => max(1, (int)($src['page'] ?? 1)),
];

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    $action = (string)($_POST['action'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT) ?: 0;
    $adminId = (int)$_SESSION['user_id'];

    if ($schema_error !== '') {
        if ($isAjax) inquiry_json(['ok' => false], 500);
        contact_flash_set('danger', $schema_error);
        inquiry_redirect($ctx);
    }
    if (!is_string($token) || !hash_equals($csrf, $token)) {
        if ($isAjax) inquiry_json(['ok' => false, 'error' => 'csrf'], 403);
        contact_flash_set('danger', 'Your session expired. Refresh the page and try again.');
        inquiry_redirect($ctx);
    }

    try {
        switch ($action) {
            case 'mark_read':
                $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = NOW() WHERE message_id = :id AND status = 'new'")
                    ->execute([':id' => $messageId]);
                if ($isAjax) {
                    $newCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
                    inquiry_json(['ok' => true, 'new_count' => $newCount]);
                }
                contact_flash_set('success', 'Message marked as read.');
                inquiry_redirect($ctx, $messageId);

            case 'mark_unread':
                $pdo->prepare("UPDATE contact_messages SET status = 'new', read_at = NULL WHERE message_id = :id AND status = 'read'")
                    ->execute([':id' => $messageId]);
                contact_flash_set('success', 'Message marked as unread.');
                inquiry_redirect($ctx, $messageId);

            case 'save_note':
                $note = trim((string)($_POST['admin_note'] ?? ''));
                if (mb_strlen($note) > 2000) {
                    contact_flash_set('danger', 'The private note is too long (2,000 characters maximum).');
                } else {
                    $pdo->prepare('UPDATE contact_messages SET admin_note = :n WHERE message_id = :id')
                        ->execute([':n' => $note !== '' ? $note : null, ':id' => $messageId]);
                    contact_flash_set('success', 'Private note saved.');
                }
                inquiry_redirect($ctx, $messageId);

            case 'delete':
                $pdo->prepare('DELETE FROM contact_messages WHERE message_id = :id')->execute([':id' => $messageId]);
                contact_flash_set('success', 'Message deleted permanently.');
                inquiry_redirect($ctx);

            case 'reply':
                $reply = trim((string)($_POST['reply_message'] ?? ''));
                if (!$messageId || $reply === '') {
                    contact_flash_set('danger', 'Please enter a reply before sending.');
                    inquiry_redirect($ctx, $messageId ?: null);
                }
                if (mb_strlen($reply) > 10000) {
                    contact_flash_set('danger', 'Your reply is too long. Please keep it under 10,000 characters.');
                    inquiry_redirect($ctx, $messageId);
                }
                $find = $pdo->prepare('SELECT message_id, name, email, subject, message FROM contact_messages WHERE message_id = :id LIMIT 1');
                $find->execute([':id' => $messageId]);
                $contact = $find->fetch();
                if (!$contact) {
                    contact_flash_set('danger', 'That inquiry could not be found. Refresh the page and try again.');
                    inquiry_redirect($ctx);
                }
                // Save first so the admin's work is never lost, even if SMTP is down.
                $pdo->prepare('INSERT INTO contact_replies (message_id, admin_id, reply_text) VALUES (:m, :a, :t)')
                    ->execute([':m' => $messageId, ':a' => $adminId, ':t' => $reply]);
                $replyId = (int)$pdo->lastInsertId();
                $result = inquiry_deliver($pdo, $replyId, $contact, $reply, $adminId);
                if ($result === true) {
                    contact_flash_set('success', 'Reply saved and emailed to ' . $contact['email'] . '.');
                } else {
                    contact_flash_set('warning', 'Reply saved, but the email was NOT sent. ' . $result . ' You can resend it from the reply history.');
                }
                inquiry_redirect($ctx, $messageId);

            case 'resend':
                $replyId = filter_input(INPUT_POST, 'reply_id', FILTER_VALIDATE_INT) ?: 0;
                $find = $pdo->prepare("SELECT r.reply_id, r.reply_text, m.message_id, m.name, m.email, m.subject, m.message
                                       FROM contact_replies r JOIN contact_messages m ON m.message_id = r.message_id
                                       WHERE r.reply_id = :id AND r.email_status = 'failed' LIMIT 1");
                $find->execute([':id' => $replyId]);
                $row = $find->fetch();
                if (!$row) {
                    contact_flash_set('danger', 'That reply was not found or has already been sent.');
                    inquiry_redirect($ctx);
                }
                $result = inquiry_deliver($pdo, $replyId, $row, $row['reply_text'], $adminId);
                if ($result === true) {
                    contact_flash_set('success', 'Email delivered to ' . $row['email'] . '.');
                } else {
                    contact_flash_set('warning', 'The email could still not be sent. ' . $result);
                }
                inquiry_redirect($ctx, (int)$row['message_id']);

            default:
                contact_flash_set('danger', 'Unknown action.');
                inquiry_redirect($ctx);
        }
    } catch (PDOException $e) {
        error_log('Inquiry action failed (' . $action . '): ' . $e->getMessage());
        if ($isAjax) inquiry_json(['ok' => false], 500);
        contact_flash_set('danger', 'The action could not be completed. Please try again.');
        inquiry_redirect($ctx, $messageId ?: null);
    }
}

/* ---------- load data for the page ---------- */
$flash = contact_flash_get();
$error_message = $schema_error;
$openId = (int)($_GET['open'] ?? 0);
$counts = ['all' => 0, 'new' => 0, 'read' => 0, 'replied' => 0];
$messages = [];
$repliesByMessage = [];
$totalMatches = 0;
$totalPages = 1;

if ($schema_error === '') {
    try {
        foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM contact_messages GROUP BY status')->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['total'];
            $counts['all'] += (int)$row['total'];
        }

        $where = [];
        $params = [];
        if ($ctx['status'] !== 'all') { $where[] = 'status = :status'; $params[':status'] = $ctx['status']; }
        if ($ctx['type'] !== 'all')   { $where[] = 'sender_type = :type'; $params[':type'] = $ctx['type']; }
        if ($ctx['q'] !== '') {
            $where[] = '(name LIKE :q1 OR email LIKE :q2 OR subject LIKE :q3 OR message LIKE :q4)';
            $like = contact_like($ctx['q']);
            $params += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages' . $whereSql);
        $cnt->execute($params);
        $totalMatches = (int)$cnt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalMatches / $perPage));
        $ctx['page'] = min($ctx['page'], $totalPages);
        $offset = ($ctx['page'] - 1) * $perPage;

        $stmt = $pdo->prepare('SELECT * FROM contact_messages' . $whereSql
            . ' ORDER BY (status = \'new\') DESC, created_at DESC, message_id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        if ($messages) {
            $ids = array_map('intval', array_column($messages, 'message_id'));
            $in = implode(',', $ids); // integers only
            $rs = $pdo->query("SELECT r.*, u.full_name AS admin_name FROM contact_replies r
                               LEFT JOIN users u ON u.user_id = r.admin_id
                               WHERE r.message_id IN ($in) ORDER BY r.created_at ASC, r.reply_id ASC");
            foreach ($rs->fetchAll() as $r) {
                $repliesByMessage[(int)$r['message_id']][] = $r;
            }
        }
    } catch (PDOException $e) {
        error_log('Loading contact inquiries failed: ' . $e->getMessage());
        $error_message = 'Could not load contact inquiries. Please check the contact_messages database tables.';
    }
}

function inquiry_h($value): string { return contact_h($value); }
function inquiry_hidden(array $ctx, string $csrf): string
{
    $o = '<input type="hidden" name="csrf_token" value="' . contact_h($csrf) . '">';
    foreach (['status', 'type', 'q', 'page'] as $k) {
        $o .= '<input type="hidden" name="' . $k . '" value="' . contact_h($ctx[$k]) . '">';
    }
    return $o;
}
$smtpReady = techtalent_mail_is_configured();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/admin-dashboard.css">
<style>
.inquiries-page{padding:28px;max-width:1500px;margin:0 auto;color:#17243a}
.inquiries-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px;flex-wrap:wrap}
.inquiries-heading h1{font-size:clamp(1.7rem,3vw,2.25rem);font-weight:800;margin:0 0 7px;color:#10213a}
.inquiries-heading p{margin:0;color:#718096}
.inquiry-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}
.inquiry-stat{background:#fff;border:1px solid #e7edf5;border-radius:16px;padding:18px 20px;box-shadow:0 8px 22px rgba(15,33,55,.04)}
.inquiry-stat span{display:block;color:#718096;font-size:.82rem;font-weight:600}.inquiry-stat strong{display:block;font-size:1.8rem;color:#10213a;margin-top:5px}
.inquiry-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:20px}
.inquiry-filters{display:flex;gap:8px;flex-wrap:wrap}.inquiry-filters a{padding:8px 14px;border:1px solid #dbe4ef;border-radius:999px;color:#44546a;text-decoration:none;font-weight:700;font-size:.88rem;background:#fff}.inquiry-filters a.active{background:#0d6efd;color:#fff;border-color:#0d6efd}
.inquiry-search{display:flex;gap:8px;flex-wrap:wrap}.inquiry-search input,.inquiry-search select{border:1px solid #d6e0eb;border-radius:10px;padding:8px 12px;font-size:.9rem;background:#fff}
.inquiry-card{background:#fff;border:1px solid #e5ebf3;border-radius:18px;margin-bottom:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,33,55,.045)}
.inquiry-card.is-new{border-left:5px solid #0d6efd}
.inquiry-card-head{width:100%;text-align:left;border:0;padding:18px 22px;background:#f8fbff;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;cursor:pointer}
.inquiry-card-head:hover{background:#f1f7ff}
.inquiry-subject{font-size:1.05rem;font-weight:700;color:#15253c;margin:0 0 6px;overflow-wrap:anywhere}.inquiry-card.is-new .inquiry-subject{font-weight:800}
.inquiry-meta{font-size:.84rem;color:#66758a;line-height:1.7;overflow-wrap:anywhere}.inquiry-meta strong{color:#334155}
.inquiry-snippet{font-size:.85rem;color:#8795a8;margin-top:4px;overflow-wrap:anywhere}
.inquiry-badge{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;font-weight:800;border-radius:999px;padding:6px 10px;white-space:nowrap}.inquiry-badge.new{background:#fff0d5;color:#8a5200}.inquiry-badge.read{background:#e6f1ff;color:#1556a5}.inquiry-badge.replied{background:#dcfce7;color:#166534}
.inquiry-body{padding:22px;display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.9fr);gap:24px;border-top:1px solid #e8eef6}
.inquiry-label{font-size:.76rem;text-transform:uppercase;letter-spacing:.08em;color:#7b8798;font-weight:800;margin-bottom:8px}
.inquiry-message{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.75;color:#344256;background:#f8fafc;border:1px solid #e9eef5;border-radius:12px;padding:16px;min-height:90px}
.inquiry-reply-form textarea,.inquiry-note-form textarea{width:100%;border:1px solid #d6e0eb;border-radius:12px;padding:12px 14px;resize:vertical}
.inquiry-reply-form textarea{min-height:150px}.inquiry-note-form textarea{min-height:70px;background:#fffdf5}
.inquiry-reply-form textarea:focus,.inquiry-note-form textarea:focus{outline:none;border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.1)}
.reply-thread{margin-top:16px}.reply-item{border-left:3px solid #16a34a;background:#f0fdf4;padding:12px 14px;border-radius:0 10px 10px 0;margin-bottom:10px}
.reply-item.failed{border-left-color:#dc2626;background:#fef2f2}
.reply-item .reply-text{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.65;color:#1f3b2b;margin-top:6px}.reply-item.failed .reply-text{color:#5b2323}
.reply-item .reply-meta{font-size:.78rem;color:#64748b}
.inquiry-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px dashed #e2e8f0}
.inquiry-alert{border-radius:12px;padding:13px 16px;margin-bottom:18px}.inquiry-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:18px;padding:45px 20px;text-align:center;color:#64748b}
.inquiry-pager{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin:24px 0 8px}.inquiry-pager a,.inquiry-pager span{padding:7px 13px;border-radius:10px;border:1px solid #dbe4ef;background:#fff;color:#44546a;text-decoration:none;font-weight:700;font-size:.88rem}.inquiry-pager .current{background:#0d6efd;border-color:#0d6efd;color:#fff}.inquiry-pager .disabled{opacity:.45}
@media(max-width:850px){.inquiry-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.inquiry-body{grid-template-columns:1fr}.inquiries-page{padding:20px 14px}}
@media(max-width:480px){.inquiry-stat{padding:14px}.inquiry-stat strong{font-size:1.4rem}.inquiry-card-head,.inquiry-body{padding:16px}}
</style>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="admin-dashboard">
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <div class="admin-brand"><strong>TechTalent Admin</strong><small>Platform Control Center</small></div>
      <div class="admin-sidebar-label">Main Menu</div>
      <nav class="admin-nav">
        <a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
        <a href="manage-inquiries.php" class="active"><i class="bi bi-envelope-paper-fill"></i><span>Contact Inquiries <span class="badge rounded-pill bg-danger ms-1 js-new-badge" <?= $counts['new'] > 0 ? '' : 'style="display:none"' ?>><?= $counts['new'] ?></span></span></a>
        <a href="../guest/jobs.php"><i class="bi bi-briefcase-fill"></i><span>Vacancies</span></a>
        <a href="../employer/manage-jobs.php"><i class="bi bi-list-check"></i><span>Manage Jobs</span></a>
        <a href="../employer/applicants.php"><i class="bi bi-file-earmark-person"></i><span>Applications</span></a>
      </nav>
      <div class="admin-sidebar-label mt-3">Management</div>
      <nav class="admin-nav">
        <a href="manage-categories.php"><i class="bi bi-folder2-open"></i><span>Categories</span></a>
        <a href="manage-reviews.php"><i class="bi bi-star-fill"></i><span>Reviews</span></a>
        <a href="../jobseeker/skills.php"><i class="bi bi-tools"></i><span>Skills</span></a>
      </nav>
      <div class="admin-sidebar-label mt-3">Account</div>
      <nav class="admin-nav"><a href="../guest/index.php"><i class="bi bi-house"></i><span>View Website</span></a><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></nav>
      <div class="admin-sidebar-footer"><i class="bi bi-shield-check me-1"></i> Administrator access enabled</div>
    </aside>
    <main class="admin-main">
      <div class="inquiries-page">
        <div class="inquiries-heading"><div><h1><i class="bi bi-envelope-paper me-2 text-primary"></i>Contact Inquiries</h1><p>Review messages from job seekers, employers and other visitors, then reply to them.</p></div><a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i> Dashboard</a></div>

        <?php if (!$smtpReady): ?><div class="alert alert-warning inquiry-alert"><i class="bi bi-envelope-exclamation me-2"></i><strong>Email sending is not set up yet.</strong> Replies will be saved here but not emailed. Add your Gmail App Password in <code>config/mail_settings.php</code>, then use "Resend email" on any saved reply.</div><?php endif; ?>
        <?php if ($flash): ?><div class="alert alert-<?= inquiry_h($flash['type']) ?> inquiry-alert"><?= inquiry_h($flash['message']) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger inquiry-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= inquiry_h($error_message) ?></div><?php endif; ?>

        <section class="inquiry-stats">
          <div class="inquiry-stat"><span>Total messages</span><strong><?= $counts['all'] ?></strong></div>
          <div class="inquiry-stat"><span>New / unread</span><strong class="js-new-count"><?= $counts['new'] ?></strong></div>
          <div class="inquiry-stat"><span>Read, awaiting reply</span><strong><?= $counts['read'] ?></strong></div>
          <div class="inquiry-stat"><span>Replied</span><strong><?= $counts['replied'] ?></strong></div>
        </section>

        <div class="inquiry-toolbar">
          <nav class="inquiry-filters" aria-label="Filter inquiries">
            <?php foreach (['all' => 'All messages', 'new' => 'New', 'read' => 'Read', 'replied' => 'Replied'] as $key => $label): ?>
              <a class="<?= $ctx['status'] === $key ? 'active' : '' ?>" href="<?= inquiry_h(inquiry_url(['status' => $key, 'type' => $ctx['type'], 'q' => $ctx['q']])) ?>"><?= inquiry_h($label) ?> (<?= $counts[$key] ?>)</a>
            <?php endforeach; ?>
          </nav>
          <form class="inquiry-search" method="get" action="manage-inquiries.php">
            <input type="hidden" name="status" value="<?= inquiry_h($ctx['status']) ?>">
            <select name="type" aria-label="Sender type">
              <?php foreach ($typeList as $t): ?><option value="<?= inquiry_h($t) ?>" <?= $ctx['type'] === $t ? 'selected' : '' ?>><?= $t === 'all' ? 'All sender types' : inquiry_h($t) ?></option><?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= inquiry_h($ctx['q']) ?>" placeholder="Search name, email, subject, message" maxlength="100" style="min-width:230px">
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            <?php if ($ctx['q'] !== '' || $ctx['type'] !== 'all'): ?><a class="btn btn-outline-secondary btn-sm" href="<?= inquiry_h(inquiry_url(['status' => $ctx['status']])) ?>">Clear</a><?php endif; ?>
          </form>
        </div>

        <?php if (!$messages): ?>
          <div class="inquiry-empty"><i class="bi bi-inbox display-5 d-block mb-3"></i><h3 class="h5 fw-bold">No inquiries found</h3><p class="mb-0"><?= ($ctx['q'] !== '' || $ctx['type'] !== 'all' || $ctx['status'] !== 'all') ? 'Try changing or clearing your filters.' : 'Messages submitted through the Contact Us page will appear here.' ?></p></div>
        <?php else: ?>
          <p class="text-muted small mb-3">Showing <?= count($messages) ?> of <?= $totalMatches ?> message<?= $totalMatches === 1 ? '' : 's' ?> &middot; page <?= $ctx['page'] ?> of <?= $totalPages ?></p>
          <?php foreach ($messages as $item):
            $mid = (int)$item['message_id'];
            $status = $item['status'] ?? 'new';
            $thread = $repliesByMessage[$mid] ?? [];
            $isOpen = ($openId === $mid);
          ?>
            <article class="inquiry-card <?= $status === 'new' ? 'is-new' : '' ?>" id="inquiry-<?= $mid ?>" data-id="<?= $mid ?>" data-status="<?= inquiry_h($status) ?>">
              <button type="button" class="inquiry-card-head" data-bs-toggle="collapse" data-bs-target="#inquiry-body-<?= $mid ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="inquiry-body-<?= $mid ?>">
                <span>
                  <span class="inquiry-subject d-block"><?= inquiry_h($item['subject'] ?? 'General Inquiry') ?></span>
                  <span class="inquiry-meta d-block"><strong><?= inquiry_h($item['name']) ?></strong> &middot; <?= inquiry_h($item['email']) ?><?php if (!empty($item['phone'])): ?> &middot; <?= inquiry_h($item['phone']) ?><?php endif; ?><br>
                  Type: <strong><?= inquiry_h($item['sender_type'] ?? 'General Inquiry') ?></strong> &middot; Received: <?= !empty($item['created_at']) ? inquiry_h(date('d M Y, h:i A', strtotime($item['created_at']))) : 'Unknown' ?></span>
                  <span class="inquiry-snippet d-block"><?= inquiry_h(mb_strimwidth(preg_replace('/\s+/', ' ', (string)$item['message']), 0, 140, '…', 'UTF-8')) ?></span>
                </span>
                <span class="inquiry-badge js-status-badge <?= inquiry_h($status) ?>"><?= inquiry_h($status) ?></span>
              </button>

              <div class="collapse inquiry-collapse <?= $isOpen ? 'show' : '' ?>" id="inquiry-body-<?= $mid ?>">
                <div class="inquiry-body">
                  <section>
                    <div class="inquiry-label">Message from sender</div>
                    <div class="inquiry-message"><?= inquiry_h($item['message']) ?></div>

                    <?php if ($thread): ?>
                      <div class="reply-thread">
                        <div class="inquiry-label">Reply history (<?= count($thread) ?>)</div>
                        <?php foreach ($thread as $r): $failed = $r['email_status'] === 'failed'; ?>
                          <div class="reply-item <?= $failed ? 'failed' : '' ?>">
                            <div class="reply-meta">
                              <i class="bi <?= $failed ? 'bi-exclamation-circle-fill text-danger' : 'bi-check-circle-fill text-success' ?> me-1"></i>
                              <strong><?= $failed ? 'Saved &ndash; email NOT delivered' : 'Emailed' ?></strong>
                              &middot; <?= inquiry_h(date('d M Y, h:i A', strtotime($r['sent_at'] ?: $r['created_at']))) ?>
                              <?php if (!empty($r['admin_name'])): ?>&middot; by <?= inquiry_h($r['admin_name']) ?><?php endif; ?>
                            </div>
                            <div class="reply-text"><?= inquiry_h($r['reply_text']) ?></div>
                            <?php if ($failed): ?>
                              <?php if (!empty($r['email_error'])): ?><div class="small text-danger mt-2"><?= inquiry_h($r['email_error']) ?></div><?php endif; ?>
                              <form method="post" action="manage-inquiries.php" class="mt-2 js-once">
                                <?= inquiry_hidden($ctx, $csrf) ?>
                                <input type="hidden" name="action" value="resend">
                                <input type="hidden" name="message_id" value="<?= $mid ?>">
                                <input type="hidden" name="reply_id" value="<?= (int)$r['reply_id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Resend email</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <div class="inquiry-actions">
                      <?php if ($status === 'read'): ?>
                        <form method="post" action="manage-inquiries.php"><?= inquiry_hidden($ctx, $csrf) ?><input type="hidden" name="action" value="mark_unread"><input type="hidden" name="message_id" value="<?= $mid ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-envelope me-1"></i>Mark as unread</button></form>
                      <?php elseif ($status === 'new'): ?>
                        <form method="post" action="manage-inquiries.php"><?= inquiry_hidden($ctx, $csrf) ?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="message_id" value="<?= $mid ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-envelope-open me-1"></i>Mark as read</button></form>
                      <?php endif; ?>
                      <a class="btn btn-sm btn-outline-primary" href="mailto:<?= inquiry_h($item['email']) ?>?subject=<?= rawurlencode('Re: ' . ($item['subject'] ?? '')) ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Open in email app</a>
                      <form method="post" action="manage-inquiries.php" class="ms-auto js-confirm" data-confirm="Delete this message and its reply history permanently?"><?= inquiry_hidden($ctx, $csrf) ?><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?= $mid ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Delete</button></form>
                    </div>
                  </section>

                  <section>
                    <div class="inquiry-label"><?= $thread ? 'Send another reply' : 'Write a reply' ?></div>
                    <form method="post" class="inquiry-reply-form js-once" action="manage-inquiries.php">
                      <?= inquiry_hidden($ctx, $csrf) ?>
                      <input type="hidden" name="action" value="reply">
                      <input type="hidden" name="message_id" value="<?= $mid ?>">
                      <select class="form-select form-select-sm mb-2 js-template" data-target="reply-<?= $mid ?>" data-name="<?= inquiry_h($item['name']) ?>" aria-label="Insert a reply template">
                        <option value="">Insert a template&hellip;</option>
                        <?php foreach (array_keys(contact_reply_templates()) as $tplName): ?><option value="<?= inquiry_h($tplName) ?>"><?= inquiry_h($tplName) ?></option><?php endforeach; ?>
                      </select>
                      <label class="visually-hidden" for="reply-<?= $mid ?>">Reply message</label>
                      <textarea id="reply-<?= $mid ?>" name="reply_message" maxlength="10000" required placeholder="Write a helpful response to <?= inquiry_h($item['name']) ?>..."></textarea>
                      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                        <small class="text-muted">Emailed to <strong><?= inquiry_h($item['email']) ?></strong> &middot; <span class="js-count">0</span>/10,000</small>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Send Reply</button>
                      </div>
                    </form>

                    <form method="post" class="inquiry-note-form mt-4" action="manage-inquiries.php">
                      <?= inquiry_hidden($ctx, $csrf) ?>
                      <input type="hidden" name="action" value="save_note">
                      <input type="hidden" name="message_id" value="<?= $mid ?>">
                      <div class="inquiry-label"><i class="bi bi-lock-fill me-1"></i>Private admin note (never emailed)</div>
                      <textarea name="admin_note" maxlength="2000" placeholder="Internal notes, follow-ups..."><?= inquiry_h($item['admin_note'] ?? '') ?></textarea>
                      <button type="submit" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-save me-1"></i>Save note</button>
                    </form>
                  </section>
                </div>
              </div>
            </article>
          <?php endforeach; ?>

          <?php if ($totalPages > 1): $base = ['status' => $ctx['status'], 'type' => $ctx['type'], 'q' => $ctx['q']]; ?>
            <nav class="inquiry-pager" aria-label="Pagination">
              <?php if ($ctx['page'] > 1): ?><a href="<?= inquiry_h(inquiry_url($base + ['page' => $ctx['page'] - 1])) ?>">&laquo; Prev</a><?php else: ?><span class="disabled">&laquo; Prev</span><?php endif; ?>
              <?php for ($i = 1; $i <= $totalPages; $i++):
                if ($i === 1 || $i === $totalPages || abs($i - $ctx['page']) <= 2): ?>
                  <?php if ($i === $ctx['page']): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= inquiry_h(inquiry_url($base + ['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
                <?php elseif (abs($i - $ctx['page']) === 3): ?><span class="disabled">&hellip;</span><?php endif; ?>
              <?php endfor; ?>
              <?php if ($ctx['page'] < $totalPages): ?><a href="<?= inquiry_h(inquiry_url($base + ['page' => $ctx['page'] + 1])) ?>">Next &raquo;</a><?php else: ?><span class="disabled">Next &raquo;</span><?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
<script>
(function () {
  var TEMPLATES = <?= json_encode(contact_reply_templates(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  var CSRF = <?= json_encode($csrf) ?>;

  // Opening a NEW message marks it as read automatically (no page reload).
  document.querySelectorAll('.inquiry-collapse').forEach(function (panel) {
    panel.addEventListener('show.bs.collapse', function () {
      var card = panel.closest('.inquiry-card');
      if (!card || card.dataset.status !== 'new') return;
      var fd = new FormData();
      fd.append('action', 'mark_read'); fd.append('message_id', card.dataset.id); fd.append('csrf_token', CSRF);
      fetch('manage-inquiries.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || !data.ok) return;
          card.dataset.status = 'read';
          card.classList.remove('is-new');
          var badge = card.querySelector('.js-status-badge');
          if (badge) { badge.textContent = 'read'; badge.className = 'inquiry-badge js-status-badge read'; }
          var mark = card.querySelector('input[value="mark_read"]');
          if (mark) { var f = mark.closest('form'); if (f) f.remove(); }
          document.querySelectorAll('.js-new-count').forEach(function (el) { el.textContent = data.new_count; });
          document.querySelectorAll('.js-new-badge').forEach(function (el) { el.textContent = data.new_count; el.style.display = data.new_count > 0 ? '' : 'none'; });
        }).catch(function () {});
    });
  });

  // Reply templates + character counter.
  document.querySelectorAll('.js-template').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var ta = document.getElementById(sel.dataset.target);
      if (!ta || !sel.value || !TEMPLATES[sel.value]) return;
      if (ta.value.trim() !== '' && !confirm('Replace the text you have already written?')) { sel.value = ''; return; }
      ta.value = TEMPLATES[sel.value].replace(/\{name\}/g, sel.dataset.name || 'there');
      ta.dispatchEvent(new Event('input')); ta.focus(); sel.value = '';
    });
  });
  document.querySelectorAll('.inquiry-reply-form textarea').forEach(function (ta) {
    var out = ta.closest('form').querySelector('.js-count');
    var update = function () { if (out) out.textContent = ta.value.length.toLocaleString(); };
    ta.addEventListener('input', update); update();
  });

  // Prevent double-sending e-mails, and confirm deletions.
  document.querySelectorAll('form.js-once').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type="submit"]');
      if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...'; }
    });
  });
  document.querySelectorAll('form.js-confirm').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // Scroll to the message that was just acted on.
  if (location.hash) { var t = document.querySelector(location.hash); if (t) t.scrollIntoView({ block: 'start' }); }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
