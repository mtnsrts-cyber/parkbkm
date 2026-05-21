<?php

declare(strict_types=1);

/**
 * Find and move unused files/folders in web/uploads to a trash folder.
 *
 * Usage:
 *   php scripts/cleanup_unused_uploads.php --dry-run
 *   php scripts/cleanup_unused_uploads.php --apply
 */

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true) || !$apply;

$protectedPrefixes = [
    'BAKIM-TALIMATLAR/',
    'periyodik-raporlar/',
];

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

$keep = [];

$normalizePath = static function (string $path): string {
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if (str_starts_with(strtolower($path), 'uploads/')) {
        $path = substr($path, 8);
    }
    return $path;
};

$addKeep = static function (array &$keep, string $path, callable $normalizePath): void {
    $p = $normalizePath($path);
    if ($p !== '') {
        $keep[$p] = true;
    }
};

$parseWebUploadsRefs = static function (string $text) use ($normalizePath): array {
    $out = [];

    if (preg_match_all('~@web/uploads/([^"\'\s)]+)~u', $text, $m)) {
        foreach ($m[1] as $p) {
            $out[] = $normalizePath(rawurldecode((string)$p));
        }
    }

    if (preg_match_all('~["\'](/?uploads/[^"\'\s]+)["\']~u', $text, $m2)) {
        foreach ($m2[1] as $p) {
            $out[] = $normalizePath(rawurldecode((string)$p));
        }
    }

    return array_values(array_filter(array_unique($out)));
};

// 1) DB: ekipman_dokuman.dosya_yolu
$rows = $pdo->query("SELECT dosya_yolu FROM ekipman_dokuman WHERE dosya_yolu IS NOT NULL AND dosya_yolu <> ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $addKeep($keep, (string)$r['dosya_yolu'], $normalizePath);
}

// 2) DB: ekipman.TANITIM_FOTO (varsa)
try {
    $rows2 = $pdo->query("SELECT TANITIM_FOTO FROM ekipman WHERE TANITIM_FOTO IS NOT NULL AND TANITIM_FOTO <> ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows2 as $r) {
        $addKeep($keep, (string)$r['TANITIM_FOTO'], $normalizePath);
    }
} catch (Throwable $e) {
    // kolon yoksa geç
}

// 3) Kodda sabit kullanılan uploads dosyaları
$staticKeep = [
    'PARKBKM_logo_compact.svg',
    'PARKBKM_logo_compact_256.png',
    'SahaPano.png',
    'SahaPano1.png',
    'ParkYuzerHavuz.png',
    'tekhat.png',
    'tekhat1.png',
    'RihtimPano.jpg',
];
foreach ($staticKeep as $p) {
    $keep[$p] = true;
}

$allFiles = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $abs = $f->getPathname();
    $rel = str_replace('\\', '/', substr($abs, strlen($uploadsRoot) + 1));
    if (str_starts_with($rel, '_trash_') || str_starts_with($rel, '_trash_dup_')) {
        continue;
    }
    $allFiles[] = $rel;
}

$allFileSet = array_fill_keys($allFiles, true);

// 4) Koddan yakalanan /uploads referanslari
$codeRefCount = 0;
$codeFilesIt = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(realpath(__DIR__ . '/..'), FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($codeFilesIt as $node) {
    if (!$node->isFile()) {
        continue;
    }

    $ext = strtolower(pathinfo($node->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'js', 'ts', 'tsx', 'jsx', 'html', 'css'], true)) {
        continue;
    }

    $content = @file_get_contents($node->getPathname());
    if ($content === false || stripos($content, 'uploads') === false) {
        continue;
    }

    foreach ($parseWebUploadsRefs($content) as $ref) {
        $keep[$ref] = true;
        $codeRefCount++;
    }
}

// 5) Eski/yeni klasor adi farklari icin basename fallback
$basenameMap = [];
foreach ($allFiles as $rel) {
    $bn = basename($rel);
    if ($bn === '') {
        continue;
    }
    $basenameMap[$bn][] = $rel;
}

$resolvedByBasename = 0;
foreach (array_keys($keep) as $k) {
    if (isset($allFileSet[$k])) {
        continue;
    }

    $bn = basename($k);
    if ($bn === '' || !isset($basenameMap[$bn])) {
        continue;
    }

    $candidates = $basenameMap[$bn];
    if (count($candidates) === 1) {
        $keep[$candidates[0]] = true;
        $resolvedByBasename++;
    }
}

$unused = [];
foreach ($allFiles as $rel) {
    $isProtected = false;
    foreach ($protectedPrefixes as $prefix) {
        if (str_starts_with($rel, $prefix)) {
            $isProtected = true;
            break;
        }
    }
    if ($isProtected) {
        continue;
    }

    if (!isset($keep[$rel])) {
        $unused[] = $rel;
    }
}

echo 'Toplam dosya: ' . count($allFiles) . PHP_EOL;
echo 'Kullanilan referans: ' . count($keep) . PHP_EOL;
echo 'Koddan yakalanan referans: ' . $codeRefCount . PHP_EOL;
echo 'Basename fallback eslesme: ' . $resolvedByBasename . PHP_EOL;
echo 'Korumali klasor: ' . implode(', ', $protectedPrefixes) . PHP_EOL;
echo 'Kullanilmayan aday: ' . count($unused) . PHP_EOL;

if ($dryRun) {
    foreach (array_slice($unused, 0, 200) as $u) {
        echo '[DRY] ' . $u . PHP_EOL;
    }
    if (count($unused) > 200) {
        echo '... (daha fazla: ' . (count($unused) - 200) . ')' . PHP_EOL;
    }
    exit(0);
}

$trashDir = $uploadsRoot . '/_trash_' . date('Ymd_His');
if (!is_dir($trashDir) && !mkdir($trashDir, 0775, true) && !is_dir($trashDir)) {
    throw new RuntimeException('Trash klasoru olusturulamadi: ' . $trashDir);
}

$moved = 0;
foreach ($unused as $rel) {
    $src = $uploadsRoot . '/' . $rel;
    if (!is_file($src)) {
        continue;
    }
    $dst = $trashDir . '/' . $rel;
    $dstDir = dirname($dst);
    if (!is_dir($dstDir)) {
        mkdir($dstDir, 0775, true);
    }
    if (@rename($src, $dst)) {
        $moved++;
        echo '[MOVE] ' . $rel . PHP_EOL;
    } else {
        echo '[FAIL] ' . $rel . PHP_EOL;
    }
}

// boş dizinleri temizle (trash hariç)
$dirs = [];
$itDirs = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($itDirs as $node) {
    if ($node->isDir()) {
        $path = $node->getPathname();
        if (str_starts_with($path, $trashDir)) {
            continue;
        }
        $dirs[] = $path;
    }
}

$removedDirs = 0;
foreach ($dirs as $d) {
    $files = @scandir($d);
    if ($files !== false && count($files) === 2) {
        if (@rmdir($d)) {
            $removedDirs++;
        }
    }
}

echo 'Tasınan dosya: ' . $moved . PHP_EOL;
echo 'Silinen bos klasor: ' . $removedDirs . PHP_EOL;
echo 'Trash klasoru: ' . $trashDir . PHP_EOL;
