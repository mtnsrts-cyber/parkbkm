<?php

use yii\db\Migration;

class m260608_130000_add_trafo_donusum_yonu_to_ekipman_meta extends Migration
{
    public function safeUp()
    {
        $this->addColumn('ekipman_meta', 'trafo_donusum_yonu', $this->string(20)->null()->after('besleme_girisleri_json'));
    }

    public function safeDown()
    {
        $this->dropColumn('ekipman_meta', 'trafo_donusum_yonu');
    }
}
