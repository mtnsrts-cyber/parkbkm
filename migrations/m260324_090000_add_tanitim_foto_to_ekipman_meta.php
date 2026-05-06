<?php

use yii\db\Migration;

class m260324_090000_add_tanitim_foto_to_ekipman_meta extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman_meta}}', true);
        if ($table === null) {
            return;
        }

        if ($table->getColumn('TANITIM_FOTO') === null) {
            $this->addColumn('{{%ekipman_meta}}', 'TANITIM_FOTO', $this->string(255)->null()->after('BOYLAM'));
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman_meta}}', true);
        if ($table !== null && $table->getColumn('TANITIM_FOTO') !== null) {
            $this->dropColumn('{{%ekipman_meta}}', 'TANITIM_FOTO');
        }
    }
}