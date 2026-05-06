<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var app\models\EkipmanDokumanSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Ekipman Döküman Yönetimi';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ekipman-dokuman-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Yeni Döküman', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <input type="text" id="dokuman-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Ekipman kodu, döküman türü, adı...)" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'dokuman-pjax', 'enablePushState' => false]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\\grid\\SerialColumn'],
            'ekipman_kodu',
            'dokuman_turu',
            'dokuman_adi',
            'dosya_yolu',
            [
                'class' => 'yii\\grid\\ActionColumn',
            ],
        ],
    ]); ?>

    <?php Pjax::end(); ?>

</div>

<script>
(function () {
    var input = document.getElementById('dokuman-live-search');
    if (!input) return;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#dokuman-pjax',
                data: { 'EkipmanDokumanSearch[globalSearch]': val },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });
})();
</script>
