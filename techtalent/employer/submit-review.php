<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Ensure employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $text   = trim($_POST['review_text'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars.';
    } elseif ($text === '') {
        $error = 'Review text cannot be empty.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reviews (user_id, rating, review_text, status, created_at) VALUES (:uid, :rating, :text, 'pending', NOW())"
            );
            $stmt->execute([
                ':uid'    => $_SESSION['user_id'],
                ':rating' => $rating,
                ':text'   => $text,
            ]);
            $success = 'Thank you! Your review has been submitted and is pending administrator approval.';
        } catch (PDOException $e) {
            error_log('Review insertion failed: ' . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Review – Employer | TechTalent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .star-rating {
            direction: rtl;
            display: inline-flex;
            font-size: 1.5rem;
        }
        .star-rating input { display: none; }
        .star-rating label { color: #ddd; cursor: pointer; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #ffc107; }
    </style>
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4 text-center">Submit a Review</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>
    <form method="POST" action="" class="needs-validation" novalidate>
        <div class="mb-3">
            <label class="form-label">Rating</label>
            <div class="star-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= (isset($_POST['rating']) && (int)$_POST['rating'] === $i) ? 'checked' : '' ?>>
                    <label for="star<?= $i ?>" class="bi bi-star"></label>
                <?php endfor; ?>
            </div>
        </div>
        <div class="mb-3">
            <label for="review_text" class="form-label">Review</label>
            <textarea class="form-control" id="review_text" name="review_text" rows="5" required><?php echo htmlspecialchars($_POST['review_text'] ?? '', ENT_QUOTES); ?></textarea>
            <div class="invalid-feedback">Please write a review.</div>
        </div>
        <button type="submit" class="btn btn-primary">Submit Review</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
 (function () {
     'use strict';
     var forms = document.querySelectorAll('.needs-validation');
     Array.prototype.slice.call(forms).forEach(function (form) {
         form.addEventListener('submit', function (event) {
             if (!form.checkValidity()) {
                 event.preventDefault();
                 event.stopPropagation();
             }
             form.classList.add('was-validated');
         }, false);
     });
 })();
</script>
</body>
</html>
