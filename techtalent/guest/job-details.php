<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../ai/skill-gap-analyzer.php';
require_once __DIR__ . '/../includes/cv-functions.php';

// Validate Job ID from GET parameter
$job_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$job_id || $job_id <= 0) {
    header("Location: jobs.php");
    exit();
}

// Fetch Job details
$job = null;
try {
    $stmt = $pdo->prepare("
        SELECT j.*, c.category_name, c.icon_class 
        FROM jobs j 
        JOIN career_categories c ON j.category_id = c.category_id 
        WHERE j.job_id = :id AND j.status = 'Published' 
        LIMIT 1
    ");
    $stmt->execute([':id' => $job_id]);
    $job = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Job Details Fetch Error: " . $e->getMessage());
}

if (!$job) {
    header("Location: jobs.php");
    exit();
}

$is_job_seeker = isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true);
$user_id = $_SESSION['user_id'] ?? null;
// Fetch candidate skills for AI analysis if logged in as job seeker
$candidate_skills = [];
if ($is_job_seeker && $user_id) {
    $stmt_usk = $pdo->prepare('SELECT s.skill_name FROM user_skills us JOIN skills s ON us.skill_id = s.skill_id WHERE us.user_id = :uid');
    $stmt_usk->execute([':uid' => $user_id]);
    $candidate_skills = $stmt_usk->fetchAll(PDO::FETCH_COLUMN);
}

// Check if candidate already applied
$has_applied = false;
$existing_status = '';
if ($is_job_seeker && $user_id) {
    try {
        $stmt_check = $pdo->prepare("SELECT current_status FROM applications WHERE job_id = :jid AND job_seeker_id = :uid LIMIT 1");
        $stmt_check->execute([':jid' => $job_id, ':uid' => $user_id]);
        $existing = $stmt_check->fetch();
        if ($existing) {
            $has_applied = true;
            $existing_status = $existing['current_status'];
        }
    } catch (PDOException $e) {
        error_log("Check Application Error: " . $e->getMessage());
    }
}

// Saved CV Studio CVs the candidate can attach
$user_cvs = [];
if ($is_job_seeker && $user_id) {
    $user_cvs = cv_list_for_user($pdo, (int)$user_id);
}
$csrf_token = cv_csrf_token();

// POST Application Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    if (!$is_job_seeker || !$user_id) {
        header("Location: ../auth/login.php");
        exit();
    }
    if (!cv_csrf_valid($_POST['csrf_token'] ?? null)) {
        header("Location: job-details.php?id={$job_id}&alert=csrf");
        exit();
    }

    $posted_job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    if ($posted_job_id && $posted_job_id === (int)$job['job_id']) {
        if ($has_applied) {
            header("Location: job-details.php?id={$job_id}&alert=already_applied");
            exit();
        }

        // Optional CV: must belong to the applicant.
        $selected_cv = null;
        $posted_cv_id = (int)($_POST['cv_id'] ?? 0);
        if ($posted_cv_id > 0) {
            $selected_cv = cv_fetch_owned($pdo, $posted_cv_id, (int)$user_id);
            if (!$selected_cv) {
                header("Location: job-details.php?id={$job_id}&alert=cv_invalid");
                exit();
            }
        }

        try {
            $pdo->beginTransaction();
            // Calculate initial match score
            $req_skills_str = $job['required_skills'] ?? '';
            $req_skills = array_filter(array_map('trim', explode(',', strtolower($req_skills_str))));
            
            $match_score = 70.00; // Default baseline score
            if (!empty($req_skills)) {
                $stmt_uskills = $pdo->prepare("
                    SELECT LOWER(s.skill_name) 
                    FROM user_skills us 
                    JOIN skills s ON us.skill_id = s.skill_id 
                    WHERE us.user_id = :uid
                ");
                $stmt_uskills->execute([':uid' => $user_id]);
                $user_skill_names = $stmt_uskills->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($user_skill_names)) {
                    $matched_count = 0;
                    foreach ($req_skills as $req) {
                        foreach ($user_skill_names as $uskill) {
                            if (str_contains($uskill, $req) || str_contains($req, $uskill)) {
                                $matched_count++;
                                break;
                            }
                        }
                    }
                    $calc_score = round(($matched_count / count($req_skills)) * 100, 2);
                    $match_score = max(50.00, min(100.00, $calc_score));
                }
            }

            // Insert application
            $stmt_app = $pdo->prepare("
                INSERT INTO applications (job_id, job_seeker_id, match_score, current_status) 
                VALUES (:jid, :uid, :score, 'Applied')
            ");
            $stmt_app->execute([
                ':jid' => $job_id,
                ':uid' => $user_id,
                ':score' => $match_score
            ]);
            $new_app_id = (int)$pdo->lastInsertId();

            // Insert initial status history log
            $stmt_hist = $pdo->prepare("
                INSERT INTO application_status_history (application_id, status, updated_by, notes) 
                VALUES (:appid, 'Applied', :uid, 'Application submitted via candidate portal')
            ");
            $stmt_hist->execute([
                ':appid' => $new_app_id,
                ':uid' => $user_id
            ]);

            // Freeze the chosen CV so later edits never change what the employer received.
            if ($selected_cv) {
                cv_snapshot_create($pdo, $new_app_id, $selected_cv);
            }
            $pdo->commit();

            header("Location: ../jobseeker/applications.php?success=applied");
            exit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Submit Application Error: " . $e->getMessage());
            header("Location: job-details.php?id={$job_id}&alert=apply_failed");
            exit();
        }
    }
}

$page_title = htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8') . " at " . htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8') . " | TechTalent";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$skills_list = array_filter(array_map('trim', explode(',', $job['required_skills'])));

$work_badge_class = 'bg-secondary bg-opacity-10 text-secondary';
if ($job['work_arrangement'] === 'Remote') {
    $work_badge_class = 'bg-success bg-opacity-10 text-success';
} elseif ($job['work_arrangement'] === 'Hybrid') {
    $work_badge_class = 'bg-info bg-opacity-10 text-info';
}

$alert_param = $_GET['alert'] ?? '';
?>

<div class="container py-4">
    <!-- Alert notifications -->
    <?php if ($alert_param === 'already_applied'): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            You have already submitted an application for this vacancy. Check your <a href="../jobseeker/applications.php" class="alert-link">Applications Tracker</a> for status updates.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (in_array($alert_param, ['csrf', 'cv_invalid', 'apply_failed'], true)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <?php
            echo [
                'csrf' => 'Your session token expired. Please submit the application again.',
                'cv_invalid' => 'The selected CV could not be found. Please choose another CV.',
                'apply_failed' => 'Your application could not be submitted. Please try again.',
            ][$alert_param];
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Back to Vacancies Link -->
    <div class="mb-4">
        <a href="jobs.php" class="btn btn-outline-navy btn-sm px-3 py-2 rounded-3 text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Back to All Vacancies
        </a>
    </div>

    <div class="row g-4">
        <!-- Main Job Details Column -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4">
                <!-- Job Header -->
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3 border-bottom pb-4">
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill fw-semibold mb-2">
                            <i class="bi <?php echo htmlspecialchars($job['icon_class'] ?? 'bi-briefcase', ENT_QUOTES, 'UTF-8'); ?> me-1"></i>
                            <?php echo htmlspecialchars($job['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <h2 class="fw-bold text-navy mb-2"><?php echo htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="d-flex align-items-center gap-3 flex-wrap text-muted">
                            <span class="fw-semibold text-dark fs-6">
                                <i class="bi bi-building me-1 text-primary"></i><?php echo htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span>•</span>
                            <span><i class="bi bi-geo-alt me-1 text-danger"></i><?php echo htmlspecialchars($job['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>•</span>
                            <span><i class="bi bi-calendar3 me-1 text-muted"></i>Posted <?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Specs Grid -->
                <div class="row g-3 my-3">
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center h-100">
                            <i class="bi bi-laptop fs-4 text-primary mb-1 d-block"></i>
                            <span class="text-muted small d-block">Work Mode</span>
                            <span class="fw-bold text-navy small"><?php echo htmlspecialchars($job['work_arrangement'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center h-100">
                            <i class="bi bi-clock-history fs-4 text-primary mb-1 d-block"></i>
                            <span class="text-muted small d-block">Job Type</span>
                            <span class="fw-bold text-navy small"><?php echo htmlspecialchars($job['job_type'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center h-100">
                            <i class="bi bi-bar-chart-steps fs-4 text-primary mb-1 d-block"></i>
                            <span class="text-muted small d-block">Experience</span>
                            <span class="fw-bold text-navy small"><?php echo htmlspecialchars($job['experience_level'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 text-center h-100">
                            <i class="bi bi-cash-stack fs-4 text-success mb-1 d-block"></i>
                            <span class="text-muted small d-block">Salary</span>
                            <span class="fw-bold text-navy small"><?php echo htmlspecialchars($job['salary_range'] ?? 'Negotiable', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Job Description -->
                <div class="my-4">
                    <h4 class="fw-bold text-navy mb-3">Job Description</h4>
                    <div class="text-dark lh-lg fs-6">
                        <?php echo nl2br(htmlspecialchars($job['description'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
                <!-- AI Competency Analysis Card -->
                <?php if ($is_job_seeker && !empty($candidate_skills) && !empty($job['required_skills'])): ?>
                    <?php
                        $match = calculate_job_match($candidate_skills, $job['required_skills']);
                        $score = $match['score'];
                        $matched = $match['matched'];
                        $gaps = $match['gaps'];
                        $recommendations = generate_recommendations($gaps);
                    ?>
                    <div class="my-4 border-top pt-4">
                        <h5 class="fw-bold text-navy mb-3">AI Match Analysis</h5>
                        <p>Match Score: <span class="badge bg-success"><?php echo htmlspecialchars($score, ENT_QUOTES, 'UTF-8'); ?>%</span></p>
                        <?php if (!empty($matched)): ?>
                            <p>Matched Skills:</p>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($matched as $skill): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold"><?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($gaps)): ?>
                            <p>Skill Gaps:</p>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($gaps as $gap): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger fw-semibold"><?php echo htmlspecialchars($gap, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($recommendations)): ?>
                            <p>Recommended Learning Resources:</p>
                            <ul class="list-unstyled">
                                <?php foreach ($recommendations as $rec): ?>
                                    <li><a href="<?php echo htmlspecialchars($rec['link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-decoration-none"><i class="bi bi-link-45deg me-1"></i><?php echo htmlspecialchars($rec['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Required Skills / Competencies -->
                <?php if (!empty($skills_list)): ?>
                    <div class="my-4 border-top pt-4">
                        <h5 class="fw-bold text-navy mb-3">Required Technical Competencies</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($skills_list as $sk): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary fs-6 px-3 py-2 rounded-3 border border-primary border-opacity-25">
                                    <i class="bi bi-check2-circle me-1"></i><?php echo htmlspecialchars($sk, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $optional_list = split_skill_list($job['optional_skills'] ?? ''); ?>
                <?php if (!empty($optional_list)): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-navy mb-2">Nice to Have</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($optional_list as $sk): ?>
                                <span class="badge bg-light text-dark fs-6 px-3 py-2 rounded-3 border"><?php echo htmlspecialchars($sk, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Application Sidebar -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 90px; z-index: 100;">
                <div class="card-body p-1 text-center">
                    <div class="mb-3">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-inline-block text-primary mb-2 fs-2">
                            <i class="bi bi-send-fill"></i>
                        </div>
                        <h4 class="fw-bold text-navy mb-1">Apply for Position</h4>
                        <p class="text-muted small">Submit your candidate profile directly to <?php echo htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="border-top border-bottom py-3 my-3 text-start">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Company:</span>
                            <span class="fw-semibold text-dark small"><?php echo htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Location:</span>
                            <span class="fw-semibold text-dark small"><?php echo htmlspecialchars($job['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Work Mode:</span>
                            <span class="badge <?php echo $work_badge_class; ?>"><?php echo htmlspecialchars($job['work_arrangement'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>

                    <?php if ($is_job_seeker): ?>
                        <?php if ($has_applied): ?>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-secondary btn-lg fw-bold rounded-3 py-2.5 fs-6" disabled>
                                    <i class="bi bi-check-circle-fill me-1"></i> Applied (<?php echo htmlspecialchars($existing_status, ENT_QUOTES, 'UTF-8'); ?>)
                                </button>
                                <a href="../jobseeker/applications.php" class="btn btn-outline-primary btn-sm rounded-3 py-2 mt-1">
                                    <i class="bi bi-journal-text me-1"></i> View in Application Tracker
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-accent btn-lg fw-bold rounded-3 py-2.5 fs-6" data-bs-toggle="modal" data-bs-target="#applyModal">
                                    <i class="bi bi-rocket-takeoff me-1"></i> Apply for this Position
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-light p-3 rounded-3 text-start mb-3 border">
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle text-primary me-1"></i>
                                Ready to apply? Sign in or create a Job Seeker account to submit your candidate profile to this employer.
                            </p>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="../auth/login.php" class="btn btn-accent fw-bold py-2 rounded-3">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Apply
                            </a>
                            <a href="../auth/register.php?role=job_seeker" class="btn btn-outline-navy fw-semibold py-2 rounded-3 text-navy">
                                <i class="bi bi-person-plus me-1"></i> Create Job Seeker Account
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Apply Confirmation Modal for Logged In Job Seekers -->
<?php if ($is_job_seeker && !$has_applied): ?>
<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header text-white rounded-top-4" style="background-color: #0f2137;">
                <h5 class="modal-title fw-bold" id="applyModalLabel">
                    <i class="bi bi-file-earmark-check text-primary me-2"></i> Submit Job Application
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="job-details.php?id=<?php echo (int)$job['job_id']; ?>" method="POST">
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="job_id" value="<?php echo (int)$job['job_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-body p-4 text-center">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success d-inline-block mb-3 fs-2">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h4 class="fw-bold text-navy mb-2"><?php echo htmlspecialchars($job['job_title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($job['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="text-start mb-3">
                        <label for="applyCv" class="form-label fw-semibold small mb-1"><i class="bi bi-file-earmark-person me-1"></i>CV to submit</label>
                        <?php if (!empty($user_cvs)): ?>
                            <select name="cv_id" id="applyCv" class="form-select" onchange="var l=document.getElementById('applyGapLink'); l.classList.toggle('d-none', this.value==='0'); l.href=l.href.replace(/id=\d+/, 'id=' + this.value);">
                                <?php foreach ($user_cvs as $i => $ucv): ?>
                                    <option value="<?php echo (int)$ucv['cv_id']; ?>" <?php echo $i === 0 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ucv['title'], ENT_QUOTES, 'UTF-8'); ?> (saved <?php echo htmlspecialchars(date('d M Y', strtotime($ucv['updated_at'])), ENT_QUOTES, 'UTF-8'); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="0">Don't attach a CV (profile only)</option>
                            </select>
                            <div class="form-text">A read-only copy of the selected CV is stored with this application. Later edits will not change it.
                                <a href="../jobseeker/cv-editor.php?id=<?php echo (int)$user_cvs[0]['cv_id']; ?>&amp;job=<?php echo (int)$job['job_id']; ?>" target="_blank" id="applyGapLink">Check skill match first</a></div>
                        <?php else: ?>
                            <input type="hidden" name="cv_id" value="0">
                            <div class="small text-muted">You have no saved CVs. <a href="../jobseeker/cv-studio.php" target="_blank">Create one in CV Studio</a>, or apply with your profile only.</div>
                        <?php endif; ?>
                    </div>
                    <div class="alert alert-info small text-start border-0 rounded-3 mb-0">
                        <i class="bi bi-info-circle-fill me-1"></i> Your profile headline, contact details, and tech stack competencies will be submitted directly to the hiring team.
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4 border-0">
                    <button type="button" class="btn btn-secondary px-3 py-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent px-4 py-2 fw-bold">
                        <i class="bi bi-send me-1"></i> Confirm & Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
