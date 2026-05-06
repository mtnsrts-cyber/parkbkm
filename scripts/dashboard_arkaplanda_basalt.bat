@echo off
REM SOG5 Dashboard - Baslatici (Yonetici yetkisi gerekmez)
REM Arka planda sessizce calisir, CMD penceresi kapanir

set SCRIPT_PATH=c:\xampp\htdocs\basic\scripts\sog5_dashboard.py

REM Python yolunu bul
for /f "tokens=*" %%i in ('where python 2^>nul') do set PYTHON_PATH=%%i

if "%PYTHON_PATH%"=="" goto hata

REM Arka planda basalt (pencere yok)
start "" /b "%PYTHON_PATH%" "%SCRIPT_PATH%"
goto bitis

:hata
echo Python bulunamadi!
pause

:bitis
