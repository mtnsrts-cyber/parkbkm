# SMART SOG5 Güç Kontrol Rölesi - Web Dashboard

Grup Arge SMART SOG5 model güç kontrol rölesi için geliştirilmiş kapsamlı izleme ve kontrol uygulaması.

## 🎯 Özellikler

- ✅ **Canlı Veri İzleme**: Gerçek zamanlı gerilim, akım, güç, enerji takibi
- ✅ **Kademe Durumları**: 32 kademenin anlık durumu ve görselleştirmesi
- ✅ **Grafiksel Analiz**: Güç ve gerilim trendleri için interaktif grafikler
- ✅ **Veritabanı Kaydı**: Tüm verilerin SQLite veritabanında saklanması
- ✅ **RESTful API**: Diğer uygulamalarla entegrasyon için API
- ✅ **Modern Web Arayüzü**: Responsive, dark theme tasarım
- ✅ **Çoklu Register Desteği**: Tüm önemli parametreler
- ✅ **Hata Yönetimi**: Gateway lock mekanizması ve hata raporlama

## 📋 Sistem Gereksinimleri

### Donanım
- **Cihaz**: Grup Arge SMART SOG5 Güç Kontrol Rölesi
- **Gateway**: ENTES ETMO-02 Ethernet/RS-485 Dönüştürücü
- **IP Adresi**: 192.168.201.248:502
- **Modbus Unit ID**: 5
- **Protokol**: Modbus TCP → RTU

### Yazılım
- Python 3.7+
- Flask
- Flask-CORS

## 🚀 Kurulum

### 1. Python Bağımlılıklarını Yükleyin

```bash
pip install flask flask-cors
```

### 2. Uygulamayı Başlatın

#### Windows (Basit):
```bash
cd C:\xampp\htdocs\basic\scripts
python sog5_dashboard.py
```

#### Veya batch dosyası ile:
```bash
cd C:\xampp\htdocs\basic\scripts
start_sog5_dashboard.bat
```

### 3. Web Arayüzünü Açın

Tarayıcınızda şu adresi açın:
```
http://localhost:5000
```

## 📊 Kullanım

### Ana Dashboard

Dashboard otomatik olarak 5 saniye arayla güncellenir ve şu bilgileri gösterir:

#### Elektriksel Parametreler
- **Gerilim (V)**: L1, L2, L3 fazları
- **Akım (A)**: L1, L2, L3 fazları (0.01A hassasiyetle)
- **Aktif Güç (kW)**: Faz bazlı ve toplam
- **Reaktif Güç (kVAR)**: Endüktif reaktif güç
- **Güç Faktörü**: Her faz için (0.001 hassasiyetle)
- **Enerji (kWh)**: Tüketim sayacı

#### Kademe Durumları
- 32 kademenin anlık durumu
- Yeşil: Devrede
- Gri: Devre dışı
- Bit bazlı durum takibi

#### Grafikler
- **Güç Trendi**: Son 50 ölçüm
- **Gerilim Trendi**: Faz bazlı izleme

### API Kullanımı

#### Güncel Veri
```bash
curl http://localhost:5000/api/current
```

#### Geçmiş Veri (Bellekten)
```bash
curl http://localhost:5000/api/history
```

#### Veritabanından Geçmiş
```bash
curl "http://localhost:5000/api/db/history?hours=24&limit=1000"
```

#### Sistem Durumu
```bash
curl http://localhost:5000/api/status
```

## 🔧 Yapılandırma

`sog5_dashboard.py` dosyasındaki şu değişkenleri düzenleyebilirsiniz:

```python
DEFAULT_IP = "192.168.201.248"      # Gateway IP adresi
DEFAULT_PORT = 502                   # Modbus TCP portu
DEFAULT_UNIT_ID = 5                  # SOG5 Modbus adresi
POLL_INTERVAL = 5                    # Veri toplama aralığı (saniye)
HISTORY_SIZE = 100                   # Bellekte tutulacak veri sayısı
DB_RETENTION_DAYS = 30               # Veritabanı saklama süresi
```

## 📁 Dosya Yapısı

```
scripts/
├── sog5_dashboard.py           # Ana Python uygulaması
├── sog5_monitor.py             # Komut satırı izleme aracı
├── sog5_test.py               # İlk bağlantı testi
├── gruparge_analizor.py       # Basit terminal izleyici
├── start_sog5_dashboard.bat   # Windows başlatıcı
├── SOG5_README.md             # Bu dosya
├── sog5_templates/
│   └── sog5_dashboard.html    # Ana HTML şablonu
└── sog5_static/
    ├── style.css              # CSS stilleri
    └── app.js                 # JavaScript uygulaması

runtime/
├── sog5_data.db               # SQLite veritabanı (otomatik oluşur)
└── locks/                     # Gateway kilit dosyaları
```

## 📖 Register Haritası

SOG5 kılavuzunda belirtilen önemli register adresleri:

| Parametre | Adres | Tip | Ölçek | Birim |
|-----------|-------|-----|-------|-------|
| Enerji L1 (Tüketim) | 0 | u32 | 1000 | kWh |
| Aktif Güç L1 | 24 | s32 | 1000 | kW |
| Reaktif Güç L1 | 30 | s32 | 1000 | kVAR |
| Güç Faktörü L1 | 42 | s16 | 100 | - |
| Frekans L1 | 47 | u16 | 1 | Hz |
| THD Akım L1 | 50 | u16 | 1 | % |
| Gerilim L1 | 56 | u16 | 1 | V |
| Akım L1 | 59 | u32 | 100 | A |
| Kademe Durumu | 73 | u32 | 1 | bit |

_Not: L2 ve L3 için offset +2 veya +1 ekleyin_

## 🔍 Sorun Giderme

### Bağlantı Hatası alıyorsanız:

1. **Cihaz IP'sini kontrol edin**:
   ```bash
   ping 192.168.201.248
   ```

2. **Gateway'in çalıştığından emin olun**

3. **Başka bir uygulama bağlı olabilir**:
   - LogReader'ı kapatın
   - Diğer Modbus istemcilerini kapatın

4. **Unit ID'yi doğrulayın**:
   ```bash
   python sog5_monitor.py --ip 192.168.201.248 --scan
   ```

### Port 5000 kullanımda hatası:

Flask portunu değiştirin:
```python
app.run(host='0.0.0.0', port=5001, debug=False, threaded=True)
```

### Veritabanı hatası:

Veritabanını sıfırlayın:
```bash
del C:\xampp\htdocs\basic\runtime\sog5_data.db
```

## 🛠️ Komut Satırı Araçları

### sog5_monitor.py
Gelişmiş izleme ve kontrol:

```bash
# Tek okuma
python sog5_monitor.py --ip 192.168.201.248 --unit-id 5 --once

# Sürekli izleme (10 sn aralıkla)
python sog5_monitor.py --ip 192.168.201.248 --unit-id 5 --interval 10

# JSON çıktı
python sog5_monitor.py --ip 192.168.201.248 --unit-id 5 --once --json

# Unit ID tarama
python sog5_monitor.py --ip 192.168.201.248 --scan

# Register yazma (FC06)
python sog5_monitor.py --ip 192.168.201.248 --unit-id 5 --write 180 6
```

### sog5_test.py
İlk bağlantı testi:

```bash
python sog5_test.py --ip 192.168.201.248 --unit 5
```

### gruparge_analizor.py
Basit terminal izleyici:

```bash
python gruparge_analizor.py --ip 192.168.201.248 --unit 5 --interval 5
```

## 📝 Notlar

- SOG5, **çoklu register okumayı desteklemiyor**. Her register ayrı FC03 isteği ile okunur.
- Gateway kilit mekanizması, eşzamanlı erişimi engelleyerek veri bütünlüğünü sağlar.
- Veritabanı otomatik olarak eski kayıtları temizler (30 gün varsayılan).
- Tüm veriler `runtime/sog5_data.db` SQLite veritabanında saklanır.

## 🔗 İlgili Belgeler

- [SOG5 Kullanma Kılavuzu](https://www.gruparge.com/wp-content/uploads/2023/01/smart-svc-role-kullanma-kilavuzu.pdf)
- [Modbus Protocol Specification](http://www.modbus.org/docs/Modbus_Application_Protocol_V1_1b3.pdf)

## 📧 Destek

Sorularınız için:
- Grup Arge: [www.gruparge.com](https://www.gruparge.com)
- RS-485 Parametreleri: 19200 bps, 8N1

## 📜 Versiyon Geçmişi

### v1.0 (2026-04-28)
- ✅ İlk sürüm
- ✅ Web dashboard
- ✅ Canlı veri izleme
- ✅ Kademe durumları
- ✅ Grafiksel analiz
- ✅ Veritabanı kaydı
- ✅ RESTful API

---

**SMART SOG5 Dashboard** | Developed for Grup Arge Power Control Relay | 2026
