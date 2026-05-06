<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\BakimTakip $model */

$this->title = 'Bakım Güncelle: #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Bakım Takip Listesi', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Bakım #' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Güncelle';
?>
<div class="bakim-takip-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
