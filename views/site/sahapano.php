<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Saha Panoları';

$this->registerCssFile(Yii::getAlias('@web/vendor/leaflet/leaflet.css'));
$this->registerJsFile(Yii::getAlias('@web/vendor/leaflet/leaflet.js'), ['position' => \yii\web\View::POS_HEAD]);

?>

<div class="kroki-viewer">
    <h1><?= Html::encode($this->title) ?></h1>
    <div id="map" style="width: 100%; height: 600px; border: 1px solid #ccc;"></div>
</div>

<?php
$krokiImageUrl = Url::to('@web/uploads/SahaPano1.png'); // Kroki dosyanızın yolu

$this->registerJs("
var map = L.map('map', {
    crs: L.CRS.Simple,
    minZoom: -3,
    maxZoom: 4,
});

// Kroki görselinin boyutları (piksel cinsinden)
var imageWidth = 6000; // Krokinizin genişliği
var imageHeight = 1250; // Krokinizin yüksekliği

// Koordinat sınırları
var bounds = [[0, 0], [imageHeight, imageWidth]];

// PNG krokirini overlay olarak ekle
var imageOverlay = L.imageOverlay('$krokiImageUrl', bounds).addTo(map);

// Marker'ın konumunu belirle (merkezde)
var markerPosition = [imageHeight/2, imageWidth/2];

// Marker ekleme
var marker = L.marker(markerPosition).addTo(map)
    .bindPopup('Örnek Marker');

// Haritayı marker'ın konumuna zoom yap ve merkezle
map.setView(markerPosition, 0); // 3 zoom seviyesi, istediğiniz gibi ayarlayın

// Alternatif olarak fitBounds kullanmak isterseniz, marker'ın etrafında küçük bir alana zoom yapabilirsiniz:
// var markerBounds = L.latLngBounds([markerPosition]);
 // map.fitBounds(markerBounds, { padding: [150, 150] });

// Tıklama ile koordinat gösterme
var popup = L.popup();
map.on('click', function(e) {
    popup
        .setLatLng(e.latlng)
        .setContent('Koordinat: ' + e.latlng.toString())
        .openOn(map);
});
", \yii\web\View::POS_READY);
?>
