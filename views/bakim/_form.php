<?php
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
?>

<div class="card p-3">
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'ekipman_id')->textInput() ?>
<?= $form->field($model, 'baslik')->textInput() ?>
<?= $form->field($model, 'aciklama')->textarea(['rows' => 4]) ?>
<?= $form->field($model, 'durum')->dropDownList([
    'Açık' => 'Açık',
    'Atölyede' => 'Atölyede',
    'Tamamlandı' => 'Tamamlandı'
]) ?>

<div class="form-group mt-3">
    <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
</div>

<?php ActiveForm::end(); ?>
</div>
