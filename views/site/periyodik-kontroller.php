<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $quickFilter */

$this->title = 'Periyodik Kontroller Listesi';
$this->params['breadcrumbs'][] = $this->title;
$isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
?>

<div class="periyodik-kontroller-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-outline-warning mb-2" id="periyodik-admin-toggle">
            Yönetim Paneli
        </button>
        <div class="card bg-dark border-secondary mb-3" id="periyodik-admin-panel" style="display:none;">
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
                    </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (($quickFilter ?? '') === 'gecikmis'): ?>
        <div class="alert alert-warning py-2">KPI filtresi aktif: Gecikmiş periyodik kontroller listeleniyor.</div>
    <?php elseif (($quickFilter ?? '') === 'yaklasan-30'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Önümüzdeki 30 gün içindeki periyodik kontroller listeleniyor.</div>
    <?php elseif (($quickFilter ?? '') === 'yaklasan-90'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Önümüzdeki 90 gün içindeki periyodik kontroller listeleniyor.</div>
    <?php endif; ?>

    <input type="text" id="periyodik-live-search" class="form-control mb-2"
        placeholder="🔍 Ara... (Ekipman kodu, cihaz adı, yer, rapor no...)"
        value="<?= Html::encode($searchTerm ?? '') ?>"
        autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'periyodik-pjax', 'enablePushState' => false]); ?>

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
                        'class'  => 'text-white',
                    ]);
                },
            ],
            'cihaz_adi',
            'bulundugu_yer',
            [
                'attribute' => 'rapor_no',
                'format' => 'raw',
                'value' => function ($model) {
                    $raporNo = trim((string)$model->rapor_no);
                    if ($raporNo === '') {
                        return '';
                    }

                    if (preg_match('/(\\d{6}\\.\\d{4}\\.\\d+)/', $raporNo, $m)) {
                        $base = $m[1];
                    } else {
                        $base = $raporNo;
                    }

                    $fileName = $base . '.pdf';
                    $filePath = Yii::getAlias('@webroot/uploads/periyodik-raporlar/' . $fileName);

                    if (file_exists($filePath)) {
                        $url = Yii::getAlias('@web/uploads/periyodik-raporlar/' . $fileName);
                        return Html::a(Html::encode($raporNo), $url, [
                            'target'     => '_blank',
                            'title'      => 'PDF raporu aç',
                            'data-pjax'  => 0,
                            'class'      => 'text-white',
                        ]);
                    }

                    return Html::encode($raporNo);
                },
            ],
            [
                'attribute' => 'son_kontrol_tarihi',
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->son_kontrol_tarihi ? Yii::$app->formatter->asDate($model->son_kontrol_tarihi, 'php:d.m.Y') : '';
                },
            ],
            [
                'attribute' => 'gelecek_kontrol_tarihi',
                'format' => 'raw',
                'value' => function ($model) {
                    return $model->gelecek_kontrol_tarihi ? Yii::$app->formatter->asDate($model->gelecek_kontrol_tarihi, 'php:d.m.Y') : '';
                },
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

    <?php Pjax::end(); ?>

</div>

<script>
(function () {
    var adminToggle = document.getElementById('periyodik-admin-toggle');
    var adminPanel = document.getElementById('periyodik-admin-panel');
    if (adminToggle && adminPanel) {
        adminToggle.addEventListener('click', function () {
            adminPanel.style.display = adminPanel.style.display === 'none' ? 'block' : 'none';
        });
    }

    var input = document.getElementById('periyodik-live-search');
    if (!input) return;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#periyodik-pjax',
                data: { q: val },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });
})();
</script>

