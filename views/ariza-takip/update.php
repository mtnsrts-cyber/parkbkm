<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\ArizaTakip $model */

$this->title = 'Arıza Güncelle: #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Arıza Takip Listesi', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Arıza #' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Güncelle';
?>
<div class="ariza-takip-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
