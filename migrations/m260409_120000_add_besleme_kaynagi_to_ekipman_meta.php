<?php

use yii\db\Migration;

class m260409_120000_add_besleme_kaynagi_to_ekipman_meta extends Migration
{
    public function safeUp()
    {
        $this->addColumn('ekipman_meta', 'besleme_kaynagi_id', $this->string(50)->null()->after('TANITIM_FOTO'));
        $this->createIndex('idx-ekipman_meta-besleme_kaynagi_id', 'ekipman_meta', 'besleme_kaynagi_id');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-ekipman_meta-besleme_kaynagi_id', 'ekipman_meta');
        $this->dropColumn('ekipman_meta', 'besleme_kaynagi_id');
    }
}
