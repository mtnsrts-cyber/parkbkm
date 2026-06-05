<?php

use yii\db\Migration;

class m260608_120000_add_besleme_grubu_to_ekipman_meta extends Migration
{
    public function safeUp()
    {
        $this->addColumn('ekipman_meta', 'besleme_grubu_tipi', $this->string(30)->null()->after('salter_akim'));
        $this->addColumn('ekipman_meta', 'besleme_girisleri_json', $this->text()->null()->after('besleme_grubu_tipi'));
    }

    public function safeDown()
    {
        $this->dropColumn('ekipman_meta', 'besleme_girisleri_json');
        $this->dropColumn('ekipman_meta', 'besleme_grubu_tipi');
    }
}
