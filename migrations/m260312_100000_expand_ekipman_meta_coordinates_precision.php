<?php

use yii\db\Migration;

class m260312_100000_expand_ekipman_meta_coordinates_precision extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman_meta}}', true);
        if ($table === null) {
            return;
        }

        if ($table->getColumn('ENLEM') !== null) {
            $this->alterColumn('{{%ekipman_meta}}', 'ENLEM', $this->decimal(12, 4)->null());
        }

        if ($table->getColumn('BOYLAM') !== null) {
            $this->alterColumn('{{%ekipman_meta}}', 'BOYLAM', $this->decimal(12, 4)->null());
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman_meta}}', true);
        if ($table === null) {
            return;
        }

        if ($table->getColumn('ENLEM') !== null) {
            $this->alterColumn('{{%ekipman_meta}}', 'ENLEM', $this->decimal(10, 8)->null());
        }

        if ($table->getColumn('BOYLAM') !== null) {
            $this->alterColumn('{{%ekipman_meta}}', 'BOYLAM', $this->decimal(11, 8)->null());
        }
    }
}
