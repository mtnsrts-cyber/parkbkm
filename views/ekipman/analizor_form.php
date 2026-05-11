<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = $title;
?>

<div class="container-fluid mt-3">
    <h4 class="mb-3" style="color:#38bdf8; border-bottom:2px solid #334155; padding-bottom:10px;">
        <?= $title ?>
    </h4>

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'ekipman_kodu')->textInput(['maxlength' => 30]) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'model')->textInput(['maxlength' => 50]) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <?= $form->field($model, 'ip')->textInput(['maxlength' => 15, 'placeholder' => '192.168.1.100']) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'port')->textInput(['value' => 502, 'type' => 'number']) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'device_id')->textInput(['type' => 'number', 'min' => 1, 'max' => 255]) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?= $form->field($model, 'aciklama')->textarea(['rows' => 2]) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?= $form->field($model, 'aktif')->checkbox() ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
        <?= Html::a('İptal', ['ekipman/analizor-index'], ['class' => 'btn btn-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>