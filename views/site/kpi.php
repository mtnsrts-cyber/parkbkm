<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $summary */
/** @var array $arizaDurumDagilim */
/** @var array $planliPeriyotDagilim */
/** @var array $planliPeriyotDagilim30 */
/** @var array $planliDurumDagilim90 */
/** @var array $bakimTakipDagilim */
/** @var int $periyodikGecikmisAdet */
/** @var int $periyodikYaklasan90Adet */

$this->title = 'KPI Paneli';
$this->params['breadcrumbs'][] = ['label' => 'Anasayfa', 'url' => ['/site/index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-kpi">
    <style>
        .site-kpi a.text-decoration-none { color: #fff !important; }
        .site-kpi a.text-decoration-none:hover { opacity: .8; }
        .site-kpi a.text-warning { color: #ffc107 !important; }
        .site-kpi a.text-danger { color: #dc3545 !important; }
        .site-kpi a.text-info { color: #0dcaf0 !important; }
        .site-kpi a.text-success { color: #198754 !important; }
    </style>
    <div class="mb-3">
        <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body p-3">
                    <div class="small text-muted">Toplam Ekipman</div>
                    <div class="h3 mb-0"><?= Html::a((string)(int)($summary['toplamEkipman'] ?? 0), ['/ekipman/index'], ['class' => 'text-white text-decoration-none']) ?></div>
                    <div class="small text-muted">Aktif: <?= Html::a((string)(int)($summary['aktifEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'AKTIF'], ['class' => 'text-decoration-none']) ?> · Hurda: <?= Html::a((string)(int)($summary['hurdaEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'HURDA'], ['class' => 'text-warning text-decoration-none']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body p-3">
                    <div class="small text-muted">Arıza</div>
                    <div class="h3 mb-0 text-danger"><?= Html::a((string)(int)($summary['acikAriza'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'open'], ['class' => 'text-danger text-decoration-none']) ?></div>
                    <div class="small text-muted">Açık · Toplam: <?= Html::a((string)(int)($summary['toplamAriza'] ?? 0), ['/ariza-takip/index'], ['class' => 'text-decoration-none']) ?></div>
                    <div class="small text-muted">Bu Ay: <?= Html::a((string)(int)($summary['buAyAriza'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'this-month'], ['class' => 'text-decoration-none']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body p-3">
                    <div class="small text-muted">Bakım</div>
                    <div class="h3 mb-0 text-info"><?= Html::a((string)(int)($summary['buAyBakim'] ?? 0), ['/bakim-takip/index', 'BakimTakipSearch[quickFilter]' => 'this-month'], ['class' => 'text-info text-decoration-none']) ?></div>
                    <div class="small text-muted">Bu Ay · Toplam: <?= Html::a((string)(int)($summary['toplamBakim'] ?? 0), ['/bakim-takip/index'], ['class' => 'text-decoration-none']) ?></div>
                    <div class="small text-muted">Planlı (10 gün): <?= Html::a((string)(int)($summary['planliYaklasan10'] ?? 0), ['/site/index', '#' => 'home-planli-grid'], ['class' => 'text-decoration-none']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-body p-3">
                    <div class="small text-muted">Arıza Maliyeti</div>
                    <div class="h4 mb-0 text-success"><?= Html::a(number_format((float)($summary['buAyMaliyet'] ?? 0), 2, ',', '.') . ' ₺', ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'this-month'], ['class' => 'text-success text-decoration-none']) ?></div>
                    <div class="small text-muted">Bu Ay</div>
                    <div class="small text-muted">Toplam: <?= Html::a(number_format((float)($summary['toplamMaliyet'] ?? 0), 2, ',', '.') . ' ₺', ['/ariza-takip/index'], ['class' => 'text-decoration-none']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-header">Arıza Son Durum Dağılımı</div>
                <div class="card-body">
                    <?php if (empty($arizaDurumDagilim)): ?>
                        <div class="text-muted small">Kayıt bulunamadı.</div>
                    <?php else: ?>
                        <?php $maxAriza = max(array_map(fn($x) => (int)$x['adet'], $arizaDurumDagilim)); ?>
                        <?php foreach ($arizaDurumDagilim as $row): ?>
                            <?php
                            $adet = (int)$row['adet'];
                            $oran = $maxAriza > 0 ? (int)round(($adet * 100) / $maxAriza) : 0;
                            $durumRaw = trim((string)$row['ARIZANIN_SON_DURUMU']);
                            $durum = $durumRaw !== '' ? $durumRaw : 'Belirtilmemiş';
                            $arizaUrl = $durumRaw !== ''
                                ? ['/ariza-takip/index', 'ArizaTakipSearch[ARIZANIN_SON_DURUMU]' => $durumRaw]
                                : ['/ariza-takip/index'];
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= Html::a(Html::encode($durum), $arizaUrl, ['class' => 'text-decoration-none']) ?></span>
                                    <span><?= Html::a((string)$adet, $arizaUrl, ['class' => 'text-decoration-none']) ?></span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $oran ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <div class="col-12 col-lg-6">
            <div class="card bg-dark border-secondary">
                <div class="card-header">Periyodik Kontrol Takibi</div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div class="border border-warning rounded p-3 h-100">
                                <div class="small text-muted">Gecikmiş Kontroller</div>
                                <div class="h3 mb-0 text-warning">
                                    <?= Html::a((string)(int)($periyodikGecikmisAdet ?? 0), ['/site/periyodik-kontroller', 'quick' => 'gecikmis'], ['class' => 'text-warning text-decoration-none']) ?>
                                </div>
                                <div class="small text-muted">Listeye git</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border border-info rounded p-3 h-100">
                                <div class="small text-muted">Önümüzdeki 90 Gün</div>
                                <div class="h3 mb-0 text-info">
                                    <?= Html::a((string)(int)($periyodikYaklasan90Adet ?? 0), ['/site/periyodik-kontroller', 'quick' => 'yaklasan-90'], ['class' => 'text-info text-decoration-none']) ?>
                                </div>
                                <div class="small text-muted">Listeye git</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-header">Planlı Bakım Periyot Dağılımı (Önümüzdeki 30 Gün)</div>
                <div class="card-body">
                    <?php if (empty($planliPeriyotDagilim30)): ?>
                        <div class="text-muted small">Önümüzdeki 30 gün için kayıt bulunamadı.</div>
                    <?php else: ?>
                        <?php $maxPeriyot30 = max(array_map(fn($x) => (int)$x['adet'], $planliPeriyotDagilim30)); ?>
                        <?php foreach ($planliPeriyotDagilim30 as $row): ?>
                            <?php
                            $adet = (int)$row['adet'];
                            $oran = $maxPeriyot30 > 0 ? (int)round(($adet * 100) / $maxPeriyot30) : 0;
                            $periyotRaw = trim((string)$row['periyodu']);
                            $periyot = $periyotRaw !== '' ? $periyotRaw : 'Belirtilmemiş';
                            $planliUrl = $periyotRaw !== ''
                                ? ['/planli-bakim/index', 'PlanliBakimSearch[periyodu]' => $periyotRaw]
                                : ['/planli-bakim/index'];
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= Html::a(Html::encode($periyot), $planliUrl, ['class' => 'text-decoration-none']) ?></span>
                                    <span><?= Html::a((string)$adet, $planliUrl, ['class' => 'text-decoration-none']) ?></span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= $oran ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-header">Planlı Bakım Periyot Dağılımı</div>
                <div class="card-body">
                    <?php if (empty($planliPeriyotDagilim)): ?>
                        <div class="text-muted small">Kayıt bulunamadı.</div>
                    <?php else: ?>
                        <?php $maxPeriyot = max(array_map(fn($x) => (int)$x['adet'], $planliPeriyotDagilim)); ?>
                        <?php foreach ($planliPeriyotDagilim as $row): ?>
                            <?php
                            $adet = (int)$row['adet'];
                            $oran = $maxPeriyot > 0 ? (int)round(($adet * 100) / $maxPeriyot) : 0;
                            $periyotRaw = trim((string)$row['periyodu']);
                            $periyot = $periyotRaw !== '' ? $periyotRaw : 'Belirtilmemiş';
                            $planliUrl = $periyotRaw !== ''
                                ? ['/planli-bakim/index', 'PlanliBakimSearch[periyodu]' => $periyotRaw]
                                : ['/planli-bakim/index'];
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= Html::a(Html::encode($periyot), $planliUrl, ['class' => 'text-decoration-none']) ?></span>
                                    <span><?= Html::a((string)$adet, $planliUrl, ['class' => 'text-decoration-none']) ?></span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= $oran ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-header">Planlı Bakım Durum Dağılımı (Son 90 Gün)</div>
                <div class="card-body">
                    <?php if (empty($planliDurumDagilim90)): ?>
                        <div class="text-muted small">Son 90 günde kayıt bulunamadı.</div>
                    <?php else: ?>
                        <?php
                        $toplamPlanli90 = array_sum(array_map(fn($x) => (int)$x['adet'], $planliDurumDagilim90));
                        $maxPlanli90 = max(array_map(fn($x) => (int)$x['adet'], $planliDurumDagilim90));
                        $durumRenk = [
                            'plan_dahilinde' => '#198754',
                            'plan_oncesi' => '#0bf536',
                            'plan_sonrasi' => '#ff1c07',
                            'ilk_bakim' => '#2563eb',
                            'otelendi' => '#6c757d',
                            'otelenmis_plan_dahilinde' => '#6610f2',
                            'otelenmis_plan_sonrasi' => '#dc3545',
                            'varsayilan' => '#20c997',
                        ];
                        ?>
                        <div class="small text-muted mb-2">Toplam: <?= Html::a($toplamPlanli90 . ' kayıt', ['/planli-bakim/index'], ['class' => 'text-decoration-none']) ?></div>
                        <?php foreach ($planliDurumDagilim90 as $row): ?>
                            <?php
                            $adet = (int)$row['adet'];
                            $oran = $maxPlanli90 > 0 ? (int)round(($adet * 100) / $maxPlanli90) : 0;
                            $oranGoster = $adet > 0 ? max($oran, 8) : 0;
                            $durumRaw = trim((string)$row['durumu']);
                            $durum = $durumRaw !== '' ? $durumRaw : 'Belirtilmemiş';
                            $yuzde = $toplamPlanli90 > 0 ? round(($adet * 100) / $toplamPlanli90, 1) : 0;
                            $durumKey = mb_strtolower(strtr($durumRaw, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
                            $durumKey = strtr($durumKey, ['ö' => 'o', 'ü' => 'u', 'ş' => 's', 'ğ' => 'g', 'ç' => 'c', 'ı' => 'i']);
                            $barColor = $durumRenk['varsayilan'];

                            if (str_contains($durumKey, 'otelenmis') && str_contains($durumKey, 'plan dahilinde')) {
                                $barColor = $durumRenk['otelenmis_plan_dahilinde'];
                            } elseif (str_contains($durumKey, 'otelenmis') && str_contains($durumKey, 'plan sonrasi')) {
                                $barColor = $durumRenk['otelenmis_plan_sonrasi'];
                            } elseif (str_contains($durumKey, 'ilk bak')) {
                                $barColor = $durumRenk['ilk_bakim'];
                            } elseif (str_contains($durumKey, 'plan dahilinde')) {
                                $barColor = $durumRenk['plan_dahilinde'];
                            } elseif (str_contains($durumKey, 'plan oncesi')) {
                                $barColor = $durumRenk['plan_oncesi'];
                            } elseif (str_contains($durumKey, 'plan sonrasi')) {
                                $barColor = $durumRenk['plan_sonrasi'];
                            } elseif (str_contains($durumKey, 'otelendi')) {
                                $barColor = $durumRenk['otelendi'];
                            }
                            $planliDurumUrl = $durumRaw !== ''
                                ? ['/planli-bakim/index', 'PlanliBakimSearch[durumu]' => $durumRaw]
                                : ['/planli-bakim/index'];
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= Html::a(Html::encode($durum), $planliDurumUrl, ['class' => 'text-decoration-none']) ?></span>
                                    <span><?= Html::a($adet, $planliDurumUrl, ['class' => 'text-decoration-none']) ?> <span class="text-muted">(<?= $yuzde ?>%)</span></span>
                                </div>
                                <div class="progress" style="height: 10px; background-color: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.15);">
                                    <div role="progressbar" style="width: <?= $oranGoster ?>%; height: 100%; background: <?= $barColor ?>; opacity: 1; border-radius: .25rem;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card bg-dark border-secondary h-100">
                <div class="card-header">Bakım Takip Dağılımı (Son 90 Gün)</div>
                <div class="card-body">
                    <?php if (empty($bakimTakipDagilim)): ?>
                        <div class="text-muted small">Son 90 günde kayıt bulunamadı.</div>
                    <?php else: ?>
                        <?php $maxBakim = max(array_map(fn($x) => (int)$x['adet'], $bakimTakipDagilim)); ?>
                        <?php foreach ($bakimTakipDagilim as $row): ?>
                            <?php
                            $adet = (int)$row['adet'];
                            $oran = $maxBakim > 0 ? (int)round(($adet * 100) / $maxBakim) : 0;
                            $bakimGenelRaw = trim((string)$row['BAKIM_GENEL']);
                            $bakimGenel = $bakimGenelRaw !== '' ? $bakimGenelRaw : 'Belirtilmemiş';
                            $bakimUrl = $bakimGenelRaw !== ''
                                ? ['/bakim-takip/index', 'BakimTakipSearch[BAKIM_GENEL]' => $bakimGenelRaw]
                                : ['/bakim-takip/index'];
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= Html::a(Html::encode($bakimGenel), $bakimUrl, ['class' => 'text-decoration-none']) ?></span>
                                    <span><?= Html::a((string)$adet, $bakimUrl, ['class' => 'text-decoration-none']) ?></span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $oran ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
