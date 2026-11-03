<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Fetch all career categories for dropdown & top pills
$db_categories = [];
try {
    $stmt_cats = $pdo->query("SELECT category_id, category_name, slug, icon_class FROM career_categories ORDER BY category_id ASC");
    $db_categories = $stmt_cats->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch categories: " . $e->getMessage());
}

// Retrieve filter inputs safely
$keyword = trim($_GET['keyword'] ?? '');
$category_filter = trim($_GET['category'] ?? ($_GET['cat'] ?? ''));
$work_mode = trim($_GET['work_mode'] ?? '');
$experience = trim($_GET['experience'] ?? '');

// Build Dynamic SQL Query
$sql = "SELECT j.*, c.category_name, c.slug as category_slug 
        FROM jobs j 
        JOIN career_categories c ON j.category_id = c.category_id 
        WHERE j.status = 'Published'";

$params = [];

// Filter by Keyword
if ($keyword !== '') {
    $sql .= " AND (j.job_title LIKE :kw1 OR j.required_skills LIKE :kw2 OR j.company_name LIKE :kw3 OR j.description LIKE :kw4)";
    $kw_param = '%' . $keyword . '%';
    $params[':kw1'] = $kw_param;
    $params[':kw2'] = $kw_param;
    $params[':kw3'] = $kw_param;
    $params[':kw4'] = $kw_param;
}

// Filter by Category
if ($category_filter !== '') {
    if (is_numeric($category_filter)) {
        $sql .= " AND j.category_id = :cat_id";
        $params[':cat_id'] = (int)$category_filter;
    } else {
        $sql .= " AND (c.slug = :cat_slug OR c.slug LIKE :cat_slug_like)";
        $params[':cat_slug'] = $category_filter;
        $params[':cat_slug_like'] = $category_filter . '%';
    }
}

// Filter by Work Arrangement
if (!empty($work_mode) && in_array($work_mode, ['Remote', 'Hybrid', 'On-site'], true)) {
    $sql .= " AND j.work_arrangement = :work_mode";
    $params[':work_mode'] = $work_mode;
}

// Filter by Experience Level
if (!empty($experience) && in_array($experience, ['Internship', 'Entry Level', 'Junior', 'Mid-Level', 'Senior'], true)) {
    $sql .= " AND j.experience_level = :experience";
    $params[':experience'] = $experience;
}

$sql .= " ORDER BY j.created_at DESC";

// Execute Query
$jobs = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Jobs Filter Query Error: " . $e->getMessage());
}

$page_title = 'Explore IT Vacancies - TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
    .job-card-modern {
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        border: 1px solid #eef2f6 !important;
    }
    .job-card-modern:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(15, 33, 55, 0.08) !important;
        border-color: #cbd5e1 !important;
    }
    .company-avatar {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.25rem;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.2);
        flex-shrink: 0;
    }
    .cat-pill-scroll {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 6px;
        scrollbar-width: thin;
    }
    .cat-pill-scroll::-webkit-scrollbar {
        height: 4px;
    }
    .cat-pill-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
</style>

<!-- Header Banner -->
<section class="text-white py-4 mb-4" style="background: linear-gradient(135deg, #0f2137 0%, #1a365d 100%);">
    <div class="container text-center py-2">
        <span class="badge bg-primary bg-opacity-25 text-primary-light border border-primary border-opacity-25 rounded-pill px-3 py-1 mb-2 fw-semibold">
            Verified IT Openings
        </span>
        <h1 class="fw-bold mb-2">Browse IT Career Opportunities</h1>
        <p class="text-white-50 mb-0 col-lg-7 mx-auto fs-6">
            Discover verified software engineering, AI, data science, cybersecurity, and cloud vacancies from leading companies.
        </p>
    </div>
</section>

<div class="container pb-5">
    <div class="row g-4">
        <!-- Left Filter Sidebar -->
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 sticky-top" style="top: 90px; z-index: 100;">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-funnel-fill text-primary me-1"></i> Filter Jobs
                        </h5>
                        <?php if ($keyword !== '' || $category_filter !== '' || $work_mode !== '' || $experience !== ''): ?>
                            <a href="jobs.php" class="text-danger small text-decoration-none fw-semibold">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </a>
                        <?php endif; ?>
                    </div>

                    <form action="jobs.php" method="GET">
                        <!-- Keyword Search -->
                        <div class="mb-3">
                            <label for="keyword" class="form-label fw-semibold text-dark small">Search Keyword</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 py-2" id="keyword" name="keyword" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Title, skill, company...">
                            </div>
                        </div>

                        <!-- Category Dropdown -->
                        <div class="mb-3">
                            <label for="category" class="form-label fw-semibold text-dark small">Career Category</label>
                            <select class="form-select py-2" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($db_categories as $cat): ?>
                                    <?php 
                                        $selected = ($category_filter == $cat['category_id'] || $category_filter === $cat['slug'] || str_starts_with($cat['slug'], $category_filter)) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($cat['category_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Work Arrangement Filter -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small d-block">Work Arrangement</label>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="work_mode" id="work_all" value="" <?php echo empty($work_mode) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="work_all">All Arrangements</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="work_mode" id="work_remote" value="Remote" <?php echo $work_mode === 'Remote' ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="work_remote">
                                    <span class="badge bg-success bg-opacity-10 text-success me-1">Remote</span>
                                </label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="work_mode" id="work_hybrid" value="Hybrid" <?php echo $work_mode === 'Hybrid' ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="work_hybrid">
                                    <span class="badge bg-info bg-opacity-10 text-info me-1">Hybrid</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="work_mode" id="work_onsite" value="On-site" <?php echo $work_mode === 'On-site' ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="work_onsite">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary me-1">On-site</span>
                                </label>
                            </div>
                        </div>

                        <!-- Experience Level Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark small d-block">Experience Level</label>
                            <select class="form-select py-2" name="experience" id="experience">
                                <option value="">All Experience Levels</option>
                                <?php 
                                $levels = ['Internship', 'Entry Level', 'Junior', 'Mid-Level', 'Senior'];
                                foreach ($levels as $lvl):
                                    $lvl_sel = ($experience === $lvl) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($lvl, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lvl_sel; ?>>
                                        <?php echo htmlspecialchars($lvl, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Submit and Clear Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-semibold py-2 rounded-3 shadow-sm">
                                <i class="bi bi-funnel me-1"></i> Apply Filters
                            </button>
                            <a href="jobs.php" class="btn btn-outline-secondary py-2 small rounded-3">
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Results Column -->
        <div class="col-lg-9 col-md-8">
            
            <!-- Quick Category Badges Bar -->
            <div class="cat-pill-scroll mb-3">
                <?php 
                    $active_all = empty($category_filter) ? 'bg-primary text-white shadow-sm' : 'bg-white text-secondary border';
                ?>
                <a href="jobs.php?keyword=<?php echo urlencode($keyword); ?>&work_mode=<?php echo urlencode($work_mode); ?>&experience=<?php echo urlencode($experience); ?>" 
                   class="badge text-decoration-none px-3 py-2 rounded-pill <?php echo $active_all; ?> fw-medium">
                    All Categories
                </a>
                <?php foreach ($db_categories as $c): ?>
                    <?php 
                        $is_c_active = ($category_filter == $c['category_id'] || $category_filter === $c['slug']);
                        $pill_class = $is_c_active ? 'bg-primary text-white shadow-sm' : 'bg-white text-secondary border';
                    ?>
                    <a href="jobs.php?category=<?php echo urlencode($c['category_id']); ?>&keyword=<?php echo urlencode($keyword); ?>&work_mode=<?php echo urlencode($work_mode); ?>&experience=<?php echo urlencode($experience); ?>" 
                       class="badge text-decoration-none px-3 py-2 rounded-pill <?php echo $pill_class; ?> fw-medium text-nowrap">
                        <?php echo htmlspecialchars($c['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Results Bar -->
            <div class="d-flex align-items-center justify-content-between mb-3 bg-white p-3 rounded-4 shadow-sm border border-light">
                <span class="fw-bold text-dark fs-6">
                    <i class="bi bi-briefcase-fill text-primary me-2"></i>
                    Found <span class="text-primary"><?php echo count($jobs); ?></span> active <?php echo count($jobs) === 1 ? 'vacancy' : 'vacancies'; ?>
                </span>
                <span class="text-muted small">Sorted by Latest</span>
            </div>

            <!-- Job Cards Grid -->
            <?php if (!empty($jobs)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($jobs as $job): ?>
                        <?php
                            $skills = array_filter(array_map('trim', explode(',', $job['required_skills'] ?? '')));
                            $company_name = $job['company_name'] ?? 'Tech Enterprise';
                            $company_initial = strtoupper(substr($company_name, 0, 1));
                            
                            $work_badge_class = 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10';
                            if ($job['work_arrangement'] === 'Remote') {
                                $work_badge_class = 'bg-success bg-opacity-10 text-success border border-success border-opacity-10';
                            } elseif ($job['work_arrangement'] === 'Hybrid') {
                                $work_badge_class = 'bg-info bg-opacity-10 text-info border border-info border-opacity-10';
                            }
                        ?>
                        <div class="card job-card-modern shadow-sm rounded-4 p-3 bg-white">
                            <div class="card-body p-2">
                                <div class="d-flex gap-3 align-items-start">
                                    
                                    <!-- Company Avatar -->
                                    <div class="company-avatar">
                                        <?php echo $company_initial; ?>
                                    </div>

                                    <!-- Main Job Content Area -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                                            <div>
                                                <h5 class="fw-bold text-dark mb-1">
                                                    <a href="job-details.php?id=<?php echo (int)$job['job_id']; ?>" class="text-dark text-decoration-none hover-primary">
                                                        <?php echo htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </h5>
                                                <div class="d-flex align-items-center gap-2 flex-wrap text-muted small">
                                                    <span class="fw-semibold text-dark"><i class="bi bi-building me-1 text-primary"></i><?php echo htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span>•</span>
                                                    <span><i class="bi bi-geo-alt me-1 text-danger"></i><?php echo htmlspecialchars($job['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-semibold small">
                                                    <?php echo htmlspecialchars($job['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Badges Row -->
                                        <div class="d-flex flex-wrap gap-2 my-2.5">
                                            <span class="badge <?php echo $work_badge_class; ?> px-2.5 py-1.5 rounded-2">
                                                <i class="bi bi-laptop me-1"></i><?php echo htmlspecialchars($job['work_arrangement'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-2">
                                                <i class="bi bi-clock me-1 text-muted"></i><?php echo htmlspecialchars($job['job_type'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-2">
                                                <i class="bi bi-bar-chart me-1 text-muted"></i><?php echo htmlspecialchars($job['experience_level'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>

                                        <!-- Required Skills Pills -->
                                        <?php if (!empty($skills)): ?>
                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                <?php 
                                                    $display_skills = array_slice($skills, 0, 4);
                                                    $remaining = count($skills) - 4;
                                                    foreach ($display_skills as $skill): 
                                                ?>
                                                    <span class="badge bg-light text-secondary border px-2 py-1 font-monospace small">
                                                        <?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if ($remaining > 0): ?>
                                                    <span class="badge bg-light text-muted border px-2 py-1 small">
                                                        +<?php echo $remaining; ?> more
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Bottom Meta / Action Row -->
                                        <div class="d-flex align-items-center justify-content-between pt-2 border-top mt-2">
                                            <div class="text-dark fw-semibold small">
                                                <i class="bi bi-cash-stack text-success me-1 fs-6"></i>
                                                <?php echo htmlspecialchars($job['salary_range'] ?? 'Negotiable', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <a href="job-details.php?id=<?php echo (int)$job['job_id']; ?>" class="btn btn-outline-primary btn-sm px-3 py-1.5 rounded-3 fw-semibold">
                                                View Details <i class="bi bi-arrow-right ms-1"></i>
                                            </a>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="my-4">
                        <i class="bi bi-search text-muted opacity-50 display-1"></i>
                        <h4 class="fw-bold text-dark mt-3 mb-2">No Vacancies Found</h4>
                        <p class="text-muted col-lg-6 mx-auto mb-4">
                            We couldn't find any active job postings matching your filter criteria. Try adjusting your keyword search or resetting filters.
                        </p>
                        <a href="jobs.php" class="btn btn-primary px-4 py-2 fw-semibold rounded-3 shadow-sm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>