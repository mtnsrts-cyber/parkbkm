<?php

use yii\db\Migration;

class m260310_150000_change_imal_yili_to_nullable_smallint extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman}}', true);
        if ($table === null || $table->getColumn('IMAL_YILI') === null) {
            return;
        }

        $this->execute("UPDATE {{%ekipman}} SET IMAL_YILI = NULL WHERE IMAL_YILI = 0");
        $this->alterColumn('{{%ekipman}}', 'IMAL_YILI', $this->smallInteger()->null());
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman}}', true);
        if ($table === null || $table->getColumn('IMAL_YILI') === null) {
            return;
        }

        $this->alterColumn('{{%ekipman}}', 'IMAL_YILI', "YEAR NULL");
    }
}
