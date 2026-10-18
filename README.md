# NutriVision 🥗

NutriVision AI is a nutrition and diet-tracking web app that pairs a **PHP/MySQL** frontend with a **Python (Flask) AI backend**. It lets users log meals (manually or via AI food-image detection), track water intake and goals, get AI-generated diet plans and nutrition insights, and receive meal-reminder push notifications.

## Features

- **User accounts** — registration, login, session-based auth (`register.php`, `login.php`, `logout.php`)
- **Meal logging** — manual entry (`add_meal.php`) and AI-assisted entry from a food photo (`add_meal_ai.php`)
- **AI food detection (CNN)** — identifies food from an uploaded image and estimates nutrition info
- **AI diet planning (LSTM)** — generates a personalized diet plan based on the user's goal and history
- **Nutrition pattern analysis (RNN)** — analyzes meal history for trends (e.g. calorie trend, daily summaries)
- **AI meal preview (GAN)** — generates a preview/suggestion for a given meal type
- **NLP chatbot** — conversational assistant for nutrition questions (`nlp_chatbot.py`)
- **Goals & dashboard** — set targets and view progress (`goal.php`, `dashboard.php`)
- **Water tracker** — logs daily water intake, resets each day (`water.php`)
- **Meal history & reviews** — browse past meals (`meal_history.php`) and read/leave reviews (`reviews.php`)
- **Push notifications** — Firebase Cloud Messaging meal reminders, with a scheduler to avoid duplicate sends

## Architecture

```
┌─────────────────┐         HTTP (JSON / multipart)        ┌──────────────────────┐
│   PHP Frontend   │ ───────────────────────────────────▶  │   Flask AI Backend   │
│  (Apache/MySQL)  │ ◀───────────────────────────────────  │   app.py :5050        │
└─────────────────┘                                        └──────────────────────┘
        │                                                           │
        │ MySQL (database.sql)                          Lazy-loaded ML models:
        │                                                 - CNN  (cnn_food_detection.py)
        ▼                                                 - LSTM (lstm_diet_planner.py)
   users / meals / goals /                                 - RNN  (rnn_nutrition_analysis.py)
   reviews / notifications                                 - GAN  (gan_meal_preview.py)
                                                             - NLP  (nlp_chatbot.py)
        │
        ▼
┌─────────────────┐
│ Firebase (FCM)   │  push notifications for meal reminders
└─────────────────┘
```

`app.py` acts as a bridge (`ai_bridge.php` on the PHP side) exposing REST endpoints that the PHP frontend calls for all AI features.

### AI backend endpoints (`app.py`, port 5050)

| Endpoint | Method | Purpose |
|---|---|---|
| `/` | GET | Health check / lists loaded models |
| `/api/cnn/predict` | POST (multipart, `image`) | Detect food from an image |
| `/api/lstm/diet-plan` | POST (JSON) | Generate a personalized diet plan |
| `/api/rnn/analyze` | POST (JSON) | Analyze nutrition patterns from meal history |
| `/api/gan/preview` | POST (JSON) | Generate a meal preview |
| `/api/nlp/chat` | POST (JSON) | Chatbot response |
| `/api/analyze/user-dashboard` | POST (JSON) | Combined RNN analysis for the dashboard |

## Tech Stack

- **Frontend:** PHP, MySQL, HTML/CSS/JS
- **AI Backend:** Python 3, Flask, Flask-CORS, TensorFlow/Keras, NLTK
- **Notifications:** Firebase Cloud Messaging (FCM), a PHP-based scheduler (`run_scheduler.php` / `send_meal_reminders.php`)

## Project Structure (key files)

```
├── app.py                      # Flask AI backend entrypoint
├── cnn_food_detection.py       # CNN model — food detection from images
├── lstm_diet_planner.py        # LSTM model — diet plan generation
├── rnn_nutrition_analysis.py   # RNN model — nutrition pattern analysis
├── gan_meal_preview.py         # GAN model — meal preview generation
├── nlp_chatbot.py              # NLP chatbot logic
├── lstm_diet_planner.h5        # Trained LSTM weights
├── lstm_scaler.pkl             # Scaler used by the LSTM model
│
├── index.php                   # Landing / login-register page
├── login.php / register.php    # Auth
├── dashboard.php                # Main user dashboard
├── add_meal.php / add_meal_ai.php   # Manual & AI-assisted meal logging
├── edit_meal.php / meal_history.php # Meal management & history
├── goal.php                    # Goal setting
├── water.php                   # Water intake tracker
├── reviews.php                 # User reviews
├── diet_plan_ai.php / ai_insights.php # AI-powered pages
├── ai_bridge.php                # Bridges PHP requests to the Flask API
│
├── db.php / setup_db.php / database.sql  # DB connection & schema
├── env_loader.php / .env.example         # Environment config loader/template
│
├── fcm_service.php             # Firebase push notification service
├── firebase-config.php / firebase-config-sw.js.php
├── firebase-messaging-sw.js    # Firebase service worker
├── push-notifications.js       # Client-side push notification logic
├── save_token.php / remove_token.php / notification_logger.php / notification_status.php
├── run_scheduler.php / send_meal_reminders.php  # Meal reminder scheduler
├── start_scheduler.bat / start_server.bat        # Windows helper scripts
│
├── style.css                   # Site styles
├── logo.svg, goals.svg, history.svg, add-meal.svg  # UI icons
└── CHANGES.md / NOTIFICATIONS.md                   # Project notes
```

## Getting Started

### Prerequisites

- PHP 7.4+ with a MySQL-compatible server (Apache/XAMPP/WAMP or similar)
- Python 3.9+ (TensorFlow-compatible)
- A Firebase project (only needed for push notifications)

### 1. Clone the repo

```bash
git clone https://github.com/nishachhatralia/Nutri_vision.git
cd Nutri_vision
```

### 2. Set up the database

Import the schema into MySQL:

```bash
mysql -u root -p < database.sql
```

Then configure `db.php` with your database credentials.

### 3. Configure environment variables

Copy the example env file and fill in your Firebase details:

```bash
cp .env.example .env
```

Edit `.env`:

| Variable | Description |
|---|---|
| `FIREBASE_PROJECT_ID` | Firebase project ID |
| `FIREBASE_SERVICE_ACCOUNT_PATH` | Path to the Firebase service account JSON (download from Firebase Console → Project Settings → Service Accounts) |
| `FIREBASE_API_KEY`, `FIREBASE_AUTH_DOMAIN`, `FIREBASE_DATABASE_URL`, `FIREBASE_STORAGE_BUCKET`, `FIREBASE_MESSAGING_SENDER_ID`, `FIREBASE_APP_ID` | Firebase web app config (public, safe for frontend) |
| `FIREBASE_VAPID_PUBLIC_KEY` | VAPID key for web push |
| `NOTIFICATION_LOG_PATH` | Where notification delivery logs are written |
| `NOTIFICATION_TIMEZONE` | Timezone used by the reminder scheduler |

Also copy `firebase-service-account.example.json` → `config/firebase-service-account.json` and fill it in with your real service-account key. **Never commit real credentials.**

### 4. Run the PHP frontend

Serve the project root with Apache/XAMPP (or PHP's built-in server for local testing):

```bash
php -S localhost:8000
```

### 5. Run the AI backend

```bash
pip install flask flask-cors tensorflow nltk
python app.py
```

The Flask server starts on `http://0.0.0.0:5050` and lazy-loads each model on first use.

On Windows, you can instead use the provided helper scripts:

```bash
start_server.bat       # starts services
start_scheduler.bat    # starts the meal-reminder scheduler
```

### 6. (Optional) Enable meal reminders

For push notifications to actually reach devices, either:
- add a real system cron entry that runs `send_meal_reminders.php` periodically, or
- use an external HTTP cron pinger (e.g. cron-job.org) if your host has no shell/cron access, or
- run `run_scheduler.php` as a long-lived process (requires a host that allows this).

## Known Limitations

- **Push notifications are implemented but not fully wired up.** `.env` ships with placeholder Firebase values, and the real `firebase-service-account.json` isn't included (only the `.example.json` template). Until real Firebase credentials are added, reminder notifications won't actually be delivered — the scheduler will log "started" but no send will go through.
- **The reminder scheduler needs a real cron job or an always-on process** to fire reliably. On basic/shared hosting without cron access, meal reminders won't trigger on their own.

## Notes

- See `CHANGES.md` for a detailed log of recent fixes (icon rendering, notification timing, water tracker date bug, branding consistency, etc.).
- See `NOTIFICATIONS.md` for more detail on the push notification system.

## License

No license specified yet — consider adding one (e.g. MIT) if you plan to share or accept contributions.
