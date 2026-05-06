<?php

use yii\db\Migration;

class m260212_170000_add_durum_to_ekipman_table extends Migration
{
    public function safeUp()
    {
        // Ekipman tablosuna DURUM kolonu eklenir.
        // AKTIF / HURDA gibi metinlerle kullanılabilir.
        $this->addColumn('{{%ekipman}}', 'DURUM', $this->string(20)->defaultValue('AKTIF'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%ekipman}}', 'DURUM');
    }
}
