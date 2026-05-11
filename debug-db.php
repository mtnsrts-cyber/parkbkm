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