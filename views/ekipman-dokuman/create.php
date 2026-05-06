<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\EkipmanDokuman $model */

$this->title = 'Yeni Döküman Ekle';
$this->params['breadcrumbs'][] = ['label' => 'Ekipman Döküman Yönetimi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ekipman-dokuman-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
