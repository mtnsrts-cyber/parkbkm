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

            $data = [
                'e_l1_import_kwh' => $readU32(0) / 1000,
                'e_l2_import_kwh' => $readU32(2) / 1000,
                'e_l3_import_kwh' => $readU32(4) / 1000,
                'e_l1_reactive_ind_kvarh' => $readU32(12) / 1000,
                'e_l2_reactive_ind_kvarh' => $readU32(14) / 1000,
                'e_l3_reactive_ind_kvarh' => $readU32(16) / 1000,
                'e_l1_reactive_cap_kvarh' => $readU32(18) / 1000,
                'e_l2_reactive_cap_kvarh' => $readU32(20) / 1000,
                'e_l3_reactive_cap_kvarh' => $readU32(22) / 1000,
                'p_l1_kw' => $readS32(24) / 1000,
                'p_l2_kw' => $readS32(26) / 1000,
                'p_l3_kw' => $readS32(28) / 1000,
                'q_ind_l1_kvar' => $readS32(30) / 1000,
                'q_ind_l2_kvar' => $readS32(32) / 1000,
                'q_ind_l3_kvar' => $readS32(34) / 1000,
                'q_cap_l1_var' => $readS32(36) / 1000,
                'q_cap_l2_var' => $readS32(38) / 1000,
                'q_cap_l3_var' => $readS32(40) / 1000,
                'pf_l1' => $readU16(42) / 100,
                'pf_l2' => $readU16(43) / 100,
                'pf_l3' => $readU16(44) / 100,
                'f_l1_hz' => $readU16(47) / 10,
                'v_l1_v' => $readU16(56),
                'v_l2_v' => $readU16(57),
                'v_l3_v' => $readU16(58),
                'i_l1_a' => $readU32(59) / 100,
                'i_l2_a' => $readU32(61) / 100,
                'i_l3_a' => $readU32(63) / 100,
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
        // Debug log
        file_put_contents('C:\xampp\htdocs\basic\logs\sog5_debug.log', date('Y-m-d H:i:s') . ' logSog5Raw called' . PHP_EOL, FILE_APPEND);
        
        // Aynı dakikada varsa kaydetme
        $datetime = date('Y-m-d H:i:00');
        $last = Yii::$app->db->createCommand('SELECT log_datetime FROM sog5_energy_logs_raw ORDER BY log_datetime DESC LIMIT 1')->queryOne();
        if ($last && $last['log_datetime'] === $datetime) {
            file_put_contents('C:\xampp\htdocs\basic\logs\sog5_debug.log', date('Y-m-d H:i:s') . ' Same minute - skipped' . PHP_EOL, FILE_APPEND);
            return; // Aynı dakika - atla
        }
        
        $eTotal = ($data['e_l1_import_kwh'] ?? 0) + ($data['e_l2_import_kwh'] ?? 0) + ($data['e_l3_import_kwh'] ?? 0);
        
        Yii::$app->db->createCommand()->insert('sog5_energy_logs_raw', [
            'log_datetime' => $datetime,
            'e_total_kwh' => $eTotal,
            'e_l1_reactive_ind_kvarh' => $data['e_l1_reactive_ind_kvarh'] ?? 0,
            'e_l2_reactive_ind_kvarh' => $data['e_l2_reactive_ind_kvarh'] ?? 0,
            'e_l3_reactive_ind_kvarh' => $data['e_l3_reactive_ind_kvarh'] ?? 0,
            'e_l1_reactive_cap_kvarh' => $data['e_l1_reactive_cap_kvarh'] ?? 0,
            'e_l2_reactive_cap_kvarh' => $data['e_l2_reactive_cap_kvarh'] ?? 0,
            'e_l3_reactive_cap_kvarh' => $data['e_l3_reactive_cap_kvarh'] ?? 0,
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
        
        // Önceki saatin raw verisini al (son 5 dakikada ortalama)
        $hourData = Yii::$app->db->createCommand('
            SELECT 
                AVG(e_total_kwh) as e_total,
                AVG(e_l1_reactive_ind_kvarh) as q_ind_1,
                AVG(e_l2_reactive_ind_kvarh) as q_ind_2,
                AVG(e_l3_reactive_ind_kvarh) as q_ind_3,
                AVG(e_l1_reactive_cap_kvarh) as q_cap_1,
                AVG(e_l2_reactive_cap_kvarh) as q_cap_2,
                AVG(e_l3_reactive_cap_kvarh) as q_cap_3
            FROM sog5_energy_logs_raw 
            WHERE log_datetime >= :start AND log_datetime < :end AND e_total_kwh > 1000000
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
        
        $lastLog = Yii::$app->db->createCommand('SELECT log_date FROM sog5_energy_logs ORDER BY log_date DESC LIMIT 1')->queryOne();

        if (!$force && $lastLog && $lastLog['log_date'] === $now) {
            return;
        }

        Yii::$app->db->createCommand()->insert('sog5_energy_logs', [
            'log_date' => $now,
            'e_l1_kwh' => $data['e_l1_import_kwh'] ?? 0,
            'e_l2_kwh' => $data['e_l2_import_kwh'] ?? 0,
            'e_l3_kwh' => $data['e_l3_import_kwh'] ?? 0,
            'e_total_kwh' => ($data['e_l1_import_kwh'] ?? 0) + ($data['e_l2_import_kwh'] ?? 0) + ($data['e_l3_import_kwh'] ?? 0),
            'q_ind_kvarh' => ($data['e_l1_reactive_ind_kvarh'] ?? 0) + ($data['e_l2_reactive_ind_kvarh'] ?? 0) + ($data['e_l3_reactive_ind_kvarh'] ?? 0),
            'q_cap_kvarh' => ($data['e_l1_reactive_cap_kvarh'] ?? 0) + ($data['e_l2_reactive_cap_kvarh'] ?? 0) + ($data['e_l3_reactive_cap_kvarh'] ?? 0),
        ])->execute();
    }
}