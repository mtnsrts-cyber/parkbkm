<?php
use yii\helpers\Html;
use yii\helpers\Json;
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
    padding: 10px;
    font-size: 16px;
}
.ekipman-filter-bar {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) minmax(180px, 260px) minmax(180px, 260px) minmax(120px, 160px) auto;
    gap: 8px;
    align-items: end;
    margin-bottom: 15px;
}
@media (max-width: 992px) {
    .ekipman-filter-bar {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 576px) {
    .ekipman-filter-bar {
        grid-template-columns: 1fr;
    }
}

.ekipman-mobile-list {
    display: none;
}

.ekipman-mobile-card {
    display: block;
    color: #fff;
    text-decoration: none;
    border: 1px solid #495057;
    border-radius: 10px;
    background: #1f2428;
}

.ekipman-mobile-card:hover {
    color: #fff;
    border-color: #ffc107;
    text-decoration: none;
}

.ekipman-mobile-code {
    color: #ffc107;
    font-weight: 700;
    white-space: nowrap;
}

.ekipman-mobile-title {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-weight: 700;
    line-height: 1.25;
}

.ekipman-mobile-place {
    color: #cbd5e1;
    font-size: .86rem;
}

.ekipman-mobile-status {
    color: #22c55e;
    font-weight: 800;
    font-size: .82rem;
}

@media (max-width: 767.98px) {
    .ekipman-desktop-grid {
        display: none;
    }

    .ekipman-mobile-list {
        display: grid;
        gap: .55rem;
    }
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
        Bu aktarım tek girişli besleme kaydı oluşturur. Çift/transfer/senkron girişler için ekipman güncelleme formundaki Besleme Grubu bölümünü kullanın. İlişkiyi silmek için ikinci sütuna boş, <code>-</code> veya <code>null</code> yazın. İlk satır başlık ise atlanır.</p>
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


<?= Html::beginForm(['ekipman/index'], 'get', ['id' => 'ekipman-filter-form', 'data-pjax' => '0']) ?>
    <div class="ekipman-filter-bar">
        <?= Html::activeTextInput($searchModel, 'globalSearch', [
            'id' => 'live-search',
            'class' => 'form-control',
            'placeholder' => '🔍 Ara... (Kodu, Tanım, Yeri, Marka, Seri)',
            'autocomplete' => 'off',
        ]) ?>

        <?= Html::activeDropDownList($searchModel, 'EKIPMAN_CINSI', $cinsList, [
            'id' => 'ekipman-cins-filter',
            'class' => 'form-select',
            'prompt' => 'Cins: Tümü',
        ]) ?>

        <?= Html::activeDropDownList($searchModel, 'EKIPMAN_TURU', $turList, [
            'id' => 'ekipman-tur-filter',
            'class' => 'form-select',
            'prompt' => 'Tür: Tümü',
        ]) ?>

        <?= Html::activeDropDownList($searchModel, 'DURUM', \app\models\Ekipman::durumSecenekleri(), [
            'id' => 'ekipman-durum-filter',
            'class' => 'form-select',
            'prompt' => 'Durum: Tümü',
        ]) ?>

        <?= Html::a('Temizle', ['ekipman/index'], ['class' => 'btn btn-outline-secondary']) ?>

        <?php if (!empty($searchModel->idList)): ?>
            <?= Html::activeHiddenInput($searchModel, 'idList') ?>
        <?php endif; ?>
        <?= Html::hiddenInput('per-page', Yii::$app->request->get('per-page', 20), ['id' => 'ekipman-page-size']) ?>
    </div>
<?= Html::endForm() ?>



<?= \app\widgets\PageSizeWidget::widget() ?>

<?php \yii\widgets\Pjax::begin(['id' => 'ekipman-pjax', 'enablePushState' => false]); ?>
    <div class="ekipman-desktop-grid">
    <?= GridView::widget([
        'id' => 'ekipman-grid',
        'dataProvider' => $dataProvider,
        'filterModel' => null,
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
                'value' => function ($model) {
                    return $model->getDurumEtiketi();
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
    </div>

    <div class="ekipman-mobile-list">
        <?php foreach ($dataProvider->getModels() as $model): ?>
            <?= Html::a(
                '<div class="d-flex justify-content-between align-items-start gap-2 mb-1">'
                    . '<div class="ekipman-mobile-code">' . Html::encode($model->id) . '</div>'
                    . '<div class="ekipman-mobile-status">' . Html::encode($model->getDurumEtiketi()) . '</div>'
                . '</div>'
                . '<div class="ekipman-mobile-title mb-1">' . Html::encode($model->MALZEMENIN_TANIMI) . '</div>'
                . '<div class="d-flex justify-content-between align-items-center gap-2">'
                    . '<div class="ekipman-mobile-place">' . Html::encode($model->EKIPMAN_YERI) . '</div>'
                    . '<span class="btn btn-sm btn-outline-warning py-0 px-2">Detay</span>'
                . '</div>',
                ['view', 'id' => $model->id],
                ['class' => 'ekipman-mobile-card p-2']
            ) ?>
        <?php endforeach; ?>
    </div>
<?php \yii\widgets\Pjax::end(); ?>

<script>

const turByCins = <?= Json::htmlEncode($turByCins) ?>;
const allTurOptions = <?= Json::htmlEncode($turList) ?>;
const idListFilter = new URLSearchParams(window.location.search).get('EkipmanSearch[idList]') || '';
const filterForm = document.getElementById('ekipman-filter-form');
const cinsFilter = document.getElementById('ekipman-cins-filter');
const turFilter = document.getElementById('ekipman-tur-filter');
const pageSizeInput = document.getElementById('ekipman-page-size');

function rebuildTurOptions() {
    const selectedCins = cinsFilter.value;
    const selectedTur = turFilter.value;
    const source = selectedCins && turByCins[selectedCins] ? turByCins[selectedCins] : allTurOptions;

    turFilter.innerHTML = '';
    turFilter.appendChild(new Option('Tür: Tümü', ''));
    Object.keys(source).forEach(function(value) {
        turFilter.appendChild(new Option(source[value], value));
    });

    if (source[selectedTur]) {
        turFilter.value = selectedTur;
    }
}

function reloadEkipmanGrid() {
    const requestData = $(filterForm).serializeArray().reduce(function(data, item) {
        data[item.name] = item.value;
        return data;
    }, {});

    if (idListFilter) {
        requestData['EkipmanSearch[idList]'] = idListFilter;
    }

    $.pjax.reload({
        container: '#ekipman-pjax',
        data: requestData,
        replace: false,
        push: false,
        timeout: 5000
    });
}

// Canlı Arama - Düzeltilmiş versiyon
let searchTimeout;
document.getElementById("live-search").addEventListener("keyup", function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(reloadEkipmanGrid, 300); // çok hızlı yazarken sürekli istek atmaz
});

[cinsFilter, turFilter, document.getElementById('ekipman-durum-filter')].forEach(function(element) {
    element.addEventListener('change', function() {
        if (element === cinsFilter) {
            rebuildTurOptions();
        }
        reloadEkipmanGrid();
    });
});

filterForm.addEventListener('submit', function(event) {
    event.preventDefault();
    reloadEkipmanGrid();
});

document.querySelectorAll('a[href*="per-page="]').forEach(function(link) {
    link.addEventListener('click', function(event) {
        const pageSize = new URL(this.href, window.location.origin).searchParams.get('per-page');
        if (!pageSize) {
            return;
        }

        event.preventDefault();
        pageSizeInput.value = pageSize;
        document.querySelectorAll('a[href*="per-page="]').forEach(function(item) {
            item.classList.toggle('btn-primary', item === link);
            item.classList.toggle('btn-outline-secondary', item !== link);
        });
        reloadEkipmanGrid();
    });
});

rebuildTurOptions();


</script>


 

