<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

include 'db.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];
$water_added = false;

if(isset($_POST['add_water'])) {
    // Check if water_intake table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'water_intake'");
    if (mysqli_num_rows($table_check) > 0) {
        $insert_query = "INSERT INTO water_intake (user_id, amount_ml) VALUES ('$user_id', 250)";
        if (mysqli_query($conn, $insert_query)) {
            $water_added = true;
        }
    }
}

// Get total water for TODAY only (was previously an all-time lifetime sum,
// which is why "today" kept showing whatever was last ever logged instead
// of resetting each day).
$total_water = 0;
$water_query = "SELECT COALESCE(SUM(amount_ml), 0) as total_water FROM water_intake WHERE user_id = $user_id AND DATE(intake_time) = CURDATE()";
$water_result = @mysqli_query($conn, $water_query);
if ($water_result) {
    $water_row = mysqli_fetch_assoc($water_result);
    $total_water = $water_row['total_water'] ?? 0;
}
$total_glasses = floor($total_water / 250);
$remaining = max(0, 2000 - $total_water);
$percent = min(100, round(($total_water / 2000) * 100));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Water Tracker - NutriVision</title>
    <link rel="stylesheet" href="style.css">
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
    <h1>💧 Water Intake Tracker</h1>
    
    <?php if($water_added): ?>
    <div class="alert alert-success">✅ +250ml added! Keep drinking!</div>
    <?php endif; ?>

    <div class="card" style="text-align: center;">
        <div style="font-size: 80px; margin: 20px 0;">💧</div>
        <h1 style="font-size: 4rem; color: var(--secondary);"><?php echo number_format($total_water, 0); ?> <small style="font-size: 1.5rem;">ml</small></h1>
        <p style="font-size: 1.2rem; color: var(--gray);"><?php echo $total_glasses; ?> glasses of 250ml</p>
        
        <div class="progress-container" style="height: 25px; margin: 20px 0;">
            <div class="progress-bar" style="width: <?php echo $percent; ?>%; background: linear-gradient(90deg, #4facfe, #00f2fe);"></div>
        </div>
        
        <div class="cards" style="grid-template-columns: repeat(3, 1fr);">
            <div class="card">
                <h3>🥤 Today</h3>
                <h1 style="color: var(--secondary);"><?php echo $total_glasses; ?></h1>
                <p>glasses</p>
            </div>
            <div class="card">
                <h3>🎯 Goal</h3>
                <h1 style="color: var(--primary);">8</h1>
                <p>glasses</p>
            </div>
            <div class="card">
                <h3>⚡ Remaining</h3>
                <h1 style="color: <?php echo $remaining > 0 ? 'var(--warning)' : 'var(--primary)'; ?>;"><?php echo ceil($remaining/250); ?></h1>
                <p>glasses</p>
            </div>
        </div>
        
        <?php if($total_water >= 2000): ?>
        <div class="alert alert-success" style="font-size: 1.2rem;">🎉 Congratulations! You've met your daily water goal! 🌟</div>
        <?php else: ?>
        <p style="color: var(--warning); font-weight: 600;">⚠️ You need <?php echo ceil($remaining/250); ?> more glasses to reach your goal!</p>
        <?php endif; ?>
        
        <br>
        <form method="POST">
            <button type="submit" name="add_water" class="btn btn-lg" style="padding: 20px 50px; font-size: 1.5rem; border-radius: 50px;">
                💧 Drink 250ml
            </button>
        </form>
    </div>
</div>

<script>
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>