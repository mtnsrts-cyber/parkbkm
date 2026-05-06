<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use app\models\EkipmanDokuman;

class EkipmanDokumanController extends Controller
{
    public function actionImportCsv($csvPath = null)
    {
        if ($csvPath === null || trim((string)$csvPath) === '') {
            $this->stdout("CSV yolu zorunlu.\n");
            return self::EXIT_CODE_ERROR;
        }

        if (!is_file($csvPath)) {
            $this->stdout("CSV bulunamadı: {$csvPath}\n");
            return self::EXIT_CODE_ERROR;
        }

        $bakimDir = Yii::getAlias('@app/web/uploads/BAKIM-TALİMATLAR');
        $elektrikDir = Yii::getAlias('@app/web/uploads/TEKNİK/ElektriProjesi');
        $brosurKlavuzDir = Yii::getAlias('@app/web/uploads/TEKNİK/BrosurKlavuz');

        if (!is_dir($bakimDir)) {
            $this->stdout("Doküman klasörü bulunamadı: {$bakimDir}\n");
            return self::EXIT_CODE_ERROR;
        }

        $fileIndexBakim = $this->buildFileIndex($bakimDir);
        $fileIndexElektrik = is_dir($elektrikDir) ? $this->buildFileIndex($elektrikDir) : [];
        $fileIndexBrosurKlavuz = is_dir($brosurKlavuzDir) ? $this->buildFileIndex($brosurKlavuzDir) : [];

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->stdout("CSV açılamadı: {$csvPath}\n");
            return self::EXIT_CODE_ERROR;
        }

        $first = fgetcsv($handle, 0, ';');
        if ($first === false) {
            fclose($handle);
            $this->stdout("CSV boş görünüyor.\n");
            return self::EXIT_CODE_ERROR;
        }

        EkipmanDokuman::deleteAll();

        $imported = 0;
        $linked = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $kod = isset($row[1]) ? trim((string)$row[1]) : '';
            $tur = isset($row[5]) ? trim((string)$row[5]) : '';
            $dokuman = isset($row[6]) ? trim((string)$row[6]) : '';

            if ($kod === '' || $tur === '' || $dokuman === '') {
                continue;
            }

            $normalizeType = $this->normalizeType($tur, $dokuman);
            if ($normalizeType === null) {
                continue;
            }

            $relPath = null;
            if (in_array($normalizeType, ['BAKIM FORMU', 'BAKIM TALİMATI'], true)) {
                $relPath = $this->findDocumentPath($dokuman, $fileIndexBakim);
            } elseif ($normalizeType === 'ELEKTRİK PROJESİ') {
                $relPath = $this->findDocumentPath($dokuman, $fileIndexElektrik);
                if ($relPath === null) {
                    $relPath = $this->findDocumentPath($dokuman, $fileIndexBrosurKlavuz);
                }
            } else {
                $relPath = $this->findDocumentPath($dokuman, $fileIndexBrosurKlavuz);
                if ($relPath === null) {
                    $relPath = $this->findDocumentPath($dokuman, $fileIndexElektrik);
                }
            }

            $model = new EkipmanDokuman();
            $model->ekipman_kodu = $kod;
            $model->dokuman_turu = $normalizeType;
            $model->dokuman_adi = $dokuman;
            $model->dosya_yolu = $relPath;
            $model->created_at = date('Y-m-d H:i:s');
            $model->updated_at = date('Y-m-d H:i:s');
            $model->save(false);

            $imported++;
            if (!empty($relPath)) {
                $linked++;
            }
        }

        fclose($handle);

        $this->stdout("Import tamamlandı. Toplam kayıt: {$imported}, Dosyası eşleşen: {$linked}\n");
        return self::EXIT_CODE_NORMAL;
    }

    private function normalizeType(string $type, string $documentName): ?string
    {
        $normalizedType = $this->normalize($type);
        $normalizedDoc = $this->normalize($documentName);

        if (str_contains($normalizedType, 'BAKIM FORMU')) {
            return 'BAKIM FORMU';
        }

        if (str_contains($normalizedType, 'BAKIM TALIMATI')) {
            return 'BAKIM TALİMATI';
        }

        if (str_contains($normalizedType, 'ELEKTRI') && str_contains($normalizedType, 'PROJE')) {
            return 'ELEKTRİK PROJESİ';
        }

        if (str_contains($normalizedType, 'KLAVUZ') && str_contains($normalizedType, 'BROSUR')) {
            if (
                str_contains($normalizedDoc, 'KULLANIM') ||
                str_contains($normalizedDoc, 'KULLANMA') ||
                str_contains($normalizedDoc, 'MANUAL') ||
                str_contains($normalizedDoc, 'INSTRUCTION') ||
                str_contains($normalizedDoc, 'USER') ||
                str_contains($normalizedDoc, 'KILAVUZ')
            ) {
                return 'KULLANMA KLAVUZU';
            }

            return 'BROŞÜR';
        }

        if (str_contains($normalizedType, 'KLAVUZ') || str_contains($normalizedType, 'KILAVUZ')) {
            return 'KULLANMA KLAVUZU';
        }

        if (str_contains($normalizedType, 'BROSUR') || str_contains($normalizedType, 'BROŞUR')) {
            return 'BROŞÜR';
        }

        return null;
    }

    private function buildFileIndex(string $baseDir): array
    {
        $index = [];

        $uploadsRoot = Yii::getAlias('@app/web/uploads');
        $uploadsRoot = str_replace('\\', '/', (string)realpath($uploadsRoot));
        if (!str_ends_with($uploadsRoot, '/')) {
            $uploadsRoot .= '/';
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', (string)realpath($fileInfo->getPathname()));
            if ($absolutePath === '' || !str_starts_with($absolutePath, $uploadsRoot)) {
                continue;
            }

            $relativeFromUploads = ltrim(substr($absolutePath, strlen($uploadsRoot)), '/');
            $basename = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
            $normalized = $this->normalize($basename);

            if (!isset($index[$normalized])) {
                $index[$normalized] = $relativeFromUploads;
            }
        }

        return $index;
    }

    private function findDocumentPath(string $dokumanAdi, array $fileIndex): ?string
    {
        $normalized = $this->normalize(pathinfo($dokumanAdi, PATHINFO_FILENAME));

        if (isset($fileIndex[$normalized])) {
            return $fileIndex[$normalized];
        }

        foreach ($fileIndex as $key => $value) {
            if (str_starts_with($key, $normalized) || str_starts_with($normalized, $key)) {
                return $value;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['İ', 'I', 'ı', 'i', 'Ğ', 'ğ', 'Ü', 'ü', 'Ş', 'ş', 'Ö', 'ö', 'Ç', 'ç', 'Â', 'â'], ['I', 'I', 'I', 'I', 'G', 'G', 'U', 'U', 'S', 'S', 'O', 'O', 'C', 'C', 'A', 'A'], $value);
        $value = mb_strtoupper($value, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim((string)$value, " .-_/\\\t\n\r\0\x0B");

        return $value;
    }
}
