<?php

use yii\db\Migration;

class m260410_120000_add_salter_to_ekipman_meta extends Migration
{
    public function safeUp()
    {
        $this->addColumn('ekipman_meta', 'salter_kodu', $this->string(30)->null()->after('besleme_kaynagi_id'));
        $this->addColumn('ekipman_meta', 'salter_akim', $this->string(30)->null()->after('salter_kodu'));
    }

    public function safeDown()
    {
        $this->dropColumn('ekipman_meta', 'salter_akim');
        $this->dropColumn('ekipman_meta', 'salter_kodu');
    }
}
