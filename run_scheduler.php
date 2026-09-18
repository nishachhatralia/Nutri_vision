<?php
/**
 * NutriVision AI - Continuous Push Notification Scheduler
 *
 * A long-running PHP CLI script that acts as an in-process scheduler
 * (similar to node-cron).  It checks the current time every 60 seconds
 * and sends meal reminder notifications at the scheduled times.
 *
 * This is the recommended approach for Windows / XAMPP environments
 * where system cron is not available.  On Linux, you can use either
 * this script OR a system crontab that calls send_meal_reminders.php.
 *
 * Scheduled times (timezone from .env, default Asia/Kolkata):
 *   09:00 — Breakfast Reminder
 *   12:00 — Lunch Reminder
 *   20:00 — Dinner Reminder
 *
 * Usage:
 *   php cron/run_scheduler.php
 *
 * Or double-click:  cron/start_scheduler.bat
 *
 * The script keeps a state file to avoid sending duplicate reminders
 * within the same minute.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../firebase-config.php';
require_once __DIR__ . '/../includes/fcm_service.php';

// Set timezone
$tz = env('NOTIFICATION_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($tz);

/**
 * Get the path to the scheduler state file.
 */
function scheduler_state_file()
{
    return __DIR__ . '/.scheduler_state.json';
}

/**
 * Load the scheduler state (which reminders have been sent today).
 * @return array
 */
function load_scheduler_state()
{
    $file = scheduler_state_file();
    if (file_exists($file)) {
        $state = json_decode(file_get_contents($file), true);
        if (is_array($state)) {
            return $state;
        }
    }
    return ['date' => '', 'sent' => []];
}

/**
 * Save the scheduler state.
 * @param array $state
 */
function save_scheduler_state($state)
{
    file_put_contents(scheduler_state_file(), json_encode($state), LOCK_EX);
}

/**
 * Main scheduler loop.
 */
function run_scheduler()
{
    $meals = [
        '09:00' => 'breakfast',
        '12:00' => 'lunch',
        '20:00' => 'dinner',
    ];

    echo "============================================\n";
    echo "  NutriVision AI - Push Notification Scheduler\n";
    echo "  Timezone: " . date_default_timezone_get() . "\n";
    echo "  Started:  " . date('Y-m-d H:i:s') . "\n";
    echo "============================================\n";
    echo "  Scheduled reminders:\n";
    echo "    09:00 — 🍳 Breakfast Reminder\n";
    echo "    12:00 — 🍱 Lunch Reminder\n";
    echo "    20:00 — 🍽️ Dinner Reminder\n";
    echo "============================================\n";
    echo "  Press Ctrl+C to stop.\n\n";

    notification_log('INFO', 'Push notification scheduler started');

    while (true) {
        $now   = date('H:i');
        $today = date('Y-m-d');

        // Load state
        $state = load_scheduler_state();

        // Reset state at midnight
        if ($state['date'] !== $today) {
            $state = ['date' => $today, 'sent' => []];
            save_scheduler_state($state);
            echo "[" . date('Y-m-d H:i:s') . "] New day — state reset.\n";
        }

        // Check if any meal reminder is due
        foreach ($meals as $scheduledTime => $mealKey) {
            if ($now === $scheduledTime && !in_array($mealKey, $state['sent'])) {
                echo "\n[" . date('Y-m-d H:i:s') . "] Sending $mealKey reminder...\n";

                // Send the reminder
                send_meal_reminder_direct($mealKey);

                // Mark as sent
                $state['sent'][] = $mealKey;
                save_scheduler_state($state);
            }
        }

        // Sleep for 60 seconds before checking again
        // (We check every 60s to catch the exact minute)
        sleep(60);
    }
}

/**
 * Send a meal reminder directly (without the CLI wrapper).
 * Reuses the logic from send_meal_reminders.php.
 *
 * @param string $mealKey
 */
function send_meal_reminder_direct($mealKey)
{
    $meals = [
        'breakfast' => [
            'title' => "🍳 Breakfast Reminder",
            'body'  => "Good morning! Don't forget to track your breakfast today.",
        ],
        'lunch' => [
            'title' => "🍱 Lunch Reminder",
            'body'  => "It's lunchtime! Remember to log your lunch.",
        ],
        'dinner' => [
            'title' => "🍽️ Dinner Reminder",
            'body'  => "Finish your day by tracking your dinner.",
        ],
    ];

    if (!isset($meals[$mealKey])) {
        echo "  Unknown meal: $mealKey\n";
        return;
    }

    $meal  = $meals[$mealKey];
    $title = $meal['title'];
    $body  = $meal['body'];

    echo "  Title: $title\n";
    echo "  Body:  $body\n";

    $result = fcm_send_to_all($title, $body, [
        'type'      => 'meal_reminder',
        'meal'      => $mealKey,
        'click_url' => '/dashboard.php',
    ]);

    echo "  Sent: {$result['sent']}, Succeeded: {$result['succeeded']}, Failed: {$result['failed']}, Invalid removed: {$result['invalid_removed']}\n\n";
}

// --- Entry point ---
if (php_sapi_name() === 'cli') {
    run_scheduler();
} else {
    header('Content-Type: text/plain');
    echo "This script must be run from the command line.\n";
    echo "Usage: php cron/run_scheduler.php\n";
    echo "Or double-click: cron/start_scheduler.bat\n";
}