<?php
$page_title = "Home - Find Top Tech Careers | TechTalent Solutions";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/db.php';

// Array of 8 IT Career Categories with Rock-Solid Image Sources & Local Fallbacks
$categories = [
    [
        'id' => 'software',
        'icon' => 'bi-code-square',
        'title' => 'Software Engineering & Development',
        'desc' => 'Frontend, Backend, Full-Stack, and Mobile development roles using modern frameworks.',
        'image' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/software/600/400',
        'tags' => ['Full-Stack', 'Mobile', 'Web APIs']
    ],
    [
        'id' => 'ai_ml',
        'icon' => 'bi-cpu-fill',
        'title' => 'Artificial Intelligence & Machine Learning',
        'desc' => 'LLM engineering, Deep Learning, NLP, Computer Vision, and AI model deployment.',
        'image' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/aiml/600/400',
        'tags' => ['LLMs', 'NLP', 'Computer Vision']
    ],
    [
        'id' => 'data',
        'icon' => 'bi-bar-chart-line-fill',
        'title' => 'Data Science & Analytics',
        'desc' => 'Big Data, Business Intelligence, Data Pipelines, Predictive Analytics, and ETL.',
        'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/datascience/600/400',
        'tags' => ['Big Data', 'PowerBI', 'Pipelines']
    ],
    [
        'id' => 'cybersecurity',
        'icon' => 'bi-shield-lock-fill',
        'title' => 'Cybersecurity',
        'desc' => 'Penetration testing, Security operations (SOC), Governance, Compliance, and Threat Intelligence.',
        'image' => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/cybersecurity/600/400',
        'tags' => ['SOC', 'Pen Testing', 'ISO 27001']
    ],
    [
        'id' => 'cloud',
        'icon' => 'bi-cloud-arrow-up-fill',
        'title' => 'Cloud Computing & DevOps',
        'desc' => 'AWS, Azure, GCP architecture, CI/CD pipelines, Kubernetes, Infrastructure as Code.',
        'image' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/clouddevops/600/400',
        'tags' => ['AWS / Azure', 'Docker', 'CI/CD']
    ],
    [
        'id' => 'networking',
        'icon' => 'bi-diagram-3-fill',
        'title' => 'Networking & Systems',
        'desc' => 'System administration, Network security, Cisco/Juniper routing, Virtualization (VMware).',
        'image' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/networking/600/400',
        'tags' => ['Cisco', 'Linux', 'VMware']
    ],
    [
        'id' => 'ui_ux_qa',
        'icon' => 'bi-palette-fill',
        'title' => 'UI/UX & Software Quality Assurance',
        'desc' => 'User experience research, UI design, Automated testing (Selenium), QA manual testing.',
        'image' => 'https://images.unsplash.com/photo-1581291518857-4e27b48ff24e?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/uiuxqa/600/400',
        'tags' => ['Figma', 'Selenium', 'Testing']
    ],
    [
        'id' => 'pm_ba',
        'icon' => 'bi-kanban-fill',
        'title' => 'IT Project Management & Business Analysis',
        'desc' => 'Agile/Scrum Leadership, Product Ownership, Requirements Gathering, Operations Management.',
        'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=600&q=80',
        'fallback' => 'https://picsum.photos/seed/projectmgmt/600/400',
        'tags' => ['Agile/Scrum', 'Jira', 'Business Analysis']
    ]
];
?>

<style>
    /* Exact Match Full-Bleed Hero Section */
    .hero-section-exact {
        background-color: #06152b;
        background: radial-gradient(circle at 15% 50%, #0d2a4d 0%, #06152b 100%);
        position: relative;
        overflow: hidden;
        min-height: 580px;
        display: flex;
        align-items: center;
    }

    /* Ambient Blue Glow Accent */
    .hero-section-exact::before {
        content: "";
        position: absolute;
        top: 10%;
        right: 25%;
        width: 480px;
        height: 480px;
        background: radial-gradient(circle, rgba(13, 110, 253, 0.28) 0%, rgba(13, 110, 253, 0) 70%);
        pointer-events: none;
        z-index: 1;
    }

  /* Full-Bleed Right-Side Photo */
    .hero-bg-photo {
        position: absolute;
        right: 0;
        bottom: 0;
        top: 0;
        width: 55%;
        max-width: 820px;
        background-image: url('../assets/images/hero-couple.jpg');
        background-size: cover;
        background-position: center top;
        background-repeat: no-repeat;
        z-index: 1;
        /* Single clean fade from dark background on the left to photo on the right */
        -webkit-mask-image: linear-gradient(to right, transparent 0%, rgba(0, 0, 0, 0.4) 15%, black 45%);
        mask-image: linear-gradient(to right, transparent 0%, rgba(0, 0, 0, 0.4) 15%, black 45%);
    }

    @media (max-width: 991.98px) {
        .hero-bg-photo {
            opacity: 0.15;
            width: 100%;
        }
    }
    /* Floating Technology Glass Badges */
    .floating-tech-badge {
        position: absolute;
        z-index: 3;
        background: rgba(15, 33, 55, 0.75);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #ffffff;
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    }
    .badge-pos-1 { top: 18%; right: 38%; }
    .badge-pos-2 { top: 12%; right: 8%; }
    .badge-pos-3 { top: 27%; right: 3%; }
    .badge-pos-4 { top: 40%; right: 4%; }

    /* Search Bar Layout */
    .hero-search-container {
        background: #ffffff;
        border-radius: 14px;
        padding: 6px;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.35);
        max-width: 680px;
    }
    .hero-search-container input,
    .hero-search-container select {
        font-size: 0.95rem;
    }

    /* Trust & Metrics Bar */
    .trust-bar {
        background: #ffffff;
        border-bottom: 1px solid #eef2f6;
    }
    .trust-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #eef5ff;
        color: #0d6efd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    /* Modern Category Image Cards */
    .category-card-modern {
        transition: transform 0.26s ease, box-shadow 0.26s ease, border-color 0.26s ease;
        background: #ffffff;
        border: 1px solid #eef2f6 !important;
        position: relative;
    }
    .category-card-modern:hover {
        transform: translateY(-7px);
        box-shadow: 0 16px 32px rgba(15, 33, 55, 0.12) !important;
        border-color: #cbd5e1 !important;
    }
    .category-img-container {
        position: relative;
        height: 145px;
        background-color: #0f2137;
        overflow: hidden;
    }
    .category-img-container img {
        height: 100%;
        width: 100%;
        object-fit: cover;
        transition: transform 0.35s ease;
    }
    .category-card-modern:hover .category-img-container img {
        transform: scale(1.08);
    }
    .category-icon-overlay {
        position: absolute;
        bottom: -18px;
        left: 20px;
        width: 46px;
        height: 46px;
        background: #ffffff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
        z-index: 2;
        transition: background-color 0.25s ease, color 0.25s ease;
    }
    .category-card-modern:hover .category-icon-overlay {
        background-color: var(--bs-primary, #0d6efd);
        color: #ffffff !important;
    }
    .category-card-modern:hover .category-icon-overlay i {
        color: #ffffff !important;
    }
    .tag-badge {
        font-size: 0.72rem;
        font-weight: 500;
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 3px 8px;
    }
    .cta-banner {
        background: linear-gradient(135deg, #09192f 0%, #102a4a 100%);
        border-radius: 24px;
        padding: 50px 40px;
    }
</style>

<!-- Hero Section -->
<section class="hero-section-exact py-5">
    
    <!-- Right Photo Layer (Full bleed right-aligned background using assets/images/hero couple.jpg) -->
    <div class="hero-bg-photo d-none d-lg-block"></div>

    <!-- Floating Badges Overlay -->
    <div class="floating-tech-badge badge-pos-1 d-none d-xl-flex">
        <i class="bi bi-code-slash text-info fs-6"></i>
        <span>Software Engineering</span>
    </div>
    <div class="floating-tech-badge badge-pos-2 d-none d-xl-flex">
        <i class="bi bi-cloud-arrow-up text-primary fs-6"></i>
        <span>Cloud Computing</span>
    </div>
    <div class="floating-tech-badge badge-pos-3 d-none d-xl-flex">
        <i class="bi bi-cpu text-info fs-6"></i>
        <span>AI &amp; Machine Learning</span>
    </div>
    <div class="floating-tech-badge badge-pos-4 d-none d-xl-flex">
        <i class="bi bi-database text-light fs-6"></i>
        <span>Data Science</span>
    </div>

    <!-- Left Content Column -->
    <div class="container position-relative" style="z-index: 2;">
        <div class="row">
            <div class="col-lg-7 text-start">
                
                <!-- Pill Tag -->
                <div class="mb-3">
                    <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 px-3 py-2 rounded-pill fw-semibold" style="font-size: 0.82rem;">
                        <i class="bi bi-rocket-takeoff me-1 text-warning"></i> Premier Tech Talent Marketplace
                    </span>
                </div>

                <!-- Main Heading -->
                <h1 class="display-5 fw-bold text-white mb-3 lh-sm" style="font-weight: 800;">
                    Empowering the Next<br>
                    Generation of <span style="color: #38bdf8;">Tech Careers</span>
                </h1>

                <!-- Subtitle -->
                <p class="text-white-50 mb-4 col-xl-10 fs-6 lh-base">
                    Connect with top-tier technology employers, discover remote and onsite tech vacancies, and elevate your career in software engineering, AI, cloud, and data science.
                </p>

                <!-- Unified Search Input Bar -->
                <div class="hero-search-container mb-4">
                    <form action="jobs.php" method="GET" class="row g-2 align-items-center m-0">
                        <!-- Keyword -->
                        <div class="col-md-5 ps-2">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-0 text-muted ps-1 pe-2">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="keyword" class="form-control border-0 shadow-none ps-0" placeholder="Job title, skills, or keyword..." aria-label="Job title">
                            </div>
                        </div>

                        <!-- Category Dropdown -->
                        <div class="col-md-4 border-start">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-0 text-muted ps-2 pe-2">
                                    <i class="bi bi-briefcase"></i>
                                </span>
                                <select name="category" class="form-select border-0 shadow-none ps-0 text-dark fw-medium" aria-label="Select Category">
                                    <option value="" selected>All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($cat['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-md-3 p-1">
                            <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-semibold shadow-sm d-flex align-items-center justify-content-center gap-1">
                                <i class="bi bi-search"></i> Search Jobs
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3 flex-wrap align-items-center">
                    <a href="#categories" class="btn btn-outline-light px-4 py-2.5 rounded-3 fw-semibold text-white border-opacity-50">
                        <i class="bi bi-compass me-1"></i> Explore Careers
                    </a>
                    <a href="../employer/post-job.php" class="btn btn-primary px-4 py-2.5 rounded-3 fw-semibold shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> Post a Vacancy
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Trust & Metrics Bar -->
<section class="trust-bar py-4">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-6 col-lg-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="trust-icon-box">
                        <i class="bi bi-buildings"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Top Companies</h6>
                        <small class="text-muted">Work with leading tech firms</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="trust-icon-box">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Verified Jobs</h6>
                        <small class="text-muted">100% genuine postings</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 border-start-lg">
                <div class="d-flex align-items-center gap-3">
                    <div class="trust-icon-box">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Career Growth</h6>
                        <small class="text-muted">Build skills &amp; advance</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="d-flex justify-content-between align-items-center ps-lg-2">
                    <div class="text-center">
                        <h5 class="fw-bold text-primary mb-0">500+</h5>
                        <small class="text-muted" style="font-size:0.75rem;">Job Vacancies</small>
                    </div>
                    <div class="text-center border-start ps-3">
                        <h5 class="fw-bold text-dark mb-0">200+</h5>
                        <small class="text-muted" style="font-size:0.75rem;">Employers</small>
                    </div>
                    <div class="text-center border-start ps-3">
                        <h5 class="fw-bold text-dark mb-0">10K+</h5>
                        <small class="text-muted" style="font-size:0.75rem;">Active Job Seekers</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 8 IT Career Categories Section with Fallback Protected Images -->
<section id="categories" class="py-5 bg-light">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill mb-2 fw-semibold">
                Specialized IT Disciplines
            </span>
            <h2 class="fw-bold text-dark mt-1">Explore 8 IT Career Categories</h2>
            <p class="text-muted col-lg-6 mx-auto">
                Tailored pathways designed specifically for technology professionals seeking high-impact engineering and IT leadership roles.
            </p>
        </div>

        <div class="row g-4">
            <?php foreach ($categories as $cat): ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card category-card-modern h-100 rounded-4 overflow-hidden shadow-sm">
                        
                        <!-- Header Image with Error Handler -->
                        <div class="category-img-container">
                            <img src="<?php echo htmlspecialchars($cat['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($cat['fallback'], ENT_QUOTES, 'UTF-8'); ?>';" 
                                 alt="<?php echo htmlspecialchars($cat['title'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="category-icon-overlay">
                                <i class="bi <?php echo htmlspecialchars($cat['icon'], ENT_QUOTES, 'UTF-8'); ?> text-primary"></i>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-4 pt-4 d-flex flex-column">
                            <h5 class="fw-bold text-dark mb-2 mt-2">
                                <?php echo htmlspecialchars($cat['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <p class="text-muted small flex-grow-1 mb-3">
                                <?php echo htmlspecialchars($cat['desc'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                            <!-- Skill Tags -->
                            <div class="d-flex flex-wrap gap-1 mt-auto">
                                <?php foreach ($cat['tags'] as $tag): ?>
                                    <span class="tag-badge"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <!-- Clickable Card Surface -->
                            <a href="jobs.php?category=<?php echo urlencode($cat['id']); ?>" class="stretched-link" aria-label="Explore <?php echo htmlspecialchars($cat['title'], ENT_QUOTES, 'UTF-8'); ?>"></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Call-to-Action Banner for Job Seekers and Employers -->
<section class="py-5">
    <div class="container">
        <div class="cta-banner text-white shadow-lg">
            <div class="row align-items-center g-4">
                <!-- Job Seekers Callout -->
                <div class="col-lg-6 border-end-lg border-white border-opacity-10 pe-lg-5">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-primary bg-opacity-25 p-3 rounded-circle text-white">
                            <i class="bi bi-person-workspace fs-2 text-primary-light"></i>
                        </div>
                        <div>
                            <span class="badge bg-primary text-white mb-1">For Job Seekers</span>
                            <h3 class="fw-bold text-white mb-0">Ready to Advance Your Tech Career?</h3>
                        </div>
                    </div>
                    <p class="text-white-50 mb-4">
                        Discover top opportunities at leading tech enterprises and high-growth startups. Build your tech profile, upload your CV, and apply seamlessly with one click.
                    </p>
                    <a href="../auth/register.php?role=job_seeker" class="btn btn-primary px-4 py-2.5 rounded-3 fw-semibold shadow-sm">
                        <i class="bi bi-rocket-takeoff me-1"></i> Join as Job Seeker
                    </a>
                </div>

                <!-- Employers Callout -->
                <div class="col-lg-6 ps-lg-5">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-primary bg-opacity-25 p-3 rounded-circle text-white">
                            <i class="bi bi-building-gear fs-2 text-warning"></i>
                        </div>
                        <div>
                            <span class="badge bg-warning text-dark mb-1">For Employers</span>
                            <h3 class="fw-bold text-white mb-0">Hiring Exceptional Tech Talent?</h3>
                        </div>
                    </div>
                    <p class="text-white-50 mb-4">
                        Access a vetted pool of software engineers, AI specialists, data scientists, and IT leaders. Post job vacancies and manage your talent pipeline effortlessly.
                    </p>
                    <a href="../auth/register.php?role=employer" class="btn btn-outline-light px-4 py-2.5 rounded-3 fw-semibold">
                        <i class="bi bi-person-plus me-1"></i> Hire Tech Talent
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>