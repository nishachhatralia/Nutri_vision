<?php
// add_reviews_table.php
// One-time, additive migration: creates the "reviews" table only.
// Does NOT touch or drop any existing tables/data. Safe to re-run.
// Run once in your browser, e.g. http://localhost/add_reviews_table.php

include 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_review (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql)) {
    echo "✅ 'reviews' table is ready. You can now use reviews.php.";
} else {
    echo "❌ Error creating 'reviews' table: " . mysqli_error($conn);
}
?>