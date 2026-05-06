<?php

use app\models\Ekipman;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\BakimTakip $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Bakım #' . $model->id . ' - İlgili Ekipmanlar';
$this->params['breadcrumbs'][] = ['label' => 'Bakım Takip Listesi', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Bakım #' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="bakim-takip-ekipmanlar">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-info py-2">
        <strong>Ortak kayıt:</strong> <?= Html::encode((string)$model->SISTEM_CIHAZ_OZELLIK) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'summary' => '',
        'tableOptions' => ['class' => 'table table-sm table-hover table-dark'],
        'columns' => [
            [
                'attribute' => 'id',
                'label' => 'Ekipman Kodu',
                'format' => 'raw',
                'value' => static function (Ekipman $row) {
                    return Html::a(
                        Html::encode((string)$row->id),
                        ['/ekipman/view', 'id' => $row->id],
                        ['class' => 'text-white text-decoration-none']
                    );
                },
            ],
            'MALZEMENIN_TANIMI:ntext',
            'EKIPMAN_YERI:ntext',
            'EKIPMAN_CINSI:ntext',
            'EKIPMAN_TURU:ntext',
        ],
        'emptyText' => '<span class="text-muted">Bu bakım kaydı için ilişkilendirilmiş ekipman bulunamadı.</span>',
    ]) ?>
</div>
