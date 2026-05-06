@echo off
REM SMART SOG5 Dashboard Başlatıcı
REM Grup Arge SOG5 Güç Kontrol Rölesi - Web Dashboard

title SOG5 Dashboard Launcher

echo ============================================================
echo   SMART SOG5 Guc Kontrol Rolesi - Web Dashboard
echo ============================================================
echo.
echo Baslaniyor...
echo.

REM Python sürümünü kontrol et
python --version >nul 2>&1
if errorlevel 1 (
    echo [HATA] Python bulunamadi!
    echo Python 3.7+ yuklu oldugunu dogrulayin.
    echo.
    pause
    exit /b 1
)

echo [OK] Python bulundu
echo.

REM Flask kontrolü
python -c "import flask" >nul 2>&1
if errorlevel 1 (
    echo [UYARI] Flask yuklu degil!
    echo.
    echo Flask yukleniyor...
    pip install flask flask-cors
    if errorlevel 1 (
        echo.
        echo [HATA] Flask yuklenemedi!
        pause
        exit /b 1
    )
    echo [OK] Flask yuklendi
    echo.
)

REM Çalışma dizinine geç
cd /d "%~dp0"

echo ============================================================
echo   Dashboard Baslatiliyor...
echo ============================================================
echo.
echo   Web Arayuzu: http://localhost:5000
echo   Cihaz IP: 192.168.201.248:502
echo   Unit ID: 5
echo.
echo   Durdurmak icin Ctrl+C basin
echo ============================================================
echo.

REM Dashboard'u başlat
python sog5_dashboard.py

if errorlevel 1 (
    echo.
    echo [HATA] Dashboard baslatilirken hata olustu!
    echo.
    echo Olasi nedenler:
    echo - Cihaz bagli degil (192.168.201.248)
    echo - Port 5000 kullaniliyor
    echo - Baska bir Modbus client baglanmis
    echo.
    pause
)
