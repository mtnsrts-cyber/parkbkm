<?php

use yii\db\Migration;

class m260403_120000_create_bakim_takip_planli_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%bakim_takip_planli}}', [
            'id' => $this->primaryKey(),
            'bakim_id' => $this->integer()->notNull(),
            'planli_id' => $this->integer()->notNull(),
            'link_type' => $this->string(20)->notNull()->defaultValue('generated'),
            'created_at' => $this->dateTime()->null(),
        ]);

        $this->createIndex('idx_btp_bakim', '{{%bakim_takip_planli}}', 'bakim_id');
        $this->createIndex('idx_btp_planli', '{{%bakim_takip_planli}}', 'planli_id');
        $this->createIndex('idx_btp_type', '{{%bakim_takip_planli}}', 'link_type');
        $this->createIndex('idx_btp_uniq', '{{%bakim_takip_planli}}', ['bakim_id', 'planli_id'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%bakim_takip_planli}}');
    }
}
