@echo off
REM SOG5 Dashboard - Gorev Zamanlayici Kurulum Scripti
REM Bu dosyayi SAG TIK -> "Yonetici olarak calistir" ile calistirin

title SOG5 Dashboard - Otomatik Baslama Kurulumu

echo ============================================================
echo   SOG5 Dashboard - Windows Gorev Zamanlayici Kurulumu
echo ============================================================
echo.

REM Yonetici kontrolu
net session >nul 2>&1
if errorlevel 1 (
    echo [HATA] Bu scripti yonetici olarak calistirmaniz gerekiyor!
    echo.
    echo SAG TIK - "Yonetici olarak calistir" secin.
    echo.
    pause
    exit /b 1
)

REM Python yolunu bul
for /f "tokens=*" %%i in ('where python 2^>nul') do set PYTHON_PATH=%%i
if "%PYTHON_PATH%"=="" (
    echo [HATA] Python bulunamadi! PATH'e ekli mi?
    pause
    exit /b 1
)
echo [OK] Python: %PYTHON_PATH%

REM Script yolu
set SCRIPT_PATH=c:\xampp\htdocs\basic\scripts\sog5_dashboard.py
set WORK_DIR=c:\xampp\htdocs\basic\scripts

echo [OK] Script: %SCRIPT_PATH%
echo.

REM Mevcut gorevi sil (varsa)
schtasks /delete /tn "SOG5_Dashboard" /f >nul 2>&1

REM Gorevi olustur - Oturum acildiginda basla, arka planda calis
schtasks /create ^
  /tn "SOG5_Dashboard" ^
  /tr "\"%PYTHON_PATH%\" \"%SCRIPT_PATH%\"" ^
  /sc ONLOGON ^
  /rl HIGHEST ^
  /f ^
  /ru "%USERNAME%"

if errorlevel 1 (
    echo [HATA] Gorev olusturulamadi!
    pause
    exit /b 1
)

echo.
echo ============================================================
echo   [OK] Kurulum tamamlandi!
echo ============================================================
echo.
echo   - Bilgisayar her acildiginda dashboard otomatik baslar
echo   - http://localhost:5000 adresinden erisebilirsiniz
echo   - Gorevi durdurmak icin: schtasks /end /tn "SOG5_Dashboard"
echo   - Gorevi kaldirmak icin: schtasks /delete /tn "SOG5_Dashboard" /f
echo.
echo Simdi test olarak baslatiliyor...
echo.

REM Hemen bir kez calistir
start "" /b "%PYTHON_PATH%" "%SCRIPT_PATH%"

echo Dashboard baslatildi. Birkac saniye bekleyin...
timeout /t 4 /nobreak >nul

echo Tarayicida kontrol edin: http://localhost:5000
echo.
pause
