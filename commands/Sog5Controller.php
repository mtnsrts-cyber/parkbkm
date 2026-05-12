<?php

namespace app\commands;

use Yii;
use yii\console\Controller;

/**
 * SOG5 Güç Kontrol Rölesi verilerini okuyup kaydeder.
 * Cron ile çalıştırılabilir.
 * 
 * Usage: php yii sog5/veri
 */
class Sog5Controller extends Controller
{
    public function actionIndex()
    {
        echo "SOG5 veri okunuyor...\n";
        
        $result = $this->actionVeri();
        
        if ($result['success'] ?? false) {
            echo "✓ SOG5 verileri kaydedildi.\n";
            return 0;
        } else {
            echo "✗ Hata: " . ($result['message'] ?? 'Bilinmiyor') . "\n";
            return 1;
        }
    }

    public function actionVeri()
    {
        $ip = '192.168.201.248';
        $port = 502;
        $unitId = 5;
        $timeout = 5;

        try {
            $readU16 = function($addr) use ($ip, $port, $unitId, $timeout) {
                $r = \app\helpers\ModbusHelper::readHoldingRegisters($ip, $port, $unitId, $addr, 1, $timeout);
                return $r[0] ?? null;
            };
            $readU32 = function($addr) use ($ip, $port, $unitId, $timeout) {
                $r = \app\helpers\ModbusHelper::readHoldingRegisters($ip, $port, $unitId, $addr, 2, $timeout);
                if (!$r || count($r) < 2) return null;
                $v = ($r[0] << 16) | $r[1];
                return $v;
            };
            $readS32 = function($addr) use ($ip, $port, $unitId, $timeout) {
                $r = \app\helpers\ModbusHelper::readHoldingRegisters($ip, $port, $unitId, $addr, 2, $timeout);
                if (!$r || count($r) < 2) return null;
                $v = ($r[0] << 16) | $r[1];
                return $v >= 0x80000000 ? ($v - 0x100000000) : $v;
            };

            // Modbus'tan null gelen değerleri bölme hatasına sokmamak için null-safe bölme.
            $safeDiv = function($val, $divisor) {
                return $val !== null ? $val / $divisor : null;
            };

            $data = [
                'e_l1_import_kwh'         => $safeDiv($readU32(0),  1000),
                'e_l2_import_kwh'         => $safeDiv($readU32(2),  1000),
                'e_l3_import_kwh'         => $safeDiv($readU32(4),  1000),
                'e_l1_reactive_ind_kvarh' => $safeDiv($readU32(12), 1000),
                'e_l2_reactive_ind_kvarh' => $safeDiv($readU32(14), 1000),
                'e_l3_reactive_ind_kvarh' => $safeDiv($readU32(16), 1000),
                'e_l1_reactive_cap_kvarh' => $safeDiv($readU32(18), 1000),
                'e_l2_reactive_cap_kvarh' => $safeDiv($readU32(20), 1000),
                'e_l3_reactive_cap_kvarh' => $safeDiv($readU32(22), 1000),
                'p_l1_kw'       => $safeDiv($readS32(24), 1000),
                'p_l2_kw'       => $safeDiv($readS32(26), 1000),
                'p_l3_kw'       => $safeDiv($readS32(28), 1000),
                'q_ind_l1_kvar' => $safeDiv($readS32(30), 1000),
                'q_ind_l2_kvar' => $safeDiv($readS32(32), 1000),
                'q_ind_l3_kvar' => $safeDiv($readS32(34), 1000),
                'q_cap_l1_var'  => $safeDiv($readS32(36), 1000),
                'q_cap_l2_var'  => $safeDiv($readS32(38), 1000),
                'q_cap_l3_var'  => $safeDiv($readS32(40), 1000),
                'pf_l1'   => $safeDiv($readU16(42), 100),
                'pf_l2'   => $safeDiv($readU16(43), 100),
                'pf_l3'   => $safeDiv($readU16(44), 100),
                'f_l1_hz' => $safeDiv($readU16(47), 10),
                'v_l1_v'  => $readU16(56),
                'v_l2_v'  => $readU16(57),
                'v_l3_v'  => $readU16(58),
                'i_l1_a'  => $safeDiv($readU32(59), 100),
                'i_l2_a'  => $safeDiv($readU32(61), 100),
                'i_l3_a'  => $safeDiv($readU32(63), 100),
                'step_status_bits' => $readU32(73),
            ];

            $step = $data['step_status_bits'] ?? 0;
            for ($i = 1; $i <= 12; $i++) {
                $data['step_' . $i] = (bool)($step & (1 << ($i - 1)));
            }

            $data['v_l1_l2_v'] = isset($data['v_l1_v']) ? round($data['v_l1_v'] * 1.732) : null;
            $data['v_l2_l3_v'] = isset($data['v_l2_v']) ? round($data['v_l2_v'] * 1.732) : null;
            $data['v_l3_l1_v'] = isset($data['v_l3_v']) ? round($data['v_l3_v'] * 1.732) : null;

            $pTotal = ($data['p_l1_kw'] ?? 0) + ($data['p_l2_kw'] ?? 0) + ($data['p_l3_kw'] ?? 0);
            $data['p_total_kw'] = $pTotal;

            $pfValues = array_filter([$data['pf_l1'] ?? null, $data['pf_l2'] ?? null, $data['pf_l3'] ?? null]);
            $data['pf_average'] = !empty($pfValues) ? array_sum($pfValues) / count($pfValues) : null;

            $indTotal = (($data['q_ind_l1_kvar'] ?? 0) + ($data['q_ind_l2_kvar'] ?? 0) + ($data['q_ind_l3_kvar'] ?? 0));
            $capTotal = (($data['q_cap_l1_var'] ?? 0) + ($data['q_cap_l2_var'] ?? 0) + ($data['q_cap_l3_var'] ?? 0));
            $data['compensation_inductive_kvar'] = $indTotal;
            $data['compensation_capacitive_kvar'] = $capTotal;
            $data['compensation_total_kvar'] = round($indTotal - $capTotal, 2);
            
            $data['timestamp'] = date('Y-m-d H:i:s');
            
            // Raw veriyi kaydet
            $this->logSog5Raw($data);
            
            // Saatlik enerji kaydet
            $this->logSog5Energy($data);

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function logSog5Raw(array $data)
    {
        file_put_contents('C:\xampp\htdocs\basic\logs\sog5_debug.log', date('Y-m-d H:i:s') . ' logSog5Raw called' . PHP_EOL, FILE_APPEND);

        $datetime = date('Y-m-d H:i:00');

        // Son satırı çek: hem aynı-dakika kontrolü hem last-known-good için kullan
        $lastRaw = Yii::$app->db->createCommand(
            'SELECT * FROM sog5_energy_logs_raw ORDER BY log_datetime DESC LIMIT 1'
        )->queryOne() ?: [];

        if (($lastRaw['log_datetime'] ?? null) === $datetime) {
            file_put_contents('C:\xampp\htdocs\basic\logs\sog5_debug.log', date('Y-m-d H:i:s') . ' Same minute - skipped' . PHP_EOL, FILE_APPEND);
            return;
        }

        // Her kolon için: Modbus'tan gelen değer null ise son geçerli DB değerini kullan (last-known-good)
        $lkg = function(string $key) use ($data, $lastRaw): ?float {
            return $data[$key] ?? ($lastRaw[$key] ?? null);
        };

        // e_total_kwh: üç faz toplamı — herhangi biri null ise son raw değere düş
        $eL1 = $data['e_l1_import_kwh'] ?? null;
        $eL2 = $data['e_l2_import_kwh'] ?? null;
        $eL3 = $data['e_l3_import_kwh'] ?? null;
        $eTotal = ($eL1 !== null && $eL2 !== null && $eL3 !== null)
            ? ($eL1 + $eL2 + $eL3)
            : ($lastRaw['e_total_kwh'] ?? null);

        Yii::$app->db->createCommand()->insert('sog5_energy_logs_raw', [
            'log_datetime'            => $datetime,
            'e_total_kwh'             => $eTotal,
            'e_l1_reactive_ind_kvarh' => $lkg('e_l1_reactive_ind_kvarh'),
            'e_l2_reactive_ind_kvarh' => $lkg('e_l2_reactive_ind_kvarh'),
            'e_l3_reactive_ind_kvarh' => $lkg('e_l3_reactive_ind_kvarh'),
            'e_l1_reactive_cap_kvarh' => $lkg('e_l1_reactive_cap_kvarh'),
            'e_l2_reactive_cap_kvarh' => $lkg('e_l2_reactive_cap_kvarh'),
            'e_l3_reactive_cap_kvarh' => $lkg('e_l3_reactive_cap_kvarh'),
        ])->execute();

        file_put_contents('C:\xampp\htdocs\basic\logs\sog5_debug.log', date('Y-m-d H:i:s') . ' Inserted: ' . $datetime . PHP_EOL, FILE_APPEND);

        // Önceki saatin verisini sog5_energy_logs'a aktar (temizlikten önce)
        $this->migrateHourlyLogs();

        // Eski kayıtları temizle (48 saatten eski)
        $cleanup = date('Y-m-d H:i:00', strtotime('-48 hours'));
        Yii::$app->db->createCommand('DELETE FROM sog5_energy_logs_raw WHERE log_datetime < :cleanup')
            ->bindValue(':cleanup', $cleanup)->execute();
    }
    
    private function migrateHourlyLogs()
    {
        // Tam saat (örneğin 15:00) - bir önceki saat (14:00) verisini aktar
        $currentHour = date('Y-m-d H:00:00');
        $prevHour = date('Y-m-d H:00:00', strtotime('-1 hour'));
        
        // Önceki saat zaten var mı kontrol et
        $exists = Yii::$app->db->createCommand('SELECT log_date FROM sog5_energy_logs WHERE log_date = :hour LIMIT 1')
            ->bindValue(':hour', $prevHour)->queryOne();
        if ($exists) return;
        
        // Önceki saatin raw verisini al — kümülatif sayaç için MAX (saatin en son geçerli değeri)
        // NULLIF(..., 0): Modbus okuma hatasından kaynaklanan 0 değerlerini dışla
        $hourData = Yii::$app->db->createCommand('
            SELECT 
                MAX(NULLIF(e_total_kwh,             0)) AS e_total,
                MAX(NULLIF(e_l1_reactive_ind_kvarh, 0)) AS q_ind_1,
                MAX(NULLIF(e_l2_reactive_ind_kvarh, 0)) AS q_ind_2,
                MAX(NULLIF(e_l3_reactive_ind_kvarh, 0)) AS q_ind_3,
                MAX(NULLIF(e_l1_reactive_cap_kvarh, 0)) AS q_cap_1,
                MAX(NULLIF(e_l2_reactive_cap_kvarh, 0)) AS q_cap_2,
                MAX(NULLIF(e_l3_reactive_cap_kvarh, 0)) AS q_cap_3
            FROM sog5_energy_logs_raw 
            WHERE log_datetime >= :start AND log_datetime < :end
        ')->bindValue(':start', $prevHour)->bindValue(':end', $currentHour)->queryOne();
        
        if ($hourData && $hourData['e_total'] > 0) {
            // Toplam ve reaktif her faz ayrı hesapla
            $eTotal = round($hourData['e_total'], 1);
            $qInd1 = round($hourData['q_ind_1'] ?? 0, 1);
            $qInd2 = round($hourData['q_ind_2'] ?? 0, 1);
            $qInd3 = round($hourData['q_ind_3'] ?? 0, 1);
            $qCap1 = round($hourData['q_cap_1'] ?? 0, 1);
            $qCap2 = round($hourData['q_cap_2'] ?? 0, 1);
            $qCap3 = round($hourData['q_cap_3'] ?? 0, 1);
            
            Yii::$app->db->createCommand()->insert('sog5_energy_logs', [
                'log_date' => $prevHour,
                'e_l1_kwh' => 0, 'e_l2_kwh' => 0, 'e_l3_kwh' => 0,
                'e_total_kwh' => $eTotal,
                'e_l1_reactive_ind_kvarh' => $qInd1,
                'e_l2_reactive_ind_kvarh' => $qInd2,
                'e_l3_reactive_ind_kvarh' => $qInd3,
                'e_l1_reactive_cap_kvarh' => $qCap1,
                'e_l2_reactive_cap_kvarh' => $qCap2,
                'e_l3_reactive_cap_kvarh' => $qCap3,
                'q_ind_kvarh' => $qInd1 + $qInd2 + $qInd3,
                'q_cap_kvarh' => $qCap1 + $qCap2 + $qCap3,
            ])->execute();
            
            file_put_contents('C:\xampp\htdocs\basic\logs\sog5_debug.log', date('Y-m-d H:i:s') . ' Migrated: ' . $prevHour . PHP_EOL, FILE_APPEND);
        }
    }

    private function logSog5Energy(array $data, $force = false)
    {
        $now = date('Y-m-d H:00:00');

        // Son energy log satırını çek: hem tekrar kontrolü hem last-known-good için
        $lastLog = Yii::$app->db->createCommand(
            'SELECT * FROM sog5_energy_logs ORDER BY log_date DESC LIMIT 1'
        )->queryOne() ?: [];

        if (!$force && ($lastLog['log_date'] ?? null) === $now) {
            return;
        }

        // Her kolon için: Modbus'tan gelen değer null ise son energy log değerini kullan (last-known-good)
        // Not: $data'daki import anahtarları DB kolonlarından farklı (e_l1_import_kwh → e_l1_kwh)
        $eL1 = $data['e_l1_import_kwh'] ?? ($lastLog['e_l1_kwh'] ?? null);
        $eL2 = $data['e_l2_import_kwh'] ?? ($lastLog['e_l2_kwh'] ?? null);
        $eL3 = $data['e_l3_import_kwh'] ?? ($lastLog['e_l3_kwh'] ?? null);

        $eTotal = ($eL1 !== null && $eL2 !== null && $eL3 !== null)
            ? ($eL1 + $eL2 + $eL3)
            : ($lastLog['e_total_kwh'] ?? null);

        $lkg = function(string $key) use ($data, $lastLog): ?float {
            return $data[$key] ?? ($lastLog[$key] ?? null);
        };

        $l1ind = $lkg('e_l1_reactive_ind_kvarh');
        $l2ind = $lkg('e_l2_reactive_ind_kvarh');
        $l3ind = $lkg('e_l3_reactive_ind_kvarh');
        $l1cap = $lkg('e_l1_reactive_cap_kvarh');
        $l2cap = $lkg('e_l2_reactive_cap_kvarh');
        $l3cap = $lkg('e_l3_reactive_cap_kvarh');

        Yii::$app->db->createCommand()->insert('sog5_energy_logs', [
            'log_date'                => $now,
            'e_l1_kwh'               => $eL1,
            'e_l2_kwh'               => $eL2,
            'e_l3_kwh'               => $eL3,
            'e_total_kwh'            => $eTotal,
            'q_ind_kvarh'            => ($l1ind ?? 0) + ($l2ind ?? 0) + ($l3ind ?? 0),
            'q_cap_kvarh'            => ($l1cap ?? 0) + ($l2cap ?? 0) + ($l3cap ?? 0),
            'e_l1_reactive_ind_kvarh' => $l1ind,
            'e_l2_reactive_ind_kvarh' => $l2ind,
            'e_l3_reactive_ind_kvarh' => $l3ind,
            'e_l1_reactive_cap_kvarh' => $l1cap,
            'e_l2_reactive_cap_kvarh' => $l2cap,
            'e_l3_reactive_cap_kvarh' => $l3cap,
        ])->execute();
    }
}