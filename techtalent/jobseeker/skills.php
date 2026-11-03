<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['job_seeker', 'jobseeker'], true)) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];

// POST Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $skill_id = filter_input(INPUT_POST, 'skill_id', FILTER_VALIDATE_INT);
        $proficiency_level = trim($_POST['proficiency_level'] ?? '');

        if (!$skill_id || $skill_id <= 0) {
            $errors[] = "Please select a valid skill from the pool.";
        }

        if (!in_array($proficiency_level, ['Beginner', 'Intermediate', 'Advanced', 'Expert'], true)) {
            $errors[] = "Please select a valid proficiency level.";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO user_skills (user_id, skill_id, proficiency_level) 
                    VALUES (:uid, :sid, :level) 
                    ON DUPLICATE KEY UPDATE proficiency_level = VALUES(proficiency_level)
                ");
                $stmt->execute([
                    ':uid' => $user_id,
                    ':sid' => $skill_id,
                    ':level' => $proficiency_level
                ]);
                header("Location: skills.php?msg=added");
                exit();
            } catch (PDOException $e) {
                error_log("Add Skill Error: " . $e->getMessage());
                $errors[] = "Failed to add skill. Please try again.";
            }
        }
    } elseif ($action === 'delete') {
        $user_skill_id = filter_input(INPUT_POST, 'user_skill_id', FILTER_VALIDATE_INT);
        if ($user_skill_id && $user_skill_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM user_skills WHERE user_skill_id = :id AND user_id = :uid");
                $stmt->execute([':id' => $user_skill_id, ':uid' => $user_id]);
                header("Location: skills.php?msg=deleted");
                exit();
            } catch (PDOException $e) {
                error_log("Delete Skill Error: " . $e->getMessage());
                $errors[] = "Failed to delete skill.";
            }
        }
    }
}

// Fetch all master skills grouped by category
$master_skills = [];
try {
    $stmt = $pdo->query("
        SELECT s.skill_id, s.skill_name, c.category_name 
        FROM skills s 
        LEFT JOIN career_categories c ON s.category_id = c.category_id 
        ORDER BY c.category_name ASC, s.skill_name ASC
    ");
    $master_skills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Master Skills Error: " . $e->getMessage());
}

// Fetch user's active tech stack skills
$user_skills = [];
try {
    $stmt = $pdo->prepare("
        SELECT us.user_skill_id, us.proficiency_level, s.skill_name, c.category_name 
        FROM user_skills us 
        JOIN skills s ON us.skill_id = s.skill_id 
        LEFT JOIN career_categories c ON s.category_id = c.category_id 
        WHERE us.user_id = :uid 
        ORDER BY s.skill_name ASC
    ");
    $stmt->execute([':uid' => $user_id]);
    $user_skills = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch User Skills Error: " . $e->getMessage());
}

$page_title = 'Technical Skills - TechTalent Solutions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$msg = $_GET['msg'] ?? '';
?>

<div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-navy mb-1"><i class="bi bi-tools text-primary me-2"></i>Technical Skills & Competencies</h2>
            <p class="text-muted mb-0">Build your tech stack portfolio to boost job match scores and visibility with employers.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-navy btn-sm px-3 py-2 rounded-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($msg === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Skill updated successfully in your tech stack.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-info alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> Skill removed from your tech stack.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column: Add Skill Card -->
        <div class="col-lg-5 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-2.5 rounded-3 fs-4">
                        <i class="bi bi-plus-circle-fill"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-navy mb-0">Add Skill</h4>
                        <span class="text-muted small">Select from the master pool</span>
                    </div>
                </div>

                <form action="skills.php" method="POST" class="mt-2">
                    <input type="hidden" name="action" value="add">
                    
                    <!-- Skill Select -->
                    <div class="mb-3">
                        <label for="skill_id" class="form-label fw-semibold text-dark small">Select Technology / Skill</label>
                        <select class="form-select py-2.5" id="skill_id" name="skill_id" required>
                            <option value="">-- Choose Skill --</option>
                            <?php 
                            $current_cat = '';
                            foreach ($master_skills as $ms):
                                $cat_label = $ms['category_name'] ?? 'General IT';
                                if ($current_cat !== $cat_label):
                                    if ($current_cat !== '') echo '</optgroup>';
                                    $current_cat = $cat_label;
                                    echo '<optgroup label="' . htmlspecialchars($current_cat, ENT_QUOTES, 'UTF-8') . '">';
                                endif;
                            ?>
                                <option value="<?php echo (int)$ms['skill_id']; ?>">
                                    <?php echo htmlspecialchars($ms['skill_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($current_cat !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>

                    <!-- Proficiency Level Select -->
                    <div class="mb-4">
                        <label for="proficiency_level" class="form-label fw-semibold text-dark small">Proficiency Level</label>
                        <select class="form-select py-2.5" id="proficiency_level" name="proficiency_level" required>
                            <option value="Beginner">Beginner (Basic understanding)</option>
                            <option value="Intermediate" selected>Intermediate (1-2 years hands-on)</option>
                            <option value="Advanced">Advanced (3+ years experience)</option>
                            <option value="Expert">Expert (Subject matter expert / Lead)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-accent w-100 py-2.5 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-plus-lg"></i> Save Skill to Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: My Tech Stack List -->
        <div class="col-lg-7 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-navy mb-0">My Tech Stack</h4>
                        <span class="text-muted small">Added skills used for candidate-job matching</span>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1.5 rounded-pill fw-bold fs-6">
                        <?php echo count($user_skills); ?> Skills
                    </span>
                </div>

                <?php if (!empty($user_skills)): ?>
                    <div class="row g-3">
                        <?php foreach ($user_skills as $us): ?>
                            <?php
                                $badge_bg = 'bg-secondary';
                                if ($us['proficiency_level'] === 'Beginner') $badge_bg = 'bg-info bg-opacity-25 text-info border border-info border-opacity-50';
                                elseif ($us['proficiency_level'] === 'Intermediate') $badge_bg = 'bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50';
                                elseif ($us['proficiency_level'] === 'Advanced') $badge_bg = 'bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50';
                                elseif ($us['proficiency_level'] === 'Expert') $badge_bg = 'bg-success bg-opacity-25 text-success border border-success border-opacity-50';
                            ?>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between border">
                                    <div>
                                        <h5 class="fw-bold text-navy mb-1"><?php echo htmlspecialchars($us['skill_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                        <span class="text-muted small"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($us['category_name'] ?? 'IT Skill', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="badge <?php echo $badge_bg; ?> px-3 py-1.5 rounded-pill fw-semibold">
                                            <?php echo htmlspecialchars($us['proficiency_level'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <form action="skills.php" method="POST" class="d-inline" onsubmit="return confirm('Remove <?php echo htmlspecialchars($us['skill_name'], ENT_QUOTES, 'UTF-8'); ?> from your tech stack?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_skill_id" value="<?php echo (int)$us['user_skill_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2 rounded-2" title="Remove Skill">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-code-square display-3 opacity-25 d-block mb-3"></i>
                        <h5 class="fw-bold text-navy">No Technical Skills Added Yet</h5>
                        <p class="small text-muted col-lg-8 mx-auto mb-0">Use the form on the left to select your key programming languages, frameworks, and tools to boost your job match score.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
