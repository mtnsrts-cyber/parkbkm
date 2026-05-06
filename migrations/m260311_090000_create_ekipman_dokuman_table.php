<?php

use yii\db\Migration;

class m260311_090000_create_ekipman_dokuman_table extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%ekipman_dokuman}}', true) !== null) {
            return;
        }

        $this->createTable('{{%ekipman_dokuman}}', [
            'id' => $this->primaryKey(),
            'ekipman_kodu' => $this->string(50)->notNull(),
            'dokuman_turu' => $this->string(50)->notNull(),
            'dokuman_adi' => $this->string(255)->notNull(),
            'dosya_yolu' => $this->string(500)->null(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

        $this->createIndex('idx_ekipman_dokuman_kod_tur', '{{%ekipman_dokuman}}', ['ekipman_kodu', 'dokuman_turu']);
        $this->createIndex('uidx_ekipman_dokuman_unique', '{{%ekipman_dokuman}}', ['ekipman_kodu', 'dokuman_turu', 'dokuman_adi'], true);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%ekipman_dokuman}}', true) === null) {
            return;
        }

        $this->dropTable('{{%ekipman_dokuman}}');
    }
}
