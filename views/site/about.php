<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use dosamigos\leaflet\types\LatLng;
use dosamigos\leaflet\types\Bounds;
use dosamigos\leaflet\types\Point;
use dosamigos\leaflet\types\LatLngBounds;
use dosamigos\leaflet\controls;
use dosamigos\leaflet\layers\LayerGroup;




$bounds = new LatLngBounds(
            [
                'southWest' => new LatLng(['lat' => 24500, 'lng' => -12700]),
                'northEast' => new LatLng(['lat' => -24500, 'lng' => 233700])
            ]
        );

$overlay = new \dosamigos\leaflet\layers\ImageOverlay([
'imageBounds' => $bounds,
'imageUrl' => 'http://localhost/images/SahaPano.png'
]);


$leaflet = new \dosamigos\leaflet\LeafLet([
    
        'center'=>  new LatLng(['lat' => 0, 'lng' => 0]),
        'zoom' => 2,
        'clientOptions' => [
        'bounds' => '[[24500, -12700], [-24500, 233700]]', 
        'crs'=> 'L.CRS.Simple',
        'minZoom'=> -8,
        'maxZoom'=> 2,
    ],  
]);
$leaflet->addLayer($overlay);

echo \dosamigos\leaflet\widgets\Map::widget(['leafLet' => $leaflet]);
?>