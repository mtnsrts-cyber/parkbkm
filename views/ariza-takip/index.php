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
<div class="ariza-takip-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($searchModel->quickFilter === 'open'): ?>
        <div class="alert alert-warning py-2">KPI filtresi aktif: Açık arızalar listeleniyor.</div>
    <?php elseif ($searchModel->quickFilter === 'this-month'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Bu ay açılan arızalar listeleniyor.</div>
    <?php endif; ?>

    <p>
        <?php if (!Yii::$app->user->isGuest): ?>
            <?= Html::a('Yeni Arıza Kaydı', ['create'], ['class' => 'btn btn-success']) ?>
        <?php endif; ?>

        <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor'])): ?>
            <?= Html::a('Excel İndir', array_merge(['export-excel'], Yii::$app->request->queryParams), ['class' => 'btn btn-primary', 'data-pjax' => 0]) ?>
        <?php endif; ?>
    </p>

    <input type="text" id="ariza-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Makine adı, kodu, bildiren, bölüm, durum...)" value="<?= Html::encode($searchModel->globalSearch ?? '') ?>" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'ariza-pjax', 'enablePushState' => false]); ?>

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
