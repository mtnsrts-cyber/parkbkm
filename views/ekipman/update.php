<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Ekipman $model */

$this->title = 'Ekipman Güncelle: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Ekipman', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Güncelle';
?>
<div class="ekipman-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
