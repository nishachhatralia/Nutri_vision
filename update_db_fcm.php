<?php
/**
 * NutriVision AI - FCM Database Migration Script
 *
 * Run this once to add the fcm_tokens table to an existing database.
 * This is for installations that already have the database set up
 * and just need the new push notification table.
 *
 * Usage: Open this file in your browser:
 *   http://localhost/nutrivision-ai-master/update_db_fcm.php
 */

include 'db.php';

echo "<h1>NutriVision AI - FCM Database Migration</h1>";

// Create fcm_tokens table
$sql = "CREATE TABLE IF NOT EXISTS fcm_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    device_info VARCHAR(255),
    is_valid TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "<p style='color: green; font-size: 1.2rem;'>✅ fcm_tokens table created successfully!</p>";
} else {
    echo "<p style='color: red; font-size: 1.2rem;'>❌ Error creating fcm_tokens table: " . mysqli_error($conn) . "</p>";
}

// Verify the table exists
$check = mysqli_query($conn, "SHOW TABLES LIKE 'fcm_tokens'");
if (mysqli_num_rows($check) > 0) {
    echo "<p style='color: green;'>✅ Verification: fcm_tokens table exists.</p>";

    // Show table structure
    $desc = mysqli_query($conn, "DESCRIBE fcm_tokens");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; font-family: sans-serif;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = mysqli_fetch_assoc($desc)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>✅ Migration Complete!</h2>";
echo "<p>The push notification system is now ready.</p>";
echo "<p>Next steps:</p>";
echo "<ol>";
echo "<li>Set up Firebase credentials in <code>.env</code></li>";
echo "<li>Place your service account JSON in <code>config/firebase-service-account.json</code></li>";
echo "<li>Log in and visit the dashboard to enable notifications</li>";
echo "<li>Start the scheduler: <code>php cron/run_scheduler.php</code></li>";
echo "</ol>";
?>