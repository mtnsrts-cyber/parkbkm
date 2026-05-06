<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PlanliBakim $model */

$this->title = 'Planlı Bakım Kaydını Güncelle: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Planlı Bakımlar Yönetimi', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Güncelle';
?>
<div class="planli-bakim-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
