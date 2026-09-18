<?php
include 'includes/icon.php';

/**
 * Real reviews for the floating strip at the bottom of the page.
 *
 * This is the public landing page, so it must keep rendering (including
 * the Login/Register buttons) even if the database is briefly unreachable.
 * db.php calls die() on a failed connection, which would take the whole
 * homepage down with it -- so we open our own quiet connection here
 * instead, and just fall back to an empty review list on any failure.
 */
$landing_reviews = [];
mysqli_report(MYSQLI_REPORT_OFF); // don't let mysqli throw -- this section must fail silently
if (is_file(__DIR__ . '/db.php')) {
    $db_config = file_get_contents(__DIR__ . '/db.php');
    preg_match('/\$db_host\s*=\s*"([^"]*)"/', $db_config, $m_host);
    preg_match('/\$db_user\s*=\s*"([^"]*)"/', $db_config, $m_user);
    preg_match('/\$db_pass\s*=\s*"([^"]*)"/', $db_config, $m_pass);
    preg_match('/\$db_name\s*=\s*"([^"]*)"/', $db_config, $m_name);

    $landing_conn = @mysqli_connect(
        $m_host[1] ?? 'localhost',
        $m_user[1] ?? 'root',
        $m_pass[1] ?? '',
        $m_name[1] ?? ''
    );

    if ($landing_conn) {
        $reviews_table_check = @mysqli_query($landing_conn, "SHOW TABLES LIKE 'reviews'");
        if ($reviews_table_check && mysqli_num_rows($reviews_table_check) > 0) {
            $landing_reviews_result = @mysqli_query($landing_conn, "
                SELECT reviews.rating, reviews.feedback, users.full_name
                FROM reviews
                JOIN users ON reviews.user_id = users.id
                WHERE reviews.rating >= 4
                ORDER BY reviews.created_at DESC
                LIMIT 12
            ");
            if ($landing_reviews_result) {
                while ($row = mysqli_fetch_assoc($landing_reviews_result)) {
                    $landing_reviews[] = $row;
                }
            }
        }
        mysqli_close($landing_conn);
    }
}

function nv_landing_stars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span style="color:' . ($i <= $rating ? '#FFC93C' : '#DADFF5') . ';">★</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>NutriVision AI</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ============ Landing page animation layer (visual only) ============ */

        .hero { position: relative; overflow: hidden; }

        .floating-particles {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        .particle {
            position: absolute;
            bottom: -60px;
            font-size: 1.8rem;
            opacity: 0;
            animation: floatUp linear infinite;
            filter: drop-shadow(0 0 8px rgba(0,214,111,0.35));
        }

        @keyframes floatUp {
            0%   { transform: translateY(0) translateX(0) rotate(0deg); opacity: 0; }
            10%  { opacity: 0.85; }
            50%  { transform: translateY(-260px) translateX(20px) rotate(180deg); }
            90%  { opacity: 0.7; }
            100% { transform: translateY(-520px) translateX(-15px) rotate(360deg); opacity: 0; }
        }

        .hero-content { position: relative; z-index: 2; }

        .hero h1 {
            background-size: 200% auto;
            animation: shine 4s linear infinite, heroTextIn 0.8s ease both;
        }

        @keyframes shine {
            to { background-position: 200% center; }
        }

        @keyframes heroTextIn {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero p { animation: heroTextIn 0.8s ease 0.15s both; }

        .hero-cta { animation: heroTextIn 0.8s ease 0.3s both; position: relative; z-index: 2; }

        .hero-cta .btn { position: relative; }

        .hero-cta .btn::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: inherit;
            background: inherit;
            z-index: -1;
            opacity: 0.55;
            animation: pulseRing 2.2s ease-out infinite;
        }

        @keyframes pulseRing {
            0%   { transform: scale(1);   opacity: 0.5; }
            100% { transform: scale(1.35); opacity: 0; }
        }

        /* Scroll-reveal for feature cards */
        .feature-card {
            opacity: 0;
            transform: translateY(28px) scale(0.97);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.22,1,0.36,1);
        }

        .feature-card.in-view { opacity: 1; transform: translateY(0) scale(1); }

        .feature-card:nth-child(1) { transition-delay: 0.05s; }
        .feature-card:nth-child(2) { transition-delay: 0.15s; }
        .feature-card:nth-child(3) { transition-delay: 0.25s; }
        .feature-card:nth-child(4) { transition-delay: 0.35s; }

        .feature-card .icon { display: inline-block; animation: bob 3s ease-in-out infinite; }
        .feature-card:nth-child(2) .icon { animation-delay: 0.3s; }
        .feature-card:nth-child(3) .icon { animation-delay: 0.6s; }
        .feature-card:nth-child(4) .icon { animation-delay: 0.9s; }

        @keyframes bob {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-8px) rotate(-6deg); }
        }

        .section-eyebrow {
            display: block;
            text-align: center;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin: 44px 0 6px;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @media (prefers-reduced-motion: reduce) {
            .particle, .hero h1, .hero p, .hero-cta, .hero-cta .btn::after,
            .feature-card, .feature-card .icon { animation: none !important; transition: none !important; }
            .feature-card { opacity: 1; transform: none; }
        }

        /* ============ Floating reviews strip ============ */
        .reviews-marquee {
            overflow: hidden;
            margin: 20px 0 60px;
            padding: 10px 0;
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
            mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
        }

        .reviews-track {
            display: flex;
            width: max-content;
            gap: 20px;
            animation: floatReviews 38s linear infinite;
        }

        .reviews-marquee:hover .reviews-track { animation-play-state: paused; }

        @keyframes floatReviews {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        .review-float-card {
            flex: 0 0 auto;
            width: 280px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.04);
            padding: 18px 20px;
        }
        body.dark-mode .review-float-card { background: rgba(25,27,58,0.7); }

        .review-float-stars { font-size: 0.95rem; margin-bottom: 8px; }
        .review-float-text {
            font-size: 0.92rem;
            color: var(--dark);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        body.dark-mode .review-float-text { color: #EDEFFC; }
        .review-float-name {
            margin-top: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--gray);
        }

        @media (prefers-reduced-motion: reduce) {
            .reviews-track { animation: none !important; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision AI</span></h2>
</div>

<div class="container">
    <div class="hero">
        <div class="floating-particles">
            <span class="particle" style="left:6%;  font-size:1.6rem; animation-duration:9s;  animation-delay:0s;">🥗</span>
            <span class="particle" style="left:16%; font-size:2.1rem; animation-duration:12s; animation-delay:1.5s;">🍎</span>
            <span class="particle" style="left:28%; font-size:1.4rem; animation-duration:8s;  animation-delay:3s;">💧</span>
            <span class="particle" style="left:42%; font-size:1.9rem; animation-duration:11s; animation-delay:0.8s;">🥑</span>
            <span class="particle" style="left:58%; font-size:1.5rem; animation-duration:10s; animation-delay:2.2s;">🍊</span>
            <span class="particle" style="left:70%; font-size:2rem;   animation-duration:13s; animation-delay:4s;">🌿</span>
            <span class="particle" style="left:82%; font-size:1.6rem; animation-duration:9.5s; animation-delay:1s;">🔥</span>
            <span class="particle" style="left:92%; font-size:1.8rem; animation-duration:10.5s; animation-delay:2.8s;">🍽️</span>
        </div>

        <div class="hero-content">
            <h1>Welcome to NutriVision AI</h1>
            <p>Smart Nutrition & Fitness Assistant</p>
        </div>
    </div>

    <div class="flex flex-center gap-20 hero-cta" style="margin: 30px 0;">
        <a href="login.php" class="btn btn-lg">🔑 Login</a>
        <a href="register.php" class="btn btn-secondary btn-lg">📝 Register</a>
    </div>

    <span class="section-eyebrow">Why NutriVision</span>

    <div class="feature-grid">
        <div class="feature-card">
            <div class="icon">🍽️</div>
            <h3>Multi-Item Meal Logging</h3>
            <p>Pick several foods at once — like Chole and Paratha together — in a single meal entry.</p>
        </div>
        <div class="feature-card">
            <div class="icon">📊</div>
            <h3>Nutrition Tracking</h3>
            <p>Track calories, protein, carbs, fat and more with every meal.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🥗</div>
            <h3>Personalized Diet Plans</h3>
            <p>Get vegetarian meal suggestions tailored to your goals.</p>
        </div>
        <div class="feature-card">
            <div class="icon">💧</div>
            <h3>Water & Goal Tracking</h3>
            <p>Stay hydrated and hit your daily nutrition targets.</p>
        </div>
    </div>
</div>

<!-- ============ Floating reviews strip ============ -->
<span class="section-eyebrow">What Users Are Saying</span>

<?php if (empty($landing_reviews)): ?>
    <div class="container" style="text-align:center; padding: 10px 0 50px;">
        <p style="color:var(--gray);">No reviews yet — be the first to share your experience after you sign in!</p>
    </div>
<?php else: ?>
    <div class="reviews-marquee">
        <div class="reviews-track">
            <?php
            // Render the list twice back-to-back so the CSS animation can
            // loop seamlessly from -50% back to 0% with no visible jump.
            for ($pass = 0; $pass < 2; $pass++):
                foreach ($landing_reviews as $r):
            ?>
                <div class="review-float-card" aria-hidden="<?php echo $pass === 1 ? 'true' : 'false'; ?>">
                    <div class="review-float-stars"><?php echo nv_landing_stars((int)$r['rating']); ?></div>
                    <p class="review-float-text">&ldquo;<?php echo htmlspecialchars(mb_strimwidth($r['feedback'], 0, 160, '…')); ?>&rdquo;</p>
                    <div class="review-float-name"><?php echo htmlspecialchars($r['full_name']); ?></div>
                </div>
            <?php
                endforeach;
            endfor;
            ?>
        </div>
    </div>
<?php endif; ?>

<script>
    // Scroll-reveal for feature cards (visual only, no data/logic touched)
    const cards = document.querySelectorAll('.feature-card');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        cards.forEach(card => observer.observe(card));
    } else {
        cards.forEach(card => card.classList.add('in-view'));
    }
</script>

</body>
</html>