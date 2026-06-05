<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;

$this->title = $name;
?>
<div class="site-error">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-danger">
        <?= nl2br(Html::encode($message)) ?>
    </div>

    <p>
        Web sunucusu isteğinizi işlerken yukarıdaki hata oluştu.
    </p>
    <p>
        Bunun bir sunucu hatası olduğunu düşünüyorsanız lütfen sistem yöneticisiyle iletişime geçin.
    </p>

</div>
