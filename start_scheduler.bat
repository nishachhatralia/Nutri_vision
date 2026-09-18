@echo off
REM ============================================================
REM  NutriVision AI - Push Notification Scheduler Launcher
REM ============================================================
REM  Double-click this file to start the continuous scheduler.
REM  It sends meal reminders at 09:00, 12:00, and 20:00 (Asia/Kolkata).
REM  Keep this window open while the scheduler is running.
REM  Press Ctrl+C to stop.
REM ============================================================

cd /d "%~dp0\.."

echo ============================================================
echo   NutriVision AI - Push Notification Scheduler
echo ============================================================
echo   Starting scheduler...
echo   This window must stay open for notifications to be sent.
echo   Press Ctrl+C to stop.
echo ============================================================
echo.

REM Try to find PHP in common XAMPP locations
set PHP_EXE=php

REM Check if php is in PATH
where php >nul 2>&1
if %errorlevel% neq 0 (
    REM Try XAMPP default path
    if exist "E:\xampp\php\php.exe" (
        set PHP_EXE=E:\xampp\php\php.exe
    ) else (
        echo ERROR: PHP not found in PATH or E:\xampp\php\
        echo Please add PHP to your PATH or edit this file.
        pause
        exit /b 1
    )
)

"%PHP_EXE%" cron/run_scheduler.php

pause