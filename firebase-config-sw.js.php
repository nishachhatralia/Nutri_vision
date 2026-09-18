<?php
/**
 * NutriVision AI - Firebase Config for Service Worker
 *
 * Outputs the PUBLIC Firebase web config as JavaScript.
 * This file is loaded by firebase-messaging-sw.js via importScripts().
 *
 * Only PUBLIC config is exposed here (apiKey, messagingSenderId, etc.).
 * These values are designed by Firebase to be public — they are NOT secrets.
 * The Admin credentials (service account private key) are NEVER exposed.
 */

header('Content-Type: application/javascript');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/firebase-config.php';

$config = firebase_web_config();

// Output as JavaScript
echo "self.FIREBASE_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";