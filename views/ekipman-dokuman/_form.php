<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\EkipmanDokuman $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="ekipman-dokuman-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'ekipman_kodu')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'dokuman_turu')->dropDownList([
        'BAKIM FORMU' => 'BAKIM FORMU',
        'BAKIM TALİMATI' => 'BAKIM TALİMATI',
        'ELEKTRİK PROJESİ' => 'ELEKTRİK PROJESİ',
        'KULLANMA KLAVUZU' => 'KULLANMA KLAVUZU',
        'BROŞÜR' => 'BROŞÜR',
    ], ['prompt' => 'Seçiniz...']) ?>

    <?= $form->field($model, 'dokuman_adi')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'dosya_yolu')->textInput(['maxlength' => true])->hint('Örn: TEKNİK/ElektriProjesi/dosya.pdf') ?>

    <div class="form-group">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
        <?= Html::a('İptal', ['index'], ['class' => 'btn btn-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
