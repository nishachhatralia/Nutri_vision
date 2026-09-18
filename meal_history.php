<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

include 'db.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];
$delete_msg = '';

// Handle delete (prepared statement, scoped to the logged-in user)
if(isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = mysqli_prepare($conn, "DELETE FROM meals WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, "ii", $delete_id, $user_id);
    if(mysqli_stmt_execute($stmt)) {
        $delete_msg = '✅ Meal deleted successfully!';
    }
    mysqli_stmt_close($stmt);
}

// Handle search (prepared statement)
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = mysqli_prepare($conn, "SELECT m.*, f.calories, f.protein, f.carbs, f.fat
                                    FROM meals m
                                    LEFT JOIN food_items f ON m.food_name = f.food_name
                                    WHERE m.user_id=? AND m.food_name LIKE ?
                                    ORDER BY m.meal_time DESC");
    mysqli_stmt_bind_param($stmt, "is", $user_id, $like);
} else {
    $stmt = mysqli_prepare($conn, "SELECT m.*, f.calories, f.protein, f.carbs, f.fat
                                    FROM meals m
                                    LEFT JOIN food_items f ON m.food_name = f.food_name
                                    WHERE m.user_id=?
                                    ORDER BY m.meal_time DESC");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$meals = [];
$grand_cal = 0; $grand_prot = 0;
while($row = mysqli_fetch_assoc($result)) {
    $row['line_cal'] = ($row['calories'] ?? 0) * $row['quantity'];
    $row['line_prot'] = ($row['protein'] ?? 0) * $row['quantity'];
    $grand_cal += $row['line_cal'];
    $grand_prot += $row['line_prot'];
    $meals[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
<title>Meal History - NutriVision</title>
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
    <h1>📜 Meal History</h1>

    <?php if($delete_msg): ?>
    <div class="alert alert-success"><?php echo $delete_msg; ?></div>
    <?php endif; ?>

    <form method="GET" style="display:flex; gap:10px; max-width:520px; align-items:flex-end;">
        <div style="flex:1;">
            <label>Search by food name</label>
            <input type="text" name="search" placeholder="e.g. Dal, Roti..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn" style="margin-bottom:16px;">🔍 Search</button>
        <?php if($search !== ''): ?>
        <a href="meal_history.php" class="btn btn-secondary" style="margin-bottom:16px;">✖️ Clear</a>
        <?php endif; ?>
    </form>

    <div style="overflow-x:auto;">
        <table>
            <tr>
                <th>#</th>
                <th>Food</th>
                <th>Qty</th>
                <th>Date &amp; Time</th>
                <th>🔥 Calories</th>
                <th>💪 Protein</th>
                <th>Actions</th>
            </tr>
            <?php if(empty($meals)): ?>
            <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--gray);">
                🍽️ No meals found. <a href="add_meal.php">Add a meal</a>
            </td></tr>
            <?php else: $count = 1; foreach($meals as $meal): ?>
            <tr>
                <td><?php echo $count++; ?></td>
                <td><strong><?php echo htmlspecialchars($meal['food_name']); ?></strong></td>
                <td><?php echo $meal['quantity']; ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($meal['meal_time'])); ?></td>
                <td><?php echo number_format($meal['line_cal'], 0); ?> kcal</td>
                <td><?php echo number_format($meal['line_prot'], 1); ?> g</td>
                <td>
                    <a href="edit_meal.php?id=<?php echo $meal['id']; ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                    <a href="?delete=<?php echo $meal['id']; ?><?php echo $search !== '' ? '&search='.urlencode($search) : ''; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this meal?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr style="background: var(--light-gray); font-weight:700;">
                <td colspan="4" style="text-align:right;">📊 TOTAL:</td>
                <td><?php echo number_format($grand_cal, 0); ?> kcal</td>
                <td><?php echo number_format($grand_prot, 1); ?> g</td>
                <td></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>
