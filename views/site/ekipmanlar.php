<?php
/** @var yii\web\View $this */
$this->title = 'Ekipmanlar | ParkBkm';
use yii\bootstrap5\Html;
use yii\bootstrap5\Modal;
?>

<div class="container-fluid p-0">
  <!-- Üst Menü -->
  <nav class="navbar navbar-expand-lg" style="background-color:#FE6B00;">
    <div class="container-fluid">
      <?= Html::img('@web/images/PARKBKM_logo_compact_256.png', [
          'alt' => 'ParkBkm',
          'width' => 40,
          'class' => 'me-2'
      ]) ?>
      <a class="navbar-brand text-white fw-bold" href="#">ParkBkm</a>
    </div>
  </nav>

  <!-- Ana Alan -->
  <div class="d-flex flex-column flex-md-row" style="height:calc(100vh - 70px);">
    <!-- Liste Paneli -->
    <div id="listPanel" class="bg-light border-end p-3 order-2 order-md-1"
         style="width:100%; max-height:40vh; overflow:auto; transition:all 0.3s;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-secondary m-0">Ekipmanlar</h5>
        <button class="btn btn-sm btn-outline-secondary d-md-none" id="toggleList">Kapat</button>
      </div>
      <input id="ekipmanSearch" class="form-control mb-3" placeholder="Ekipman ara...">
      <div id="ekipmanListesi"></div>
    </div>

    <!-- Harita + Detay -->
    <div class="flex-grow-1 position-relative order-1 order-md-2">
      <div id="map" style="height:100%; width:100%;"></div>

      <!-- Detay Kartı -->
      <div id="ekipmanDetay" class="card shadow position-absolute top-0 end-0 m-3 p-3"
           style="width:320px; display:none; background:white; z-index:1000;">
        <h6 class="fw-bold text-secondary mb-2">Ekipman Detayı</h6>
        <div id="detayIcerik"></div>
        <button class="btn btn-sm btn-outline-secondary mt-2" id="detayKapat">Kapat</button>
      </div>

      <!-- Mobilde liste açma butonu -->
      <button id="openList" class="btn btn-light shadow position-absolute bottom-3 start-50 translate-middle-x d-md-none"
              style="bottom:20px;">📋 Listeyi Aç</button>
    </div>
  </div>
</div>

<!-- Modal (Bakım Kaydı Formu) -->
<?php
Modal::begin([
    'id' => 'bakimModal',
    'title' => '<h6 class="m-0 text-secondary">Bakım Kaydı Oluştur</h6>',
    'size' => Modal::SIZE_DEFAULT,
]);
?>
<form id="bakimForm">
  <div class="mb-2">
    <label class="form-label">Ekipman Adı</label>
    <input type="text" class="form-control" id="formEkipmanAd" readonly>
  </div>
  <div class="mb-2">
    <label class="form-label">Arıza / Bakım Tanımı</label>
    <textarea class="form-control" id="formAciklama" rows="3" required></textarea>
  </div>
  <div class="mb-2">
    <label class="form-label">Öncelik</label>
    <select class="form-select" id="formOncelik">
      <option>Normal</option>
      <option>Yüksek</option>
      <option>Acil</option>
    </select>
  </div>
  <div class="text-end">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
    <button type="submit" class="btn btn-primary" style="background-color:#FE6B00; border:none;">Kaydet</button>
  </div>
</form>
<?php Modal::end(); ?>

<?php
$this->registerCssFile(Yii::getAlias('@web/vendor/leaflet/leaflet.css'));
$this->registerJsFile(Yii::getAlias('@web/vendor/leaflet/leaflet.js'), ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<?php
$js = <<<JS
let seciliEkipman = null;

let ekipmanData = [
  { id: 1, ad: 'Pompa 1', tip: 'Deniz Suyu Pompası', durum: 'Aktif', sonBakim: '2025-10-10', lat: 40.981, lng: 29.120 },
  { id: 2, ad: 'Vinç 2', tip: 'Portal Vinç 15 Ton', durum: 'Bakımda', sonBakim: '2025-11-01', lat: 40.979, lng: 29.125 },
  { id: 3, ad: 'Kompresör 3', tip: 'Hava Kompresörü', durum: 'Aktif', sonBakim: '2025-09-20', lat: 40.977, lng: 29.128 }
];

let map = L.map('map').setView([40.98, 29.12], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let markers = [];
function renderMarkers(filtered = ekipmanData) {
  markers.forEach(m => map.removeLayer(m));
  markers = [];
  filtered.forEach(e => {
    let m = L.marker([e.lat, e.lng]).addTo(map)
      .bindPopup('<b>' + e.ad + '</b>')
      .on('click', () => showDetay(e));
    markers.push(m);
  });
}

function renderList(filtered = ekipmanData) {
  let html = filtered.map(e => `
    <div class="list-item p-2 border-bottom" style="cursor:pointer;">
      <b>${e.ad}</b> <br><small>${e.tip}</small>
    </div>
  `).join('');
  $('#ekipmanListesi').html(html);

  $('#ekipmanListesi .list-item').each((i, el) => {
    $(el).on('click', () => {
      const eq = filtered[i];
      map.setView([eq.lat, eq.lng], 18);
      showDetay(eq);
    });
  });
}

function showDetay(e) {
  seciliEkipman = e;
  $('#detayIcerik').html(`
    <p><b>Ad:</b> ${e.ad}</p>
    <p><b>Tip:</b> ${e.tip}</p>
    <p><b>Durum:</b> <span class="badge bg-${e.durum === 'Aktif' ? 'success' : 'warning'}">${e.durum}</span></p>
    <p><b>Son Bakım:</b> ${e.sonBakim}</p>
    <button id="btnBakim" class="btn btn-sm btn-outline-primary w-100 mt-2">Bakım Kaydı Oluştur</button>
  `);
  $('#ekipmanDetay').fadeIn();

  $('#btnBakim').on('click', function() {
    $('#formEkipmanAd').val(e.ad);
    $('#bakimModal').modal('show');
  });
}

$('#detayKapat').on('click', () => $('#ekipmanDetay').fadeOut());

$('#ekipmanSearch').on('input', function() {
  const q = $(this).val().toLowerCase();
  const filtered = ekipmanData.filter(e => e.ad.toLowerCase().includes(q));
  renderMarkers(filtered);
  renderList(filtered);
});

$('#bakimForm').on('submit', function(e) {
  e.preventDefault();
  const kayit = {
    ekipman: $('#formEkipmanAd').val(),
    aciklama: $('#formAciklama').val(),
    oncelik: $('#formOncelik').val(),
    tarih: new Date().toISOString().slice(0,10)
  };
  console.log('Yeni Bakım Kaydı:', kayit);

  alert('Bakım kaydı oluşturuldu: ' + kayit.ekipman);
  $('#bakimModal').modal('hide');
  $('#formAciklama').val('');
});

$('#openList').on('click', () => $('#listPanel').css('max-height','80vh'));
$('#toggleList').on('click', () => $('#listPanel').css('max-height','0vh'));

renderMarkers();
renderList();
JS;
$this->registerJs($js);
?>
