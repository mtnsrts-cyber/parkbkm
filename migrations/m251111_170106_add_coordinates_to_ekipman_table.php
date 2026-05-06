<?php

use yii\db\Migration;

/**
 * Class m251111_170106_add_coordinates_to_ekipman_table
 */
class m251111_170106_add_coordinates_to_ekipman_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // ENLEM ve BOYLAM sütunlarını ekle (eğer yoksa)
        $table = $this->db->schema->getTableSchema('{{%ekipman}}');
        if ($table !== null && $table->getColumn('ENLEM') === null) {
            $this->addColumn('{{%ekipman}}', 'ENLEM', $this->decimal(10, 8)->null());
        }
        if ($table !== null && $table->getColumn('BOYLAM') === null) {
            $this->addColumn('{{%ekipman}}', 'BOYLAM', $this->decimal(11, 8)->null());
        }
        
        // İsterseniz varsayılan değerler ekleyebilirsiniz
        // Örnek: $this->update('{{%ekipman}}', ['ENLEM' => 41.0082, 'BOYLAM' => 28.9784]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Geri alma işlemi - sütunları sil (varsa)
        $table = $this->db->schema->getTableSchema('{{%ekipman}}');
        if ($table !== null && $table->getColumn('ENLEM') !== null) {
            $this->dropColumn('{{%ekipman}}', 'ENLEM');
        }
        if ($table !== null && $table->getColumn('BOYLAM') !== null) {
            $this->dropColumn('{{%ekipman}}', 'BOYLAM');
        }
    }
}