<?php
/**
 * NutriVision AI - Save FCM Token Endpoint
 *
 * Called by the frontend after Firebase generates an FCM registration token.
 * Validates that the user is logged in, then stores the token in the
 * fcm_tokens table linked to the user's account.
 *
 * Request:
 *   POST /api/save_token.php
 *   Content-Type: application/json
 *   Body: { "token": "fcm_token_string", "device_info": "Chrome/Windows" }
 *
 * Response:
 *   { "success": true }  or  { "success": false, "error": "..." }
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../firebase-config.php';
require_once __DIR__ . '/../includes/fcm_service.php';

// --- Authentication check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// --- Only accept POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// --- Read JSON body ---
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);

if (!is_array($input)) {
    // Fallback to form POST
    $input = $_POST;
}

// --- Validate token ---
$token = trim($input['token'] ?? '');
if ($token === '' || strlen($token) < 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing FCM token']);
    exit;
}

// --- Sanitize device info ---
$deviceInfo = trim($input['device_info'] ?? '');
if (strlen($deviceInfo) > 255) {
    $deviceInfo = substr($deviceInfo, 0, 255);
}

// --- Save token to database ---
$result = fcm_save_token($conn, $userId, $token, $deviceInfo);

if ($result) {
    notification_log('INFO', "FCM token saved for user $userId");
    echo json_encode(['success' => true, 'message' => 'Token saved']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save token']);
}