<?php

declare(strict_types=1);

/**
 * Normalize uploads file names to ASCII and sync ekipman_dokuman.dosya_yolu.
 *
 * Usage:
 *   php scripts/normalize_dokuman_paths.php --dry-run
 *   php scripts/normalize_dokuman_paths.php --apply
 */

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true) || !$apply;

$root = realpath(__DIR__ . '/../web/uploads');
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "uploads klasoru bulunamadi\n");
    exit(1);
}

$dbConfigPath = __DIR__ . '/../config/db.php';
if (!is_file($dbConfigPath)) {
    fwrite(STDERR, "db config bulunamadi: $dbConfigPath\n");
    exit(1);
}

$db = require $dbConfigPath;
if (!isset($db['dsn'], $db['username'], $db['password'])) {
    fwrite(STDERR, "db config gecersiz\n");
    exit(1);
}

$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('SET NAMES utf8mb4');

/** @return string */
function normalize_file_name(string $name): string
{
    $map = [
        'İ' => 'I', 'İ' => 'I', 'ı' => 'i',
        'Ş' => 'S', 'ş' => 's',
        'Ğ' => 'G', 'ğ' => 'g',
        'Ü' => 'U', 'ü' => 'u',
        'Ö' => 'O', 'ö' => 'o',
        'Ç' => 'C', 'ç' => 'c',
        'Â' => 'A', 'â' => 'a',
        'Î' => 'I', 'î' => 'i',
        'Û' => 'U', 'û' => 'u',
        '’' => '', "'" => '', '`' => '',
        '�' => 'I',
    ];

    $name = strtr($name, $map);
    $name = preg_replace('/\s+/u', '_', $name) ?? $name;
    $name = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $name) ?? $name;
    $name = preg_replace('/_+/', '_', $name) ?? $name;
    $name = trim($name, '._-');

    return $name !== '' ? $name : 'file';
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

$pathMap = [];
$renamed = 0;
$unchanged = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $oldAbs = $fileInfo->getPathname();
    $oldBase = $fileInfo->getFilename();
    $dir = $fileInfo->getPath();

    $pi = pathinfo($oldBase);
    $ext = isset($pi['extension']) && $pi['extension'] !== '' ? '.' . $pi['extension'] : '';
    $baseNoExt = $pi['filename'] ?? $oldBase;

    $newBase = normalize_file_name($baseNoExt) . $ext;

    $oldRel = str_replace('\\', '/', substr($oldAbs, strlen($root) + 1));

    if ($newBase === $oldBase) {
        $unchanged++;
        continue;
    }

    $newAbs = $dir . DIRECTORY_SEPARATOR . $newBase;
    if (file_exists($newAbs)) {
        $n = 1;
        $cleanNoExt = normalize_file_name($baseNoExt);
        do {
            $newBase = $cleanNoExt . '_' . $n . $ext;
            $newAbs = $dir . DIRECTORY_SEPARATOR . $newBase;
            $n++;
        } while (file_exists($newAbs));
    }

    $newRel = str_replace('\\', '/', substr($newAbs, strlen($root) + 1));

    echo ($dryRun ? '[DRY] ' : '[APPLY] ') . $oldRel . ' => ' . $newRel . PHP_EOL;

    if ($apply) {
        if (!@rename($oldAbs, $newAbs)) {
            echo '  !! rename basarisiz' . PHP_EOL;
            continue;
        }
    }

    $pathMap[$oldRel] = $newRel;
    $renamed++;
}

$updated = 0;
if (!empty($pathMap)) {
    $rows = $pdo->query("SELECT id, dosya_yolu FROM ekipman_dokuman WHERE dosya_yolu IS NOT NULL AND dosya_yolu <> ''")->fetchAll(PDO::FETCH_ASSOC);
    $upd = $pdo->prepare('UPDATE ekipman_dokuman SET dosya_yolu = :yol WHERE id = :id');

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $path = str_replace('\\', '/', ltrim((string)$row['dosya_yolu'], '/'));
        if (str_starts_with(strtolower($path), 'uploads/')) {
            $path = substr($path, 8);
        }

        if (!isset($pathMap[$path])) {
            continue;
        }

        $newPath = $pathMap[$path];
        echo ($dryRun ? '[DRY-DB] ' : '[DB] ') . "id=$id : $path => $newPath" . PHP_EOL;
        if ($apply) {
            $upd->execute([':yol' => $newPath, ':id' => $id]);
        }
        $updated++;
    }
}

echo '----' . PHP_EOL;
echo 'Mod: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . PHP_EOL;
echo 'Yeniden adlandirilan dosya: ' . $renamed . PHP_EOL;
echo 'Zaten uygun dosya: ' . $unchanged . PHP_EOL;
echo 'Guncellenen db kaydi: ' . $updated . PHP_EOL;
