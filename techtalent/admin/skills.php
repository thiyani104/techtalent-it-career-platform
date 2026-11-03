
<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// --------------------------------------------------
// Admin-only access
// --------------------------------------------------
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../auth/login.php');
    exit();
}

$page_title = 'Manage Skills - TechTalent';

if (empty($_SESSION['skills_csrf_token'])) {
    $_SESSION['skills_csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['skills_csrf_token'];
$errors = [];
$success = '';
$edit_skill = null;

// --------------------------------------------------
// Handle add, update and delete
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($posted_token) ||
        !hash_equals($csrf_token, $posted_token)
    ) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add' || $action === 'update') {
                $skill_name = trim($_POST['skill_name'] ?? '');
                $category_input = $_POST['category_id'] ?? '';
                $category_id = null;

                if ($skill_name === '') {
                    $errors[] = 'Please enter a skill name.';
                } elseif (mb_strlen($skill_name) > 100) {
                    $errors[] = 'The skill name must be 100 characters or fewer.';
                }

                if ($category_input !== '') {
                    $validated_category = filter_var(
                        $category_input,
                        FILTER_VALIDATE_INT
                    );

                    if ($validated_category === false || $validated_category <= 0) {
                        $errors[] = 'Please select a valid category.';
                    } else {
                        $category_check = $pdo->prepare(
                            'SELECT category_id
                             FROM career_categories
                             WHERE category_id = :category_id'
                        );
                        $category_check->execute([
                            ':category_id' => $validated_category
                        ]);

                        if (!$category_check->fetchColumn()) {
                            $errors[] = 'The selected category does not exist.';
                        } else {
                            $category_id = $validated_category;
                        }
                    }
                }

                // Avoid duplicate skill names.
                if (!$errors) {
                    $duplicate_sql = '
                        SELECT skill_id
                        FROM skills
                        WHERE LOWER(skill_name) = LOWER(:skill_name)
                    ';

                    $duplicate_params = [
                        ':skill_name' => $skill_name
                    ];

                    if ($action === 'update') {
                        $posted_id = filter_input(
                            INPUT_POST,
                            'skill_id',
                            FILTER_VALIDATE_INT
                        );

                        if (!$posted_id || $posted_id <= 0) {
                            $errors[] = 'Invalid skill selected for editing.';
                        } else {
                            $duplicate_sql .= ' AND skill_id <> :skill_id';
                            $duplicate_params[':skill_id'] = $posted_id;
                        }
                    }

                    if (!$errors) {
                        $duplicate = $pdo->prepare($duplicate_sql);
                        $duplicate->execute($duplicate_params);

                        if ($duplicate->fetchColumn()) {
                            $errors[] = 'A skill with this name already exists.';
                        }
                    }
                }

                if (!$errors && $action === 'add') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO skills (skill_name, category_id)
                         VALUES (:skill_name, :category_id)'
                    );

                    $stmt->execute([
                        ':skill_name' => $skill_name,
                        ':category_id' => $category_id
                    ]);

                    $_SESSION['skills_flash'] = [
                        'type' => 'success',
                        'message' => 'Skill added successfully.'
                    ];

                    header('Location: skills.php');
                    exit();
                }

                if (!$errors && $action === 'update') {
                    $stmt = $pdo->prepare(
                        'UPDATE skills
                         SET skill_name = :skill_name,
                             category_id = :category_id
                         WHERE skill_id = :skill_id'
                    );

                    $stmt->execute([
                        ':skill_name' => $skill_name,
                        ':category_id' => $category_id,
                        ':skill_id' => $posted_id
                    ]);

                    $_SESSION['skills_flash'] = [
                        'type' => 'success',
                        'message' => 'Skill updated successfully.'
                    ];

                    header('Location: skills.php');
                    exit();
                }
            } elseif ($action === 'delete') {
                $skill_id = filter_input(
                    INPUT_POST,
                    'skill_id',
                    FILTER_VALIDATE_INT
                );

                if (!$skill_id || $skill_id <= 0) {
                    $errors[] = 'Please select a valid skill to delete.';
                } else {
                    $stmt = $pdo->prepare(
                        'DELETE FROM skills WHERE skill_id = :skill_id'
                    );
                    $stmt->execute([':skill_id' => $skill_id]);

                    $_SESSION['skills_flash'] = [
                        'type' => 'success',
                        'message' => $stmt->rowCount() > 0
                            ? 'Skill deleted successfully.'
                            : 'The selected skill was not found.'
                    ];

                    header('Location: skills.php');
                    exit();
                }
            } else {
                $errors[] = 'Invalid action requested.';
            }
        } catch (PDOException $e) {
            error_log('Admin Skills Error: ' . $e->getMessage());

            if ($action === 'delete') {
                $errors[] = 'This skill could not be deleted. It may be linked to job-seeker profiles. Remove the relevant skill assignments first, or keep the skill.';
            } else {
                $errors[] = 'The operation could not be completed. Check the database tables and try again.';
            }
        }
    }
}

// --------------------------------------------------
// Flash message
// --------------------------------------------------
if (isset($_SESSION['skills_flash'])) {
    $flash = $_SESSION['skills_flash'];
    $success = $flash['message'] ?? '';
    unset($_SESSION['skills_flash']);
}

// --------------------------------------------------
// Categories for the add/edit form
// --------------------------------------------------
$categories = [];

try {
    $stmt = $pdo->query(
        'SELECT category_id, category_name
         FROM career_categories
         ORDER BY category_name ASC'
    );
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin Skills Categories Error: ' . $e->getMessage());
    $errors[] = 'Categories could not be loaded. Check the career_categories table.';
}

// --------------------------------------------------
// Load selected skill for editing
// --------------------------------------------------
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($edit_id && $edit_id > 0) {
        try {
            $stmt = $pdo->prepare(
                'SELECT skill_id, skill_name, category_id
                 FROM skills
                 WHERE skill_id = :skill_id'
            );
            $stmt->execute([':skill_id' => $edit_id]);
            $edit_skill = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$edit_skill) {
                $errors[] = 'The requested skill could not be found.';
            }
        } catch (PDOException $e) {
            error_log('Admin Skills Edit Error: ' . $e->getMessage());
            $errors[] = 'The selected skill could not be loaded.';
        }
    }
}

// --------------------------------------------------
// Search and list skills
// --------------------------------------------------
$search = trim($_GET['q'] ?? '');
$skills = [];
$total_skills = 0;

try {
    $count_stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM skills s
         WHERE (:search = "" OR s.skill_name LIKE :term)'
    );
    $count_stmt->execute([
        ':search' => $search,
        ':term' => '%' . $search . '%'
    ]);
    $total_skills = (int) $count_stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT
            s.skill_id,
            s.skill_name,
            s.category_id,
            c.category_name,
            (
                SELECT COUNT(*)
                FROM user_skills us
                WHERE us.skill_id = s.skill_id
            ) AS assigned_users
         FROM skills s
         LEFT JOIN career_categories c
            ON s.category_id = c.category_id
         WHERE (:search = "" OR s.skill_name LIKE :term)
         ORDER BY s.skill_name ASC'
    );

    $stmt->execute([
        ':search' => $search,
        ':term' => '%' . $search . '%'
    ]);

    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin Skills List Error: ' . $e->getMessage());
    $errors[] = 'Skills could not be loaded. Check the skills and user_skills tables.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
.skills-admin-page {
    min-height: 70vh;
    padding: 36px 0 55px;
    background: #f4f7fb;
}
.skills-admin-page .page-heading {
    color: #14284b;
    font-weight: 800;
}
.skills-admin-page .panel {
    background: #fff;
    border: 1px solid #e7edf5;
    border-radius: 18px;
    box-shadow: 0 8px 28px rgba(22, 42, 75, .06);
}
.skills-admin-page .stat-number {
    color: #14284b;
    font-size: 1.8rem;
    font-weight: 800;
}
.skills-admin-page .form-label {
    font-weight: 650;
    color: #24344d;
}
.skills-admin-page .table thead th {
    color: #52627a;
    background: #f7f9fc;
    border-bottom: 1px solid #e5eaf2;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}
.skills-admin-page .table td {
    vertical-align: middle;
}
.skills-admin-page .skill-icon {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eaf1ff;
    color: #2459bb;
    border-radius: 12px;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.skills-admin-page .btn-primary {
    background: #2459bb;
    border-color: #2459bb;
}
.skills-admin-page .btn-primary:hover {
    background: #194796;
    border-color: #194796;
}
.skills-admin-page .empty-state {
    padding: 42px 15px;
    text-align: center;
    color: #6b7890;
}
@media (max-width: 767px) {
    .skills-admin-page { padding-top: 22px; }
    .skills-admin-page .panel { border-radius: 14px; }
}
</style>

<div class="skills-admin-page">
    <div class="container">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="text-primary fw-semibold small mb-2">
                    <i class="bi bi-shield-lock me-1"></i> ADMINISTRATION
                </div>
                <h2 class="page-heading mb-1">
                    <i class="bi bi-tools me-2"></i>Skills Management
                </h2>
                <p class="text-muted mb-0">
                    Manage the master skill pool used across TechTalent.
                </p>
            </div>

            <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger rounded-3">
                <strong><i class="bi bi-exclamation-triangle me-1"></i>Please check:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="panel p-4 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="skill-icon"><i class="bi bi-collection"></i></div>
                        <div>
                            <div class="text-muted small">Skills in Master Pool</div>
                            <div class="stat-number"><?= number_format($total_skills) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel p-4 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="skill-icon"><i class="bi bi-tags"></i></div>
                        <div>
                            <div class="text-muted small">Available Categories</div>
                            <div class="stat-number"><?= number_format(count($categories)) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel p-4 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="skill-icon"><i class="bi bi-person-check"></i></div>
                        <div>
                            <div class="text-muted small">Purpose</div>
                            <div class="fw-bold mt-1">Candidate Skill Profiles</div>
                            <div class="text-muted small">Used for skill matching</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- Add / Edit Form -->
            <div class="col-lg-4">
                <div class="panel p-4">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <div class="skill-icon">
                            <i class="bi bi-<?= $edit_skill ? 'pencil-square' : 'plus-lg' ?>"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">
                                <?= $edit_skill ? 'Edit Skill' : 'Add New Skill' ?>
                            </h5>
                            <div class="text-muted small">
                                <?= $edit_skill ? 'Update the selected skill' : 'Add a skill to the master pool' ?>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="skills.php<?= $edit_skill ? '?edit=' . (int)$edit_skill['skill_id'] : '' ?>">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <input type="hidden" name="action"
                               value="<?= $edit_skill ? 'update' : 'add' ?>">

                        <?php if ($edit_skill): ?>
                            <input type="hidden" name="skill_id"
                                   value="<?= (int)$edit_skill['skill_id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="skill_name" class="form-label">Skill Name</label>
                            <input
                                type="text"
                                class="form-control form-control-lg rounded-3"
                                id="skill_name"
                                name="skill_name"
                                maxlength="100"
                                placeholder="e.g. PHP, Java, React"
                                required
                                value="<?= htmlspecialchars($edit_skill['skill_name'] ?? ($_POST['skill_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="mb-4">
                            <label for="category_id" class="form-label">Career Category</label>
                            <select class="form-select form-select-lg rounded-3"
                                    id="category_id" name="category_id">
                                <option value="">General / No category</option>
                                <?php foreach ($categories as $category): ?>
                                    <?php
                                    $selected_category = $edit_skill['category_id']
                                        ?? ($_POST['category_id'] ?? '');
                                    ?>
                                    <option
                                        value="<?= (int)$category['category_id'] ?>"
                                        <?= (string)$selected_category === (string)$category['category_id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Select a category to organize this skill.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-3 fw-semibold">
                            <i class="bi bi-<?= $edit_skill ? 'check-lg' : 'plus-circle' ?> me-1"></i>
                            <?= $edit_skill ? 'Save Changes' : 'Add Skill' ?>
                        </button>

                        <?php if ($edit_skill): ?>
                            <a href="skills.php" class="btn btn-light border w-100 mt-2 rounded-3">
                                Cancel Editing
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="alert alert-info border-0 rounded-4 mt-3 small">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Skills in this master pool are available to job seekers when building their technical profiles.
                </div>
            </div>

            <!-- Skills Table -->
            <div class="col-lg-8">
                <div class="panel p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">All Skills</h5>
                            <p class="text-muted small mb-0">
                                Search, edit, and manage the master skill list.
                            </p>
                        </div>
                        <span class="badge text-bg-primary rounded-pill px-3 py-2">
                            <?= count($skills) ?> shown
                        </span>
                    </div>

                    <form method="GET" action="skills.php" class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="search" name="q" class="form-control"
                                   placeholder="Search by skill name..."
                                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn btn-primary" type="submit">Search</button>
                            <?php if ($search !== ''): ?>
                                <a href="skills.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ($skills): ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Skill</th>
                                        <th>Category</th>
                                        <th>Profiles</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($skills as $skill): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="skill-icon">
                                                        <i class="bi bi-code-slash"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold text-dark">
                                                            <?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            ID: <?= (int)$skill['skill_id'] ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill text-bg-light border">
                                                    <?= htmlspecialchars($skill['category_name'] ?? 'General IT', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">
                                                    <?= number_format((int)$skill['assigned_users']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a class="btn btn-sm btn-outline-primary rounded-2"
                                                       href="skills.php?edit=<?= (int)$skill['skill_id'] ?>"
                                                       title="Edit skill">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>

                                                    <form method="POST" action="skills.php"
                                                          onsubmit="return confirm('Delete this skill from the master pool? If job-seeker profiles use it, deletion may be blocked.');">
                                                        <input type="hidden" name="csrf_token"
                                                               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="skill_id"
                                                               value="<?= (int)$skill['skill_id'] ?>">
                                                        <button type="submit"
                                                                class="btn btn-sm btn-outline-danger rounded-2"
                                                                title="Delete skill">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-search display-5 d-block mb-3"></i>
                            <h6 class="fw-bold text-dark">No skills found</h6>
                            <p class="mb-0 small">
                                <?= $search !== ''
                                    ? 'Try a different search term.'
                                    : 'Add your first skill using the form.' ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
