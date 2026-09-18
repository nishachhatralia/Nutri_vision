<?php
/**
 * NutriVision AI - Meal Reminder Cron Job
 *
 * Sends scheduled meal reminder push notifications to all users with
 * valid FCM tokens.
 *
 * Three reminders are sent daily (timezone: Asia/Kolkata):
 *
 *   09:00 AM  🍳 Breakfast Reminder
 *   12:00 PM  🍱 Lunch Reminder
 *   08:00 PM  🍽️ Dinner Reminder
 *
 * Usage (CLI):
 *   php cron/send_meal_reminders.php breakfast
 *   php cron/send_meal_reminders.php lunch
 *   php cron/send_meal_reminders.php dinner
 *   php cron/send_meal_reminders.php            (auto-detect by current time)
 *
 * System cron (Linux) example:
 *   0 9  * * *  php /path/to/cron/send_meal_reminders.php breakfast
 *   0 12 * * *  php /path/to/cron/send_meal_reminders.php lunch
 *   0 20 * * *  php /path/to/cron/send_meal_reminders.php dinner
 *
 * Windows Task Scheduler: see cron/start_scheduler.bat
 */

// --- Bootstrap ---
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../firebase-config.php';
require_once __DIR__ . '/../includes/fcm_service.php';

// Set timezone
$tz = env('NOTIFICATION_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($tz);

/**
 * Shared "already sent today" state.
 *
 * This file is also used by cron/run_scheduler.php, so the two never
 * step on each other's toes. It's what stops the same reminder being
 * sent more than once.
 *
 * Bug this fixes: this script is the one meant to be wired into a REAL
 * system cron or an external HTTP cron pinger (see NOTIFICATIONS.md).
 * Those are commonly configured to run every few minutes (e.g. "*\/5 * * * *"),
 * not at the exact minute. Combined with auto_detect_meal()'s 30-minute
 * matching window, that used to mean the SAME reminder could be sent
 * several times in a row (e.g. breakfast firing at 9:00, 9:05, 9:10...),
 * which is exactly the "wrong timing" users would see as spammy/broken
 * notifications. Now each meal is only ever sent once per calendar day.
 */
function scheduler_state_file_path()
{
    return __DIR__ . '/.scheduler_state.json';
}

function load_scheduler_state_shared()
{
    $file = scheduler_state_file_path();
    if (file_exists($file)) {
        $state = json_decode(file_get_contents($file), true);
        if (is_array($state) && isset($state['date'], $state['sent'])) {
            return $state;
        }
    }
    return ['date' => '', 'sent' => []];
}

function save_scheduler_state_shared($state)
{
    file_put_contents(scheduler_state_file_path(), json_encode($state), LOCK_EX);
}

/**
 * Has this meal already been sent today? Also resets the state for a new day.
 */
function meal_already_sent_today($mealKey)
{
    $today = date('Y-m-d');
    $state = load_scheduler_state_shared();

    if ($state['date'] !== $today) {
        // New day — reset.
        $state = ['date' => $today, 'sent' => []];
        save_scheduler_state_shared($state);
        return false;
    }

    return in_array($mealKey, $state['sent'], true);
}

/**
 * Mark a meal as sent today.
 */
function mark_meal_sent_today($mealKey)
{
    $today = date('Y-m-d');
    $state = load_scheduler_state_shared();

    if ($state['date'] !== $today) {
        $state = ['date' => $today, 'sent' => []];
    }
    if (!in_array($mealKey, $state['sent'], true)) {
        $state['sent'][] = $mealKey;
    }
    save_scheduler_state_shared($state);
}

/**
 * Meal reminder definitions.
 */
function get_meal_reminders()
{
    return [
        'breakfast' => [
            'time'  => '09:00',
            'title' => "🍳 Breakfast Reminder",
            'body'  => "Good morning! Don't forget to track your breakfast today.",
        ],
        'lunch' => [
            'time'  => '12:00',
            'title' => "🍱 Lunch Reminder",
            'body'  => "It's lunchtime! Remember to log your lunch.",
        ],
        'dinner' => [
            'time'  => '20:00',
            'title' => "🍽️ Dinner Reminder",
            'body'  => "Finish your day by tracking your dinner.",
        ],
    ];
}

/**
 * Auto-detect which meal reminder to send based on current time.
 * Sends the reminder whose scheduled time is closest to the current time
 * (within a 30-minute window).
 *
 * @return string|null  'breakfast' | 'lunch' | 'dinner' | null
 */
function auto_detect_meal()
{
    $meals  = get_meal_reminders();
    $now    = date('H:i');
    $best   = null;
    $bestDiff = PHP_INT_MAX;

    foreach ($meals as $key => $meal) {
        $diff = abs(strtotime($now) - strtotime($meal['time']));
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $best     = $key;
        }
    }

    // Only send if within 30 minutes of the scheduled time
    if ($bestDiff > 1800) {
        return null;
    }

    return $best;
}

/**
 * Send a specific meal reminder to all users with FCM tokens.
 *
 * @param string $mealKey  'breakfast' | 'lunch' | 'dinner'
 * @return array
 */
function send_meal_reminder($mealKey)
{
    $meals = get_meal_reminders();

    if (!isset($meals[$mealKey])) {
        echo "Unknown meal: $mealKey\n";
        echo "Valid options: breakfast, lunch, dinner\n";
        return ['error' => 'unknown_meal'];
    }

    // Guard against duplicate sends: if a real system cron or an external
    // HTTP pinger calls this script more than once inside the matching
    // window (very common — most cron setups run every few minutes, not
    // at the exact minute), only the first call actually sends.
    if (meal_already_sent_today($mealKey)) {
        echo "$mealKey reminder was already sent today (" . date('Y-m-d') . "). Skipping.\n";
        return ['skipped' => true, 'reason' => 'already_sent_today'];
    }

    $meal  = $meals[$mealKey];
    $title = $meal['title'];
    $body  = $meal['body'];

    echo "Sending $mealKey reminder...\n";
    echo "  Title: $title\n";
    echo "  Body:  $body\n";
    echo "  Time:  " . date('Y-m-d H:i:s') . " (" . date_default_timezone_get() . ")\n";

    $result = fcm_send_to_all($title, $body, [
        'type'      => 'meal_reminder',
        'meal'      => $mealKey,
        'click_url' => '/dashboard.php',
    ]);

    mark_meal_sent_today($mealKey);

    echo "\n";
    echo "  Sent:            {$result['sent']}\n";
    echo "  Succeeded:       {$result['succeeded']}\n";
    echo "  Failed:          {$result['failed']}\n";
    echo "  Invalid removed: {$result['invalid_removed']}\n";
    echo "\nDone.\n";

    return $result;
}

// --- CLI entry point ---
if (php_sapi_name() === 'cli') {
    $mealKey = isset($argv[1]) ? strtolower(trim($argv[1])) : null;

    if (!$mealKey) {
        $mealKey = auto_detect_meal();
        if (!$mealKey) {
            echo "No meal reminder due at this time (" . date('H:i') . ").\n";
            echo "Usage: php cron/send_meal_reminders.php [breakfast|lunch|dinner]\n";
            exit(0);
        }
        echo "Auto-detected meal: $mealKey\n\n";
    }

    send_meal_reminder($mealKey);
} else {
    // Allow web access for testing (with a GET parameter)
    $mealKey = isset($_GET['meal']) ? strtolower(trim($_GET['meal'])) : null;

    if (!$mealKey) {
        $mealKey = auto_detect_meal();
    }

    header('Content-Type: text/plain');

    if (!$mealKey) {
        echo "No meal reminder due at this time (" . date('H:i') . ").\n";
        echo "Usage: ?meal=breakfast|lunch|dinner\n";
        exit;
    }

    echo "Sending $mealKey reminder...\n\n";
    $result = send_meal_reminder($mealKey);
    echo "\nResult: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}