<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\PlanliBakim $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="planli-bakim-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->errorSummary($model, ['class' => 'alert alert-danger']) ?>

    <?= $form->field($model, 'kaynak_planli_id')->hiddenInput()->label(false) ?>

    <?= $form->field($model, 'kodu')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'tanimi')->textarea(['rows' => 2]) ?>

    <?= $form->field($model, 'periyodu')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'tarihi')->input('date') ?>

    <div class="card border-warning mb-3" id="planli-erteleme-card" style="display:none;">
        <div class="card-body py-2">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <?= $form->field($model, 'bakim_ertele')->checkbox() ?>
                </div>
                <div class="col-md-4" id="planli-ertelenen-tarih-wrap" style="display:none;">
                    <?= $form->field($model, 'ertelenen_tarih')->input('date') ?>
                </div>
                <div class="col-md-4 small text-muted pb-3">
                    İşaretlenirse bakım yapılmış sayılmaz; sadece ilgili planlı bakım tarihi öteleme tarihine alınır.
                </div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('İptal', ['index'], ['class' => 'btn btn-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
$this->registerJs('(function(){
    function togglePlanliErteleme(){
        var hasSource = $("#planlibakim-kaynak_planli_id").val() !== "";
        var checked = $("#planlibakim-bakim_ertele").is(":checked");

        if (hasSource) {
            $("#planli-erteleme-card").show();
        } else {
            $("#planli-erteleme-card").hide();
        }

        if (hasSource && checked) {
            $("#planli-ertelenen-tarih-wrap").show();
        } else {
            $("#planli-ertelenen-tarih-wrap").hide();
        }
    }

    $(document).on("change", "#planlibakim-bakim_ertele", togglePlanliErteleme);
    togglePlanliErteleme();
})();');
?>
