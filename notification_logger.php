<?php
/**
 * NutriVision AI - Notification Logger
 *
 * Lightweight file-based logger for push notification delivery tracking.
 * Logs successful and failed deliveries, plus invalid token cleanup.
 */

require_once __DIR__ . '/env_loader.php';

/**
 * Get the notification log file path.
 * @return string
 */
function notification_log_path()
{
    $configured = env('NOTIFICATION_LOG_PATH', './logs/notifications.log');
    // Resolve relative to project root
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($configured, './');
    $dir  = dirname($path);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $path;
}

/**
 * Write a line to the notification log.
 *
 * @param string $level   INFO | SUCCESS | WARNING | ERROR
 * @param string $message Log message
 * @param array  $context Optional context data (user_id, token, etc.)
 * @return void
 */
function notification_log($level, $message, $context = [])
{
    $path    = notification_log_path();
    $ts      = date('Y-m-d H:i:s');
    $context = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
    $line    = "[$ts] [$level] $message$context" . PHP_EOL;

    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Log a successful notification delivery.
 * @param int    $userId
 * @param string $token
 * @param string $title
 * @return void
 */
function notification_log_success($userId, $token, $title)
{
    notification_log('SUCCESS', "Notification delivered", [
        'user_id' => $userId,
        'token'   => substr($token, 0, 20) . '...',
        'title'   => $title,
    ]);
}

/**
 * Log a failed notification delivery.
 * @param int    $userId
 * @param string $token
 * @param string $title
 * @param string $error
 * @return void
 */
function notification_log_failure($userId, $token, $title, $error)
{
    notification_log('ERROR', "Notification failed", [
        'user_id' => $userId,
        'token'   => substr($token, 0, 20) . '...',
        'title'   => $title,
        'error'   => $error,
    ]);
}

/**
 * Log removal of an invalid token.
 * @param string $token
 * @param string $reason
 * @return void
 */
function notification_log_token_removed($token, $reason)
{
    notification_log('WARNING', "Invalid token removed", [
        'token'  => substr($token, 0, 20) . '...',
        'reason' => $reason,
    ]);
}