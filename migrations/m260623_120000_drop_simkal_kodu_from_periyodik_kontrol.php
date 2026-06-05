<?php

use yii\db\Migration;

class m260623_120000_drop_simkal_kodu_from_periyodik_kontrol extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%periyodik_kontrol}}');
        if ($table !== null && isset($table->columns['simkal_kodu'])) {
            $this->dropColumn('{{%periyodik_kontrol}}', 'simkal_kodu');
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%periyodik_kontrol}}');
        if ($table !== null && !isset($table->columns['simkal_kodu'])) {
            $this->addColumn('{{%periyodik_kontrol}}', 'simkal_kodu', $this->string(100)->after('cihaz_adi'));
        }
    }
}
