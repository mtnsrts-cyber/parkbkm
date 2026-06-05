<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\models\ContactForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\captcha\Captcha;

$this->title = 'İletişim';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-contact">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (Yii::$app->session->hasFlash('contactFormSubmitted')): ?>

        <div class="alert alert-success">
            Bizimle iletişime geçtiğiniz için teşekkür ederiz. En kısa sürede yanıt vereceğiz.
        </div>

        <p>
            Yii hata ayıklayıcı açıksa e-posta içeriğini hata ayıklayıcıdaki mail panelinden görebilirsiniz.
            <?php if (Yii::$app->mailer->useFileTransport): ?>
                Uygulama geliştirme modunda olduğu için e-posta gönderilmedi; bunun yerine
                <code><?= Yii::getAlias(Yii::$app->mailer->fileTransportPath) ?></code> altında dosya olarak kaydedildi.
                E-posta gönderimini etkinleştirmek için <code>mail</code> bileşenindeki <code>useFileTransport</code>
                ayarını <code>false</code> yapın.
            <?php endif; ?>
        </p>

    <?php else: ?>

        <p>
            Sorularınız için aşağıdaki formu doldurarak bizimle iletişime geçebilirsiniz.
        </p>

        <div class="row">
            <div class="col-lg-5">

                <?php $form = ActiveForm::begin(['id' => 'contact-form']); ?>

                    <?= $form->field($model, 'name')->textInput(['autofocus' => true]) ?>

                    <?= $form->field($model, 'email') ?>

                    <?= $form->field($model, 'subject') ?>

                    <?= $form->field($model, 'body')->textarea(['rows' => 6]) ?>

                    <?= $form->field($model, 'verifyCode')->widget(Captcha::class, [
                        'template' => '<div class="row"><div class="col-lg-3">{image}</div><div class="col-lg-6">{input}</div></div>',
                    ]) ?>

                    <div class="form-group">
                        <?= Html::submitButton('Gönder', ['class' => 'btn btn-primary', 'name' => 'contact-button']) ?>
                    </div>

                <?php ActiveForm::end(); ?>

            </div>
        </div>

    <?php endif; ?>
</div>
