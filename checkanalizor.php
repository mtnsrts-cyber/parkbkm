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

$rows = $db->createCommand("SELECT * FROM analizor_cihazlar WHERE aktif=1")->queryAll();

echo "Aktif analizorler:\n";
print_r($rows);