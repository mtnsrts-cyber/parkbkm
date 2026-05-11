<?php

use yii\db\Migration;

/**
 * analizor_olcum tip alanina 'period15dk' (15 dakikalik ortalama) secenegini ekler.
 */
class m260506_080000_add_period15dk_to_analizor_tip extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('analizor_olcum', 'tip',
            "ENUM('anlik','saatlik','period15dk') NOT NULL DEFAULT 'anlik' COMMENT 'Kayıt tipi'"
        );
    }

    public function safeDown()
    {
        $this->alterColumn('analizor_olcum', 'tip',
            "ENUM('anlik','saatlik') NOT NULL DEFAULT 'anlik' COMMENT 'Kayıt tipi'"
        );
    }
}
