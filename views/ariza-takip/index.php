<?php

use app\models\ArizaTakip;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use yii\grid\ActionColumn;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var app\models\ArizaTakipSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Arıza Takip Listesi';
$this->params['breadcrumbs'][] = $this->title;
?>
<style>
.ariza-desktop-grid {}
.ariza-mobile-list { display: none; }
.ariza-mobile-summary { display: none; }
.ariza-mobile-pager { display: none; }
.ariza-mobile-card {
    display: block;
    color: #fff;
    text-decoration: none;
    border: 1px solid #495057;
    border-radius: 10px;
    background: #1f2428;
}
.ariza-mobile-card:hover {
    color: #fff;
    border-color: #ffc107;
    text-decoration: none;
}
.ariza-mobile-date {
    color: #ffc107;
    font-weight: 800;
    white-space: nowrap;
}
.ariza-mobile-status {
    font-weight: 800;
    font-size: .82rem;
}
.ariza-mobile-status-faal { color: #22c55e; }
.ariza-mobile-status-arizali-faal { color: #ffc107; }
.ariza-mobile-status-gayri-faal { color: #ef4444; }
.ariza-mobile-title {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-weight: 700;
    line-height: 1.25;
}
.ariza-mobile-meta {
    color: #cbd5e1;
    font-size: .86rem;
}
.ariza-mobile-summary {
    color: #fff;
    font-weight: 700;
    font-size: .92rem;
}
.ariza-mobile-pager-inner {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: .45rem;
    align-items: center;
}
.ariza-mobile-page-status {
    border: 1px solid #495057;
    border-radius: 8px;
    padding: .45rem .7rem;
    color: #fff;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
    background: #1f2428;
}
.ariza-mobile-pager-extra {
    display: flex;
    justify-content: center;
    gap: .45rem;
    margin-top: .45rem;
}
@media (max-width: 767.98px) {
    .ariza-desktop-grid { display: none; }
    .ariza-mobile-list {
        display: grid;
        gap: .55rem;
    }
    .ariza-mobile-summary { display: block; }
    .ariza-mobile-pager { display: block; }
}
</style>
<div class="ariza-takip-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($searchModel->quickFilter === 'open'): ?>
        <div class="alert alert-warning py-2">KPI filtresi aktif: Açık arızalar listeleniyor.</div>
    <?php elseif ($searchModel->quickFilter === 'this-month'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Bu ay açılan arızalar listeleniyor.</div>
    <?php endif; ?>

    <p>
        <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true)): ?>
            <?= Html::a('Yeni Arıza Kaydı', ['create'], ['class' => 'btn btn-success']) ?>
        <?php endif; ?>

        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
            <button type="button" class="btn btn-outline-warning ml-1" data-bs-toggle="collapse" data-bs-target="#arizaAktarPanel" aria-expanded="false" aria-controls="arizaAktarPanel">
                Toplu Arıza Aktar
            </button>
        <?php endif; ?>

        <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor'])): ?>
            <?= Html::a('Excel İndir', array_merge(['export-excel'], Yii::$app->request->queryParams), ['class' => 'btn btn-primary', 'data-pjax' => 0]) ?>
        <?php endif; ?>
    </p>

    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
        <div id="arizaAktarPanel" class="collapse">
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-body py-3">
                    <div class="font-weight-bold mb-2">Toplu Arıza Aktar</div>
                    <p class="small text-muted mb-3">
                        Excel/CSV dosyası yükleyin. Zorunlu başlıklar: <code>Arıza Bildirim Tarihi</code>, <code>Arıza Tarihi</code>, <code>Arızalanan Makine Adı</code>, <code>Arızalanan Makine Kodu</code>, <code>Arızanın Son Durumu</code>.
                    </p>
                    <?= Html::beginForm(['ariza-takip/toplu-aktar'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'd-flex align-items-center flex-wrap mb-0']) ?>
                        <input type="file" name="ariza_excel" accept=".xlsx,.xls,.csv" class="form-control-file mr-2 mb-2" style="max-width: 360px;" required>
                        <button type="submit" class="btn btn-success btn-sm mb-2">Yükle</button>
                        <a href="#" class="btn btn-outline-info btn-sm ml-2 mb-2" id="ornekArizaCsvIndir">Örnek CSV</a>
                    <?= Html::endForm() ?>
                </div>
            </div>
        </div>
        <script>
        document.getElementById('ornekArizaCsvIndir') && document.getElementById('ornekArizaCsvIndir').addEventListener('click', function(e) {
            e.preventDefault();
            var csv = "Arıza Bildirim Tarihi;Arıza Tarihi;Arızayı Bildiren;Arızaya Sebebiyet Veren Firma;Arızalanan Makine Adı;Arızalanan Makine Kodu;Arızalanan Parça;Arızanın Meydana Geldiği Bölüm;Arıza Kök Nedeni;Kalıcı Aksiyon;Arıza Sebebi;Arızanın Giderildiği Tarih;Arızanın Son Durumu;Arızalı Kaldığı Süre (Saat);Yedek Parça Bekleme Süresi (Saat);Malzeme Tutarı;İşçilik Fiyatı;Maliyet (TL);Arızanın Ayrıntılı Açıklaması\n" +
                "16.06.2026;16.06.2026;Metin Sarıtaş;;ÖRNEK EKİPMAN;ORNEK-01;Pompa;SAHA;Aşınma;Parça değiştirildi;Mekanik;17.06.2026;FAAL;2;0;1000;500;1500;Toplu aktarım örneği\n";
            var blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'});
            var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
            a.download = 'ariza_takip_toplu_aktar_ornek.csv'; a.click();
        });
        </script>
    <?php endif; ?>

    <input type="text" id="ariza-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Makine adı, kodu, bildiren, bölüm, durum...)" value="<?= Html::encode($searchModel->globalSearch ?? '') ?>" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'ariza-pjax', 'enablePushState' => false]); ?>
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

    <div class="ariza-desktop-grid">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table table-sm table-hover table-dark'],
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'attribute' => 'ARIZA_BILDIRIM_TARIHI',
                'value' => function($model){
                    return Yii::$app->formatter->asDate($model->ARIZA_BILDIRIM_TARIHI, 'php:d.m.Y');
                }
            ],
           // [
           //     'attribute' => 'ARIZA_TARIHI',
           //     'value' => function($model){
           //         return Yii::$app->formatter->asDate($model->ARIZA_TARIHI, 'php:d.m.Y');
           //     }
           // ],
           // 'ARIZAYI_BILDIREN',
            [
                'attribute' => 'ARIZALANAN_MAKINE_ADI',
                'format' => 'raw',
                'value' => static function (ArizaTakip $model) {
                    $label = Html::encode((string)$model->ARIZALANAN_MAKINE_ADI);
                    $ekipmanKodu = trim((string)$model->ARIZALANAN_MAKINE_KODU);

                    if ($ekipmanKodu === '') {
                        return $label;
                    }

                    return Html::a(
                        $label,
                        ['/ekipman/view', 'id' => $ekipmanKodu],
                        ['class' => 'text-reset text-decoration-none', 'style' => 'color: inherit;']
                    );
                },
            ],
           // 'ARIZANIN_MEYDANA_GELDIGI_BOLUM',
            'ARIZANIN_SON_DURUMU',

            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, ArizaTakip $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                },
                'visibleButtons' => [
                    'update' => function ($model, $key, $index) {
                        return !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
                    },
                    'delete' => function ($model, $key, $index) {
                        return !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
                    },
                    'view' => true,
                ],
            ],
        ],
    ]); ?>
    </div>

    <div class="ariza-mobile-summary mb-2">
        <?= Html::encode($summaryText) ?>
    </div>

    <div class="ariza-mobile-list">
        <?php foreach ($dataProvider->getModels() as $model): ?>
            <?php
            $durum = strtoupper((string)$model->ARIZANIN_SON_DURUMU);
            $durumClass = $durum === 'FAAL'
                ? 'ariza-mobile-status-faal'
                : ($durum === 'ARIZALI_FAAL' ? 'ariza-mobile-status-arizali-faal' : 'ariza-mobile-status-gayri-faal');
            ?>
            <?= Html::a(
                '<div class="d-flex justify-content-between align-items-start gap-2 mb-1">'
                    . '<div class="ariza-mobile-date">' . Html::encode(Yii::$app->formatter->asDate($model->ARIZA_BILDIRIM_TARIHI, 'php:d.m.Y')) . '</div>'
                    . '<div class="ariza-mobile-status ' . $durumClass . '">' . Html::encode((string)$model->ARIZANIN_SON_DURUMU) . '</div>'
                . '</div>'
                . '<div class="ariza-mobile-title mb-1">' . Html::encode((string)$model->ARIZALANAN_MAKINE_ADI) . '</div>'
                . '<div class="d-flex justify-content-between align-items-center gap-2">'
                    . '<div class="ariza-mobile-meta">' . Html::encode((string)$model->ARIZALANAN_MAKINE_KODU) . '</div>'
                    . '<span class="btn btn-sm btn-outline-warning py-0 px-2">Detay</span>'
                . '</div>',
                ['view', 'id' => $model->id],
                ['class' => 'ariza-mobile-card p-2']
            ) ?>
        <?php endforeach; ?>
    </div>

    <div class="ariza-mobile-pager mt-3">
        <?php if ($pagination !== false && $pageCount > 1): ?>
            <div class="ariza-mobile-pager-inner">
                <?= $pageNumber > 1 ? Html::a('Önceki', $pagination->createUrl($pageNumber - 2), ['class' => 'btn btn-outline-primary btn-sm']) : Html::tag('span', 'Önceki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
                <div class="ariza-mobile-page-status">Sayfa <?= Html::encode((string)$pageNumber) ?> / <?= Html::encode((string)$pageCount) ?></div>
                <?= $pageNumber < $pageCount ? Html::a('Sonraki', $pagination->createUrl($pageNumber), ['class' => 'btn btn-outline-primary btn-sm']) : Html::tag('span', 'Sonraki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
            </div>
            <div class="ariza-mobile-pager-extra">
                <?= $pageNumber > 1 ? Html::a('İlk sayfa', $pagination->createUrl(0), ['class' => 'btn btn-outline-secondary btn-sm']) : '' ?>
                <?= $pageNumber < $pageCount ? Html::a('Son sayfa', $pagination->createUrl($pageCount - 1), ['class' => 'btn btn-outline-secondary btn-sm']) : '' ?>
            </div>
        <?php endif; ?>
    </div>

    <?php Pjax::end(); ?>

</div>

<script>
(function () {
    var input = document.getElementById('ariza-live-search');
    if (!input) return;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#ariza-pjax',
                data: { 'ArizaTakipSearch[globalSearch]': val },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });
})();
</script>
