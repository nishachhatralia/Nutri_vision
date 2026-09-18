<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

include 'db.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];
$review_error = '';
$review_saved = false;

// Make sure the reviews table exists before doing anything else
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'reviews'");
if (!$table_check || mysqli_num_rows($table_check) === 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Reviews - NutriVision</title><link rel="stylesheet" href="style.css"></head>
    <body>
        <div class="navbar"><h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision</span></h2></div>
        <div class="container">
            <h1>⭐ Reviews &amp; Feedback</h1>
            <div class="alert alert-danger">
                ❌ The <code>reviews</code> table doesn't exist yet.
                Please run <a href="add_reviews_table.php"><strong>add_reviews_table.php</strong></a> once first, then come back to this page.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle review submit (insert new, or update the user's existing review)
if(isset($_POST['submit_review'])) {
    $rating = (int)($_POST['rating'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $review_error = "Please select a star rating (1–5).";
    } elseif (empty($feedback)) {
        $review_error = "Please write a short review before submitting.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO reviews (user_id, rating, feedback)
                                        VALUES (?, ?, ?)
                                        ON DUPLICATE KEY UPDATE rating = VALUES(rating), feedback = VALUES(feedback), created_at = CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $rating, $feedback);
        if (mysqli_stmt_execute($stmt)) {
            $review_saved = true;
        } else {
            $review_error = "Something went wrong saving your review: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch this user's existing review (to pre-fill the form / show "update" state)
$stmt = mysqli_prepare($conn, "SELECT rating, feedback FROM reviews WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$my_review = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$prefill_rating = $my_review['rating'] ?? 0;
$prefill_feedback = $my_review['feedback'] ?? '';

// Aggregate stats
$agg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating FROM reviews"));
$total_reviews = (int)($agg['total'] ?? 0);
$avg_rating = round($agg['avg_rating'] ?? 0, 1);

$distribution = [];
for ($i = 5; $i >= 1; $i--) {
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM reviews WHERE rating = $i"));
    $distribution[$i] = (int)($row['c'] ?? 0);
}

// All reviews, newest first
$list_result = mysqli_query($conn, "SELECT reviews.rating, reviews.feedback, reviews.created_at, users.full_name
                                     FROM reviews
                                     JOIN users ON reviews.user_id = users.id
                                     ORDER BY reviews.created_at DESC
                                     LIMIT 100");

function nv_star_html($rating, $size = '1.1rem') {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span style="font-size:' . $size . '; color:' . ($i <= $rating ? 'var(--accent2)' : '#DADFF5') . ';">★</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reviews - NutriVision</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .rating-summary { display:flex; align-items:center; gap:28px; flex-wrap:wrap; }
        .rating-big { font-family:var(--font-mono); font-size:3.2rem; font-weight:700; color:var(--dark); line-height:1; }
        body.dark-mode .rating-big { color:#EDEFFC; }
        .rating-bars { flex:1; min-width:220px; }

        .rating-bar-row { display:flex; align-items:center; gap:10px; margin:4px 0; font-size:0.85rem; color:var(--gray); }
        .rating-bar-row .progress-container { flex:1; height:8px; }
        .star-input { font-size:2.2rem; line-height:1; cursor:pointer; user-select:none; }
        .star-input span { color:#DADFF5; transition:color .15s, transform .1s; display:inline-block; }
        .star-input span.filled { color:var(--accent2); }
        .star-input span:hover { transform:scale(1.15); }
        body.dark-mode .star-input span { color:rgba(255,255,255,0.18); }
        .review-item { padding:16px 18px; border-radius:var(--radius); background:white; box-shadow:var(--card-shadow); margin:12px 0; border-left:4px solid var(--accent2); }
        body.dark-mode .review-item { background:rgba(25,27,58,0.7); }
        .review-item .review-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px; }
        .review-item .review-name { font-weight:700; }
        .review-item .review-date { font-size:0.78rem; color:var(--gray); }
        .review-item .review-text { color:var(--dark); }
        body.dark-mode .review-item .review-text { color:#EDEFFC; }
    </style>
</head>
<body>
<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision</span></h2>
    <div class="nav-links">
        <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark</span>
        <a href="dashboard.php" class="btn">📊 Dashboard</a>
    </div>
</div>

<div class="container">
    <h1>⭐ Reviews &amp; Feedback</h1>

    <?php if($review_saved): ?>
    <div class="alert alert-success">✅ Thanks! Your review has been <?php echo $my_review ? 'updated' : 'submitted'; ?>.</div>
    <?php endif; ?>
    <?php if($review_error): ?>
    <div class="alert alert-danger">❌ <?php echo htmlspecialchars($review_error); ?></div>
    <?php endif; ?>

    <!-- Overall rating summary -->
    <div class="card">
        <h2>Overall Rating</h2>
        <?php if ($total_reviews === 0): ?>
            <p style="color:var(--gray);">No reviews yet — be the first to share your feedback below!</p>
        <?php else: ?>
        <div class="rating-summary">
            <div style="text-align:center;">
                <div class="rating-big"><?php echo $avg_rating; ?></div>
                <div><?php echo nv_star_html(round($avg_rating), '1.3rem'); ?></div>
                <div style="color:var(--gray); font-size:0.85rem; margin-top:4px;"><?php echo $total_reviews; ?> review<?php echo $total_reviews == 1 ? '' : 's'; ?></div>
            </div>
            <div class="rating-bars">
                <?php foreach ($distribution as $star => $count):
                    $pct = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
                ?>
                <div class="rating-bar-row">
                    <span style="width:38px;"><?php echo $star; ?> ★</span>
                    <div class="progress-container"><div class="progress-bar" style="width:<?php echo $pct; ?>%;"></div></div>
                    <span style="width:28px; text-align:right;"><?php echo $count; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leave / update a review -->
    <form method="POST">
        <h2><?php echo $my_review ? '✏️ Update Your Review' : '📝 Leave a Review'; ?></h2>

        <label>Your Rating</label>
        <div class="star-input" id="starInput">
            <span data-val="1">★</span>
            <span data-val="2">★</span>
            <span data-val="3">★</span>
            <span data-val="4">★</span>
            <span data-val="5">★</span>
        </div>
        <input type="hidden" name="rating" id="ratingValue" value="<?php echo (int)$prefill_rating; ?>">

        <label style="margin-top:16px;">Your Feedback</label>
        <textarea name="feedback" rows="4" placeholder="What do you like? What could be better?" style="width:100%; padding:13px 15px; border:1.5px solid #DFD8C4; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:1rem; background:#FAFBFF;"><?php echo htmlspecialchars($prefill_feedback); ?></textarea>

        <button type="submit" name="submit_review" class="btn btn-lg" style="margin-top:16px;">
            <?php echo $my_review ? '✅ Update Review' : '✅ Submit Review'; ?>
        </button>
    </form>

    <!-- All reviews -->
    <h2>💬 What Users Are Saying</h2>
    <?php if (mysqli_num_rows($list_result) === 0): ?>
        <p style="color:var(--gray);">No reviews yet.</p>
    <?php else: while ($r = mysqli_fetch_assoc($list_result)): ?>
        <div class="review-item">
            <div class="review-head">
                <span class="review-name"><?php echo htmlspecialchars($r['full_name']); ?></span>
                <span class="review-date"><?php echo date('d M Y', strtotime($r['created_at'])); ?></span>
            </div>
            <div><?php echo nv_star_html($r['rating']); ?></div>
            <p class="review-text" style="margin-top:6px;"><?php echo nl2br(htmlspecialchars($r['feedback'])); ?></p>
        </div>
    <?php endwhile; endif; ?>
</div>

<script>
const starInput = document.getElementById('starInput');
const ratingValue = document.getElementById('ratingValue');
const stars = starInput.querySelectorAll('span');

function paintStars(val) {
    stars.forEach(s => s.classList.toggle('filled', parseInt(s.dataset.val) <= val));
}
paintStars(parseInt(ratingValue.value) || 0);

stars.forEach(s => {
    s.addEventListener('click', () => {
        ratingValue.value = s.dataset.val;
        paintStars(parseInt(s.dataset.val));
    });
    s.addEventListener('mouseenter', () => paintStars(parseInt(s.dataset.val)));
});
starInput.addEventListener('mouseleave', () => paintStars(parseInt(ratingValue.value) || 0));

function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>