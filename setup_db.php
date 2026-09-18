<?php
/**
 * NutriVision AI - Database Setup Script
 * Run this once to create all required tables
 */

include 'db.php';

echo "<h1>NutriVision AI - Database Setup</h1>";

// Create database if not exists
$create_db = "CREATE DATABASE IF NOT EXISTS nutrivision_db";
if (mysqli_query($conn, $create_db)) {
    echo "<p style='color: green;'>✅ Database 'nutrivision_db' ready</p>";
}

// Use the database
mysqli_select_db($conn, 'nutrivision_db');

// 1. Users table (add water_intake column for the water.php to work)
$sql1 = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT,
    gender VARCHAR(20),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    goal VARCHAR(50),
    activity_level VARCHAR(50),
    daily_calorie_goal INT DEFAULT 2000,
    water_intake INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (mysqli_query($conn, $sql1)) {
    echo "<p style='color: green;'>✅ Users table created</p>";
} else {
    echo "<p style='color: red;'>❌ Users table error: " . mysqli_error($conn) . "</p>";
}

// Check if columns exist, add if missing
$columns_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'daily_calorie_goal'");
if (mysqli_num_rows($columns_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD daily_calorie_goal INT DEFAULT 2000");
    echo "<p style='color: green;'>✅ Added daily_calorie_goal column</p>";
}

$columns_check2 = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'water_intake'");
if (mysqli_num_rows($columns_check2) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD water_intake INT DEFAULT 0");
    echo "<p style='color: green;'>✅ Added water_intake column</p>";
}

// 2. Food items table
$sql2 = "CREATE TABLE IF NOT EXISTS food_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    food_name VARCHAR(100) NOT NULL UNIQUE,
    calories INT NOT NULL,
    protein DECIMAL(5,2) NOT NULL,
    carbs DECIMAL(5,2) NOT NULL,
    fat DECIMAL(5,2) NOT NULL,
    fiber DECIMAL(5,2) DEFAULT 0,
    sugar DECIMAL(5,2) DEFAULT 0,
    cholesterol DECIMAL(5,2) DEFAULT 0,
    vitamins VARCHAR(150) DEFAULT '0mg',
    minerals VARCHAR(150) DEFAULT '0mg',
    category VARCHAR(50),
    image_url VARCHAR(255)
)";
if (mysqli_query($conn, $sql2)) {
    echo "<p style='color: green;'>✅ Food items table created</p>";
} else {
    echo "<p style='color: red;'>❌ Food items error: " . mysqli_error($conn) . "</p>";
}

// 3. Meals table
$sql3 = "CREATE TABLE IF NOT EXISTS meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    food_name VARCHAR(100),
    quantity INT DEFAULT 1,
    meal_type VARCHAR(50) DEFAULT 'general',
    meal_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql3)) {
    echo "<p style='color: green;'>✅ Meals table created</p>";
} else {
    echo "<p style='color: red;'>❌ Meals error: " . mysqli_error($conn) . "</p>";
}

// 3b. Meal history table (used for the dashboard's "Today's Calories" card)
$sql3b = "CREATE TABLE IF NOT EXISTS meal_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    food_name VARCHAR(100),
    quantity INT DEFAULT 1,
    meal_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql3b)) {
    echo "<p style='color: green;'>✅ Meal history table created</p>";
} else {
    echo "<p style='color: red;'>❌ Meal history error: " . mysqli_error($conn) . "</p>";
}

// 4. Water intake table
$sql4 = "CREATE TABLE IF NOT EXISTS water_intake (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount_ml INT DEFAULT 250,
    intake_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql4)) {
    echo "<p style='color: green;'>✅ Water intake table created</p>";
} else {
    echo "<p style='color: red;'>❌ Water intake error: " . mysqli_error($conn) . "</p>";
}

// 5. Goals table
$sql5 = "CREATE TABLE IF NOT EXISTS goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    target_calories INT DEFAULT 2000,
    target_protein DECIMAL(5,2) DEFAULT 50.00,
    target_carbs DECIMAL(5,2) DEFAULT 200.00,
    target_fat DECIMAL(5,2) DEFAULT 50.00,
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql5)) {
    echo "<p style='color: green;'>✅ Goals table created</p>";
} else {
    echo "<p style='color: red;'>❌ Goals error: " . mysqli_error($conn) . "</p>";
}

// 6. FCM tokens table (Web Push Notifications)
$sql6 = "CREATE TABLE IF NOT EXISTS fcm_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    device_info VARCHAR(255),
    is_valid TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql6)) {
    echo "<p style='color: green;'>✅ FCM tokens table created</p>";
} else {
    echo "<p style='color: red;'>❌ FCM tokens table error: " . mysqli_error($conn) . "</p>";
}

// 7. Chatbot conversations
$sql7 = "CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_message TEXT,
    bot_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
if (mysqli_query($conn, $sql7)) {
    echo "<p style='color: green;'>✅ Chatbot conversations table created</p>";
} else {
    echo "<p style='color: red;'>❌ Chatbot table error: " . mysqli_error($conn) . "</p>";
}

echo "<hr>";
echo "<h2>✅ Setup Complete!</h2>";
echo "<p>All database tables are created. Now:</p>";
echo "<ol>";
echo "<li><a href='import_food.php'>Click here to import food data</a></li>";
echo "<li><a href='register.php'>Register a new account</a></li>";
echo "<li><a href='login.php'>Login and use the app</a></li>";
echo "</ol>";
?>