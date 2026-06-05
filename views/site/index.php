<?php
/** @var yii\web\View $this */
$this->title = 'ParkBkm - Bakım Portalı';

use yii\helpers\Html;
use yii\grid\GridView;
use yii\grid\CheckboxColumn;
use yii\helpers\ArrayHelper;
use app\models\User;

/** @var array $summary */

$canTopluBakimIsle = !Yii::$app->user->isGuest
    && in_array((string)(Yii::$app->user->identity->role ?? ''), ['admin', 'editor'], true);

$bakimYapanlarOptions = [];
$topluBakimGunleri = [];
$homePlanliModels = $dataProvider->getModels();
$today = new \DateTime('today');
$tomorrow = (clone $today)->modify('+1 day');
$weekEnd = (clone $today)->modify('+7 days');
$planliYarin = 0;
$planliBuHafta = 0;

foreach ($homePlanliModels as $item) {
    try {
        $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
    } catch (\Exception $e) {
        continue;
    }

    if ($sonrakiTarih->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
        $planliYarin++;
    }
    if ($sonrakiTarih > $today && $sonrakiTarih <= $weekEnd) {
        $planliBuHafta++;
    }
}

$criticalActions = [
    [
        'label' => 'Gecikmiş planlı bakım',
        'value' => (int)($summary['planliGecikmis'] ?? 0),
        'url' => ['/site/index', 'quick' => 'planli-gecikmis', '#' => 'home-planli-grid'],
        'class' => 'danger',
    ],
    [
        'label' => 'Bugün son gün',
        'value' => (int)($summary['planliSonGun'] ?? 0),
        'url' => ['/site/index', 'quick' => 'planli-son-gun', '#' => 'home-planli-grid'],
        'class' => 'warning',
    ],
    [
        'label' => 'Yarın yapılacak',
        'value' => $planliYarin,
        'url' => ['/site/index', 'quick' => 'planli-yarin', '#' => 'home-planli-grid'],
        'class' => 'info',
    ],
    [
        'label' => '7 gün içinde',
        'value' => $planliBuHafta,
        'url' => ['/site/index', 'quick' => 'planli-7-gun', '#' => 'home-planli-grid'],
        'class' => 'success',
    ],
];
$planliActionFilters = ['planli-gecikmis', 'planli-son-gun', 'planli-yarin', 'planli-7-gun'];
$isPlanliPanelOpen = in_array((string)($quickFilter ?? ''), $planliActionFilters, true);
$bakimFaaliyet = $summary['bakimFaaliyet'] ?? [];
$activityUrl = static function (?string $kind = null, ?string $from = null, ?string $to = null, ?string $period = null): array {
    $params = ['/bakim-takip/index'];

    if ($from !== null) {
        $params['BakimTakipSearch[TARIH_from]'] = $from;
    }
    if ($to !== null) {
        $params['BakimTakipSearch[TARIH_to]'] = $to;
    }
    if ($kind !== null) {
        $params['BakimTakipSearch[activityKind]'] = $kind;
    }
    if ($period !== null && $period !== '') {
        $params['BakimTakipSearch[planliPeriod]'] = $period;
    }

    return $params;
};
$activityCountLink = static function (int $count, array $url, string $tone = 'warning', string $class = ''): string {
    return Html::a((string)$count, $url, ['class' => trim('home-activity-count home-count-' . $tone . ' ' . $class)]);
};
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$yearStart = date('Y-01-01');
$yearEnd = date('Y-12-31');
$renderFaaliyetSection = static function (string $title, array $data, ?string $from, ?string $to, bool $showPeriods = false, string $class = '') use ($activityUrl, $activityCountLink): string {
    $rows = '<div class="home-activity-section ' . Html::encode($class) . '">';
    $rows .= '<div class="home-activity-title">' . Html::encode($title) . '</div>';
    $rows .= '<div class="home-activity-metrics">';
    $rows .= '<div class="home-activity-metric"><span>Genel iş</span>' . $activityCountLink((int)($data['general'] ?? 0), $activityUrl('general', $from, $to), 'success') . '</div>';

    if ($showPeriods) {
        foreach (($data['periods'] ?? []) as $period) {
            $rows .= '<div class="home-activity-metric"><span>Planlı ' . Html::encode($period['label'] ?? '') . '</span>'
                . $activityCountLink((int)($period['count'] ?? 0), $activityUrl('planli', $from, $to, (string)($period['period'] ?? '')), 'warning')
                . '</div>';
        }
    } else {
        $rows .= '<div class="home-activity-metric"><span>Planlı bakım</span>' . $activityCountLink((int)($data['planli'] ?? 0), $activityUrl('planli', $from, $to), 'warning') . '</div>';
    }

    $rows .= '<div class="home-activity-metric home-activity-total"><span>Toplam</span>' . $activityCountLink((int)($data['total'] ?? 0), $activityUrl(null, $from, $to), 'success', 'home-activity-total-link') . '</div>';
    $rows .= '</div>';
    $rows .= '</div>';

    return $rows;
};
$planliEmptyText = ($quickFilter ?? '') === 'planli-gecikmis'
    ? '<span class="text-muted small">Planlı bakımı gecikmiş ekipman bulunmuyor.</span>'
    : '<span class="text-muted small">Önümüzdeki 10 gün içinde planlı bakım bulunmuyor.</span>';
if ($canTopluBakimIsle) {
    $bakimYapanlarOptions = ArrayHelper::map(
        User::find()
            ->where(['in', 'role', ['user', 'editor']])
            ->orderBy(['username' => SORT_ASC])
            ->all(),
        'username',
        'username'
    );

    foreach ($homePlanliModels as $item) {
        if (!empty($item['sonraki_tarih'])) {
            $topluBakimGunleri[$item['sonraki_tarih']] = (new \DateTime($item['sonraki_tarih']))->format('d.m.Y');
        }
    }
}
?>

<div class="row g-2 mb-3 home-kpi-row">
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100 home-kpi-card home-kpi-neutral">
            <div class="card-body p-2">
                <div class="h6 mb-1 text-warning">Ekipman Envanteri</div>
                <div class="h4 mb-0"><?= Html::a((string)(int)($summary['toplamEkipman'] ?? 0), ['/ekipman/index'], ['class' => 'text-decoration-none home-count-success']) ?></div>
                <div class="small text-muted home-kpi-lines">
                    <div>Aktif: <?= Html::a((string)(int)($summary['aktifEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'AKTIF'], ['class' => 'text-decoration-none home-count-success']) ?></div>
                    <div>Kullanım dışı: <?= Html::a((string)(int)($summary['kullanimDisiEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'KULLANIM_DISI'], ['class' => 'text-decoration-none home-count-warning']) ?></div>
                    <div>Hurda: <?= Html::a((string)(int)($summary['hurdaEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'HURDA'], ['class' => 'text-decoration-none home-count-danger']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100 home-kpi-card home-kpi-danger">
            <div class="card-body p-2 d-flex flex-column justify-content-between">
                <div>
                    <div class="h6 mb-1 text-warning">Arıza Takibi</div>
                    <div class="small text-muted">Açık arıza</div>
                    <div class="h4 mb-0"><?= Html::a((string)(int)($summary['acikAriza'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'open'], ['class' => 'text-decoration-none home-count-danger']) ?></div>
                    <div class="small text-muted">Toplam arıza: <?= Html::a((string)(int)($summary['toplamAriza'] ?? 0), ['/ariza-takip/index'], ['class' => 'text-decoration-none home-count-warning']) ?></div>
                    <div class="small text-muted mt-2">Faal: <?= Html::a((string)(int)($summary['arizaFaal'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[ARIZANIN_SON_DURUMU]' => 'FAAL'], ['class' => 'text-decoration-none home-count-success']) ?></div>
                    <div class="small text-muted">Arızalı faal: <?= Html::a((string)(int)($summary['arizaArizaliFaal'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[ARIZANIN_SON_DURUMU]' => 'ARIZALI_FAAL'], ['class' => 'text-decoration-none home-count-warning']) ?></div>
                    <div class="small text-muted">Gayrı faal: <?= Html::a((string)(int)($summary['arizaGayriFaal'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[ARIZANIN_SON_DURUMU]' => 'GAYRI_FAAL'], ['class' => 'text-decoration-none home-count-danger']) ?></div>
                    <div class="small text-muted mt-2">Bu ay maliyet: <?= Html::a(number_format((float)($summary['buAyMaliyet'] ?? 0), 2, ',', '.') . ' ₺', ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'this-month'], ['class' => 'text-decoration-none home-count-success']) ?></div>
                    <div class="small text-muted">Toplam maliyet: <?= Html::a(number_format((float)($summary['toplamMaliyet'] ?? 0), 2, ',', '.') . ' ₺', ['/ariza-takip/index'], ['class' => 'text-decoration-none home-count-warning']) ?></div>
                </div>
                <div class="mt-2">
                    <?= Html::a('Detay KPI', ['/site/kpi'], ['class' => 'btn btn-sm btn-outline-warning w-100']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card bg-dark border-secondary h-100 home-kpi-card home-kpi-warning">
            <div class="card-body p-2">
                <div class="h6 mb-2 text-warning">Bakım Ekibi Faaliyetleri</div>
                <div class="home-activity-summary small">
                    <div class="home-activity-periods">
                        <?= $renderFaaliyetSection('Bu ay', $bakimFaaliyet['month'] ?? [], $monthStart, $monthEnd) ?>
                        <?= $renderFaaliyetSection('Bu yıl', $bakimFaaliyet['year'] ?? [], $yearStart, $yearEnd) ?>
                    </div>
                    <?= $renderFaaliyetSection('Tüm kayıtlar', $bakimFaaliyet['all'] ?? [], null, null, true, 'home-activity-all') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card bg-dark border-secondary h-100 home-kpi-card home-kpi-warning home-action-card">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="small text-muted">Planlı ve Periyodik</div>
                        <h5 class="mb-0 text-warning">Aksiyon Merkezi</h5>
                    </div>
                </div>

                <div class="home-action-section-title">Planlı bakım</div>
                <div class="home-action-list">
                    <?php foreach ($criticalActions as $action): ?>
                        <?= Html::a(
                            '<span>' . Html::encode($action['label']) . '</span><strong>' . (int)$action['value'] . '</strong>',
                            $action['url'],
                            ['class' => 'home-action-item home-action-' . $action['class']]
                        ) ?>
                    <?php endforeach; ?>
                </div>

                <div class="home-action-section-title mt-3">Periyodik kontrol</div>
                <div class="home-action-list home-action-list-compact">
                    <?= Html::a(
                        '<span>Gecikmiş kontrol</span><strong>' . (int)($summary['periyodikGecikmis'] ?? 0) . '</strong>',
                        ['/site/periyodik-kontroller', 'quick' => 'gecikmis'],
                        ['class' => 'home-action-item home-action-danger']
                    ) ?>
                    <?= Html::a(
                        '<span>30 gün yaklaşan</span><strong>' . (int)($summary['periyodikYaklasan30'] ?? 0) . '</strong>',
                        ['/site/periyodik-kontroller', 'quick' => 'yaklasan-30'],
                        ['class' => 'home-action-item home-action-warning']
                    ) ?>
                </div>
                <div class="mt-3">
                    <?= Html::a('Tüm planlı bakım ekranı', ['/planli-bakim/index'], ['class' => 'btn btn-sm btn-outline-warning w-100']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 align-items-start home-dashboard-grid">

    <!-- Planlı bakımı yaklaşan ekipmanlar (10 gün içinde) -->
    <div class="col-12">
    <div class="home-panel bg-dark text-white p-2" style="max-height: 78vh; overflow-y: auto;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="text-warning mb-0"><?php
                if (($quickFilter ?? '') === 'planli-gecikmis') {
                    echo '🛠 Planlı Bakımı Gecikenler';
                } elseif (($quickFilter ?? '') === 'planli-son-gun') {
                    echo '🛠 Bugün Son Günü Olan Planlı Bakımlar';
                } elseif (($quickFilter ?? '') === 'planli-yarin') {
                    echo '🛠 Yarın Yapılacak Planlı Bakımlar';
                } elseif (($quickFilter ?? '') === 'planli-7-gun') {
                    echo '🛠 7 Gün İçindeki Planlı Bakımlar';
                } else {
                    echo '🛠 Planlı Bakımı Yaklaşanlar';
                }
            ?></h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="home-planli-toggle"><?= $isPlanliPanelOpen ? 'Daralt' : 'Genişlet' ?></button>
        </div>

        <div id="home-planli-panel-body" class="<?= $isPlanliPanelOpen ? '' : 'd-none' ?>">

        <?php if ($canTopluBakimIsle): ?>
            <?php echo Html::beginForm(['/site/toplu-bakim-isle'], 'post', ['id' => 'home-toplu-bakim-form']); ?>
        <?php endif; ?>

        <?php if ($canTopluBakimIsle): ?>
            <div class="mb-2 p-2 border border-secondary rounded">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div class="flex-grow-1" style="min-width: 180px;">
                        <?= Html::label('Güne göre hızlı seçim', 'home_select_due_date', ['class' => 'form-label mb-1 small']) ?>
                        <?= Html::dropDownList('home_select_due_date', '', ['' => 'Gün seçiniz'] + $topluBakimGunleri, [
                            'id' => 'home_select_due_date',
                            'class' => 'form-select form-select-sm',
                        ]) ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-info" id="home-select-due-date">Seçili Günü İşaretle</button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="home-clear-selection">Seçimi Temizle</button>
                    </div>
                </div>
            </div>

            <div id="home-bulk-toolbar" class="d-none mb-2 p-2 border border-secondary rounded">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <div class="small text-muted mb-1">Seçili kayıt</div>
                        <div id="home-selected-count" class="fw-bold">0</div>
                    </div>
                    <div>
                        <?= Html::label('Bakım Tarihi', 'home_toplu_tarih', ['class' => 'form-label mb-1 small']) ?>
                        <?= Html::input('date', 'toplu_tarih', date('Y-m-d'), ['class' => 'form-control form-control-sm', 'id' => 'home_toplu_tarih']) ?>
                    </div>
                    <div class="form-check form-switch mb-1 ms-2">
                        <?= Html::checkbox('bakim_ertele', false, [
                            'class' => 'form-check-input',
                            'id' => 'home_bakim_ertele',
                            'value' => 1,
                        ]) ?>
                        <?= Html::label('Bakımı Ötele', 'home_bakim_ertele', ['class' => 'form-check-label small']) ?>
                    </div>
                    <div class="small text-muted mb-1" id="home-erteleme-help" style="max-width: 320px; display:none;">
                        İşaretlenirse bakım yapılmış sayılmaz; seçili planlı bakım tarihleri bu tarihe ötelenir.
                    </div>
                    <div class="form-check form-switch mb-1 ms-2" id="home-bakim-takip-switch-wrap">
                        <?= Html::checkbox('bakim_takip_ekle', true, [
                            'class' => 'form-check-input',
                            'id' => 'home_bakim_takip_ekle',
                            'value' => 1,
                        ]) ?>
                        <?= Html::label('Bakım Takip\'e de işle', 'home_bakim_takip_ekle', ['class' => 'form-check-label small']) ?>
                    </div>
                    <div id="home-bakim-takip-fields" class="d-flex flex-wrap align-items-end gap-2 w-100 mt-2">
                        <div class="w-100">
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="bakim_takip_kayit_turu" id="home_kayit_turu_tek" value="ayri" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="home_kayit_turu_tek">Tek Tek İşle</label>
                                <input type="radio" class="btn-check" name="bakim_takip_kayit_turu" id="home_kayit_turu_grup" value="grup" autocomplete="off" checked>
                                <label class="btn btn-outline-secondary" for="home_kayit_turu_grup">Grup İşle</label>
                            </div>
                        </div>
                        <div>
                            <?= Html::label('Bakım Süresi (Saat)', 'home_bakim_suresi_saat', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::input('number', 'bakim_suresi_saat', '', [
                                'class' => 'form-control form-control-sm',
                                'id' => 'home_bakim_suresi_saat',
                                'step' => '0.25',
                                'min' => 0,
                                'placeholder' => 'Örn: 1.5',
                            ]) ?>
                        </div>
                        <div style="min-width: 260px;">
                            <?= Html::label('Bakımı Yapanlar', 'home_bakim_isi_yapanlar', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::dropDownList('bakim_isi_yapanlar[]', [], $bakimYapanlarOptions, [
                                'id' => 'home_bakim_isi_yapanlar',
                                'multiple' => true,
                                'class' => 'form-select form-select-sm',
                                'size' => 4,
                            ]) ?>
                        </div>
                        <div class="flex-grow-1" style="min-width: 280px;">
                            <?= Html::label('Yapılan İş - İlave Not (Opsiyonel)', 'home_bakim_yapilan_is_ek', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::textInput('bakim_yapilan_is_ek', '', [
                                'class' => 'form-control form-control-sm',
                                'id' => 'home_bakim_yapilan_is_ek',
                                'placeholder' => 'Otomatik metne eklenecek ilave not',
                            ]) ?>
                        </div>
                        <div id="home-bakim-grup-baslik-wrap" class="flex-grow-1" style="min-width: 280px;">
                            <?= Html::label('Grup Başlığı', 'home_bakim_grup_basligi', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::textInput('bakim_grup_basligi', '', [
                                'class' => 'form-control form-control-sm',
                                'id' => 'home_bakim_grup_basligi',
                                'placeholder' => 'Örn: Yüzer havuz valf grupları',
                            ]) ?>
                            <div class="small text-muted mt-1">Yalnızca Grup İşle modunda gerekli</div>
                        </div>
                    </div>
                    <div class="pb-0">
                        <?= Html::submitButton('Seçilenleri Bakım İşle', [
                            'class' => 'btn btn-sm btn-warning',
                            'id' => 'home-bulk-submit',
                            'data-confirm' => 'Seçilen kayıtlar işlenecek. Bakım Takip işaretliyse her ekipman için ayrı bakım takip kaydı da oluşturulacak. Devam edilsin mi?',
                        ]) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?= GridView::widget([
            'id' => 'home-planli-grid',
            'dataProvider' => $dataProvider,
            'summary' => false,
            'tableOptions' => ['class' => 'table table-sm table-dark table-hover home-planli-table'],
            'rowOptions' => function ($item) {
                $class = '';
                try {
                    $today = new \DateTime('today');
                    $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
                    if ($sonrakiTarih < $today) {
                        $class = 'home-row-overdue';
                    } elseif ($sonrakiTarih == $today) {
                        $class = 'home-row-today';
                    }
                } catch (\Exception $e) {
                    $class = '';
                }

                return ['data-due-date' => $item['sonraki_tarih'] ?? '', 'class' => $class];
            },
            'columns' => [
                [
                    'class' => CheckboxColumn::class,
                    'name' => 'selection',
                    'visible' => $canTopluBakimIsle,
                    'checkboxOptions' => function ($item) {
                        return ['value' => $item['planli_id']];
                    },
                ],
                [
                    'label' => 'Ekipman / Tanım',
                    'format' => 'raw',
                    'value' => function ($item) {
                        $ekipmanLink = Html::a(
                            Html::encode($item['tanimi']),
                            ['/ekipman/view', 'id' => $item['ekipman_id']],
                            ['class' => 'ekipman-tanim-link', 'title' => 'Ekipman detayını aç']
                        );

                        // Yeni planlı bakım kaydı için aynı emoji ile ikon / tooltip'li buton
                        $icon = '🛠';
                        $planliButton = Html::a(
                            $icon,
                            ['/planli-bakim/create', 'planli_id' => $item['planli_id']],
                            [
                                'class' => 'btn btn-sm btn-outline-warning ms-2 p-1 px-2 home-planli-action',
                                'title' => 'Bakımı işle',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'left',
                            ]
                        );

                        return '<div class="d-flex justify-content-between align-items-center gap-2">'
                            . '<div class="home-equipment-cell">' . $ekipmanLink . '</div>'
                            . '<div>' . $planliButton . '</div>'
                            . '</div>';
                    },
                ],
                [
                    'label' => 'Periyot',
                    'value' => function ($item) {
                        return str_replace('Periyodik: ', '', $item['periyodu']);
                    },
                ],
              
                [
                    'label' => 'Planlı Bakım',
                    'format' => 'raw',
                    'value' => function ($item) {
                        try {
                            $sonTarih = new \DateTime($item['son_tarih']);
                            $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
                            $totalDays = max(1, $sonTarih->diff($sonrakiTarih)->days);
                            $today = new \DateTime('today');

                            if ($sonrakiTarih < $today) {
                                $remainingDays = 0;
                                $percent = 100;
                                $barClass = 'bg-danger';
                                $barStyle = '';
                                $label = $sonrakiTarih->format('d.m.Y') . ' - GECİKMİŞ';
                            } elseif ($sonrakiTarih == $today) {
                                $remainingDays = 0;
                                $percent = 100;
                                $barClass = '';
                                $barStyle = 'background-color: #c2410c;';
                                $label = $sonrakiTarih->format('d.m.Y') . ' - BUGÜN SON GÜN';
                            } else {
                                $remainingDays = $today->diff($sonrakiTarih)->days;
                                $completedDays = $totalDays - $remainingDays;
                                $percent = max(5, min(100, round($completedDays * 100 / $totalDays)));
                                $fractionRemaining = $remainingDays / $totalDays;

                                if ($fractionRemaining <= 0.2) {
                                    $barClass = 'bg-warning';
                                } elseif ($fractionRemaining <= 0.5) {
                                    $barClass = 'bg-info';
                                } else {
                                    $barClass = 'bg-success';
                                }
                                $barStyle = '';

                                $label = $sonrakiTarih->format('d.m.Y') . ' - ' . $remainingDays . ' gün kaldı';
                            }
                        } catch (\Exception $e) {
                            $percent = 0;
                            $barClass = 'bg-secondary';
                            $barStyle = '';
                            $label = 'Tarih hesaplanamadı';
                        }

                        $progress = '<div class="progress position-relative" style="height: 22px; margin-bottom: 0;">';
                        $progress .= '<div class="progress-bar ' . $barClass . '" role="progressbar" style="width: ' . $percent . '%; opacity: 0.4; ' . $barStyle . '" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"></div>';
                        $progress .= '<span class="position-absolute w-100 text-center small" style="color: #fff; line-height: 22px;">' . Html::encode($label) . '</span>';
                        $progress .= '</div>';

                        return $progress;
                    },
                ],
            ],
            'emptyText' => $planliEmptyText,
        ]); ?>

        <?php if ($canTopluBakimIsle): ?>
            <?php echo Html::endForm(); ?>
        <?php endif; ?>

        </div>
    </div>
    </div>

</div>

<?php
$this->registerJs('(function(){
    function toggleBakimTakipFields(){
        var enabled = $("#home_bakim_takip_ekle").is(":checked");
        var ertele = $("#home_bakim_ertele").is(":checked");
        $("#home-bakim-takip-switch-wrap").toggle(!ertele);
        $("#home-erteleme-help").toggle(ertele);
        $("#home-bakim-takip-fields").toggleClass("d-none", !enabled || ertele);
        $("#home-bulk-submit").text(ertele ? "Seçilenleri Ötele" : "Seçilenleri Bakım İşle");
        $("#home-bulk-submit").attr("data-confirm", ertele
            ? "Seçilen kayıtlar bakım yapılmış sayılmadan ötelenir. Devam edilsin mi?"
            : "Seçilen kayıtlar işlenecek. Bakım Takip işaretliyse her ekipman için ayrı bakım takip kaydı da oluşturulacak. Devam edilsin mi?");
        toggleGrupBaslik();
    }

    function toggleGrupBaslik(){
        var isGrup = $("#home_kayit_turu_grup").is(":checked") && !$("#home_bakim_ertele").is(":checked");
        $("#home-bakim-grup-baslik-wrap").toggle(isGrup);
    }

    function updateBulkToolbar(){
        if(!$("#home-bulk-toolbar").length){
            return;
        }

        var selected = $("#home-planli-grid").find("input[name=\"selection[]\"]:checked").length;
        if(selected > 0){
            $("#home-bulk-toolbar").removeClass("d-none");
        } else {
            $("#home-bulk-toolbar").addClass("d-none");
        }
        $("#home-selected-count").text(selected);
    }

    $(document).on("change", "#home-planli-grid input[type=checkbox]", updateBulkToolbar);
    $(document).on("change", "#home_bakim_ertele", toggleBakimTakipFields);
    $(document).on("change", "#home_bakim_takip_ekle", toggleBakimTakipFields);
    $(document).on("change", "input[name=\'bakim_takip_kayit_turu\']", toggleGrupBaslik);

    $(document).on("click", "#home-select-due-date", function(){
        var dueDate = $("#home_select_due_date").val();
        if(!dueDate){
            alert("Lütfen seçilecek günü seçiniz.");
            return;
        }

        $("#home-planli-grid input[name=\"selection[]\"]").prop("checked", false);
        $("#home-planli-grid tbody tr[data-due-date=\"" + dueDate + "\"] input[name=\"selection[]\"]").prop("checked", true);
        updateBulkToolbar();
    });

    $(document).on("click", "#home-clear-selection", function(){
        $("#home-planli-grid input[name=\"selection[]\"]").prop("checked", false);
        updateBulkToolbar();
    });

    $(document).on("click", "#home-planli-toggle", function(){
        var body = $("#home-planli-panel-body");
        body.toggleClass("d-none");
        $(this).text(body.hasClass("d-none") ? "Genişlet" : "Daralt");
    });

    $("#home-toplu-bakim-form").on("submit", function(e){
        var selected = $("#home-planli-grid").find("input[name=\"selection[]\"]:checked").length;
        var tarih = $("#home_toplu_tarih").val();
        var bakimTakipEkle = $("#home_bakim_takip_ekle").is(":checked");
        var bakimErtele = $("#home_bakim_ertele").is(":checked");
        var bakimSuresi = $("#home_bakim_suresi_saat").val();
        var yapanlar = $("#home_bakim_isi_yapanlar").val() || [];
        var grupBasligi = $("#home_bakim_grup_basligi").val().trim();

        if(selected === 0){
            e.preventDefault();
            alert("Lütfen en az bir kayıt seçiniz.");
            return false;
        }

        if(!tarih){
            e.preventDefault();
            alert("Lütfen bakım tarihini seçiniz.");
            return false;
        }

        if(bakimTakipEkle && !bakimErtele){
            if(!bakimSuresi || parseFloat(bakimSuresi) <= 0){
                e.preventDefault();
                alert("Bakım Takip için geçerli bir bakım süresi giriniz.");
                return false;
            }

            if(yapanlar.length === 0){
                e.preventDefault();
                alert("Bakım Takip için en az bir personel seçiniz.");
                return false;
            }

            var isGrup = $("#home_kayit_turu_grup").is(":checked");
            if(isGrup && !grupBasligi){
                e.preventDefault();
                alert("Grup başlığı giriniz.");
                return false;
            }
        }
    });

    updateBulkToolbar();
    toggleBakimTakipFields();
    toggleGrupBaslik();
})();');
?>
