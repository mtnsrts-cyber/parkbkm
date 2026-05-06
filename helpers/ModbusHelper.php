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
}
