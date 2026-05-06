<?php
use yii\helpers\Html;
use yii\grid\GridView;

$this->title = 'Ekipman Listesi';
?>
<style>
.table-hover tbody tr:hover {
    background-color: #f5faff !important;
    cursor: pointer;
}
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid #ddd;
}
.column-buttons {
    margin-bottom: 10px;
}
.column-buttons label {
    margin-right: 12px;
    cursor: pointer;
}
#live-search {
    margin-bottom: 15px;
    padding: 10px;
    font-size: 16px;
}
</style>

<h1><?= Html::encode($this->title) ?></h1>

<p>
    
    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
    <?= Html::a('Yeni Ekle', ['create'], ['class' => 'btn btn-success']) ?>
<?php endif; ?>

    <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor'])): ?>
        <?= Html::a('Excel İndir', ['export-excel'], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('PDF İndir', ['export-pdf'], ['class' => 'btn btn-danger']) ?>
        <button type="button" class="btn btn-outline-warning btn-sm ml-2" onclick="document.getElementById('enerjiAktarPanel').style.display = document.getElementById('enerjiAktarPanel').style.display==='none' ? 'block':'none'">
            ⚡ Enerji Kaynağı Toplu Aktar
        </button>
    <?php endif; ?>
</p>

<!-- Enerji Kaynağı CSV Aktarım Paneli -->
<div id="enerjiAktarPanel" style="display:none;" class="alert alert-secondary mb-3">
    <strong>⚡ Enerji Kaynağı Toplu Aktarım</strong>
    <p class="small mb-2">CSV/TXT dosyası yükleyin. Format: <code>ekipman_id;enerji_kaynagi_id;salter_kodu;salter_akim</code> (noktalı virgül, virgül veya tab ayraç)<br>
    Şalter sütunları opsiyoneldir. İlişkiyi silmek için ikinci sütuna boş, <code>-</code> veya <code>null</code> yazın. İlk satır başlık ise atlanır.</p>
    <?= Html::beginForm(['ekipman/enerji-kaynagi-aktar'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'd-flex align-items-center']) ?>
        <input type="file" name="csv_file" accept=".csv,.txt" class="form-control-file mr-2" style="max-width: 300px;" required>
        <button type="submit" class="btn btn-warning btn-sm">Yükle</button>
        <a href="#" class="btn btn-outline-info btn-sm ml-2" id="ornekCsvIndir">Örnek CSV</a>
    <?= Html::endForm() ?>
</div>
<script>
document.getElementById('ornekCsvIndir') && document.getElementById('ornekCsvIndir').addEventListener('click', function(e) {
    e.preventDefault();
    var csv = "ekipman_id;enerji_kaynagi_id;salter_kodu;salter_akim\nESNT-ADP-02;ESNT-TR-04;Q5;63A\nRHT-SP-01;ESNT-ADP-02;Q3;32A\nXYZ-01;-;;\n";
    var blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'});
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'enerji_kaynagi_ornek.csv'; a.click();
});
</script>


<!-- Canlı Arama Input -->
<input type="text" id="live-search" class="form-control" placeholder="🔍 Ara... (Kodu, Tanım, Yeri, Marka, Seri)" autocomplete="off">



<?= \app\widgets\PageSizeWidget::widget() ?>

<?php \yii\widgets\Pjax::begin(['id' => 'ekipman-pjax', 'enablePushState' => false]); ?>
    <?= GridView::widget([
        'id' => 'ekipman-grid',
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table table-sm table-hover table-dark'],
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            
             [
                'attribute' => 'id',
                'label' =>'Kodu'
            ],

            [
                'attribute' => 'MALZEMENIN_TANIMI',
                'label' =>'Tanımı'
            ],

            [
                'attribute' => 'EKIPMAN_YERI',
                'label' => '📍 Ekipman Yeri'
            ],

            [
                'attribute' => 'DURUM',
                'label' => 'Durum',
                'filter' => ['AKTIF' => 'AKTİF', 'HURDA' => 'HURDA'],
                'value' => function ($model) {
                    return strtoupper((string)$model->DURUM) === 'HURDA' ? 'HURDA' : 'AKTİF';
                },
            ],
           // [
           //     'attribute' => 'MARKA',
           //     'label' => '🏷 Marka'
           // ],
         //   [
         //       'attribute' => 'SERI_NO',
         //       'label' => '🔢 Seri No'
         //    ],

           [
    'class' => 'yii\grid\ActionColumn',
    'template' => '{view}', // sadece view gösterilir
            ],

        ],
    ]); ?>
<?php \yii\widgets\Pjax::end(); ?>

<script>

const idListFilter = new URLSearchParams(window.location.search).get('EkipmanSearch[idList]') || '';


// Canlı Arama - Düzeltilmiş versiyon
let searchTimeout;
document.getElementById("live-search").addEventListener("keyup", function() {
    clearTimeout(searchTimeout);
    
    let searchValue = this.value.trim();
    
    searchTimeout = setTimeout(function() {
        const requestData = {};

        $("#ekipman-grid-filters").find("input, select").each(function() {
            const name = this.name;
            if (!name) {
                return;
            }

            const value = $(this).val();
            if (value !== null && value !== "") {
                requestData[name] = value;
            }
        });

        requestData['EkipmanSearch[globalSearch]'] = searchValue;

        if (idListFilter) {
            requestData['EkipmanSearch[idList]'] = idListFilter;
        }

        $.pjax.reload({
            container: "#ekipman-pjax",
            data: requestData,
            replace: false,
            push: false,
            timeout: 5000
        });
    }, 300); // 300ms gecikme - çok hızlı yazarken sürekli istek atmaz
});


</script>


 

