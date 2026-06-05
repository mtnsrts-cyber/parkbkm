<?php

use yii\db\Migration;

class m260608_140000_add_trafo_gerilim_degeri_to_ekipman_meta extends Migration
{
    public function safeUp()
    {
        $this->addColumn('ekipman_meta', 'trafo_gerilim_degeri', $this->string(50)->null()->after('trafo_donusum_yonu'));
    }

    public function safeDown()
    {
        $this->dropColumn('ekipman_meta', 'trafo_gerilim_degeri');
    }
}
