<?php

declare(strict_types=1);

/**
 * Sync BAKIM-TALIMATLAR document paths by document code prefix.
 *
 * Usage:
 *   php scripts/sync_bakim_paths_by_code.php --dry-run
 *   php scripts/sync_bakim_paths_by_code.php --apply
 */

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true) || !$apply;

$dir = realpath(__DIR__ . '/../web/uploads/BAKIM-TALIMATLAR');
if ($dir === false || !is_dir($dir)) {
    fwrite(STDERR, "BAKIM-TALIMATLAR klasoru bulunamadi\n");
    exit(1);
}

$db = require __DIR__ . '/../config/db.php';
$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('SET NAMES utf8mb4');

$fileByCode = [];
foreach (new DirectoryIterator($dir) as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $name = $file->getFilename();
    if (preg_match('/^([A-Z]{2}-\d{2,3}-\d{2}-\d{3})[_\s-]/', $name, $m)) {
        $fileByCode[$m[1]] = 'BAKIM-TALIMATLAR/' . $name;
    }
}

$rows = $pdo->query("SELECT id, dokuman_adi, dosya_yolu FROM ekipman_dokuman WHERE dokuman_turu LIKE 'BAKIM%' OR dosya_yolu LIKE 'BAKIM%' OR dokuman_adi REGEXP '^(FR|TL)-[0-9]' ")->fetchAll(PDO::FETCH_ASSOC);
$update = $pdo->prepare('UPDATE ekipman_dokuman SET dosya_yolu = :path WHERE id = :id');

$matched = 0;
$updated = 0;
$missingCode = 0;

foreach ($rows as $row) {
    $source = (string)$row['dokuman_adi'] . ' ' . (string)$row['dosya_yolu'];
    if (!preg_match('/\b([A-Z]{2}-\d{2,3}-\d{2}-\d{3})\b/', $source, $m)) {
        continue;
    }

    $code = $m[1];
    if (!isset($fileByCode[$code])) {
        $missingCode++;
        continue;
    }

    $newPath = $fileByCode[$code];
    $oldPath = str_replace('\\', '/', trim((string)$row['dosya_yolu']));
    $matched++;

    if ($oldPath === $newPath) {
        continue;
    }

    echo ($dryRun ? '[DRY-DB] ' : '[DB] ') . 'id=' . (int)$row['id'] . ' : ' . $oldPath . ' => ' . $newPath . PHP_EOL;
    if ($apply) {
        $update->execute([':path' => $newPath, ':id' => (int)$row['id']]);
    }
    $updated++;
}

echo '----' . PHP_EOL;
echo 'Mod: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . PHP_EOL;
echo 'Klasorde bulunan kodlu dosya: ' . count($fileByCode) . PHP_EOL;
echo 'Kodla eslesen db kaydi: ' . $matched . PHP_EOL;
echo 'Guncellenecek/guncellenen db kaydi: ' . $updated . PHP_EOL;
echo 'Dosyasi bulunamayan kodlu db kaydi: ' . $missingCode . PHP_EOL;
