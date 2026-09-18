# NutriVision AI — Changes in this update

## 1. Icons (logo / Add Meal / Goals / History)
**Root cause:** these all used emoji characters (🥗 🍽️ 🎯 📜). Two things
were killing them:
- The navbar logo `<h2>` used a CSS gradient-text trick
  (`-webkit-text-fill-color: transparent` + `background-clip: text`) on the
  whole heading, emoji included — on several browsers this strips the emoji's
  own colour too, leaving it blank/invisible.
- Emoji rendering also depends on the device having a colour-emoji font
  installed. Where it's missing, you just get an empty box next to the
  green button background — which reads as "everything is just green."

**Fix:** replaced all four with real inline SVG icons (`/icons/*.svg`,
loaded via `includes/icon.php` → `nv_icon('name')`). They use `currentColor`,
so the same file renders green in the navbar and white inside buttons —
no emoji font dependency, no CSS side effects. Updated: `dashboard.php`,
`index.php`, `login.php`, `register.php`, `style.css`.

Also fixed the **push notification icon**, which had the identical bug at
the OS level: every notification referenced `/notification-icon.png`, which
never existed (only `.svg` did — 404), and the SVG was a solid green square
with an emoji drawn as `<text>`. Android's status bar ignores colour and
alpha-blends the whole opaque icon to one tint, so it rendered as a plain
green blob. Added two real files:
- `notification-icon.png` — full-colour icon (shown in the expanded
  notification / desktop browsers)
- `notification-badge.png` — white silhouette on a transparent background
  (what Android's status bar actually uses)

Wired into `includes/fcm_service.php`, `firebase-messaging-sw.js`, and
`js/push-notifications.js`.

## 2. Floating reviews on the login/register page
Added an auto-scrolling "What Users Are Saying" strip at the bottom of
`index.php`, pulling real 4★/5★ reviews from the existing `reviews` table.
Pauses on hover, respects `prefers-reduced-motion`, and shows a friendly
empty state if there are no reviews yet. Uses its own lightweight DB
connection (not `db.php`) so a database hiccup can never take down the
public landing page/login-register buttons.

## 3. Notifications — timing bug + why real users never got any
Two separate problems, only one of which is fixable in code:

- **Fixed:** `cron/send_meal_reminders.php` (the one-shot script meant for a
  real system cron) had no "already sent today" guard. Most real cron setups
  fire every few minutes, not at the exact minute — combined with the
  30-minute auto-detect window, the same reminder could fire repeatedly.
  Added shared state tracking (`cron/.scheduler_state.json`) so each meal
  reminder now sends at most once per day, matching `run_scheduler.php`'s
  existing behaviour.

- **Not fixable from here — needs your Firebase project:** `.env` still has
  placeholder values (`your-project-id`, `AIzaSyXXX...`) and
  `config/firebase-service-account.json` doesn't exist (only the
  `.example.json` template). No push notification has ever actually reached
  Firebase — the logs only ever show "scheduler started," never a send. To
  fix:
  1. Firebase Console → your project → Project Settings → Service Accounts
     → Generate new private key → save as `config/firebase-service-account.json`
  2. Fill in the real values in `.env` (project ID, web config, VAPID key —
     all under Project Settings → General / Cloud Messaging)
  3. Make sure something actually calls `cron/send_meal_reminders.php` on a
     schedule in production — either a real system cron entry (see the
     example at the top of that file), or an external HTTP cron pinger
     (e.g. cron-job.org) if your host doesn't give you shell/cron access.
     `cron/run_scheduler.php` only works if you can keep a process running
     24/7, which most shared hosting won't allow.

## 4. Test notifications removed
- Removed the 📤 "send test notification" button from the dashboard navbar
- Deleted `api/send_test_notification.php`
- Removed `sendTest()` from `js/push-notifications.js`
- Deleted the stray leftover `test.php` (just echoed "Database Connected
  Successfully" — a leftover debug file)
- Cleaned up the matching references in `NOTIFICATIONS.md`

## 5. Consistent premium logo (all pages) + dark-mode contrast
Every page previously had its own different logo treatment — a mix of emoji
(🍽️ 🧠 🥗 ✏️ 🎯 📜 ⭐ 💧) each next to the word "NutriVision" in the navbar,
so the brand mark changed depending on what page you were on.

Replaced `icons/logo.svg` with a single, self-contained badge mark: a
rounded-square icon using the site's own brand gradient (`#00D66F → #00C2FF`,
same as `--gradient-primary`) with a white leaf glyph. Because the badge
carries its own background rather than relying on the surrounding page
colour, it renders identically — and with proper contrast — in both light
and dark mode.

Applied via `nv_icon('logo', 24)` to the navbar on **every single page**:
`index.php`, `login.php`, `register.php`, `dashboard.php`, `add_meal.php`,
`add_meal_ai.php`, `ai_insights.php`, `diet_plan_ai.php`, `edit_meal.php`,
`goal.php`, `meal_history.php`, `reviews.php` (both navbars), `water.php`.
No page's text/wording or layout was changed — only the icon graphic.

## 6. Water tracker showing stale/yesterday's data
**Root cause:** both `water.php` and `dashboard.php` summed
**all-time** water intake (`SELECT SUM(amount_ml) ... WHERE user_id = ...`,
no date filter) instead of just today's. So "Today: 8 glasses" was really
a lifetime running total that never reset — whatever you'd logged most
recently just kept showing.

**Fix:** both queries now filter with `AND DATE(intake_time) = CURDATE()`,
so the count genuinely resets each day and only reflects what's actually
been logged today.
