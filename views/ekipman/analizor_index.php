<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Enerji Analizörleri';
?>

<div class="container-fluid mt-3">
    <h4 class="mb-3" style="color:#38bdf8; border-bottom:2px solid #334155; padding-bottom:10px;">
        ⚡ Enerji Analizörleri Yönetimi
    </h4>

    <p>
        <?= Html::a('+ Yeni Analizör', ['ekipman/analizor-create'], ['class' => 'btn btn-success']) ?>
    </p>

    <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white">
            <thead class="thead-dark">
                <tr>
                    <th>Ekipman Kodu</th>
                    <th>IP Adresi</th>
                    <th>Port</th>
                    <th>Device ID</th>
                    <th>Model</th>
                    <th>Açıklama</th>
                    <th>Aktif</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($models as $m): ?>
                <tr>
                    <td><?= Html::encode($m->ekipman_kodu) ?></td>
                    <td><?= Html::encode($m->ip) ?></td>
                    <td><?= $m->port ?></td>
                    <td><?= $m->device_id ?></td>
                    <td><?= Html::encode($m->model) ?></td>
                    <td><?= Html::encode($m->aciklama) ?></td>
                    <td>
                        <?php if ($m->aktif): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Pasif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= Html::a('Düzenle', ['ekipman/analizor-update', 'id' => $m->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                        <?= Html::a('Sil', ['ekipman/analizor-delete', 'id' => $m->id], [
                            'class' => 'btn btn-sm btn-outline-danger',
                            'data' => [
                                'confirm' => 'Bu analizörü silmek istediğinize emin misiniz?',
                                'method' => 'post',
                            ],
                        ]) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($models)): ?>
        <div class="alert alert-info">Henüz eklenmiş analizör yok. Yeni analizör eklemek için butona tıklayın.</div>
    <?php endif; ?>
</div>