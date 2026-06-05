<?php

namespace app\commands;

use app\models\BakimTakip;
use app\models\BakimTakipEkipman;
use app\models\Ekipman;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Expression;
use yii\db\Query;

class BakimTakipController extends Controller
{
    public function actionEkipmanEsles($apply = 0): int
    {
        $models = BakimTakip::find()
            ->alias('bt')
            ->leftJoin(['bte' => BakimTakipEkipman::tableName()], 'bte.bakim_id = bt.id')
            ->where(['bte.bakim_id' => null])
            ->andWhere(['not', ['bt.SISTEM_CIHAZ_OZELLIK' => null]])
            ->andWhere(['<>', 'bt.SISTEM_CIHAZ_OZELLIK', ''])
            ->orderBy(['bt.id' => SORT_ASC])
            ->all();

        $incelenen = 0;
        $eslesecek = 0;
        $baglanan = 0;
        $apply = (int)$apply === 1;

        foreach ($models as $model) {
            $incelenen++;
            $ekipmanIds = $this->extractEkipmanIdsFromText((string)$model->SISTEM_CIHAZ_OZELLIK);
            if (empty($ekipmanIds)) {
                continue;
            }

            $eslesecek++;
            $this->stdout('#' . $model->id . ' -> ' . implode(', ', $ekipmanIds) . "\n");

            if (!$apply) {
                continue;
            }

            foreach ($ekipmanIds as $ekipmanId) {
                $exists = BakimTakipEkipman::find()
                    ->where(['bakim_id' => (int)$model->id, 'ekipman_id' => (string)$ekipmanId])
                    ->exists();
                if ($exists) {
                    continue;
                }

                $pivot = new BakimTakipEkipman();
                $pivot->bakim_id = (int)$model->id;
                $pivot->ekipman_id = (string)$ekipmanId;
                if ($pivot->save(false)) {
                    $baglanan++;
                }
            }
        }

        $this->stdout("İncelenen: {$incelenen}, eşleşebilecek kayıt: {$eslesecek}, eklenen bağlantı: {$baglanan}\n");
        if (!$apply) {
            $this->stdout("Değişiklik yapılmadı. Uygulamak için: yii bakim-takip/ekipman-esles 1\n");
        }

        return ExitCode::OK;
    }

    public function actionDuzeltCokluPlanliYapilanIs(): int
    {
        $ids = (new Query())
            ->select('bt.id')
            ->from(['bt' => BakimTakip::tableName()])
            ->innerJoin(['bte' => BakimTakipEkipman::tableName()], 'bte.bakim_id = bt.id')
            ->where(new Expression("bt.PERIYODIK_PLANLI LIKE 'PLANLI%'"))
            ->groupBy('bt.id')
            ->having(new Expression('COUNT(bte.ekipman_id) > 1'))
            ->orderBy(['bt.id' => SORT_ASC])
            ->column();

        if (empty($ids)) {
            $this->stdout("Güncellenecek çoklu planlı bakım kaydı bulunamadı.\n");
            return ExitCode::OK;
        }

        $this->stdout('İncelenecek kayıt sayısı: ' . count($ids) . "\n");

        $guncellenen = 0;
        foreach ($ids as $id) {
            $model = BakimTakip::findOne((int)$id);
            if ($model === null) {
                continue;
            }

            $onceki = (string)$model->YAPILAN_IS;
            $model->syncGeneratedPlanliYapilanIs();
            $yeni = (string)$model->YAPILAN_IS;

            if ($yeni === '' || $yeni === $onceki) {
                continue;
            }

            BakimTakip::updateAll(['YAPILAN_IS' => $yeni], ['id' => $model->id]);
            $guncellenen++;
            $this->stdout("Güncellendi: #{$model->id}\n");
        }

        $this->stdout("Toplam güncellenen kayıt: {$guncellenen}\n");
        return ExitCode::OK;
    }

    /**
     * Tüm çelik sapanlar (MBL-CLS-*) için bugün tarihli "PLANLI: İLK BAKIM"
     * bakım takip kaydı oluşturur ve tüm çelik sapanları ona bağlar.
     */
    public function actionCelikSapanIlkBakim(): int
    {
        $ekipmanIds = Ekipman::find()
            ->where(['like', 'id', 'MBL-CLS-%', false])
            ->orderBy(['id' => SORT_ASC])
            ->select('id')
            ->column();

        if (empty($ekipmanIds)) {
            $this->stdout("Çelik sapan (MBL-CLS-*) ekipman bulunamadı.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('Bulundu: ' . count($ekipmanIds) . " çelik sapan\n");

        $bakim = new BakimTakip();
        $bakim->BAKIM_GENEL       = 'BAKIM';
        $bakim->PERIYODIK_PLANLI  = 'PLANLI: İLK BAKIM';
        $bakim->TARIH             = '2026-04-01';
        $bakim->BAKIM_SURESI_SAAT = 10;
        $bakim->YERI              = 'SAHA';
        $bakim->ISI_YAPANLAR      = 'Mesut Işık';
        $bakim->SISTEM_CIHAZ_OZELLIK = 'Çelik sapanlar';
        $bakim->ekipmanIds        = $ekipmanIds;

        if (!$bakim->save()) {
            $this->stdout("HATA - kayıt oluşturulamadı:\n");
            foreach ($bakim->errors as $alan => $hatalar) {
                $this->stdout("  {$alan}: " . implode(', ', $hatalar) . "\n");
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Bakım kaydı oluşturuldu. ID: #{$bakim->id}\n");
        $this->stdout("Bağlanan ekipman sayısı: " . count($ekipmanIds) . "\n");
        return ExitCode::OK;
    }

    private function extractEkipmanIdsFromText(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        preg_match_all('/[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+/', $text, $matches);
        $candidates = array_values(array_unique(array_map('trim', $matches[0] ?? [])));
        if (empty($candidates)) {
            return [];
        }

        return Ekipman::find()
            ->select('id')
            ->where(['id' => $candidates])
            ->orderBy(['id' => SORT_ASC])
            ->column();
    }
}
