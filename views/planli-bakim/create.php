<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PlanliBakim $model */

$this->title = 'Yeni Planlı Bakım Kaydı Oluştur';
$this->params['breadcrumbs'][] = ['label' => 'Planlı Bakımlar Yönetimi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="planli-bakim-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
