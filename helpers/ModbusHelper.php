<?php

namespace app\helpers;

/**
 * Modbus TCP okuyucu — PHP soketle doğrudan cihazdan veri çeker.
 * FC03 (Read Holding Registers) destekler.
 */
class ModbusHelper
{
    private const LOCK_TIMEOUT_MS = 10000;
    private const LOCK_WAIT_US = 50000;

    /**
     * Modbus TCP FC03 ile holding register'ları okur.
     *
     * @param string $ip   Cihaz IP adresi
     * @param int    $port TCP portu (genellikle 502)
     * @param int    $unitId  Slave/Device ID
     * @param int    $startAddr Başlangıç register adresi
     * @param int    $count Okunacak register sayısı (max 125)
     * @param int    $timeout Bağlantı timeout (saniye)
     * @return int[]|false  Register değerleri (16-bit unsigned) veya hata durumunda false
     */
    public static function readHoldingRegisters(string $ip, int $port, int $unitId, int $startAddr, int $count, int $timeout = 3)
    {
        $lockPath = self::acquireGatewayLock($ip, $port);
        $sock = @stream_socket_client("tcp://{$ip}:{$port}", $errno, $errstr, $timeout);
        if (!$sock) {
            self::releaseGatewayLock($lockPath);
            \Yii::warning("Modbus bağlantı hatası: {$errstr} ({$errno})", __METHOD__);
            return false;
        }
        stream_set_timeout($sock, $timeout);

        // Modbus TCP ADU: Transaction ID (2) + Protocol ID (2) + Length (2) + Unit ID (1) + PDU
        // PDU: FC (1) + Start Addr (2) + Quantity (2)
        $transactionId = mt_rand(1, 65535);
        $request = pack('nnn', $transactionId, 0, 6) // Transaction ID + Protocol ID + Length
            . chr($unitId)                              // Unit ID (1 byte)
            . chr(3)                                    // FC03
            . pack('nn', $startAddr, $count);           // Start Address + Quantity

        $written = @fwrite($sock, $request);
        if ($written === false || $written < strlen($request)) {
            fclose($sock);
            self::releaseGatewayLock($lockPath);
            return false;
        }

        // Yanıt: en az 9 byte header (7 MBAP + 1 FC + 1 byte count)
        $header = self::readExact($sock, 9);
        if ($header === false) {
            fclose($sock);
            self::releaseGatewayLock($lockPath);
            return false;
        }

        $h = unpack('ntxid/nprotocol/nlength/Cunit/Cfc/CbyteCount', $header);
        if ($h['fc'] & 0x80) {
            // Exception response
            fclose($sock);
            self::releaseGatewayLock($lockPath);
            \Yii::warning("Modbus exception: FC={$h['fc']}", __METHOD__);
            return false;
        }

        $dataLen = $h['byteCount'];
        $data = self::readExact($sock, $dataLen);
        fclose($sock);
        self::releaseGatewayLock($lockPath);

        if ($data === false || strlen($data) < $dataLen) {
            return false;
        }

        // 16-bit register'lara çevir
        $registers = [];
        for ($i = 0; $i < $dataLen; $i += 2) {
            $registers[] = (ord($data[$i]) << 8) | ord($data[$i + 1]);
        }

        return $registers;
    }

    /**
     * 32-bit unsigned/signed değer oku (2 ardışık register, Big Endian word order).
     */
    public static function toUint32(array $regs, int $offset): ?int
    {
        if (!isset($regs[$offset], $regs[$offset + 1])) {
            return null;
        }
        $v = $regs[$offset] * 65536 + $regs[$offset + 1];
        return ($v === 0xFFFFFFFF) ? null : $v;
    }

    public static function toSint32(array $regs, int $offset): ?int
    {
        $v = self::toUint32($regs, $offset);
        if ($v === null) {
            return null;
        }
        return ($v >= 0x80000000) ? $v - 0x100000000 : $v;
    }

    /**
     * ENTES MPR-45S-V2 register haritasına göre tüm ölçüm değerlerini parse eder.
     *
     * @param int[] $regs 0-99 arası register dizisi
     * @return array İsimlendirilmiş ölçüm değerleri
     */
    public static function parseEntesMpr45(array $regs): array
    {
        $g = function ($offset, $div, $signed = false) use ($regs) {
            $v = $signed ? self::toSint32($regs, $offset) : self::toUint32($regs, $offset);
            return ($v !== null) ? round($v / $div, 3) : null;
        };

        $data = [
            'V_L1N'   => $g(0,  10),
            'V_L2N'   => $g(2,  10),
            'V_L3N'   => $g(4,  10),
            'V_L1L2'  => $g(8,  10),
            'V_L2L3'  => $g(10, 10),
            'V_L3L1'  => $g(12, 10),
            'P_L1'    => $g(14, 10),
            'P_L2'    => $g(16, 10),
            'P_L3'    => $g(18, 10),
            'I_N'     => $g(6,  1),
            'Freq'    => $g(24, 100),
            'PF_L1'   => $g(72, 1000, true),
            'PF_L2'   => $g(74, 1000, true),
            'PF_L3'   => $g(76, 1000, true),
            'PF_avg'  => $g(86, 1000, true),
            // Enerji sayaçları (Wh → kWh)
            'E_import_L1_kWh' => $g(26, 1000),
            'E_import_L2_kWh' => $g(28, 1000),
            'E_import_L3_kWh' => $g(30, 1000),
            'E_import_total_kWh' => $g(34, 1000),
            'E_export_total_kWh' => $g(36, 1000),
            'Q_L1'  => $g(38, 10, true),
            'Q_L2'  => $g(40, 10, true),
            'Q_L3'  => $g(42, 10, true),
        ];

        // Bazi cihaz/gateway kombinasyonlarinda L-L register'lari 5V gibi gecersiz donebiliyor.
        // L-N degerleri guvenilirken L-L mantiksiz ise, fazor yaklasimi ile L-L'yi yeniden hesapla.
        $fixLl = static function (?float $ll, ?float $va, ?float $vb): ?float {
            if ($va === null || $vb === null || $va < 100 || $vb < 100) {
                return $ll;
            }
            if ($ll === null || $ll < 100 || $ll > 600) {
                return round(sqrt($va * $va + $vb * $vb + $va * $vb), 1);
            }
            return $ll;
        };

        $data['V_L1L2'] = $fixLl($data['V_L1L2'], $data['V_L1N'], $data['V_L2N']);
        $data['V_L2L3'] = $fixLl($data['V_L2L3'], $data['V_L2N'], $data['V_L3N']);
        $data['V_L3L1'] = $fixLl($data['V_L3L1'], $data['V_L3N'], $data['V_L1N']);

        // Hesaplanan değerler
        if ($data['P_L1'] !== null && $data['P_L2'] !== null && $data['P_L3'] !== null) {
            $data['P_total_kW'] = round(($data['P_L1'] + $data['P_L2'] + $data['P_L3']) / 1000, 2);

            $sTotal = 0;
            $iTotal = 0;
            $phases = [
                [$data['P_L1'], $data['V_L1N'], $data['PF_L1']],
                [$data['P_L2'], $data['V_L2N'], $data['PF_L2']],
                [$data['P_L3'], $data['V_L3N'], $data['PF_L3']],
            ];
            foreach ($phases as [$p, $v, $pf]) {
                if ($p !== null && $v !== null && $v > 0) {
                    $s = ($pf !== null && abs($pf) > 0.01) ? abs($p / $pf) : $p;
                    $sTotal += $s;
                    $iTotal += $s / $v;
                }
            }
            $data['S_total_kVA'] = round($sTotal / 1000, 2);
            $pw = $data['P_L1'] + $data['P_L2'] + $data['P_L3'];
            $data['Q_total_kVAR'] = round(sqrt(max($sTotal ** 2 - $pw ** 2, 0)) / 1000, 2);
            $data['I_avg_A'] = round($iTotal / 3, 1);
        }

        $data['timestamp'] = date('Y-m-d H:i:s');

        return $data;
    }

    private static function readExact($sock, int $len)
    {
        $buf = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = @fread($sock, $remaining);
            if ($chunk === false || $chunk === '') {
                return false;
            }
            $buf .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $buf;
    }

    private static function acquireGatewayLock(string $ip, int $port): string
    {
        $lockDir = \Yii::getAlias('@runtime/locks');
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0777, true);
        }

        $lockPath = $lockDir . DIRECTORY_SEPARATOR . 'gateway_' . str_replace('.', '_', $ip) . '_' . $port . '.lock';
        $start = microtime(true);

        while (!@mkdir($lockPath)) {
            clearstatcache();

            // Eski kilitleri temizle (10+ saniye eski)
            $lockAge = @filemtime($lockPath);
            if ($lockAge !== false && (time() - $lockAge) > 10) {
                @rmdir($lockPath);
                continue;
            }

            if (((microtime(true) - $start) * 1000) >= self::LOCK_TIMEOUT_MS) {
                throw new \RuntimeException('Gateway lock timeout: ' . $lockPath);
            }
            usleep(self::LOCK_WAIT_US);
        }

        return $lockPath;
    }

    private static function releaseGatewayLock(?string $lockPath): void
    {
        if ($lockPath && is_dir($lockPath)) {
            @rmdir($lockPath);
        }
    }

    /**
     * SOG5 Güç Kontrol Rölesi register haritasına göre tüm ölçüm değerlerini parse eder.
     *
     * @param int[] $regs 0-79 arası register dizisi
     * @return array İsimlendirilmiş ölçüm değerleri
     */
    public static function parseSog5(array $regs): array
    {
        $gU16 = function($offset, $div = 1) use ($regs) {
            return isset($regs[$offset]) ? $regs[$offset] / $div : null;
        };
        $gS16 = function($offset, $div = 1) use ($regs) {
            $v = $regs[$offset] ?? null;
            return $v !== null ? ($v > 32767 ? ($v - 65536) / $div : $v / $div) : null;
        };
        $gU32 = function($offset, $div = 1) use ($regs) {
            if (!isset($regs[$offset], $regs[$offset + 1])) return null;
            $v = ($regs[$offset] << 16) | $regs[$offset + 1];
            return $v / $div;
        };
        $gS32 = function($offset, $div = 1) use ($regs) {
            if (!isset($regs[$offset], $regs[$offset + 1])) return null;
            $v = ($regs[$offset] << 16) | $regs[$offset + 1];
            return $v >= 0x80000000 ? ($v - 0x100000000) / $div : $v / $div;
        };

        $data = [
            'e_l1_import_kwh'   => $gU32(0, 1000),
            'e_l2_import_kwh'  => $gU32(2, 1000),
            'e_l3_import_kwh'  => $gU32(4, 1000),
            'e_l1_reactive_cap_kvarh' => $gU32(12, 1000),
            'e_l2_reactive_cap_kvarh' => $gU32(14, 1000),
            'e_l3_reactive_cap_kvarh' => $gU32(16, 1000),
            'e_l1_reactive_ind_kvarh' => $gU32(18, 1000),
            'e_l2_reactive_ind_kvarh' => $gU32(20, 1000),
            'e_l3_reactive_ind_kvarh' => $gU32(22, 1000),
            'p_l1_kw'           => $gS32(24, 1000),
            'p_l2_kw'           => $gS32(26, 1000),
            'p_l3_kw'           => $gS32(28, 1000),
            'q_ind_l1_kvar'     => $gS32(30, 1000),
            'q_ind_l2_kvar'     => $gS32(32, 1000),
            'q_ind_l3_kvar'     => $gS32(34, 1000),
            'q_cap_l1_var'     => $gS32(36, 1000),
            'q_cap_l2_var'     => $gS32(38, 1000),
            'q_cap_l3_var'     => $gS32(40, 1000),
            'pf_l1'            => $gS16(42, 100),
            'pf_l2'            => $gS16(43, 100),
            'pf_l3'            => $gS16(44, 100),
            'f_l1_hz'          => $gU16(47, 10),
            'f_l2_hz'          => $gU16(48, 10),
            'f_l3_hz'          => $gU16(49, 10),
            'thdi_l1_pct'      => $gU16(50),
            'thdi_l2_pct'      => $gU16(51),
            'thdi_l3_pct'      => $gU16(52),
            'svc_open_l1_pct'  => $gU16(53, 10),
            'svc_open_l2_pct'  => $gU16(54, 10),
            'svc_open_l3_pct'  => $gU16(55, 10),
            'v_l1_v'           => $gU16(56),
            'v_l2_v'           => $gU16(57),
            'v_l3_v'           => $gU16(58),
            'i_l1_a'           => $gU32(59, 100),
            'i_l2_a'           => $gU32(61, 100),
            'i_l3_a'           => $gU32(63, 100),
            'step_status_bits' => $gU32(73),
        ];

        $stepStatus = $data['step_status_bits'] ?? 0;
        for ($i = 1; $i <= 12; $i++) {
            $data['step_' . $i] = (bool)($stepStatus & (1 << ($i - 1)));
        }

        $indL1 = $data['q_ind_l1_kvar'] ?? 0;
        $indL2 = $data['q_ind_l2_kvar'] ?? 0;
        $indL3 = $data['q_ind_l3_kvar'] ?? 0;
        $capL1 = $data['q_cap_l1_var'] ?? 0;
        $capL2 = $data['q_cap_l2_var'] ?? 0;
        $capL3 = $data['q_cap_l3_var'] ?? 0;

        $data['compensation_inductive_kvar'] = round(($indL1 + $indL2 + $indL3) / 1000, 2);
        $data['compensation_capacitive_kvar'] = round(($capL1 + $capL2 + $capL3) / 1000, 2);
        $data['compensation_total_kvar'] = round($data['compensation_inductive_kvar'] - $data['compensation_capacitive_kvar'], 2);

        $pL1 = $data['p_l1_kw'] ?? 0;
        $pL2 = $data['p_l2_kw'] ?? 0;
        $pL3 = $data['p_l3_kw'] ?? 0;
        $data['p_total_kw'] = round(($pL1 + $pL2 + $pL3), 2);

        $pfSum = ($data['pf_l1'] ?? 0) + ($data['pf_l2'] ?? 0) + ($data['pf_l3'] ?? 0);
        $data['pf_average'] = round($pfSum / 3, 2);

        $v1 = $data['v_l1_v'] ?? null;
        $v2 = $data['v_l2_v'] ?? null;
        $v3 = $data['v_l3_v'] ?? null;
        $data['v_l1_l2_v'] = ($v1 && $v2) ? round(sqrt($v1 * $v1 + $v2 * $v2 + $v1 * $v2), 1) : null;
        $data['v_l2_l3_v'] = ($v2 && $v3) ? round(sqrt($v2 * $v2 + $v3 * $v3 + $v2 * $v3), 1) : null;
        $data['v_l3_l1_v'] = ($v3 && $v1) ? round(sqrt($v3 * $v3 + $v1 * $v1 + $v3 * $v1), 1) : null;

        $data['timestamp'] = date('Y-m-d H:i:s');

        return $data;
    }
}
