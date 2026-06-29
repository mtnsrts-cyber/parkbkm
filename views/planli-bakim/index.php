<?php

use app\models\PlanliBakim;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var app\models\PlanliBakimSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Planlı Bakımlar Yönetimi';
$this->params['breadcrumbs'][] = $this->title;
?>
<style>
.planli-desktop-grid {}
.planli-mobile-list { display: none; }
.planli-mobile-summary { display: none; }
.planli-mobile-pager { display: none; }
.planli-mobile-card {
    display: block;
    color: #fff;
    text-decoration: none;
    border: 1px solid #495057;
    border-radius: 10px;
    background: #1f2428;
}
.planli-mobile-card:hover {
    color: #fff;
    border-color: #ffc107;
    text-decoration: none;
}
.planli-mobile-code {
    color: #ffc107;
    font-weight: 800;
    white-space: nowrap;
}
.planli-mobile-status {
    color: #22c55e;
    font-weight: 800;
    font-size: .82rem;
}
.planli-mobile-title {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-weight: 700;
    line-height: 1.25;
}
.planli-mobile-meta {
    color: #cbd5e1;
    font-size: .86rem;
}
.planli-mobile-summary {
    color: #fff;
    font-weight: 700;
    font-size: .92rem;
}
.planli-mobile-pager-inner {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: .45rem;
    align-items: center;
}
.planli-mobile-page-status {
    border: 1px solid #495057;
    border-radius: 8px;
    padding: .45rem .7rem;
    color: #fff;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
    background: #1f2428;
}
.planli-mobile-pager-extra {
    display: flex;
    justify-content: center;
    gap: .45rem;
    margin-top: .45rem;
}
@media (max-width: 767.98px) {
    .planli-desktop-grid { display: none; }
    .planli-mobile-list {
        display: grid;
        gap: .55rem;
    }
    .planli-mobile-summary { display: block; }
    .planli-mobile-pager { display: block; }
}
</style>
<div class="planli-bakim-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true)): ?>
            <?= Html::a('Yeni Planlı Bakım Kaydı', ['create'], ['class' => 'btn btn-success']) ?>
        <?php endif; ?>
    </p>

    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
        <p>
            <button class="btn btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#planli-bakim-csv-import" aria-expanded="false" aria-controls="planli-bakim-csv-import">
                CSV ile Toplu Aktarım
            </button>
        </p>
        <div class="collapse" id="planli-bakim-csv-import">
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-2">CSV ile Toplu Planlı Bakım Aktarımı</h5>
                    <p class="text-muted small mb-2">
                        CSV başlıkları: <code>kodu</code>, <code>tanimi</code>, <code>periyodu</code>, <code>tarihi</code>, isteğe bağlı <code>durumu</code>.
                        Tarih formatı <code>YYYY-MM-DD</code> veya <code>GG.AA.YYYY</code> olabilir.
                    </p>
                    <?= Html::beginForm(['toplu-aktar'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'd-flex align-items-center flex-wrap mb-0']) ?>
                        <?= Html::fileInput('planli_bakim_csv', null, ['class' => 'form-control-file mr-2 mb-2', 'style' => 'max-width: 360px;', 'accept' => '.csv,.txt,text/csv', 'required' => true]) ?>
                        <?= Html::submitButton('CSV Aktar', ['class' => 'btn btn-warning btn-sm mb-2']) ?>
                        <a href="#" class="btn btn-outline-info btn-sm ml-2 mb-2" id="ornekPlanliBakimCsvIndir">Örnek CSV</a>
                    <?= Html::endForm() ?>
                </div>
            </div>
        </div>
        <script>
        document.getElementById('ornekPlanliBakimCsvIndir') && document.getElementById('ornekPlanliBakimCsvIndir').addEventListener('click', function(e) {
            e.preventDefault();
            var csv = "kodu;tanimi;periyodu;tarihi;durumu\n" +
                "ORNEK-01;Örnek planlı bakım;Periyodik: 1 Ay;16.06.2026;\n";
            var blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'});
            var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
            a.download = 'planli_bakim_toplu_aktar_ornek.csv'; a.click();
        });
        </script>
    <?php endif; ?>

    <input type="text" id="planlibakim-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Ekipman kodu, tanım, periyot, durum...)" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'planlibakim-pjax', 'enablePushState' => false]); ?>
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

    <div class="planli-desktop-grid">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table table-sm table-hover table-dark'],
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'kodu',
            'tanimi:ntext',
            [
                'attribute' => 'periyodu',
                'value' => function (PlanliBakim $model) {
                    return str_replace('Periyodik: ', '', $model->periyodu);
                },
            ],
            [
                'attribute' => 'tarihi',
                'value' => function (PlanliBakim $model) {
                    return $model->tarihi ? Yii::$app->formatter->asDate($model->tarihi, 'php:d.m.Y') : null;
                },
                'filter' => Html::input('date', 'PlanliBakimSearch[tarihi]', $searchModel->tarihi, ['class' => 'form-control']),
            ],
            'durumu',
            [
                'class' => ActionColumn::class,
                'urlCreator' => function ($action, PlanliBakim $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                },
                'visibleButtons' => [
                    'update' => function () {
                        return !Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor']);
                    },
                    'delete' => function () {
                        return !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
                    },
                    'view' => true,
                ],
            ],
        ],
    ]); ?>
    </div>

    <div class="planli-mobile-summary mb-2">
        <?= Html::encode($summaryText) ?>
    </div>

    <div class="planli-mobile-list">
        <?php foreach ($dataProvider->getModels() as $model): ?>
            <?= Html::a(
                '<div class="d-flex justify-content-between align-items-start gap-2 mb-1">'
                    . '<div class="planli-mobile-code">' . Html::encode((string)$model->kodu) . '</div>'
                    . '<div class="planli-mobile-status">' . Html::encode((string)$model->durumu) . '</div>'
                . '</div>'
                . '<div class="planli-mobile-title mb-1">' . Html::encode((string)$model->tanimi) . '</div>'
                . '<div class="d-flex justify-content-between align-items-center gap-2">'
                    . '<div class="planli-mobile-meta">' . Html::encode(str_replace('Periyodik: ', '', (string)$model->periyodu)) . ' · ' . Html::encode($model->tarihi ? Yii::$app->formatter->asDate($model->tarihi, 'php:d.m.Y') : '') . '</div>'
                    . '<span class="btn btn-sm btn-outline-warning py-0 px-2">Detay</span>'
                . '</div>',
                ['view', 'id' => $model->id],
                ['class' => 'planli-mobile-card p-2']
            ) ?>
        <?php endforeach; ?>
    </div>

    <div class="planli-mobile-pager mt-3">
        <?php if ($pagination !== false && $pageCount > 1): ?>
            <div class="planli-mobile-pager-inner">
                <?= $pageNumber > 1 ? Html::a('Önceki', $pagination->createUrl($pageNumber - 2), ['class' => 'btn btn-outline-primary btn-sm']) : Html::tag('span', 'Önceki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
                <div class="planli-mobile-page-status">Sayfa <?= Html::encode((string)$pageNumber) ?> / <?= Html::encode((string)$pageCount) ?></div>
                <?= $pageNumber < $pageCount ? Html::a('Sonraki', $pagination->createUrl($pageNumber), ['class' => 'btn btn-outline-primary btn-sm']) : Html::tag('span', 'Sonraki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
            </div>
            <div class="planli-mobile-pager-extra">
                <?= $pageNumber > 1 ? Html::a('İlk sayfa', $pagination->createUrl(0), ['class' => 'btn btn-outline-secondary btn-sm']) : '' ?>
                <?= $pageNumber < $pageCount ? Html::a('Son sayfa', $pagination->createUrl($pageCount - 1), ['class' => 'btn btn-outline-secondary btn-sm']) : '' ?>
            </div>
        <?php endif; ?>
    </div>

    <?php Pjax::end(); ?>

</div>

<script>
(function () {
    var input = document.getElementById('planlibakim-live-search');
    if (!input) return;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#planlibakim-pjax',
                data: { 'PlanliBakimSearch[globalSearch]': val },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });
})();
</script>
