<?php

use yii\db\Migration;

class m260310_140000_drop_durum_coordinates_from_ekipman_table extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman}}', true);
        if ($table === null) {
            return;
        }

        if ($table->getColumn('DURUM') !== null) {
            $this->dropColumn('{{%ekipman}}', 'DURUM');
        }

        if ($table->getColumn('ENLEM') !== null) {
            $this->dropColumn('{{%ekipman}}', 'ENLEM');
        }

        if ($table->getColumn('BOYLAM') !== null) {
            $this->dropColumn('{{%ekipman}}', 'BOYLAM');
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%ekipman}}', true);
        if ($table === null) {
            return;
        }

        if ($table->getColumn('DURUM') === null) {
            $this->addColumn('{{%ekipman}}', 'DURUM', $this->string(20)->defaultValue('AKTIF'));
        }

        if ($table->getColumn('ENLEM') === null) {
            $this->addColumn('{{%ekipman}}', 'ENLEM', $this->decimal(10, 8)->null());
        }

        if ($table->getColumn('BOYLAM') === null) {
            $this->addColumn('{{%ekipman}}', 'BOYLAM', $this->decimal(11, 8)->null());
        }
    }
}
