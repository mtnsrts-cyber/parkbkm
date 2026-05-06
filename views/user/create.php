<?php $form = \yii\widgets\ActiveForm::begin(); ?>

<?= $form->field($model, 'username')->textInput(); ?>
<?= $form->field($model, 'password_hash')->passwordInput(); ?>
<?= $form->field($model, 'role')->dropDownList(['user' => 'User', 'admin' => 'Admin', 'editor' => 'Editor']); ?>
<?= $form->field($model, 'gorev')->dropDownList(['Elektrik' => 'Elektrik', 'Mekanik' => 'Mekanik', 'Diğer' => 'Diğer']); ?>
<button class="btn btn-success">Kaydet</button>

<?php \yii\widgets\ActiveForm::end(); ?>

