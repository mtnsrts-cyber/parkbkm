<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\BakimTakip $model */

$this->title = 'Yeni Bakım Kaydı';
$this->params['breadcrumbs'][] = ['label' => 'Bakım Takip Listesi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="bakim-takip-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
