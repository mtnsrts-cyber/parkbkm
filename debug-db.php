<?php
defined('YII_ENV_DEV') or define('YII_ENV_DEV', true);
defined('YII_DEBUG') or define('YII_DEBUG', true);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$db = new \yii\db\Connection([
    'dsn' => 'mysql:host=127.0.0.1;dbname=parkbkm',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
]);
$db->open();

// Hourly son 5
echo "=== SOG5 HOURLY SON 5 ===\n";
$hourly = $db->createCommand("SELECT * FROM sog5_energy_logs ORDER BY id DESC LIMIT 5")->queryAll();
print_r($hourly);

echo "\n=== SOG5 RAW SON 20 (ozet) ===\n";
$rawRows = $db->createCommand("SELECT log_datetime, e_total_kwh, e_l1_reactive_ind_kvarh, e_l2_reactive_ind_kvarh, e_l3_reactive_ind_kvarh, e_l1_reactive_cap_kvarh, e_l2_reactive_cap_kvarh, e_l3_reactive_cap_kvarh FROM sog5_energy_logs_raw ORDER BY log_datetime DESC LIMIT 20")->queryAll();
print_r($rawRows);

$nullCells = 0;
$zeroCells = 0;
$keys = [
    'e_total_kwh',
    'e_l1_reactive_ind_kvarh', 'e_l2_reactive_ind_kvarh', 'e_l3_reactive_ind_kvarh',
    'e_l1_reactive_cap_kvarh', 'e_l2_reactive_cap_kvarh', 'e_l3_reactive_cap_kvarh',
];

foreach ($rawRows as $r) {
    foreach ($keys as $k) {
        if (!array_key_exists($k, $r) || $r[$k] === null) {
            $nullCells++;
        }
        if ((float)($r[$k] ?? 0) === 0.0) {
            $zeroCells++;
        }
    }
}

echo "\n=== RAW KALITE ===\n";
echo "rows=" . count($rawRows) . "\n";
echo "null_cells=" . $nullCells . "\n";
echo "zero_cells=" . $zeroCells . "\n";

$drops = 0;
$prev = null;
foreach (array_reverse($rawRows) as $r) {
    $curr = (float)($r['e_total_kwh'] ?? 0);
    if ($prev !== null && $curr < $prev) {
        $drops++;
    }
    $prev = $curr;
}
echo "e_total_drops=" . $drops . "\n";
