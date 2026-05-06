<?php

/**
 * Enerji analizörü bağlantı eşleştirmesi.
 * Ekipman kodu => Modbus TCP bağlantı parametreleri
 */
return [
    'ESNT-ADP-03' => [
        'ip'        => '192.168.201.248',
        'port'      => 502,
        'device_id' => 1,
        'model'     => 'ENTES MPR-45S-V2',
        'aciklama'  => 'Kompresör Ana Dağıtım Elektrik Panoları ADP-03',
    ],
    // Diğer analizörler buraya eklenebilir:
    // 'ESNT-ADP-01' => [
    //     'ip'        => '192.168.201.xxx',
    //     'port'      => 502,
    //     'device_id' => 1,
    //     'model'     => 'ENTES MPR-45S-V2',
    //     'aciklama'  => '...',
    // ],
];
