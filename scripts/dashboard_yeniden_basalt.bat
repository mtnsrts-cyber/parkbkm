@echo off
title SOG5 Dashboard - Yeniden Baslatma

echo Dashboard yeniden baslatiliyor...

REM Once durdur
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":5000 "') do (
    taskkill /PID %%a /F >nul 2>&1
)

timeout /t 2 /nobreak >nul

REM Python yolunu bul
for /f "tokens=*" %%i in ('where python 2^>nul') do set PYTHON_PATH=%%i

REM Yeniden basalt (arka planda)
start "" /b "%PYTHON_PATH%" "c:\xampp\htdocs\basic\scripts\sog5_dashboard.py"

echo [OK] Dashboard yeniden baslatildi.
echo Adres: http://localhost:5000
timeout /t 3 /nobreak >nul

REM Tarayiciyi ac
start http://localhost:5000
