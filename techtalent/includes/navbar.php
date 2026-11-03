<?php
// Determine user login status and role dynamically
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['role']);
$user_role = $_SESSION['role'] ?? 'guest';

// Dynamic dashboard routing based on session role
$dashboard_url = '../guest/index.php';
if ($user_role === 'admin') {
    $dashboard_url = '../admin/dashboard.php';
} elseif ($user_role === 'employer') {
    $dashboard_url = '../employer/dashboard.php';
} elseif ($user_role === 'job_seeker' || $user_role === 'jobseeker') {
    $dashboard_url = '../jobseeker/dashboard.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="../guest/index.php">
                    <div class="d-flex align-items-center justify-content-center" style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.1);backdrop-filter:blur(8px);">
                        <img src="../assets/images/logo-icon.svg" alt="TechTalent Icon" width="24" height="24">
                    </div>
                    <div class="ms-2">
                        <div class="fs-5 fw-bold text-white" style="line-height:1;">
                            Tech<span style="color:#38bdf8;">Talent</span>
                        </div>
                        <div class="text-white-50" style="font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;">SOLUTIONS (PVT) LTD</div>
                    </div>
                </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTechTalent" aria-controls="navbarTechTalent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarTechTalent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item">
                    <a class="nav-link" href="../guest/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../guest/jobs.php">Job Vacancies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../guest/index.php#categories">Career Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../guest/reviews.php">Reviews</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../guest/about.php">About Us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../guest/contact.php">Contact Us</a>
                </li>
            <?php if ($user_role === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link" href="../admin/dashboard.php">Admin Panel</a>
        </li>
    <?php endif; ?>
    </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo htmlspecialchars($dashboard_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-navy me-2">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                    <a href="../auth/logout.php" class="btn btn-accent">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-outline-navy me-2">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                    <a href="../auth/register.php" class="btn btn-accent">
                        <i class="bi bi-person-plus me-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
