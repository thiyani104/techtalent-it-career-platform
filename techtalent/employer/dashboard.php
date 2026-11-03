<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auth Guard: Ensure employer
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['employer'], true)) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = 'Employer Dashboard - TechTalent Recruiter';
$uid = (int)$_SESSION['user_id'];

// Company profile (name + logo) of the logged-in employer
require_once __DIR__ . '/../includes/employer-helpers.php';
$profile      = employer_profile($pdo, $uid);
$company_name = $profile['company_name'] !== '' ? $profile['company_name'] : (!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Your Company');
$company_logo = employer_logo_url($profile['company_logo']);

// Active Vacancies
$active_vacancies = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = :uid AND status = 'Published'");
    $stmt->execute([':uid' => $uid]);
    $active_vacancies = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Active Vacancies Error: ' . $e->getMessage());
}

// Total Applicants
$total_applicants = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.employer_id = :uid");
    $stmt->execute([':uid' => $uid]);
    $total_applicants = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Total Applicants Error: ' . $e->getMessage());
}

// Shortlisted Candidates
$shortlisted = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.employer_id = :uid AND a.current_status = 'Shortlisted'");
    $stmt->execute([':uid' => $uid]);
    $shortlisted = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Shortlisted Candidates Error: ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    body {
        background-color: #f4f7fb;
    }

    /* 1. Recruiter Greeting Hero Banner */
    .employer-hero {
        background: radial-gradient(circle at 75% 30%, #173d6e 0%, #0c2346 45%, #051428 100%);
        border-radius: 22px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 16px 36px rgba(5, 20, 40, 0.28);
    }
    .hero-recruiter-pill {
        background: rgba(56, 189, 248, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.3);
        font-size: 0.8rem;
        padding: 5px 14px;
        border-radius: 50rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .status-dot-pulse-recruiter {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #38bdf8;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.3);
    }
    .hero-btn-post {
        background: #1570ef;
        border: 1px solid #1570ef;
        color: #fff;
        border-radius: 12px;
        padding: 9px 20px;
        font-weight: 600;
        font-size: 0.92rem;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .hero-btn-post:hover {
        background: #0d5ecc;
        color: #fff;
        transform: translateY(-2px);
    }
    .hero-btn-manage {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        border-radius: 12px;
        padding: 9px 20px;
        font-weight: 600;
        font-size: 0.92rem;
        text-decoration: none;
        backdrop-filter: blur(6px);
        transition: all 0.2s ease;
    }
    .hero-btn-manage:hover {
        background: rgba(255, 255, 255, 0.16);
        color: #fff;
        transform: translateY(-2px);
    }

    /* 2. Metric Cards */
    .metric-display-card {
        background: #ffffff;
        border: 1px solid #eef2f6;
        border-radius: 20px;
        padding: 24px 20px;
        box-shadow: 0 8px 24px rgba(15, 33, 55, 0.05);
        position: relative;
        overflow: hidden;
        min-height: 190px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.22s ease, box-shadow 0.22s ease;
    }
    .metric-display-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 14px 30px rgba(15, 33, 55, 0.1);
    }

    .metric-info {
        flex-grow: 1;
        text-align: center;
    }
    .metric-label {
        font-size: 0.95rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
    }
    .metric-number {
        font-size: 3.2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 12px;
    }
    .num-blue { color: #0284c7; }
    .num-green { color: #16a34a; }
    .num-gold { color: #d97706; }

    .metric-pill-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 18px;
        border-radius: 50rem;
        font-size: 0.84rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .pill-blue {
        border: 1px solid #bae6fd;
        color: #0369a1;
        background: #ffffff;
    }
    .pill-blue:hover {
        background: #f0f9ff;
        color: #0284c7;
    }
    .pill-green {
        border: 1px solid #bbf7d0;
        color: #15803d;
        background: #ffffff;
    }
    .pill-green:hover {
        background: #f0fdf4;
        color: #16a34a;
    }
    .pill-gold {
        border: 1px solid #fed7aa;
        color: #b45309;
        background: #ffffff;
    }
    .pill-gold:hover {
        background: #fffbeb;
        color: #d97706;
    }

    .graphic-pod {
        width: 100px;
        height: 100px;
        flex-shrink: 0;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pod-circle-blue {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #e0f2fe;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0284c7;
        font-size: 1.8rem;
    }
    .pod-circle-green {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #dcfce7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #16a34a;
        font-size: 1.8rem;
    }
    .pod-circle-gold {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #fef3c7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #d97706;
        font-size: 1.8rem;
    }
    .trending-indicator {
        position: absolute;
        top: 2px;
        right: 10px;
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    /* 3. Bottom Action Buttons */
    .btn-act-green {
        background: #16a34a;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 600;
        font-size: 0.92rem;
        box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-act-green:hover {
        background: #15803d;
        color: #ffffff;
        transform: translateY(-2px);
    }
    .btn-act-navy {
        background: #0f3261;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 600;
        font-size: 0.92rem;
        box-shadow: 0 4px 14px rgba(15, 50, 97, 0.35);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-act-navy:hover {
        background: #0c274c;
        color: #ffffff;
        transform: translateY(-2px);
    }
    .btn-act-cyan {
        background: #06b6d4;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 600;
        font-size: 0.92rem;
        box-shadow: 0 4px 14px rgba(6, 182, 212, 0.35);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-act-cyan:hover {
        background: #0891b2;
        color: #ffffff;
        transform: translateY(-2px);
    }
    .btn-act-outline {
        background: #ffffff;
        color: #334155;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 600;
        font-size: 0.92rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-act-outline:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
        transform: translateY(-2px);
    }
</style>

<div class="container py-4">

    <!-- 1. Recruiter Greeting Banner with Company Name -->
    <div class="employer-hero p-4 p-md-5 mb-4 text-white">
        <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
            <div class="col-lg-7">
                <?php if ($company_logo): ?>
                    <img src="<?= htmlspecialchars($company_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?> logo"
                         class="d-block mb-3" style="height:64px;max-width:180px;object-fit:contain;background:#fff;border-radius:14px;padding:8px;">
                <?php endif; ?>
                <span class="hero-recruiter-pill mb-3">
                    <span class="status-dot-pulse-recruiter"></span> Recruiter Portal
                </span>
                <h1 class="display-6 fw-bold text-white mb-2" style="font-weight: 800;">
                    Welcome back,<br>
                    <span style="color: #38bdf8;"><?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?>!</span>
                </h1>
                <p class="text-white-50 mb-0 fs-6 col-xl-11 lh-base">
                    Track job applications, screen technical candidate competencies, and manage your active hiring pipeline.
                </p>
            </div>
            
            <div class="col-lg-5 text-center text-lg-end">
                <div class="d-inline-flex flex-wrap gap-2 mb-3">
                    <a href="company-profile.php" class="hero-btn-manage">
                        <i class="bi bi-building-gear me-1"></i> Company Profile
                    </a>
                    <a href="post-job.php" class="hero-btn-post">
                        <i class="bi bi-plus-circle me-1"></i> Post Vacancy
                    </a>
                    <a href="manage-jobs.php" class="hero-btn-manage">
                        <i class="bi bi-card-checklist me-1"></i> Manage Vacancies
                    </a>
                </div>

                <!-- Recruiter Desk / Talent Pipeline Vector Illustration -->
                <div class="d-none d-md-block mt-2">
                    <svg width="280" height="135" viewBox="0 0 280 135" fill="none" xmlns="http://www.w3.org/2000/svg" class="img-fluid" style="filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));">
                        <path d="M40 115H240L250 125H30L40 115Z" fill="#1e3a5f" />
                        <rect x="75" y="30" width="130" height="75" rx="6" fill="#0b1728" stroke="#38bdf8" stroke-width="2" />
                        <rect x="130" y="105" width="20" height="10" fill="#38bdf8" opacity="0.6"/>
                        <rect x="85" y="40" width="110" height="6" rx="2" fill="#1570ef" opacity="0.8"/>
                        <circle cx="95" cy="65" r="14" fill="#0284c7" opacity="0.4"/>
                        <path d="M95 55v10l7 4" stroke="#38bdf8" stroke-width="2" stroke-linecap="round"/>
                        <rect x="120" y="75" width="8" height="16" rx="2" fill="#38bdf8"/>
                        <rect x="133" y="65" width="8" height="26" rx="2" fill="#10b981"/>
                        <rect x="146" y="58" width="8" height="33" rx="2" fill="#0284c7"/>
                        <rect x="159" y="70" width="8" height="21" rx="2" fill="#f59e0b"/>
                        <rect x="172" y="52" width="8" height="39" rx="2" fill="#38bdf8"/>
                        <rect x="185" y="15" width="65" height="34" rx="6" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>
                        <circle cx="200" cy="32" r="7" fill="#ffffff"/>
                        <path d="M200 32c-4 0-7 3-7 7h14c0-4-3-7-7-7z" fill="#0b1728" opacity="0.3"/>
                        <rect x="212" y="27" width="26" height="4" rx="1.5" fill="#ffffff"/>
                        <rect x="212" y="34" width="18" height="3" rx="1.5" fill="#bae6fd"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Metric Display Cards -->
    <div class="row g-4 mb-4">
        
        <!-- Active Vacancies Card -->
        <div class="col-md-4">
            <div class="metric-display-card">
                <div class="metric-info text-start">
                    <div class="metric-label">Active Vacancies</div>
                    <div class="metric-number num-blue"><?= $active_vacancies ?></div>
                    <a href="manage-jobs.php" class="metric-pill-btn pill-blue">
                        <i class="bi bi-gear"></i> Manage Vacancies
                    </a>
                </div>
                <div class="graphic-pod">
                    <span class="trending-indicator">Trending <i class="bi bi-arrow-up-right"></i></span>
                    <div class="pod-circle-blue">
                        <i class="bi bi-briefcase"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Applicants Card -->
        <div class="col-md-4">
            <div class="metric-display-card">
                <div class="metric-info text-start">
                    <div class="metric-label">Total Applicants</div>
                    <div class="metric-number num-green"><?= $total_applicants ?></div>
                    <a href="applicants.php" class="metric-pill-btn pill-green">
                        <i class="bi bi-people"></i> View Applicants
                    </a>
                </div>
                <div class="graphic-pod">
                    <div class="pod-circle-green">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shortlisted Candidates Card -->
        <div class="col-md-4">
            <div class="metric-display-card">
                <div class="metric-info text-start">
                    <div class="metric-label">Shortlisted Candidates</div>
                    <div class="metric-number num-gold"><?= $shortlisted ?></div>
                    <a href="applicants.php" class="metric-pill-btn pill-gold">
                        <i class="bi bi-list-task"></i> View Shortlists
                    </a>
                </div>
                <div class="graphic-pod">
                    <div class="pod-circle-gold">
                        <i class="bi bi-award-fill"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 3. Action Buttons Strip with Company Profile Included -->
    <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
        <a href="company-profile.php" class="btn-act-navy">
            <i class="bi bi-building-gear"></i> Company Profile
        </a>
        <a href="post-job.php" class="btn-act-green">
            <i class="bi bi-plus-lg"></i> Post New Vacancy
        </a>
        <a href="manage-jobs.php" class="btn-act-navy">
            <i class="bi bi-folder2-open"></i> Manage Vacancies
        </a>
        <a href="applicants.php" class="btn-act-cyan">
            <i class="bi bi-people"></i> View Applicants
        </a>
        <a href="submit-review.php" class="btn-act-outline">
            <i class="bi bi-star"></i> Submit Review
        </a>
    </div>

</div>

<?php 
include __DIR__ . '/../includes/footer.php'; 
ob_end_flush();
?>