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
@media (max-width: 767.98px) {
    .planli-desktop-grid { display: none; }
    .planli-mobile-list {
        display: grid;
        gap: .55rem;
    }
}
</style>
<div class="planli-bakim-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true)): ?>
            <?= Html::a('Yeni Planlı Bakım Kaydı', ['create'], ['class' => 'btn btn-success']) ?>
        <?php endif; ?>
    </p>

    <input type="text" id="planlibakim-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Ekipman kodu, tanım, periyot, durum...)" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'planlibakim-pjax', 'enablePushState' => false]); ?>

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
