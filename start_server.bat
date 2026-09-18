@echo off
echo ========================================
echo   NutriVision AI - Backend Server
echo   Starting Python AI/ML Server...
echo ========================================
echo.

cd /d "%~dp0"

echo Checking Python installation...
python --version
if errorlevel 1 (
    echo Python not found. Please install Python 3.8+
    pause
    exit /b 1
)

echo Installing dependencies...
pip install tensorflow pillow numpy pandas scikit-learn flask flask-cors nltk 2>nul

echo.
echo ========================================
echo Starting AI Backend on port 5050
echo Press Ctrl+C to stop
echo ========================================
echo.

python app.py

pause