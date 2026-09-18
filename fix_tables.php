<?php
// Force recreate all tables
$conn = mysqli_connect("127.0.0.1", "root", "Admin@123", "", 3308);
if (!$conn) { die("Connection Failed: " . mysqli_connect_error()); }

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS nutrivision_db");
mysqli_select_db($conn, 'nutrivision_db');

echo "<h2>Creating database tables...</h2>";

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
$tables = ['reviews', 'chatbot_conversations', 'goals', 'water_intake', 'meal_history', 'meals', 'food_items', 'users'];
foreach ($tables as $t) {
    mysqli_query($conn, "DROP TABLE IF EXISTS $t");
    echo "<p>Dropped $t</p>";
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// Users
mysqli_query($conn, "CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT, gender VARCHAR(20),
    weight DECIMAL(5,2), height DECIMAL(5,2),
    goal VARCHAR(50), activity_level VARCHAR(50),
    daily_calorie_goal INT DEFAULT 2000,
    water_intake INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ users table created</p>";

// Food items
mysqli_query($conn, "CREATE TABLE food_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    food_name VARCHAR(100) NOT NULL UNIQUE,
    calories INT NOT NULL,
    protein DECIMAL(5,2) NOT NULL,
    carbs DECIMAL(5,2) NOT NULL,
    fat DECIMAL(5,2) NOT NULL,
    fiber DECIMAL(5,2) DEFAULT 0,
    sugar DECIMAL(5,2) DEFAULT 0,
    vitamins VARCHAR(150) DEFAULT '0mg',
    minerals VARCHAR(150) DEFAULT '0mg',
    category VARCHAR(50),
    image_url VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ food_items table created</p>";

// Meals
mysqli_query($conn, "CREATE TABLE meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    food_name VARCHAR(100),
    quantity INT DEFAULT 1,
    meal_type VARCHAR(50) DEFAULT 'general',
    meal_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ meals table created</p>";

// Meal history (used for the dashboard's "Today's Calories" card)
mysqli_query($conn, "CREATE TABLE meal_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    food_name VARCHAR(100),
    quantity INT DEFAULT 1,
    meal_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ meal_history table created</p>";

// Water intake
mysqli_query($conn, "CREATE TABLE water_intake (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount_ml INT DEFAULT 250,
    intake_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ water_intake table created</p>";

// Goals
mysqli_query($conn, "CREATE TABLE goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    target_calories INT DEFAULT 2000,
    target_protein DECIMAL(5,2) DEFAULT 50.00,
    target_carbs DECIMAL(5,2) DEFAULT 200.00,
    target_fat DECIMAL(5,2) DEFAULT 50.00,
    start_date DATE, end_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ goals table created</p>";

// Chatbot
mysqli_query($conn, "CREATE TABLE chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, user_message TEXT, bot_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ chatbot_conversations table created</p>";

// Reviews
mysqli_query($conn, "CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_review (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or die(mysqli_error($conn));
echo "<p style='color:green;'>✅ reviews table created</p>";

echo "<hr><h2 style='color:green;'>✅ ALL 8 TABLES CREATED!</h2>";
echo "<ol>
<li><a href='import_food.php'><strong>Step 1: Import Food Data</strong></a></li>
<li><a href='register.php'><strong>Step 2: Register</strong></a></li>
<li><a href='login.php'><strong>Step 3: Login</strong></a></li>
<li><a href='dashboard.php'><strong>Step 4: Dashboard</strong></a></li>
</ol>";
?>