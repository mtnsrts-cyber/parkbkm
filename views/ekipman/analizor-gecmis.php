<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use yii\widgets\ActiveForm;

$this->title = $model->id . ' — Enerji Analizörü Geçmiş Verileri';
$this->params['breadcrumbs'][] = ['label' => 'Ekipman', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Enerji Geçmişi';

$this->registerCssFile("https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css");
$this->registerJsFile("https://code.jquery.com/jquery-3.6.0.min.js", ['position' => \yii\web\View::POS_HEAD]);
$this->registerJsFile("https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js");
?>

<div class="analizor-gecmis">

    <div class="d-flex align-items-center mb-3">
        <h3 class="mb-0">
            <span style="color:#e94560">⚡</span>
            <?= Html::encode($model->id) ?> — <?= Html::encode($model->MALZEMENIN_TANIMI) ?>
        </h3>
        <span class="badge badge-info ml-3"><?= Html::encode($analizorConfig['model']) ?></span>
        <?= Html::a('← Ekipman Sayfası', ['view', 'id' => $model->id, '#' => 'enerji'], ['class' => 'btn btn-sm btn-outline-secondary ml-auto']) ?>
    </div>

    <p class="text-muted"><?= Html::encode($analizorConfig['aciklama']) ?></p>

    <!-- Filtre -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['analizor-gecmis', 'id' => $model->id], 'options' => ['class' => 'form-inline']]); ?>
                <label class="mr-2">Başlangıç:</label>
                <input type="date" name="AnalizorOlcumSearch[tarih_baslangic]" value="<?= Html::encode($searchModel->tarih_baslangic) ?>" class="form-control form-control-sm mr-3">
                <label class="mr-2">Bitiş:</label>
                <input type="date" name="AnalizorOlcumSearch[tarih_bitis]" value="<?= Html::encode($searchModel->tarih_bitis) ?>" class="form-control form-control-sm mr-3">
                <button type="submit" class="btn btn-sm btn-primary mr-2">Filtrele</button>
                <?= Html::a('Temizle', ['analizor-gecmis', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <!-- Günlük Özet -->
    <?php if (!empty($gunlukOzet)): ?>
    <div class="card mb-3">
        <div class="card-header bg-dark text-white py-2">
            <strong>📊 Günlük Özet (Son 30 Gün)</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Tarih</th>
                        <th class="text-right">Ort. kW</th>
                        <th class="text-right">Max kW</th>
                        <th class="text-right">Min kW</th>
                        <th class="text-right">Ort. PF</th>
                        <th class="text-right">Ort. Hz</th>
                        <th class="text-right">Günlük Tüketim (kWh)</th>
                        <th class="text-right">Ölçüm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gunlukOzet as $g): ?>
                    <tr>
                        <td><strong><?= Html::encode($g['tarih']) ?></strong></td>
                        <td class="text-right"><?= number_format((float)$g['ort_kw'], 1) ?></td>
                        <td class="text-right text-danger"><?= number_format((float)$g['max_kw'], 1) ?></td>
                        <td class="text-right text-success"><?= number_format((float)$g['min_kw'], 1) ?></td>
                        <td class="text-right"><?= number_format((float)$g['ort_pf'], 3) ?></td>
                        <td class="text-right"><?= number_format((float)$g['ort_freq'], 2) ?></td>
                        <td class="text-right font-weight-bold">
                            <?php
                                $tuketim = (float)$g['max_kwh'] - (float)$g['min_kwh'];
                                echo $tuketim > 0 ? number_format($tuketim, 1) : '-';
                            ?>
                        </td>
                        <td class="text-right text-muted"><?= (int)$g['olcum_sayisi'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detay Tablo -->
    <div class="card">
        <div class="card-header bg-dark text-white py-2">
            <strong>📋 Tüm Ölçümler</strong>
            <span class="badge badge-light ml-2"><?= $dataProvider->getTotalCount() ?> kayıt</span>
        </div>

        <?php
        // Kolon varlik kontrolunu sadece aktif sayfaya gore degil,
        // secili tarih araligindaki tum kayitlara gore yap.
        $models = method_exists($dataProvider, 'getAllModels')
            ? $dataProvider->getAllModels()
            : $dataProvider->getModels();
        $hasAnyValue = function(array $rows, string $attr): bool {
            foreach ($rows as $row) {
                if (isset($row[$attr]) && $row[$attr] !== null && $row[$attr] !== '') {
                    return true;
                }
            }
            return false;
        };

        $optionalColumns = [
            'v_l1l2' => [
                'attribute' => 'v_l1l2',
                'label' => 'V L1-L2',
                'format' => ['decimal', 1],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            'v_l2l3' => [
                'attribute' => 'v_l2l3',
                'label' => 'V L2-L3',
                'format' => ['decimal', 1],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            'v_l3l1' => [
                'attribute' => 'v_l3l1',
                'label' => 'V L3-L1',
                'format' => ['decimal', 1],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            'p_total_kw' => [
                'attribute' => 'p_total_kw',
                'label' => 'P (kW)',
                'format' => ['decimal', 2],
                'contentOptions' => ['class' => 'text-right font-weight-bold'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            's_total_kva' => [
                'attribute' => 's_total_kva',
                'label' => 'S (kVA)',
                'format' => ['decimal', 2],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            'i_avg_a' => [
                'attribute' => 'i_avg_a',
                'label' => 'I (A)',
                'format' => ['decimal', 1],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            'freq' => [
                'attribute' => 'freq',
                'label' => 'Hz',
                'format' => ['decimal', 2],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
            'pf_avg' => [
                'attribute' => 'pf_avg',
                'label' => 'PF',
                'format' => ['decimal', 3],
                'contentOptions' => ['class' => 'text-right'],
                'headerOptions' => ['class' => 'text-right'],
            ],
        ];

        $columns = [
            [
                'attribute' => 'created_at',
                'label' => 'Zaman',
                'format' => ['datetime', 'php:d.m.Y H:i:s'],
                'headerOptions' => ['style' => 'white-space:nowrap'],
            ],
        ];

        $hiddenColumns = [];
        foreach ($optionalColumns as $attr => $config) {
            if ($hasAnyValue($models, $attr)) {
                $columns[] = $config;
            } else {
                $hiddenColumns[] = $attr;
            }
        }

        $columns[] = [
            'attribute' => 'e_import_total_kwh',
            'label' => 'Σ kWh',
            'format' => ['decimal', 1],
            'contentOptions' => ['class' => 'text-right font-weight-bold text-primary'],
            'headerOptions' => ['class' => 'text-right'],
        ];
        ?>

        <?php if (!empty($hiddenColumns)): ?>
        <div class="alert alert-warning mb-0 rounded-0">
            Bu tarih aralığında bazı ölçümler mevcut değil. Sadece dolu sütunlar gösteriliyor.
        </div>
        <?php endif; ?>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-sm table-striped table-hover mb-0'],
            'headerRowOptions' => ['class' => 'thead-light'],
            'columns' => $columns,
        ]) ?>
    </div>
</div>
