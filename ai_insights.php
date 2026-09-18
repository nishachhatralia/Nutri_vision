<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'includes/icon.php';
include 'ai_bridge.php';

$user_id = $_SESSION['user_id'];
$ai_available = $ai->isAvailable();

// Get user's meal data
$meals_query = "SELECT m.*, f.calories, f.protein, f.carbs, f.fat, f.fiber, f.sugar 
                FROM meals m 
                LEFT JOIN food_items f ON m.food_name = f.food_name 
                WHERE m.user_id = $user_id 
                ORDER BY m.id DESC LIMIT 50";
$meals_result = mysqli_query($conn, $meals_query);
$meals_data = AIBridge::formatMealsForAI($meals_result);

// Get user info
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Run analysis
$analysis = null;
if($ai_available && !empty($meals_data)) {
    $analysis_result = $ai->analyzeNutrition($meals_data);
    if(isset($analysis_result['success'])) {
        $analysis = $analysis_result;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nutrition Insights - NutriVision</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .insight-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: var(--card-shadow);
            border-left: 4px solid var(--primary);
        }
        .insight-card.warning { border-left-color: var(--warning); }
        .insight-card.danger { border-left-color: var(--danger); }
        .insight-card h3 { margin-bottom: 10px; }
        .insight-card ul { list-style: none; padding: 0; }
        .insight-card ul li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .insight-card ul li:last-child { border-bottom: none; }
        
        .score-circle {
            width: 120px; height: 120px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; font-weight: bold; margin: 0 auto 15px; color: white;
        }
        .recommendation-item {
            padding: 12px; margin: 8px 0;
            background: var(--light-gray); border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        .recommendation-item.important { border-left-color: var(--warning); background: #FFF6E0; }
        body.dark-mode .insight-card { background: rgba(25,27,58,0.7); }
        body.dark-mode .recommendation-item { background: rgba(25,27,58,0.7); }
        body.dark-mode .recommendation-item.important { background: rgba(255,176,32,0.14); }
        .trend-up { color: var(--danger); }
        .trend-down { color: var(--primary); }
        .trend-stable { color: var(--secondary); }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
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
    <h1>🧠 Nutrition Insights</h1>
    <p style="color: var(--gray);">Analyzing your eating patterns for smarter recommendations</p>

    <?php if(!$ai_available): ?>
    <div class="insight-card warning">
        <h3>⚠️ Backend Offline</h3>
        <p>Start the server with: <code>python ai_backend/app.py</code> then refresh this page.</p>
    </div>
    <?php endif; ?>

    <?php if($analysis && isset($analysis['analysis'])): 
        $a = $analysis['analysis'];
        $score = $a['score'] ?? 50;
        $score_color = $score >= 80 ? 'var(--primary)' : ($score >= 60 ? 'var(--warning)' : 'var(--danger)');
    ?>
    
    <!-- Overall Score -->
    <div class="card" style="text-align: center;">
        <h2>📊 Nutrition Health Score</h2>
        <div class="score-circle" style="background: <?php echo $score_color; ?>">
            <?php echo $score; ?>/100
        </div>
        <p style="font-size: 1.2rem;">
            <?php 
            if($score >= 80) echo "🌟 Excellent nutrition habits!";
            elseif($score >= 60) echo "👍 Good progress! Room for improvement.";
            else echo "⚠️ Needs attention. Review recommendations below.";
            ?>
        </p>
        <?php if(isset($a['calorie_trend'])): ?>
        <p>Calorie trend: 
            <span class="<?php echo 'trend-' . $a['calorie_trend']; ?>">
                <?php echo ucfirst($a['calorie_trend']); ?>
                <?php if($a['calorie_trend'] == 'increasing') echo ' 📈'; ?>
                <?php if($a['calorie_trend'] == 'decreasing') echo ' 📉'; ?>
            </span>
        </p>
        <?php endif; ?>
        <?php if(isset($a['avg_daily_calories'])): ?>
        <p>Average daily calories: <strong><?php echo number_format($a['avg_daily_calories'], 0); ?> kcal</strong></p>
        <?php endif; ?>
    </div>

    <!-- Patterns Detected -->
    <?php if(!empty($a['patterns'])): ?>
    <div class="insight-card">
        <h3>🔄 Patterns Detected</h3>
        <ul>
            <?php foreach($a['patterns'] as $pattern): ?>
            <li>🔍 <?php echo htmlspecialchars($pattern); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Deficiencies -->
    <?php if(!empty($a['deficiencies'])): ?>
    <div class="insight-card danger">
        <h3>⚠️ Deficiencies Detected</h3>
        <ul>
            <?php foreach($a['deficiencies'] as $deficit): ?>
            <li>⚠️ <?php echo htmlspecialchars($deficit); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Excesses -->
    <?php if(!empty($a['excesses'])): ?>
    <div class="insight-card warning">
        <h3>⚡ Excessive Intake Detected</h3>
        <ul>
            <?php foreach($a['excesses'] as $excess): ?>
            <li>⚡ <?php echo htmlspecialchars($excess); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Recommendations -->
    <?php if(!empty($a['recommendations'])): ?>
    <div class="card">
        <h2>💡 Recommendations</h2>
        <?php foreach($a['recommendations'] as $i => $rec): ?>
        <div class="recommendation-item <?php echo ($i < 2) ? 'important' : ''; ?>">
            <?php echo htmlspecialchars($rec); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- No data yet -->
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="font-size: 48px;">📝</p>
        <h2>Start Tracking Your Meals</h2>
        <p style="color: var(--gray);">Add some meals to get personalized insights about your nutrition patterns!</p>
        <a href="add_meal.php" class="btn" style="margin-top: 15px;">Add Your First Meal</a>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
}
</script>
</body>
</html>