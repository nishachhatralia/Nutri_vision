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
$diet_plan = null;
$meal_preview = null;
$user_goal = 'maintain_weight';
$ai_available = false;

$ai_available = $ai->isAvailable();

// Get user's goal
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);
if($user) {
    $user_goal = strtolower(str_replace(' ', '_', $user['goal'] ?? 'maintain_weight'));
}

// Get user's meal data
$meals_query = "SELECT m.*, f.calories, f.protein, f.carbs, f.fat, f.fiber, f.sugar 
                FROM meals m 
                LEFT JOIN food_items f ON m.food_name = f.food_name 
                WHERE m.user_id = $user_id 
                ORDER BY m.id DESC LIMIT 30";
$meals_result = mysqli_query($conn, $meals_query);
$meals_data = AIBridge::formatMealsForAI($meals_result);

// Get late meal preferences
$late_query = "SELECT COUNT(*) as late_meals FROM meals WHERE user_id = $user_id AND HOUR(meal_time) > 22";
$late_result = mysqli_query($conn, $late_query);
$late_row = mysqli_fetch_assoc($late_result);
$preferences = ['late_meals' => (int)($late_row['late_meals'] ?? 0), 'avg_dinner_hour' => 20];

// Handle user meal input submission
$user_meal_input = [];
if(isset($_POST['user_meal_submit'])) {
    for($i = 1; $i <= 4; $i++) {
        $meal_name = $_POST['meal_name_' . $i] ?? '';
        $meal_items = $_POST['meal_items_' . $i] ?? '';
        if($meal_name && $meal_items) {
            $user_meal_input[] = ['meal' => $meal_name, 'items' => $meal_items];
        }
    }
}

// Generate diet plan
if($ai_available) {
    $result = $ai->generateDietPlan($user_goal, $meals_data, $preferences);
    if(isset($result['success']) && $result['success']) {
        $diet_plan = $result;
    }
}

// Generate meal preview
$selected_meal_type = $_POST['meal_type'] ?? 'lunch';
if($ai_available) {
    $preview_result = $ai->getMealPreview($selected_meal_type, ['goal' => $user_goal]);
    if(isset($preview_result['success'])) {
        $meal_preview = $preview_result;
    }
}

if(isset($_POST['generate_plan'])) {
    if($ai_available) {
        $result = $ai->generateDietPlan($user_goal, $meals_data, $preferences);
        if(isset($result['success']) && $result['success']) {
            $diet_plan = $result;
        }
    }
}

// VEGETARIAN-ONLY alternative meal suggestions database
$veg_alternatives = [
    'Grains' => ['Roti (Whole Wheat)', 'Brown Rice', 'Steamed Rice', 'Ragi (Nachni) Roti', 'Oats Porridge', 'Quinoa'],
    'Pulse' => ['Dal Tadka (Lentils)', 'Moong Dal', 'Chole (Chickpea Curry)', 'Rajma (Kidney Beans)', 'Khichdi (Rice & Lentils)'],
    'Protein' => ['Paneer (Cottage Cheese)', 'Palak Paneer', 'Matar Paneer', 'Paneer Tikka', 'Tofu'],
    'Vegetable' => ['Mixed Vegetable Sabzi', 'Aloo Gobi', 'Palak Paneer', 'Green Peas (Matar)', 'Bottle Gourd (Lauki)'],
    'Gujarati Snack' => ['Dhokla (Steamed)', 'Khandvi', 'Thepla (Methi Thepla)', 'Handvo', 'Fafda'],
    'South Indian' => ['Idli (2 pieces)', 'Dosa (Plain)', 'Sambhar', 'Uttapam', 'Vada (Medu Vada)'],
    'Breakfast' => ['Poha (Flattened Rice)', 'Upma (Sooji)', 'Oats Porridge', 'Muesli with Milk', 'Idli with Sambhar'],
    'Fruit' => ['Banana', 'Apple', 'Orange', 'Papaya', 'Mango', 'Watermelon'],
    'Dairy' => ['Curd / Yogurt', 'Buttermilk (Chaas)', 'Milk (Full Cream)', 'Paneer (Cottage Cheese)'],
    'Nuts' => ['Almonds (10 pieces)', 'Walnuts (4 pieces)', 'Nuts Mix', 'Peanut Butter'],
    'Snack' => ['Moong Sprouts', 'Chana Masala', 'Bhel Puri'],
    'Street Food' => ['Pav Bhaji', 'Bhel Puri', 'Samosa (1 piece)'],
    'Rice Dish' => ['Khichdi (Rice & Lentils)', 'Vegetable Biryani', 'Steamed Rice', 'Brown Rice'],
    'Indian Sweet' => ['Shrikhand', 'Basundi', 'Jalebi']
];

// Nutrition info for alternatives (per serving)
$food_nutrition = [];
$fnq = mysqli_query($conn, "SELECT food_name, calories, protein, carbs, fat FROM food_items");
if ($fnq) {
    while($fn = mysqli_fetch_assoc($fnq)) {
        $food_nutrition[$fn['food_name']] = [
            'calories' => $fn['calories'],
            'protein' => $fn['protein'],
            'carbs' => $fn['carbs'],
            'fat' => $fn['fat']
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diet Plan - NutriVision</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .meal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 20px 0; }
        .meal-card { background: white; border-radius: 12px; padding: 20px; box-shadow: var(--card-shadow); transition: all 0.3s; }
        .meal-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .meal-card h3 { font-size: 1.2rem; margin-bottom: 10px; }
        .meal-card ul { list-style: none; padding: 0; }
        .meal-card ul li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .meal-card .nutrition-info { margin-top: 10px; padding: 10px; background: var(--light-gray); border-radius: 8px; font-size: 0.9rem; }
        .plan-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .plan-summary .stat { text-align: center; padding: 15px; background: white; border-radius: 10px; box-shadow: var(--card-shadow); }
        .plan-summary .stat h3 { font-size: 1.5rem; color: var(--primary); }
        .plan-summary .stat p { color: var(--gray); font-size: 0.9rem; }
        .user-meal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; }
        .user-meal-input { padding: 15px; background: var(--light-gray); border-radius: 10px; }
        .veg-badge { background: #E4FFF1; color: #00B85E; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 700; }
        .total-box { background: linear-gradient(135deg, #00D66F, #00C2FF); color: white; padding: 20px; border-radius: 12px; margin: 20px 0; }
        .total-box .big-num { font-size: 2.5rem; font-weight: 700; }
        body.dark-mode .meal-card { background: rgba(25,27,58,0.7); }
        body.dark-mode .plan-summary .stat { background: rgba(25,27,58,0.7); }
        body.dark-mode .user-meal-input { background: rgba(25,27,58,0.7); }
        @media (max-width: 600px) { .meal-grid { grid-template-columns: 1fr; } .plan-summary { grid-template-columns: repeat(2, 1fr); } }
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
    <h1>🥗 Diet Plan</h1>
    
    <?php if(!$ai_available): ?>
    <div class="alert alert-warning">⚠️ Backend not running. Start: <code>python ai_backend/app.py</code></div>
    <?php endif; ?>

    <!-- Step 1: Enter Your Meal Pattern -->
    <div class="card" style="margin: 20px 0;">
        <h2>📝 What You Ate Today</h2>
        <p style="color: var(--gray); margin-bottom: 15px;">Enter what you typically eat - we'll suggest healthier VEGETARIAN alternatives only if needed</p>
        
        <form method="POST">
            <div class="user-meal-grid">
                <div class="user-meal-input">
                    <label>🌅 Breakfast</label>
                    <input type="text" name="meal_name_1" value="Breakfast" placeholder="Meal name">
                    <input type="text" name="meal_items_1" placeholder="e.g. Poha, 2 roti, dhokla">
                </div>
                <div class="user-meal-input">
                    <label>☀️ Lunch</label>
                    <input type="text" name="meal_name_2" value="Lunch" placeholder="Meal name">
                    <input type="text" name="meal_items_2" placeholder="e.g. Dal, rice, sabzi">
                </div>
                <div class="user-meal-input">
                    <label>🌙 Dinner</label>
                    <input type="text" name="meal_name_3" value="Dinner" placeholder="Meal name">
                    <input type="text" name="meal_items_3" placeholder="e.g. Roti, paneer, salad">
                </div>
                <div class="user-meal-input">
                    <label>✨ Snacks</label>
                    <input type="text" name="meal_name_4" value="Snacks" placeholder="Meal name">
                    <input type="text" name="meal_items_4" placeholder="e.g. Nuts, fruit, dhokla">
                </div>
            </div>
            <br>
            <button type="submit" name="user_meal_submit" class="btn btn-secondary">✅ Save My Pattern</button>
        </form>
    </div>

    <!-- If user entered pattern, check it and suggest -->
    <?php if(!empty($user_meal_input)): ?>
    <div class="card">
        <h2>✅ Your Meal Pattern - Analysis</h2>
        <div style="overflow-x: auto;">
            <table>
                <tr><th>Meal</th><th>You Ate</th><th>Status</th><th>🌿 Better Alternative (if needed)</th><th>🔥 Calories</th><th>💪 Protein</th></tr>
                <?php 
                $total_plan_cal = 0; $total_plan_prot = 0;
                $alt_categories = ['Breakfast', 'Grains', 'Pulse', 'Protein', 'Vegetable', 'Gujarati Snack', 'South Indian', 'Fruit', 'Dairy'];
                $good_keywords = ['healthy', 'good', 'salad', 'fruit', 'vegetable', 'roti', 'dal', 'paneer', 'dhokla', 'idli', 'dosa', 'khichdi', 'poha', 'upma', 'thepla', 'sambhar', 'curd', 'yogurt', 'milk', 'nuts', 'almond', 'walnut', 'sprout', 'oat'];
                
                foreach($user_meal_input as $i => $user_meal):
                    $items_lower = strtolower($user_meal['items']);
                    $is_good = false;
                    foreach ($good_keywords as $kw) {
                        if (strpos($items_lower, $kw) !== false) { $is_good = true; break; }
                    }
                    
                    // Pick alternatives
                    $cat_idx = $i % count($alt_categories);
                    $cat = $alt_categories[$cat_idx];
                    $alt_options = [];
                    if (isset($veg_alternatives[$cat])) {
                        $pool = $veg_alternatives[$cat];
                        shuffle($pool);
                        $alt_options = array_slice($pool, 0, 2);
                    }
                    
                    // Calculate nutrition for alternatives
                    $alt_cal = 0; $alt_prot = 0; $alt_names = [];
                    foreach ($alt_options as $ao) {
                        if (isset($food_nutrition[$ao])) {
                            $alt_cal += $food_nutrition[$ao]['calories'];
                            $alt_prot += $food_nutrition[$ao]['protein'];
                            $alt_names[] = $ao;
                        }
                    }
                    $total_plan_cal += $alt_cal;
                    $total_plan_prot += $alt_prot;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user_meal['meal']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user_meal['items']); ?></td>
                    <td>
                        <?php if($is_good): ?>
                            <span style="color: var(--primary); font-weight: 600;">✅ Good</span>
                        <?php else: ?>
                            <span style="color: var(--warning); font-weight: 600;">⚡ Can Improve</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($is_good): ?>
                            <span style="color: var(--gray);">Keep your current - it's healthy!</span>
                        <?php else: ?>
                            <?php foreach($alt_names as $an): ?>
                                <span class="badge badge-primary" style="display:inline-block; margin:2px;">🌿 <?php echo $an; ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $alt_cal > 0 ? $alt_cal . ' kcal' : '-'; ?></td>
                    <td><?php echo $alt_prot > 0 ? $alt_prot . 'g' : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- Total Nutrition Summary -->
    <div class="total-box">
        <h3>📊 Total Nutrition from Suggested Plan</h3>
        <div class="flex" style="gap: 30px; flex-wrap: wrap; margin-top: 15px;">
            <div>
                <p>🔥 Total Calories</p>
                <span class="big-num"><?php echo $total_plan_cal; ?> kcal</span>
            </div>
            <div>
                <p>💪 Total Protein</p>
                <span class="big-num"><?php echo $total_plan_prot; ?> g</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Suggested Vegetarian Meals -->
    <h2>🌿 Suggested Vegetarian Meals</h2>
    <p style="color: var(--gray);">All suggestions are 100% vegetarian <span class="veg-badge">🌿 Veg</span></p>
    
    <div class="meal-grid">
        <?php 
        $meal_types = [
            'breakfast' => ['icon' => '🌅', 'cat' => 'Breakfast'],
            'lunch' => ['icon' => '☀️', 'cat' => 'Pulse'],
            'dinner' => ['icon' => '🌙', 'cat' => 'Vegetable'],
            'snacks' => ['icon' => '✨', 'cat' => 'Fruit']
        ];
        foreach($meal_types as $mt => $info): 
            $items = $veg_alternatives[$info['cat']] ?? [];
            shuffle($items);
            $display_items = array_slice($items, 0, 3);
            $sum_cal = 0; $sum_prot = 0;
            foreach ($display_items as $di) {
                if (isset($food_nutrition[$di])) {
                    $sum_cal += $food_nutrition[$di]['calories'];
                    $sum_prot += $food_nutrition[$di]['protein'];
                }
            }
        ?>
        <div class="meal-card">
            <h3><?php echo $info['icon'] . ' ' . ucfirst($mt); ?></h3>
            <ul>
                <?php foreach($display_items as $item): ?>
                <li>🌿 <?php echo $item; ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="nutrition-info">
                <small>🔥 ~<?php echo $sum_cal; ?> kcal | 💪 ~<?php echo $sum_prot; ?>g protein | 🌿 Veg</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Generate / Refresh -->
    <form method="POST" style="text-align: center; max-width: 100%;">
        <button type="submit" name="generate_plan" class="btn btn-lg" style="padding: 15px 40px;">🔄 Refresh Suggestions</button>
    </form>
</div>

<script>
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>