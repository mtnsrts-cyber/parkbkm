<?php
/**
 * @var yii\web\View $this
 */

use yii\bootstrap5\Html;

$this->title = 'SOG5 Canlı Enerji İzleme';
?>

<style>
.sog5-dashboard { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #e2e8f0; }
.sog5-dashboard header { text-align: center; padding: 10px 0 20px; border-bottom: 2px solid #334155; margin-bottom: 20px; }
.sog5-dashboard h1 { color: #38bdf8; font-size: 26px; }
.sog5-dashboard .status { color: #94a3b8; font-size: 14px; margin-top: 8px; }
.sog5-dashboard .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px; }
.sog5-dashboard .card { background: #1e293b; border-radius: 12px; padding: 16px; border: 1px solid #334155; }
.sog5-dashboard .card-title { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.sog5-dashboard .card-title span { font-size: 18px; }
.sog5-dashboard .metric-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #334155; }
.sog5-dashboard .metric-row:last-child { border-bottom: none; }
.sog5-dashboard .metric-label { color: #cbd5e1; font-size: 14px; }
.sog5-dashboard .metric-value { font-size: 18px; font-weight: 600; }
.sog5-dashboard .metric-unit { font-size: 12px; color: #94a3b8; margin-left: 4px; }
.sog5-dashboard .total-row { background: #0f172a; border-radius: 8px; padding: 10px; margin-top: 10px; }
.sog5-dashboard .total-row .metric-value { font-size: 22px; color: #38bdf8; }
.sog5-dashboard .disconnected { color: #ef4444; }
.sog5-dashboard .connected { color: #22c55e; }
.badge-green { color: #22c55e; }
.badge-yellow { color: #eab308; }
.badge-red { color: #ef4444; }
.badge-blue { color: #3b82f6; }
.badge-purple { color: #a855f7; }
.badge-orange { color: #f97316; }
</style>

<div class="sog5-dashboard">
  <div class="container-fluid">
    <header>
      <h1>Smart SOG5 SGA124 — Canlı Enerji İzleme</h1>
      <div class="status" id="sog5-status">Bağlantı kuruluyor...</div>
      <div class="status" id="sog5-lastUpdate"></div>
    </header>

    <div class="grid">
      <div class="card">
        <div class="card-title"><span>⚡</span> Gerilim (V)</div>
        <div class="metric-row"><span class="metric-label">L1-N</span><span><span class="metric-value badge-green" id="v_L1">---</span><span class="metric-unit">V</span></span></div>
        <div class="metric-row"><span class="metric-label">L2-N</span><span><span class="metric-value badge-green" id="v_L2">---</span><span class="metric-unit">V</span></span></div>
        <div class="metric-row"><span class="metric-label">L3-N</span><span><span class="metric-value badge-green" id="v_L3">---</span><span class="metric-unit">V</span></span></div>
        <div class="metric-row"><span class="metric-label">L1-L2</span><span><span class="metric-value badge-yellow" id="v_L1L2">---</span><span class="metric-unit">V</span></span></div>
        <div class="metric-row"><span class="metric-label">L2-L3</span><span><span class="metric-value badge-yellow" id="v_L2L3">---</span><span class="metric-unit">V</span></span></div>
        <div class="metric-row"><span class="metric-label">L1-L3</span><span><span class="metric-value badge-yellow" id="v_L1L3">---</span><span class="metric-unit">V</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>🔌</span> Akım (A)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-blue" id="c_L1">---</span><span class="metric-unit">A</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-blue" id="c_L2">---</span><span class="metric-unit">A</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-blue" id="c_L3">---</span><span class="metric-unit">A</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>🔄</span> Frekans (Hz)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-purple" id="f_L1">---</span><span class="metric-unit">Hz</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-purple" id="f_L2">---</span><span class="metric-unit">Hz</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-purple" id="f_L3">---</span><span class="metric-unit">Hz</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>💡</span> Aktif Güç (kW)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-green" id="ap_L1">---</span><span class="metric-unit">kW</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-green" id="ap_L2">---</span><span class="metric-unit">kW</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-green" id="ap_L3">---</span><span class="metric-unit">kW</span></span></div>
        <div class="total-row metric-row"><span class="metric-label"><b>TOPLAM</b></span><span><span class="metric-value" id="ap_Total">---</span><span class="metric-unit">kW</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>🔋</span> İndüktif Güç (kVAr)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-yellow" id="ip_L1">---</span><span class="metric-unit">kVAr</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-yellow" id="ip_L2">---</span><span class="metric-unit">kVAr</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-yellow" id="ip_L3">---</span><span class="metric-unit">kVAr</span></span></div>
        <div class="total-row metric-row"><span class="metric-label"><b>TOPLAM</b></span><span><span class="metric-value" id="ip_Total">---</span><span class="metric-unit">kVAr</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>⚡</span> Kapasitif Güç (kVAr)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-purple" id="cp_L1">---</span><span class="metric-unit">kVAr</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-purple" id="cp_L2">---</span><span class="metric-unit">kVAr</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-purple" id="cp_L3">---</span><span class="metric-unit">kVAr</span></span></div>
        <div class="total-row metric-row"><span class="metric-label"><b>TOPLAM</b></span><span><span class="metric-value" id="cp_Total">---</span><span class="metric-unit">kVAr</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>📐</span> Cos φ</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-green" id="cos_L1">---</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-green" id="cos_L2">---</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-green" id="cos_L3">---</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>⚠️</span> THDI (%)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-orange" id="thdi_L1">---</span><span class="metric-unit">%</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-orange" id="thdi_L2">---</span><span class="metric-unit">%</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-orange" id="thdi_L3">---</span><span class="metric-unit">%</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>📊</span> Aktif Enerji (kWh)</div>
        <div class="metric-row"><span class="metric-label">L1</span><span><span class="metric-value badge-blue" id="ae_L1">---</span><span class="metric-unit">kWh</span></span></div>
        <div class="metric-row"><span class="metric-label">L2</span><span><span class="metric-value badge-blue" id="ae_L2">---</span><span class="metric-unit">kWh</span></span></div>
        <div class="metric-row"><span class="metric-label">L3</span><span><span class="metric-value badge-blue" id="ae_L3">---</span><span class="metric-unit">kWh</span></span></div>
        <div class="total-row metric-row"><span class="metric-label"><b>TOPLAM</b></span><span><span class="metric-value" id="ae_Total">---</span><span class="metric-unit">kWh</span></span></div>
      </div>

      <div class="card">
        <div class="card-title"><span>🔁</span> Reaktif Enerji (kVArh)</div>
        <div class="metric-row"><span class="metric-label">İndüktif</span><span><span class="metric-value badge-yellow" id="re_ind">---</span><span class="metric-unit">kVArh</span></span></div>
        <div class="metric-row"><span class="metric-label">Kapasitif</span><span><span class="metric-value badge-purple" id="re_cap">---</span><span class="metric-unit">kVArh</span></span></div>
      </div>
    </div>

    <div class="text-center pb-4 text-muted small">
      Smart SOG5 SGA124 | Modbus TCP | Node.js Portable | Grup Arge
    </div>
  </div>
</div>

<script>
  const statusEl = document.getElementById('sog5-status');
  const lastUpdateEl = document.getElementById('sog5-lastUpdate');

  function calcLineVoltage(a, b) {
    if (a === null || b === null) return null;
    return Math.sqrt(a*a + b*b + a*b).toFixed(1);
  }

  function updateValue(id, val, decimals = 2) {
    const el = document.getElementById(id);
    if (!el) return;
    if (val === null || val === undefined) { el.textContent = '---'; return; }
    el.textContent = typeof val === 'number' ? val.toFixed(decimals) : val;
  }

  function onData(d) {
    statusEl.textContent = 'Canlı veri alınıyor';
    statusEl.className = 'status connected';
    lastUpdateEl.textContent = new Date(d.timestamp).toLocaleString('tr-TR');

    const v = d.voltage || {};
    updateValue('v_L1', v.L1, 1);
    updateValue('v_L2', v.L2, 1);
    updateValue('v_L3', v.L3, 1);
    updateValue('v_L1L2', calcLineVoltage(v.L1, v.L2), 1);
    updateValue('v_L2L3', calcLineVoltage(v.L2, v.L3), 1);
    updateValue('v_L1L3', calcLineVoltage(v.L1, v.L3), 1);

    const c = d.current || {};
    updateValue('c_L1', c.L1, 2);
    updateValue('c_L2', c.L2, 2);
    updateValue('c_L3', c.L3, 2);

    const f = d.frequency || {};
    updateValue('f_L1', f.L1, 1);
    updateValue('f_L2', f.L2, 1);
    updateValue('f_L3', f.L3, 1);

    const ap = d.active_power || {};
    updateValue('ap_L1', ap.L1);
    updateValue('ap_L2', ap.L2);
    updateValue('ap_L3', ap.L3);
    updateValue('ap_Total', ap.Total);

    const ip = d.inductive_power || {};
    updateValue('ip_L1', ip.L1);
    updateValue('ip_L2', ip.L2);
    updateValue('ip_L3', ip.L3);
    updateValue('ip_Total', ip.Total);

    const cp = d.capacitive_power || {};
    updateValue('cp_L1', cp.L1);
    updateValue('cp_L2', cp.L2);
    updateValue('cp_L3', cp.L3);
    updateValue('cp_Total', cp.Total);

    const cos = d.cos_phi || {};
    updateValue('cos_L1', cos.L1);
    updateValue('cos_L2', cos.L2);
    updateValue('cos_L3', cos.L3);

    const thdi = d.thdi || {};
    updateValue('thdi_L1', thdi.L1, 1);
    updateValue('thdi_L2', thdi.L2, 1);
    updateValue('thdi_L3', thdi.L3, 1);

    const ae = d.active_energy || {};
    updateValue('ae_L1', ae.L1);
    updateValue('ae_L2', ae.L2);
    updateValue('ae_L3', ae.L3);
    updateValue('ae_Total', ae.Total);

    const re = d.reactive_energy || {};
    updateValue('re_ind', re.inductive_total);
    updateValue('re_cap', re.capacitive_total);
  }

  function connect() {
    const ws = new WebSocket('ws://127.0.0.1:3456/ws');
    ws.binaryType = 'arraybuffer';

    ws.onopen = () => {
      statusEl.textContent = 'Bağlantı kuruldu, veri bekleniyor...';
      statusEl.className = 'status connected';
    };

    ws.onmessage = (ev) => {
      try {
        const text = new TextDecoder().decode(ev.data);
        const data = JSON.parse(text);
        onData(data);
      } catch (e) {}
    };

    ws.onclose = () => {
      statusEl.textContent = 'Bağlantı koptu, yeniden bağlanılıyor...';
      statusEl.className = 'status disconnected';
      setTimeout(connect, 3000);
    };

    ws.onerror = () => {
      statusEl.textContent = 'Bağlantı hatası';
      statusEl.className = 'status disconnected';
    };
  }

  connect();
</script>
