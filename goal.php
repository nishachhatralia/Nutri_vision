<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];
$goal_saved = false;

// Get user data first — needed to compute suggested targets
$user_query = "SELECT * FROM users WHERE id='$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Calculate BMR / maintenance calories
$weight = (float)($user['weight'] ?? 70);
$height = (float)($user['height'] ?? 170);
$age = (int)($user['age'] ?? 30);
$gender = $user['gender'] ?? 'Male';

if($gender == 'Male') {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
} else {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
}

$activity_mult = $user['activity_level'] == 'Advanced' ? 1.725 : ($user['activity_level'] == 'Intermediate' ? 1.55 : 1.375);
$maintenance_calories = round($bmr * $activity_mult);

// ---------------------------------------------------------------
// Suggested targets per goal type — the app computes these, the
// user only picks a goal. No manual number entry.
// ---------------------------------------------------------------
function nv_goal_plan($goal_type, $maintenance, $weight) {
    switch ($goal_type) {
        case 'Muscle Gain':
            $cal = $maintenance + 400;
            $protein = round($weight * 1.6);
            $carbs = round($cal * 0.45 / 4);
            $fat = round($cal * 0.25 / 9);
            $summary = "Muscle Gain Plan: eat about {$cal} kcal/day (a 400 kcal surplus over maintenance) with extra protein and regular strength training to support muscle growth.";
            $tip = "ICMR-NIN Dietary Guidelines for Indians (2024): protein supplements are not recommended for healthy people — meet protein needs through whole foods like dal, paneer, curd and sprouts, alongside adequate carbs, fats, and regular physical activity to build and preserve muscle. The official ICMR-NIN baseline RDA is 0.83g protein/kg body weight/day (2020); the higher target above reflects your added activity level.";
            $box_class = 'surplus-box';
            $box_title = '📈 Muscle Gain — Calorie Surplus';
            $delta_label = 'Target Surplus';
            $delta_value = '+400 kcal';
            $delta_color = '#00B85E';
            $expected = '~0.3–0.5 kg muscle/week';
            break;
        case 'Maintain Weight':
            $cal = $maintenance;
            $protein = round($weight * 1.2);
            $carbs = round($cal * 0.5 / 4);
            $fat = round($cal * 0.25 / 9);
            $summary = "Maintain Weight: eat about {$cal} kcal/day. Balance all macros and stay active.";
            $tip = "WHO Healthy Diet guidance: balance calories eaten with calories used, limit free sugar to under 10% of daily energy, and get at least 150 minutes of moderate physical activity per week to hold a stable, healthy weight.";
            $box_class = 'surplus-box';
            $box_title = '⚖️ Maintain Weight';
            $delta_label = 'Adjustment';
            $delta_value = '0 kcal (at maintenance)';
            $delta_color = '#00B85E';
            $expected = 'Stable weight';
            break;
        case 'Improve Health':
            $cal = max(1200, $maintenance - 100);
            $protein = round($weight * 1.4);
            $carbs = round($cal * 0.45 / 4);
            $fat = round($cal * 0.3 / 9);
            $summary = "Improve Health: eat about {$cal} kcal/day. Focus on nutrient quality over quantity.";
            $tip = "ICMR-NIN Dietary Guidelines for Indians (2024): cut down on sugar, salt, refined and deep-fried foods, and reused cooking oil. Increase fruits, vegetables, pulses, whole grains and millets. Get 7–8 hours of sleep and limit non-essential screen time.";
            $box_class = 'surplus-box';
            $box_title = '🌿 Improve Health';
            $delta_label = 'Slight Adjustment';
            $delta_value = '-100 kcal';
            $delta_color = '#00B85E';
            $expected = 'Better nutrient quality, gradual improvement';
            break;
        default:
            $goal_type = 'Weight Loss';
            $cal = max(1200, $maintenance - 500);
            $protein = round($weight * 1.6);
            $carbs = round($cal * 0.35 / 4);
            $fat = round($cal * 0.25 / 9);
            $summary = "Weight Loss Plan: eat about {$cal} kcal/day (a 500 kcal deficit). High protein helps preserve muscle while you lose fat.";
            $tip = "CDC (U.S. Centers for Disease Control and Prevention): a safe, sustainable rate of weight loss is 0.5–1 kg per week, from a daily deficit of 500–1,000 kcal through diet and activity combined. Faster loss risks muscle and nutrient loss. Favour fibre-rich vegetables, whole grains and plant proteins (ICMR-NIN Dietary Guidelines for Indians, 2024) — avoid crash diets.";
            $box_class = 'deficit-box';
            $box_title = '📉 Weight Loss — Calorie Deficit';
            $delta_label = 'Target Deficit';
            $delta_value = '-500 kcal';
            $delta_color = '#B5123A';
            $expected = '~0.5 kg/week';
    }
    return compact('goal_type','cal','protein','carbs','fat','summary','tip','box_class','box_title','delta_label','delta_value','delta_color','expected');
}

// Handle goal selection — the value POSTed is only the goal *type*.
// The app computes and stores the suggested numbers itself.
if(isset($_POST['save_goal'])) {
    $allowed_goals = ['Weight Loss', 'Muscle Gain', 'Maintain Weight', 'Improve Health'];
    $posted_goal = $_POST['goal_type'] ?? 'Weight Loss';
    $goal_type = in_array($posted_goal, $allowed_goals) ? $posted_goal : 'Weight Loss';

    $plan = nv_goal_plan($goal_type, $maintenance_calories, $weight);
    $daily_calorie_goal = $plan['cal'];
    $target_protein = $plan['protein'];
    $target_carbs = $plan['carbs'];
    $target_fat = $plan['fat'];

    mysqli_query($conn, "UPDATE users SET daily_calorie_goal='$daily_calorie_goal', goal='$goal_type' WHERE id='$user_id'");

    $check = mysqli_query($conn, "SELECT id FROM goals WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $g = mysqli_fetch_assoc($check);
        $goal_id = $g['id'];
        $g_query = "UPDATE goals SET target_calories='$daily_calorie_goal', target_protein='$target_protein', target_carbs='$target_carbs', target_fat='$target_fat', start_date=CURDATE() WHERE id=$goal_id";
    } else {
        $g_query = "INSERT INTO goals (user_id, target_calories, target_protein, target_carbs, target_fat, start_date) VALUES ('$user_id', '$daily_calorie_goal', '$target_protein', '$target_carbs', '$target_fat', CURDATE())";
    }
    mysqli_query($conn, $g_query);

    $user['goal'] = $goal_type; // keep local copy in sync for this render
    $goal_saved = true;
}

// Get current goal (freshly saved above, or previously stored)
$goal_query = "SELECT * FROM goals WHERE user_id = $user_id ORDER BY id DESC LIMIT 1";
$goal_result = mysqli_query($conn, $goal_query);
$current_goal = mysqli_fetch_assoc($goal_result);

// Active plan to display (based on saved goal, or a sensible default)
$active_goal_type = $user['goal'] ?: 'Weight Loss';
$plan = nv_goal_plan($active_goal_type, $maintenance_calories, $weight);

// Get today's actual intake
$today_query = "SELECT 
    COALESCE(SUM(food_items.calories * meals.quantity), 0) AS today_cal,
    COALESCE(SUM(food_items.protein * meals.quantity), 0) AS today_prot,
    COALESCE(SUM(food_items.carbs * meals.quantity), 0) AS today_carbs,
    COALESCE(SUM(food_items.fat * meals.quantity), 0) AS today_fat
FROM meals 
JOIN food_items ON meals.food_name = food_items.food_name 
WHERE meals.user_id='$user_id' AND DATE(meals.meal_time) = CURDATE()";
$today_result = mysqli_query($conn, $today_query);
$today = mysqli_fetch_assoc($today_result);
$today_cal = $today['today_cal'] ?? 0;
$today_prot = $today['today_prot'] ?? 0;
$today_carbs = $today['today_carbs'] ?? 0;
$today_fat = $today['today_fat'] ?? 0;

// ---------------------------------------------------------------
// Weekly / daily nutrition report, benchmarked against government
// dietary data: ICMR-NIN "Nutrient Requirements for Indians — RDA
// & EAR 2020" and the ICMR-NIN "Dietary Guidelines for Indians".
// ---------------------------------------------------------------
$week_query = "SELECT DATE(meals.meal_time) AS log_date,
    SUM(food_items.calories * meals.quantity) AS day_cal,
    SUM(food_items.protein * meals.quantity) AS day_prot,
    SUM(food_items.carbs * meals.quantity) AS day_carbs,
    SUM(food_items.fat * meals.quantity) AS day_fat,
    SUM(food_items.fiber * meals.quantity) AS day_fiber,
    SUM(food_items.sugar * meals.quantity) AS day_sugar
FROM meals
JOIN food_items ON meals.food_name = food_items.food_name
WHERE meals.user_id='$user_id' AND meals.meal_time >= (CURDATE() - INTERVAL 6 DAY)
GROUP BY DATE(meals.meal_time)
ORDER BY log_date DESC";
$week_result = mysqli_query($conn, $week_query);
$week_days = [];
if ($week_result) { while ($wd = mysqli_fetch_assoc($week_result)) { $week_days[] = $wd; } }
$days_logged = count($week_days);

// Government RDA reference values (sedentary adult, ICMR-NIN RDA 2020 / DGI)
$rda_calories = ($gender == 'Male') ? 2110 : 1660;      // sedentary reference man/woman
$rda_protein  = round(0.83 * $weight, 1);                // 0.83 g/kg body weight/day
$rda_carbs    = round(($rda_calories * 0.55) / 4);        // ~55% of energy from carbohydrate
$rda_fat      = round(($rda_calories * 0.25) / 9);        // ~25% of energy from fat
$rda_fiber    = 30;                                        // 30 g per 2000 kcal
$rda_sugar    = 25;                                        // DGI: limit free sugar to 20-25 g/day

function nv_compare($label, $unit, $actual, $target, $lower_is_better = false) {
    $pct = $target > 0 ? ($actual / $target) * 100 : 0;
    if ($lower_is_better) {
        if ($actual > $target * 1.15) {
            $status = 'bad';
            $text = "⚠️ $label is " . round($actual,1) . "$unit/day — above the government limit of {$target}{$unit}/day. Try cutting back on sweets, sugary drinks, and desserts.";
        } elseif ($actual > $target) {
            $status = 'warn';
            $text = "🟡 $label is slightly above the recommended {$target}{$unit}/day. A little moderation will help.";
        } else {
            $status = 'good';
            $text = "✅ $label (" . round($actual,1) . "$unit/day) is within the recommended limit of {$target}{$unit}/day.";
        }
    } else {
        if ($pct < 80) {
            $status = 'bad';
            $text = "⚠️ $label is low — " . round($actual,1) . "$unit/day vs the recommended {$target}{$unit}/day. Try to increase this.";
        } elseif ($pct > 130) {
            $status = 'warn';
            $text = "🟡 $label is well above the recommended {$target}{$unit}/day (" . round($actual,1) . "$unit/day). Consider moderating portions.";
        } else {
            $status = 'good';
            $text = "✅ $label (" . round($actual,1) . "$unit/day) is close to the recommended {$target}{$unit}/day.";
        }
    }
    return ['status' => $status, 'text' => $text];
}

$report_mode = 'none';
$report_rows = [];
$report_period_label = '';

if ($days_logged >= 3) {
    $report_mode = 'weekly';
    $avg_cal   = array_sum(array_column($week_days, 'day_cal'))   / $days_logged;
    $avg_prot  = array_sum(array_column($week_days, 'day_prot'))  / $days_logged;
    $avg_carbs = array_sum(array_column($week_days, 'day_carbs')) / $days_logged;
    $avg_fat   = array_sum(array_column($week_days, 'day_fat'))   / $days_logged;
    $avg_fiber = array_sum(array_column($week_days, 'day_fiber')) / $days_logged;
    $avg_sugar = array_sum(array_column($week_days, 'day_sugar')) / $days_logged;
    $report_period_label = "$days_logged day" . ($days_logged > 1 ? 's' : '') . " logged in the last 7";

    $report_rows[] = nv_compare('Calories', ' kcal', $avg_cal, $rda_calories);
    $report_rows[] = nv_compare('Protein', 'g', $avg_prot, $rda_protein);
    $report_rows[] = nv_compare('Carbohydrates', 'g', $avg_carbs, $rda_carbs);
    $report_rows[] = nv_compare('Fat', 'g', $avg_fat, $rda_fat, true);
    $report_rows[] = nv_compare('Fiber', 'g', $avg_fiber, $rda_fiber);
    $report_rows[] = nv_compare('Sugar', 'g', $avg_sugar, $rda_sugar, true);
} elseif ($days_logged >= 1) {
    $report_mode = 'daily';
    $latest = $week_days[0];
    $report_period_label = 'Based on ' . date('d M Y', strtotime($latest['log_date']));

    $report_rows[] = nv_compare('Calories', ' kcal', $latest['day_cal'], $rda_calories);
    $report_rows[] = nv_compare('Protein', 'g', $latest['day_prot'], $rda_protein);
    $report_rows[] = nv_compare('Carbohydrates', 'g', $latest['day_carbs'], $rda_carbs);
    $report_rows[] = nv_compare('Fat', 'g', $latest['day_fat'], $rda_fat, true);
    $report_rows[] = nv_compare('Fiber', 'g', $latest['day_fiber'], $rda_fiber);
    $report_rows[] = nv_compare('Sugar', 'g', $latest['day_sugar'], $rda_sugar, true);
}
?>
<!DOCTYPE html>
<html>
<head><title>Goals - NutriVision</title><link rel="stylesheet" href="style.css">
<style>
.goal-option { display:flex; gap:15px; flex-wrap:wrap; margin:15px 0; }
.goal-card { flex:1; min-width:180px; padding:20px; border:2px solid #ECEFFC; border-radius:12px; text-align:center; cursor:pointer; transition:.3s; background:white; }
.goal-card:hover { border-color:var(--primary); transform:translateY(-3px); }
.goal-card.selected { border-color:var(--primary); background:#E4FFF1; }
.goal-card .icon { font-size:2.5rem; margin-bottom:10px; }
.goal-card h4 { margin-bottom:5px; }
.goal-card p { font-size:.85rem; color:var(--gray); }
.health-tip { background:linear-gradient(135deg,#FF3D7F,#FFC93C); color:white; padding:20px; border-radius:12px; margin:20px 0; }
.health-tip h3 { color:white !important; }
body.dark-mode .goal-card { background:rgba(25,27,58,0.7); border-color:rgba(255,255,255,0.12); }
body.dark-mode .goal-card.selected { background:rgba(0,214,111,0.14); border-color:var(--primary); }
.suggestion-card { padding:20px; margin:15px 0; background:var(--light-gray); border-radius:12px; border-left:4px solid var(--primary); }
body.dark-mode .suggestion-card { background:rgba(25,27,58,0.7); }
.deficit-box { padding:15px; border-radius:10px; margin:10px 0; background:#FFF6E0; border:1px solid #FFB020; }
.deficit-box h3 { color:#8A5A00 !important; }
.surplus-box { padding:15px; border-radius:10px; margin:10px 0; background:#E4FFF1; border:1px solid #00D66F; }
.surplus-box h3 { color:#00B85E !important; }
body.dark-mode .deficit-box { background:rgba(255,176,32,0.12); border-color:#FFB020; }
body.dark-mode .surplus-box { background:rgba(0,214,111,0.12); border-color:#00D66F; }
.calc-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #ECEFFC; }
body.dark-mode .calc-row { border-color:rgba(255,255,255,0.1); }
.report-row { padding:12px 14px; margin:8px 0; border-radius:10px; font-size:0.92rem; border-left:4px solid; }
.report-row.good { background:#E4FFF1; border-color:#00D66F; color:#00593A; }
.report-row.warn { background:#FFF6E0; border-color:#FFB020; color:#6E4A00; }
.report-row.bad  { background:#FFE9EE; border-color:#FF3D5A; color:#8A0E28; }
body.dark-mode .report-row.good { background:rgba(0,214,111,0.14); color:#B9FFDB; }
body.dark-mode .report-row.warn { background:rgba(255,176,32,0.14); color:#FFE3A8; }
body.dark-mode .report-row.bad  { background:rgba(255,61,90,0.14); color:#FFC2CE; }
.report-source { font-size:0.78rem; color:var(--gray); margin-top:14px; }
.target-pill { display:inline-flex; flex-direction:column; align-items:center; padding:14px 10px; background:white; border-radius:12px; box-shadow:var(--card-shadow); min-width:100px; }
body.dark-mode .target-pill { background:rgba(25,27,58,0.7); }
.target-pill .val { font-family:var(--font-mono); font-size:1.3rem; font-weight:700; color:var(--primary); }
.target-pill .lbl { font-size:0.72rem; color:var(--gray); text-transform:uppercase; letter-spacing:.04em; margin-top:2px; }
</style></head>
<body>
<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision</span></h2>
    <div class="nav-links">
        <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark</span>
        <a href="dashboard.php" class="btn">📊 Dashboard</a>
    </div>
</div>

<div class="container">
    <h1>🎯 Your Goals</h1>

    <?php if($goal_saved): ?>
    <div class="alert alert-success">✅ Goal updated — here's what NutriVision suggests for you.</div>
    <?php endif; ?>

    <!-- Today's vs Target -->
    <div class="card">
        <h2>📊 Your Progress Today vs Suggested Target</h2>
        <div class="cards" style="grid-template-columns:repeat(4,1fr);">
            <div class="card" style="border-left:4px solid <?php echo $today_cal <= ($current_goal['target_calories'] ?? 2000) ? 'var(--primary)' : 'var(--danger)'; ?>;">
                <h3>🔥 Calories</h3>
                <h1><?php echo number_format($today_cal,0); ?></h1>
                <p>of <?php echo $current_goal['target_calories'] ?? 2000; ?> suggested</p>
                <p style="font-weight:600;color:<?php echo ($today_cal <= ($current_goal['target_calories'] ?? 2000)) ? 'var(--primary)' : 'var(--danger)'; ?>;">
                    <?php $diff = ($current_goal['target_calories'] ?? 2000) - $today_cal; echo $diff >= 0 ? "Need $diff more" : "Exceeded by ".abs($diff); ?>
                </p>
            </div>
            <div class="card" style="border-left:4px solid var(--primary);">
                <h3>💪 Protein</h3>
                <h1><?php echo number_format($today_prot,1); ?>g</h1>
                <p>of <?php echo $current_goal['target_protein'] ?? 50; ?>g</p>
                <p style="font-weight:600;"><?php $pd = ($current_goal['target_protein']??50)-$today_prot; echo $pd>=0 ? "Need ".number_format($pd,1)."g more" : "✅ Great!"; ?></p>
            </div>
            <div class="card" style="border-left:4px solid var(--warning);">
                <h3>🌾 Carbs</h3>
                <h1><?php echo number_format($today_carbs,1); ?>g</h1>
                <p>of <?php echo $current_goal['target_carbs'] ?? 200; ?>g</p>
                <p style="font-weight:600;"><?php $cd = ($current_goal['target_carbs']??200)-$today_carbs; echo $cd>=0 ? "Need ".number_format($cd,1)."g more" : "✅ Done"; ?></p>
            </div>
            <div class="card" style="border-left:4px solid var(--secondary);">
                <h3>🥑 Fat</h3>
                <h1><?php echo number_format($today_fat,1); ?>g</h1>
                <p>of <?php echo $current_goal['target_fat'] ?? 50; ?>g</p>
                <p style="font-weight:600;"><?php $fd = ($current_goal['target_fat']??50)-$today_fat; echo $fd>=0 ? "Need ".number_format($fd,1)."g more" : "✅ Done"; ?></p>
            </div>
        </div>
    </div>

    <!-- Weekly / Daily Report vs Government Guidelines -->
    <div class="card">
        <h2>📅 <?php echo $report_mode === 'weekly' ? 'Weekly Nutrition Report' : ($report_mode === 'daily' ? 'Daily Nutrition Report' : 'Nutrition Report'); ?></h2>

        <?php if ($report_mode === 'none'): ?>
            <p style="color:var(--gray);">No meals logged in the last 7 days yet. Add a few meals and check back here for suggestions based on official ICMR-NIN dietary guidelines.</p>

        <?php else: ?>
            <p style="color:var(--gray); margin-bottom:6px;">
                <?php echo htmlspecialchars($report_period_label); ?>
                <?php if ($report_mode === 'daily'): ?>
                    — log meals on more days this week to unlock a full weekly report.
                <?php endif; ?>
            </p>

            <?php foreach ($report_rows as $row_item): ?>
                <div class="report-row <?php echo $row_item['status']; ?>"><?php echo $row_item['text']; ?></div>
            <?php endforeach; ?>

            <p class="report-source">
                Source: ICMR-NIN "Nutrient Requirements for Indians — Recommended Dietary Allowances (RDA) &amp; Estimated Average Requirements, 2020" and the ICMR-NIN "Dietary Guidelines for Indians". Values shown are for a sedentary reference adult (<?php echo $rda_calories; ?> kcal, <?php echo $rda_protein; ?>g protein based on your weight, <?php echo $rda_fiber; ?>g fiber, ≤<?php echo $rda_sugar; ?>g free sugar/day) and are general guidance, not medical advice.
            </p>
        <?php endif; ?>
    </div>

    <!-- BMR & Stats -->
    <div class="card" style="text-align:center;">
        <h2>Your Body Statistics</h2>
        <div class="cards" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
            <div class="card"><h3>⚖️ Weight</h3><h1><?php echo $weight; ?> kg</h1></div>
            <div class="card"><h3>📏 Height</h3><h1><?php echo $height; ?> cm</h1></div>
            <div class="card"><h3>🔥 BMR</h3><h1><?php echo round($bmr); ?></h1></div>
            <div class="card"><h3>⚡ Maintenance</h3><h1><?php echo $maintenance_calories; ?> kcal</h1></div>
        </div>
    </div>

    <!-- Goal Selection -->
    <div class="card">
        <h2>What is your goal?</h2>
        <p style="color:var(--gray); margin-bottom:10px;">Pick a goal and NutriVision will work out how much to increase or decrease your calories, protein, carbs, and fat — no manual entry needed.</p>
        <form method="POST" id="goalForm">
            <div class="goal-option">
                <div class="goal-card <?php echo ($active_goal_type=='Weight Loss')?'selected':''; ?>" onclick="chooseGoal('Weight Loss')">
                    <div class="icon">📉</div><h4>Weight Loss</h4><p>Reduce body fat</p>
                </div>
                <div class="goal-card <?php echo ($active_goal_type=='Muscle Gain')?'selected':''; ?>" onclick="chooseGoal('Muscle Gain')">
                    <div class="icon">💪</div><h4>Muscle Gain</h4><p>Build muscle</p>
                </div>
                <div class="goal-card <?php echo ($active_goal_type=='Maintain Weight')?'selected':''; ?>" onclick="chooseGoal('Maintain Weight')">
                    <div class="icon">⚖️</div><h4>Maintain</h4><p>Stay at current</p>
                </div>
                <div class="goal-card <?php echo ($active_goal_type=='Improve Health')?'selected':''; ?>" onclick="chooseGoal('Improve Health')">
                    <div class="icon">🌿</div><h4>Improve Health</h4><p>Better nutrition</p>
                </div>
            </div>
            <input type="hidden" name="goal_type" id="goalType" value="<?php echo htmlspecialchars($active_goal_type); ?>">
            <button type="submit" name="save_goal" style="display:none;" aria-hidden="true"></button>
        </form>
    </div>

    <!-- Suggested Targets (read-only, computed by the app) -->
    <div class="card">
        <h2>🎯 Suggested Targets — <?php echo htmlspecialchars($plan['goal_type']); ?></h2>

        <div class="flex flex-wrap gap-15" style="margin: 16px 0;">
            <div class="target-pill"><span class="val"><?php echo $plan['cal']; ?></span><span class="lbl">kcal / day</span></div>
            <div class="target-pill"><span class="val"><?php echo $plan['protein']; ?>g</span><span class="lbl">Protein</span></div>
            <div class="target-pill"><span class="val"><?php echo $plan['carbs']; ?>g</span><span class="lbl">Carbs</span></div>
            <div class="target-pill"><span class="val"><?php echo $plan['fat']; ?>g</span><span class="lbl">Fat</span></div>
        </div>

        <div class="suggestion-card"><?php echo $plan['summary']; ?></div>

        <div class="<?php echo $plan['box_class']; ?>">
            <h3><?php echo $plan['box_title']; ?></h3>
            <div class="calc-row"><span>Maintenance Calories:</span><strong><?php echo $maintenance_calories; ?> kcal</strong></div>
            <div class="calc-row"><span><?php echo $plan['delta_label']; ?>:</span><strong style="color:<?php echo $plan['delta_color']; ?>;"><?php echo $plan['delta_value']; ?></strong></div>
            <div class="calc-row"><span style="font-weight:700;">Your Suggested Goal:</span><strong style="color:<?php echo $plan['delta_color']; ?>;"><?php echo $plan['cal']; ?> kcal</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong><?php echo number_format($today_cal,0); ?> kcal</strong></div>
            <div class="calc-row"><span>Remaining Today:</span><strong style="color:<?php echo ($plan['cal'] - $today_cal >= 0) ? '#00B85E' : '#B5123A'; ?>;"><?php echo number_format(abs($plan['cal'] - $today_cal),0); ?> kcal <?php echo ($plan['cal'] - $today_cal >= 0) ? 'left' : 'over'; ?></strong></div>
            <div class="calc-row"><span>Expected Progress:</span><strong style="color:#00B85E;"><?php echo $plan['expected']; ?></strong></div>
        </div>

        <div class="<?php echo $plan['box_class']; ?>">
            <h3>💪 Protein Target</h3>
            <div class="calc-row"><span>Need per day:</span><strong><?php echo $plan['protein']; ?>g</strong></div>
            <div class="calc-row"><span>Eaten Today:</span><strong><?php echo number_format($today_prot,1); ?>g</strong></div>
            <div class="calc-row"><span>Still Need:</span><strong style="color:<?php echo (($plan['protein']-$today_prot) >= 0) ? '#C98A1F' : '#00B85E'; ?>;"><?php echo number_format(max(0,$plan['protein']-$today_prot),1); ?>g more</strong></div>
            <div class="calc-row"><span>Good Sources:</span><strong>Paneer, Dal, Tofu, Sprouts, Curd</strong></div>
        </div>
    </div>

    <div class="health-tip">
        <h3>📋 Official Health Recommendations</h3>
        <div><?php echo $plan['tip']; ?></div>
        <p style="font-size:0.78rem; margin-top:10px; opacity:0.9;">Calorie and protein targets combine official RDA/guideline baselines above with common activity-based adjustments for your selected goal — not a medical prescription. Consult a doctor or registered dietitian for individual advice.</p>
    </div>
</div>

<script>
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }

function chooseGoal(goal) {
    document.getElementById('goalType').value = goal;
    document.getElementById('goalForm').submit();
}
</script>
</body>
</html>