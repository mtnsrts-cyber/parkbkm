<?php

use yii\db\Migration;

/**
 * analizor_olcum tablosuna tip kolonu ekler.
 * 'anlik' = anlık detaylı kayıt (60s), 'saatlik' = saatlik ortalama.
 */
class m260418_120000_add_tip_to_analizor_olcum extends Migration
{
    public function safeUp()
    {
        $this->addColumn('analizor_olcum', 'tip', "ENUM('anlik','saatlik') NOT NULL DEFAULT 'anlik' COMMENT 'Kayıt tipi' AFTER ekipman_id");
        $this->createIndex('idx_analizor_olcum_tip', 'analizor_olcum', 'tip');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_analizor_olcum_tip', 'analizor_olcum');
        $this->dropColumn('analizor_olcum', 'tip');
    }
}
