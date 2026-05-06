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
<div class="planli-bakim-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Yeni Planlı Bakım Kaydı', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <input type="text" id="planlibakim-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Ekipman kodu, tanım, periyot, durum...)" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'planlibakim-pjax', 'enablePushState' => false]); ?>

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
