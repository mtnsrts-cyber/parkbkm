<?php

use yii\db\Migration;

class m260310_120000_create_ekipman_meta_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

        if ($this->db->schema->getTableSchema('{{%ekipman_meta}}', true) === null) {
            $this->createTable('{{%ekipman_meta}}', [
                'ekipman_id' => $this->string(50)->notNull(),
                'DURUM' => $this->string(20)->notNull()->defaultValue('AKTIF'),
                'ENLEM' => $this->decimal(10, 8)->null(),
                'BOYLAM' => $this->decimal(11, 8)->null(),
                'updated_at' => $this->dateTime()->null(),
            ], $tableOptions);

            $this->addPrimaryKey('pk_ekipman_meta_ekipman_id', '{{%ekipman_meta}}', 'ekipman_id');
        }

        $metaTable = $this->db->schema->getTableSchema('{{%ekipman_meta}}', true);
        if ($metaTable !== null && $metaTable->getColumn('ekipman_id') !== null) {
            $this->alterColumn('{{%ekipman_meta}}', 'ekipman_id', $this->string(50)->notNull());
        }

        $rows = (new \yii\db\Query())
            ->from('{{%ekipman}}')
            ->all($this->db);

        foreach ($rows as $row) {
            $exists = (new \yii\db\Query())
                ->from('{{%ekipman_meta}}')
                ->where(['ekipman_id' => (string)$row['id']])
                ->exists($this->db);

            if ($exists) {
                continue;
            }

            $durum = isset($row['DURUM']) && $row['DURUM'] !== null && trim((string)$row['DURUM']) !== ''
                ? strtoupper((string)$row['DURUM'])
                : 'AKTIF';

            $this->insert('{{%ekipman_meta}}', [
                'ekipman_id' => (string)$row['id'],
                'DURUM' => $durum,
                'ENLEM' => $row['ENLEM'] ?? null,
                'BOYLAM' => $row['BOYLAM'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%ekipman_meta}}', true) !== null) {
            $this->dropTable('{{%ekipman_meta}}');
        }
    }
}
