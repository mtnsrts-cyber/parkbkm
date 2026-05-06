<?php

use yii\helpers\Html;

use yii\helpers\Url;

$this->title = 'ELEKTRİK TEK HAT ŞEMASI';

$this->registerCssFile('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');

$this->registerJsFile('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', ['position' => \yii\web\View::POS_HEAD]);

?>

<div class="kroki-viewer">

<h1><?= Html::encode($this->title) ?></h1>

<div id="map" style="width: 100%; height: 600px; border: 2px solid #ccc;"></div>

</div>

<?php

$krokiImageUrl = Url::to('@web/uploads/tekhat1.png'); // Kroki dosyanızın yolu

$this->registerJs("
var map = L.map('map', {

crs: L.CRS.Simple,

minZoom: 0,

maxZoom: 6,


});

// Kroki görselinin boyutları (piksel cinsinden)

// Koordinat sınırları

var bounds = [[0, 0], [750,750]];

// PNG krokirini overlay olarak ekle

var imageOverlay = L.imageOverlay('$krokiImageUrl', bounds).addTo(map);

// Haritayı kroki sınırlarına fit et

map.fitBounds(bounds);

// Tıklama ile koordinat gösterme

// var popup = L.popup();

//map.on('click', function(e) {

// popup

// .setLatLng(e.latlng)

// .setContent('Koordinat: ' + e.latlng.toString())

// .openOn(map);

// });

// Marker ekleme örneği

// var marker = L.marker([imageHeight/2, imageWidth/2]).addTo(map)

// .bindPopup('Örnek Marker');

", \yii\web\View::POS_READY);

?>
