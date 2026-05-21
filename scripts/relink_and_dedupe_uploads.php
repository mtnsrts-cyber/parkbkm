<?php

declare(strict_types=1);

/**
 * Canonicalize dokuman paths and deduplicate legacy uploads copies.
 *
 * Usage:
 *   php scripts/relink_and_dedupe_uploads.php --dry-run
 *   php scripts/relink_and_dedupe_uploads.php --apply
 */

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true) || !$apply;

$uploadsRoot = realpath(__DIR__ . '/../web/uploads');
if ($uploadsRoot === false || !is_dir($uploadsRoot)) {
    fwrite(STDERR, "uploads klasoru bulunamadi\n");
    exit(1);
}

$db = require __DIR__ . '/../config/db.php';
$pdo = new PDO($db['dsn'], $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('SET NAMES utf8mb4');

$normalize = static function (string $path): string {
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if (str_starts_with(strtolower($path), 'uploads/')) {
        $path = substr($path, 8);
    }
    return $path;
};

$toCanonical = static function (string $path): string {
    $map = [
        'TEKNIK/ElektriProjesi/' => 'teknik-dokumanlar/elektrik-projeleri/',
        'TEKNIK/ELEKTRIK_PROJELERI/' => 'teknik-dokumanlar/svg/',
        'TEKNIK/BrosurKlavuz/' => 'teknik-dokumanlar/brosurler/',
        'BAKIM-TALİMATLAR/' => 'BAKIM-TALIMATLAR/',
    ];
    foreach ($map as $from => $to) {
        if (str_starts_with($path, $from)) {
            return $to . substr($path, strlen($from));
        }
    }
    return $path;
};

$rows = $pdo->query("SELECT id, dosya_yolu FROM ekipman_dokuman WHERE dosya_yolu IS NOT NULL AND dosya_yolu <> ''")->fetchAll(PDO::FETCH_ASSOC);
$upd = $pdo->prepare('UPDATE ekipman_dokuman SET dosya_yolu = :yol WHERE id = :id');

$allFiles = [];
$itAll = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($itAll as $node) {
    if (!$node->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($node->getPathname(), strlen($uploadsRoot) + 1));
    if (str_starts_with($rel, '_trash_') || str_starts_with($rel, '_trash_dup_')) {
        continue;
    }
    $allFiles[$rel] = true;
}

$basenameMap = [];
foreach (array_keys($allFiles) as $rel) {
    $bn = basename($rel);
    if ($bn === '') {
        continue;
    }
    $basenameMap[$bn][] = $rel;
}

$dbUpdated = 0;
$dbSkippedMissing = 0;
$dbRecoveredByBasename = 0;

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $old = $normalize((string)$row['dosya_yolu']);
    $new = $toCanonical($old);

    if ($new === $old) {
        continue;
    }

    $newAbs = $uploadsRoot . '/' . $new;
    $oldAbs = $uploadsRoot . '/' . $old;
    if (!is_file($newAbs) && !is_file($oldAbs)) {
        $bn = basename($old);
        if ($bn !== '' && isset($basenameMap[$bn]) && count($basenameMap[$bn]) === 1) {
            $new = $basenameMap[$bn][0];
            echo ($dryRun ? '[DRY-DB-RECOVER] ' : '[DB-RECOVER] ') . "id=$id : $old => $new" . PHP_EOL;
            if ($apply) {
                $upd->execute([':yol' => $new, ':id' => $id]);
            }
            $dbUpdated++;
            $dbRecoveredByBasename++;
            continue;
        }

        $dbSkippedMissing++;
        continue;
    }

    echo ($dryRun ? '[DRY-DB] ' : '[DB] ') . "id=$id : $old => $new" . PHP_EOL;
    if ($apply) {
        $upd->execute([':yol' => $new, ':id' => $id]);
    }
    $dbUpdated++;
}

$legacyPrefixes = [
    'TEKNIK/ElektriProjesi/',
    'TEKNIK/ELEKTRIK_PROJELERI/',
    'TEKNIK/BrosurKlavuz/',
];

$trashDir = $uploadsRoot . '/_trash_dup_' . date('Ymd_His');
$filesMoved = 0;
$filesMismatch = 0;
$filesNoCanonical = 0;

foreach ($legacyPrefixes as $legacyPrefix) {
    $legacyDir = $uploadsRoot . '/' . rtrim($legacyPrefix, '/');
    if (!is_dir($legacyDir)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($legacyDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }

        $legacyAbs = $f->getPathname();
        $legacyRel = str_replace('\\', '/', substr($legacyAbs, strlen($uploadsRoot) + 1));
        $canonicalRel = $toCanonical($legacyRel);
        $canonicalAbs = $uploadsRoot . '/' . $canonicalRel;

        if (!is_file($canonicalAbs)) {
            $filesNoCanonical++;
            continue;
        }

        if (filesize($legacyAbs) !== filesize($canonicalAbs) || md5_file($legacyAbs) !== md5_file($canonicalAbs)) {
            $filesMismatch++;
            continue;
        }

        $trashAbs = $trashDir . '/' . $legacyRel;
        echo ($dryRun ? '[DRY-MOVE] ' : '[MOVE] ') . $legacyRel . ' => ' . str_replace('\\', '/', substr($trashAbs, strlen($uploadsRoot) + 1)) . PHP_EOL;

        if ($apply) {
            $parent = dirname($trashAbs);
            if (!is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            if (!@rename($legacyAbs, $trashAbs)) {
                continue;
            }
        }

        $filesMoved++;
    }
}

echo '----' . PHP_EOL;
echo 'Mod: ' . ($dryRun ? 'DRY-RUN' : 'APPLY') . PHP_EOL;
echo 'Guncellenen db kaydi: ' . $dbUpdated . PHP_EOL;
echo 'Basename ile kurtarilan db kaydi: ' . $dbRecoveredByBasename . PHP_EOL;
echo 'Eksik dosya nedeniyle atlanan db kaydi: ' . $dbSkippedMissing . PHP_EOL;
echo 'Trasha tasinacak/tasinan duplicate dosya: ' . $filesMoved . PHP_EOL;
echo 'Icerik farkli oldugu icin atlanan duplicate: ' . $filesMismatch . PHP_EOL;
echo 'Canonical karsiligi bulunamayan legacy dosya: ' . $filesNoCanonical . PHP_EOL;
if ($apply) {
    echo 'Trash klasoru: ' . $trashDir . PHP_EOL;
}
