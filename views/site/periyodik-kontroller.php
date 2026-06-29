<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $quickFilter */
/** @var string $scope */

$this->title = 'Periyodik Kontroller Listesi';
$this->params['breadcrumbs'][] = $this->title;
$isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
$scope = $scope ?? 'active';
$pdfPostMaxBytes = min((int)ini_get('post_max_size') * 1024 * 1024, PHP_INT_MAX);
$pdfMaxFiles = (int)ini_get('max_file_uploads');
$renderRaporLink = static function ($model): string {
    $raporNo = trim((string)$model->rapor_no);
    if ($raporNo === '') {
        return '';
    }

    $base = preg_match('/(\d{6}\.\d{4}\.\d+)/', $raporNo, $m) ? $m[1] : $raporNo;
    $fileName = $base . '.pdf';
    $filePath = Yii::getAlias('@webroot/uploads/periyodik-raporlar/' . $fileName);

    if (file_exists($filePath)) {
        return Html::a(Html::encode($raporNo), Yii::getAlias('@web/uploads/periyodik-raporlar/' . $fileName), [
            'target' => '_blank',
            'title' => 'PDF raporu aç',
            'data-pjax' => 0,
            'class' => 'text-warning text-decoration-none fw-bold',
        ]);
    }

    return Html::encode($raporNo);
};
$renderKalanSure = static function ($model): string {
    if ((int)($model->is_eski ?? 0) === 1) {
        return '<span class="badge bg-secondary">Eski rapor</span>';
    }

    if (empty($model->gelecek_kontrol_tarihi)) {
        return '<span class="text-muted">Tarih yok</span>';
    }

    try {
        $today = new DateTime('today');
        $due = new DateTime($model->gelecek_kontrol_tarihi);
    } catch (Exception $e) {
        return '<span class="text-muted">Tarih hatalı</span>';
    }

    $diff = (int)$today->diff($due)->format('%r%a');
    if ($diff < 0) {
        $days = abs($diff);
        $class = $days > 30 ? 'danger' : 'warning';
        $percent = min(100, max(12, (int)round($days * 100 / 30)));
        $label = $days . ' gün geçti';
    } elseif ($diff === 0) {
        $class = 'warning';
        $percent = 100;
        $label = 'Bugün son gün';
    } else {
        $class = $diff <= 30 ? 'warning' : 'success';
        $percent = $diff <= 30 ? max(12, (int)round($diff * 100 / 30)) : 100;
        $label = $diff . ' gün kaldı';
    }

    return '<div class="periyodik-due-bar periyodik-due-' . $class . '">'
        . '<div class="periyodik-due-fill" style="width:' . $percent . '%"></div>'
        . '<span>' . Html::encode($label) . '</span>'
        . '</div>';
};
?>

<style>
.periyodik-desktop-grid {}
.periyodik-mobile-list { display: none; }
.periyodik-mobile-summary { display: none; }
.periyodik-mobile-pager { display: none; }
.periyodik-mobile-card {
    display: block;
    color: #fff;
    text-decoration: none;
    border: 1px solid #495057;
    border-radius: 10px;
    background: #1f2428;
}
.periyodik-mobile-card:hover {
    color: #fff;
    border-color: #ffc107;
    text-decoration: none;
}
.periyodik-mobile-code {
    color: #ffc107;
    font-weight: 800;
    white-space: nowrap;
}
.periyodik-mobile-title {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-weight: 700;
    line-height: 1.25;
}
.periyodik-mobile-meta {
    color: #cbd5e1;
    font-size: .86rem;
}
.periyodik-mobile-report a { color: #ffc107; font-weight: 800; }
.periyodik-mobile-card .periyodik-due-bar { height: 22px; }
.periyodik-mobile-summary {
    color: #fff;
    font-weight: 700;
    font-size: .92rem;
}
.periyodik-mobile-pager-inner {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: .45rem;
    align-items: center;
}
.periyodik-mobile-page-status {
    border: 1px solid #495057;
    border-radius: 8px;
    padding: .45rem .7rem;
    color: #fff;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
    background: #1f2428;
}
.periyodik-mobile-pager-extra {
    display: flex;
    justify-content: center;
    gap: .45rem;
    margin-top: .45rem;
}
@media (max-width: 767.98px) {
    .periyodik-desktop-grid { display: none; }
    .periyodik-mobile-list {
        display: grid;
        gap: .55rem;
    }
    .periyodik-mobile-summary { display: block; }
    .periyodik-mobile-pager { display: block; }
}
</style>

<div class="periyodik-kontroller-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-outline-warning mb-2" data-bs-toggle="collapse" data-bs-target="#periyodik-admin-panel" aria-expanded="false" aria-controls="periyodik-admin-panel">
            Yönetim Paneli
        </button>
        <div class="collapse" id="periyodik-admin-panel">
        <div class="card bg-dark border-secondary mb-3">
            <div class="card-body py-3">
                <?= Html::beginForm(['site/periyodik-kontrol-import'], 'post', ['enctype' => 'multipart/form-data', 'data-pjax' => 0, 'class' => 'row g-2 align-items-end']) ?>
                    <div class="col-md-6">
                        <label class="form-label mb-1" for="periyodik_excel">Excel/CSV ile kayıt ekle veya güncelle</label>
                        <input type="file" id="periyodik_excel" name="periyodik_excel" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="col-md-auto">
                        <?= Html::submitButton('İçe Aktar', ['class' => 'btn btn-primary']) ?>
                    </div>
                    <div class="col-12 small text-muted">
                        Zorunlu başlıklar: Ekipman Kodu/Kodu ve Cihaz Adı. Rapor No doluysa aynı ekipman kodu + rapor no kaydı güncellenir.
                    </div>
                <?= Html::endForm() ?>

                <hr class="border-secondary my-3">

                <?= Html::beginForm(['site/periyodik-rapor-upload'], 'post', ['enctype' => 'multipart/form-data', 'data-pjax' => 0, 'class' => 'row g-2 align-items-end']) ?>
                    <div class="col-md-6">
                        <label class="form-label mb-1" for="periyodik_raporlar">Toplu periyodik rapor PDF yükle</label>
                        <input type="file" id="periyodik_raporlar" name="periyodik_raporlar[]" class="form-control" accept=".pdf,application/pdf" multiple required>
                    </div>
                    <div class="col-md-auto">
                        <?= Html::submitButton('PDF Yükle', ['class' => 'btn btn-warning']) ?>
                    </div>
                    <div class="col-12 small text-muted">
                        Dosya adı rapor no olmalıdır. Örnek: <code>260504.1916.174.pdf</code>. Aynı isimli dosya varsa yenisiyle değiştirilir.
                        Sunucu limiti: toplam <code><?= Html::encode((string)ini_get('post_max_size')) ?></code>, tek dosya <code><?= Html::encode((string)ini_get('upload_max_filesize')) ?></code>, en fazla <code><?= Html::encode((string)ini_get('max_file_uploads')) ?></code> dosya.
                    </div>
                    <div class="col-12 small text-warning" id="periyodik-rapor-upload-warning" style="display:none;"></div>
                    <div class="col-12 small text-muted" id="periyodik-rapor-upload-summary"></div>
                <?= Html::endForm() ?>
            </div>
        </div>
        </div>
    <?php endif; ?>

    <?php if (($quickFilter ?? '') === 'gecikmis'): ?>
        <div class="alert alert-warning py-2">KPI filtresi aktif: Gecikmiş aktif periyodik kontroller listeleniyor.</div>
    <?php elseif (($quickFilter ?? '') === 'yaklasan-30'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Önümüzdeki 30 gün içindeki aktif periyodik kontroller listeleniyor.</div>
    <?php elseif (($quickFilter ?? '') === 'yaklasan-90'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Önümüzdeki 90 gün içindeki aktif periyodik kontroller listeleniyor.</div>
    <?php endif; ?>

    <?php if (($quickFilter ?? '') === ''): ?>
        <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Periyodik kontrol görünümü">
            <?= Html::a('Aktif takip', ['/site/periyodik-kontroller', 'scope' => 'active', 'q' => $searchTerm ?? null], [
                'class' => 'btn ' . ($scope === 'active' ? 'btn-warning' : 'btn-outline-warning'),
            ]) ?>
            <?= Html::a('Tüm raporlar', ['/site/periyodik-kontroller', 'scope' => 'all', 'q' => $searchTerm ?? null], [
                'class' => 'btn ' . ($scope === 'all' ? 'btn-warning' : 'btn-outline-warning'),
            ]) ?>
            <?= Html::a('Eski raporlar', ['/site/periyodik-kontroller', 'scope' => 'old', 'q' => $searchTerm ?? null], [
                'class' => 'btn ' . ($scope === 'old' ? 'btn-warning' : 'btn-outline-warning'),
            ]) ?>
        </div>
        <div class="small text-muted mb-2">
            Aktif takip, aynı ekipman ve cihaz için en güncel raporu gösterir. Eski raporlar arşivde kalır.
        </div>
    <?php endif; ?>

    <input type="text" id="periyodik-live-search" class="form-control mb-2"
        placeholder="🔍 Ara... (Ekipman kodu, cihaz adı, yer, rapor no...)"
        value="<?= Html::encode($searchTerm ?? '') ?>"
        autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'periyodik-pjax', 'enablePushState' => false]); ?>
    <?php
    $pagination = $dataProvider->getPagination();
    $totalCount = $dataProvider->getTotalCount();
    $currentCount = count($dataProvider->getModels());
    $pageNumber = $pagination === false ? 1 : $pagination->getPage() + 1;
    $pageCount = $pagination === false ? 1 : max(1, $pagination->getPageCount());
    $firstItem = $totalCount === 0 || $pagination === false ? ($totalCount === 0 ? 0 : 1) : $pagination->getOffset() + 1;
    $lastItem = $totalCount === 0 ? 0 : $firstItem + $currentCount - 1;
    $summaryText = Yii::$app->formatter->asInteger($totalCount) . ' öğenin ' . Yii::$app->formatter->asInteger($firstItem) . '-' . Yii::$app->formatter->asInteger($lastItem) . ' arası gösteriliyor.';
    ?>

    <div class="periyodik-desktop-grid">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-sm table-hover table-dark'],
        'columns' => array_filter([
            [
                'attribute' => 'ekipman_id',
                'label' => 'Ekipman Kodu',
                'format' => 'raw',
                'value' => function ($model) {
                    return Html::a(Html::encode($model->ekipman_id), ['ekipman/view', 'id' => $model->ekipman_id], [
                        'target' => '_blank',
                        'title'  => 'Ekipman detayını aç',
                        'class'  => 'text-white fw-bold',
                    ]);
                },
            ],
            [
                'attribute' => 'cihaz_adi',
                'label' => 'Cihaz Adı',
            ],
            [
                'attribute' => 'rapor_no',
                'format' => 'raw',
                'value' => $renderRaporLink,
            ],
            [
                'attribute' => 'gelecek_kontrol_tarihi',
                'label' => 'Geçerlilik Sonu',
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->gelecek_kontrol_tarihi ? Yii::$app->formatter->asDate($model->gelecek_kontrol_tarihi, 'php:d.m.Y') : '';
                },
            ],
            [
                'label' => 'Kalan Süre',
                'format' => 'raw',
                'contentOptions' => ['style' => 'min-width: 220px;'],
                'value' => $renderKalanSure,
            ],
            $isAdmin ? [
                'label' => 'İşlem',
                'format' => 'raw',
                'contentOptions' => ['class' => 'text-nowrap'],
                'value' => function ($model) {
                    $return = Url::current();
                    return Html::a('Düzenle', ['site/periyodik-kontrol-update', 'id' => $model->id, 'return' => $return], [
                        'class' => 'btn btn-sm btn-outline-info mr-1',
                        'data-pjax' => 0,
                    ]) . Html::a('Sil', ['site/periyodik-kontrol-delete', 'id' => $model->id, 'return' => $return], [
                        'class' => 'btn btn-sm btn-outline-danger',
                        'data-method' => 'post',
                        'data-confirm' => 'Bu periyodik kontrol kaydı silinsin mi?',
                        'data-pjax' => 0,
                    ]);
                },
            ] : null,
        ]),
        'emptyText' => 'Periyodik kontrol kaydı bulunmamaktadır.',
    ]) ?>
    </div>

    <div class="periyodik-mobile-summary mb-2">
        <?= Html::encode($summaryText) ?>
    </div>

    <div class="periyodik-mobile-list">
        <?php foreach ($dataProvider->getModels() as $model): ?>
            <div class="periyodik-mobile-card p-2">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <?= Html::a(Html::encode($model->ekipman_id), ['ekipman/view', 'id' => $model->ekipman_id], [
                        'class' => 'periyodik-mobile-code',
                        'target' => '_blank',
                    ]) ?>
                    <span class="badge <?= (int)($model->is_eski ?? 0) === 1 ? 'bg-secondary' : 'bg-success' ?>">
                        <?= (int)($model->is_eski ?? 0) === 1 ? 'Eski' : 'Aktif' ?>
                    </span>
                </div>
                <div class="periyodik-mobile-title mb-1"><?= Html::encode($model->cihaz_adi) ?></div>
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="periyodik-mobile-report"><?= $renderRaporLink($model) ?></div>
                    <div class="periyodik-mobile-meta text-nowrap">
                        <?= $model->gelecek_kontrol_tarihi ? Html::encode(Yii::$app->formatter->asDate($model->gelecek_kontrol_tarihi, 'php:d.m.Y')) : '' ?>
                    </div>
                </div>
                <?= $renderKalanSure($model) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="periyodik-mobile-pager mt-3">
        <?php if ($pagination !== false && $pageCount > 1): ?>
            <div class="periyodik-mobile-pager-inner">
                <?= $pageNumber > 1 ? Html::a('Önceki', $pagination->createUrl($pageNumber - 2), ['class' => 'btn btn-outline-primary btn-sm']) : Html::tag('span', 'Önceki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
                <div class="periyodik-mobile-page-status">Sayfa <?= Html::encode((string)$pageNumber) ?> / <?= Html::encode((string)$pageCount) ?></div>
                <?= $pageNumber < $pageCount ? Html::a('Sonraki', $pagination->createUrl($pageNumber), ['class' => 'btn btn-outline-primary btn-sm']) : Html::tag('span', 'Sonraki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
            </div>
            <div class="periyodik-mobile-pager-extra">
                <?= $pageNumber > 1 ? Html::a('İlk sayfa', $pagination->createUrl(0), ['class' => 'btn btn-outline-secondary btn-sm']) : '' ?>
                <?= $pageNumber < $pageCount ? Html::a('Son sayfa', $pagination->createUrl($pageCount - 1), ['class' => 'btn btn-outline-secondary btn-sm']) : '' ?>
            </div>
        <?php endif; ?>
    </div>

    <?php Pjax::end(); ?>

</div>

<script>
(function () {
    var raporInput = document.getElementById('periyodik_raporlar');
    var raporWarning = document.getElementById('periyodik-rapor-upload-warning');
    var raporSummary = document.getElementById('periyodik-rapor-upload-summary');
    var maxFiles = <?= (int)$pdfMaxFiles ?>;
    var postMaxBytes = <?= (int)$pdfPostMaxBytes ?>;
    if (raporInput && raporWarning && raporSummary) {
        raporInput.addEventListener('change', function () {
            var files = Array.prototype.slice.call(this.files || []);
            var total = files.reduce(function (sum, file) { return sum + file.size; }, 0);
            var totalMb = total / 1024 / 1024;
            var postMaxMb = postMaxBytes / 1024 / 1024;
            var warnings = [];

            raporSummary.textContent = files.length ? files.length + ' dosya seçildi, toplam yaklaşık ' + totalMb.toFixed(1) + ' MB.' : '';
            if (maxFiles > 0 && files.length > maxFiles) {
                warnings.push('Seçilen dosya adedi sunucu limitini aşıyor. Limit: ' + maxFiles + ' dosya.');
            }
            if (postMaxBytes > 0 && total > postMaxBytes) {
                warnings.push('Seçilen dosyaların toplam boyutu sunucu limitini aşıyor. Limit: ' + postMaxMb.toFixed(0) + ' MB.');
            }

            raporWarning.style.display = warnings.length ? 'block' : 'none';
            raporWarning.textContent = warnings.join(' ');
        });
    }

    var input = document.getElementById('periyodik-live-search');
    if (!input) return;
    var scope = <?= json_encode($scope) ?>;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#periyodik-pjax',
                data: { q: val, scope: scope },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });
})();
</script>

