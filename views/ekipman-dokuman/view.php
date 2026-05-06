<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\EkipmanDokuman $model */

$this->title = $model->dokuman_adi;
$this->params['breadcrumbs'][] = ['label' => 'Ekipman Döküman Yönetimi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ekipman-dokuman-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Güncelle', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Sil', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Bu kaydı silmek istiyor musunuz?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'ekipman_kodu',
            'dokuman_turu',
            'dokuman_adi',
            'dosya_yolu',
            'created_at',
            'updated_at',
        ],
    ]) ?>

</div>
