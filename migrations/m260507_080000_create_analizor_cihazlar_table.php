<?php

use yii\db\Migration;

/**
 * Enerji analizörü cihazlarının bağlantı parametrelerini saklayan tablo.
 */
class m260507_080000_create_analizor_cihazlar_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('analizor_cihazlar', [
            'id'           => $this->primaryKey(),
            'ekipman_kodu' => $this->string(30)->notNull()->comment('Ekipman kodu (örn: ESNT-ADP-03)'),
            'ip'           => $this->string(15)->notNull()->comment('Modbus TCP IP adresi'),
            'port'         => $this->integer()->notNull()->defaultValue(502)->comment('TCP portu'),
            'device_id'    => $this->integer()->notNull()->defaultValue(1)->comment('Modbus Unit/Slave ID'),
            'model'        => $this->string(50)->notNull()->comment('Analizör modeli'),
            'aciklama'     => $this->string(255)->null()->comment('Açıklama'),
            'aktif'        => $this->boolean()->notNull()->defaultValue(true)->comment('Aktif mi?'),
        ]);

        $this->createIndex('idx_analizor_cihazlar_ekipman', 'analizor_cihazlar', 'ekipman_kodu', true);
        $this->createIndex('idx_analizor_cihazlar_aktif', 'analizor_cihazlar', 'aktif');
    }

    public function safeDown()
    {
        $this->dropTable('analizor_cihazlar');
    }
}
