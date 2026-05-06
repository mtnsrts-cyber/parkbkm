<?php
use yii\helpers\Html;
use yii\grid\GridView;

$this->title = 'Kullanıcılar';
?>

<h1><?= Html::encode($this->title) ?></h1>

<p>
    <?= Html::a('Yeni Ekle', ['create'], ['class' => 'btn btn-success']) ?>
   
</p>



<?= \yii\grid\GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        'id',
        'username',
        'role',
        'status',
        'gorev',
        ['class' => 'yii\grid\ActionColumn'],
    ],
]); ?>

