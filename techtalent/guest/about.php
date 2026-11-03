<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$page_title = 'About TechTalent - Empowering Tech Careers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    /* Hero Banner */
    .about-hero-section {
        background: radial-gradient(circle at 15% 50%, #0d2a4d 0%, #06152b 100%);
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .about-hero-section::before {
        content: "";
        position: absolute;
        top: -50px;
        right: 10%;
        width: 380px;
        height: 380px;
        background: radial-gradient(circle, rgba(56, 189, 248, 0.2) 0%, transparent 70%);
        pointer-events: none;
    }

    /* Core Content Card Styling */
    .about-highlight-card {
        background: #ffffff;
        border: 1px solid #eef2f6 !important;
        border-radius: 20px;
        transition: transform 0.24s ease, box-shadow 0.24s ease;
    }
    .about-highlight-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 28px rgba(15, 33, 55, 0.08) !important;
    }

    /* Feature Pod */
    .feature-icon-pod {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        flex-shrink: 0;
    }

    /* Feature Cards */
    .feature-card-item {
        background: #ffffff;
        border: 1px solid #eef2f6;
        border-radius: 18px;
        padding: 24px;
        height: 100%;
        transition: transform 0.24s ease, box-shadow 0.24s ease, border-color 0.24s ease;
    }
    .feature-card-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 16px 32px rgba(15, 33, 55, 0.08);
        border-color: #93c5fd;
    }

    /* Pill Badges */
    .about-badge-soft {
        background: rgba(56, 189, 248, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.3);
        font-size: 0.8rem;
        padding: 5px 14px;
        border-radius: 50rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .cta-banner-about {
        background: linear-gradient(135deg, #09192f 0%, #102a4a 100%);
        border-radius: 22px;
        padding: 45px 35px;
    }
</style>

<!-- Top Hero Header -->
<section class="about-hero-section py-5 text-center">
    <div class="container py-3">
        <span class="about-badge-soft mb-3">
            <i class="bi bi-stars text-warning"></i> Next-Gen Recruitment
        </span>
        <h1 class="display-5 fw-bold text-white mb-2">About TechTalent Solutions</h1>
        <p class="text-white-50 fs-6 col-lg-7 mx-auto mb-0">
            Empowering the Next Generation of Tech Careers across Software Engineering, AI, Cloud, Cybersecurity, and Data Science.
        </p>
    </div>
</section>

<div class="container py-5">
    
    <!-- Who We Are Row (Action buttons removed) -->
    <div class="row align-items-center g-5 mb-5">
        <div class="col-lg-6">
            <span class="text-primary fw-bold text-uppercase small tracking-wider">Our Story</span>
            <h2 class="fw-bold text-dark mt-1 mb-3">Who We Are</h2>
            <p class="text-muted lh-base mb-3">
                <strong>TechTalent Solutions</strong> is a premier career development and recruitment platform engineered to bridge the technical divide between progressive enterprise employers and ambitious IT professionals.
            </p>
            <p class="text-muted lh-base mb-0">
                Whether you are an aspiring engineer looking for your initial industry breakthrough or an experienced architect aiming for strategic leadership roles, our ecosystem provides high-precision keyword matching, automated competency scoring, and streamlined application management.
            </p>
        </div>

        <!-- Modern Tech Illustration -->
        <div class="col-lg-6 text-center">
            <svg width="100%" height="340" viewBox="0 0 500 340" fill="none" xmlns="http://www.w3.org/2000/svg" class="img-fluid" style="filter: drop-shadow(0 14px 28px rgba(15, 33, 55, 0.1));">
                <!-- Outer Tech Card -->
                <rect x="30" y="25" width="440" height="290" rx="20" fill="#0b1e3b" stroke="#1e3a5f" stroke-width="2"/>
                <!-- Browser Bar -->
                <rect x="30" y="25" width="440" height="40" rx="20" fill="#07152b"/>
                <circle cx="55" cy="45" r="5" fill="#ef4444"/>
                <circle cx="70" cy="45" r="5" fill="#f59e0b"/>
                <circle cx="85" cy="45" r="5" fill="#10b981"/>
                <rect x="120" y="37" width="260" height="16" rx="8" fill="#1e293b"/>
                <!-- Network / Code Grid Panels -->
                <rect x="55" y="85" width="180" height="100" rx="12" fill="#0f294d" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="4 2"/>
                <rect x="70" y="105" width="100" height="10" rx="3" fill="#38bdf8" opacity="0.8"/>
                <rect x="70" y="125" width="140" height="6" rx="2" fill="#64748b"/>
                <rect x="70" y="137" width="120" height="6" rx="2" fill="#64748b"/>
                <rect x="70" y="149" width="80" height="6" rx="2" fill="#64748b"/>
                <!-- Candidate Matching Card -->
                <rect x="260" y="85" width="185" height="140" rx="14" fill="#ffffff"/>
                <circle cx="295" cy="120" r="16" fill="#0284c7"/>
                <circle cx="295" cy="116" r="6" fill="#ffffff"/>
                <path d="M295 124c-5 0-9 4-9 8h18c0-4-4-8-9-8z" fill="#07152b" opacity="0.3"/>
                <rect x="325" y="110" width="95" height="8" rx="2" fill="#0f172a"/>
                <rect x="325" y="124" width="70" height="6" rx="2" fill="#94a3b8"/>
                <rect x="275" y="155" width="155" height="6" rx="2" fill="#e2e8f0"/>
                <rect x="275" y="167" width="130" height="6" rx="2" fill="#e2e8f0"/>
                <rect x="275" y="185" width="75" height="22" rx="6" fill="#dcfce7"/>
                <text x="312" y="199" fill="#15803d" font-size="9" font-family="sans-serif" font-weight="bold" text-anchor="middle">94% Match</text>
                <!-- Terminal / Stats Footer -->
                <rect x="55" y="200" width="180" height="95" rx="12" fill="#07152b" stroke="#1e3a5f" stroke-width="1"/>
                <text x="75" y="225" fill="#38bdf8" font-family="monospace" font-size="10">&gt; ATS Analyzer: OK</text>
                <text x="75" y="245" fill="#34d399" font-family="monospace" font-size="10">&gt; PHP PDO Secure</text>
                <text x="75" y="265" fill="#94a3b8" font-family="monospace" font-size="10">&gt; 8 Core Categories</text>
            </svg>
        </div>
    </div>

    <!-- Mission & Vision Cards Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="about-highlight-card shadow-sm p-4 p-md-5 h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="feature-icon-pod bg-primary-subtle text-primary">
                        <i class="bi bi-bullseye"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-0">Our Mission</h4>
                </div>
                <p class="text-muted lh-base mb-0">
                    To simplify the tech hiring and recruitment process by matching developers, data scientists, and IT leaders with verified vacancies based on clear, transparent competency rubrics.
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="about-highlight-card shadow-sm p-4 p-md-5 h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="feature-icon-pod bg-info-subtle text-info">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-0">Our Vision</h4>
                </div>
                <p class="text-muted lh-base mb-0">
                    To be the foremost reliable career ecosystem in the IT industry, acclaimed for algorithmic transparency, ATS preparedness, and genuine professional talent development.
                </p>
            </div>
        </div>
    </div>

    <!-- Platform Capabilities & Intelligent Features -->
    <div class="py-4">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill mb-2 fw-semibold">
                Platform Capabilities
            </span>
            <h2 class="fw-bold text-dark">Intelligent Platform Features</h2>
            <p class="text-muted col-lg-7 mx-auto">
                Discover the end-to-end tooling designed to streamline technical talent acquisition for both job seekers and hiring teams.
            </p>
        </div>

        <div class="row g-4">
            
            <!-- Feature 1: CV Studio & ATS Analyzer -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-item">
                    <div class="feature-icon-pod bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-file-earmark-code"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">CV Studio &amp; ATS Analyzer</h5>
                    <p class="text-muted small lh-base mb-0">
                        Create print-ready tech resumes or import external PDF/TXT files. The built-in ATS engine checks keyword density against job descriptions to optimize applicant readiness.
                    </p>
                </div>
            </div>

            <!-- Feature 2: 8 Specialized IT Disciplines -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-item">
                    <div class="feature-icon-pod bg-info-subtle text-info mb-3">
                        <i class="bi bi-grid-1x2"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Categorized Career Hub</h5>
                    <p class="text-muted small lh-base mb-0">
                        Explore dedicated openings across 8 specialized domains: Software Engineering, AI &amp; ML, Data Science, Cybersecurity, Cloud/DevOps, Networking, UI/UX &amp; QA, and IT Project Management.
                    </p>
                </div>
            </div>

            <!-- Feature 3: Technical Skills Matrix -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-item">
                    <div class="feature-icon-pod bg-warning-subtle text-warning mb-3">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Technical Competency Stack</h5>
                    <p class="text-muted small lh-base mb-0">
                        Candidates record their exact programming languages, frameworks, and tools with proficiency tags, enabling recruiters to filter applicants by precise tech competencies.
                    </p>
                </div>
            </div>

            <!-- Feature 4: Live Recruiter Pipeline -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-item">
                    <div class="feature-icon-pod bg-success-subtle text-success mb-3">
                        <i class="bi bi-kanban"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Recruitment Pipeline</h5>
                    <p class="text-muted small lh-base mb-0">
                        Employers can post vacancies, screen applicant profiles, update hiring statuses (Under Review, Shortlisted, Rejected), and maintain a clear audit trail of hiring decisions.
                    </p>
                </div>
            </div>

            <!-- Feature 5: CV Snapshot Immutability -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-item">
                    <div class="feature-icon-pod bg-danger-subtle text-danger mb-3">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Immutable Application Snapshots</h5>
                    <p class="text-muted small lh-base mb-0">
                        When applying, an immutable JSON snapshot of your resume is preserved alongside the application, guaranteeing recruiters evaluate the exact credentials submitted.
                    </p>
                </div>
            </div>

            <!-- Feature 6: Company Profile Branding -->
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-item">
                    <div class="feature-icon-pod bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-building-gear"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Employer Branding Portal</h5>
                    <p class="text-muted small lh-base mb-0">
                        Organizations can curate company profile pages highlighting their culture, engineering principles, headquarters, and active vacancies to attract top-tier tech candidates.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- Bottom CTA Banner -->
    <div class="mt-5">
        <div class="cta-banner-about text-white shadow-lg">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h3 class="fw-bold text-white mb-2">Join TechTalent Solutions Today</h3>
                    <p class="text-white-50 mb-0">
                        Whether you are looking to hire high-performing technical talent or searching for your next career opportunity, our platform is built for you.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="../auth/register.php" class="btn btn-primary px-4 py-2.5 rounded-3 fw-semibold shadow-sm">
                        <i class="bi bi-person-plus me-1"></i> Register Now
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>