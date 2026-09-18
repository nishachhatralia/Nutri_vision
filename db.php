<?php
// Fail fast instead of hanging/buffering if the host or port is wrong
ini_set('default_socket_timeout', 5);

// Make mysqli return false on failure instead of throwing an uncaught exception,
// so the friendly error message below actually gets shown.
mysqli_report(MYSQLI_REPORT_OFF);

// ---- EDIT THESE TO MATCH YOUR XAMPP MYSQL SETUP ----
$db_host = "localhost:3308";   // Check XAMPP Control Panel -> MySQL -> Config -> my.ini -> "port="
$db_user = "root";
$db_pass = "";     // XAMPP's default root password is BLANK unless you changed it yourself
$db_name = "nutrivision_db";
// ------------------------------------------------------

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("
    <div style='font-family:sans-serif;max-width:600px;margin:60px auto;padding:20px;border:2px solid #e74c3c;border-radius:10px;background:#fff5f5;'>
        <h2 style='color:#e74c3c;'>❌ Database Connection Failed</h2>
        <p><strong>Error:</strong> " . htmlspecialchars(mysqli_connect_error()) . "</p>
        <p><strong>How to fix:</strong></p>
        <ol>
            <li>Open XAMPP Control Panel</li>
            <li>Click <em>Config</em> next to MySQL &rarr; <em>my.ini</em></li>
            <li>Find the line starting with <code>port=</code></li>
            <li>Put that exact number into the <code>\$db_port</code> variable at the top of <code>db.php</code></li>
            <li>Also make sure the MySQL service is actually started (green/running in the XAMPP panel)</li>
        </ol>
    </div>
    ");
}