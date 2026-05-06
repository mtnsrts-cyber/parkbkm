<?php
use yii\bootstrap5\Modal;
use yii\bootstrap5\Html;

/** @var $model app\models\Ekipman */
?>

<h3><?= Html::encode($model->ADI) ?></h3>
<p><b>Marka:</b> <?= $model->MARKA ?></p>
<p><b>Seri No:</b> <?= $model->SERI_NO ?></p>

<?= Html::button('Bakım Kaydı Oluştur', [
    'class' => 'btn btn-warning',
    'id' => 'btnYeniBakim'
]) ?>

<?php
Modal::begin([
    'title' => 'Bakım Kaydı Oluştur',
    'id' => 'modalBakim',
    'size' => Modal::SIZE_LARGE,
]);
?>
<div id="modalBakimContent"></div>
<?php Modal::end(); ?>

<?php
$createUrl = \yii\helpers\Url::to(['bakim/create', 'id' => $model->ID]);

$script = <<<JS
$('#btnYeniBakim').on('click', function() {
    $('#modalBakim').modal('show')
        .find('#modalBakimContent')
        .load('$createUrl');
});
JS;

$this->registerJs($script);
?>
