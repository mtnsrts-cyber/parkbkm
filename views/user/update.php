<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Kullanıcı Güncelle: ' . $model->username;
$this->params['breadcrumbs'][] = ['label' => 'Users', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Güncelle';
?>
<div class="user-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="user-form">

        <?php $form = ActiveForm::begin(); ?>

        <?= $form->field($model, 'username')->textInput(['maxlength' => true]) ?>

        <?= $form->field($model, 'password')->passwordInput(['value' => '']) ?>

        <?= $form->field($model, 'gorev')->dropDownList(['Elektrik' => 'Elektrik', 'Mekanik' => 'Mekanik', 'Diğer' => 'Diğer']); ?>
        
        <small>Şifreyi değiştirmek istemiyorsanız boş bırakın.</small>

        <?= $form->field($model, 'role')->dropDownList([
            'admin' => 'Admin',
            'editor' => 'Editor',
            'user' => 'User',
        ]) ?>

        <div class="form-group">
            <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
        </div>

        <?php ActiveForm::end(); ?>

    </div>

</div>
