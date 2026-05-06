<?php
use yii\grid\GridView;
use yii\bootstrap5\Html;
use yii\widgets\Pjax;

$this->title = "Bakım Kayıtları";
?>

<div class="d-flex justify-content-between mb-3">
    <h3>Bakım Kayıtları</h3>
    <?= Html::a('Yeni Bakım Kaydı', ['create'], ['class' => 'btn btn-warning']) ?>
</div>

<input type="text" id="bakim-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Başlık, ekipman, durum...)" autocomplete="off">

<?= \app\widgets\PageSizeWidget::widget() ?>

<?php Pjax::begin(['id' => 'bakim-pjax', 'enablePushState' => false]); ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'columns' => [
        'id',
        'ekipman_id',
        'baslik',
        'durum',
        ['class' => 'yii\grid\ActionColumn'],
    ],
]); ?>

<?php Pjax::end(); ?>

<script>
(function () {
    var input = document.getElementById('bakim-live-search');
    if (!input) return;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#bakim-pjax',
                data: { 'BakimSearch[globalSearch]': val },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });
})();
</script>
