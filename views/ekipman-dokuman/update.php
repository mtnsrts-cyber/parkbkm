<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\EkipmanDokuman $model */

$this->title = 'Döküman Güncelle: ' . $model->dokuman_adi;
$this->params['breadcrumbs'][] = ['label' => 'Ekipman Döküman Yönetimi', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->dokuman_adi, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Güncelle';
?>
<div class="ekipman-dokuman-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
