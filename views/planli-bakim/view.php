<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\PlanliBakim $model */

$this->title = 'Planlı Bakım Detayı #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Planlı Bakımlar Yönetimi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="planli-bakim-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Güncelle', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
            <?= Html::a('Sil', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'Bu kaydı silmek istediğinize emin misiniz?',
                    'method' => 'post',
                ],
            ]) ?>
        <?php endif; ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'kodu',
            'tanimi:ntext',
            'periyodu',
            'tarihi',
            'durumu',
        ],
    ]) ?>

</div>
