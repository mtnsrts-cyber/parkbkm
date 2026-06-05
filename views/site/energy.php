<?php
/**
 * Enerji İzleme Dashboard
 * Sol 1/3: SOG5 Güç Kontrol Rölesi
 * Sağ 2/3: Entes Analizör
 *
 * @var yii\web\View $this
 * @var array $analizorler
 */

use yii\helpers\Url;
use yii\bootstrap5\Html;

$this->title = 'Enerji İzleme Dashboard';

$reqId = Yii::$app->request->get('id');
if ($reqId && isset($analizorler[$reqId])) {
    $aktifId = $reqId;
} else {
    $aktifId = $analizorler ? array_keys($analizorler)[0] : null;
}
$aktifConfig = $analizorler[$aktifId] ?? null;
?>

<style>
.sog5-panel { background:#0d1626; border:1px solid #1e3a5f; border-top:3px solid #38bdf8; border-radius:8px; padding:12px; font-size:12px; }
.sog5-panel h5 { color:#e94560; margin-bottom:10px; font-size:13px; }
.sog5-status-card { background:#0f172a; border:1px solid #334155; border-radius:6px; padding:8px; margin-bottom:8px; }
.sog5-step-row { display:flex; justify-content:space-between; align-items:center; padding:3px 0; border-bottom:1px solid #2d3348; font-size:11px; }
.sog5-step-row:last-child { border-bottom:none; }
.sog5-step-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:5px; }
.sog5-dot-on { background:#22c55e; box-shadow:0 0 5px #22c55e; }
.sog5-dot-off { background:#374151; }
.sog5-steps-horizontal { display:flex; gap:4px; flex-wrap:wrap; justify-content:center; }
.sog5-step-item { text-align:center; min-width:36px; }
.sog5-step-item .sog5-step-dot { width:12px; height:12px; margin:0 auto 3px; }
.sog5-step-item .step-num { font-size:9px; color:#9ca3af; }
.sog5-power-bar { display:flex; align-items:center; gap:6px; margin:4px 0; flex-wrap:wrap; }
.sog5-power-bar-label { width:60px; font-size:11px; color:#9ca3af; flex-shrink:0; }
.sog5-power-bar-track { flex:1; min-width:80px; height:11px; background:#2d3348; border-radius:4px; overflow:hidden; position:relative; }
.sog5-power-bar-fill { height:100%; border-radius:4px; transition:width 0.5s ease; }
.sog5-bar-p { background:#22c55e; }
.sog5-bar-qind { background:#f59e0b; }
.sog5-bar-qcap { background:#06b6d4; }
.sog5-power-bar-value { min-width:52px; text-align:right; font-size:11px; font-weight:bold; }
.energy-card { background: rgba(255,255,255,0.03); border-radius: 6px; padding: 8px; }
.sog5-panel .h5 { font-size:1rem !important; }
.sog5-panel .h6 { font-size:0.85rem !important; }
.sog5-panel .small, .sog5-panel .text-muted { font-size:10px !important; }
.sog5-panel .badge { font-size:9px; padding:2px 6px; }
</style>

<div class="container-fluid mt-3">
    <h4 class="mb-3" style="color:#38bdf8; border-bottom:2px solid #334155; padding-bottom:10px;">
        ⚡ Enerji İzleme Dashboard
    </h4>
    <div class="row">
        <!-- SOG5 - SOL 1/2 -->
        <div class="col-lg-6 mb-3">
            <div class="sog5-panel">
                <h5>🔧 SOG5 Güç Kontrol Rölesi</h5>
                
                <!-- Bağlantı Durumu -->
                <div class="sog5-status-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Bağlantı Durumu</span>
                        <span class="badge" id="sog5-status-badge" style="background:#6c757d; color:#fff">Bekleniyor...</span>
                    </div>
                    <div class="text-muted small" id="sog5-last-update">--:--:--</div>
                </div>

                <!-- QBilgi -->
                <div class="sog5-status-card">
                    <div class="text-muted small mb-2"><strong>QBilgi</strong></div>
                    <div class="sog5-power-bar">
                        <span class="sog5-power-bar-label">P (Aktif)</span>
                        <div class="sog5-power-bar-track"><div class="sog5-power-bar-fill sog5-bar-p" id="sog5-bar-p" style="width:0%"></div></div>
                        <span class="sog5-power-bar-value text-success" id="sog5-p-val">---</span>
                    </div>
                    <div class="sog5-power-bar">
                        <span class="sog5-power-bar-label">Q (Endük.)</span>
                        <div class="sog5-power-bar-track"><div class="sog5-power-bar-fill sog5-bar-qind" id="sog5-bar-qind" style="width:0%"></div></div>
                        <span class="sog5-power-bar-value text-warning" id="sog5-qind-val">---</span>
                        <span class="small text-muted ms-1" id="sog5-qind-pct">---</span>
                    </div>
                    <div class="sog5-power-bar">
                        <span class="sog5-power-bar-label">Q (Kapas.)</span>
                        <div class="sog5-power-bar-track"><div class="sog5-power-bar-fill sog5-bar-qcap" id="sog5-bar-qcap" style="width:0%"></div></div>
                        <span class="sog5-power-bar-value text-info" id="sog5-qcap-val">---</span>
                        <span class="small text-muted ms-1" id="sog5-qcap-pct">---</span>
                    </div>
                    <div class="sog5-step-row mt-2">
                        <span class="text-muted small">Cos φ</span>
                        <span class="font-weight-bold text-info" id="sog5-cosfi">---</span>
                    </div>
                    <div class="sog5-step-row">
                        <span class="text-muted small">Frekans</span>
                        <span class="font-weight-bold" id="sog5-freq">---</span>
                    </div>
                </div>

                <!-- Enerji Sayaçları -->
                <div class="sog5-status-card">
                    <div class="text-muted small mb-3"><strong>Enerji Sayaçları</strong></div>
                    
                    <!-- Aktif Tüketim -->
                    <div class="energy-card mb-3" style="border-left: 4px solid #22c55e;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small text-muted">Aktif Tüketim</div>
                            <div class="badge bg-success rounded-pill">kWh</div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-muted small">Saatlik</div>
                                <div class="h5 mb-0" id="sog5-e-hourly">---</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Günlük</div>
                                <div class="h5 mb-0" id="sog5-e-daily">---</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Aylık</div>
                                <div class="h5 mb-0 text-muted" id="sog5-e-monthly">---</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reaktif Endüktif -->
                    <div class="energy-card mb-3" style="border-left: 4px solid #f59e0b;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small text-muted">Reaktif Endüktif</div>
                            <div class="badge bg-warning text-dark rounded-pill">kVArh</div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-muted small">Saatlik</div>
                                <div class="h6 mb-0" id="sog5-qind-hourly">---</div>
                                <div class="small text-muted" id="sog5-qind-oran-hourly"></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Günlük</div>
                                <div class="h6 mb-0" id="sog5-qind-daily">---</div>
                                <div class="small text-muted" id="sog5-qind-oran-daily"></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Aylık</div>
                                <div class="h6 mb-0 text-muted" id="sog5-qind-monthly">---</div>
                                <div class="small text-muted" id="sog5-qind-oran-monthly"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reaktif Kapasitif -->
                    <div class="energy-card" style="border-left: 4px solid #06b6d4;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small text-muted">Reaktif Kapasitif</div>
                            <div class="badge bg-info rounded-pill">kVArh</div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-muted small">Saatlik</div>
                                <div class="h6 mb-0" id="sog5-qcap-hourly">---</div>
                                <div class="small text-muted" id="sog5-qcap-oran-hourly"></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Günlük</div>
                                <div class="h6 mb-0" id="sog5-qcap-daily">---</div>
                                <div class="small text-muted" id="sog5-qcap-oran-daily"></div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Aylık</div>
                                <div class="h6 mb-0 text-muted" id="sog5-qcap-monthly">---</div>
                                <div class="small text-muted" id="sog5-qcap-oran-monthly"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- ENTES - SAĞ 1/2 -->
        <div class="col-lg-6" style="border-left: 2px solid #2d3348; padding-left:20px;">
            <div style="background:#12111a; border:1px solid #2d1f4a; border-top:3px solid #e94560; border-radius:8px; padding:12px;">
            <!-- Analizör Seçici -->
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                <select class="form-select form-select-sm w-auto" id="analizorSelect">
                    <option value="">-- Analizör Seç --</option>
                    <?php foreach ($analizorler as $id => $cfg): ?>
                        <option value="<?= Html::encode($id) ?>" <?= $id === $aktifId ? 'selected' : '' ?>><?= Html::encode($id) ?> - <?= Html::encode($cfg['model'] ?? '-') ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (Yii::$app->user->identity && Yii::$app->user->identity->role === 'admin'): ?>
                    <a href="<?= Url::to(['ekipman/analizor-index']) ?>" class="btn btn-sm btn-outline-warning">⚙ Yönet</a>
                <?php endif; ?>
            </div>

            <?php if (!$aktifConfig): ?>
                <div class="alert alert-warning">Lütfen bir analizör seçiniz.</div>
            <?php else: ?>
                <!-- Başlık -->
                <div class="d-flex align-items-center mb-3">
                    <h5 class="mb-0" style="color:#e94560">⚡ Enerji Analizörü - ENTES MPR-45S-V2</h5>
                    <span class="ml-3 badge" id="analizorStatus" style="font-size:0.9em; background:#6c757d; color:#fff">Bekleniyor...</span>
                    <span class="ml-2 text-muted small" id="analizorTime"></span>
                </div>
                <div class="text-muted small mb-2">
                    IP: <?= Html::encode($aktifConfig['ip']) ?>:<?= Html::encode($aktifConfig['port']) ?>
                    | Device ID: <?= Html::encode($aktifConfig['device_id']) ?>
                    | <?= Html::encode($aktifConfig['aciklama']) ?>
                </div>

                <!-- Kartlar -->
                <div class="row" id="analizorCards">
                    <!-- Gerilim -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white py-2"><strong>GERİLİM</strong></div>
                            <div class="card-body p-2">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td class="text-muted">V L1-N</td><td class="text-right font-weight-bold" id="az_V_L1N">---</td><td class="text-muted">V</td></tr>
                                    <tr><td class="text-muted">V L2-N</td><td class="text-right font-weight-bold" id="az_V_L2N">---</td><td class="text-muted">V</td></tr>
                                    <tr><td class="text-muted">V L3-N</td><td class="text-right font-weight-bold" id="az_V_L3N">---</td><td class="text-muted">V</td></tr>
                                    <tr class="border-top"><td class="text-muted">V L1-L2</td><td class="text-right font-weight-bold" id="az_V_L1L2">---</td><td class="text-muted">V</td></tr>
                                    <tr><td class="text-muted">V L2-L3</td><td class="text-right font-weight-bold" id="az_V_L2L3">---</td><td class="text-muted">V</td></tr>
                                    <tr><td class="text-muted">V L3-L1</td><td class="text-right font-weight-bold" id="az_V_L3L1">---</td><td class="text-muted">V</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Güç -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white py-2"><strong>GÜÇ</strong></div>
                            <div class="card-body p-2">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td class="text-muted">P L1</td><td class="text-right font-weight-bold" id="az_P_L1">---</td><td class="text-muted">kW</td></tr>
                                    <tr><td class="text-muted">P L2</td><td class="text-right font-weight-bold" id="az_P_L2">---</td><td class="text-muted">kW</td></tr>
                                    <tr><td class="text-muted">P L3</td><td class="text-right font-weight-bold" id="az_P_L3">---</td><td class="text-muted">kW</td></tr>
                                    <tr class="border-top"><td class="text-muted"><strong>P Toplam</strong></td><td class="text-right font-weight-bold text-success" id="az_P_total_kW" style="font-size:1.2em">---</td><td class="text-muted">kW</td></tr>
                                    <tr><td class="text-muted">S Toplam</td><td class="text-right font-weight-bold" id="az_S_total_kVA">---</td><td class="text-muted">kVA</td></tr>
                                    <tr><td class="text-muted">Q Toplam</td><td class="text-right font-weight-bold" id="az_Q_total_kVAR">---</td><td class="text-muted">kVAR</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Diğer -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white py-2"><strong>DİĞER</strong></div>
                            <div class="card-body p-2">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td class="text-muted">Frekans</td><td class="text-right font-weight-bold" id="az_Freq">---</td><td class="text-muted">Hz</td></tr>
                                    <tr><td class="text-muted">I Ortalama</td><td class="text-right font-weight-bold" id="az_I_avg_A">---</td><td class="text-muted">A</td></tr>
                                    <tr><td class="text-muted">I Nötr</td><td class="text-right font-weight-bold" id="az_I_N">---</td><td class="text-muted">mA</td></tr>
                                    <tr class="border-top"><td class="text-muted">PF L1</td><td class="text-right font-weight-bold" id="az_PF_L1">---</td><td></td></tr>
                                    <tr><td class="text-muted">PF L2</td><td class="text-right font-weight-bold" id="az_PF_L2">---</td><td></td></tr>
                                    <tr><td class="text-muted">PF L3</td><td class="text-right font-weight-bold" id="az_PF_L3">---</td><td></td></tr>
                                    <tr><td class="text-muted"><strong>PF Ort.</strong></td><td class="text-right font-weight-bold text-info" id="az_PF_avg" style="font-size:1.2em">---</td><td></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enerji Sayacı -->
                <div class="row">
                    <div class="col-md-10 mb-3">
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark py-2"><strong>⚡ ENERJİ SAYACI</strong></div>
                            <div class="card-body p-2">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><td class="text-muted"><strong>Toplam Tüketim</strong></td><td class="text-right font-weight-bold text-warning" id="az_E_import_total_kWh" style="font-size:1.3em">---</td><td class="text-muted">kWh</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div><!-- /entes-wrapper -->
        </div>
    </div>

    <!-- Alt Satır: Tüketim Grafiği + Gerilim + Kademe -->
    <div class="row mt-3">
        <!-- Tüketim Grafiği -->
        <div class="col-lg-7 mb-3">
            <div class="sog5-panel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted small"><strong>Tüketim Grafiği</strong></div>
                    <div class="d-flex gap-2" style="font-size:10px;">
                        <span><span style="display:inline-block;width:10px;height:10px;background:#22c55e;border-radius:2px;"></span> Aktif</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#f59e0b;border-radius:2px;"></span> Endüktif</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#06b6d4;border-radius:2px;"></span> Kapasitif</span>
                    </div>
                </div>
                <ul class="nav nav-tabs mb-2" id="chartTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active small py-1 px-2" id="chart-hourly-tab" data-bs-toggle="tab" data-bs-target="#chart-hourly-pane" type="button" role="tab">Saatlik</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small py-1 px-2" id="chart-daily-tab" data-bs-toggle="tab" data-bs-target="#chart-daily-pane" type="button" role="tab">Günlük</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small py-1 px-2" id="chart-monthly-tab" data-bs-toggle="tab" data-bs-target="#chart-monthly-pane" type="button" role="tab">Aylık</button>
                    </li>
                </ul>
                <div class="tab-content" id="chartTabContent">
                    <div class="tab-pane fade show active" id="chart-hourly-pane" role="tabpanel">
                        <div id="chart-hourly-container" style="height:200px;display:flex;align-items:flex-end;gap:4px;padding:10px 0;">
                            <div class="chart-bars" style="display:flex;align-items:flex-end;gap:3px;height:160px;width:100%;"></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="chart-daily-pane" role="tabpanel">
                        <div id="chart-daily-container" style="height:200px;display:flex;align-items:flex-end;gap:4px;padding:10px 0;">
                            <div class="chart-bars" style="display:flex;align-items:flex-end;gap:3px;height:160px;width:100%;"></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="chart-monthly-pane" role="tabpanel">
                        <div id="chart-monthly-container" style="height:200px;display:flex;align-items:flex-end;gap:4px;padding:10px 0;">
                            <div class="chart-bars" style="display:flex;align-items:flex-end;gap:3px;height:160px;width:100%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gerilim + Kademe Durumları -->
        <div class="col-lg-5 mb-3">
            <div class="sog5-panel">
                <!-- Gerilim -->
                <div class="sog5-status-card mb-3">
                    <div class="text-muted small mb-2"><strong>Gerilim</strong></div>
                    <div class="row">
                        <div class="col-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted">V L1-N</td><td class="text-right font-weight-bold" id="sog5-v-l1n">---</td><td class="text-muted">V</td></tr>
                                <tr><td class="text-muted">V L2-N</td><td class="text-right font-weight-bold" id="sog5-v-l2n">---</td><td class="text-muted">V</td></tr>
                                <tr><td class="text-muted">V L3-N</td><td class="text-right font-weight-bold" id="sog5-v-l3n">---</td><td class="text-muted">V</td></tr>
                            </table>
                        </div>
                        <div class="col-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted">V L1-L2</td><td class="text-right font-weight-bold" id="sog5-v-l1l2">---</td><td class="text-muted">V</td></tr>
                                <tr><td class="text-muted">V L2-L3</td><td class="text-right font-weight-bold" id="sog5-v-l2l3">---</td><td class="text-muted">V</td></tr>
                                <tr><td class="text-muted">V L3-L1</td><td class="text-right font-weight-bold" id="sog5-v-l3l1">---</td><td class="text-muted">V</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Kademe Durumları -->
                <div class="sog5-status-card">
                    <div class="text-muted small mb-2 text-center"><strong>Kademe Durumları</strong></div>
                    <div id="sog5-steps" class="sog5-steps-horizontal">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <div class="sog5-step-item">
                            <span class="sog5-step-dot sog5-dot-off" id="step-dot-<?= $i ?>"></span>
                            <div class="step-num">K<?= $i ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var sog5Polling = false;
var sog5Timer = null;
var sog5Interval = 30000;
var sog5ApiUrl = <?= json_encode(Url::to(['/ekipman/sog5-veri'])) ?>;

var analizorPolling = false;
var analizorTimer = null;
var pollInterval = 30000;
var currentId = <?= json_encode($aktifId) ?>;
var analizorPageUrlTemplate = <?= json_encode(Url::to(['site/energy', 'id' => '__ID__'])) ?>;
var analizorApiUrlTemplate = <?= json_encode(Url::to(['/ekipman/analizor-veri', 'id' => '__ID__'])) ?>;
var analizorApiUrl = currentId ? analizorApiUrlTemplate.replace('__ID__', encodeURIComponent(currentId)) : null;
var sog5GrafikUrlTemplate = <?= json_encode(Url::to(['/ekipman/sog5-grafik', 'type' => '__TYPE__'])) ?>;

var tuketimPolling = false;
var tuketimTimer = null;

window.sog5Poll = function() {
    if (!sog5Polling) return;
    document.getElementById('sog5-status-badge').style.background = '#ffc107';
    document.getElementById('sog5-status-badge').textContent = 'Okunuyor...';

    fetch(sog5ApiUrl)
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (!sog5Polling) return;
            if (!json.success) {
                document.getElementById('sog5-status-badge').style.background = '#dc3545';
                document.getElementById('sog5-status-badge').textContent = 'Hata';
                sog5Timer = setTimeout(sog5Poll, sog5Interval * 2);
                return;
            }

            var d = json.data;
            document.getElementById('sog5-status-badge').style.background = '#28a745';
            document.getElementById('sog5-status-badge').textContent = 'Bağlı';
            document.getElementById('sog5-last-update').textContent = d.timestamp || new Date().toLocaleTimeString('tr-TR');

            for (var i = 1; i <= 12; i++) {
                var on = d['step_' + i] === true || d['step_' + i] === 1;
                var dot = document.getElementById('step-dot-' + i);
                if (dot) dot.className = 'sog5-step-dot ' + (on ? 'sog5-dot-on' : 'sog5-dot-off');
            }

            var maxP = 500;
            var maxQ = 50;
            var p = 0;
            if (d.p_total_kw !== null && d.p_total_kw !== undefined) {
                p = parseFloat(d.p_total_kw);
                document.getElementById('sog5-bar-p').style.width = Math.min(100, p / maxP * 100) + '%';
                document.getElementById('sog5-p-val').textContent = p.toFixed(1) + ' kW';
            }
            if (d.compensation_inductive_kvar !== null) {
                var qind = parseFloat(d.compensation_inductive_kvar);
                document.getElementById('sog5-bar-qind').style.width = Math.min(100, qind / maxQ * 100) + '%';
                document.getElementById('sog5-qind-val').textContent = qind.toFixed(1) + ' kVAr';
                document.getElementById('sog5-qind-pct').textContent = (p > 0 ? (qind / p * 100).toFixed(1) : '0.0') + '%P';
            }
            if (d.compensation_capacitive_kvar !== null) {
                var qcap = parseFloat(d.compensation_capacitive_kvar);
                document.getElementById('sog5-bar-qcap').style.width = Math.min(100, qcap / maxQ * 100) + '%';
                document.getElementById('sog5-qcap-val').textContent = qcap.toFixed(1) + ' kVAr';
                document.getElementById('sog5-qcap-pct').textContent = (p > 0 ? (qcap / p * 100).toFixed(1) : '0.0') + '%P';
            }
            if (d.v_l1_v !== null) document.getElementById('sog5-v-l1n').textContent = parseFloat(d.v_l1_v).toFixed(1);
            if (d.v_l2_v !== null) document.getElementById('sog5-v-l2n').textContent = parseFloat(d.v_l2_v).toFixed(1);
            if (d.v_l3_v !== null) document.getElementById('sog5-v-l3n').textContent = parseFloat(d.v_l3_v).toFixed(1);
            if (d.v_l1_l2_v !== null) document.getElementById('sog5-v-l1l2').textContent = parseFloat(d.v_l1_l2_v).toFixed(1);
            if (d.v_l2_l3_v !== null) document.getElementById('sog5-v-l2l3').textContent = parseFloat(d.v_l2_l3_v).toFixed(1);
            if (d.v_l3_l1_v !== null) document.getElementById('sog5-v-l3l1').textContent = parseFloat(d.v_l3_l1_v).toFixed(1);
            if (d.cosfi !== null) document.getElementById('sog5-cosfi').textContent = parseFloat(d.cosfi).toFixed(2);
            if (d.frequency_hz !== null) document.getElementById('sog5-freq').textContent = parseFloat(d.frequency_hz).toFixed(1) + ' Hz';

            sog5Timer = setTimeout(sog5Poll, sog5Interval);
        })
        .catch(function(e) {
            if (!sog5Polling) return;
            document.getElementById('sog5-status-badge').style.background = '#dc3545';
            document.getElementById('sog5-status-badge').textContent = 'Hata';
            sog5Timer = setTimeout(sog5Poll, sog5Interval * 2);
        });
}

window.analizorPoll = function() {
    if (!analizorPolling || !analizorApiUrl) return;
    document.getElementById('analizorStatus').style.background = '#ffc107';
    document.getElementById('analizorStatus').textContent = 'Okunuyor...';

    fetch(analizorApiUrl)
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (!analizorPolling) return;
            if (json.success) {
                document.getElementById('analizorStatus').style.background = '#28a745';
                document.getElementById('analizorStatus').textContent = 'Bağlı';
                var d = json.data;
                var fields = {
                    'V_L1N': 1, 'V_L2N': 1, 'V_L3N': 1,
                    'V_L1L2': 1, 'V_L2L3': 1, 'V_L3L1': 1,
                    'P_L1': 1, 'P_L2': 1, 'P_L3': 1,
                    'P_total_kW': 2, 'S_total_kVA': 2, 'Q_total_kVAR': 2,
                    'I_avg_A': 1, 'I_N': 0,
                    'PF_L1': 3, 'PF_L2': 3, 'PF_L3': 3, 'PF_avg': 3,
                    'Freq': 2,
                    'E_import_total_kWh': 1,
                };
                var kWconvert = ['P_L1', 'P_L2', 'P_L3'];
                var numFormat = function(v, decimals) {
                    if (decimals === 0) return Math.round(v).toLocaleString('tr-TR');
                    return v.toLocaleString('tr-TR', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
                };
                Object.keys(fields).forEach(function(k) {
                    var el = document.getElementById('az_' + k);
                    if (el && d[k] !== null && d[k] !== undefined) {
                        var v = parseFloat(d[k]);
                        if (!isNaN(v)) {
                            if (kWconvert.indexOf(k) !== -1) { v = v / 1000; }
                            el.textContent = numFormat(v, fields[k]);
                        }
                    }
                });
                document.getElementById('analizorTime').textContent = 'Son: ' + d.timestamp;
                analizorTimer = setTimeout(window.analizorPoll, pollInterval);
            } else {
                document.getElementById('analizorStatus').style.background = '#dc3545';
                document.getElementById('analizorStatus').textContent = 'Hata';
                document.getElementById('analizorTime').textContent = json.message || '';
                analizorTimer = setTimeout(window.analizorPoll, pollInterval * 2);
            }
        })
        .catch(function(e) {
            if (!analizorPolling) return;
            document.getElementById('analizorStatus').style.background = '#dc3545';
            document.getElementById('analizorStatus').textContent = 'Bağlantı Hatası';
            analizorTimer = setTimeout(window.analizorPoll, pollInterval * 2);
        });
}

function sog5TuketimPoll() {
    if (!tuketimPolling) return;

    fetch(<?= json_encode(Url::to(['/ekipman/sog5-tuketim'])) ?>)
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (!tuketimPolling) return;
            if (json.success && json.data) {
                var d = json.data;
                
                if (d.hourly && d.hourly.e_kwh !== null) {
                    document.getElementById('sog5-e-hourly').textContent = d.hourly.e_kwh;
                }
                if (d.daily && d.daily.e_kwh !== null) {
                    document.getElementById('sog5-e-daily').textContent = d.daily.e_kwh;
                }
                if (d.monthly && d.monthly.e_kwh !== null) {
                    document.getElementById('sog5-e-monthly').textContent = d.monthly.e_kwh;
                }
                
                if (d.hourly && d.hourly.q_ind_total !== null) {
                    document.getElementById('sog5-qind-hourly').textContent = d.hourly.q_ind_total;
                    document.getElementById('sog5-qind-oran-hourly').textContent = d.hourly.q_ind_oran !== null ? '(' + d.hourly.q_ind_oran + '%)' : '';
                }
                if (d.daily && d.daily.q_ind_total !== null) {
                    document.getElementById('sog5-qind-daily').textContent = d.daily.q_ind_total;
                    document.getElementById('sog5-qind-oran-daily').textContent = d.daily.q_ind_oran !== null ? '(' + d.daily.q_ind_oran + '%)' : '';
                }
                if (d.monthly && d.monthly.q_ind_total !== null) {
                    document.getElementById('sog5-qind-monthly').textContent = d.monthly.q_ind_total;
                    document.getElementById('sog5-qind-oran-monthly').textContent = d.monthly.q_ind_oran !== null ? '(' + d.monthly.q_ind_oran + '%)' : '';
                }
                
                if (d.hourly && d.hourly.q_cap_total !== null) {
                    document.getElementById('sog5-qcap-hourly').textContent = d.hourly.q_cap_total;
                    document.getElementById('sog5-qcap-oran-hourly').textContent = d.hourly.q_cap_oran !== null ? '(' + d.hourly.q_cap_oran + '%)' : '';
                }
                if (d.daily && d.daily.q_cap_total !== null) {
                    document.getElementById('sog5-qcap-daily').textContent = d.daily.q_cap_total;
                    document.getElementById('sog5-qcap-oran-daily').textContent = d.daily.q_cap_oran !== null ? '(' + d.daily.q_cap_oran + '%)' : '';
                }
                if (d.monthly && d.monthly.q_cap_total !== null) {
                    document.getElementById('sog5-qcap-monthly').textContent = d.monthly.q_cap_total;
                    document.getElementById('sog5-qcap-oran-monthly').textContent = d.monthly.q_cap_oran !== null ? '(' + d.monthly.q_cap_oran + '%)' : '';
                }
            }
            tuketimTimer = setTimeout(sog5TuketimPoll, 60000);
        })
        .catch(function(e) {
            tuketimTimer = setTimeout(sog5TuketimPoll, 120000);
        });
}

function loadGrafik(type) {
    var containerId = type === 'hourly' ? 'chart-hourly-container' : (type === 'daily' ? 'chart-daily-container' : 'chart-monthly-container');
    
    fetch(sog5GrafikUrlTemplate.replace('__TYPE__', encodeURIComponent(type)))
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.success && json.data) {
                var d = json.data;
                var container = document.getElementById(containerId);
                if (!container) return;
                
                var barsContainer = container.querySelector('.chart-bars');
                if (!barsContainer) return;
                
                var maxVal = 0;
                for (var i = 0; i < d.aktif.length; i++) {
                    if (d.aktif[i] > maxVal) maxVal = d.aktif[i];
                    if (d.qind[i] > maxVal) maxVal = d.qind[i];
                    if (d.qcap[i] > maxVal) maxVal = d.qcap[i];
                }
                if (maxVal === 0) maxVal = 1;
                
                barsContainer.innerHTML = '';
                
                for (var i = 0; i < d.labels.length; i++) {
                    var eVal = d.aktif[i] || 0;
                    var qindVal = d.qind[i] || 0;
                    var qcapVal = d.qcap[i] || 0;
                    
                    var group = document.createElement('div');
                    group.style.cssText = 'flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;min-width:20px;';
                    
                    var stack = document.createElement('div');
                    stack.style.cssText = 'display:flex;align-items:flex-end;gap:1px;height:150px;width:100%;';
                    
                    var eH = (eVal / maxVal * 100);
                    var eBar = document.createElement('div');
                    eBar.style.cssText = 'flex:1;background:#22c55e;min-height:2px;border-radius:2px 2px 0 0;height:' + eH + '%;';
                    
                    var qindH = (qindVal / maxVal * 100);
                    var qindBar = document.createElement('div');
                    qindBar.style.cssText = 'flex:1;background:#f59e0b;min-height:2px;border-radius:2px 2px 0 0;height:' + qindH + '%;';
                    
                    var qcapH = (qcapVal / maxVal * 100);
                    var qcapBar = document.createElement('div');
                    qcapBar.style.cssText = 'flex:1;background:#06b6d4;min-height:2px;border-radius:2px 2px 0 0;height:' + qcapH + '%;';
                    
                    stack.appendChild(eBar);
                    stack.appendChild(qindBar);
                    stack.appendChild(qcapBar);
                    
                    var label = document.createElement('div');
                    label.style.cssText = 'font-size:9px;color:#9ca3af;text-align:center;';
                    label.textContent = d.labels[i];
                    
                    group.appendChild(stack);
                    group.appendChild(label);
                    barsContainer.appendChild(group);
                }
            }
        });
}

document.querySelectorAll('#chartTab button').forEach(function(btn) {
    btn.addEventListener('shown.bs.tab', function() {
        var typeMap = { 
            'chart-hourly-tab': 'hourly', 
            'chart-daily-tab': 'daily', 
            'chart-monthly-tab': 'monthly' 
        };
        var type = typeMap[this.id] || 'hourly';
        loadGrafik(type);
    });
});

var analizorSelect = document.getElementById('analizorSelect');
if (analizorSelect) {
    analizorSelect.addEventListener('change', function() {
        if (this.value) {
            window.location.href = analizorPageUrlTemplate.replace('__ID__', encodeURIComponent(this.value));
        }
    });
}

window.onload = function() {
    sog5Polling = true;
    sog5Poll();
    
    analizorPolling = true;
    analizorPoll();
    
    tuketimPolling = true;
    sog5TuketimPoll();
    
    loadGrafik('hourly');
};
</script>
