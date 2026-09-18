<?php
/**
 * NutriVision AI - Notification Status Endpoint
 *
 * Returns the current notification permission state for the logged-in user.
 * The frontend uses this to decide whether to prompt for permission.
 *
 * Request:
 *   GET /api/notification_status.php
 *
 * Response:
 *   {
 *     "success": true,
 *     "firebase_configured": true,
 *     "token_count": 2,
 *     "has_tokens": true
 *   }
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

// --- Get user's token count ---
$tokens = fcm_get_user_tokens($conn, $userId);

echo json_encode([
    'success'             => true,
    'firebase_configured' => firebase_is_configured(),
    'token_count'         => count($tokens),
    'has_tokens'          => count($tokens) > 0,
]);