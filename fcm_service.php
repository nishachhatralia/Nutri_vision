<?php
/**
 * NutriVision AI - Firebase Cloud Messaging (FCM) Service
 *
 * This module provides server-side push notification sending using the
 * FCM HTTP v1 API.  It handles:
 *
 *   - OAuth2 access token generation (JWT signed with RS256 via OpenSSL)
 *   - Access token caching (tokens are valid for ~1 hour)
 *   - Multicast sending using curl_multi for parallel HTTP requests
 *   - Automatic removal of invalid / expired FCM registration tokens
 *   - Delivery logging (success + failure)
 *
 * No external Composer packages are required — only PHP's built-in
 * openssl and cURL extensions (both enabled in XAMPP by default).
 *
 * The Firebase ADMIN credentials (service account private key) are read
 * from the .env file and are NEVER exposed to the frontend.
 */

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/notification_logger.php';

// Re-use the existing mysqli connection from db.php if available
// (db.php creates $conn).  If not available, we create our own.
global $conn;
if (!isset($conn)) {
    require_once dirname(__DIR__) . '/db.php';
}

/* ================================================================
 *  Section 1 — OAuth2 Access Token (JWT + RS256)
 * ================================================================ */

/**
 * Base64URL-encode a string (RFC 4648 §5, no padding).
 */
function fcm_base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64URL-decode a string.
 */
function fcm_base64url_decode($data)
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Build and sign a JWT for Google OAuth2 server-to-server auth.
 *
 * @param array  $serviceAccount  Decoded service account JSON.
 * @param string $scope           OAuth2 scope.
 * @return string|null  Signed JWT, or null on failure.
 */
function fcm_build_jwt($serviceAccount, $scope = 'https://www.googleapis.com/auth/firebase.messaging')
{
    if (empty($serviceAccount['private_key']) || empty($serviceAccount['client_email'])) {
        return null;
    }

    $now      = time();
    $header   = ['alg' => 'RS256', 'typ' => 'JWT'];
    $payload  = [
        'iss'   => $serviceAccount['client_email'],
        'scope' => $scope,
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ];

    $base64Header  = fcm_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $base64Payload = fcm_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signingInput  = $base64Header . '.' . $base64Payload;

    $signature = '';
    $ok = openssl_sign(
        $signingInput,
        $signature,
        $serviceAccount['private_key'],
        'sha256WithRSAEncryption'
    );

    if (!$ok) {
        notification_log('ERROR', 'JWT signing failed: ' . openssl_error_string());
        return null;
    }

    return $signingInput . '.' . fcm_base64url_encode($signature);
}

/**
 * Exchange a signed JWT for an OAuth2 access token.
 *
 * @param string $jwt  Signed JWT from fcm_build_jwt().
 * @return array|null  ['access_token' => ..., 'expires_in' => ...] or null.
 */
function fcm_fetch_access_token($jwt)
{
    $postFields = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT       => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        notification_log('ERROR', 'OAuth2 token request failed: ' . $error);
        return null;
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data['access_token'])) {
        notification_log('ERROR', 'OAuth2 token request returned HTTP ' . $httpCode, ['response' => $response]);
        return null;
    }

    return $data;
}

/**
 * Get a valid OAuth2 access token, using a file-based cache to avoid
 * regenerating the JWT on every call.
 *
 * @return string|null  Access token, or null if Firebase isn't configured.
 */
function fcm_get_access_token()
{
    $cacheFile = dirname(__DIR__) . '/config/.fcm_access_token_cache.json';

    // Check cache first
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (!empty($cached['access_token']) && !empty($cached['expires_at'])) {
            // Refresh 5 minutes before expiry
            if (time() < $cached['expires_at'] - 300) {
                return $cached['access_token'];
            }
        }
    }

    // Generate a new token
    $serviceAccount = firebase_service_account();
    if (!$serviceAccount) {
        notification_log('ERROR', 'Firebase service account not found. Check FIREBASE_SERVICE_ACCOUNT_PATH in .env');
        return null;
    }

    $jwt = fcm_build_jwt($serviceAccount);
    if (!$jwt) {
        return null;
    }

    $tokenData = fcm_fetch_access_token($jwt);
    if (!$tokenData) {
        return null;
    }

    // Cache the token
    $cache = [
        'access_token' => $tokenData['access_token'],
        'expires_at'   => time() + (int)($tokenData['expires_in'] ?? 3600),
    ];

    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, json_encode($cache), LOCK_EX);

    return $tokenData['access_token'];
}

/* ================================================================
 *  Section 2 — Database Helpers (fcm_tokens table)
 * ================================================================ */

/**
 * Save or update an FCM token for a user.
 * Supports multiple devices per user (one row per token).
 *
 * @param mysqli $conn
 * @param int    $userId
 * @param string $token
 * @param string $deviceInfo  Optional browser/device description.
 * @return bool
 */
function fcm_save_token($conn, $userId, $token, $deviceInfo = '')
{
    $userId     = (int)$userId;
    $token      = mysqli_real_escape_string($conn, $token);
    $deviceInfo = mysqli_real_escape_string($conn, $deviceInfo);

    // Check if token already exists
    $check = mysqli_query($conn, "SELECT id FROM fcm_tokens WHERE token = '$token' LIMIT 1");

    if (mysqli_num_rows($check) > 0) {
        // Token exists — update user_id (in case user re-logged in on same browser)
        // and refresh last_used
        mysqli_query($conn, "UPDATE fcm_tokens SET user_id = $userId, last_used_at = NOW(), is_valid = 1 WHERE token = '$token'");
    } else {
        // New token
        mysqli_query($conn, "INSERT INTO fcm_tokens (user_id, token, device_info, is_valid) VALUES ($userId, '$token', '$deviceInfo', 1)");
    }

    return true;
}

/**
 * Remove an FCM token (e.g. when user revokes permission).
 *
 * @param mysqli $conn
 * @param string $token
 * @return bool
 */
function fcm_remove_token($conn, $token)
{
    $token = mysqli_real_escape_string($conn, $token);
    mysqli_query($conn, "DELETE FROM fcm_tokens WHERE token = '$token'");
    return true;
}

/**
 * Fetch all valid FCM tokens, grouped by user.
 *
 * @param mysqli $conn
 * @return array  [ [ 'user_id' => int, 'token' => string ], ... ]
 */
function fcm_get_all_tokens($conn)
{
    $result = mysqli_query($conn, "SELECT user_id, token FROM fcm_tokens WHERE is_valid = 1 ORDER BY user_id");
    $tokens = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tokens[] = $row;
        }
    }
    return $tokens;
}

/**
 * Fetch all valid FCM tokens for a specific user.
 *
 * @param mysqli $conn
 * @param int    $userId
 * @return array
 */
function fcm_get_user_tokens($conn, $userId)
{
    $userId = (int)$userId;
    $result = mysqli_query($conn, "SELECT token FROM fcm_tokens WHERE user_id = $userId AND is_valid = 1");
    $tokens = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tokens[] = $row['token'];
        }
    }
    return $tokens;
}

/**
 * Mark a token as invalid and remove it from the database.
 *
 * @param mysqli $conn
 * @param string $token
 * @param string $reason
 * @return void
 */
function fcm_invalidate_token($conn, $token, $reason = '')
{
    $token = mysqli_real_escape_string($conn, $token);
    mysqli_query($conn, "DELETE FROM fcm_tokens WHERE token = '$token'");
    notification_log_token_removed($token, $reason);
}

/* ================================================================
 *  Section 3 — Sending Notifications (FCM HTTP v1 API)
 * ================================================================ */

/**
 * Build the JSON payload for a single FCM v1 message.
 *
 * @param string $token   FCM registration token.
 * @param string $title   Notification title.
 * @param string $body    Notification body.
 * @param array  $data    Optional data payload (key-value pairs).
 * @param string $icon    Optional icon URL (full-colour, shown in the
 *                        notification body / expanded view).
 * @param string $url     Optional click URL.
 * @param string $badge   Optional badge URL (Android status bar --
 *                        MUST be a white silhouette on a transparent
 *                        background; Android ignores colour here and
 *                        alpha-blends the whole image to one tint, so a
 *                        solid-filled icon shows up as a plain colour
 *                        blob instead of a recognisable glyph).
 * @return array
 */
function fcm_build_message($token, $title, $body, $data = [], $icon = '/notification-icon.png', $url = null, $badge = '/notification-badge.png')
{
    $message = [
        'message' => [
            'token'        => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'webpush' => [
                'notification' => [
                    'icon'  => $icon,
                    'badge' => $badge,
                    'tag'   => 'meal-reminder',
                ],
                'fcm_options' => [
                    'analytics_label' => 'meal_reminder',
                ],
            ],
        ],
    ];

    if (!empty($data)) {
        $message['message']['data'] = $data;
    }

    if ($url) {
        $message['message']['webpush']['fcm_options']['link'] = $url;
    }

    return $message;
}

/**
 * Send a single notification to one FCM token.
 *
 * @param string $token
 * @param string $title
 * @param string $body
 * @param array  $data
 * @return array  ['success' => bool, 'error' => string|null, 'invalid' => bool]
 */
function fcm_send_to_token($token, $title, $body, $data = [])
{
    $accessToken = fcm_get_access_token();
    if (!$accessToken) {
        return ['success' => false, 'error' => 'No access token', 'invalid' => false];
    }

    $projectId = firebase_project_id();
    $url       = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
    $payload   = fcm_build_message($token, $title, $body, $data);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'cURL: ' . $error, 'invalid' => false];
    }

    $responseData = json_decode($response, true);

    // HTTP 200 = success
    if ($httpCode === 200) {
        return ['success' => true, 'error' => null, 'invalid' => false];
    }

    // Check for invalid token errors
    $errorCode = $responseData['error']['details'][0]['errorCode'] ??
                 $responseData['error']['status'] ??
                 'UNKNOWN';
    $errorMsg  = $responseData['error']['message'] ?? 'Unknown error';

    // These error codes indicate the token is no longer valid
    $invalidCodes = ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND', 'UNAUTHENTICATED'];
    $isInvalid = in_array($errorCode, $invalidCodes) ||
                 stripos($errorMsg, 'UNREGISTERED') !== false ||
                 stripos($errorMsg, 'invalid registration') !== false ||
                 stripos($errorMsg, 'not a valid FCM') !== false;

    return [
        'success' => false,
        'error'   => "$errorCode: $errorMsg",
        'invalid' => $isInvalid,
    ];
}

/**
 * Send a notification to MANY tokens using curl_multi for parallel requests.
 *
 * - Sends in configurable batch sizes (default 50 concurrent requests).
 * - Continues even if individual sends fail.
 * - Removes invalid tokens automatically.
 * - Logs every success and failure.
 *
 * @param array  $tokensWithUsers  [ ['user_id' => int, 'token' => string], ... ]
 * @param string $title
 * @param string $body
 * @param array  $data              Optional data payload.
 * @param int    $batchSize         Concurrent requests per batch (default 50).
 * @return array  ['sent' => int, 'succeeded' => int, 'failed' => int, 'invalid_removed' => int]
 */
function fcm_send_multicast($tokensWithUsers, $title, $body, $data = [], $batchSize = 50)
{
    global $conn;

    $total       = count($tokensWithUsers);
    $succeeded   = 0;
    $failed      = 0;
    $invalidRemoved = 0;

    if ($total === 0) {
        notification_log('INFO', "No FCM tokens to send to. Title: $title");
        return ['sent' => 0, 'succeeded' => 0, 'failed' => 0, 'invalid_removed' => 0];
    }

    $accessToken = fcm_get_access_token();
    if (!$accessToken) {
        notification_log('ERROR', "Cannot send multicast: no access token. Title: $title");
        return ['sent' => 0, 'succeeded' => 0, 'failed' => $total, 'invalid_removed' => 0];
    }

    $projectId = firebase_project_id();
    $apiUrl    = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

    notification_log('INFO', "Starting multicast send", [
        'total_tokens' => $total,
        'title'        => $title,
        'batch_size'   => $batchSize,
    ]);

    // Process in batches
    for ($offset = 0; $offset < $total; $offset += $batchSize) {
        $batch = array_slice($tokensWithUsers, $offset, $batchSize);
        $mh    = curl_multi_init();
        $handles = [];

        foreach ($batch as $i => $item) {
            $payload = fcm_build_message($item['token'], $title, $body, $data);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            curl_multi_add_handle($mh, $ch);
            $handles[$i] = [
                'handle'  => $ch,
                'token'   => $item['token'],
                'user_id' => $item['user_id'],
            ];
        }

        // Execute the batch
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 1.0); // Wait for activity (1s timeout)
            }
        } while ($active && $status === CURLM_OK);

        // Process responses
        foreach ($handles as $i => $info) {
            $ch       = $info['handle'];
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = curl_multi_getcontent($ch);
            $error    = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);

            $token  = $info['token'];
            $userId = $info['user_id'];

            if ($error) {
                $failed++;
                notification_log_failure($userId, $token, $title, 'cURL: ' . $error);
                continue;
            }

            $responseData = json_decode($response, true);

            if ($httpCode === 200) {
                $succeeded++;
                notification_log_success($userId, $token, $title);
            } else {
                $failed++;
                $errorCode = $responseData['error']['details'][0]['errorCode'] ??
                             $responseData['error']['status'] ?? 'UNKNOWN';
                $errorMsg  = $responseData['error']['message'] ?? 'Unknown error';

                $invalidCodes = ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND', 'UNAUTHENTICATED'];
                $isInvalid = in_array($errorCode, $invalidCodes) ||
                             stripos($errorMsg, 'UNREGISTERED') !== false ||
                             stripos($errorMsg, 'invalid registration') !== false ||
                             stripos($errorMsg, 'not a valid FCM') !== false;

                notification_log_failure($userId, $token, $title, "$errorCode: $errorMsg");

                if ($isInvalid && $conn) {
                    fcm_invalidate_token($conn, $token, "$errorCode: $errorMsg");
                    $invalidRemoved++;
                }
            }
        }

        curl_multi_close($mh);
    }

    notification_log('INFO', "Multicast send complete", [
        'title'            => $title,
        'total'            => $total,
        'succeeded'        => $succeeded,
        'failed'           => $failed,
        'invalid_removed'  => $invalidRemoved,
    ]);

    return [
        'sent'             => $total,
        'succeeded'        => $succeeded,
        'failed'           => $failed,
        'invalid_removed'  => $invalidRemoved,
    ];
}

/**
 * Send a notification to all users with valid FCM tokens.
 *
 * @param string $title
 * @param string $body
 * @param array  $data
 * @return array
 */
function fcm_send_to_all($title, $body, $data = [])
{
    global $conn;
    $tokens = fcm_get_all_tokens($conn);
    return fcm_send_multicast($tokens, $title, $body, $data);
}

/**
 * Send a notification to a specific user (all their devices).
 *
 * @param int    $userId
 * @param string $title
 * @param string $body
 * @param array  $data
 * @return array
 */
function fcm_send_to_user($userId, $title, $body, $data = [])
{
    global $conn;
    $tokens = fcm_get_user_tokens($conn, $userId);

    $tokensWithUsers = array_map(function ($token) use ($userId) {
        return ['user_id' => $userId, 'token' => $token];
    }, $tokens);

    return fcm_send_multicast($tokensWithUsers, $title, $body, $data);
}