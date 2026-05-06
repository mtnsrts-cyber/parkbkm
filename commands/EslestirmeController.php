<?php

namespace app\commands;

use app\models\ArizaTakip;
use app\models\BakimTakip;
use app\models\BakimTakipEkipman;
use app\models\Ekipman;
use yii\console\Controller;

class EslestirmeController extends Controller
{
    public function actionEkipmanAra(string $q, int $limit = 20): int
    {
        $query = trim($q);
        if ($query === '') {
            $this->stdout("Arama metni boş olamaz.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $rows = Ekipman::find()
            ->select(['id', 'MALZEMENIN_TANIMI'])
            ->where(['like', 'MALZEMENIN_TANIMI', $query])
            ->orderBy(['id' => SORT_ASC])
            ->limit($limit)
            ->asArray()
            ->all();

        $this->stdout("\n=== Ekipman Arama ===\n");
        $this->stdout("Sorgu: $query\n");
        $this->stdout("Bulunan: " . count($rows) . "\n\n");

        foreach ($rows as $idx => $row) {
            $name = (string)($row['MALZEMENIN_TANIMI'] ?? '');
            $this->stdout(sprintf("%d) ekipman_id=%s | ad=%s\n", $idx + 1, (string)$row['id'], $name));
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBakimLinkEkle(int $bakimId, string $ekipmanId, int $apply = 0): int
    {
        $row = BakimTakip::findOne($bakimId);
        if ($row === null) {
            $this->stdout("Bakım kaydı bulunamadı: $bakimId\n");
            return self::EXIT_CODE_NORMAL;
        }

        $equipment = Ekipman::findOne($ekipmanId);
        if ($equipment === null) {
            $this->stdout("Ekipman bulunamadı: $ekipmanId\n");
            return self::EXIT_CODE_NORMAL;
        }

        $exists = BakimTakipEkipman::find()
            ->where(['bakim_id' => $bakimId, 'ekipman_id' => $ekipmanId])
            ->exists();

        $mode = $apply === 1 ? 'UYGULANDI' : 'ÖNİZLEME';
        $this->stdout("\n=== Bakım Link Ekle ($mode) ===\n");
        $this->stdout("bakim_id=$bakimId | ekipman_id=$ekipmanId\n");
        $this->stdout("bakim_sistem_cihaz=" . (string)$row->SISTEM_CIHAZ_OZELLIK . "\n");
        $this->stdout("ekipman_adi=" . (string)$equipment->MALZEMENIN_TANIMI . "\n");

        if ($exists) {
            $this->stdout("Sonuç: Link zaten var, işlem yapılmadı.\n");
            return self::EXIT_CODE_NORMAL;
        }

        if ($apply !== 1) {
            $this->stdout("Sonuç: Uygulanmadı (önizleme).\n");
            $this->stdout("Uygulamak için: php yii eslestirme/bakim-link-ekle $bakimId $ekipmanId 1\n");
            return self::EXIT_CODE_NORMAL;
        }

        $pivot = new BakimTakipEkipman();
        $pivot->bakim_id = $bakimId;
        $pivot->ekipman_id = $ekipmanId;

        if ($pivot->save()) {
            $this->stdout("Sonuç: Link eklendi.\n");
        } else {
            $this->stdout("Sonuç: Link eklenemedi.\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBakimLinkSil(int $bakimId, string $ekipmanId, int $apply = 0): int
    {
        $row = BakimTakip::findOne($bakimId);
        if ($row === null) {
            $this->stdout("Bakım kaydı bulunamadı: $bakimId\n");
            return self::EXIT_CODE_NORMAL;
        }

        $equipment = Ekipman::findOne($ekipmanId);
        if ($equipment === null) {
            $this->stdout("Ekipman bulunamadı: $ekipmanId\n");
            return self::EXIT_CODE_NORMAL;
        }

        $pivot = BakimTakipEkipman::findOne(['bakim_id' => $bakimId, 'ekipman_id' => $ekipmanId]);

        $mode = $apply === 1 ? 'UYGULANDI' : 'ÖNİZLEME';
        $this->stdout("\n=== Bakım Link Sil ($mode) ===\n");
        $this->stdout("bakim_id=$bakimId | ekipman_id=$ekipmanId\n");
        $this->stdout("bakim_sistem_cihaz=" . (string)$row->SISTEM_CIHAZ_OZELLIK . "\n");
        $this->stdout("ekipman_adi=" . (string)$equipment->MALZEMENIN_TANIMI . "\n");

        if ($pivot === null) {
            $this->stdout("Sonuç: Link bulunamadı, işlem yapılmadı.\n");
            return self::EXIT_CODE_NORMAL;
        }

        if ($apply !== 1) {
            $this->stdout("Sonuç: Uygulanmadı (önizleme).\n");
            $this->stdout("Uygulamak için: php yii eslestirme/bakim-link-sil $bakimId $ekipmanId 1\n");
            return self::EXIT_CODE_NORMAL;
        }

        if ($pivot->delete() !== false) {
            $this->stdout("Sonuç: Link silindi.\n");
        } else {
            $this->stdout("Sonuç: Link silinemedi.\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBakimTarihteMetinTekKalem(string $tarih, string $keyword, string $baslik, int $apply = 0, string $sureSaat = ''): int
    {
        $normalizedKeyword = $this->normalize($keyword);
        $title = trim($baslik);

        if ($normalizedKeyword === '') {
            $this->stdout("Anahtar kelime boş olamaz.\n");
            return self::EXIT_CODE_NORMAL;
        }

        if ($title === '') {
            $this->stdout("Grup başlığı boş olamaz.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $durationOverride = null;
        $sureSaat = trim($sureSaat);
        if ($sureSaat !== '') {
            if (!is_numeric($sureSaat)) {
                $this->stdout("Süre saat sayısal olmalıdır.\n");
                return self::EXIT_CODE_NORMAL;
            }

            $durationOverride = (float)$sureSaat;
            if ($durationOverride < 0) {
                $this->stdout("Süre saat negatif olamaz.\n");
                return self::EXIT_CODE_NORMAL;
            }
        }

        $rows = BakimTakip::find()
            ->where(['TARIH' => $tarih])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $matchedRows = [];
        $equipmentIds = [];
        foreach ($rows as $row) {
            $haystack = $this->normalize(
                trim(
                    $this->stringifyValue($row->SISTEM_CIHAZ_OZELLIK)
                    . ' ' . $this->stringifyValue($row->YAPILAN_IS)
                    . ' ' . $this->stringifyValue($row->BAKIM_GENEL)
                )
            );

            if ($haystack === '' || mb_stripos($haystack, $normalizedKeyword) === false) {
                continue;
            }

            $matchedRows[] = $row;

            $equipmentId = $this->extractEquipmentIdFromBakim($row);
            if ($equipmentId !== null) {
                $equipmentIds[$equipmentId] = true;
            }
        }

        if (count($matchedRows) < 2) {
            $this->stdout("Tek kaleme düşürmek için en az 2 kayıt gerekli. Bulunan: " . count($matchedRows) . "\n");
            return self::EXIT_CODE_NORMAL;
        }

        $missingEquipmentIds = [];
        foreach (array_keys($equipmentIds) as $equipmentId) {
            if (Ekipman::findOne($equipmentId) === null) {
                $missingEquipmentIds[] = $equipmentId;
            }
        }

        if (!empty($missingEquipmentIds)) {
            $this->stdout("Ekipman tablosunda bulunamayan kodlar: " . implode(', ', $missingEquipmentIds) . "\n");
            return self::EXIT_CODE_NORMAL;
        }

        $first = $matchedRows[0];
        $rowIds = array_map(static fn (BakimTakip $row): int => (int)$row->id, $matchedRows);
        $personnel = $this->mergeCommaSeparatedValues(array_map(fn (BakimTakip $row): string => $this->stringifyValue($row->ISI_YAPANLAR), $matchedRows));
        $periyot = $this->mergeDistinctValues(array_map(fn (BakimTakip $row): string => $this->stringifyValue($row->PERIYODIK_PLANLI), $matchedRows));
        $yapilanIs = $this->mergeDistinctValues(array_map(fn (BakimTakip $row): string => $this->stringifyValue($row->YAPILAN_IS), $matchedRows));
        $yer = $this->mergeDistinctValues(array_map(fn (BakimTakip $row): string => $this->stringifyValue($row->YERI), $matchedRows));
        $bakimGenel = $this->mergeDistinctValues(array_map(fn (BakimTakip $row): string => $this->stringifyValue($row->BAKIM_GENEL), $matchedRows));
        $totalDuration = 0.0;
        foreach ($matchedRows as $row) {
            $totalDuration += (float)$row->BAKIM_SURESI_SAAT;
        }
        $finalDuration = $durationOverride ?? $totalDuration;

        $mode = $apply === 1 ? 'UYGULANDI' : 'ÖNİZLEME';
        $this->stdout("\n=== Bakım Tarihte Metin Tek Kalem ($mode) ===\n");
        $this->stdout("tarih=$tarih | anahtar=$keyword | baslik=$title\n");
        $this->stdout("Kayit sayisi: " . count($matchedRows) . "\n");
        $this->stdout("Kayıt id'leri: " . implode(', ', $rowIds) . "\n");
        $this->stdout("Ekipman sayisi: " . count($equipmentIds) . "\n");
        $this->stdout("Toplam sure: " . number_format($totalDuration, 2, '.', '') . " saat\n");
        if ($durationOverride !== null) {
            $this->stdout("Override sure: " . number_format($finalDuration, 2, '.', '') . " saat\n");
        }
        $this->stdout("Periyot: $periyot\n");
        $this->stdout("Yapanlar: $personnel\n");

        if ($apply !== 1) {
            $this->stdout("Sonuç: Uygulanmadı (önizleme).\n");
            $command = "php yii eslestirme/bakim-tarihte-metin-tek-kalem \"$tarih\" \"$keyword\" \"$title\" 1";
            if ($durationOverride !== null) {
                $command .= ' "' . number_format($finalDuration, 2, '.', '') . '"';
            }
            $this->stdout("Uygulamak için: $command\n");
            return self::EXIT_CODE_NORMAL;
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $group = new BakimTakip();
            $group->BAKIM_GENEL = $bakimGenel !== '' ? $bakimGenel : (string)$first->BAKIM_GENEL;
            $group->PERIYODIK_PLANLI = $periyot;
            $group->TARIH = $tarih;
            $group->BAKIM_SURESI_SAAT = $finalDuration;
            $group->YERI = $yer;
            $group->SISTEM_CIHAZ_OZELLIK = $title;
            $group->YAPILAN_IS = $yapilanIs;
            $group->ISI_YAPANLAR = $personnel;
            $group->ekipmanIds = array_values(array_keys($equipmentIds));

            if (!$group->save()) {
                $errors = $group->getFirstErrors();
                throw new \RuntimeException('Grup kaydı kaydedilemedi: ' . implode(' | ', $errors));
            }

            BakimTakipEkipman::deleteAll(['bakim_id' => $rowIds]);
            BakimTakip::deleteAll(['id' => $rowIds]);

            $transaction->commit();

            $this->stdout("Sonuç: Tek kayıt oluşturuldu. yeni_bakim_id=" . (int)$group->id . "\n");
            $this->stdout("Silinen eski kayıt sayısı: " . count($rowIds) . "\n");
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->stdout("Sonuç: İşlem başarısız. " . $e->getMessage() . "\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionArizaUygunKayitlar(int $limit = 5, int $apply = 0): int
    {
        [$byExactName, $normalizedEquipmentNames, $equipmentIds] = $this->buildEquipmentLookup();

        if (empty($equipmentIds)) {
            $this->stdout("Ekipman kaydı bulunamadı.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $candidates = [];
        $rows = ArizaTakip::find()->orderBy(['id' => SORT_DESC])->all();
        foreach ($rows as $row) {
            $nameRaw = trim((string)$row->ARIZALANAN_MAKINE_ADI);
            if ($nameRaw === '') {
                continue;
            }

            $matches = $this->findMatches(
                $nameRaw,
                $byExactName,
                $normalizedEquipmentNames,
                $equipmentIds,
                true
            );

            if (count($matches) !== 1) {
                continue;
            }

            $matchedId = (string)$matches[0];
            if ((string)$row->ARIZALANAN_MAKINE_KODU === $matchedId) {
                continue;
            }

            $candidates[] = [
                'id' => (int)$row->id,
                'tarih' => (string)$row->ARIZA_BILDIRIM_TARIHI,
                'makine_adi' => $nameRaw,
                'eski_kod' => (string)$row->ARIZALANAN_MAKINE_KODU,
                'yeni_kod' => $matchedId,
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        $mode = $apply === 1 ? 'UYGULANDI' : 'ÖNİZLEME';
        $this->stdout("\n=== Arıza Uygun Kayıtlar ($mode) ===\n");
        $this->stdout("Limit: $limit\n");
        $this->stdout("Bulunan aday: " . count($candidates) . "\n\n");

        $updated = 0;
        foreach ($candidates as $idx => $candidate) {
            $line = sprintf(
                "%d) ariza_id=%d | tarih=%s | eski_kod=%s | yeni_kod=%s | makine=%s\n",
                $idx + 1,
                (int)$candidate['id'],
                (string)$candidate['tarih'],
                (string)$candidate['eski_kod'],
                (string)$candidate['yeni_kod'],
                (string)$candidate['makine_adi']
            );
            $this->stdout($line);

            if ($apply === 1) {
                $row = ArizaTakip::findOne((int)$candidate['id']);
                if ($row !== null) {
                    $row->ARIZALANAN_MAKINE_KODU = (string)$candidate['yeni_kod'];
                    if ($row->save(false, ['ARIZALANAN_MAKINE_KODU'])) {
                        $updated++;
                    }
                }
            }
        }

        if ($apply === 1) {
            $this->stdout("\nGüncellenen kayıt sayısı: $updated\n");
        } else {
            $this->stdout("\nUygulamak için: php yii eslestirme/ariza-uygun-kayitlar $limit 1\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBakimUygunKayitlar(int $limit = 5, int $apply = 0): int
    {
        [$byExactName, $normalizedEquipmentNames, $equipmentIds] = $this->buildEquipmentLookup();

        if (empty($equipmentIds)) {
            $this->stdout("Ekipman kaydı bulunamadı.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $pivotExisting = BakimTakipEkipman::find()
            ->select(['bakim_id', 'ekipman_id'])
            ->asArray()
            ->all();

        $existingMap = [];
        foreach ($pivotExisting as $pivot) {
            $bakimId = (int)$pivot['bakim_id'];
            if (!isset($existingMap[$bakimId])) {
                $existingMap[$bakimId] = [];
            }
            $existingMap[$bakimId][(string)$pivot['ekipman_id']] = true;
        }

        $candidates = [];
        $rows = BakimTakip::find()->orderBy(['id' => SORT_DESC])->all();
        foreach ($rows as $row) {
            $textRaw = trim((string)$row->SISTEM_CIHAZ_OZELLIK);
            if ($textRaw === '') {
                continue;
            }

            $matches = $this->findMatches(
                $textRaw,
                $byExactName,
                $normalizedEquipmentNames,
                $equipmentIds,
                false
            );

            if (count($matches) !== 1) {
                continue;
            }

            $matchedId = (string)$matches[0];
            $bakimId = (int)$row->id;
            if (isset($existingMap[$bakimId][$matchedId])) {
                continue;
            }

            $candidates[] = [
                'id' => $bakimId,
                'tarih' => (string)$row->TARIH,
                'text' => $textRaw,
                'ekipman_id' => $matchedId,
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        $mode = $apply === 1 ? 'UYGULANDI' : 'ÖNİZLEME';
        $this->stdout("\n=== Bakım Uygun Kayıtlar ($mode) ===\n");
        $this->stdout("Limit: $limit\n");
        $this->stdout("Bulunan aday: " . count($candidates) . "\n\n");

        $inserted = 0;
        foreach ($candidates as $idx => $candidate) {
            $lineText = (string)$candidate['text'];
            if (mb_strlen($lineText) > 120) {
                $lineText = mb_substr($lineText, 0, 120) . '...';
            }

            $line = sprintf(
                "%d) bakim_id=%d | tarih=%s | ekipman_id=%s | metin=%s\n",
                $idx + 1,
                (int)$candidate['id'],
                (string)$candidate['tarih'],
                (string)$candidate['ekipman_id'],
                $lineText
            );
            $this->stdout($line);

            if ($apply === 1) {
                $pivot = new BakimTakipEkipman();
                $pivot->bakim_id = (int)$candidate['id'];
                $pivot->ekipman_id = (string)$candidate['ekipman_id'];
                if ($pivot->save()) {
                    $inserted++;
                }
            }
        }

        if ($apply === 1) {
            $this->stdout("\nEklenen link sayısı: $inserted\n");
        } else {
            $this->stdout("\nUygulamak için: php yii eslestirme/bakim-uygun-kayitlar $limit 1\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBakimEslesmeyenOrnekler(int $limit = 10): int
    {
        [$byExactName, $normalizedEquipmentNames, $equipmentIds] = $this->buildEquipmentLookup();

        if (empty($equipmentIds)) {
            $this->stdout("Ekipman kaydı bulunamadı.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $unmatched = [];
        $scanned = 0;

        $rows = BakimTakip::find()->orderBy(['id' => SORT_DESC])->all();
        foreach ($rows as $row) {
            $scanned++;
            $textRaw = trim((string)$row->SISTEM_CIHAZ_OZELLIK);
            if ($textRaw === '') {
                continue;
            }

            $matches = $this->findMatches(
                $textRaw,
                $byExactName,
                $normalizedEquipmentNames,
                $equipmentIds,
                false
            );

            if (empty($matches)) {
                $unmatched[] = [
                    'id' => (int)$row->id,
                    'text' => $textRaw,
                    'tarih' => (string)$row->TARIH,
                ];
            }

            if (count($unmatched) >= $limit) {
                break;
            }
        }

        $this->stdout("\n=== Bakım Takip Eşleşmeyen Örnekler ===\n");
        $this->stdout("Tarama: $scanned kayıt\n");
        $this->stdout("Gösterilen örnek: " . count($unmatched) . "\n\n");

        foreach ($unmatched as $idx => $item) {
            $text = (string)$item['text'];
            if (mb_strlen($text) > 140) {
                $text = mb_substr($text, 0, 140) . '...';
            }
            $line = sprintf(
                "%d) id=%d | tarih=%s | sistem/cihaz=%s\n",
                $idx + 1,
                (int)$item['id'],
                (string)$item['tarih'],
                $text
            );
            $this->stdout($line);
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionEkipmanKodlari(int $apply = 0): int
    {
        [$byExactName, $normalizedEquipmentNames, $equipmentIds] = $this->buildEquipmentLookup();

        if (empty($equipmentIds)) {
            $this->stdout("Ekipman kaydı bulunamadı.\n");
            return self::EXIT_CODE_NORMAL;
        }

        $arizaUpdated = 0;
        $arizaMatched = 0;
        $arizaAmbiguous = 0;
        $arizaNoMatch = 0;

        $arizaRows = ArizaTakip::find()->all();
        foreach ($arizaRows as $row) {
            $nameRaw = trim((string)$row->ARIZALANAN_MAKINE_ADI);
            if ($nameRaw === '') {
                $arizaNoMatch++;
                continue;
            }

            $matches = $this->findMatches(
                $nameRaw,
                $byExactName,
                $normalizedEquipmentNames,
                $equipmentIds,
                true
            );

            if (count($matches) === 1) {
                $arizaMatched++;
                $matchedId = $matches[0];
                if ((string)$row->ARIZALANAN_MAKINE_KODU !== $matchedId) {
                    if ($apply === 1) {
                        $row->ARIZALANAN_MAKINE_KODU = $matchedId;
                        if ($row->save(false, ['ARIZALANAN_MAKINE_KODU'])) {
                            $arizaUpdated++;
                        }
                    } else {
                        $arizaUpdated++;
                    }
                }
            } elseif (count($matches) > 1) {
                $arizaAmbiguous++;
            } else {
                $arizaNoMatch++;
            }
        }

        $pivotExisting = BakimTakipEkipman::find()
            ->select(['bakim_id', 'ekipman_id'])
            ->asArray()
            ->all();

        $existingMap = [];
        foreach ($pivotExisting as $pivot) {
            $bakimId = (int)$pivot['bakim_id'];
            if (!isset($existingMap[$bakimId])) {
                $existingMap[$bakimId] = [];
            }
            $existingMap[$bakimId][(string)$pivot['ekipman_id']] = true;
        }

        $bakimMatchedRows = 0;
        $bakimInsertedLinks = 0;
        $bakimAmbiguous = 0;
        $bakimNoMatch = 0;
        $bakimBlankIgnored = 0;

        $bakimRows = BakimTakip::find()->all();
        foreach ($bakimRows as $row) {
            $bakimId = (int)$row->id;
            $textRaw = trim((string)$row->SISTEM_CIHAZ_OZELLIK);

            if ($textRaw === '') {
                $bakimBlankIgnored++;
                continue;
            }

            $matches = $this->findMatches(
                $textRaw,
                $byExactName,
                $normalizedEquipmentNames,
                $equipmentIds,
                false
            );

            if (empty($matches)) {
                $bakimNoMatch++;
                continue;
            }

            $newLinks = 0;
            foreach ($matches as $matchedId) {
                if (isset($existingMap[$bakimId][$matchedId])) {
                    continue;
                }

                if ($apply === 1) {
                    $pivot = new BakimTakipEkipman();
                    $pivot->bakim_id = $bakimId;
                    $pivot->ekipman_id = $matchedId;
                    if ($pivot->save()) {
                        $newLinks++;
                        $existingMap[$bakimId][$matchedId] = true;
                    }
                } else {
                    $newLinks++;
                }
            }

            if ($newLinks > 0) {
                $bakimMatchedRows++;
                $bakimInsertedLinks += $newLinks;
            } elseif (count($matches) > 1) {
                $bakimAmbiguous++;
            }
        }

        $mode = $apply === 1 ? 'UYGULANDI' : 'ÖNİZLEME';
        $this->stdout("\n=== Ekipman Kod Eşleştirme ($mode) ===\n");
        $this->stdout("Arıza Takip - Eşleşen Satır: $arizaMatched\n");
        $this->stdout("Arıza Takip - Güncellenecek/Güncellenen Kod: $arizaUpdated\n");
        $this->stdout("Arıza Takip - Belirsiz Eşleşme: $arizaAmbiguous\n");
        $this->stdout("Arıza Takip - Eşleşmeyen: $arizaNoMatch\n\n");

        $this->stdout("Bakım Takip - Eşleşen Satır: $bakimMatchedRows\n");
        $this->stdout("Bakım Takip - Eklenecek/Eklenen Pivot Link: $bakimInsertedLinks\n");
        $this->stdout("Bakım Takip - Belirsiz Eşleşme: $bakimAmbiguous\n");
        $this->stdout("Bakım Takip - Eşleşmeyen: $bakimNoMatch\n\n");
        $this->stdout("Bakım Takip - Boş alan (yoksayıldı): $bakimBlankIgnored\n\n");

        if ($apply !== 1) {
            $this->stdout("Uygulamak için: php yii eslestirme/ekipman-kodlari 1\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    private function buildEquipmentLookup(): array
    {
        $equipments = Ekipman::find()
            ->select(['id', 'MALZEMENIN_TANIMI'])
            ->asArray()
            ->all();

        $byExactName = [];
        $normalizedEquipmentNames = [];
        $equipmentIds = [];

        foreach ($equipments as $equipment) {
            $id = (string)$equipment['id'];
            $equipmentIds[] = $id;

            $name = $this->normalize((string)($equipment['MALZEMENIN_TANIMI'] ?? ''));
            if ($name !== '') {
                if (!isset($byExactName[$name])) {
                    $byExactName[$name] = [];
                }
                $byExactName[$name][] = $id;
                $normalizedEquipmentNames[$id] = $name;
            }
        }

        return [$byExactName, $normalizedEquipmentNames, $equipmentIds];
    }

    private function findMatches(
        string $rawText,
        array $byExactName,
        array $normalizedEquipmentNames,
        array $equipmentIds,
        bool $singleMatchOnly
    ): array {
        $normalizedText = $this->normalize($rawText);
        if ($normalizedText === '') {
            return [];
        }

        if (isset($byExactName[$normalizedText])) {
            $exact = array_values(array_unique($byExactName[$normalizedText]));
            if ($singleMatchOnly) {
                return count($exact) === 1 ? $exact : [];
            }
            return $exact;
        }

        $matches = [];

        foreach ($equipmentIds as $equipmentId) {
            if ($equipmentId !== '' && mb_stripos($rawText, $equipmentId) !== false) {
                $matches[$equipmentId] = true;
            }
        }

        foreach ($normalizedEquipmentNames as $equipmentId => $equipmentName) {
            if ($equipmentName === '' || mb_strlen($equipmentName) < 4) {
                continue;
            }

            if (mb_stripos($normalizedText, $equipmentName) !== false) {
                $matches[$equipmentId] = true;
            }
        }

        $result = array_values(array_keys($matches));
        if ($singleMatchOnly) {
            return count($result) === 1 ? $result : [];
        }

        return $result;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ı' => 'i',
            'İ' => 'i',
            'i̇' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9\s\-]/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);

        return trim((string)$value);
    }

    private function extractEquipmentIdFromBakim(BakimTakip $row): ?string
    {
        $text = trim((string)$row->SISTEM_CIHAZ_OZELLIK);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^([A-Z0-9\-]+)\s+-/u', $text, $matches) === 1) {
            return (string)$matches[1];
        }

        return null;
    }

    private function mergeDistinctValues(array $values): string
    {
        $unique = [];
        foreach ($values as $value) {
            $trimmed = trim((string)$value);
            if ($trimmed === '' || in_array($trimmed, $unique, true)) {
                continue;
            }
            $unique[] = $trimmed;
        }

        return implode(' | ', $unique);
    }

    private function mergeCommaSeparatedValues(array $values): string
    {
        $unique = [];
        foreach ($values as $value) {
            $parts = preg_split('/\s*,\s*/u', trim((string)$value)) ?: [];
            foreach ($parts as $part) {
                $trimmed = trim((string)$part);
                if ($trimmed === '' || in_array($trimmed, $unique, true)) {
                    continue;
                }
                $unique[] = $trimmed;
            }
        }

        return implode(', ', $unique);
    }

    private function stringifyValue($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string)$value;
    }
}
