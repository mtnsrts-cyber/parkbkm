<?php

use yii\db\Migration;

/**
 * Enerji analizörü ölçüm verilerini saklayacak tablo.
 */
class m260418_070000_create_analizor_olcum_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('analizor_olcum', [
            'id'        => $this->primaryKey(),
            'ekipman_id' => $this->string(30)->notNull()->comment('Ekipman kodu'),
            'created_at' => $this->dateTime()->notNull()->comment('Ölçüm zamanı'),

            // Gerilim (V)
            'v_l1n'  => $this->decimal(8, 1),
            'v_l2n'  => $this->decimal(8, 1),
            'v_l3n'  => $this->decimal(8, 1),
            'v_l1l2' => $this->decimal(8, 1),
            'v_l2l3' => $this->decimal(8, 1),
            'v_l3l1' => $this->decimal(8, 1),

            // Güç (W)
            'p_l1' => $this->decimal(12, 1),
            'p_l2' => $this->decimal(12, 1),
            'p_l3' => $this->decimal(12, 1),
            'p_total_kw' => $this->decimal(10, 2)->comment('Toplam aktif güç kW'),
            's_total_kva' => $this->decimal(10, 2)->comment('Toplam görünür güç kVA'),
            'q_total_kvar' => $this->decimal(10, 2)->comment('Toplam reaktif güç kVAR'),

            // Akım
            'i_avg_a' => $this->decimal(8, 1)->comment('Ortalama akım A'),
            'i_n'     => $this->decimal(8, 0)->comment('Nötr akım mA'),

            // Frekans
            'freq' => $this->decimal(6, 2),

            // Güç faktörü
            'pf_l1'  => $this->decimal(5, 3),
            'pf_l2'  => $this->decimal(5, 3),
            'pf_l3'  => $this->decimal(5, 3),
            'pf_avg' => $this->decimal(5, 3),

            // Enerji sayaçları (kWh)
            'e_import_total_kwh' => $this->decimal(14, 3)->comment('Toplam aktif enerji tüketimi kWh'),
            'e_export_total_kwh' => $this->decimal(14, 3)->comment('Toplam aktif enerji ihraç kWh'),
        ]);

        $this->createIndex('idx_analizor_olcum_ekipman', 'analizor_olcum', 'ekipman_id');
        $this->createIndex('idx_analizor_olcum_tarih', 'analizor_olcum', 'created_at');
        $this->createIndex('idx_analizor_olcum_ekipman_tarih', 'analizor_olcum', ['ekipman_id', 'created_at']);
    }

    public function safeDown()
    {
        $this->dropTable('analizor_olcum');
    }
}
