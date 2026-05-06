#!/usr/bin/env python3
"""
SMART SOG5 Güç Kontrol Rölesi - Web Dashboard
Grup Arge SOG5 için kapsamlı izleme ve kontrol uygulaması

Özellikler:
- Canlı veri izleme (gerilim, akım, güç, enerji)
- Kademe durumları ve kontrol
- Gerçek zamanlı grafikler
- Veritabanı kaydı
- RESTful API
- Modern web arayüzü

Kullanım:
  python scripts/sog5_dashboard.py
  Tarayıcıda: http://localhost:5000

Bağımlılıklar:
  pip install flask flask-cors pymodbus
"""

import os
import sys
import json
import time
import socket
import struct
import sqlite3
import threading
from datetime import datetime, timedelta
from collections import deque

from flask import Flask, render_template, jsonify, request, send_from_directory
from flask_cors import CORS

# ─── Yapılandırma ────────────────────────────────────────────────────────────
DEFAULT_IP = "192.168.201.248"
DEFAULT_PORT = 502
DEFAULT_UNIT_ID = 5
POLL_INTERVAL = 5  # saniye
HISTORY_SIZE = 100  # Bellekte tutulacak veri sayısı
DB_RETENTION_DAYS = 30  # Veritabanında tutulacak gün sayısı
DEMO_HOURLY_DATE = "2026-05-01"
END_AKTIF_LIMIT_PCT = 20.0
KAP_AKTIF_LIMIT_PCT = 15.0
MAX_VALID_DAILY_ACTIVE_KWH = 100000.0
MAX_VALID_DAILY_REACTIVE_KVARH = 20000.0

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
RUNTIME_DIR = os.path.join(PROJECT_ROOT, "runtime")
DB_PATH = os.path.join(RUNTIME_DIR, "sog5_data.db")
LOCK_DIR = os.path.join(RUNTIME_DIR, "locks")

os.makedirs(RUNTIME_DIR, exist_ok=True)
os.makedirs(LOCK_DIR, exist_ok=True)

# ─── Modbus İletişim ─────────────────────────────────────────────────────────
CONNECT_TIMEOUT = 3
READ_TIMEOUT = 3
LOCK_TIMEOUT = 10.0
LOCK_WAIT = 0.05
STALE_LOCK_SECONDS = 15.0
REQUEST_DELAY = 0.12
REGISTER_MAP_LIVE_LOCK_TIMEOUT = 45.0


class GatewayLock:
    """Gateway için dosya bazlı kilit mekanizması"""
    def __init__(self, ip, port, timeout=LOCK_TIMEOUT):
        self.timeout = timeout
        safe_name = f"gateway_{ip.replace('.', '_')}_{port}.lock"
        self.lock_path = os.path.join(LOCK_DIR, safe_name)

    def __enter__(self):
        start = time.time()
        while True:
            try:
                os.mkdir(self.lock_path)
                return self
            except FileExistsError:
                # Crash kalan kilitleri temizle
                try:
                    age = time.time() - os.path.getmtime(self.lock_path)
                    if age >= STALE_LOCK_SECONDS:
                        os.rmdir(self.lock_path)
                        continue
                except FileNotFoundError:
                    continue
                except OSError:
                    pass

                if time.time() - start >= self.timeout:
                    raise TimeoutError(f"Gateway lock timeout: {self.lock_path}")
                time.sleep(LOCK_WAIT)

    def __exit__(self, exc_type, exc_val, exc_tb):
        try:
            os.rmdir(self.lock_path)
        except FileNotFoundError:
            pass


class ModbusTCP:
    """Modbus TCP istemcisi"""
    def __init__(self, ip, port, unit_id):
        self.ip = ip
        self.port = port
        self.unit_id = unit_id
        self.sock = None

    def connect(self):
        self.sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.sock.settimeout(CONNECT_TIMEOUT)
        self.sock.connect((self.ip, self.port))
        self.sock.settimeout(READ_TIMEOUT)

    def close(self):
        if self.sock:
            try:
                self.sock.close()
            except Exception:
                pass
            self.sock = None

    def _build_frame(self, pdu):
        txid = int(time.time() * 1000) & 0xFFFF
        length = len(pdu) + 1
        return struct.pack(">HHHB", txid, 0, length, self.unit_id) + pdu

    def _recv_response(self):
        header = b""
        while len(header) < 7:
            chunk = self.sock.recv(7 - len(header))
            if not chunk:
                raise ConnectionError("Connection closed")
            header += chunk

        _, _, length, _ = struct.unpack(">HHHB", header)
        remaining = length - 1

        pdu = b""
        while len(pdu) < remaining:
            chunk = self.sock.recv(remaining - len(pdu))
            if not chunk:
                raise ConnectionError("Connection closed")
            pdu += chunk

        fc = pdu[0]
        if fc >= 0x80:
            exc = pdu[1] if len(pdu) > 1 else 0
            raise RuntimeError(f"Modbus exception FC={fc} code={exc}")

        return pdu

    def read_regs(self, address, count):
        """FC03 - Holding register okuma"""
        pdu = struct.pack(">BHH", 0x03, address, count)
        self.sock.sendall(self._build_frame(pdu))
        resp = self._recv_response()
        byte_count = resp[1]
        data = resp[2 : 2 + byte_count]
        regs = []
        for i in range(0, len(data), 2):
            regs.append(struct.unpack(">H", data[i : i + 2])[0])
        return regs

    def write_single_reg(self, address, value):
        """FC06 - Tek register yazma"""
        pdu = struct.pack(">BHH", 0x06, address, value & 0xFFFF)
        self.sock.sendall(self._build_frame(pdu))
        _ = self._recv_response()
        return True


# ─── Veri Dönüşüm Fonksiyonları ──────────────────────────────────────────────
def to_u32(regs):
    """İki register'ı unsigned 32-bit'e çevir"""
    return (regs[0] << 16) | regs[1]


def to_s32(regs):
    """İki register'ı signed 32-bit'e çevir"""
    v = to_u32(regs)
    if v >= 0x80000000:
        v -= 0x100000000
    return v


def to_s16(v):
    """Unsigned 16-bit'i signed'a çevir"""
    return v - 0x10000 if v >= 0x8000 else v


def read_one_point(client, addr, dtype, scale=1.0):
    """Tek bir veri noktası oku"""
    if dtype == "u16":
        regs = client.read_regs(addr, 1)
        return regs[0] / scale
    if dtype == "s16":
        regs = client.read_regs(addr, 1)
        return to_s16(regs[0]) / scale
    if dtype == "u32":
        regs = client.read_regs(addr, 2)
        return to_u32(regs) / scale
    if dtype == "s32":
        regs = client.read_regs(addr, 2)
        return to_s32(regs) / scale
    raise ValueError(f"Unknown dtype: {dtype}")


def read_one_point_with_raw(client, addr, dtype, scale=1.0, register_count=None):
    """Tek bir veri noktası oku ve ham register verisini birlikte döndür."""
    if dtype == "u16":
        regs = client.read_regs(addr, 1)
        return regs[0] / scale, regs
    if dtype == "s16":
        regs = client.read_regs(addr, 1)
        return to_s16(regs[0]) / scale, regs
    if dtype == "u32":
        regs = client.read_regs(addr, 2)
        return to_u32(regs) / scale, regs
    if dtype == "s32":
        regs = client.read_regs(addr, 2)
        return to_s32(regs) / scale, regs
    if dtype == "byte8":
        regs = client.read_regs(addr, 1)
        return (regs[0] & 0xFF) / scale, regs
    if dtype == "char48":
        count = register_count or 24
        regs = client.read_regs(addr, count)
        chars = []
        for reg in regs:
            chars.append(chr((reg >> 8) & 0xFF))
            chars.append(chr(reg & 0xFF))
        text = "".join(chars).replace("\x00", "").strip()
        return text, regs
    raise ValueError(f"Unknown dtype: {dtype}")


# ─── SOG5 Register Haritası ──────────────────────────────────────────────────
# Trafo Çarpanları
# Röle tüm değerleri sekonder taraftan raporlar; birincil (gerçek) değere
# dönüştürmek için aşağıdaki çarpanlar uygulanır.
CT_RATIO = 1.0     # SOG5 birincil taraf değerlerini doğrudan raporlar
VT_RATIO = 1.0     # SOG5 birincil taraf değerlerini doğrudan raporlar
PT_RATIO = 1.0     # Trafo çarpanı yok — cihaz primer değerleri veriyor

# Klavuzdan alınan register tanımları
REGISTER_MAP = {
    # Aktif Enerji - Tüketim (kWh)
    "e_l1_import_kwh": {"addr": 0, "dtype": "u32", "scale": 1000.0, "unit": "kWh", "group": "energy"},
    "e_l2_import_kwh": {"addr": 2, "dtype": "u32", "scale": 1000.0, "unit": "kWh", "group": "energy"},
    "e_l3_import_kwh": {"addr": 4, "dtype": "u32", "scale": 1000.0, "unit": "kWh", "group": "energy"},
    
    # Reaktif Enerji - Endüktif (kVARh)
    "e_l1_reactive_ind_kvarh": {"addr": 18, "dtype": "u32", "scale": 1000.0, "unit": "kVARh", "group": "energy"},
    "e_l2_reactive_ind_kvarh": {"addr": 20, "dtype": "u32", "scale": 1000.0, "unit": "kVARh", "group": "energy"},
    "e_l3_reactive_ind_kvarh": {"addr": 22, "dtype": "u32", "scale": 1000.0, "unit": "kVARh", "group": "energy"},
    
    # Reaktif Enerji - Kapasitif (kVARh)
    "e_l1_reactive_cap_kvarh": {"addr": 12, "dtype": "u32", "scale": 1000.0, "unit": "kVARh", "group": "energy"},
    "e_l2_reactive_cap_kvarh": {"addr": 14, "dtype": "u32", "scale": 1000.0, "unit": "kVARh", "group": "energy"},
    "e_l3_reactive_cap_kvarh": {"addr": 16, "dtype": "u32", "scale": 1000.0, "unit": "kVARh", "group": "energy"},
    
    # Aktif Güç (kW)
    "p_l1_kw": {"addr": 24, "dtype": "s32", "scale": 1000.0, "unit": "kW", "group": "power"},
    "p_l2_kw": {"addr": 26, "dtype": "s32", "scale": 1000.0, "unit": "kW", "group": "power"},
    "p_l3_kw": {"addr": 28, "dtype": "s32", "scale": 1000.0, "unit": "kW", "group": "power"},
    
    # Reaktif Güç (kVAR)
    "q_ind_l1_kvar": {"addr": 30, "dtype": "s32", "scale": 1000.0, "unit": "kVAR", "group": "power"},
    "q_ind_l2_kvar": {"addr": 32, "dtype": "s32", "scale": 1000.0, "unit": "kVAR", "group": "power"},
    "q_ind_l3_kvar": {"addr": 34, "dtype": "s32", "scale": 1000.0, "unit": "kVAR", "group": "power"},
    
    # Güç Faktörü
    "pf_l1": {"addr": 42, "dtype": "s16", "scale": 100.0, "unit": "", "group": "pf"},
    "pf_l2": {"addr": 43, "dtype": "s16", "scale": 100.0, "unit": "", "group": "pf"},
    "pf_l3": {"addr": 44, "dtype": "s16", "scale": 100.0, "unit": "", "group": "pf"},
    
    # Frekans (Hz)
    "f_l1_hz": {"addr": 47, "dtype": "u16", "scale": 10.0, "unit": "Hz", "group": "freq"},
    "f_l2_hz": {"addr": 48, "dtype": "u16", "scale": 10.0, "unit": "Hz", "group": "freq"},
    "f_l3_hz": {"addr": 49, "dtype": "u16", "scale": 10.0, "unit": "Hz", "group": "freq"},
    
    # THD Akım (%)
    "thdi_l1_pct": {"addr": 50, "dtype": "u16", "scale": 1.0, "unit": "%", "group": "thd"},
    "thdi_l2_pct": {"addr": 51, "dtype": "u16", "scale": 1.0, "unit": "%", "group": "thd"},
    "thdi_l3_pct": {"addr": 52, "dtype": "u16", "scale": 1.0, "unit": "%", "group": "thd"},

    # SVC Açma Yüzdesi (%)
    "svc_open_l1_pct": {"addr": 53, "dtype": "u16", "scale": 10.0, "unit": "%", "group": "svc"},
    "svc_open_l2_pct": {"addr": 54, "dtype": "u16", "scale": 10.0, "unit": "%", "group": "svc"},
    "svc_open_l3_pct": {"addr": 55, "dtype": "u16", "scale": 10.0, "unit": "%", "group": "svc"},
    
    # Gerilim - Faz-Nötr (V)
    "v_l1_v": {"addr": 56, "dtype": "u16", "scale": 1.0, "unit": "V", "group": "voltage"},
    "v_l2_v": {"addr": 57, "dtype": "u16", "scale": 1.0, "unit": "V", "group": "voltage"},
    "v_l3_v": {"addr": 58, "dtype": "u16", "scale": 1.0, "unit": "V", "group": "voltage"},
    
    # Gerilim - Faz-Faz: addr 53-55 anlamli deger donmuyor, L-N'den hesaplanacak

    # Akım (A)
    "i_l1_a": {"addr": 59, "dtype": "u32", "scale": 100.0, "unit": "A", "group": "current"},
    "i_l2_a": {"addr": 61, "dtype": "u32", "scale": 100.0, "unit": "A", "group": "current"},
    "i_l3_a": {"addr": 63, "dtype": "u32", "scale": 100.0, "unit": "A", "group": "current"},
    
    # Kademe Durumu
    "step_status_bits": {"addr": 73, "dtype": "u32", "scale": 1.0, "unit": "", "group": "steps"},
}


MANUAL_GUIDE_REGISTERS = [
    {"key": "q_cap_l1_var", "label": "1. Faz Kapasitif Guc", "addr": 36, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide"},
    {"key": "q_cap_l2_var", "label": "2. Faz Kapasitif Guc", "addr": 38, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide"},
    {"key": "q_cap_l3_var", "label": "3. Faz Kapasitif Guc", "addr": 40, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide"},
    {"key": "cos_phi_l1_pct", "label": "1. Faz Cos Phi", "addr": 42, "dtype": "s16", "scale": 100.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "cos_phi_l2_pct", "label": "2. Faz Cos Phi", "addr": 43, "dtype": "s16", "scale": 100.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "cos_phi_l3_pct", "label": "3. Faz Cos Phi", "addr": 44, "dtype": "s16", "scale": 100.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "reached_inductive_pct", "label": "Ulasilan Enduktif Yuzde", "addr": 45, "dtype": "u16", "scale": 10.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "reached_capacitive_pct", "label": "Ulasilan Kapasitif Yuzde", "addr": 46, "dtype": "u16", "scale": 10.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "f_l1_hz", "label": "1. Faz Frekansi", "addr": 47, "dtype": "u16", "scale": 1.0, "unit": "Hz", "access": "R", "group": "guide"},
    {"key": "f_l2_hz", "label": "2. Faz Frekansi", "addr": 48, "dtype": "u16", "scale": 1.0, "unit": "Hz", "access": "R", "group": "guide"},
    {"key": "f_l3_hz", "label": "3. Faz Frekansi", "addr": 49, "dtype": "u16", "scale": 1.0, "unit": "Hz", "access": "R", "group": "guide"},
    {"key": "thdi_l1_pct", "label": "1. Faz THDI", "addr": 50, "dtype": "u16", "scale": 1.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "thdi_l2_pct", "label": "2. Faz THDI", "addr": 51, "dtype": "u16", "scale": 1.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "thdi_l3_pct", "label": "3. Faz THDI", "addr": 52, "dtype": "u16", "scale": 1.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "svc_open_l1_pct", "label": "1. Faz SVC Acma Yuzdesi", "addr": 53, "dtype": "u16", "scale": 10.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "svc_open_l2_pct", "label": "2. Faz SVC Acma Yuzdesi", "addr": 54, "dtype": "u16", "scale": 10.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "svc_open_l3_pct", "label": "3. Faz SVC Acma Yuzdesi", "addr": 55, "dtype": "u16", "scale": 10.0, "unit": "%", "access": "R", "group": "guide"},
    {"key": "v_l1_v", "label": "1. Faz Voltaji", "addr": 56, "dtype": "u16", "scale": 1.0, "unit": "V", "access": "R", "group": "guide"},
    {"key": "v_l2_v", "label": "2. Faz Voltaji", "addr": 57, "dtype": "u16", "scale": 1.0, "unit": "V", "access": "R", "group": "guide"},
    {"key": "v_l3_v", "label": "3. Faz Voltaji", "addr": 58, "dtype": "u16", "scale": 1.0, "unit": "V", "access": "R", "group": "guide"},
    {"key": "i_l1_a", "label": "1. Faz Akim", "addr": 59, "dtype": "u32", "scale": 100.0, "unit": "A", "access": "R", "group": "guide"},
    {"key": "i_l2_a", "label": "2. Faz Akim", "addr": 61, "dtype": "u32", "scale": 100.0, "unit": "A", "access": "R", "group": "guide"},
    {"key": "i_l3_a", "label": "3. Faz Akim", "addr": 63, "dtype": "u32", "scale": 100.0, "unit": "A", "access": "R", "group": "guide"},
    {"key": "serial_no", "label": "Seri No", "addr": 70, "dtype": "char48", "scale": 1.0, "unit": "", "access": "R", "register_count": 24, "group": "guide"},
    {"key": "device_status", "label": "Cihaz Durumu", "addr": 72, "dtype": "byte8", "scale": 1.0, "unit": "", "access": "R", "group": "guide"},
    {"key": "step_status_bits", "label": "Kademe Durumlari", "addr": 73, "dtype": "u32", "scale": 1.0, "unit": "", "access": "R", "group": "guide"},
    {"key": "step_test_cancel", "label": "Kademe Test Iptali", "addr": 100, "dtype": "u16", "scale": 1.0, "unit": "", "access": "W", "group": "guide"},
    {"key": "trafo_test_cancel", "label": "Trafo Test Iptali", "addr": 101, "dtype": "u16", "scale": 1.0, "unit": "", "access": "W", "group": "guide"},
    {"key": "reactive_response_s", "label": "Reaktifte Cevap Suresi", "addr": 150, "dtype": "u16", "scale": 100.0, "unit": "Sn", "access": "R/W", "group": "guide"},
    {"key": "normal_response_s", "label": "Normalde Cevap Suresi", "addr": 151, "dtype": "u16", "scale": 100.0, "unit": "Sn", "access": "R/W", "group": "guide"},
    {"key": "svc_response_s", "label": "SVC Cevap Suresi", "addr": 153, "dtype": "u16", "scale": 100.0, "unit": "Sn", "access": "R/W", "group": "guide"},
    {"key": "cond_discharge_s", "label": "Kond. Bosalma Suresi", "addr": 154, "dtype": "u16", "scale": 100.0, "unit": "Sn", "access": "R/W", "group": "guide"},
    {"key": "energy_integral_s", "label": "Enerji Integral Suresi", "addr": 158, "dtype": "u16", "scale": 100.0, "unit": "Sn", "access": "R/W", "group": "guide"},
    {"key": "ade_opamp_gain", "label": "ADE Opamp Carpani", "addr": 159, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "ade_hw_opamp_gain", "label": "ADE Hw Opamp Carpani", "addr": 161, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "inductive_hysteresis", "label": "Enduktif Histeresis", "addr": 166, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "capacitive_hysteresis", "label": "Kapasitif Histeresis", "addr": 167, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "response_resolution", "label": "Cevap Cozunurlugu", "addr": 168, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "inductive_limit", "label": "Enduktif Limit", "addr": 169, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "capacitive_limit", "label": "Kapasitif Limit", "addr": 170, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "lc_offset_l1", "label": "LC Offset L1", "addr": 171, "dtype": "s16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "lc_offset_l2", "label": "LC Offset L2", "addr": 172, "dtype": "s16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "lc_offset_l3", "label": "LC Offset L3", "addr": 173, "dtype": "s16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "svc_max_open_l1", "label": "1. SVC Max Acma Yuzdesi", "addr": 177, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "svc_max_open_l2", "label": "2. SVC Max Acma Yuzdesi", "addr": 178, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "svc_max_open_l3", "label": "3. SVC Max Acma Yuzdesi", "addr": 179, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "ct_ratio", "label": "Akim Trafo Orani", "addr": 180, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "vt_ratio", "label": "Gerilim Trafo Orani", "addr": 181, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R/W", "group": "guide"},
    {"key": "step_test", "label": "Kademe Testi", "addr": 185, "dtype": "u16", "scale": 1.0, "unit": "", "access": "W", "group": "guide"},
    {"key": "trafo_test", "label": "Trafo Testi", "addr": 186, "dtype": "u16", "scale": 1.0, "unit": "", "access": "W", "group": "guide"},
    {"key": "step1_q1", "label": "1. Kademe Q1", "addr": 256, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step1_q2", "label": "1. Kademe Q2", "addr": 258, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step1_q3", "label": "1. Kademe Q3", "addr": 260, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step2_q1", "label": "2. Kademe Q1", "addr": 262, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step2_q2", "label": "2. Kademe Q2", "addr": 264, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step2_q3", "label": "2. Kademe Q3", "addr": 266, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step3_q1", "label": "3. Kademe Q1", "addr": 268, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step3_q2", "label": "3. Kademe Q2", "addr": 270, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step3_q3", "label": "3. Kademe Q3", "addr": 272, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step4_q1", "label": "4. Kademe Q1", "addr": 274, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step4_q2", "label": "4. Kademe Q2", "addr": 276, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step4_q3", "label": "4. Kademe Q3", "addr": 278, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step5_q1", "label": "5. Kademe Q1", "addr": 280, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step5_q2", "label": "5. Kademe Q2", "addr": 282, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step5_q3", "label": "5. Kademe Q3", "addr": 284, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step6_q1", "label": "6. Kademe Q1", "addr": 286, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step6_q2", "label": "6. Kademe Q2", "addr": 288, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step6_q3", "label": "6. Kademe Q3", "addr": 290, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step7_q1", "label": "7. Kademe Q1", "addr": 292, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step7_q2", "label": "7. Kademe Q2", "addr": 294, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step7_q3", "label": "7. Kademe Q3", "addr": 296, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step8_q1",  "label": "8. Kademe Q1",  "addr": 298, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step8_q2",  "label": "8. Kademe Q2",  "addr": 300, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step8_q3",  "label": "8. Kademe Q3",  "addr": 302, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step9_q1",  "label": "9. Kademe Q1",  "addr": 304, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step9_q2",  "label": "9. Kademe Q2",  "addr": 306, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step9_q3",  "label": "9. Kademe Q3",  "addr": 308, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step10_q1", "label": "10. Kademe Q1", "addr": 310, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step10_q2", "label": "10. Kademe Q2", "addr": 312, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step10_q3", "label": "10. Kademe Q3", "addr": 314, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step11_q1", "label": "11. Kademe Q1", "addr": 316, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step11_q2", "label": "11. Kademe Q2", "addr": 318, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step11_q3", "label": "11. Kademe Q3", "addr": 320, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step12_q1", "label": "12. Kademe Q1", "addr": 322, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step12_q2", "label": "12. Kademe Q2", "addr": 324, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step12_q3", "label": "12. Kademe Q3", "addr": 326, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step13_q1", "label": "13. Kademe Q1", "addr": 328, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step13_q2", "label": "13. Kademe Q2", "addr": 330, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step13_q3", "label": "13. Kademe Q3", "addr": 332, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step14_q1", "label": "14. Kademe Q1", "addr": 334, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step14_q2", "label": "14. Kademe Q2", "addr": 336, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step14_q3", "label": "14. Kademe Q3", "addr": 338, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step15_q1", "label": "15. Kademe Q1", "addr": 340, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step15_q2", "label": "15. Kademe Q2", "addr": 342, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step15_q3", "label": "15. Kademe Q3", "addr": 344, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step16_q1", "label": "16. Kademe Q1", "addr": 346, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step16_q2", "label": "16. Kademe Q2", "addr": 348, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step16_q3", "label": "16. Kademe Q3", "addr": 350, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step17_q1", "label": "17. Kademe Q1", "addr": 352, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step17_q2", "label": "17. Kademe Q2", "addr": 354, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step17_q3", "label": "17. Kademe Q3", "addr": 356, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step18_q1", "label": "18. Kademe Q1", "addr": 358, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step18_q2", "label": "18. Kademe Q2", "addr": 360, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    {"key": "step18_q3", "label": "18. Kademe Q3", "addr": 362, "dtype": "s32", "scale": 1.0, "unit": "", "access": "R", "group": "guide_step"},
    # SVC Reaktif Güç
    {"key": "svc1_q1",   "label": "1. SVC Q1",     "addr": 364, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc1_q2",   "label": "1. SVC Q2",     "addr": 366, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc1_q3",   "label": "1. SVC Q3",     "addr": 368, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc2_q1",   "label": "2. SVC Q1",     "addr": 370, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc2_q2",   "label": "2. SVC Q2",     "addr": 372, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc2_q3",   "label": "2. SVC Q3",     "addr": 374, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc3_q1",   "label": "3. SVC Q1",     "addr": 376, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc3_q2",   "label": "3. SVC Q2",     "addr": 378, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    {"key": "svc3_q3",   "label": "3. SVC Q3",     "addr": 380, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_svc"},
    # Güç Akış Grafiği Örnekleri (18 örnek x 5 kayıt)
    {"key": "sample1_q1",  "label": "1. Örnek Q1",    "addr": 512, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample1_q2",  "label": "1. Örnek Q2",    "addr": 514, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample1_q3",  "label": "1. Örnek Q3",    "addr": 516, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample1_pct", "label": "1. Örnek Yüzde", "addr": 518, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample1_tm",  "label": "1. Örnek Zaman", "addr": 519, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample2_q1",  "label": "2. Örnek Q1",    "addr": 520, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample2_q2",  "label": "2. Örnek Q2",    "addr": 522, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample2_q3",  "label": "2. Örnek Q3",    "addr": 524, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample2_pct", "label": "2. Örnek Yüzde", "addr": 526, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample2_tm",  "label": "2. Örnek Zaman", "addr": 527, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample3_q1",  "label": "3. Örnek Q1",    "addr": 528, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample3_q2",  "label": "3. Örnek Q2",    "addr": 530, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample3_q3",  "label": "3. Örnek Q3",    "addr": 532, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample3_pct", "label": "3. Örnek Yüzde", "addr": 534, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample3_tm",  "label": "3. Örnek Zaman", "addr": 535, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample4_q1",  "label": "4. Örnek Q1",    "addr": 536, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample4_q2",  "label": "4. Örnek Q2",    "addr": 538, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample4_q3",  "label": "4. Örnek Q3",    "addr": 540, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample4_pct", "label": "4. Örnek Yüzde", "addr": 542, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample4_tm",  "label": "4. Örnek Zaman", "addr": 543, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample5_q1",  "label": "5. Örnek Q1",    "addr": 544, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample5_q2",  "label": "5. Örnek Q2",    "addr": 546, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample5_q3",  "label": "5. Örnek Q3",    "addr": 548, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample5_pct", "label": "5. Örnek Yüzde", "addr": 550, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample5_tm",  "label": "5. Örnek Zaman", "addr": 551, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample6_q1",  "label": "6. Örnek Q1",    "addr": 552, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample6_q2",  "label": "6. Örnek Q2",    "addr": 554, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample6_q3",  "label": "6. Örnek Q3",    "addr": 556, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample6_pct", "label": "6. Örnek Yüzde", "addr": 558, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample6_tm",  "label": "6. Örnek Zaman", "addr": 559, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample7_q1",  "label": "7. Örnek Q1",    "addr": 560, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample7_q2",  "label": "7. Örnek Q2",    "addr": 562, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample7_q3",  "label": "7. Örnek Q3",    "addr": 564, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample7_pct", "label": "7. Örnek Yüzde", "addr": 566, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample7_tm",  "label": "7. Örnek Zaman", "addr": 567, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample8_q1",  "label": "8. Örnek Q1",    "addr": 568, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample8_q2",  "label": "8. Örnek Q2",    "addr": 570, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample8_q3",  "label": "8. Örnek Q3",    "addr": 572, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample8_pct", "label": "8. Örnek Yüzde", "addr": 574, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample8_tm",  "label": "8. Örnek Zaman", "addr": 575, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample9_q1",  "label": "9. Örnek Q1",    "addr": 576, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample9_q2",  "label": "9. Örnek Q2",    "addr": 578, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample9_q3",  "label": "9. Örnek Q3",    "addr": 580, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample9_pct", "label": "9. Örnek Yüzde", "addr": 582, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample9_tm",  "label": "9. Örnek Zaman", "addr": 583, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample10_q1",  "label": "10. Örnek Q1",    "addr": 584, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample10_q2",  "label": "10. Örnek Q2",    "addr": 586, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample10_q3",  "label": "10. Örnek Q3",    "addr": 588, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample10_pct", "label": "10. Örnek Yüzde", "addr": 590, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample10_tm",  "label": "10. Örnek Zaman", "addr": 591, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample11_q1",  "label": "11. Örnek Q1",    "addr": 592, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample11_q2",  "label": "11. Örnek Q2",    "addr": 594, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample11_q3",  "label": "11. Örnek Q3",    "addr": 596, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample11_pct", "label": "11. Örnek Yüzde", "addr": 598, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample11_tm",  "label": "11. Örnek Zaman", "addr": 599, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample12_q1",  "label": "12. Örnek Q1",    "addr": 600, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample12_q2",  "label": "12. Örnek Q2",    "addr": 602, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample12_q3",  "label": "12. Örnek Q3",    "addr": 604, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample12_pct", "label": "12. Örnek Yüzde", "addr": 606, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample12_tm",  "label": "12. Örnek Zaman", "addr": 607, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample13_q1",  "label": "13. Örnek Q1",    "addr": 608, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample13_q2",  "label": "13. Örnek Q2",    "addr": 610, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample13_q3",  "label": "13. Örnek Q3",    "addr": 612, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample13_pct", "label": "13. Örnek Yüzde", "addr": 614, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample13_tm",  "label": "13. Örnek Zaman", "addr": 615, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample14_q1",  "label": "14. Örnek Q1",    "addr": 616, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample14_q2",  "label": "14. Örnek Q2",    "addr": 618, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample14_q3",  "label": "14. Örnek Q3",    "addr": 620, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample14_pct", "label": "14. Örnek Yüzde", "addr": 622, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample14_tm",  "label": "14. Örnek Zaman", "addr": 623, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample15_q1",  "label": "15. Örnek Q1",    "addr": 624, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample15_q2",  "label": "15. Örnek Q2",    "addr": 626, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample15_q3",  "label": "15. Örnek Q3",    "addr": 628, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample15_pct", "label": "15. Örnek Yüzde", "addr": 630, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample15_tm",  "label": "15. Örnek Zaman", "addr": 631, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample16_q1",  "label": "16. Örnek Q1",    "addr": 632, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample16_q2",  "label": "16. Örnek Q2",    "addr": 634, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample16_q3",  "label": "16. Örnek Q3",    "addr": 636, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample16_pct", "label": "16. Örnek Yüzde", "addr": 638, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample16_tm",  "label": "16. Örnek Zaman", "addr": 639, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample17_q1",  "label": "17. Örnek Q1",    "addr": 640, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample17_q2",  "label": "17. Örnek Q2",    "addr": 642, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample17_q3",  "label": "17. Örnek Q3",    "addr": 644, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample17_pct", "label": "17. Örnek Yüzde", "addr": 646, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample17_tm",  "label": "17. Örnek Zaman", "addr": 647, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    {"key": "sample18_q1",  "label": "18. Örnek Q1",    "addr": 648, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample18_q2",  "label": "18. Örnek Q2",    "addr": 650, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample18_q3",  "label": "18. Örnek Q3",    "addr": 652, "dtype": "s32", "scale": 1.0, "unit": "VAr", "access": "R", "group": "guide_sample"},
    {"key": "sample18_pct", "label": "18. Örnek Yüzde", "addr": 654, "dtype": "u16", "scale": 1.0, "unit": "%",   "access": "R", "group": "guide_sample"},
    {"key": "sample18_tm",  "label": "18. Örnek Zaman", "addr": 655, "dtype": "u16", "scale": 1.0, "unit": "",    "access": "R", "group": "guide_sample"},
    # Kademe Kullanımları (768-785)
    {"key": "stepuse1",  "label": "1. Kademe Kullanımı",  "addr": 768, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse2",  "label": "2. Kademe Kullanımı",  "addr": 769, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse3",  "label": "3. Kademe Kullanımı",  "addr": 770, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse4",  "label": "4. Kademe Kullanımı",  "addr": 771, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse5",  "label": "5. Kademe Kullanımı",  "addr": 772, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse6",  "label": "6. Kademe Kullanımı",  "addr": 773, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse7",  "label": "7. Kademe Kullanımı",  "addr": 774, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse8",  "label": "8. Kademe Kullanımı",  "addr": 775, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse9",  "label": "9. Kademe Kullanımı",  "addr": 776, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse10", "label": "10. Kademe Kullanımı", "addr": 777, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse11", "label": "11. Kademe Kullanımı", "addr": 778, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse12", "label": "12. Kademe Kullanımı", "addr": 779, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse13", "label": "13. Kademe Kullanımı", "addr": 780, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse14", "label": "14. Kademe Kullanımı", "addr": 781, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse15", "label": "15. Kademe Kullanımı", "addr": 782, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse16", "label": "16. Kademe Kullanımı", "addr": 783, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse17", "label": "17. Kademe Kullanımı", "addr": 784, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
    {"key": "stepuse18", "label": "18. Kademe Kullanımı", "addr": 785, "dtype": "u16", "scale": 1.0, "unit": "", "access": "R", "group": "guide_stepuse"},
]


def build_guide_register_map():
    """Kullanici tarafindan paylasilan kilavuz satirlarini register listesine cevir."""
    rows = [dict(item) for item in MANUAL_GUIDE_REGISTERS]
    rows.sort(key=lambda x: (x["addr"], x["label"]))
    return rows


GUIDE_REGISTER_MAP = build_guide_register_map()


def collect_guide_register_snapshot(ip, port, unit_id, lock_timeout=LOCK_TIMEOUT):
    """Kılavuz register listesini tekil sorgularla oku.

    Cihaz kısıtı nedeniyle geniş blok (çoklu adres) okuma yapılmaz.
    Her item kendi adresinden, gerekli register adedi kadar okunur.
    """
    out = []

    with GatewayLock(ip, port, timeout=lock_timeout):
        for item in GUIDE_REGISTER_MAP:
            row = dict(item)
            row["ok"] = False
            row["error"] = None
            row["raw"] = None
            row["value"] = None

            try:
                client = ModbusTCP(ip, port, unit_id)
                client.connect()
                try:
                    value, regs = read_one_point_with_raw(
                        client,
                        item["addr"],
                        item["dtype"],
                        item["scale"],
                        item.get("register_count"),
                    )
                finally:
                    client.close()

                row["ok"] = True
                row["value"] = round(value, 3) if isinstance(value, float) else value
                row["raw"] = regs
            except Exception as e:
                row["error"] = str(e)

            out.append(row)
            time.sleep(REQUEST_DELAY)

    return out


# ─── Veritabanı ──────────────────────────────────────────────────────────────
class Database:
    """SQLite veritabanı yöneticisi"""
    
    def __init__(self, db_path):
        self.db_path = db_path
        self.init_db()
    
    def _connect(self):
        """SQLite bağlantısı aç: WAL mode + 30s timeout (eşzamanlı erişim için)."""
        conn = sqlite3.connect(self.db_path, timeout=30)
        conn.execute("PRAGMA journal_mode=WAL")
        conn.execute("PRAGMA synchronous=NORMAL")
        return conn

    def init_db(self):
        """Veritabanı tablolarını oluştur"""
        conn = self._connect()
        c = conn.cursor()
        
        # Ana veri tablosu
        c.execute('''
            CREATE TABLE IF NOT EXISTS measurements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                unit_id INTEGER,
                v_l1 REAL, v_l2 REAL, v_l3 REAL,
                i_l1 REAL, i_l2 REAL, i_l3 REAL,
                p_l1 REAL, p_l2 REAL, p_l3 REAL,
                q_l1 REAL, q_l2 REAL, q_l3 REAL,
                pf_l1 REAL, pf_l2 REAL, pf_l3 REAL,
                f_hz REAL,
                e_l1 REAL, e_l2 REAL, e_l3 REAL,
                step_status INTEGER,
                p_total REAL,
                e_total REAL,
                e_reactive_ind_total REAL,
                e_reactive_cap_total REAL
            )
        ''')

        self._ensure_column(c, 'measurements', 'e_reactive_ind_total', 'REAL')
        self._ensure_column(c, 'measurements', 'e_reactive_cap_total', 'REAL')
        
        # İndeks oluştur
        c.execute('CREATE INDEX IF NOT EXISTS idx_timestamp ON measurements(timestamp)')
        
        # Günlük snapshot tablosu
        c.execute('''
            CREATE TABLE IF NOT EXISTS daily_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_date DATE UNIQUE NOT NULL,
                snapshot_time TIME DEFAULT '08:00:00',
                unit_id INTEGER,
                e_l1_kwh REAL,
                e_l2_kwh REAL,
                e_l3_kwh REAL,
                e_total_kwh REAL,
                e_l1_reactive_ind_kvarh REAL,
                e_l2_reactive_ind_kvarh REAL,
                e_l3_reactive_ind_kvarh REAL,
                e_total_reactive_ind_kvarh REAL,
                e_l1_reactive_cap_kvarh REAL,
                e_l2_reactive_cap_kvarh REAL,
                e_l3_reactive_cap_kvarh REAL,
                e_total_reactive_cap_kvarh REAL,
                daily_consumption_kwh REAL,
                daily_reactive_ind_kvarh REAL,
                daily_reactive_cap_kvarh REAL,
                reactive_ind_ratio_pct REAL,
                reactive_cap_ratio_pct REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        
        c.execute('CREATE INDEX IF NOT EXISTS idx_snapshot_date ON daily_snapshots(snapshot_date)')

        c.execute('''
            CREATE TABLE IF NOT EXISTS hourly_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_date DATE NOT NULL,
                hour_slot TEXT NOT NULL,
                active_kwh REAL,
                reactive_ind_kvarh REAL,
                reactive_cap_kvarh REAL,
                source TEXT DEFAULT 'mock',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(snapshot_date, hour_slot)
            )
        ''')
        c.execute('CREATE INDEX IF NOT EXISTS idx_hourly_snapshot_date ON hourly_snapshots(snapshot_date, hour_slot)')

        self._seed_demo_hourly_data(c)
        self._rebuild_daily_deltas(c)
        
        conn.commit()
        conn.close()

    def _ensure_column(self, cursor, table_name, column_name, column_def):
        """Tabloda eksik kolon varsa geriye uyumlu olarak ekle."""
        cursor.execute(f"PRAGMA table_info({table_name})")
        existing = {row[1] for row in cursor.fetchall()}
        if column_name not in existing:
            cursor.execute(f"ALTER TABLE {table_name} ADD COLUMN {column_name} {column_def}")

    def _distribute_total(self, total_value, weights):
        """Günlük toplamı saatlik profile dağıt."""
        if total_value is None:
            return [None] * len(weights)
        norm = sum(weights) or 1.0
        values = [round(total_value * (weight / norm), 2) for weight in weights]
        diff = round(total_value - sum(values), 2)
        values[-1] = round(values[-1] + diff, 2)
        return values

    def _seed_demo_hourly_data(self, cursor):
        """Excel örneğine göre test amaçlı saatlik dağılım verisi ekle."""
        cursor.execute(
            'SELECT COUNT(1) FROM hourly_snapshots WHERE snapshot_date = ?',
            (DEMO_HOURLY_DATE,)
        )
        existing = cursor.fetchone()[0]
        if existing:
            return

        hour_weights = [
            0.020, 0.018, 0.017, 0.017, 0.018, 0.022,
            0.030, 0.045, 0.060, 0.070, 0.075, 0.080,
            0.082, 0.080, 0.075, 0.070, 0.060, 0.055,
            0.048, 0.040, 0.032, 0.026, 0.022, 0.018,
        ]
        active_values = self._distribute_total(8549.10, hour_weights)
        ind_values = self._distribute_total(165.60, hour_weights)
        cap_values = self._distribute_total(89.70, hour_weights)

        for hour, (active, ind, cap) in enumerate(zip(active_values, ind_values, cap_values)):
            cursor.execute(
                '''
                INSERT OR IGNORE INTO hourly_snapshots (
                    snapshot_date, hour_slot, active_kwh,
                    reactive_ind_kvarh, reactive_cap_kvarh, source
                ) VALUES (?, ?, ?, ?, ?, ?)
                ''',
                (
                    DEMO_HOURLY_DATE,
                    f"{hour:02d}:00",
                    active,
                    ind,
                    cap,
                    'excel-demo',
                )
            )

    def _rebuild_daily_deltas(self, cursor):
        """Mevcut günlük snapshot farklarını gün aralığına göre normalize et."""
        cursor.execute('''
            SELECT snapshot_date, e_total_kwh, e_total_reactive_ind_kvarh, e_total_reactive_cap_kvarh
            FROM daily_snapshots
            ORDER BY snapshot_date ASC
        ''')
        rows = []
        for row in cursor.fetchall():
            rows.append({
                'snapshot_date': row[0],
                'e_total_kwh': row[1],
                'e_total_reactive_ind_kvarh': row[2],
                'e_total_reactive_cap_kvarh': row[3],
            })
        if len(rows) < 2:
            return

        prev = rows[0]
        for cur in rows[1:]:
            try:
                prev_dt = datetime.strptime(prev['snapshot_date'], '%Y-%m-%d').date()
                cur_dt = datetime.strptime(cur['snapshot_date'], '%Y-%m-%d').date()
                gap_days = max((cur_dt - prev_dt).days, 1)
            except ValueError:
                gap_days = 1

            daily_active = None
            daily_ind = None
            daily_cap = None
            ratio_ind = None
            ratio_cap = None

            if cur.get('e_total_kwh') is not None and prev.get('e_total_kwh') is not None:
                daily_active = round((cur['e_total_kwh'] - prev['e_total_kwh']) / gap_days, 2)
            if cur.get('e_total_reactive_ind_kvarh') is not None and prev.get('e_total_reactive_ind_kvarh') is not None:
                daily_ind = round((cur['e_total_reactive_ind_kvarh'] - prev['e_total_reactive_ind_kvarh']) / gap_days, 2)
            if cur.get('e_total_reactive_cap_kvarh') is not None and prev.get('e_total_reactive_cap_kvarh') is not None:
                daily_cap = round((cur['e_total_reactive_cap_kvarh'] - prev['e_total_reactive_cap_kvarh']) / gap_days, 2)

            if daily_active is not None and daily_active > 0:
                if daily_ind is not None:
                    ratio_ind = round((daily_ind / daily_active) * 100, 2)
                if daily_cap is not None:
                    ratio_cap = round((daily_cap / daily_active) * 100, 2)

            cursor.execute('''
                UPDATE daily_snapshots
                SET daily_consumption_kwh = ?,
                    daily_reactive_ind_kvarh = ?,
                    daily_reactive_cap_kvarh = ?,
                    reactive_ind_ratio_pct = ?,
                    reactive_cap_ratio_pct = ?
                WHERE snapshot_date = ?
            ''', (
                daily_active,
                daily_ind,
                daily_cap,
                ratio_ind,
                ratio_cap,
                cur['snapshot_date'],
            ))
            prev = cur
    
    def save_measurement(self, data):
        """Ölçüm verisini kaydet"""
        conn = self._connect()
        c = conn.cursor()
        
        c.execute('''
            INSERT INTO measurements (
                unit_id, v_l1, v_l2, v_l3, i_l1, i_l2, i_l3,
                p_l1, p_l2, p_l3, q_l1, q_l2, q_l3,
                pf_l1, pf_l2, pf_l3, f_hz,
                e_l1, e_l2, e_l3, step_status, p_total, e_total,
                e_reactive_ind_total, e_reactive_cap_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            data.get('unit_id'),
            data.get('v_l1_v'), data.get('v_l2_v'), data.get('v_l3_v'),
            data.get('i_l1_a'), data.get('i_l2_a'), data.get('i_l3_a'),
            data.get('p_l1_kw'), data.get('p_l2_kw'), data.get('p_l3_kw'),
            data.get('q_ind_l1_kvar'), data.get('q_ind_l2_kvar'), data.get('q_ind_l3_kvar'),
            data.get('pf_l1'), data.get('pf_l2'), data.get('pf_l3'),
            data.get('f_l1_hz'),
            data.get('e_l1_import_kwh'), data.get('e_l2_import_kwh'), data.get('e_l3_import_kwh'),
            int(data.get('step_status_bits', 0)),
            data.get('p_total_kw'),
            data.get('e_total_import_kwh'),
            data.get('e_total_reactive_ind_kvarh'),
            data.get('e_total_reactive_cap_kvarh')
        ))
        
        conn.commit()
        conn.close()
    
    def cleanup_old_data(self, days=DB_RETENTION_DAYS):
        """Eski verileri temizle"""
        conn = self._connect()
        c = conn.cursor()
        
        cutoff = datetime.now() - timedelta(days=days)
        c.execute('DELETE FROM measurements WHERE timestamp < ?', (cutoff,))
        
        conn.commit()
        conn.close()
    
    def get_history(self, hours=24, limit=1000):
        """Geçmiş verileri getir"""
        conn = self._connect()
        conn.row_factory = sqlite3.Row
        c = conn.cursor()
        
        cutoff = datetime.now() - timedelta(hours=hours)
        c.execute('''
            SELECT * FROM measurements 
            WHERE timestamp >= ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ''', (cutoff, limit))
        
        rows = c.fetchall()
        conn.close()
        
        return [dict(row) for row in rows]

    def get_hourly_consumption(self, snapshot_date=None):
        """Saatlik tüketim verilerini getir."""
        conn = self._connect()
        conn.row_factory = sqlite3.Row
        c = conn.cursor()

        if snapshot_date is None:
            c.execute('SELECT snapshot_date FROM hourly_snapshots ORDER BY snapshot_date DESC LIMIT 1')
            row = c.fetchone()
            snapshot_date = row[0] if row else None

        if snapshot_date is None:
            conn.close()
            return []

        c.execute('''
            SELECT snapshot_date, hour_slot, active_kwh, reactive_ind_kvarh,
                   reactive_cap_kvarh, source
            FROM hourly_snapshots
            WHERE snapshot_date = ?
            ORDER BY hour_slot ASC
        ''', (snapshot_date,))

        rows = [dict(row) for row in c.fetchall()]
        conn.close()
        return rows
    
    def save_daily_snapshot(self, data):
        """Günlük snapshot kaydet"""
        conn = self._connect()
        c = conn.cursor()
        
        snapshot_date = data.get('snapshot_date', datetime.now().strftime("%Y-%m-%d"))
        
        # Önce bugünün kaydı var mı kontrol et
        c.execute('SELECT id FROM daily_snapshots WHERE snapshot_date = ?', (snapshot_date,))
        existing = c.fetchone()
        
        if existing:
            # Güncelle
            c.execute('''
                UPDATE daily_snapshots SET
                    unit_id = ?, e_l1_kwh = ?, e_l2_kwh = ?, e_l3_kwh = ?, e_total_kwh = ?,
                    e_l1_reactive_ind_kvarh = ?, e_l2_reactive_ind_kvarh = ?, e_l3_reactive_ind_kvarh = ?,
                    e_total_reactive_ind_kvarh = ?, e_l1_reactive_cap_kvarh = ?, e_l2_reactive_cap_kvarh = ?,
                    e_l3_reactive_cap_kvarh = ?, e_total_reactive_cap_kvarh = ?,
                    daily_consumption_kwh = ?, daily_reactive_ind_kvarh = ?, daily_reactive_cap_kvarh = ?,
                    reactive_ind_ratio_pct = ?, reactive_cap_ratio_pct = ?
                WHERE snapshot_date = ?
            ''', (
                data.get('unit_id'), data.get('e_l1_kwh'), data.get('e_l2_kwh'), data.get('e_l3_kwh'),
                data.get('e_total_kwh'), data.get('e_l1_reactive_ind_kvarh'),
                data.get('e_l2_reactive_ind_kvarh'), data.get('e_l3_reactive_ind_kvarh'),
                data.get('e_total_reactive_ind_kvarh'), data.get('e_l1_reactive_cap_kvarh'),
                data.get('e_l2_reactive_cap_kvarh'), data.get('e_l3_reactive_cap_kvarh'),
                data.get('e_total_reactive_cap_kvarh'), data.get('daily_consumption_kwh'),
                data.get('daily_reactive_ind_kvarh'), data.get('daily_reactive_cap_kvarh'),
                data.get('reactive_ind_ratio_pct'), data.get('reactive_cap_ratio_pct'),
                snapshot_date
            ))
        else:
            # Yeni kayıt
            c.execute('''
                INSERT INTO daily_snapshots (
                    snapshot_date, unit_id, e_l1_kwh, e_l2_kwh, e_l3_kwh, e_total_kwh,
                    e_l1_reactive_ind_kvarh, e_l2_reactive_ind_kvarh, e_l3_reactive_ind_kvarh,
                    e_total_reactive_ind_kvarh, e_l1_reactive_cap_kvarh, e_l2_reactive_cap_kvarh,
                    e_l3_reactive_cap_kvarh, e_total_reactive_cap_kvarh,
                    daily_consumption_kwh, daily_reactive_ind_kvarh, daily_reactive_cap_kvarh,
                    reactive_ind_ratio_pct, reactive_cap_ratio_pct
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                snapshot_date, data.get('unit_id'), data.get('e_l1_kwh'), data.get('e_l2_kwh'),
                data.get('e_l3_kwh'), data.get('e_total_kwh'), data.get('e_l1_reactive_ind_kvarh'),
                data.get('e_l2_reactive_ind_kvarh'), data.get('e_l3_reactive_ind_kvarh'),
                data.get('e_total_reactive_ind_kvarh'), data.get('e_l1_reactive_cap_kvarh'),
                data.get('e_l2_reactive_cap_kvarh'), data.get('e_l3_reactive_cap_kvarh'),
                data.get('e_total_reactive_cap_kvarh'), data.get('daily_consumption_kwh'),
                data.get('daily_reactive_ind_kvarh'), data.get('daily_reactive_cap_kvarh'),
                data.get('reactive_ind_ratio_pct'), data.get('reactive_cap_ratio_pct')
            ))
        
        conn.commit()
        conn.close()
    
    def get_daily_snapshots(self, days=30):
        """Günlük snapshot'ları getir"""
        conn = self._connect()
        conn.row_factory = sqlite3.Row
        c = conn.cursor()
        
        c.execute('''
            SELECT ds.*, (
                SELECT COUNT(1)
                FROM hourly_snapshots hs
                WHERE hs.snapshot_date = ds.snapshot_date
            ) AS hourly_count
            FROM daily_snapshots ds
            WHERE ds.daily_consumption_kwh IS NULL OR (
                ds.daily_consumption_kwh BETWEEN 0 AND ?
                AND ABS(COALESCE(ds.daily_reactive_ind_kvarh, 0)) <= ?
                AND ABS(COALESCE(ds.daily_reactive_cap_kvarh, 0)) <= ?
            )
            ORDER BY snapshot_date DESC
            LIMIT ?
        ''', (
            MAX_VALID_DAILY_ACTIVE_KWH,
            MAX_VALID_DAILY_REACTIVE_KVARH,
            MAX_VALID_DAILY_REACTIVE_KVARH,
            days,
        ))
        
        rows = c.fetchall()
        conn.close()
        
        return [dict(row) for row in rows]

    def get_latest_snapshot_before(self, date_str):
        """Verilen tarihten önceki en son günlük snapshot'ı getir."""
        conn = self._connect()
        conn.row_factory = sqlite3.Row
        c = conn.cursor()
        c.execute('''
            SELECT *
            FROM daily_snapshots
            WHERE snapshot_date < ?
            ORDER BY snapshot_date DESC
            LIMIT 1
        ''', (date_str,))
        row = c.fetchone()
        conn.close()
        return dict(row) if row else None

    def get_consumption_summary(self, current_data):
        """Aybaşı→şimdi ve dün→şimdi tüketim oran özetlerini hesapla."""
        current_active = current_data.get('e_total_import_kwh')
        current_ind = current_data.get('e_total_reactive_ind_kvarh')
        current_cap = current_data.get('e_total_reactive_cap_kvarh')

        if current_active is None or current_ind is None or current_cap is None:
            return {}

        today = datetime.now().date()
        month_start = today.replace(day=1).strftime('%Y-%m-%d')
        today_str = today.strftime('%Y-%m-%d')

        base_month = self.get_latest_snapshot_before(month_start)
        base_day = self.get_latest_snapshot_before(today_str)

        def build_delta(base):
            if not base:
                return None
            base_active = base.get('e_total_kwh')
            base_ind = base.get('e_total_reactive_ind_kvarh')
            base_cap = base.get('e_total_reactive_cap_kvarh')

            if base_active is None or base_ind is None or base_cap is None:
                return None

            d_active = round(current_active - base_active, 2)
            d_ind = round(current_ind - base_ind, 2)
            d_cap = round(current_cap - base_cap, 2)

            if d_active <= 0:
                ind_pct = None
                cap_pct = None
            else:
                ind_pct = round((d_ind / d_active) * 100, 2)
                cap_pct = round((d_cap / d_active) * 100, 2)

            return {
                'from_date': base.get('snapshot_date'),
                'active_kwh': d_active,
                'reactive_ind_kvarh': d_ind,
                'reactive_cap_kvarh': d_cap,
                'ind_pct': ind_pct,
                'cap_pct': cap_pct,
            }

        month_to_now = build_delta(base_month)
        day_to_now = build_delta(base_day)

        now_ind_pct = None
        now_cap_pct = None
        if current_active > 0:
            now_ind_pct = round((current_ind / current_active) * 100, 2)
            now_cap_pct = round((current_cap / current_active) * 100, 2)

        return {
            'limits': {
                'ind_pct': END_AKTIF_LIMIT_PCT,
                'cap_pct': KAP_AKTIF_LIMIT_PCT,
            },
            'current_totals': {
                'active_kwh': current_active,
                'reactive_ind_kvarh': current_ind,
                'reactive_cap_kvarh': current_cap,
                'ind_pct': now_ind_pct,
                'cap_pct': now_cap_pct,
                'ind_over_limit': (now_ind_pct is not None and now_ind_pct >= END_AKTIF_LIMIT_PCT),
                'cap_over_limit': (now_cap_pct is not None and now_cap_pct >= KAP_AKTIF_LIMIT_PCT),
            },
            'month_to_now': month_to_now,
            'day_to_now': day_to_now,
        }
    
    def get_previous_day_snapshot(self, date_str):
        """Bir önceki günün snapshot'ını getir"""
        conn = self._connect()
        conn.row_factory = sqlite3.Row
        c = conn.cursor()
        
        c.execute('''
            SELECT * FROM daily_snapshots 
            WHERE snapshot_date < ? 
            ORDER BY snapshot_date DESC 
            LIMIT 1
        ''', (date_str,))
        
        row = c.fetchone()
        conn.close()
        
        return dict(row) if row else None


# ─── Veri Toplayıcı ──────────────────────────────────────────────────────────
class DataCollector:
    """Arka planda veri toplayan thread"""
    
    def __init__(self, ip, port, unit_id, db):
        self.ip = ip
        self.port = port
        self.unit_id = unit_id
        self.db = db
        self.running = False
        self.thread = None
        self.scheduler_thread = None
        self.current_data = {}
        self.history = deque(maxlen=HISTORY_SIZE)
        self.error_count = 0
        self.last_error = None
        self.lock = threading.Lock()
        self.last_snapshot_date = None
        self.last_hourly_slot = None   # "YYYY-MM-DD HH" — saatlik kayıt takibi
        self.prev_hourly_energy = {}   # saat başı enerji referans değerleri
        self.pause_event = threading.Event()
    
    def start(self):
        """Veri toplamayı başlat"""
        if self.running:
            return
        
        self.running = True
        self.thread = threading.Thread(target=self._collect_loop, daemon=True)
        self.thread.start()
        
        # Günlük + saatlik snapshot scheduler'ı başlat
        self.scheduler_thread = threading.Thread(target=self._scheduler_loop, daemon=True)
        self.scheduler_thread.start()
    
    def stop(self):
        """Veri toplamayı durdur"""
        self.running = False
        if self.thread:
            self.thread.join(timeout=5)
        if self.scheduler_thread:
            self.scheduler_thread.join(timeout=5)
    
    def _collect_loop(self):
        """Ana veri toplama döngüsü"""
        while self.running:
            if self.pause_event.is_set():
                time.sleep(0.2)
                continue

            try:
                data = self._collect_snapshot()
                
                with self.lock:
                    self.current_data = data
                    self.history.append(data)
                    self.error_count = 0
                    self.last_error = None
                
                # Veritabanına kaydet
                try:
                    self.db.save_measurement(data)
                except Exception as e:
                    with self.lock:
                        self.error_count += 1
                        self.last_error = f"DB: {e}"
                    print(f"DB save error: {e}")
                
            except Exception as e:
                with self.lock:
                    self.error_count += 1
                    self.last_error = str(e)
                print(f"Collection error: {e}")
            
            time.sleep(POLL_INTERVAL)

    def set_pause(self, paused):
        """Polling'i gecici duraklat/devam ettir."""
        if paused:
            self.pause_event.set()
        else:
            self.pause_event.clear()
    
    def _collect_snapshot(self):
        """Tek bir veri snapshot'ı topla"""
        data = {
            "ts": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "unit_id": self.unit_id,
        }
        
        with GatewayLock(self.ip, self.port):
            for key, config in REGISTER_MAP.items():
                try:
                    client = ModbusTCP(self.ip, self.port, self.unit_id)
                    client.connect()
                    try:
                        value = read_one_point(
                            client,
                            config["addr"],
                            config["dtype"],
                            config["scale"]
                        )
                    finally:
                        client.close()

                    # Dashboard'da gerilim sekonder tarafta gösterilir.
                    # Güç ve enerji hesapları için primer dönüşüm korunur.
                    if config["group"] == "current" and value is not None:
                        value = value * CT_RATIO          # A_sek → A_prim
                    elif config["group"] == "power" and value is not None:
                        value = value * PT_RATIO          # W_sek → W_prim

                    data[key] = round(value, 3) if value is not None else None
                except Exception as e:
                    data[key] = None
                    data[f"{key}_err"] = str(e)

                # Gateway/RTU tarafını zorlamamak için kısa bekleme
                time.sleep(REQUEST_DELAY)
        
        # Hesaplanan değerler - Güç Toplamı
        if all(data.get(k) is not None for k in ("p_l1_kw", "p_l2_kw", "p_l3_kw")):
            data["p_total_kw"] = round(data["p_l1_kw"] + data["p_l2_kw"] + data["p_l3_kw"], 3)
        
        # Aktif Enerji Toplamı
        if all(data.get(k) is not None for k in ("e_l1_import_kwh", "e_l2_import_kwh", "e_l3_import_kwh")):
            data["e_total_import_kwh"] = round(
                data["e_l1_import_kwh"] + data["e_l2_import_kwh"] + data["e_l3_import_kwh"],
                3
            )
        
        # Reaktif Enerji Toplamı - Endüktif
        if all(data.get(k) is not None for k in ("e_l1_reactive_ind_kvarh", "e_l2_reactive_ind_kvarh", "e_l3_reactive_ind_kvarh")):
            data["e_total_reactive_ind_kvarh"] = round(
                data["e_l1_reactive_ind_kvarh"] + data["e_l2_reactive_ind_kvarh"] + data["e_l3_reactive_ind_kvarh"],
                3
            )
        
        # Reaktif Enerji Toplamı - Kapasitif
        if all(data.get(k) is not None for k in ("e_l1_reactive_cap_kvarh", "e_l2_reactive_cap_kvarh", "e_l3_reactive_cap_kvarh")):
            data["e_total_reactive_cap_kvarh"] = round(
                data["e_l1_reactive_cap_kvarh"] + data["e_l2_reactive_cap_kvarh"] + data["e_l3_reactive_cap_kvarh"],
                3
            )
        
        # Faz-Faz gerilim: L-N degerlerinden hesapla (V_LL = sqrt(Va^2 + Vb^2 + Va*Vb))
        import math as _math
        for ll_key, va_key, vb_key in [
            ("v_l1_l2_v", "v_l1_v", "v_l2_v"),
            ("v_l2_l3_v", "v_l2_v", "v_l3_v"),
            ("v_l3_l1_v", "v_l3_v", "v_l1_v"),
        ]:
            va = data.get(va_key)
            vb = data.get(vb_key)
            if va is not None and vb is not None and va > 0 and vb > 0:
                data[ll_key] = round(_math.sqrt(va * va + vb * vb + va * vb), 1)
            else:
                data[ll_key] = None

        # Oranlar (%)
        if data.get("e_total_import_kwh") and data.get("e_total_import_kwh") > 0:
            if data.get("e_total_reactive_ind_kvarh") is not None:
                data["reactive_ind_ratio_pct"] = round(
                    (data["e_total_reactive_ind_kvarh"] / data["e_total_import_kwh"]) * 100, 2
                )
            if data.get("e_total_reactive_cap_kvarh") is not None:
                data["reactive_cap_ratio_pct"] = round(
                    (data["e_total_reactive_cap_kvarh"] / data["e_total_import_kwh"]) * 100, 2
                )
        
        return data
    
    def get_current_data(self):
        """Güncel veriyi al"""
        with self.lock:
            return self.current_data.copy()
    
    def get_history(self):
        """Geçmiş verileri al"""
        with self.lock:
            return list(self.history)
    
    def get_status(self):
        """Toplayıcı durumunu al"""
        with self.lock:
            return {
                "running": self.running,
                "error_count": self.error_count,
                "last_error": self.last_error,
                "data_points": len(self.history),
                "last_snapshot_date": self.last_snapshot_date
            }
    
    def _scheduler_loop(self):
        """Günlük + saatlik snapshot scheduler — her 1 dakikada kontrol eder."""
        while self.running:
            try:
                now = datetime.now()
                today_str = now.strftime("%Y-%m-%d")
                hour_slot = now.strftime("%H:00")
                hourly_key = f"{today_str} {hour_slot}"

                # ── Saatlik kayıt: her saat değişiminde ─────────────────────
                if self.last_hourly_slot != hourly_key:
                    current = self.get_current_data()
                    if current and current.get('e_total_import_kwh') is not None:
                        self._save_hourly_snapshot(today_str, hour_slot, current)
                        self.last_hourly_slot = hourly_key

                # ── Günlük snapshot: saat 00:05-00:59 arasında bir kez ──────
                if self.last_snapshot_date != today_str and now.hour == 0 and now.minute >= 5:
                    current = self.get_current_data()
                    if current and current.get('e_total_import_kwh'):
                        self._take_daily_snapshot(today_str, current)
                        self.last_snapshot_date = today_str
                        print(f"[SNAPSHOT] Gunluk snapshot alindi: {today_str}")

                # 60 saniyede bir kontrol
                time.sleep(60)

            except Exception as e:
                print(f"Scheduler error: {e}")
                time.sleep(60)

    def _save_hourly_snapshot(self, date_str, hour_slot, current_data):
        """Saatlik enerji tüketimini kaydet (bu saat — önceki saat farkı)."""
        try:
            total_kwh = current_data.get('e_total_import_kwh')
            total_ind  = current_data.get('e_total_reactive_ind_kvarh', 0) or 0
            total_cap  = current_data.get('e_total_reactive_cap_kvarh', 0) or 0

            prev = self.prev_hourly_energy
            active_diff   = round(total_kwh - prev.get('kwh', total_kwh), 3) if prev else 0.0
            ind_diff      = round(total_ind  - prev.get('ind', total_ind),  3) if prev else 0.0
            cap_diff      = round(total_cap  - prev.get('cap', total_cap),  3) if prev else 0.0

            # Negatif fark veya aşırı değerleri filtrele
            if active_diff < 0 or active_diff > MAX_VALID_DAILY_ACTIVE_KWH / 24.0 * 3:
                active_diff = 0.0
            if abs(ind_diff) > MAX_VALID_DAILY_REACTIVE_KVARH / 24.0 * 3:
                ind_diff = 0.0
            if abs(cap_diff) > MAX_VALID_DAILY_REACTIVE_KVARH / 24.0 * 3:
                cap_diff = 0.0

            conn = self.db._connect()
            c = conn.cursor()
            c.execute(
                '''INSERT OR REPLACE INTO hourly_snapshots
                   (snapshot_date, hour_slot, active_kwh, reactive_ind_kvarh, reactive_cap_kvarh, source)
                   VALUES (?, ?, ?, ?, ?, ?)''',
                (date_str, hour_slot, active_diff, ind_diff, cap_diff, 'live')
            )
            conn.commit()
            conn.close()

            # Referansı güncelle
            self.prev_hourly_energy = {'kwh': total_kwh, 'ind': total_ind, 'cap': total_cap}
            print(f"[HOURLY] {date_str} {hour_slot} → {active_diff:.1f} kWh kaydedildi")

        except Exception as e:
            print(f"Hourly snapshot error: {e}")
    
    def _take_daily_snapshot(self, date_str, current_data):
        """Günlük snapshot al ve kaydet"""
        try:
            # Önceki günün snapshot'ını al
            prev = self.db.get_previous_day_snapshot(date_str)
            
            snapshot = {
                'snapshot_date': date_str,
                'unit_id': self.unit_id,
                'e_l1_kwh': current_data.get('e_l1_import_kwh'),
                'e_l2_kwh': current_data.get('e_l2_import_kwh'),
                'e_l3_kwh': current_data.get('e_l3_import_kwh'),
                'e_total_kwh': current_data.get('e_total_import_kwh'),
                'e_l1_reactive_ind_kvarh': current_data.get('e_l1_reactive_ind_kvarh'),
                'e_l2_reactive_ind_kvarh': current_data.get('e_l2_reactive_ind_kvarh'),
                'e_l3_reactive_ind_kvarh': current_data.get('e_l3_reactive_ind_kvarh'),
                'e_total_reactive_ind_kvarh': current_data.get('e_total_reactive_ind_kvarh'),
                'e_l1_reactive_cap_kvarh': current_data.get('e_l1_reactive_cap_kvarh'),
                'e_l2_reactive_cap_kvarh': current_data.get('e_l2_reactive_cap_kvarh'),
                'e_l3_reactive_cap_kvarh': current_data.get('e_l3_reactive_cap_kvarh'),
                'e_total_reactive_cap_kvarh': current_data.get('e_total_reactive_cap_kvarh'),
            }
            
            # Günlük tüketim hesapla (bugün - dün)
            if prev:
                gap_days = 1
                prev_date = prev.get('snapshot_date')
                if prev_date:
                    try:
                        prev_dt = datetime.strptime(prev_date, '%Y-%m-%d').date()
                        cur_dt = datetime.strptime(date_str, '%Y-%m-%d').date()
                        gap_days = max((cur_dt - prev_dt).days, 1)
                    except ValueError:
                        gap_days = 1

                snapshot['daily_consumption_kwh'] = round(
                    (snapshot['e_total_kwh'] - prev.get('e_total_kwh', 0)) / gap_days, 2
                )
                snapshot['daily_reactive_ind_kvarh'] = round(
                    (snapshot['e_total_reactive_ind_kvarh'] - prev.get('e_total_reactive_ind_kvarh', 0)) / gap_days, 2
                )
                snapshot['daily_reactive_cap_kvarh'] = round(
                    (snapshot['e_total_reactive_cap_kvarh'] - prev.get('e_total_reactive_cap_kvarh', 0)) / gap_days, 2
                )
                
                # Günlük oranlar
                if snapshot['daily_consumption_kwh'] > 0:
                    snapshot['reactive_ind_ratio_pct'] = round(
                        (snapshot['daily_reactive_ind_kvarh'] / snapshot['daily_consumption_kwh']) * 100, 2
                    )
                    snapshot['reactive_cap_ratio_pct'] = round(
                        (snapshot['daily_reactive_cap_kvarh'] / snapshot['daily_consumption_kwh']) * 100, 2
                    )

                # Anlamsız sıçramaları filtrele
                if (
                    snapshot['daily_consumption_kwh'] < 0
                    or snapshot['daily_consumption_kwh'] > MAX_VALID_DAILY_ACTIVE_KWH
                    or abs(snapshot['daily_reactive_ind_kvarh']) > MAX_VALID_DAILY_REACTIVE_KVARH
                    or abs(snapshot['daily_reactive_cap_kvarh']) > MAX_VALID_DAILY_REACTIVE_KVARH
                ):
                    print(
                        f"[SNAPSHOT] Anomali filtrelendi ({date_str}) | "
                        f"aktif={snapshot['daily_consumption_kwh']} "
                        f"ind={snapshot['daily_reactive_ind_kvarh']} "
                        f"cap={snapshot['daily_reactive_cap_kvarh']}"
                    )
                    return
            
            # Veritabanına kaydet
            self.db.save_daily_snapshot(snapshot)
            
        except Exception as e:
            print(f"Snapshot error: {e}")


# ─── Flask Web Uygulaması ────────────────────────────────────────────────────
app = Flask(__name__, 
            static_folder=os.path.join(SCRIPT_DIR, 'sog5_static'),
            template_folder=os.path.join(SCRIPT_DIR, 'sog5_templates'))
CORS(app)

# Global değişkenler
db = Database(DB_PATH)
collector = None


@app.route('/')
def index():
    """Ana sayfa"""
    return render_template('sog5_dashboard.html')


@app.route('/register-map')
def register_map_page():
    """Kılavuz register listeleme sayfası"""
    return render_template('sog5_register_map.html')


@app.route('/api/register-map')
def api_register_map():
    """Kılavuz register metadata API"""
    return jsonify(
        {
            "ip": DEFAULT_IP,
            "port": DEFAULT_PORT,
            "unit_id": DEFAULT_UNIT_ID,
            "count": len(GUIDE_REGISTER_MAP),
            "registers": GUIDE_REGISTER_MAP,
        }
    )


@app.route('/api/register-map/live')
def api_register_map_live():
    """Kılavuz register listesini canlı ham+decode oku"""
    was_paused = False
    try:
        if collector is not None:
            collector.set_pause(True)
            was_paused = True

        rows = collect_guide_register_snapshot(
            DEFAULT_IP,
            DEFAULT_PORT,
            DEFAULT_UNIT_ID,
            lock_timeout=REGISTER_MAP_LIVE_LOCK_TIMEOUT,
        )
        return jsonify(
            {
                "success": True,
                "ip": DEFAULT_IP,
                "port": DEFAULT_PORT,
                "unit_id": DEFAULT_UNIT_ID,
                "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "rows": rows,
            }
        )
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        if collector is not None and was_paused:
            collector.set_pause(False)


@app.route('/api/current')
def api_current():
    """Güncel veri API"""
    if collector is None:
        return jsonify({"error": "Collector not started"}), 503
    
    data = collector.get_current_data()
    return jsonify(data)


@app.route('/api/history')
def api_history():
    """Geçmiş veri API"""
    if collector is None:
        return jsonify({"error": "Collector not started"}), 503
    
    history = collector.get_history()
    return jsonify(history)


@app.route('/api/status')
def api_status():
    """Sistem durumu API"""
    if collector is None:
        return jsonify({"error": "Collector not started"}), 503
    
    status = collector.get_status()
    return jsonify(status)


@app.route('/api/db/history')
def api_db_history():
    """Veritabanından geçmiş veri"""
    hours = request.args.get('hours', 24, type=int)
    limit = request.args.get('limit', 1000, type=int)
    
    history = db.get_history(hours=hours, limit=limit)
    return jsonify(history)


@app.route('/api/control/step/<int:step_num>', methods=['POST'])
def api_control_step(step_num):
    """Kademe kontrolü (test)"""
    # Adres 185: Kademe testi
    # Low byte = kademe numarası
    try:
        with GatewayLock(DEFAULT_IP, DEFAULT_PORT):
            client = ModbusTCP(DEFAULT_IP, DEFAULT_PORT, DEFAULT_UNIT_ID)
            client.connect()
            try:
                client.write_single_reg(185, step_num)
                return jsonify({"success": True, "step": step_num})
            finally:
                client.close()
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.route('/api/control/step/cancel', methods=['POST'])
def api_control_step_cancel():
    """Kademe testi iptali"""
    # Adres 100: Kademe testi iptali
    try:
        with GatewayLock(DEFAULT_IP, DEFAULT_PORT):
            client = ModbusTCP(DEFAULT_IP, DEFAULT_PORT, DEFAULT_UNIT_ID)
            client.connect()
            try:
                client.write_single_reg(100, 1)
                return jsonify({"success": True})
            finally:
                client.close()
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.route('/api/daily-records')
def api_daily_records():
    """Günlük sayaç kayıtları API"""
    days = request.args.get('days', 30, type=int)
    records = db.get_daily_snapshots(days=days)
    return jsonify(records)


@app.route('/api/hourly-consumption')
def api_hourly_consumption():
    """Saatlik toplam aktif/reaktif tüketim API"""
    snapshot_date = request.args.get('date')
    rows = db.get_hourly_consumption(snapshot_date=snapshot_date)
    return jsonify(rows)


@app.route('/api/consumption-summary')
def api_consumption_summary():
    """Ceza eşikleriyle birlikte tüketim oran özeti API"""
    if collector is None:
        return jsonify({"error": "Collector not started"}), 503

    current = collector.get_current_data()
    summary = db.get_consumption_summary(current)
    return jsonify(summary)


def main():
    """Ana fonksiyon"""
    global collector
    
    print("=" * 60)
    print("SMART SOG5 Güç Kontrol Rölesi - Web Dashboard")
    print("=" * 60)
    print(f"Cihaz IP: {DEFAULT_IP}:{DEFAULT_PORT}")
    print(f"Unit ID: {DEFAULT_UNIT_ID}")
    print(f"Veritabanı: {DB_PATH}")
    print(f"Polling: {POLL_INTERVAL} saniye")
    print("=" * 60)
    
    # Veri toplayıcıyı başlat
    collector = DataCollector(DEFAULT_IP, DEFAULT_PORT, DEFAULT_UNIT_ID, db)
    collector.start()
    
    print("\n[OK] Veri toplayici baslatildi")
    print(f"\n[WEB] Arayuz: http://localhost:5000")
    print("\nCikmak icin Ctrl+C\n")
    
    try:
        app.run(host='0.0.0.0', port=5000, debug=False, threaded=True)
    except KeyboardInterrupt:
        print("\n\nKapatılıyor...")
    finally:
        if collector:
            collector.stop()
        print("[OK] Temizlendi")


if __name__ == "__main__":
    main()
