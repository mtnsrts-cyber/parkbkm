@echo off
title SOG5 Dashboard - Durdur

echo Dashboard ve Watchdog durduruluyor...

REM Tum pythonw.exe proseslerini durdur (watchdog + dashboard)
taskkill /f /im pythonw.exe >nul 2>&1

REM Port 5000 uzerindeki kalan proseleri durdur
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":5000 "') do (
    taskkill /PID %%a /F >nul 2>&1
)

echo [OK] Dashboard durduruldu.
timeout /t 2 /nobreak >nul
