<?php
/**
 * NutriVision AI - Remove FCM Token Endpoint
 *
 * Called by the frontend when the user revokes notification permission
 * or when the token is no longer valid.  Removes the token from the
 * fcm_tokens table so the server stops sending to it.
 *
 * Request:
 *   POST /api/remove_token.php
 *   Content-Type: application/json
 *   Body: { "token": "fcm_token_string" }
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
    $input = $_POST;
}

// --- Validate token ---
$token = trim($input['token'] ?? '');
if ($token === '' || strlen($token) < 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing FCM token']);
    exit;
}

// --- Remove token from database ---
fcm_remove_token($conn, $token);

notification_log('INFO', "FCM token removed for user $userId (permission revoked)");

echo json_encode(['success' => true, 'message' => 'Token removed']);