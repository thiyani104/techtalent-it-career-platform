
<?php
// Build the correct URL regardless of which admin page includes the sidebar.
$projectBase = '/' . trim(
    str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))),
    '/'
);
?>

<a href="<?= htmlspecialchars($projectBase . '/admin/manage-jobs.php', ENT_QUOTES, 'UTF-8') ?>"
   class="admin-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'manage-jobs.php' ? 'active' : '' ?>">
    <i class="bi bi-briefcase"></i>
    <span>Manage Jobs</span>
</a>
