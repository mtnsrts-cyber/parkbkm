<?php

use yii\db\Migration;

class m260225_140000_create_periyodik_kontrol_table extends Migration
{
    /**
     * Tabloyu güvenli şekilde oluştur (varsa tekrar oluşturma).
     */
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%periyodik_kontrol}}');

        if ($table === null) {
            $this->createTable('{{%periyodik_kontrol}}', [
                'id' => $this->primaryKey(),
                // CSV'deki "kodu" alanı: ekipman tablosundaki id ile eşleşir
                'ekipman_id' => $this->string(50)->notNull(),
                // CİHAZ ADI/DEVICE NAME
                'cihaz_adi' => $this->string(255)->notNull(),
                // SİMKAL KODU / SERİ NO
                'simkal_kodu' => $this->string(100),
                // RAPOR NO
                'rapor_no' => $this->string(100),
                // BULUNDUĞU YER / LOCATION
                'bulundugu_yer' => $this->string(255),
                // ADET / PCS
                'adet' => $this->integer(),
                // KABUL DEĞERLERİ
                'kabul_degerleri' => $this->string(255),
                // ÖLÇÜM DEĞERLERİ
                'olcum_degerleri' => $this->string(255),
                // SON KONTROL TARİHİ
                'son_kontrol_tarihi' => $this->date(),
                // GELECEK KONTROL TARİHİ
                'gelecek_kontrol_tarihi' => $this->date(),
                // PERİYODİK KONTROL GEREKTİRİR (EVET/BOŞ) -> boolean
                'periyodik_kontrol_gerektirir' => $this->boolean()->defaultValue(false),
                // PERİYODİK KONTROL GEREKTİRMEZ (EVET/BOŞ) -> boolean
                'periyodik_kontrol_gerektirmez' => $this->boolean()->defaultValue(false),
            ]);

            // ekipman ile ilişki için index (FK tanımı olmadan)
            $this->createIndex(
                'idx_periyodik_kontrol_ekipman',
                '{{%periyodik_kontrol}}',
                'ekipman_id'
            );
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%periyodik_kontrol}}');
    }
}
