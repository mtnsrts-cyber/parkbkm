<?php

use app\helpers\IconHelper;

$this->title = 'Tersane Kroki';
?>

<div id="map" style="height:80vh; width:100%;"></div>

<?php
$this->registerCssFile(Yii::getAlias('@web/vendor/leaflet/leaflet.css'));
$this->registerJsFile(Yii::getAlias('@web/vendor/leaflet/leaflet.js'), ['position' => \yii\web\View::POS_HEAD]);
$this->registerCss(<<<CSS
.leaflet-control-layers {
    background: #fff;
    color: #222;
    border: 2px solid rgba(0,0,0,.2);
    border-radius: 4px;
}
.leaflet-control-layers-expanded {
    padding: 6px 10px 6px 6px;
}
.leaflet-control-layers label {
    margin: 0 0 4px;
    color: #222;
    font-size: 13px;
}
.leaflet-popup-content-wrapper {
    background: #111827;
    color: #f8fafc;
    border: 1px solid rgba(148, 163, 184, .45);
    border-radius: 12px;
    box-shadow: 0 14px 32px rgba(0,0,0,.35);
}
.leaflet-popup-tip {
    background: #111827;
    border: 1px solid rgba(148, 163, 184, .35);
}
.leaflet-popup-content {
    margin: 12px 14px;
    min-width: 220px;
}
.map-popup-card {
    display: grid;
    gap: 6px;
}
.map-popup-code {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(37, 99, 235, .18);
    color: #93c5fd;
    font-weight: 700;
    text-decoration: none;
    letter-spacing: .02em;
}
.map-popup-code:hover {
    color: #bfdbfe;
    text-decoration: none;
}
.map-popup-title {
    color: #fff;
    font-weight: 700;
    line-height: 1.25;
}
.map-popup-location {
    color: #cbd5e1;
    font-size: 12px;
}
.map-popup-location span {
    color: #94a3b8;
}
.leaflet-container a.leaflet-popup-close-button {
    color: #cbd5e1;
    padding: 8px 8px 0 0;
}
.leaflet-container a.leaflet-popup-close-button:hover {
    color: #fff;
}
CSS);

$mapImage = Yii::getAlias('@web/images/SahaPano.png');
$parkYuzerImage = Yii::getAlias('@web/images/ParkYuzerHavuz.png');

// read actual image sizes if available so overlay bounds match coordinates saved from view.php
$sahaPath = Yii::getAlias('@webroot/images/SahaPano.png');
$parkPath = Yii::getAlias('@webroot/images/ParkYuzerHavuz.png');
$sahaWidth = 6000; $sahaHeight = 1250; // defaults
$parkWidth = null; $parkHeight = null;
if (file_exists($sahaPath)) {
    $size = @getimagesize($sahaPath);
    if ($size) { $sahaWidth = $size[0]; $sahaHeight = $size[1]; }
}
if (file_exists($parkPath)) {
    $size2 = @getimagesize($parkPath);
    if ($size2) { $parkWidth = $size2[0]; $parkHeight = $size2[1]; }
}

// MARKER OLUŞTUR - YÜZER HAVUZ-3'ÜN DIŞINDA KALANLAR VE YÜZER HAVUZ-3 EKİPMANLARI
$sahaMarkers = [];      // SahaPano.png'de gösterilecek (YÜZER HAVUZ-3 hariç)
$yuzerHavuzMarkers = []; // ParkYuzerHavuz.png'de gösterilecek (sadece YÜZER HAVUZ-3)

$isYuzerHavuzEquipment = static function (?string $id, ?string $location = null): bool {
    if ($id !== null && $id !== '') {
        $idUpper = mb_strtoupper($id, 'UTF-8');
        $idUpper = strtr($idUpper, [
            'İ' => 'I',
            'İ' => 'I',
            'Ş' => 'S',
            'Ğ' => 'G',
            'Ü' => 'U',
            'Ö' => 'O',
            'Ç' => 'C',
        ]);
        $prefix = explode('-', $idUpper, 2)[0] ?? '';
        $prefix = preg_replace('/[^A-Z0-9]+/u', '', $prefix);

        if (strpos($prefix, 'YHY') === 0 || strpos($prefix, 'YHK') === 0 || strpos($prefix, 'YH') === 0) {
            return true;
        }
    }

    if ($location === null) {
        return false;
    }

    $normalized = mb_strtoupper($location, 'UTF-8');
    $normalized = strtr($normalized, [
        'İ' => 'I',
        'İ' => 'I',
        'Ş' => 'S',
        'Ğ' => 'G',
        'Ü' => 'U',
        'Ö' => 'O',
        'Ç' => 'C',
    ]);
    $normalized = preg_replace('/[^A-Z0-9]+/u', '', $normalized);

    return strpos($normalized, 'YUZERHAVUZ') !== false;
};

foreach ($items as $item) {
    $id = $item['id'] ?? null;
    $tanim = $item['MALZEMENIN_TANIMI'] ?? '';
    $ekipmanYeri = $item['EKIPMAN_YERI'] ?? '';
    $enlem = $item['ENLEM'] ?? null;
    $boylam = $item['BOYLAM'] ?? null;

    if ($id === null) {
        continue;
    }

    if ($enlem === null || $boylam === null) {
        continue;
    }

    $viewUrl = \yii\helpers\Url::to(['ekipman/view', 'id' => $id]);
    $popupHtml = '<div class="map-popup-card">'
        . '<a class="map-popup-code" href="' . htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">#' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '</a>'
        . '<div class="map-popup-title">' . htmlspecialchars((string)$tanim, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div class="map-popup-location"><span>Yer:</span> ' . htmlspecialchars((string)$ekipmanYeri, ENT_QUOTES, 'UTF-8') . '</div>'
        . '</div>';

    $markerData = [
        'id' => $id,
        'x' => floatval($boylam),
        'y' => floatval($enlem),
        'popup' => $popupHtml,
        'location' => $ekipmanYeri
    ];

    // "YÜZER HAVUZ-3" lokasyonundaki ekipmanları ayıkla
    if ($isYuzerHavuzEquipment((string)$id, $ekipmanYeri)) {
        $yuzerHavuzMarkers[] = $markerData;
    } else {
        $sahaMarkers[] = $markerData;
    }
}

$sahaJson = json_encode($sahaMarkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$yuzerHavuzJson = json_encode($yuzerHavuzMarkers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$mapImageJson = json_encode($mapImage);
$parkYuzerImageJson = json_encode($parkYuzerImage);
$sahaWidthJson = json_encode($sahaWidth);
$sahaHeightJson = json_encode($sahaHeight);
$parkWidthJson = json_encode($parkWidth);
$parkHeightJson = json_encode($parkHeight);

$js = <<<JS
var map = L.map('map', {
    crs: L.CRS.Simple,
    minZoom: -2,
    maxZoom: 3,
    zoomSnap: 0.25
});

// bounds will be set per-image using actual sizes (height, width)
var sahaBounds = [[0, 0], [$sahaHeightJson, $sahaWidthJson]];
var parkBounds = null;
if ($parkWidthJson !== null && $parkHeightJson !== null) {
    parkBounds = [[0,0], [$parkHeightJson, $parkWidthJson]];
}

// ============ KATMAN 1: SAHA (SahaPano.png + Saha Ekipmanları) ============
var sahaLayerGroup = L.featureGroup();
var sahaImageLayer = L.imageOverlay($mapImageJson, sahaBounds);
var sahaLayer = L.layerGroup([sahaImageLayer, sahaLayerGroup]);

// ============ KATMAN 2: YÜZER HAVUZ-3 (ParkYuzerHavuz.png + Yüzer Havuz Ekipmanları) ============
var yuzerHavuzLayerGroup = L.featureGroup();
var yuzerHavuzLayer = null;
if (parkBounds) {
    var yuzerHavuzImageLayer = L.imageOverlay($parkYuzerImageJson, parkBounds);
    yuzerHavuzLayer = L.layerGroup([yuzerHavuzImageLayer, yuzerHavuzLayerGroup]);
}

// Varsayılan olarak Saha katmanını göster
sahaLayer.addTo(map);

// Layer control
var baseLayers = {
    'Saha (SahaPano)': sahaLayer
};

if (yuzerHavuzLayer) {
    baseLayers['Yüzer Havuz-3'] = yuzerHavuzLayer;
}

L.control.layers(baseLayers, null, {collapsed: false}).addTo(map);
// fit to saha by default
map.fitBounds(sahaBounds);

// --- Compute scale factors so older stored coordinates (recorded against a base image size)
// can be adapted to the actual image size on disk. Default historical base used here
// is 6000x1250 (width x height). If you previously recorded positions against a
// different base image, change these values accordingly.
var sahaBaseWidth = 6000;
var sahaBaseHeight = 1250;
var sahaScaleX = 1.0;
var sahaScaleY = 1.0;
if (typeof $sahaWidthJson === 'number' && typeof $sahaHeightJson === 'number') {
    sahaScaleX = ($sahaWidthJson) / sahaBaseWidth;
    sahaScaleY = ($sahaHeightJson) / sahaBaseHeight;
}
console.log('sahaScaleX, sahaScaleY=', sahaScaleX, sahaScaleY);

var markerStyleEl = document.createElement('style');
markerStyleEl.textContent =
    '.map-marker-tag {' +
    'background:transparent;' +
    'border:none;' +
    'border-radius:0;' +
    'height:16px;' +
    'line-height:14px;' +
    'padding:0 2px;' +
    'display:inline-flex;' +
    'align-items:center;' +
    'gap:4px;' +
    'min-width:16px;' +
    'font-size:9px;' +
    'font-weight:700;' +
    'color:#0a3a5b;' +
    'text-align:center;' +
    'white-space:nowrap;' +
    'box-shadow:none;' +
    'transform-origin:50% 50%;' +
    'transform:scale(1);' +
    'transition:transform .1s linear;' +
    '}' +
    '.map-marker-tag .dot {' +
    'width:7px;' +
    'height:7px;' +
    'border-radius:50%;' +
    'background:#f26522;' +
    'border:1px solid rgba(10,58,91,.55);' +
    'box-shadow:0 0 0 1px rgba(255,255,255,.65);' +
    'flex:0 0 auto;' +
    '}' +
    '.map-marker-tag .label {' +
    'line-height:1;' +
    '}';
document.head.appendChild(markerStyleEl);

function normalizePoint(x, y, scaleX, scaleY, boundsHeight, boundsWidth, preferScaled) {
    function inBounds(lat, lng) {
        return lat >= 0 && lng >= 0 && lat <= boundsHeight && lng <= boundsWidth;
    }

    var scaledCandidates = [
        [y * scaleY, x * scaleX],
        [x * scaleY, y * scaleX]
    ];
    var rawCandidates = [
        [y, x],
        [x, y]
    ];

    var candidates = preferScaled ? scaledCandidates.concat(rawCandidates) : rawCandidates.concat(scaledCandidates);

    for (var i = 0; i < candidates.length; i++) {
        var lat = candidates[i][0];
        var lng = candidates[i][1];
        if (inBounds(lat, lng)) {
            return [lat, lng];
        }
    }

    return [y, x];
}

// ============ SAHA EKİPMANLARI ============
var sahaMarkers = $sahaJson;

function createDivIcon(id) {
    return L.divIcon({
        html: '<div class="map-marker-tag marker-img"><span class="dot"></span><span class="label">' + id + '</span></div>',
        iconSize: [24, 16],
        iconAnchor: [12, 8],
        popupAnchor: [0, -8],
        className: 'custom-svg-icon'
    });
}

function renderMarkersChunked(markers, layerGroup, options) {
    var index = 0;
    var chunkSize = 250;

    function runChunk() {
        var end = Math.min(index + chunkSize, markers.length);
        for (; index < end; index++) {
            var m = markers[index];
            var icon = createDivIcon(m.id);
            var point = normalizePoint(m.x, m.y, options.scaleX, options.scaleY, options.boundsHeight, options.boundsWidth, options.preferScaled);
            L.marker(point, {icon: icon})
                .bindPopup(m.popup)
                .addTo(layerGroup);
        }

        if (index < markers.length) {
            requestAnimationFrame(runChunk);
        }
    }

    runChunk();
}

renderMarkersChunked(sahaMarkers, sahaLayerGroup, {
    scaleX: sahaScaleX,
    scaleY: sahaScaleY,
    boundsHeight: $sahaHeightJson,
    boundsWidth: $sahaWidthJson,
    preferScaled: true
});

// ============ YÜZER HAVUZ-3 EKİPMANLARI ============
var yuzerHavuzMarkers = $yuzerHavuzJson;

renderMarkersChunked(yuzerHavuzMarkers, yuzerHavuzLayerGroup, {
    scaleX: 1,
    scaleY: 1,
    boundsHeight: $parkHeightJson || Number.MAX_SAFE_INTEGER,
    boundsWidth: $parkWidthJson || Number.MAX_SAFE_INTEGER,
    preferScaled: false
});

// ============ İKON ÖLÇEKLENDIRME ============
function updateIconScale() {
    var z = map.getZoom();
    var minZ = map.options.minZoom !== undefined ? map.options.minZoom : -2;
    var maxZ = map.options.maxZoom !== undefined ? map.options.maxZoom : 3;
    var t = 0;
    if (maxZ > minZ) t = (z - minZ) / (maxZ - minZ);
    if (t < 0) t = 0; if (t > 1) t = 1;
    var eased = 1 - Math.pow(1 - t, 2);
    var minScale = 0.75;
    var maxScale = 1.55;
    var s = minScale + (maxScale - minScale) * eased;
    
    document.querySelectorAll('.custom-svg-icon .marker-img').forEach(function(img){
        img.style.transform = 'scale(' + s + ')';
    });
}

map.on('zoomend', updateIconScale);
updateIconScale();
JS;

$this->registerJs($js, \yii\web\View::POS_END);
?>
