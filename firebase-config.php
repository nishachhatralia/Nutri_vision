<?php
/**
 * NutriVision AI - Firebase Configuration
 *
 * Reads all Firebase credentials from environment variables (.env file).
 * - Admin credentials (service account) are NEVER exposed to the frontend.
 * - Public web config is safe to output in HTML/JS.
 *
 * Include this file wherever Firebase config is needed.
 */

require_once __DIR__ . '/includes/env_loader.php';

/**
 * Get the Firebase project ID from environment.
 * @return string|null
 */
function firebase_project_id()
{
    return env('FIREBASE_PROJECT_ID');
}

/**
 * Get the path to the Firebase service account JSON file.
 * @return string|null
 */
function firebase_service_account_path()
{
    $path = env('FIREBASE_SERVICE_ACCOUNT_PATH');
    if ($path && !file_exists($path)) {
        // Try resolving relative to project root
        $altPath = __DIR__ . DIRECTORY_SEPARATOR . ltrim($path, './');
        if (file_exists($altPath)) {
            $path = $altPath;
        }
    }
    return $path;
}

/**
 * Load and decode the Firebase service account JSON.
 * @return array|null
 */
function firebase_service_account()
{
    $path = firebase_service_account_path();
    if (!$path || !file_exists($path)) {
        return null;
    }
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Get the PUBLIC Firebase web configuration (safe for frontend).
 * These values are designed by Firebase to be public.
 * @return array
 */
function firebase_web_config()
{
    return [
        'apiKey'            => env('FIREBASE_API_KEY'),
        'authDomain'        => env('FIREBASE_AUTH_DOMAIN'),
        'databaseURL'       => env('FIREBASE_DATABASE_URL'),
        'projectId'         => env('FIREBASE_PROJECT_ID'),
        'storageBucket'     => env('FIREBASE_STORAGE_BUCKET'),
        'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'appId'             => env('FIREBASE_APP_ID'),
    ];
}

/**
 * Get the VAPID public key for web push.
 * @return string|null
 */
function firebase_vapid_public_key()
{
    return env('FIREBASE_VAPID_PUBLIC_KEY');
}

/**
 * Check if Firebase is configured (all required values present).
 * @return bool
 */
function firebase_is_configured()
{
    $projectId = firebase_project_id();
    $sa        = firebase_service_account();
    $webConfig = firebase_web_config();

    return !empty($projectId)
        && $sa !== null
        && !empty($webConfig['apiKey'])
        && !empty($webConfig['messagingSenderId'])
        && !empty(firebase_vapid_public_key());
}

/**
 * Output the public Firebase config as a JavaScript object.
 * Call this inside a <script> tag in PHP pages.
 * @return void
 */
function firebase_web_config_js()
{
    $config = firebase_web_config();
    $vapid  = firebase_vapid_public_key();
    echo "window.FIREBASE_CONFIG = " . json_encode($config) . ";\n";
    echo "window.FIREBASE_VAPID_PUBLIC_KEY = " . json_encode($vapid) . ";\n";
}