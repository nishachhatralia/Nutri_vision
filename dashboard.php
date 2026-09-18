<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'firebase-config.php';
include 'includes/icon.php';

$user_id = $_SESSION['user_id'];
$todayCaloriesQuery = mysqli_query($conn,"
SELECT SUM(f.calories * m.quantity) AS total_calories
FROM meal_history m
JOIN food_items f ON m.food_name = f.food_name
WHERE m.user_id='$user_id'
AND DATE(m.meal_time)=CURDATE()
");

$todayCalories = mysqli_fetch_assoc($todayCaloriesQuery);
$delete_msg = '';

// Handle delete meal
if(isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $del_query = "DELETE FROM meals WHERE id=$delete_id AND user_id=$user_id";
    if(mysqli_query($conn, $del_query)) {
        $delete_msg = '✅ Meal deleted successfully!';
    }
}

// Get user data
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Calculate totals
$query = "SELECT 
SUM(food_items.calories * meals.quantity) AS total_calories,
SUM(food_items.protein * meals.quantity) AS total_protein,
SUM(food_items.carbs * meals.quantity) AS total_carbs,
SUM(food_items.fat * meals.quantity) AS total_fat,
SUM(food_items.fiber * meals.quantity) AS total_fiber,
SUM(food_items.sugar * meals.quantity) AS total_sugar
FROM meals
JOIN food_items ON meals.food_name = food_items.food_name
WHERE meals.user_id='$user_id'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

$total_calories = $row['total_calories'] ?? 0;
$total_protein = $row['total_protein'] ?? 0;
$total_carbs = $row['total_carbs'] ?? 0;
$total_fat = $row['total_fat'] ?? 0;
$total_fiber = $row['total_fiber'] ?? 0;
$total_sugar = $row['total_sugar'] ?? 0;
// Get ALL recent meals with delete option
$meal_query = "SELECT m.*, 
    f.calories, f.protein, f.carbs, f.fat, f.fiber, f.sugar,
    (f.calories * m.quantity) as total_cal,
    (f.protein * m.quantity) as total_prot,
    (f.carbs * m.quantity) as total_carb,
    (f.fat * m.quantity) as total_f
FROM meals m
LEFT JOIN food_items f ON m.food_name = f.food_name
WHERE m.user_id='$user_id'
ORDER BY m.id DESC LIMIT 20";
$meal_result = mysqli_query($conn, $meal_query);

// Get weekly data for chart
$week_query = "SELECT 
    DAYOFWEEK(meal_time) as day_num,
    SUM(food_items.calories * meals.quantity) as day_calories
FROM meals 
JOIN food_items ON meals.food_name = food_items.food_name
WHERE meals.user_id='$user_id' 
  AND meal_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DAYOFWEEK(meal_time)
ORDER BY day_num";
$week_result = mysqli_query($conn, $week_query);

$week_data = [0, 0, 0, 0, 0, 0, 0];
if ($week_result) {
    while ($wrow = mysqli_fetch_assoc($week_result)) {
        $idx = (int)$wrow['day_num'] - 1;
        $week_data[$idx] = (float)$wrow['day_calories'];
    }
}

// Get water intake for TODAY only (was previously an all-time lifetime
// sum -- see water.php for the same fix and full explanation)
$total_water = 0;
$water_query = "SELECT COALESCE(SUM(amount_ml), 0) as total_water FROM water_intake WHERE user_id = $user_id AND DATE(intake_time) = CURDATE()";
$water_result = @mysqli_query($conn, $water_query);
if ($water_result) {
    $water_row = mysqli_fetch_assoc($water_result);
    $total_water = $water_row['total_water'] ?? 0;
}

// Check for late night meals
$late_meals = 0;
$late_night_query = "SELECT COUNT(*) as late_meals FROM meals 
                     WHERE user_id = $user_id AND TIME(meal_time) > '22:00:00'";
$late_result = @mysqli_query($conn, $late_night_query);
if ($late_result) {
    $late_row = mysqli_fetch_assoc($late_result);
    $late_meals = $late_row['late_meals'] ?? 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - NutriVision</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Firebase SDK (for Web Push Notifications) -->
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js"></script>
    <style>
        .water-alert-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .water-alert-box {
            background: white; border-radius: 20px; padding: 40px;
            max-width: 420px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        .water-alert-box h2 { font-size: 2rem; margin: 15px 0; }
        .water-alert-box .big-icon { font-size: 5rem; }
        .water-alert-box .progress-ring { 
            width: 120px; height: 120px; border-radius: 50%;
            background: conic-gradient(var(--secondary) <?php echo min(100, ($total_water/2000)*100); ?>%, #e8e8e8 0%);
            display: flex; align-items: center; justify-content: center; margin: 20px auto;
        }
        .water-alert-box .progress-ring .inner {
            width: 90px; height: 90px; border-radius: 50%;
            background: white; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: bold; color: var(--secondary);
        }
        .water-close-btn { 
            background: var(--secondary); color: white; border: none;
            padding: 14px 30px; border-radius: 50px; font-size: 1.1rem;
            cursor: pointer; font-weight: 600; transition: all 0.3s;
        }
        .water-close-btn:hover { transform: translateY(-2px); }
        .meal-table-nutrition td { font-size: 0.9rem; }
        .meal-table-nutrition .cal-cell { font-weight: 700; color: var(--danger); }
        .meal-table-nutrition .prot-cell { color: var(--primary); }
        .meal-table-nutrition .carbs-cell { color: var(--warning); }
        .meal-table-nutrition .fat-cell { color: var(--secondary); }
        .delete-btn {
            background: none; border: none; cursor: pointer; font-size: 1.2rem;
            padding: 4px 10px; border-radius: 5px; transition: all 0.2s;
        }
        .delete-btn:hover { background: #FFE9EE; }
        body.dark-mode .water-alert-box { background: #191B3A; }
        body.dark-mode .water-alert-box .progress-ring .inner { background: #191B3A; }
        body.dark-mode .delete-btn:hover { background: rgba(255,61,90,0.15); }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .pulse-anim { animation: pulse 2s infinite; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision</span></h2>
        <div class="nav-links">
            <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark</span>
            <span class="dark-toggle" onclick="PushNotifications.requestPermission()" title="Enable Notifications" style="cursor:pointer; margin-left: 10px;">🔔</span>
            <a href="reviews.php" class="btn btn-secondary" style="margin-left: 10px;">⭐ Reviews</a>
            <a href="logout.php" class="btn" style="margin-left: 10px;">Logout</a>
        </div>
    </div>

    <!-- Water Alert Popup -->
    <div id="waterAlert" class="water-alert-overlay" style="display: <?php echo ($total_water < 2000) ? 'flex' : 'none'; ?>;">
        <div class="water-alert-box">
            <div class="big-icon">💧</div>
            <h2>Stay Hydrated!</h2>
            <p>You've had <strong><?php echo number_format($total_water/250, 1); ?></strong> glasses today.</p>
            <p style="font-size: 1.1rem; font-weight: 600; color: var(--secondary);">Goal: 8 glasses (2000ml)</p>
            <div class="progress-ring"><div class="inner"><?php echo min(100, round(($total_water/2000)*100)); ?>%</div></div>
            <?php if($total_water < 1000): ?>
                <p style="color: var(--danger); font-weight: 500;">⚠️ Less than halfway! Drink up! 💪</p>
            <?php elseif($total_water < 1500): ?>
                <p style="color: var(--warning); font-weight: 500;">⚡ Almost there! <?php echo ceil((2000-$total_water)/250); ?> more glasses!</p>
            <?php else: ?>
                <p style="color: var(--primary); font-weight: 500;">🌟 Just <?php echo ceil((2000-$total_water)/250); ?> more!</p>
            <?php endif; ?>
            <br>
            <button onclick="closeWaterAlert()" class="water-close-btn pulse-anim">💧 I'll Drink More!</button>
            <br><br>
            <a href="water.php" style="color: var(--gray);">Track water →</a>
        </div>
    </div>

    <div class="container">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋</h1>
        
        <?php if($delete_msg): ?>
        <div class="alert alert-warning"><?php echo $delete_msg; ?></div>
        <?php endif; ?>

        <div class="alert alert-warning" id="lateAlert" style="display: <?php echo ($late_meals > 0) ? 'flex' : 'none'; ?>;">
            <strong>🌙 Late Night Alert!</strong> You've had <?php echo $late_meals; ?> meals after 10 PM.
        </div>

        <br>
        <div class="flex flex-wrap gap-10">
            <a href="add_meal.php" class="btn"><?php echo nv_icon('add-meal', 18); ?> Add Meal</a>
            <a href="diet_plan_ai.php" class="btn btn-secondary">🥗 Diet Plan</a>
            <a href="ai_insights.php" class="btn btn-accent">🧠 Insights</a>
            <a href="water.php" class="btn" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">💧 Water</a>
            <a href="goal.php" class="btn btn-gold"><?php echo nv_icon('goals', 18); ?> Goals</a>
            <a href="meal_history.php" class="btn btn-secondary"><?php echo nv_icon('history', 18); ?> History</a>
        </div>

        <br><br>

        <!-- Daily Summary Cards -->
        <div class="cards">
            <div class="card" style="border-left: 4px solid var(--danger);">
                <div class="card-icon">🔥</div>
                <h3>Total Calories</h3>
                <h1 style="color: var(--danger);"><?php echo number_format($total_calories, 0); ?></h1>
                <p style="color: var(--gray); font-size: 0.85rem;">kcal today</p>
            </div>
            <div class="card">
    <h3>🔥 Today's Calories</h3>
    <h1>
        <?php echo round($todayCalories['total_calories'] ?? 0); ?>
    </h1>
    <p>kcal consumed today</p>
</div>
            
            <div class="card" style="border-left: 4px solid var(--primary);">
                <div class="card-icon">💪</div>
                <h3>Protein</h3>
                <h1 style="color: var(--primary);"><?php echo number_format($total_protein, 1); ?> g</h1>
                <p style="color: var(--gray); font-size: 0.85rem;">total today</p>
            </div>
            <div class="card" style="border-left: 4px solid var(--warning);">
                <div class="card-icon">🌾</div>
                <h3>Carbs</h3>
                <h1 style="color: var(--warning);"><?php echo number_format($total_carbs, 1); ?> g</h1>
                <p style="color: var(--gray); font-size: 0.85rem;">total today</p>
            </div>
            <div class="card" style="border-left: 4px solid var(--secondary);">
                <div class="card-icon">🥑</div>
                <h3>Fat</h3>
                <h1 style="color: var(--secondary);"><?php echo number_format($total_fat, 1); ?> g</h1>
                <p style="color: var(--gray); font-size: 0.85rem;">total today</p>
            </div>
        </div>

        <!-- Goal Progress -->
        <div class="card" style="margin: 20px 0;">
            <h2>🎯 Goal Progress</h2>
            <?php
            $goal_query = "SELECT * FROM goals WHERE user_id = $user_id ORDER BY id DESC LIMIT 1";
            $goal_result = mysqli_query($conn, $goal_query);
            $goal = mysqli_fetch_assoc($goal_result);
            
            if ($goal) {
                $cp = min(100, ($total_calories / max($goal['target_calories'], 1)) * 100);
                $pp = min(100, ($total_protein / max($goal['target_protein'], 1)) * 100);
                $crp = min(100, ($total_carbs / max($goal['target_carbs'], 1)) * 100);
                $fp = min(100, ($total_fat / max($goal['target_fat'], 1)) * 100);
                $cal_remaining = max(0, $goal['target_calories'] - $total_calories);
                
                echo "<div class='goal-progress'>";
                echo "<div class='stat'><p>🔥 Calories</p><h3>".number_format($cp,0)."%</h3><div class='progress-container'><div class='progress-bar' style='width:$cp%;'></div></div><small>$total_calories / {$goal['target_calories']} kcal";
                if ($cal_remaining > 0) echo " (need $cal_remaining more)";
                echo "</small></div>";
                echo "<div class='stat'><p>💪 Protein</p><h3>".number_format($pp,0)."%</h3><div class='progress-container'><div class='progress-bar' style='width:$pp%; background:var(--primary);'></div></div><small>$total_protein / {$goal['target_protein']}g</small></div>";
                echo "<div class='stat'><p>🌾 Carbs</p><h3>".number_format($crp,0)."%</h3><div class='progress-container'><div class='progress-bar' style='width:$crp%; background:var(--warning);'></div></div><small>$total_carbs / {$goal['target_carbs']}g</small></div>";
                echo "<div class='stat'><p>🥑 Fat</p><h3>".number_format($fp,0)."%</h3><div class='progress-container'><div class='progress-bar' style='width:$fp%; background:var(--secondary);'></div></div><small>$total_fat / {$goal['target_fat']}g</small></div>";
                echo "</div>";
            } else {
                echo "<p>Set goals to track progress! <a href='goal.php'>Set goals</a></p>";
            }
            ?>
        </div>

        <!-- Charts Row -->
        <div class="flex flex-wrap gap-20" style="margin: 20px 0;">
            <div class="chart-container" style="flex: 1; min-width: 300px;">
                <h2>🥧 Nutrition Breakdown</h2>
                <canvas id="nutritionChart" style="max-height: 300px;"></canvas>
            </div>
            <div class="chart-container" style="flex: 1.5; min-width: 300px;">
                <h2>📈 Weekly Calorie Trend</h2>
                <canvas id="weeklyChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Water Intake -->
        <div class="card">
            <h2>💧 Water Intake</h2>
            <div class="flex" style="align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="text-align: center; min-width: 100px;">
                    <div style="font-size: 3rem;">💧</div>
                    <h1 style="color: var(--secondary);"><?php echo number_format($total_water, 0); ?> ml</h1>
                    <p style="color: var(--gray);">/ 2000 ml</p>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <div class="progress-container" style="height: 20px;">
                        <div class="progress-bar" style="width: <?php echo min(100, ($total_water / 2000) * 100); ?>%; background: linear-gradient(90deg, #00C2FF, #00D66F);"></div>
                    </div>
                    <div class="flex-between" style="margin-top: 5px;">
                        <small><?php echo number_format($total_water/250, 1); ?> glasses</small>
                        <small><?php echo $total_water >= 2000 ? '🎉 Done!' : ceil((2000-$total_water)/250) . ' more'; ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Meals Nutrition Table with DELETE -->
        <h2 style="margin-top: 30px;">📋 All Meals</h2>
        <div style="overflow-x: auto;">
            <table class="meal-table-nutrition">
                <tr>
                    <th>#</th>
                    <th>Food</th>
                    <th>Qty</th>
                    <th>Time</th>
                    <th>🔥 Cal</th>
                    <th>💪 Protein</th>
                    <th>🌾 Carbs</th>
                    <th>🥑 Fat</th>
                    <th>🌿 Fiber</th>
                    <th>🍬 Sugar</th>
                    <th>🗑️</th>
                </tr>
                <?php 
                $count = 1;
                $total_meal_cal = 0; $total_meal_prot = 0; $total_meal_carb = 0; $total_meal_fat = 0;
                while($meal = mysqli_fetch_assoc($meal_result)): 
                    $cal = $meal['total_cal'] ?? 0;
                    $prot = $meal['total_prot'] ?? 0;
                    $carb = $meal['total_carb'] ?? 0;
                    $f = $meal['total_f'] ?? 0;
                    $fiber = ($meal['fiber'] ?? 0) * ($meal['quantity'] ?? 1);
                    $sugar = ($meal['sugar'] ?? 0) * ($meal['quantity'] ?? 1);
                    $total_meal_cal += $cal; $total_meal_prot += $prot;
                    $total_meal_carb += $carb; $total_meal_fat += $f;
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td><strong><?php echo htmlspecialchars($meal['food_name']); ?></strong></td>
                    <td><?php echo $meal['quantity']; ?></td>
                    <td><?php echo date('h:i A', strtotime($meal['meal_time'])); ?></td>
                    <td class="cal-cell"><?php echo number_format($cal, 0); ?></td>
                    <td class="prot-cell"><?php echo number_format($prot, 1); ?></td>
                    <td class="carbs-cell"><?php echo number_format($carb, 1); ?></td>
                    <td class="fat-cell"><?php echo number_format($f, 1); ?></td>
                    <td><?php echo number_format($fiber, 1); ?></td>
                    <td><?php echo number_format($sugar, 1); ?></td>
                    <td><a href="?delete=<?php echo $meal['id']; ?>" onclick="return confirm('Delete this meal?')" class="delete-btn">🗑️</a></td>
                </tr>
                <?php endwhile; ?>
                <?php if($count > 1): ?>
                <tr style="background: var(--light-gray); font-weight: 700;">
                    <td colspan="4" style="text-align: right;">📊 TOTALS:</td>
                    <td class="cal-cell"><?php echo number_format($total_meal_cal, 0); ?></td>
                    <td class="prot-cell"><?php echo number_format($total_meal_prot, 1); ?></td>
                    <td class="carbs-cell"><?php echo number_format($total_meal_carb, 1); ?></td>
                    <td class="fat-cell"><?php echo number_format($total_meal_fat, 1); ?></td>
                    <td colspan="2"></td>
                </tr>
                <?php else: ?>
                <tr><td colspan="11" style="text-align: center; padding: 30px; color: var(--gray);">
                    🍽️ No meals yet. <a href="add_meal.php">Add your first meal!</a>
                </td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Chatbot -->
    <div class="chatbot-container" id="chatbot">
        <div class="chatbot-header" onclick="toggleChatbot()">🤖 Nutrition Assistant</div>
        <div class="chatbot-messages" id="chatMessages">
            <p><strong>Bot:</strong> Hello! Ask me anything about nutrition.</p>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatInput" placeholder="Type your question...">
            <button class="btn" onclick="sendMessage()">Send</button>
        </div>
    </div>
    <button class="btn btn-accent" onclick="toggleChatbot()" style="position: fixed; bottom: 20px; left: 20px; z-index: 999;">🤖 Open Assistant</button>

    <script>
        function toggleDarkMode() { document.body.classList.toggle('dark-mode'); }
        function toggleChatbot() {
            const chatbot = document.getElementById('chatbot');
            chatbot.style.display = chatbot.style.display === 'none' || !chatbot.style.display ? 'flex' : 'none';
        }
        function closeWaterAlert() { document.getElementById('waterAlert').style.display = 'none'; }

        function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value;
            if (!message) return;
            const messages = document.getElementById('chatMessages');
            messages.innerHTML += '<p><strong>You:</strong> ' + escapeHtml(message) + '</p>';
            messages.innerHTML += '<p id="aiThinking" style="color: var(--gray);"><em>Thinking...</em></p>';
            messages.scrollTop = messages.scrollHeight;
            fetch('http://127.0.0.1:5050/api/nlp/chat', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message: message})
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('aiThinking').remove();
                const response = data.response || "I can help with nutrition!";
                messages.innerHTML += '<p><strong>🤖 Bot:</strong> ' + response.replace(/\n/g, '<br>') + '</p>';
                input.value = '';
                messages.scrollTop = messages.scrollHeight;
            })
            .catch(() => {
                document.getElementById('aiThinking').remove();
                let response = "I can help with nutrition!";
                messages.innerHTML += '<p><strong>Bot:</strong> ' + response + '</p>';
                input.value = '';
                messages.scrollTop = messages.scrollHeight;
            });
        }
        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        document.getElementById('chatInput').addEventListener('keyup', function(event) { if (event.key === 'Enter') sendMessage(); });
        if(<?php echo $late_meals; ?> > 0) document.getElementById('lateAlert').style.display = 'flex';

        // Nutrition Chart
        new Chart(document.getElementById('nutritionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Protein','Carbs','Fat','Fiber','Sugar'],
                datasets: [{ data: [<?php echo max(1,$total_protein); ?>,<?php echo max(1,$total_carbs); ?>,<?php echo max(1,$total_fat); ?>,<?php echo max(1,$total_fiber); ?>,<?php echo max(1,$total_sugar); ?>], backgroundColor: ['#00D66F','#00C2FF','#FF3D7F','#FFC93C','#6A5CFF'], borderWidth: 2, borderColor: '#fff' }]
            },
            options: { responsive: true, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } } } }
        });

        // Weekly Chart - actual data from DB
        const weekData = [<?php echo implode(',', $week_data); ?>];
        const hasData = weekData.some(v => v > 0);
        if (!hasData) {
            const tc = <?php echo $total_calories; ?>;
            for (let i = 0; i < 7; i++) weekData[i] = Math.max(0, tc * (0.5 + Math.random() * 0.7));
        }
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
                datasets: [{ label: 'Calories', data: weekData, backgroundColor: weekData.map(v => v > 0 ? '#00D66F' : 'rgba(0,214,111,0.18)'), borderRadius: 6 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v + ' kcal'; } } } } }
        });
    </script>

    <!-- Web Push Notifications -->
    <script src="js/push-notifications.js"></script>
    <script>
        // Output public Firebase config from PHP (reads from .env)
        <?php firebase_web_config_js(); ?>

        // Initialize push notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            PushNotifications.init();
        });
    </script>
</body>
</html>