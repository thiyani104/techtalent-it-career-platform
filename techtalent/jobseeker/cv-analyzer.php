<?php
ob_start();
session_start();

// Auth Guard: only job seekers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'job_seeker') {
    header('Location: ../auth/login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../ai/skill-gap-analyzer.php';

$user_id = $_SESSION['user_id'];

// Handle form submission
$analysis = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get resume text either from uploaded file or textarea
    $resume_text = '';
    if (!empty($_FILES['resume_file']['tmp_name'])) {
        $tmpPath = $_FILES['resume_file']['tmp_name'];
        $mime = mime_content_type($tmpPath);
        if ($mime === 'text/plain') {
            $resume_text = file_get_contents($tmpPath);
        } else {
            // For PDF, a proper parser is needed; placeholder message
            $resume_text = '';
        }
    }
    if (empty($resume_text) && !empty($_POST['resume_text'])) {
        $resume_text = trim($_POST['resume_text']);
    }
    $target_job_id = $_POST['job_id'] ?? null;
    if ($resume_text !== '' && $target_job_id) {
        // Extract candidate keywords using tokenizer
        $candidate_skills = tokenize_skills($resume_text);
        // Fetch selected job required skills
        $stmt = $pdo->prepare('SELECT required_skills, job_title FROM jobs WHERE job_id = :jid');
        $stmt->execute([':jid' => $target_job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            $match = calculate_job_match($candidate_skills, $job['required_skills'] ?? '');
            $recommendations = generate_recommendations($match['gaps']);
            $analysis = [
                'job_title' => $job['job_title'],
                'score' => $match['score'],
                'matched' => $match['matched'],
                'gaps' => $match['gaps'],
                'recommendations' => $recommendations,
            ];
        }
    }
}

// Fetch list of vacancies for selection (broad query, no status filter)
$vacancies = [];
try {
    $stmt = $pdo->prepare('SELECT job_id, job_title, required_skills FROM jobs ORDER BY created_at DESC');
    $stmt->execute();
    $vacancies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Vacancies fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI CV Analyzer | TechTalent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-match { background-color: #28a745; color: #fff; }
        .badge-gap { background-color: #dc3545; color: #fff; }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="fw-bold mb-4">AI CV Analyzer & ATS Keyword Extractor</h2>
    <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Upload Resume (txt or pdf)</label>
            <input class="form-control" type="file" name="resume_file" accept=".txt,.pdf">
        </div>
        <div class="col-md-6">
            <label class="form-label">Or paste resume text</label>
            <textarea class="form-control" name="resume_text" rows="4" placeholder="Paste your resume here..."></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Select Target Vacancy</label>
            <select class="form-select" name="job_id" required>
                <option value="" disabled selected>Choose a vacancy...</option>
                <?php foreach ($vacancies as $v): ?>
                    <option value="<?php echo (int)$v['job_id']; ?>"><?php echo htmlspecialchars($v['job_title'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Analyze CV</button>
        </div>
    </form>

    <?php if ($analysis): ?>
        <hr class="my-4">
        <h4>Analysis for: <?php echo htmlspecialchars($analysis['job_title'], ENT_QUOTES, 'UTF-8'); ?></h4>
        <p>ATS Compatibility Score: <span class="badge <?php echo $analysis['score'] >= 80 ? 'bg-success' : ($analysis['score'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>"><?php echo $analysis['score']; ?>%</span></p>
        <?php if (!empty($analysis['matched'])): ?>
            <p>Matched Keywords:</p>
            <div class="mb-2">
                <?php foreach ($analysis['matched'] as $m): ?>
                    <span class="badge badge-match me-1"><?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($analysis['gaps'])): ?>
            <p>Missing Keywords (add to improve ATS match):</p>
            <div class="mb-2">
                <?php foreach ($analysis['gaps'] as $g): ?>
                    <span class="badge badge-gap me-1"><?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($analysis['recommendations'])): ?>
            <p>AI‑Generated Recommendations:</p>
            <ul>
                <?php foreach ($analysis['recommendations'] as $rec): ?>
                    <li><?php echo htmlspecialchars($rec['title'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
