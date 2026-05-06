<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\ArizaTakip $model */

$this->title = 'Arıza #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Arıza Takip Listesi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="ariza-takip-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
            <?= Html::a('Güncelle', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Sil', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'Bu kaydı silmek istediğinizden emin misiniz?',
                    'method' => 'post',
                ],
            ]) ?>
        <?php endif; ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'ARIZA_BILDIRIM_TARIHI',
                'value' => function($model){
                    return Yii::$app->formatter->asDate($model->ARIZA_BILDIRIM_TARIHI, 'php:d.m.Y');
                }
            ],
            [
                'attribute' => 'ARIZA_TARIHI',
                'value' => function($model){
                    return Yii::$app->formatter->asDate($model->ARIZA_TARIHI, 'php:d.m.Y');
                }
            ],
            'ARIZAYI_BILDIREN',
            'ARIZAYA_SEBEBIYET_VEREN_FIRMA',
            'ARIZALANAN_MAKINE_ADI',
            'ARIZALANAN_MAKINE_KODU',
            'ARIZALANAN_PARCA',
            'ARIZANIN_MEYDANA_GELDIGI_BOLUM',
            'ARIZA_KOK_NEDENI:ntext',
            'KALICI_AKSIYON:ntext',
            'ARIZA_SEBEBI:ntext',
            [
                'attribute' => 'ARIZANIN_GIDERILDIGI_TARIH',
                'value' => function($model){
                    return $model->ARIZANIN_GIDERILDIGI_TARIH
                        ? Yii::$app->formatter->asDate($model->ARIZANIN_GIDERILDIGI_TARIH, 'php:d.m.Y')
                        : null;
                }
            ],
            'ARIZANIN_SON_DURUMU',
            'ARIZALI_KALDIGI_SURE_SAAT',
            'YEDEK_PARCA_BEKLEME_SURESI_SAAT',
            [
                'attribute' => 'MALZEME_TUTARI',
                'value' => function($model) {
                    return $model->MALZEME_TUTARI !== null
                        ? number_format((float)$model->MALZEME_TUTARI, 2, ',', '.') . ' ₺'
                        : null;
                },
            ],
            [
                'attribute' => 'ISCILIK_FIYATI',
                'value' => function($model) {
                    return $model->ISCILIK_FIYATI !== null
                        ? number_format((float)$model->ISCILIK_FIYATI, 2, ',', '.') . ' ₺'
                        : null;
                },
            ],
            [
                'attribute' => 'MALIYET_TL',
                'value' => function($model) {
                    return $model->MALIYET_TL !== null
                        ? number_format((float)$model->MALIYET_TL, 2, ',', '.') . ' ₺'
                        : null;
                },
            ],
            'ARIZANIN_AYRINTILI_ACIKLAMASI:ntext',
            [
                'attribute' => 'created_by',
                'visible' => !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin',
            ],
        ],
    ]) ?>

</div>
