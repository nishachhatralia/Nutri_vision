# 🔔 Web Push Notifications — NutriVision AI

Complete Firebase Cloud Messaging (FCM) integration for browser push notifications.  
Sends scheduled meal reminders (Breakfast, Lunch, Dinner) to users who have granted notification permission.

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Files Created / Modified](#files-created--modified)
4. [Dependencies](#dependencies)
5. [Database Changes](#database-changes)
6. [How to Configure Firebase](#how-to-configure-firebase)
7. [How to Test Notifications Locally](#how-to-test-notifications-locally)
8. [How to Deploy to Production](#how-to-deploy-to-production)
9. [Code Walkthrough](#code-walkthrough)
10. [Troubleshooting](#troubleshooting)

---

## Overview

This implementation adds **Web Push Notifications** to NutriVision AI using **Firebase Cloud Messaging (FCM)**. It works like Flipkart, Amazon, and other modern websites that send browser notifications.

### What it does

- When a logged-in user visits the dashboard, the browser asks for notification permission.
- If the user clicks **Allow**, Firebase generates an FCM registration token.
- The token is saved in the database, linked to the user's account.
- A **scheduler** runs daily and sends meal reminder notifications at:
  - **09:00 AM** — 🍳 Breakfast Reminder
  - **12:00 PM** — 🍱 Lunch Reminder
  - **08:00 PM** — 🍽️ Dinner Reminder
- Notifications are received even when the website is in the background (via service worker).
- Invalid/expired tokens are automatically removed.
- Multiple devices per user are supported.

### What it does NOT do

- ❌ No email reminders (this is push-only, as requested)
- ❌ No SMS notifications

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     BROWSER (Frontend)                       │
│                                                               │
│  dashboard.php                                                │
│    ├── Firebase JS SDK (from Google CDN)                      │
│    ├── js/push-notifications.js  (permission + token)          │
│    └── firebase-messaging-sw.js  (background notifications)   │
│                                                               │
│  Flow: Request permission → Get FCM token → POST to backend   │
└──────────────────────────┬──────────────────────────────────┘
                           │  HTTPS (api/save_token.php)
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    PHP BACKEND (XAMPP)                       │
│                                                               │
│  api/save_token.php        → Store token in DB (authenticated)│
│  api/remove_token.php      → Remove token (permission revoked)│
│  api/notification_status   → Check if user has tokens         │
│                                                               │
│  includes/fcm_service.php  → OAuth2 + FCM v1 API sending     │
│  includes/env_loader.php   → Read .env file                   │
│  includes/notification_logger.php → Delivery logging          │
│  firebase-config.php       → Config from env vars             │
│                                                               │
│  cron/send_meal_reminders.php → One-shot cron job             │
│  cron/run_scheduler.php       → Continuous scheduler          │
│  cron/start_scheduler.bat     → Windows launcher               │
└──────────────────────────┬──────────────────────────────────┘
                           │  HTTPS (FCM v1 API)
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              FIREBASE CLOUD MESSAGING (FCM)                  │
│                                                               │
│  Receives message → Delivers to browser via service worker   │
└─────────────────────────────────────────────────────────────┘
```

### Why PHP instead of Node.js?

The existing project is a **pure PHP/XAMPP application**. The task said *"or follow the existing project structure if different."* Using PHP:

- ✅ No additional runtime (Node.js) to install or maintain
- ✅ Consistent with the existing codebase
- ✅ Works on any PHP hosting
- ✅ No process management for a separate Node.js service
- ✅ Uses PHP's built-in `openssl` and `cURL` extensions (no Composer needed)

---

## Files Created / Modified

### New Files

| File | Purpose |
|------|---------|
| `.env` | Firebase credentials (secrets — never commit) |
| `.env.example` | Template for `.env` |
| `.gitignore` | Protects secrets from version control |
| `.htaccess` | Blocks public access to `.env`, service account, logs |
| `firebase-config.php` | Reads Firebase config from env vars |
| `firebase-config-sw.js.php` | Outputs JS config for service worker |
| `firebase-messaging-sw.js` | Service worker for background notifications |
| `includes/env_loader.php` | Simple `.env` file parser (no Composer) |
| `includes/fcm_service.php` | Core: OAuth2 JWT + FCM v1 API + multicast |
| `includes/notification_logger.php` | File-based delivery logging |
| `api/save_token.php` | Endpoint: save FCM token (authenticated) |
| `api/remove_token.php` | Endpoint: remove FCM token (authenticated) |
| `api/notification_status.php` | Endpoint: check token status |
| `cron/send_meal_reminders.php` | One-shot cron job for meal reminders |
| `cron/run_scheduler.php` | Continuous PHP scheduler (like node-cron) |
| `cron/start_scheduler.bat` | Windows batch launcher for scheduler |
| `js/push-notifications.js` | Frontend: Firebase init, permission, token |
| `config/firebase-service-account.example.json` | Template for service account |
| `update_db_fcm.php` | Migration script for existing databases |
| `notification-icon.svg` | Default notification icon |
| `NOTIFICATIONS.md` | This documentation |

### Modified Files

| File | Changes |
|------|---------|
| `dashboard.php` | Added Firebase SDK, notification bell (🔔), push init |
| `database.sql` | Added `fcm_tokens` table schema |
| `setup_db.php` | Added `fcm_tokens` table creation |

---

## Dependencies

### No external packages required! 🎉

This implementation uses only **PHP built-in extensions** (all enabled in XAMPP by default):

| Extension | Used For |
|-----------|----------|
| `openssl` | JWT signing for OAuth2 (RS256) |
| `curl` | HTTP requests to FCM v1 API + OAuth2 token endpoint |
| `mysqli` | Database (already used by the project) |
| `json` | Encoding/decoding API payloads |

### Frontend (loaded from CDN, no install)

| Library | Version | Purpose |
|---------|---------|---------|
| Firebase App SDK | 10.12.2 | Firebase initialization |
| Firebase Messaging SDK | 10.12.2 | FCM token + message handling |

---

## Database Changes

### New Table: `fcm_tokens`

```sql
CREATE TABLE fcm_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                    -- Links to users.id
    token VARCHAR(255) NOT NULL UNIQUE,      -- FCM registration token
    device_info VARCHAR(255),                -- Browser/OS info
    is_valid TINYINT(1) DEFAULT 1,            -- 1 = valid, 0 = invalid
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Design decisions:**
- **One row per token** (not per user) → supports multiple devices/browsers
- `UNIQUE` on `token` → prevents duplicates
- `ON DELETE CASCADE` → tokens auto-deleted when user is deleted
- `is_valid` flag → can mark invalid without deleting (for debugging)

### To apply the migration:

**For new installations:**
```
Visit: http://localhost/nutrivision-ai-master/setup_db.php
```

**For existing installations:**
```
Visit: http://localhost/nutrivision-ai-master/update_db_fcm.php
```

---

## How to Configure Firebase

### Step 1: Create a Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click **Add Project** → Name it (e.g., "NutriVision AI")
3. Wait for project creation

### Step 2: Register a Web App

1. In Firebase Console, click the **Web icon** (</>)
2. App nickname: `NutriVision Web`
3. **Check** "Also set up Firebase Hosting" (optional)
4. Click **Register App**
5. Copy the `firebaseConfig` values shown

### Step 3: Generate VAPID Keys (for Web Push)

1. Go to **Project Settings** → **Cloud Messaging**
2. Scroll to **Web Push** section
3. Click **Generate VAPID Key Pair**
4. Copy the **Public Key**

### Step 4: Generate Service Account Key (for server-side sending)

1. Go to **Project Settings** → **Service Accounts**
2. Click **Generate New Private Key**
3. A JSON file downloads — **keep this secure!**
4. Rename it to `firebase-service-account.json`

### Step 5: Configure the `.env` File

1. Open the `.env` file in the project root
2. Fill in all values:

```env
# Firebase Admin (server-side)
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_SERVICE_ACCOUNT_PATH=./config/firebase-service-account.json

# Firebase Web Config (public)
FIREBASE_API_KEY=AIzaSy...
FIREBASE_AUTH_DOMAIN=your-project-id.firebaseapp.com
FIREBASE_DATABASE_URL=https://your-project-id.firebaseio.com
FIREBASE_STORAGE_BUCKET=your-project-id.appspot.com
FIREBASE_MESSAGING_SENDER_ID=123456789012
FIREBASE_APP_ID=1:123456789012:web:abcdef

# VAPID Public Key
FIREBASE_VAPID_PUBLIC_KEY=BPc...

# Settings
NOTIFICATION_LOG_PATH=./logs/notifications.log
NOTIFICATION_TIMEZONE=Asia/Kolkata
```

### Step 6: Place the Service Account JSON

1. Copy the downloaded `firebase-service-account.json` to:
   ```
   config/firebase-service-account.json
   ```
2. Verify `.htaccess` blocks public access to this file

### Step 7: Run the Database Migration

```
Visit: http://localhost/nutrivision-ai-master/update_db_fcm.php
```

---

## How to Test Notifications Locally

### Prerequisites

- XAMPP running (Apache + MySQL)
- Firebase configured (`.env` + service account JSON)
- Database migrated (`fcm_tokens` table exists)
- Using **Chrome** or **Firefox** (not Safari — it has limited push support)
- Site served over **HTTPS** or **localhost** (browsers require this for push)

### Test 1: Token Registration

1. Start Apache and MySQL in XAMPP
2. Open `http://localhost/nutrivision-ai-master/login.php`
3. Log in with your account
4. You should see a notification permission prompt
5. Click **Allow**
6. Open browser DevTools → Console — you should see:
   ```
   [Push] FCM token saved to server.
   ```
7. Check the database:
   ```sql
   SELECT * FROM fcm_tokens;
   ```
   You should see your token.

### Test 2: Test Meal Reminders

1. Open in browser:
   ```
   http://localhost/nutrivision-ai-master/cron/send_meal_reminders.php?meal=breakfast
   ```
2. You should see output like:
   ```
   Sending breakfast reminder...
     Title: 🍳 Breakfast Reminder
     Body:  Good morning! Don't forget to track your breakfast today.
     Sent: 1, Succeeded: 1, Failed: 0, Invalid removed: 0
   ```
3. You should receive the notification!

### Test 3: Start the Scheduler

1. Double-click `cron/start_scheduler.bat`
2. Or run from command line:
   ```
   php cron/run_scheduler.php
   ```
3. You should see:
   ```
   ============================================
     NutriVision AI - Push Notification Scheduler
     Timezone: Asia/Kolkata
     Started:  2026-07-31 10:00:00
   ============================================
     Scheduled reminders:
       09:00 — 🍳 Breakfast Reminder
       12:00 — 🍱 Lunch Reminder
       20:00 — 🍽️ Dinner Reminder
   ============================================
     Press Ctrl+C to stop.
   ```
4. Keep the window open. At 09:00, 12:00, and 20:00, it will send notifications.

### Test 4: Check Logs

Notification delivery logs are written to:
```
logs/notifications.log
```

Example log entries:
```
[2026-07-31 09:00:00] [INFO] Starting multicast send {"total_tokens":5,"title":"🍳 Breakfast Reminder","batch_size":50}
[2026-07-31 09:00:01] [SUCCESS] Notification delivered {"user_id":1,"token":"dGhpcyBpcyBhIHRv...","title":"🍳 Breakfast Reminder"}
[2026-07-31 09:00:01] [INFO] Multicast send complete {"title":"🍳 Breakfast Reminder","total":5,"succeeded":4,"failed":1,"invalid_removed":1}
```

---

## How to Deploy to Production

### Step 1: HTTPS is Required

Browsers only allow push notifications on **HTTPS** origins (except `localhost`).  
Ensure your production site uses HTTPS (e.g., via Let's Encrypt / Cloudflare).

### Step 2: Upload Files

Upload all new files to your production server:

```
.env                          (with production Firebase credentials)
.htaccess
firebase-config.php
firebase-config-sw.js.php
firebase-messaging-sw.js
includes/                     (entire directory)
api/                          (entire directory)
cron/                         (entire directory)
js/push-notifications.js
config/firebase-service-account.json
notification-icon.svg
```

Also upload the modified:
```
dashboard.php
database.sql
setup_db.php
```

### Step 3: Run Migration

Visit:
```
https://yourdomain.com/update_db_fcm.php
```

### Step 4: Set Permissions

```bash
# Protect the .env file
chmod 600 .env

# Protect the service account JSON
chmod 600 config/firebase-service-account.json

# Make logs directory writable
chmod 755 logs/
chmod 644 logs/notifications.log
```

### Step 5: Start the Scheduler

**Option A: System Cron (Linux — recommended for production)**

```bash
crontab -e
```

Add:
```cron
# Breakfast at 09:00 AM (Asia/Kolkata)
0 9 * * * cd /var/www/nutrivision && php cron/send_meal_reminders.php breakfast

# Lunch at 12:00 PM
0 12 * * * cd /var/www/nutrivision && php cron/send_meal_reminders.php lunch

# Dinner at 08:00 PM
0 20 * * * cd /var/www/nutrivision && php cron/send_meal_reminders.php dinner
```

**Option B: Continuous Scheduler (Windows / shared hosting)**

```bash
php cron/run_scheduler.php &
```

Or use a process manager like `supervisor` or `systemd` to keep it running.

### Step 6: Verify

1. Visit your production site
2. Log in
3. Grant notification permission
4. Send a test notification
5. Check `logs/notifications.log`

---

## Code Walkthrough

### `includes/env_loader.php` — Environment Variable Loader

A lightweight `.env` file parser with **zero dependencies**. It reads the `.env` file, parses `KEY=VALUE` pairs, and loads them into `$_ENV` / `getenv()`. Real environment variables take precedence over `.env` file values.

**Why this was needed:** The task required storing secrets in `.env`, but the project didn't use Composer. This simple parser avoids adding any dependencies.

### `firebase-config.php` — Firebase Configuration

Reads all Firebase credentials from environment variables. Provides functions:
- `firebase_project_id()` — project ID
- `firebase_service_account()` — decoded service account JSON (server-side only)
- `firebase_web_config()` — public web config (safe for frontend)
- `firebase_vapid_public_key()` — VAPID key for web push
- `firebase_is_configured()` — checks if all required values are present
- `firebase_web_config_js()` — outputs config as JavaScript for the frontend

**Why this was needed:** Centralizes all Firebase config in one place. Admin credentials are never exposed to the frontend; only the public web config is.

### `includes/fcm_service.php` — Core FCM Service

The heart of the backend. Contains three sections:

**Section 1: OAuth2 Access Token**
- `fcm_build_jwt()` — Creates a JWT signed with RS256 using `openssl_sign()`
- `fcm_fetch_access_token()` — Exchanges the JWT for an OAuth2 access token via cURL
- `fcm_get_access_token()` — Gets a cached token or generates a new one (tokens are valid for ~1 hour, cached in a file)

**Why:** The FCM HTTP v1 API requires OAuth2 authentication. This implementation uses PHP's built-in `openssl` extension to sign JWTs — no Composer packages needed.

**Section 2: Database Helpers**
- `fcm_save_token()` — Saves/updates a token (supports multiple devices per user)
- `fcm_remove_token()` — Removes a token (when permission is revoked)
- `fcm_get_all_tokens()` — Fetches all valid tokens (for broadcast)
- `fcm_get_user_tokens()` — Fetches tokens for a specific user
- `fcm_invalidate_token()` — Removes an invalid token and logs it

**Why:** The `fcm_tokens` table supports multiple devices per user (one row per token). The `UNIQUE` constraint on `token` prevents duplicates.

**Section 3: Sending Notifications**
- `fcm_build_message()` — Builds the FCM v1 API JSON payload
- `fcm_send_to_token()` — Sends to a single token
- `fcm_send_multicast()` — Sends to many tokens using `curl_multi` (parallel requests)
- `fcm_send_to_all()` — Sends to all users with valid tokens
- `fcm_send_to_user()` — Sends to a specific user's devices

**Why `curl_multi`:** The FCM v1 API sends to one token per request. `curl_multi` allows parallel requests (default: 50 concurrent), which is efficient for thousands of users. The batch size is configurable.

**Invalid token handling:** When FCM returns `UNREGISTERED`, `INVALID_ARGUMENT`, or similar errors, the token is automatically deleted from the database and logged.

### `includes/notification_logger.php` — Delivery Logging

File-based logger that writes to `logs/notifications.log`. Logs:
- `INFO` — Scheduler starts, multicast begins/ends
- `SUCCESS` — Individual notification delivered
- `ERROR` — Individual notification failed
- `WARNING` — Invalid token removed

**Why:** Provides an audit trail of all notification deliveries for debugging and monitoring.

### `api/save_token.php` — Save Token Endpoint

- Validates the user is logged in (session check)
- Accepts POST with JSON body: `{ "token": "...", "device_info": "..." }`
- Validates the token (minimum 20 characters)
- Calls `fcm_save_token()` to store in the database
- Returns JSON response

**Why:** The frontend needs a secure endpoint to send the FCM token. Authentication ensures tokens are only saved for logged-in users.

### `api/remove_token.php` — Remove Token Endpoint

- Validates the user is logged in
- Accepts POST with JSON body: `{ "token": "..." }`
- Calls `fcm_remove_token()` to delete from the database
- Returns JSON response

**Why:** When a user revokes notification permission, the token should be removed so the server stops sending to it.

### `firebase-messaging-sw.js` — Service Worker

- Imports Firebase SDK from Google's CDN
- Imports public config from `firebase-config-sw.js.php`
- Initializes Firebase
- Handles `onBackgroundMessage` for data-only messages
- Handles `notificationclick` to open/focus the app

**Why:** The service worker enables notifications to be received even when the website is closed or in the background. It runs independently of the page.

### `firebase-config-sw.js.php` — Service Worker Config

- Outputs the public Firebase web config as JavaScript
- Sets `Content-Type: application/javascript`
- Sets `Cache-Control: no-cache` to ensure fresh config

**Why:** Service workers can't run PHP, so they use `importScripts()` to load this PHP-generated JS file. Only public config is exposed.

### `js/push-notifications.js` — Frontend Module

A self-contained IIFE module (`PushNotifications`) that handles:
1. Browser support detection
2. Firebase config availability check
3. Service worker registration
4. Permission request (only if not already asked — uses `localStorage`)
5. FCM token generation
6. Token sent to backend via `fetch()`
7. Token refresh handling (`onTokenRefresh`)
8. Foreground message handling (`onMessage`)
9. Permission denied handling (no repeated prompts)
10. In-page notification banner for foreground messages
11. Manual permission request (for the 🔔 button)

**Why:** Encapsulates all client-side push logic in one reusable module. The `localStorage` check prevents repeatedly prompting users who dismissed the permission dialog.

### `cron/send_meal_reminders.php` — One-Shot Cron Job

- Can be called from system cron with a meal argument
- Auto-detects the meal based on current time (within 30 min window)
- Also accessible via web browser for testing
- Sends the notification to all users with valid tokens

**Why:** System cron is the most reliable scheduler for production Linux servers.

### `cron/run_scheduler.php` — Continuous Scheduler

- Long-running PHP CLI script (like `node-cron`)
- Checks the time every 60 seconds
- Sends reminders at 09:00, 12:00, and 20:00 (Asia/Kolkata)
- Uses a state file to prevent duplicate sends within the same minute
- Resets state at midnight

**Why:** For Windows/XAMPP environments where system cron isn't available, this provides an equivalent continuous scheduler.

### `cron/start_scheduler.bat` — Windows Launcher

- Finds PHP in PATH or XAMPP default location
- Launches `run_scheduler.php`
- Keeps the window open

**Why:** Makes it easy to start the scheduler on Windows by double-clicking.

### `dashboard.php` — Modified

Three changes:
1. **Head:** Added Firebase SDK `<script>` tags (from Google CDN)
2. **Navbar:** Added 🔔 (enable notifications) icon
3. **Before `</body>`:** Added `push-notifications.js` and `PushNotifications.init()` call with PHP-generated config

**Why:** The dashboard is the main page users see after logging in, making it the ideal place to request notification permission.

---

## Troubleshooting

### "Firebase config not available" in console

- The `.env` file is missing or has placeholder values
- Run through the [Firebase Configuration](#how-to-configure-firebase) steps

### "No FCM token available" in console

- Notification permission was not granted
- Check `Notification.permission` in the browser console
- Try clicking the 🔔 icon in the navbar

### "JWT signing failed" in logs

- The service account JSON is missing or invalid
- Ensure `config/firebase-service-account.json` exists and has a valid `private_key`
- Check `FIREBASE_SERVICE_ACCOUNT_PATH` in `.env`

### "OAuth2 token request returned HTTP 400"

- The service account credentials are wrong
- Re-download the service account JSON from Firebase Console
- Ensure the project ID matches

### Notifications not received in background

- Check that the service worker is registered:
  ```
  chrome://serviceworker-internals/
  ```
- Ensure the service worker scope is `/`
- Try unregistering and re-registering:
  ```javascript
  navigator.serviceWorker.getRegistrations().then(regs => regs.forEach(r => r.unregister()));
  ```

### "Permission denied" when writing logs

- Ensure the `logs/` directory exists and is writable:
  ```bash
  mkdir -p logs && chmod 755 logs
  ```

### Notifications work on localhost but not in production

- Ensure the production site is served over **HTTPS**
- Check that the service worker is accessible at `https://yourdomain.com/firebase-messaging-sw.js`
- Verify the Firebase auth domain in `.env` matches your Firebase project

### Token not saved to database

- Check the `fcm_tokens` table exists:
  ```sql
  SHOW TABLES LIKE 'fcm_tokens';
  ```
- Run the migration: `http://localhost/nutrivision-ai-master/update_db_fcm.php`
- Check browser console for errors from `api/save_token.php`

### Scheduler not sending

- Check the timezone in `.env` (`NOTIFICATION_TIMEZONE=Asia/Kolkata`)
- Verify the scheduler is running (check the terminal output)
- Check `logs/notifications.log` for errors
- Try the web-based test: `http://localhost/nutrivision-ai-master/cron/send_meal_reminders.php?meal=breakfast`

---

## Security Notes

- ✅ **Admin credentials** (service account private key) are stored in `.env` and `config/firebase-service-account.json` — never exposed to the frontend
- ✅ **Public web config** (apiKey, messagingSenderId) is safe to expose — Firebase's security model is based on Security Rules, not hidden config
- ✅ **`.htaccess`** blocks public access to `.env`, service account JSON, and log files
- ✅ **`.gitignore`** prevents secrets from being committed to version control
- ✅ **All API endpoints** validate authentication (session check) before accepting tokens
- ✅ **Token validation** — minimum 20 characters, escaped with `mysqli_real_escape_string()`
- ✅ **Input sanitization** — device info is truncated to 255 characters

---

## Performance Notes

- **`curl_multi`** sends 50 concurrent requests per batch (configurable)
- **OAuth2 access tokens** are cached for ~55 minutes (1 hour validity minus 5 min buffer)
- **Invalid tokens** are removed immediately, keeping the database clean
- **Scheduler** runs in a separate process — never blocks the web server
- **Multicast** continues even if individual sends fail
- For **thousands of users**, the batch size can be increased in `fcm_send_multicast()` (default: 50)

---

*Built for NutriVision AI — Smart Nutrition & Fitness Assistant*