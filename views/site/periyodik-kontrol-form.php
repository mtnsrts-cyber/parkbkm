<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\PeriyodikKontrol $model */
/** @var string|null $return */

$this->title = 'Periyodik Kontrol Düzenle';
$this->params['breadcrumbs'][] = ['label' => 'Periyodik Kontroller Listesi', 'url' => ['periyodik-kontroller']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="periyodik-kontrol-form-page">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card bg-dark border-secondary">
        <div class="card-body">
            <?php $form = ActiveForm::begin(); ?>

            <div class="row">
                <div class="col-md-4">
                    <?= $form->field($model, 'ekipman_id')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-md-8">
                    <?= $form->field($model, 'cihaz_adi')->textInput(['maxlength' => true]) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <?= $form->field($model, 'rapor_no')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'adet')->input('number') ?>
                </div>
            </div>

            <?= $form->field($model, 'bulundugu_yer')->textInput(['maxlength' => true]) ?>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'kabul_degerleri')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'olcum_degerleri')->textInput(['maxlength' => true]) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'son_kontrol_tarihi')->input('date') ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'gelecek_kontrol_tarihi')->input('date') ?>
                </div>
            </div>

            <div class="form-group mb-0">
                <?= Html::submitButton('Kaydet', ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Vazgeç', $return ?: ['periyodik-kontroller'], ['class' => 'btn btn-secondary ml-2']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
