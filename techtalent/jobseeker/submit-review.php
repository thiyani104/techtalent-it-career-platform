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

$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    if ($rating >= 1 && $rating <= 5 && $review_text !== '') {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reviews (user_id, rating, review_text, status, created_at) VALUES (:uid, :rating, :text, 'pending', NOW())"
            );
            $stmt->execute([
                ':uid' => $_SESSION['user_id'],
                ':rating' => $rating,
                ':text' => $review_text,
            ]);
            $feedback = '<div class="alert alert-success" role="alert">Your review has been submitted and is pending approval.</div>';
        } catch (PDOException $e) {
            error_log('Review insert error: ' . $e->getMessage());
            $feedback = '<div class="alert alert-danger" role="alert">An error occurred while submitting your review. Please try again later.</div>';
        }
    } else {
        $feedback = '<div class="alert alert-warning" role="alert">Please provide a rating (1‑5) and a review comment.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Review | TechTalent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .star-rating input { display: none; }
        .star-rating label { font-size: 1.5rem; color: #ddd; cursor: pointer; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #ffc107; }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="fw-bold mb-4">Submit Your Review</h2>
    <?= $feedback ?>
    <form method="POST" class="row g-3">
        <div class="col-12">
            <label class="form-label">Rating</label>
            <div class="star-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= ($i === 5) ? 'checked' : '' ?>>
                    <label for="star<?= $i ?>" title="<?= $i ?> stars">&#9733;</label>
                <?php endfor; ?>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="review_text">Review Comment</label>
            <textarea class="form-control" id="review_text" name="review_text" rows="5" placeholder="Write your review here..." required></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
