#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
2026 Yılı Planlı Bakım Takvimi PDF Oluşturucu
Veritabanındaki planlibakim tablosundan 2026 yılı verilerini çekerek
aylık takvim PDF çıktısı üretir.
"""

import os
import sys
from collections import defaultdict
from datetime import datetime

import mysql.connector
from fpdf import FPDF

# ─── Ayarlar ────────────────────────────────────────────────────────────────
DB_CONFIG = {
    "host": "127.0.0.1",
    "user": "root",
    "password": "",
    "database": "parkbkm",
    "charset": "utf8mb4",
}

AY_ISIMLERI = [
    "", "Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran",
    "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık",
]

PERIYOT_RENKLERI = {
    "Periyodik: 1 Ay": (52, 152, 219),    # Mavi
    "Periyodik: 3 Ay": (46, 204, 113),    # Yeşil
    "Periyodik: 6 Ay": (241, 196, 15),    # Sarı
    "Periyodik: 1 Yıl": (231, 76, 60),    # Kırmızı
}

DURUM_SEMBOLLERI = {
    "Plan dahilinde": "●",
    "Plan öncesi": "◐",
    "Plan sonrası": "◑",
    "İlk bakımı": "◆",
    "İlk Bakım": "◆",
    "ötelendi": "→",
}

OUTPUT_DIR = os.path.dirname(os.path.abspath(__file__))
OUTPUT_FILE = os.path.join(OUTPUT_DIR, "Planli_Bakim_Takvimi_2026.pdf")


# ─── Veritabanı ─────────────────────────────────────────────────────────────
def fetch_data():
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    # Ana veri
    cursor.execute("""
        SELECT kodu, tanimi, periyodu, tarihi, durumu
        FROM planlibakim
        WHERE tarihi >= '2026-01-01' AND tarihi <= '2026-12-31'
        ORDER BY kodu, tarihi
    """)
    rows = cursor.fetchall()

    # Özet istatistikler
    cursor.execute("""
        SELECT
            MONTH(tarihi) as ay,
            periyodu,
            COUNT(*) as sayi,
            SUM(CASE WHEN durumu NOT IN ('ötelendi') THEN 1 ELSE 0 END) as aktif
        FROM planlibakim
        WHERE tarihi >= '2026-01-01' AND tarihi <= '2026-12-31'
        GROUP BY MONTH(tarihi), periyodu
        ORDER BY ay, periyodu
    """)
    summary = cursor.fetchall()

    cursor.close()
    conn.close()
    return rows, summary


# ─── PDF Sınıfı ─────────────────────────────────────────────────────────────
class BakimTakvimPDF(FPDF):
    def __init__(self):
        super().__init__(orientation="L", unit="mm", format="A4")
        self._add_fonts()
        self.set_auto_page_break(auto=True, margin=15)

    def _add_fonts(self):
        font_dir = r"C:\Windows\Fonts"
        # DejaVu varsa onu kullan, yoksa Arial Unicode
        dejavu = os.path.join(font_dir, "DejaVuSans.ttf")
        arial_uni = os.path.join(font_dir, "ARIALUNI.TTF")
        segoe = os.path.join(font_dir, "segoeui.ttf")
        segoe_bold = os.path.join(font_dir, "segoeuib.ttf")

        if os.path.exists(segoe):
            self.add_font("main", "", segoe, uni=True)
            if os.path.exists(segoe_bold):
                self.add_font("main", "B", segoe_bold, uni=True)
            else:
                self.add_font("main", "B", segoe, uni=True)
        elif os.path.exists(dejavu):
            self.add_font("main", "", dejavu, uni=True)
            self.add_font("main", "B", dejavu, uni=True)
        elif os.path.exists(arial_uni):
            self.add_font("main", "", arial_uni, uni=True)
            self.add_font("main", "B", arial_uni, uni=True)
        else:
            # Fallback
            self.add_font("main", "", os.path.join(font_dir, "arial.ttf"), uni=True)
            self.add_font("main", "B", os.path.join(font_dir, "arialbd.ttf"), uni=True)

    def header(self):
        self.set_font("main", "B", 11)
        self.set_text_color(44, 62, 80)
        self.cell(0, 8, "2026 YILI PLANLI BAKIM TAKVİMİ", 0, 0, "C")
        self.ln(4)
        self.set_draw_color(52, 73, 94)
        self.set_line_width(0.5)
        self.line(10, self.get_y(), self.w - 10, self.get_y())
        self.ln(4)

    def footer(self):
        self.set_y(-12)
        self.set_font("main", "", 7)
        self.set_text_color(149, 165, 166)
        self.cell(0, 10, f"Sayfa {self.page_no()}/{{nb}}", 0, 0, "C")
        self.cell(0, 10, f"Oluşturulma: {datetime.now().strftime('%d.%m.%Y %H:%M')}", 0, 0, "R")

    # ── Renkli hücre yardımcıları ───────
    def colored_header_cell(self, w, h, txt, border=1, ln=0, align="C"):
        self.set_fill_color(52, 73, 94)
        self.set_text_color(255, 255, 255)
        self.set_font("main", "B", 8)
        self.cell(w, h, txt, border, ln, align, fill=True)

    def data_cell(self, w, h, txt, border=1, ln=0, align="C", fill_color=None):
        if fill_color:
            self.set_fill_color(*fill_color)
            fill = True
        else:
            fill = False
        self.set_text_color(44, 62, 80)
        self.set_font("main", "", 7)
        self.cell(w, h, txt, border, ln, align, fill=fill)


# ─── PDF Oluşturma ──────────────────────────────────────────────────────────
def build_pdf(rows, summary):
    pdf = BakimTakvimPDF()
    pdf.alias_nb_pages()

    # ═══════════════════════════════════════════════════
    # SAYFA 1: ÖZET TABLO  (Aylık / Periyot Dağılımı)
    # ═══════════════════════════════════════════════════
    pdf.add_page()
    pdf.set_font("main", "B", 12)
    pdf.set_text_color(44, 62, 80)
    pdf.cell(0, 10, "ÖZET: AYLIK BAKIM DAĞILIMI", 0, 1, "C")
    pdf.ln(2)

    periyotlar = ["Periyodik: 1 Ay", "Periyodik: 3 Ay", "Periyodik: 6 Ay", "Periyodik: 1 Yıl"]

    # Başlık satırı
    col_w_ay = 30
    col_w_per = 40
    col_w_top = 35
    row_h = 7

    x_start = (pdf.w - col_w_ay - len(periyotlar) * col_w_per - col_w_top) / 2
    pdf.set_x(x_start)
    pdf.colored_header_cell(col_w_ay, row_h, "AY")
    for p in periyotlar:
        kisa = p.replace("Periyodik: ", "")
        pdf.colored_header_cell(col_w_per, row_h, kisa)
    pdf.colored_header_cell(col_w_top, row_h, "TOPLAM")
    pdf.ln()

    # Özet verisi düzenle
    ay_periyot = defaultdict(lambda: defaultdict(int))
    for s in summary:
        per_str = s["periyodu"]
        # Terminal encoding sorunlarından dolayı normalize edelim
        for p in periyotlar:
            if p.split(": ")[1][:2].lower() in per_str.lower() or per_str == p:
                ay_periyot[s["ay"]][p] = s["sayi"]
                break
        else:
            ay_periyot[s["ay"]][per_str] = s["sayi"]

    genel_toplam = 0
    for ay_no in range(1, 13):
        pdf.set_x(x_start)
        if ay_no % 2 == 0:
            bg = (234, 242, 248)
        else:
            bg = None

        pdf.data_cell(col_w_ay, row_h, AY_ISIMLERI[ay_no], fill_color=bg)
        ay_toplam = 0
        for p in periyotlar:
            val = ay_periyot[ay_no].get(p, 0)
            ay_toplam += val
            r, g, b = PERIYOT_RENKLERI.get(p, (200, 200, 200))
            if val > 0:
                # Açık tonlu arka plan
                light_bg = (min(r + 160, 245), min(g + 160, 245), min(b + 160, 245))
                pdf.data_cell(col_w_per, row_h, str(val), fill_color=light_bg)
            else:
                pdf.data_cell(col_w_per, row_h, "-", fill_color=bg)
        genel_toplam += ay_toplam
        pdf.set_font("main", "B", 7)
        pdf.data_cell(col_w_top, row_h, str(ay_toplam), fill_color=(214, 234, 248))
        pdf.set_font("main", "", 7)
        pdf.ln()

    # Genel toplam
    pdf.set_x(x_start)
    pdf.colored_header_cell(col_w_ay, row_h, "TOPLAM")
    for p in periyotlar:
        toplam = sum(ay_periyot[ay].get(p, 0) for ay in range(1, 13))
        pdf.colored_header_cell(col_w_per, row_h, str(toplam))
    pdf.colored_header_cell(col_w_top, row_h, str(genel_toplam))
    pdf.ln(12)

    # Lejant
    pdf.set_font("main", "B", 9)
    pdf.cell(0, 6, "Periyot Renk Kodları:", 0, 1)
    pdf.set_font("main", "", 8)
    for p, (r, g, b) in PERIYOT_RENKLERI.items():
        pdf.set_fill_color(r, g, b)
        pdf.cell(8, 5, "", 1, 0, fill=True)
        pdf.cell(2)
        pdf.set_text_color(44, 62, 80)
        pdf.cell(60, 5, p, 0, 1)
    pdf.ln(5)

    pdf.set_font("main", "", 8)
    pdf.set_text_color(100, 100, 100)
    pdf.cell(0, 5, f"Toplam {len(set(r['kodu'] for r in rows))} farklı ekipman için {len(rows)} planlı bakım kaydı", 0, 1)

    # ═══════════════════════════════════════════════════
    # SAYFA 2+: AYLIK DETAY TABLOLARI
    # ═══════════════════════════════════════════════════
    # Aylara göre grupla
    aylik_veri = defaultdict(list)
    for r in rows:
        ay = r["tarihi"].month if hasattr(r["tarihi"], "month") else int(str(r["tarihi"]).split("-")[1])
        aylik_veri[ay].append(r)

    for ay_no in range(1, 13):
        kayitlar = aylik_veri.get(ay_no, [])
        if not kayitlar:
            continue

        pdf.add_page()
        pdf.set_font("main", "B", 12)
        pdf.set_text_color(44, 62, 80)
        pdf.cell(0, 8, f"{AY_ISIMLERI[ay_no].upper()} 2026 - PLANLI BAKIM DETAYI ({len(kayitlar)} kayıt)", 0, 1, "C")
        pdf.ln(2)

        # Tablo başlıkları
        col_widths = [8, 25, 95, 30, 22, 30, 62]
        headers = ["#", "Ekipman Kodu", "Tanımı", "Periyodu", "Tarihi", "Durumu", "Not"]

        for i, h in enumerate(headers):
            pdf.colored_header_cell(col_widths[i], 7, h)
        pdf.ln()

        # Satırlar
        for idx, k in enumerate(kayitlar, 1):
            if pdf.get_y() > pdf.h - 20:
                pdf.add_page()
                pdf.set_font("main", "B", 10)
                pdf.set_text_color(44, 62, 80)
                pdf.cell(0, 8, f"{AY_ISIMLERI[ay_no].upper()} 2026 (devam)", 0, 1, "C")
                pdf.ln(2)
                for i, h in enumerate(headers):
                    pdf.colored_header_cell(col_widths[i], 7, h)
                pdf.ln()

            bg = (245, 248, 250) if idx % 2 == 0 else None

            kodu = str(k.get("kodu", "") or "")
            tanimi = str(k.get("tanimi", "") or "")
            periyodu = str(k.get("periyodu", "") or "")
            durumu = str(k.get("durumu", "") or "")

            tarihi_val = k.get("tarihi")
            if hasattr(tarihi_val, "strftime"):
                tarihi = tarihi_val.strftime("%d.%m")
            else:
                tarihi = str(tarihi_val)

            periyot_kisa = periyodu.replace("Periyodik: ", "")

            # Periyot rengine göre sol kenar
            per_renk = PERIYOT_RENKLERI.get(periyodu, (200, 200, 200))

            pdf.data_cell(col_widths[0], 6, str(idx), fill_color=bg)
            pdf.data_cell(col_widths[1], 6, kodu, align="L", fill_color=bg)

            # Tanımı kısalt
            max_tanimi_len = 58
            tanimi_kisa = tanimi[:max_tanimi_len] + "..." if len(tanimi) > max_tanimi_len else tanimi
            pdf.data_cell(col_widths[2], 6, tanimi_kisa, align="L", fill_color=bg)

            # Periyot hücresini renkli yap
            pdf.set_fill_color(*per_renk)
            pdf.set_text_color(255, 255, 255)
            pdf.set_font("main", "B", 7)
            pdf.cell(col_widths[3], 6, periyot_kisa, 1, 0, "C", fill=True)

            pdf.data_cell(col_widths[4], 6, tarihi, fill_color=bg)
            pdf.data_cell(col_widths[5], 6, durumu, fill_color=bg)
            pdf.data_cell(col_widths[6], 6, "", fill_color=bg)
            pdf.ln()

    # ═══════════════════════════════════════════════════
    # EKIPMAN BAZLI YILLIK TAKVIM MATRİSİ
    # ═══════════════════════════════════════════════════
    # Ekipmanlara göre grupla
    ekipman_takvim = defaultdict(lambda: defaultdict(list))
    for r in rows:
        kodu = str(r.get("kodu", "") or "")
        ay = r["tarihi"].month if hasattr(r["tarihi"], "month") else int(str(r["tarihi"]).split("-")[1])
        periyodu = str(r.get("periyodu", "") or "")
        ekipman_takvim[kodu][ay].append(periyodu)

    # Ekipmanları sırala
    sorted_ekipmanlar = sorted(ekipman_takvim.keys())

    pdf.add_page()
    pdf.set_font("main", "B", 12)
    pdf.set_text_color(44, 62, 80)
    pdf.cell(0, 8, "EKİPMAN BAZLI YILLIK BAKIM TAKVİMİ MATRİSİ", 0, 1, "C")
    pdf.ln(2)

    # Matris başlıkları
    col_ek = 30
    col_ay = (pdf.w - 20 - col_ek) / 12
    row_h_m = 5.5

    def draw_matrix_header():
        pdf.colored_header_cell(col_ek, 7, "Ekipman")
        for m in range(1, 13):
            pdf.colored_header_cell(col_ay, 7, AY_ISIMLERI[m][:3])
        pdf.ln()

    draw_matrix_header()

    for idx, ek_kodu in enumerate(sorted_ekipmanlar):
        if pdf.get_y() > pdf.h - 18:
            pdf.add_page()
            pdf.set_font("main", "B", 10)
            pdf.set_text_color(44, 62, 80)
            pdf.cell(0, 8, "EKİPMAN BAZLI YILLIK BAKIM TAKVİMİ (devam)", 0, 1, "C")
            pdf.ln(2)
            draw_matrix_header()

        bg = (245, 248, 250) if idx % 2 == 0 else None

        pdf.set_font("main", "", 6)
        pdf.set_text_color(44, 62, 80)
        if bg:
            pdf.set_fill_color(*bg)
        pdf.cell(col_ek, row_h_m, ek_kodu, 1, 0, "L", fill=bool(bg))

        for m in range(1, 13):
            periyotlar_ay = ekipman_takvim[ek_kodu].get(m, [])
            if periyotlar_ay:
                # En yüksek periyotun rengini kullan
                onceklik = ["Periyodik: 1 Yıl", "Periyodik: 6 Ay", "Periyodik: 3 Ay", "Periyodik: 1 Ay"]
                renk = (200, 200, 200)
                etiket = str(len(periyotlar_ay))
                for oncelik in onceklik:
                    if oncelik in periyotlar_ay:
                        renk = PERIYOT_RENKLERI.get(oncelik, (200, 200, 200))
                        break
                pdf.set_fill_color(*renk)
                pdf.set_text_color(255, 255, 255)
                pdf.set_font("main", "B", 6)
                pdf.cell(col_ay, row_h_m, etiket, 1, 0, "C", fill=True)
            else:
                pdf.set_text_color(200, 200, 200)
                pdf.set_font("main", "", 6)
                if bg:
                    pdf.set_fill_color(*bg)
                pdf.cell(col_ay, row_h_m, "-", 1, 0, "C", fill=bool(bg))
        pdf.ln()

    # Alt lejant
    pdf.ln(5)
    pdf.set_font("main", "", 7)
    pdf.set_text_color(44, 62, 80)
    pdf.cell(0, 5, "Hücredeki sayı = O ay yapılacak bakım adedi  |  Renk = En yüksek periyotlu bakım tipi", 0, 1)
    pdf.ln(2)
    for p, (r, g, b) in PERIYOT_RENKLERI.items():
        pdf.set_fill_color(r, g, b)
        pdf.cell(6, 4, "", 1, 0, fill=True)
        pdf.cell(2)
        pdf.cell(50, 4, p, 0, 1)

    # ═══════════════════════════════════════════════════
    # KAYDET
    # ═══════════════════════════════════════════════════
    pdf.output(OUTPUT_FILE)
    return OUTPUT_FILE


# ─── Ana ─────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    print("Veritabanından veriler çekiliyor...")
    rows, summary = fetch_data()
    print(f"  {len(rows)} kayıt, {len(set(r['kodu'] for r in rows))} farklı ekipman")
    print("PDF oluşturuluyor...")
    path = build_pdf(rows, summary)
    print(f"PDF başarıyla oluşturuldu: {path}")
