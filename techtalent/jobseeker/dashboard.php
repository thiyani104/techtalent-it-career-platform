<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auth Guard: ensure job seeker
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true)) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Tech Professional';

// Dashboard stats
$profile_completion = 0;
$app_count = 0;
$skill_count = 0;
try {
    $stmt_p = $pdo->prepare("SELECT headline, phone, location, bio FROM job_seeker_profiles WHERE user_id = :uid LIMIT 1");
    $stmt_p->execute([':uid' => $user_id]);
    $prof = $stmt_p->fetch(PDO::FETCH_ASSOC);
    if ($prof) {
        if (!empty(trim($prof['headline'] ?? ''))) $profile_completion += 20;
        if (!empty(trim($prof['phone'] ?? ''))) $profile_completion += 20;
        if (!empty(trim($prof['location'] ?? ''))) $profile_completion += 20;
        if (!empty(trim($prof['bio'] ?? ''))) $profile_completion += 20;
    }
    $stmt_s = $pdo->prepare("SELECT COUNT(*) FROM user_skills WHERE user_id = :uid");
    $stmt_s->execute([':uid' => $user_id]);
    $skill_count = (int)$stmt_s->fetchColumn();
    if ($skill_count >= 3) $profile_completion += 20; elseif ($skill_count > 0) $profile_completion += 10;
    
    $stmt_a = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_seeker_id = :uid");
    $stmt_a->execute([':uid' => $user_id]);
    $app_count = (int)$stmt_a->fetchColumn();
} catch (PDOException $e) {
    error_log('Dashboard Stats Query Error: ' . $e->getMessage());
}

$page_title = 'Job Seeker Dashboard - TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    :root {
        --tt-navy-dark: #07172f;
        --tt-navy-card: #0b1f3d;
        --tt-blue-primary: #1570ef;
        --tt-blue-light: #38bdf8;
    }

    body {
        background-color: #f8fafc;
    }

    /* 1. Header Banner */
    .dashboard-hero-mockup {
        background: radial-gradient(circle at 75% 40%, #173d6e 0%, #0c2346 45%, #051428 100%);
        border-radius: 22px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 16px 36px rgba(5, 20, 40, 0.28);
    }
    .hero-badge-pill {
        background: rgba(14, 165, 233, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.3);
        font-size: 0.8rem;
        padding: 5px 14px;
        border-radius: 50rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .status-dot-pulse {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
    }
    .hero-btn-primary {
        background: #1570ef;
        border: 1px solid #1570ef;
        color: #fff;
        border-radius: 12px;
        padding: 9px 20px;
        font-weight: 600;
        font-size: 0.92rem;
        transition: all 0.2s ease;
    }
    .hero-btn-primary:hover {
        background: #0d5ecc;
        color: #fff;
        transform: translateY(-2px);
    }
    .hero-btn-outline {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        border-radius: 12px;
        padding: 9px 20px;
        font-weight: 600;
        font-size: 0.92rem;
        backdrop-filter: blur(6px);
        transition: all 0.2s ease;
    }
    .hero-btn-outline:hover {
        background: rgba(255, 255, 255, 0.16);
        color: #fff;
        transform: translateY(-2px);
    }

    /* 2. Dark Metric Strip Cards */
    .metric-card-dark {
        background: #0d2242;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 20px 22px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .metric-card-dark:hover {
        transform: translateY(-4px);
        box-shadow: 0 14px 28px rgba(11, 31, 61, 0.25);
        border-color: rgba(56, 189, 248, 0.4);
        color: #fff;
    }
    .metric-icon-pod {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        flex-shrink: 0;
    }
    .pod-blue { background: rgba(14, 165, 233, 0.18); color: #38bdf8; }
    .pod-purple { background: rgba(147, 51, 234, 0.18); color: #c084fc; }
    .pod-green { background: rgba(16, 185, 129, 0.18); color: #34d399; }

    .custom-progress-track {
        height: 6px;
        width: 140px;
        background: rgba(255, 255, 255, 0.12);
        border-radius: 10px;
        overflow: hidden;
        margin-top: 8px;
    }
    .custom-progress-bar {
        height: 100%;
        background: #38bdf8;
        border-radius: 10px;
    }
    .metric-chevron {
        font-size: 1.15rem;
        color: rgba(255, 255, 255, 0.45);
        transition: transform 0.2s ease, color 0.2s ease;
    }
    .metric-card-dark:hover .metric-chevron {
        transform: translateX(4px);
        color: #fff;
    }

    /* 3. White Action Cards */
    .action-card-white {
        background: #ffffff;
        border: 1px solid #eef2f6;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(15, 33, 55, 0.04);
        display: flex;
        flex-direction: column;
        height: 100%;
        transition: transform 0.24s ease, box-shadow 0.24s ease;
    }
    .action-card-white:hover {
        transform: translateY(-5px);
        box-shadow: 0 16px 32px rgba(15, 33, 55, 0.08);
    }
    .action-head-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: #0a1f3d;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .action-badge-arrow {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }
    .action-card-white:hover .action-badge-arrow {
        background: #0a1f3d;
        color: #ffffff;
    }
    .action-pill-btn {
        border-radius: 14px;
        padding: 9px 18px;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .action-pill-btn-outline {
        border: 1px solid #cbd5e1;
        color: #1e293b;
        background: transparent;
    }
    .action-pill-btn-outline:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }
    .action-pill-btn-dark {
        background: #0a1f3d;
        border: 1px solid #0a1f3d;
        color: #ffffff;
    }
    .action-pill-btn-dark:hover {
        background: #153460;
        color: #ffffff;
    }

    /* 4. Bottom Row Feature Cards */
    .cv-studio-card {
        background: linear-gradient(135deg, #103362 0%, #0d284f 55%, #081a36 100%);
        border-radius: 22px;
        padding: 30px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(56, 189, 248, 0.2);
        box-shadow: 0 12px 28px rgba(10, 31, 61, 0.2);
    }
    .feedback-card {
        background: radial-gradient(circle at 80% 20%, #143763 0%, #0b1f3d 100%);
        border-radius: 22px;
        padding: 30px;
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 12px 28px rgba(10, 31, 61, 0.2);
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .btn-pill-light {
        background: #ffffff;
        color: #0a1f3d;
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 700;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.2s ease;
    }
    .btn-pill-light:hover {
        background: #e2e8f0;
        color: #0a1f3d;
        transform: translateY(-2px);
    }
    .btn-pill-outline-light {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #ffffff;
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .btn-pill-outline-light:hover {
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
        border-color: #ffffff;
    }
</style>

<div class="container py-4">

    <!-- 1. Header Banner -->
    <div class="dashboard-hero-mockup p-4 p-md-5 mb-4">
        <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
            <div class="col-lg-7">
                <span class="hero-badge-pill mb-3">
                    <span class="status-dot-pulse"></span> Candidate Portal
                </span>
                <h1 class="display-6 fw-bold text-white mb-2" style="font-weight: 800;">
                    Welcome back,<br>
                    <span style="color: #38bdf8;"><?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>!</span>
                </h1>
                <p class="text-white-50 mb-0 fs-6 col-xl-10 lh-base">
                    Track your applications, manage your technical competency stack, and check automated ATS job readiness.
                </p>
            </div>
            
            <!-- Right Laptop / Desk Workspace Illustration Vector -->
            <div class="col-lg-5 text-center text-lg-end position-relative">
                <div class="d-inline-flex flex-wrap gap-2 mb-3">
                    <a href="../guest/jobs.php" class="hero-btn-primary text-decoration-none">
                        <i class="bi bi-search me-1"></i> Browse Jobs
                    </a>
                    <a href="profile.php" class="hero-btn-outline text-decoration-none">
                        <i class="bi bi-person-lines-fill me-1"></i> Edit Profile
                    </a>
                </div>

                <!-- Ambient illustration matching mockup -->
                <div class="d-none d-md-block mt-2">
                    <svg width="280" height="150" viewBox="0 0 280 150" fill="none" xmlns="http://www.w3.org/2000/svg" class="img-fluid" style="filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));">
                        <!-- Laptop Base -->
                        <path d="M50 120H230L245 130H35L50 120Z" fill="#1e3a5f" />
                        <rect x="70" y="45" width="140" height="75" rx="6" fill="#0b1728" stroke="#38bdf8" stroke-width="2" />
                        <!-- Screen UI elements -->
                        <rect x="78" y="55" width="30" height="20" rx="3" fill="#1570ef" opacity="0.8"/>
                        <circle cx="93" cy="63" r="5" fill="#fff" opacity="0.9"/>
                        <rect x="114" y="55" width="88" height="6" rx="2" fill="#38bdf8" opacity="0.6"/>
                        <rect x="114" y="65" width="70" height="4" rx="2" fill="#64748b"/>
                        <rect x="114" y="73" width="78" height="4" rx="2" fill="#64748b"/>
                        <!-- Floating Avatar & Bubble Card -->
                        <rect x="180" y="25" width="55" height="30" rx="6" fill="#1570ef" opacity="0.9"/>
                        <circle cx="195" cy="40" r="7" fill="#ffffff"/>
                        <path d="M195 40c-4 0-7 3-7 7h14c0-4-3-7-7-7z" fill="#0b1728" opacity="0.4"/>
                        <rect x="206" y="34" width="22" height="4" rx="2" fill="#ffffff"/>
                        <rect x="206" y="42" width="16" height="3" rx="1.5" fill="#bae6fd"/>
                        <!-- Plant pot -->
                        <path d="M245 110L248 130H236L239 110H245Z" fill="#38bdf8" opacity="0.8"/>
                        <circle cx="242" cy="105" r="5" fill="#10b981"/>
                        <circle cx="246" cy="100" r="4" fill="#34d399"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Dark Metric Strip Cards Row -->
    <div class="row g-4 mb-4">
        
        <!-- Profile Strength -->
        <div class="col-md-4">
            <a href="profile.php" class="metric-card-dark">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon-pod pod-blue">
                        <i class="bi bi-bar-chart-line-fill"></i>
                    </div>
                    <div>
                        <span class="text-white-50 small fw-bold text-uppercase tracking-wider" style="font-size:0.75rem;">Profile Strength</span>
                        <h4 class="fw-bold mb-0 mt-1"><?= $profile_completion; ?>%</h4>
                        <div class="custom-progress-track">
                            <div class="custom-progress-bar" style="width: <?= $profile_completion; ?>%;"></div>
                        </div>
                    </div>
                </div>
                <i class="bi bi-chevron-right metric-chevron"></i>
            </a>
        </div>

        <!-- Tech Competencies -->
        <div class="col-md-4">
            <a href="skills.php" class="metric-card-dark">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon-pod pod-purple">
                        <i class="bi bi-code-slash"></i>
                    </div>
                    <div>
                        <span class="text-white-50 small fw-bold text-uppercase tracking-wider" style="font-size:0.75rem;">Tech Competencies</span>
                        <h4 class="fw-bold mb-0 mt-1"><?= $skill_count; ?> <span class="fs-6 fw-normal text-white-50">Skills</span></h4>
                        <span class="small fw-semibold mt-1 d-inline-block" style="color: #38bdf8;">
                            Manage Stack <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </div>
                <i class="bi bi-chevron-right metric-chevron"></i>
            </a>
        </div>

        <!-- Active Applications -->
        <div class="col-md-4">
            <a href="applications.php" class="metric-card-dark">
                <div class="d-flex align-items-center gap-3">
                    <div class="metric-icon-pod pod-green">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <span class="text-white-50 small fw-bold text-uppercase tracking-wider" style="font-size:0.75rem;">Active Applications</span>
                        <h4 class="fw-bold mb-0 mt-1"><?= $app_count; ?></h4>
                        <span class="small fw-semibold mt-1 d-inline-block" style="color: #38bdf8;">
                            View Status <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </div>
                </div>
                <i class="bi bi-chevron-right metric-chevron"></i>
            </a>
        </div>

    </div>

    <!-- 3. White Action Cards Row -->
    <div class="row g-4 mb-4">
        
        <!-- Action 1: My Profile -->
        <div class="col-md-4">
            <div class="action-card-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="action-head-icon">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">My Profile</h5>
                            <span class="text-muted small">Bio, headline &amp; location</span>
                        </div>
                    </div>
                    <a href="profile.php" class="action-badge-arrow text-decoration-none">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
                <p class="text-muted small flex-grow-1 mb-4">
                    Keep your contact information, professional career statement, and work management preferences current.
                </p>
                <a href="profile.php" class="action-pill-btn action-pill-btn-outline text-decoration-none">
                    <i class="bi bi-pencil-square me-1"></i> Edit Profile <i class="bi bi-chevron-right ms-1 small"></i>
                </a>
            </div>
        </div>

        <!-- Action 2: Skills Manager -->
        <div class="col-md-4">
            <div class="action-card-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="action-head-icon">
                            <i class="bi bi-diagram-3-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Skills Manager</h5>
                            <span class="text-muted small">Technical stack &amp; proficiency</span>
                        </div>
                    </div>
                    <a href="skills.php" class="action-badge-arrow text-decoration-none">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
                <p class="text-muted small flex-grow-1 mb-4">
                    Add new technical competencies with proficiency benchmarks to enhance your matching score on jobs.
                </p>
                <a href="skills.php" class="action-pill-btn action-pill-btn-dark text-decoration-none">
                    <i class="bi bi-gear-fill me-1"></i> Manage Tech Stack <i class="bi bi-chevron-right ms-1 small"></i>
                </a>
            </div>
        </div>

        <!-- Action 3: Applications Tracker -->
        <div class="col-md-4">
            <div class="action-card-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="action-head-icon">
                            <i class="bi bi-card-checklist"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Applications Tracker</h5>
                            <span class="text-muted small">Live submission tracking</span>
                        </div>
                    </div>
                    <a href="applications.php" class="action-badge-arrow text-decoration-none">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
                <p class="text-muted small flex-grow-1 mb-4">
                    Monitor employer feedback, application review, states, shortlisted notifications, and immutable CV snapshots.
                </p>
                <a href="applications.php" class="action-pill-btn action-pill-btn-outline text-decoration-none">
                    <i class="bi bi-eye me-1"></i> View Applications <i class="bi bi-chevron-right ms-1 small"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- 4. Bottom Feature Cards Row -->
    <div class="row g-4">
        
        <!-- CV Studio & ATS Analyzer -->
        <div class="col-lg-7">
            <div class="cv-studio-card d-flex flex-column h-100">
                <div class="row align-items-center g-4">
                    <div class="col-md-7">
                        <span class="badge hero-badge-pill mb-2">
                            Intelligent Career Tool
                        </span>
                        <h4 class="fw-bold text-white mb-2">CV Studio &amp; ATS Analyzer</h4>
                        <p class="text-white-50 small mb-4 lh-base">
                            Draft modern tech resumes from your profile, import text/PDF resumes, verify keyword readiness against published job requirements, and export print-ready PDFs.
                        </p>
                        <a href="cv-studio.php" class="btn-pill-light text-decoration-none">
                            <i class="bi bi-file-earmark-person"></i> Open CV Studio <i class="bi bi-chevron-right small ms-1"></i>
                        </a>
                    </div>
                    
                    <!-- ATS Shield Illustration Vector -->
                    <div class="col-md-5 text-center">
                        <svg width="180" height="150" viewBox="0 0 180 150" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Document Sheet -->
                            <rect x="40" y="20" width="90" height="115" rx="8" fill="#ffffff" opacity="0.95"/>
                            <rect x="52" y="32" width="22" height="22" rx="11" fill="#38bdf8"/>
                            <circle cx="63" cy="40" r="5" fill="#ffffff"/>
                            <rect x="80" y="36" width="38" height="5" rx="2" fill="#0f172a" opacity="0.7"/>
                            <rect x="80" y="45" width="25" height="4" rx="2" fill="#94a3b8"/>
                            <rect x="52" y="65" width="66" height="4" rx="2" fill="#cbd5e1"/>
                            <rect x="52" y="74" width="66" height="4" rx="2" fill="#cbd5e1"/>
                            <rect x="52" y="83" width="50" height="4" rx="2" fill="#cbd5e1"/>
                            <rect x="52" y="92" width="60" height="4" rx="2" fill="#cbd5e1"/>
                            <!-- ATS Approved Badge -->
                            <rect x="100" y="95" width="46" height="34" rx="8" fill="#0284c7" stroke="#ffffff" stroke-width="2"/>
                            <path d="M115 110L121 116L131 106" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <text x="123" y="125" fill="#ffffff" font-size="8" font-family="sans-serif" font-weight="bold" text-anchor="middle">ATS</text>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Feedback Card -->
        <div class="col-lg-5">
            <div class="feedback-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="metric-icon-pod" style="background: #f59e0b; color: #ffffff;">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-white mb-0">Platform Feedback</h5>
                            <span class="text-white-50 small">Share your experience</span>
                        </div>
                    </div>
                    <a href="submit-review.php" class="action-badge-arrow text-decoration-none" style="background: rgba(255,255,255,0.1); color: #fff;">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
                <p class="text-white-50 small flex-grow-1 mb-4 lh-base">
                    Rate platform workflows and share candidate reviews to help employers discover reliable feedback on TechTalent Solutions.
                </p>
                <a href="submit-review.php" class="btn-pill-outline-light text-decoration-none">
                    <i class="bi bi-star me-1"></i> Submit Review <i class="bi bi-chevron-right small ms-1"></i>
                </a>
            </div>
        </div>

    </div>

</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>