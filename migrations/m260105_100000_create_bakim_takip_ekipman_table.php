<?php

use yii\db\Migration;

class m260105_100000_create_bakim_takip_ekipman_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%bakim_takip_ekipman}}', [
            'id' => $this->primaryKey(),
            'bakim_id' => $this->integer()->notNull(),
            'ekipman_id' => $this->string(50)->notNull(),
        ]);

        $this->createIndex('idx_bakim_takip_ekipman_bakim', '{{%bakim_takip_ekipman}}', 'bakim_id');
        $this->createIndex('idx_bakim_takip_ekipman_uniq', '{{%bakim_takip_ekipman}}', ['bakim_id','ekipman_id'], true);

        // Yalnızca bakim_id için FK verelim (ekipman tablosunda id tipi net değil)
        $this->addForeignKey(
            'fk_bakim_takip_ekipman_bakim',
            '{{%bakim_takip_ekipman}}',
            'bakim_id',
            '{{%bakim_takip}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function down()
    {
        $this->dropForeignKey('fk_bakim_takip_ekipman_bakim', '{{%bakim_takip_ekipman}}');
        $this->dropTable('{{%bakim_takip_ekipman}}');
    }
}
