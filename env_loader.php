<?php
/**
 * NutriVision AI - Simple .env File Loader
 *
 * Loads environment variables from a .env file into $_ENV and getenv().
 * No external dependencies required (no Composer needed).
 *
 * Usage:
 *   require_once 'includes/env_loader.php';
 *   $projectId = env('FIREBASE_PROJECT_ID');
 */

/**
 * Load a .env file and populate the environment.
 *
 * @param string $path  Path to the .env file (default: project root).
 */
function loadEnv($path = null)
{
    if ($path === null) {
        // Resolve relative to this file: includes/ -> ../ = project root
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    }

    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Skip lines without an = sign
        if (strpos($line, '=') === false) {
            continue;
        }

        // Split into key / value
        list($key, $value) = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Remove surrounding quotes (single or double)
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Only set if not already defined in the real environment
        // (real environment variables take precedence over .env file)
        if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Get an environment variable with an optional default.
 *
 * @param  string $key     Variable name.
 * @param  mixed  $default Default value if not found.
 * @return mixed
 */
function env($key, $default = null)
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

// Auto-load .env on include
loadEnv();