<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

include 'db.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];
$meal_added = false;
$meal_error = '';
$nutrition_rows = [];
$custom_names = [];
$added_names = [];

// Handle manual meal add (now supports multiple foods in one submission)
if(isset($_POST['add_meal'])) {
    $selected_foods = $_POST['food'] ?? [];
    if (!is_array($selected_foods)) { $selected_foods = [$selected_foods]; }

    $custom_food = trim($_POST['custom_food'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 1);

    // Swap the literal "Other" entry for the typed custom food name
    $final_foods = [];
    foreach ($selected_foods as $f) {
        $f = trim($f);
        if ($f === 'Other') {
            if (!empty($custom_food)) { $final_foods[] = $custom_food; }
        } elseif ($f !== '') {
            $final_foods[] = $f;
        }
    }
    $final_foods = array_values(array_unique($final_foods));

    if (empty($final_foods)) {
        $meal_error = "Please select at least one food item (or enter a custom one).";
    } elseif ($quantity <= 0) {
        $meal_error = "Quantity must be greater than 0.";
    } else {
        $any_failed = false;
        foreach ($final_foods as $food) {
            $food_safe = mysqli_real_escape_string($conn, $food);
            $query = "INSERT INTO meals (user_id, food_name, quantity) VALUES ('$user_id', '$food_safe', '$quantity')";

            if (mysqli_query($conn, $query)) {
                mysqli_query($conn, "INSERT INTO meal_history (user_id, food_name, quantity) VALUES ('$user_id','$food_safe','$quantity')");
                $added_names[] = $food;

                $nr = mysqli_query($conn, "SELECT * FROM food_items WHERE food_name='$food_safe'");
                if ($nr && mysqli_num_rows($nr) > 0) {
                    $nutrition_rows[] = mysqli_fetch_assoc($nr);
                } else {
                    $custom_names[] = $food;
                }
            } else {
                $any_failed = true;
            }
        }

        if (!empty($added_names)) {
            $meal_added = true;
        }
        if ($any_failed && empty($added_names)) {
            $meal_error = "Database error: " . mysqli_error($conn);
        }
    }
}

// Get all food items
$food_result = mysqli_query($conn, "SELECT * FROM food_items ORDER BY food_name");
?>
<!DOCTYPE html>
<html>
<head><title>Add Meal - NutriVision</title><link rel="stylesheet" href="style.css">
<style>
.cat-btn { padding:5px 12px; border:1px solid #ECEFFC; border-radius:20px; background:white; cursor:pointer; font-size:0.8rem; display:inline-block; margin:2px; }
.cat-btn.active { background:var(--primary); color:white; border-color:var(--primary); }
body.dark-mode .cat-btn { background:rgba(25,27,58,0.7); color:#EDEFFC; border-color:rgba(255,255,255,0.14); }
body.dark-mode .cat-btn.active { background:var(--primary); color:white; }
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
    <h1>🍽️ Add Meal</h1>
    
    <?php if($meal_added): ?>
    <div class="alert alert-success">
        ✅ Added: <?php echo htmlspecialchars(implode(', ', $added_names)); ?> — <a href="dashboard.php">View Dashboard</a>
    </div>
    <?php endif; ?>
    <?php if($meal_error): ?>
    <div class="alert alert-danger">❌ <?php echo $meal_error; ?></div>
    <?php endif; ?>

    <!-- Manual Entry -->
    <form method="POST">
        <h2>📝 Add Food</h2>
        
        <label>Filter</label>
        <div style="margin-bottom:10px;">
            <span class="cat-btn active" onclick="filter('all',this)">🍽️ All</span>
            <span class="cat-btn" onclick="filter('Grains',this)">🌾 Grains</span>
            <span class="cat-btn" onclick="filter('Pulse',this)">🥗 Lentils</span>
            <span class="cat-btn" onclick="filter('Vegetable',this)">🥬 Veg</span>
            <span class="cat-btn" onclick="filter('Protein',this)">💪 Protein</span>
            <span class="cat-btn" onclick="filter('Fruit',this)">🍎 Fruits</span>
            <span class="cat-btn" onclick="filter('Gujarati',this)">🫓 Gujarati</span>
            <span class="cat-btn" onclick="filter('South Indian',this)">🫓 South</span>
            <span class="cat-btn" onclick="filter('Breakfast',this)">🌅 Breakfast</span>
            <span class="cat-btn" onclick="filter('Dairy',this)">🥛 Dairy</span>
            <span class="cat-btn" onclick="filter('Nuts',this)">🥜 Nuts</span>
            <span class="cat-btn" onclick="filter('Snack',this)">🍿 Snack</span>
            <span class="cat-btn" onclick="filter('Street Food',this)">🌭 Street Food</span>
            <span class="cat-btn" onclick="filter('Rice Dish',this)">🍚 Rice</span>
            <span class="cat-btn" onclick="filter('Indian Sweet',this)">🍮 Sweet</span>
        </div>

        <label>Select Food (hold Ctrl/Cmd to pick multiple — e.g. Chole + Paratha)</label>
        <select name="food[]" id="foodSelect" multiple size="8" required>
            <?php 
            if($food_result && mysqli_num_rows($food_result) > 0):
                while($food = mysqli_fetch_assoc($food_result)):
            ?>
            <option value="<?php echo htmlspecialchars($food['food_name']); ?>" data-cat="<?php echo $food['category'] ?? ''; ?>">
                <?php echo htmlspecialchars($food['food_name']); ?> (<?php echo $food['calories']; ?> kcal)
            </option>
            <?php endwhile; endif; ?>
            <option value="Other">✏️ Other (type manually)</option>
        </select>

        <div id="customDiv" style="display:none;">
            <label>Enter Food Name</label>
            <input type="text" name="custom_food" placeholder="e.g. My homemade dish">
        </div>

        <label>Quantity (servings, applied to each selected item)</label>
        <input type="number" name="quantity" value="1" min="0.5" step="0.5" required>

        <button type="submit" name="add_meal" class="btn btn-lg">✅ Add Selected Meal(s)</button>
    </form>

    <?php if($meal_added && !empty($custom_names)): ?>
    <div class="card">
        <h2>📊 Nutrition</h2>
        <div class="alert alert-info">
            <strong>✏️ Custom item(s) added:</strong> "<?php echo htmlspecialchars(implode(', ', $custom_names)); ?>"<br>
            <p style="margin-top:8px;">ℹ️ Nutrition info not available in database for these. They're still logged, but won't count toward the totals below.</p>
            <p>💡 <strong>Suggestion:</strong> For accurate tracking, select a similar food from the list above next time.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!empty($nutrition_rows)):
        $sum_cal = $sum_prot = $sum_carbs = $sum_fat = $sum_fiber = $sum_sugar = 0;
        foreach ($nutrition_rows as $nr) {
            $sum_cal   += $nr['calories'];
            $sum_prot  += $nr['protein'];
            $sum_carbs += $nr['carbs'];
            $sum_fat   += $nr['fat'];
            $sum_fiber += $nr['fiber'];
            $sum_sugar += $nr['sugar'];
        }
    ?>
    <div class="card">
        <h2>📊 Nutrition <?php echo count($nutrition_rows) > 1 ? '(combined total)' : '(per serving)'; ?></h2>
        <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(130px,1fr));">
            <div class="card"><h3>🔥 Calories</h3><h1><?php echo round($sum_cal,1); ?> kcal</h1></div>
            <div class="card"><h3>💪 Protein</h3><h1><?php echo round($sum_prot,1); ?>g</h1></div>
            <div class="card"><h3>🌾 Carbs</h3><h1><?php echo round($sum_carbs,1); ?>g</h1></div>
            <div class="card"><h3>🥑 Fat</h3><h1><?php echo round($sum_fat,1); ?>g</h1></div>
            <div class="card"><h3>🌿 Fiber</h3><h1><?php echo round($sum_fiber,1); ?>g</h1></div>
            <div class="card"><h3>🍬 Sugar</h3><h1><?php echo round($sum_sugar,1); ?>g</h1></div>
        </div>
        <small>Includes: <?php echo htmlspecialchars(implode(', ', array_column($nutrition_rows, 'food_name'))); ?></small>
    </div>
    <?php endif; ?>
</div>

<script>
function filter(cat, btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#foodSelect option').forEach(o => {
        if(o.dataset.cat) o.style.display = (cat==='all' || o.dataset.cat.includes(cat)) ? '' : 'none';
    });
}
document.getElementById('foodSelect').onchange = function() {
    const selected = Array.from(this.selectedOptions).map(o => o.value);
    document.getElementById('customDiv').style.display = selected.includes('Other') ? 'block' : 'none';
};
function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
</script>
</body>
</html>