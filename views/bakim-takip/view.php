<?php

use app\models\Ekipman;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\BakimTakip $model */

$this->title = 'Bakım #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Bakım Takip Listesi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

$renderEkipmanBaglantisi = static function ($model): string {
    $ekipmanIds = array_values(array_filter((array)$model->ekipmanIds));
    if (empty($ekipmanIds)) {
        return nl2br(Html::encode((string)$model->SISTEM_CIHAZ_OZELLIK));
    }

    $ekipmanlar = Ekipman::find()
        ->where(['id' => $ekipmanIds])
        ->indexBy('id')
        ->all();

    $ozetParcalar = [];
    foreach ($ekipmanIds as $ekipmanId) {
        $ekipman = $ekipmanlar[$ekipmanId] ?? null;
        if ($ekipman === null) {
            $ozetParcalar[] = (string)$ekipmanId;
            continue;
        }

        $etiket = trim((string)$ekipman->id . ' - ' . (string)$ekipman->MALZEMENIN_TANIMI);
        if (!empty($ekipman->EKIPMAN_YERI)) {
            $etiket .= ' (' . (string)$ekipman->EKIPMAN_YERI . ')';
        }
        $ozetParcalar[] = $etiket;
    }

    if (count($ekipmanIds) === 1) {
        $tekId = $ekipmanIds[0];
        $tekEkipman = $ekipmanlar[$tekId] ?? null;
        if ($tekEkipman === null) {
            return Html::encode((string)$tekId);
        }

        return Html::a(
            Html::encode($ozetParcalar[0]),
            ['/ekipman/view', 'id' => $tekEkipman->id],
            ['class' => 'text-reset text-decoration-none']
        );
    }

    $ozellik = trim((string)$model->SISTEM_CIHAZ_OZELLIK);
    $otomatikOzet = trim(implode(', ', $ozetParcalar));
    if ($ozellik !== '' && $ozellik !== $otomatikOzet) {
        return Html::a(
            nl2br(Html::encode($ozellik)),
            Url::to(['/bakim-takip/ekipmanlar', 'id' => $model->id]),
            ['class' => 'text-reset text-decoration-none']
        );
    }

    $parcalar = [];
    foreach ($ekipmanIds as $indeks => $ekipmanId) {
        $ekipman = $ekipmanlar[$ekipmanId] ?? null;
        if ($ekipman === null) {
            $parcalar[] = Html::encode((string)$ekipmanId);
            continue;
        }

        $parcalar[] = Html::a(
            Html::encode($ozetParcalar[$indeks]),
            ['/ekipman/view', 'id' => $ekipman->id],
            ['class' => 'text-reset text-decoration-none']
        );
    }

    return implode('<br>', $parcalar);
};
?>
<div class="bakim-takip-view">

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
            'BAKIM_GENEL',
            'PERIYODIK_PLANLI',
            [
                'attribute' => 'TARIH',
                'value' => function($model){
                    return Yii::$app->formatter->asDate($model->TARIH, 'php:d.m.Y');
                }
            ],
            'BAKIM_SURESI_SAAT',
            'YERI',
            [
                'attribute' => 'SISTEM_CIHAZ_OZELLIK',
                'format' => 'raw',
                'value' => $renderEkipmanBaglantisi,
            ],
            'YAPILAN_IS:ntext',
            [
                'attribute' => 'ISI_YAPANLAR',
                'format' => 'ntext',
                'value' => function($model) {
                    return is_array($model->ISI_YAPANLAR) ? implode(', ', $model->ISI_YAPANLAR) : $model->ISI_YAPANLAR;
                }
            ],
            [
                'attribute' => 'created_by',
                'visible' => !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin',
            ],
        ],
    ]) ?>

</div>
