
<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// --------------------------------------------------
// Fetch approved reviews only
// --------------------------------------------------
$reviews = [];
$db_error = false;

try {
    $sql = "
        SELECT
            r.review_id,
            r.rating,
            r.review_text,
            r.created_at,
            u.full_name,
            u.role
        FROM reviews r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE LOWER(TRIM(r.status)) = 'approved'
        ORDER BY r.created_at DESC, r.review_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Guest Reviews Page Error: ' . $e->getMessage());
    $db_error = true;
}

// --------------------------------------------------
// Calculate statistics from approved reviews
// --------------------------------------------------
$total_reviews = count($reviews);
$rating_sum = 0;
$rating_count = 0;

foreach ($reviews as $review) {
    $rating = (int) $review['rating'];

    if ($rating >= 1 && $rating <= 5) {
        $rating_sum += $rating;
        $rating_count++;
    }
}

$average_rating = $rating_count > 0
    ? $rating_sum / $rating_count
    : 0;

$page_title = 'Community Reviews | TechTalent Solutions';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<style>
/* =========================================
   TechTalent Reviews Page
========================================= */

.tt-reviews {
    --tt-navy: #091b34;
    --tt-blue: #2459bb;
    --tt-sky: #38bdf8;
    --tt-muted: #69778d;

    background: #f5f8fc;
    color: #182943;
    padding-bottom: 70px;
    min-height: 70vh;
}

.tt-reviews * {
    box-sizing: border-box;
}

/* =========================================
   HERO SECTION
========================================= */

.tt-reviews .reviews-hero {
    position: relative;
    overflow: hidden;

    background:
        radial-gradient(
            circle at 85% 15%,
            rgba(56, 189, 248, 0.20),
            transparent 32%
        ),
        linear-gradient(
            125deg,
            #07172d 0%,
            #102e55 65%,
            #174b83 100%
        );

    color: #ffffff;
    padding: 75px 0 95px;
}

.tt-reviews .reviews-hero::after {
    content: "";
    position: absolute;
    width: 330px;
    height: 330px;
    right: -100px;
    bottom: -190px;

    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50%;

    box-shadow:
        0 0 0 35px rgba(255, 255, 255, 0.025),
        0 0 0 75px rgba(255, 255, 255, 0.025);

    pointer-events: none;
}

.tt-reviews .hero-content {
    position: relative;
    z-index: 1;
}

/* Hero left side */

.tt-reviews .hero-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    padding: 8px 15px;
    border: 1px solid rgba(255, 255, 255, 0.19);
    background: rgba(255, 255, 255, 0.08);

    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #d8efff;
    margin-bottom: 22px;
}

.tt-reviews .hero-title {
    max-width: 760px;
    font-size: clamp(2.2rem, 4.5vw, 3.6rem);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -1.4px;
    margin-bottom: 20px;
}

.tt-reviews .hero-title span {
    color: var(--tt-sky);
}

.tt-reviews .hero-description {
    max-width: 600px;
    color: #c6d5e8;
    font-size: 1.05rem;
    line-height: 1.85;
    margin-bottom: 0;
}

/* Hero right-side image */

.tt-reviews .review-hero-image {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;

    min-height: 300px;
    padding: 12px;
}

.tt-reviews .review-hero-image::before {
    content: "";
    position: absolute;
    width: 78%;
    height: 78%;
    background: rgba(56, 189, 248, 0.12);
    border-radius: 50%;
    filter: blur(35px);
}

.tt-reviews .review-hero-image img {
    position: relative;
    z-index: 1;

    display: block;
    width: 100%;
    max-width: 440px;
    max-height: 350px;

    object-fit: contain;
    border-radius: 20px;

    filter: drop-shadow(
        0 18px 28px rgba(0, 0, 0, 0.22)
    );
}

/* =========================================
   STATISTICS
========================================= */

.tt-reviews .rating-summary {
    position: relative;
    z-index: 2;
    margin-top: -45px;
}

.tt-reviews .summary-card {
    height: 100%;
    padding: 26px;
    background: #ffffff;
    border: 1px solid #e7edf5;
    border-radius: 20px;

    box-shadow: 0 12px 35px rgba(18, 40, 75, 0.08);
}

.tt-reviews .summary-icon {
    display: flex;
    align-items: center;
    justify-content: center;

    width: 48px;
    height: 48px;

    background: #eaf2ff;
    color: var(--tt-blue);
    border-radius: 14px;

    font-size: 1.3rem;
    margin-bottom: 15px;
}

.tt-reviews .summary-number {
    font-size: 2rem;
    line-height: 1.2;
    color: var(--tt-navy);
    font-weight: 800;
    margin-bottom: 5px;
}

.tt-reviews .summary-caption {
    color: var(--tt-muted);
    font-size: 0.9rem;
    margin: 0;
}

.tt-reviews .star-rating {
    color: #f5ad22;
    display: inline-flex;
    gap: 3px;
    font-size: 1rem;
}

/* =========================================
   REVIEWS SECTION
========================================= */

.tt-reviews .reviews-content {
    padding-top: 65px;
}

.tt-reviews .section-kicker {
    color: var(--tt-blue);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-size: 0.76rem;
    font-weight: 800;
    margin-bottom: 10px;
}

.tt-reviews .section-heading {
    color: var(--tt-navy);
    font-size: clamp(1.7rem, 3vw, 2.35rem);
    font-weight: 800;
    letter-spacing: -0.6px;
    margin-bottom: 10px;
}

.tt-reviews .section-description {
    color: var(--tt-muted);
    max-width: 630px;
    line-height: 1.7;
    margin: 0 auto;
}

.tt-reviews .review-count {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    color: #344761;
    background: #eaf0f8;

    padding: 9px 14px;
    border-radius: 50px;

    font-size: 0.84rem;
    font-weight: 700;
}

/* =========================================
   REVIEW CARDS
========================================= */

.tt-reviews .review-card {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;

    background: #ffffff;
    border: 1px solid #e7edf5;
    border-radius: 20px;
    padding: 25px;

    box-shadow: 0 5px 20px rgba(18, 40, 75, 0.035);

    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease,
        border-color 0.22s ease;
}

.tt-reviews .review-card:hover {
    transform: translateY(-5px);
    border-color: #cfdef4;
    box-shadow: 0 16px 35px rgba(18, 40, 75, 0.09);
}

.tt-reviews .review-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.tt-reviews .role-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    border-radius: 50px;
    padding: 7px 11px;

    font-size: 0.72rem;
    font-weight: 700;

    background: #edf3ff;
    color: #2459bb;
}

.tt-reviews .role-employer {
    background: #e8f7ef;
    color: #16764a;
}

.tt-reviews .role-other {
    background: #f0edff;
    color: #6544b5;
}

.tt-reviews .quote-mark {
    color: #d9e6f8;
    font-size: 2.5rem;
    line-height: 0.8;
    margin-bottom: 12px;
}

.tt-reviews .review-text {
    color: #52627a;
    font-size: 0.95rem;
    line-height: 1.85;

    overflow-wrap: anywhere;
    white-space: normal;

    margin-bottom: 26px;
    flex-grow: 1;
}

.tt-reviews .review-footer {
    display: flex;
    align-items: center;
    gap: 12px;

    border-top: 1px solid #edf1f6;
    padding-top: 18px;
    margin-top: auto;
}

.tt-reviews .review-avatar {
    width: 44px;
    height: 44px;

    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;

    border-radius: 50%;
    color: #2459bb;
    background: #eaf2ff;

    font-size: 1rem;
    font-weight: 800;
}

.tt-reviews .reviewer-name {
    color: #172b49;
    font-size: 0.94rem;
    font-weight: 750;
    margin-bottom: 3px;
    overflow-wrap: anywhere;
}

.tt-reviews .review-date {
    color: #8490a2;
    font-size: 0.78rem;
}

/* =========================================
   EMPTY AND ERROR STATES
========================================= */

.tt-reviews .empty-state {
    background: #ffffff;
    border: 1px solid #e7edf5;
    border-radius: 24px;
    padding: 60px 24px;
    text-align: center;

    box-shadow: 0 10px 30px rgba(18, 40, 75, 0.04);
}

.tt-reviews .empty-icon {
    width: 76px;
    height: 76px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0 auto 20px;

    border-radius: 22px;
    background: #eaf2ff;
    color: var(--tt-blue);

    font-size: 2rem;
}

.tt-reviews .empty-title {
    color: var(--tt-navy);
    font-weight: 800;
    margin-bottom: 10px;
}

.tt-reviews .error-state {
    border: 1px solid #f1c4c4;
    background: #fff5f5;
    color: #842029;

    border-radius: 14px;
    padding: 18px 20px;
}

/* =========================================
   BOTTOM CALL TO ACTION
========================================= */

.tt-reviews .bottom-cta {
    background: linear-gradient(120deg, #0a1b34, #174779);
    border-radius: 22px;
    padding: 35px;
    color: #ffffff;
    margin-top: 60px;
}

.tt-reviews .bottom-cta h3 {
    font-weight: 800;
    margin-bottom: 10px;
}

.tt-reviews .bottom-cta p {
    color: #c6d5e8;
    line-height: 1.7;
    margin-bottom: 0;
}

.tt-reviews .cta-button {
    background: #ffffff;
    color: #123762;
    border: 1px solid #ffffff;

    padding: 12px 19px;
    border-radius: 10px;

    font-weight: 750;
    text-decoration: none;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    transition:
        background 0.2s ease,
        transform 0.2s ease;
}

.tt-reviews .cta-button:hover {
    background: #eaf2ff;
    color: #123762;
    transform: translateY(-2px);
}

/* =========================================
   RESPONSIVE DESIGN
========================================= */

@media (max-width: 991px) {
    .tt-reviews .reviews-hero {
        padding: 55px 0 80px;
    }

    .tt-reviews .review-hero-image {
        min-height: 220px;
    }

    .tt-reviews .review-hero-image img {
        max-width: 360px;
        max-height: 270px;
    }

    .tt-reviews .hero-title {
        letter-spacing: -0.7px;
    }
}

@media (max-width: 767px) {
    .tt-reviews .summary-card {
        padding: 21px;
    }

    .tt-reviews .reviews-content {
        padding-top: 45px;
    }

    .tt-reviews .review-card {
        padding: 21px;
    }

    .tt-reviews .bottom-cta {
        padding: 27px 22px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tt-reviews .review-card,
    .tt-reviews .cta-button {
        transition: none;
    }
}
</style>

<main class="tt-reviews">

    <!-- =====================================
         HERO WITH IMAGE ON THE RIGHT
    ====================================== -->
    <section class="reviews-hero">
        <div class="container hero-content">
            <div class="row align-items-center g-4">

                <!-- Left: Hero Text -->
                <div class="col-12 col-lg-7">
                    <div class="hero-label">
                        <i class="bi bi-chat-square-heart-fill"></i>
                        THE TECHTALENT COMMUNITY
                    </div>

                    <h1 class="hero-title">
                        Real experiences.<br>
                        <span>Real career journeys.</span>
                    </h1>

                    <p class="hero-description">
                        Discover what job seekers and employers think about
                        TechTalent. Explore feedback from our community as
                        they connect, grow, and discover opportunities in
                        the technology industry.
                    </p>
                </div>

                <!-- Right: Hero Image -->
                <div class="col-12 col-lg-5">
                    <div class="review-hero-image">
                        <img
                            src="../assets/images/online-survey-review.jpg"
                            alt="Online customer reviews and feedback"
                            loading="eager"
                            onerror="this.style.display='none';"
                        >
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- =====================================
         REAL REVIEW STATISTICS
    ====================================== -->
    <section class="rating-summary">
        <div class="container">
            <div class="row g-3">

                <div class="col-12 col-md-4">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="bi bi-chat-left-text-fill"></i>
                        </div>

                        <div class="summary-number">
                            <?= number_format($total_reviews) ?>
                        </div>

                        <p class="summary-caption">
                            Published community reviews
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="bi bi-star-fill"></i>
                        </div>

                        <div class="summary-number">
                            <?= $rating_count > 0
                                ? number_format($average_rating, 1) . '/5'
                                : '—' ?>
                        </div>

                        <div
                            class="star-rating mb-2"
                            aria-label="<?= $rating_count > 0
                                ? 'Average rating ' .
                                  number_format($average_rating, 1) .
                                  ' out of 5'
                                : 'No ratings yet' ?>"
                        >
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi <?= $i <= (int) round($average_rating)
                                    ? 'bi-star-fill'
                                    : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                        </div>

                        <p class="summary-caption">
                            Average approved review rating
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>

                        <div class="summary-number">
                            Our Community
                        </div>

                        <p class="summary-caption">
                            Feedback from job seekers and employers
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- =====================================
         APPROVED REVIEW CARDS
    ====================================== -->
    <section class="reviews-content">
        <div class="container">

            <div class="text-center mb-5">
                <div class="section-kicker">
                    Community feedback
                </div>

                <h2 class="section-heading">
                    What Our Members Say
                </h2>

                <p class="section-description">
                    Read experiences shared by members of the TechTalent
                    community. Only reviews approved for publication are shown.
                </p>
            </div>

            <?php if ($db_error): ?>

                <div class="error-state" role="alert">
                    <div class="fw-bold mb-1">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Reviews are temporarily unavailable
                    </div>

                    <p class="mb-0">
                        We couldn't load the reviews right now. Please try
                        again later. If the problem continues, contact
                        the website administrator.
                    </p>
                </div>

            <?php elseif ($total_reviews === 0): ?>

                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-chat-square-heart"></i>
                    </div>

                    <h3 class="empty-title">
                        Your community story starts here
                    </h3>

                    <p
                        class="text-secondary mx-auto mb-3"
                        style="max-width: 520px; line-height: 1.8;"
                    >
                        There are no approved reviews to display yet. Reviews submitted by TechTalent members will appear here after administrator approval.
                    </p>
                    <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'job_seeker'): ?>
                        <a class="cta-button" href="../jobseeker/submit-review.php">Share your review</a>
                    <?php elseif (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'employer'): ?>
                        <a class="cta-button" href="../employer/submit-review.php">Share your review</a>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="d-flex align-items-center justify-content-between
                            flex-wrap gap-3 mb-4">

                    <div class="fw-bold text-dark">
                        Latest published reviews
                    </div>

                    <div class="review-count">
                        <i class="bi bi-patch-check-fill text-primary"></i>

                        <?= number_format($total_reviews) ?>

                        <?= $total_reviews === 1 ? 'review' : 'reviews' ?>
                    </div>
                </div>

                <div class="row g-4">

                    <?php foreach ($reviews as $review): ?>

                        <?php
                        $rating = max(
                            0,
                            min(5, (int) $review['rating'])
                        );

                        $raw_role = strtolower(
                            trim((string) ($review['role'] ?? 'member'))
                        );

                        if ($raw_role === 'employer') {
                            $role_label = 'Employer';
                            $role_class = 'role-employer';
                            $role_icon = 'bi-building';

                        } elseif (
                            in_array(
                                $raw_role,
                                ['job_seeker', 'jobseeker'],
                                true
                            )
                        ) {
                            $role_label = 'Job Seeker';
                            $role_class = '';
                            $role_icon = 'bi-person-workspace';

                        } else {
                            $role_label = ucwords(
                                str_replace('_', ' ', $raw_role)
                            );

                            $role_class = 'role-other';
                            $role_icon = 'bi-person';
                        }

                        $reviewer_name = trim(
                            (string) ($review['full_name'] ?? 'Community Member')
                        );

                        if ($reviewer_name === '') {
                            $reviewer_name = 'Community Member';
                        }

                        $initial = function_exists('mb_substr')
                            ? mb_strtoupper(mb_substr($reviewer_name, 0, 1))
                            : strtoupper(substr($reviewer_name, 0, 1));

                        $timestamp = !empty($review['created_at'])
                            ? strtotime($review['created_at'])
                            : false;
                        ?>

                        <div class="col-12 col-md-6 col-lg-4">
                            <article class="review-card">

                                <div class="review-top">

                                    <div
                                        class="star-rating"
                                        aria-label="<?= $rating ?> out of 5 stars"
                                    >
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $rating
                                                ? 'bi-star-fill'
                                                : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                    </div>

                                    <span class="role-label <?= htmlspecialchars(
                                        $role_class,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">
                                        <i class="bi <?= htmlspecialchars(
                                            $role_icon,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"></i>

                                        <?= htmlspecialchars(
                                            $role_label,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <div class="quote-mark" aria-hidden="true">
                                    <i class="bi bi-quote"></i>
                                </div>

                                <p class="review-text"><?= nl2br(
                                    htmlspecialchars(
                                        (string) ($review['review_text'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?></p>

                                <div class="review-footer">

                                    <div
                                        class="review-avatar"
                                        aria-hidden="true"
                                    >
                                        <?= htmlspecialchars(
                                            $initial,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div class="flex-grow-1 min-w-0">

                                        <div class="reviewer-name">
                                            <?= htmlspecialchars(
                                                $reviewer_name,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <div class="review-date">
                                            <i class="bi bi-calendar3 me-1"></i>

                                            <?= $timestamp
                                                ? date('M j, Y', $timestamp)
                                                : 'Date unavailable' ?>
                                        </div>

                                    </div>

                                    <i
                                        class="bi bi-patch-check-fill text-primary"
                                        title="Published review"
                                        aria-label="Published review"
                                    ></i>

                                </div>

                            </article>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <!-- =====================================
                 BOTTOM CALL TO ACTION
            ====================================== -->
            <section class="bottom-cta">
                <div class="row align-items-center g-4">

                    <div class="col-lg-8">
                        <h3>
                            Ready to start your next career chapter?
                        </h3>

                        <p>
                            Explore technology opportunities and connect
                            with employers through TechTalent Solutions.
                        </p>
                    </div>

                    <div class="col-lg-4 text-lg-end">
                        <a href="jobs.php" class="cta-button">
                            Explore Job Opportunities
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>

                </div>
            </section>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>
