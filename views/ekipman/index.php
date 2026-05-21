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
        <button type="button" class="btn btn-success ml-1" onclick="document.getElementById('ekipmanAktarPanel').style.display = document.getElementById('ekipmanAktarPanel').style.display==='none' ? 'block':'none'">
            Toplu Ekipman Aktar
        </button>
<?php endif; ?>

    <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor'])): ?>
        <?= Html::a('Excel İndir', ['export-excel'], ['class' => 'btn btn-primary ml-1']) ?>
        <?= Html::a('PDF İndir', ['export-pdf'], ['class' => 'btn btn-danger ml-1']) ?>
        <button type="button" class="btn btn-warning ml-1" onclick="document.getElementById('enerjiAktarPanel').style.display = document.getElementById('enerjiAktarPanel').style.display==='none' ? 'block':'none'">
            Enerji Kaynağı Toplu Aktar
        </button>
    <?php endif; ?>
</p>

<?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
<!-- Ekipman Excel/CSV Aktarım Paneli -->
<div id="ekipmanAktarPanel" style="display:none;" class="card bg-dark border-secondary mb-3">
    <div class="card-body py-3">
        <div class="font-weight-bold mb-2">Toplu Ekipman Aktar</div>
        <p class="small text-muted mb-3">
            Excel/CSV dosyası yükleyin. Aynı ekipman kodu varsa mevcut kayıt korunur, sadece yeni ekipmanlar eklenir.<br>
            Beklenen başlıklar: <code>ID</code>, <code>EKİPMAN YERİ</code>, <code>EKİPMAN CİNSİ</code>, <code>EKİPMAN TÜRÜ</code>, <code>MALZEMENİN TANIMI</code>, <code>MARKA</code>, <code>SERİ NO</code>, <code>TİP</code>, <code>VARSA DİĞER TANITICI BİLGİ</code>, <code>MİKTAR</code>, <code>İMAL YILI</code>, <code>NOTLAR</code>.
        </p>
        <?= Html::beginForm(['ekipman/toplu-aktar'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'd-flex align-items-center flex-wrap mb-0']) ?>
            <input type="file" name="ekipman_excel" accept=".xlsx,.xls,.csv" class="form-control-file mr-2 mb-2" style="max-width: 360px;" required>
            <button type="submit" class="btn btn-success btn-sm mb-2">Yükle</button>
            <a href="#" class="btn btn-outline-info btn-sm ml-2 mb-2" id="ornekEkipmanCsvIndir">Örnek CSV</a>
        <?= Html::endForm() ?>
    </div>
</div>
<?php endif; ?>

<!-- Enerji Kaynağı CSV Aktarım Paneli -->
<div id="enerjiAktarPanel" style="display:none;" class="card bg-dark border-secondary mb-3">
    <div class="card-body py-3">
        <div class="font-weight-bold mb-2">Enerji Kaynağı Toplu Aktarım</div>
        <p class="small text-muted mb-3">CSV/TXT dosyası yükleyin. Format: <code>ekipman_id;enerji_kaynagi_id;salter_kodu;salter_akim</code> (noktalı virgül, virgül veya tab ayraç)<br>
        Şalter sütunları opsiyoneldir. İlişkiyi silmek için ikinci sütuna boş, <code>-</code> veya <code>null</code> yazın. İlk satır başlık ise atlanır.</p>
        <?= Html::beginForm(['ekipman/enerji-kaynagi-aktar'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'd-flex align-items-center flex-wrap mb-0']) ?>
            <input type="file" name="csv_file" accept=".csv,.txt" class="form-control-file mr-2 mb-2" style="max-width: 360px;" required>
            <button type="submit" class="btn btn-warning btn-sm mb-2">Yükle</button>
            <a href="#" class="btn btn-outline-info btn-sm ml-2 mb-2" id="ornekCsvIndir">Örnek CSV</a>
        <?= Html::endForm() ?>
    </div>
</div>
<script>
document.getElementById('ornekCsvIndir') && document.getElementById('ornekCsvIndir').addEventListener('click', function(e) {
    e.preventDefault();
    var csv = "ekipman_id;enerji_kaynagi_id;salter_kodu;salter_akim\nESNT-ADP-02;ESNT-TR-04;Q5;63A\nRHT-SP-01;ESNT-ADP-02;Q3;32A\nXYZ-01;-;;\n";
    var blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'});
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'enerji_kaynagi_ornek.csv'; a.click();
});

document.getElementById('ornekEkipmanCsvIndir') && document.getElementById('ornekEkipmanCsvIndir').addEventListener('click', function(e) {
    e.preventDefault();
    var csv = "ID;EKİPMAN YERİ;EKİPMAN CİNSİ;EKİPMAN TÜRÜ;MALZEMENİN TANIMI;MARKA;SERİ NO;TİP;VARSA DİĞER TANITICI BİLGİ;MİKTAR;İMAL YILI;NOTLAR\n" +
        "ORNEK-01;SAHA;ELEKTRİK PANOLARI;PANO;ÖRNEK EKİPMAN;MARKA;SN123;TIP-1;;1;2026;Toplu aktarım örneği\n";
    var blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'});
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'ekipman_toplu_aktar_ornek.csv'; a.click();
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


 

