<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Ekipman $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="ekipman-form">

    <?php $form = ActiveForm::begin(); ?>
    <?php if ($model->isNewRecord): ?>
        <?= $form->field($model, 'id')->textInput([
            'maxlength' => 50,
            'placeholder' => 'Ekipman kodu (zorunlu)',
        ]) ?>
    <?php else: ?>
        <?= $form->field($model, 'id')->textInput([
            'maxlength' => 50,
            'readonly' => true,
        ]) ?>
    <?php endif; ?>

    <?= $form->field($model, 'MALZEMENIN_TANIMI')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'EKIPMAN_YERI')->textInput(['maxlength' => 50, 'placeholder' => 'En fazla 50 karakter']) ?>

    <!-- Kısa metin alanları (textInput) -->
    <?= $form->field($model, 'EKIPMAN_CINSI')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'EKIPMAN_TURU')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'MARKA')->textInput(['maxlength' => 25]) ?>
    <?= $form->field($model, 'SERI_NO')->textInput(['maxlength' => 25]) ?>
    <?= $form->field($model, 'TIP')->textInput(['maxlength' => 25]) ?>
    <?= $form->field($model, 'VARSA_DIGER_TANITICI_BILGI')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'MIKTAR')->textInput(['type' => 'number', 'min' => 0]) ?>
    <?= $form->field($model, 'IMAL_YILI')->textInput(['type' => 'number', 'min' => 1900, 'max' => date('Y') + 5 ,
        'placeholder' => 'Bilinmiyorsa boş bırakın',
    ]) ?>

    <?= $form->field($model, 'DURUM')->dropDownList([
        'AKTIF' => 'AKTİF',
        'HURDA' => 'HURDA',
    ], [
        'prompt' => 'Durum seçiniz...',
    ]) ?>

    <!-- Uzun metin alanları (textarea) -->
    
    <?= $form->field($model, 'NOTLAR')->textarea(['rows' => 4]) ?>

    <!-- Enerji Kaynağı -->
    <?php
    $beslemeSecenekleri = \yii\helpers\ArrayHelper::map(
        \app\models\Ekipman::find()
            ->select(['id', 'MALZEMENIN_TANIMI'])
            ->where(['!=', 'id', (string)$model->id])
            ->andWhere(['EKIPMAN_CINSI' => ['ELEKTRİK PANOLARI', 'KESİNTİSİZ GÜÇ KAYNAĞI', 'TRAFOLAR']])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all(),
        'id',
        function ($item) {
            return $item['id'] . ' — ' . $item['MALZEMENIN_TANIMI'];
        }
    );
    ?>
    <?= $form->field($model, 'besleme_kaynagi_id')->dropDownList($beslemeSecenekleri, [
        'prompt' => 'Enerji kaynağı seçiniz (opsiyonel)...',
        'class' => 'form-control',
    ]) ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'salter_kodu')->textInput(['placeholder' => 'Örn: Q5', 'maxlength' => 30]) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'salter_akim')->textInput(['placeholder' => 'Örn: 63A', 'maxlength' => 30]) ?>
        </div>
    </div>

    <!-- Koordinatlar -->
    <?= $form->field($model, 'ENLEM')->textInput(['step' => 'any', 'type' => 'number', 'placeholder' => 'Örn: 41.0082']) ?>
    <?= $form->field($model, 'BOYLAM')->textInput(['step' => 'any', 'type' => 'number', 'placeholder' => 'Örn: 28.9784']) ?>

    <div class="form-group">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>