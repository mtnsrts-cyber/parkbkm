<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\helpers\ModbusHelper;
use app\models\AnalizorOlcum;

/**
 * Enerji analizörlerinden periyodik veri toplama komutu.
 *
 * Kullanım:
 *   yii analizor/topla              → Tüm analizörlerden tek seferlik oku
 *   yii analizor/topla ESNT-ADP-03  → Sadece belirtilen ekipman
 *   yii analizor/dongu              → Sürekli döngüde (varsayılan 60s aralıkla)
 *   yii analizor/dongu 300          → 5 dakika aralıkla
 */
class AnalizorController extends Controller
{
    /**
     * Tüm tanımlı analizörlerden (veya belirtilen ekipmandan) tek seferlik veri oku ve kaydet.
     */
    public function actionTopla(?string $ekipmanId = null): int
    {
        $config = require Yii::getAlias('@app/config/analizor.php');

        if ($ekipmanId !== null) {
            if (!isset($config[$ekipmanId])) {
                $this->stderr("Hata: '{$ekipmanId}' için analizör tanımı bulunamadı.\n");
                return ExitCode::DATAERR;
            }
            $config = [$ekipmanId => $config[$ekipmanId]];
        }

        $ok = 0;
        $fail = 0;

        foreach ($config as $id => $c) {
            $this->stdout("{$id} [{$c['ip']}:{$c['port']}] okunuyor... ");

            $regs = ModbusHelper::readHoldingRegisters($c['ip'], $c['port'], $c['device_id'], 0, 100, 3);
            if ($regs === false) {
                $this->stderr("HATA: Bağlantı kurulamadı.\n");
                $fail++;
                continue;
            }

            $data = ModbusHelper::parseEntesMpr45($regs);
            if (AnalizorOlcum::kaydet($id, $data)) {
                $this->stdout("OK (P={$data['P_total_kW']}kW, E={$data['E_import_total_kWh']}kWh)\n");
                $ok++;
            } else {
                $this->stderr("HATA: Veritabanına kaydedilemedi.\n");
                $fail++;
            }
        }

        $this->stdout("\nToplam: {$ok} başarılı, {$fail} hatalı\n");
        return $fail > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Sürekli döngüde veri topla.
     * @param int $aralik Saniye cinsinden okuma aralığı (varsayılan 60)
     */
    public function actionDongu(int $aralik = 60): int
    {
        $this->stdout("Analizör veri toplama döngüsü başlatıldı (aralık: {$aralik}s)\n");
        $this->stdout("Durdurmak için Ctrl+C\n\n");

        while (true) {
            $this->actionTopla();
            sleep($aralik);
        }
    }

    /**
     * 24 saatten eski anlık kayıtları saatlik ortalamalara sıkıştır.
     *
     *   yii analizor/sikistir          → Varsayılan 24 saat eşik
     *   yii analizor/sikistir 48       → 48 saat eşik
     *   yii analizor/sikistir 24 1     → dry-run (sadece göster)
     */
    public function actionSikistir(int $saatEsik = 24, int $dryRun = 0): int
    {
        $esikZaman = date('Y-m-d H:i:s', strtotime("-{$saatEsik} hours"));
        $this->stdout("Sıkıştırma eşiği: {$esikZaman} öncesi (>{$saatEsik} saat)\n");

        $db = Yii::$app->db;

        // Sıkıştırılacak saatlik grupları bul
        $gruplar = $db->createCommand("
            SELECT ekipman_id,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS saat,
                   COUNT(*) AS kayit_sayisi,
                   AVG(v_l1n) AS v_l1n, AVG(v_l2n) AS v_l2n, AVG(v_l3n) AS v_l3n,
                   AVG(v_l1l2) AS v_l1l2, AVG(v_l2l3) AS v_l2l3, AVG(v_l3l1) AS v_l3l1,
                   AVG(p_l1) AS p_l1, AVG(p_l2) AS p_l2, AVG(p_l3) AS p_l3,
                   AVG(p_total_kw) AS p_total_kw,
                   AVG(s_total_kva) AS s_total_kva,
                   AVG(q_total_kvar) AS q_total_kvar,
                   AVG(i_avg_a) AS i_avg_a, AVG(i_n) AS i_n,
                   AVG(freq) AS freq,
                   AVG(pf_l1) AS pf_l1, AVG(pf_l2) AS pf_l2, AVG(pf_l3) AS pf_l3,
                   AVG(pf_avg) AS pf_avg,
                   MAX(e_import_total_kwh) AS e_import_total_kwh,
                   MAX(e_export_total_kwh) AS e_export_total_kwh
            FROM analizor_olcum
            WHERE tip = 'anlik'
              AND created_at < :esik
            GROUP BY ekipman_id, DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
            ORDER BY saat
        ", [':esik' => $esikZaman])->queryAll();

        if (empty($gruplar)) {
            $this->stdout("Sıkıştırılacak kayıt bulunamadı.\n");
            return ExitCode::OK;
        }

        $toplamAnlik = array_sum(array_column($gruplar, 'kayit_sayisi'));
        $this->stdout("Bulundu: {$toplamAnlik} anlık kayıt → " . count($gruplar) . " saatlik kayıt\n");

        if ($dryRun) {
            $this->stdout("[DRY RUN] Değişiklik yapılmadı.\n");
            foreach (array_slice($gruplar, 0, 10) as $g) {
                $this->stdout("  {$g['ekipman_id']} | {$g['saat']} | {$g['kayit_sayisi']} kayıt → 1 saatlik\n");
            }
            if (count($gruplar) > 10) {
                $this->stdout("  ... ve " . (count($gruplar) - 10) . " grup daha\n");
            }
            return ExitCode::OK;
        }

        $transaction = $db->beginTransaction();
        try {
            $eklenen = 0;
            $silinen = 0;

            foreach ($gruplar as $g) {
                $ekipmanId = $g['ekipman_id'];
                $saat = $g['saat'];
                $saatSonu = date('Y-m-d H:i:s', strtotime($saat) + 3600);

                // Zaten saatlik kayıt varsa atla
                $mevcut = $db->createCommand(
                    "SELECT id FROM analizor_olcum WHERE ekipman_id = :eid AND tip = 'saatlik' AND created_at = :saat LIMIT 1",
                    [':eid' => $ekipmanId, ':saat' => $saat]
                )->queryScalar();

                if (!$mevcut) {
                    // Saatlik özet kaydı ekle
                    $db->createCommand()->insert('analizor_olcum', [
                        'ekipman_id' => $ekipmanId,
                        'tip' => 'saatlik',
                        'created_at' => $saat,
                        'v_l1n' => $g['v_l1n'], 'v_l2n' => $g['v_l2n'], 'v_l3n' => $g['v_l3n'],
                        'v_l1l2' => $g['v_l1l2'], 'v_l2l3' => $g['v_l2l3'], 'v_l3l1' => $g['v_l3l1'],
                        'p_l1' => $g['p_l1'], 'p_l2' => $g['p_l2'], 'p_l3' => $g['p_l3'],
                        'p_total_kw' => $g['p_total_kw'],
                        's_total_kva' => $g['s_total_kva'],
                        'q_total_kvar' => $g['q_total_kvar'],
                        'i_avg_a' => $g['i_avg_a'], 'i_n' => $g['i_n'],
                        'freq' => $g['freq'],
                        'pf_l1' => $g['pf_l1'], 'pf_l2' => $g['pf_l2'], 'pf_l3' => $g['pf_l3'],
                        'pf_avg' => $g['pf_avg'],
                        'e_import_total_kwh' => $g['e_import_total_kwh'],
                        'e_export_total_kwh' => $g['e_export_total_kwh'],
                    ])->execute();
                    $eklenen++;
                }

                // O saatteki eski anlık kayıtları sil
                $cnt = $db->createCommand(
                    "DELETE FROM analizor_olcum WHERE ekipman_id = :eid AND tip = 'anlik' AND created_at >= :bas AND created_at < :son",
                    [':eid' => $ekipmanId, ':bas' => $saat, ':son' => $saatSonu]
                )->execute();
                $silinen += $cnt;
            }

            $transaction->commit();

            $this->stdout("\nTamamlandı!\n");
            $this->stdout("  Eklenen saatlik kayıt: {$eklenen}\n");
            $this->stdout("  Silinen anlık kayıt:   {$silinen}\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->stderr("HATA: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
