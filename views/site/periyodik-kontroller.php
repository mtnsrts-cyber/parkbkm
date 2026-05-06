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
?>

<div class="periyodik-kontroller-index">

    <h1><?= Html::encode($this->title) ?></h1>

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
        'columns' => [
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

                    if (preg_match('/(\\d{6}\\.\\d{4}\\.\\d{1,2})/', $raporNo, $m)) {
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
                'format' => ['date', 'php:d.m.Y'],
            ],
            [
                'attribute' => 'gelecek_kontrol_tarihi',
                'format' => ['date', 'php:d.m.Y'],
            ],
        ],
        'emptyText' => 'Periyodik kontrol kaydı bulunmamaktadır.',
    ]) ?>

    <?php Pjax::end(); ?>

</div>

<script>
(function () {
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

