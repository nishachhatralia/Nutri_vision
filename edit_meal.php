<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

include 'db.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];

if(!isset($_GET['id'])) { header("Location: meal_history.php"); exit; }
$meal_id = (int)$_GET['id'];

// Fetch the meal, scoped to this user
$stmt = mysqli_prepare($conn, "SELECT * FROM meals WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($stmt, "ii", $meal_id, $user_id);
mysqli_stmt_execute($stmt);
$meal = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if(!$meal) {
    header("Location: meal_history.php");
    exit;
}

$error = '';
$updated = false;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name = trim($_POST['food'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 0);

    if(empty($food_name)) {
        $error = "Please select a food item.";
    } elseif($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE meals SET food_name=?, quantity=? WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "sdii", $food_name, $quantity, $meal_id, $user_id);
        if(mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: meal_history.php");
            exit;
        } else {
            $error = "Error updating meal: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Food list for dropdown
$food_result = mysqli_query($conn, "SELECT * FROM food_items ORDER BY food_name");
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Meal - NutriVision</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision</span></h2>
    <div class="nav-links">
        <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark</span>
        <a href="meal_history.php" class="btn">📜 History</a>
        <a href="dashboard.php" class="btn">📊 Dashboard</a>
    </div>
</div>

<div class="container">
    <h1>✏️ Edit Meal</h1>

    <?php if($error): ?>
    <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Food</label>
        <select name="food" required>
            <?php while($food = mysqli_fetch_assoc($food_result)):
                $sel = ($food['food_name'] === $meal['food_name']) ? 'selected' : ''; ?>
            <option value="<?php echo htmlspecialchars($food['food_name']); ?>" <?php echo $sel; ?>>
                <?php echo htmlspecialchars($food['food_name']); ?> (<?php echo $food['calories']; ?> kcal)
            </option>
            <?php endwhile; ?>
            <?php
            // If the current food isn't in food_items (custom entry), still show it
            mysqli_data_seek($food_result, 0);
            $found = false;
            while($f = mysqli_fetch_assoc($food_result)) { if($f['food_name'] === $meal['food_name']) { $found = true; break; } }
            if(!$found): ?>
            <option value="<?php echo htmlspecialchars($meal['food_name']); ?>" selected>
                <?php echo htmlspecialchars($meal['food_name']); ?> (custom)
            </option>
            <?php endif; ?>
        </select>

        <label>Quantity (servings)</label>
        <input type="number" name="quantity" value="<?php echo htmlspecialchars($meal['quantity']); ?>" min="0.5" step="0.5" required>

        <button type="submit" class="btn btn-lg">✅ Update Meal</button>
        <a href="meal_history.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>
