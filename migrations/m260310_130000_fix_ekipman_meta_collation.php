<?php

use yii\db\Migration;

class m260310_130000_fix_ekipman_meta_collation extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%ekipman_meta}}', true) === null) {
            return;
        }

        $this->execute("ALTER TABLE {{%ekipman_meta}} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $this->execute("ALTER TABLE {{%ekipman_meta}} MODIFY ekipman_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
        $this->execute("ALTER TABLE {{%ekipman_meta}} MODIFY DURUM VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'AKTIF'");
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%ekipman_meta}}', true) === null) {
            return;
        }

        $this->execute("ALTER TABLE {{%ekipman_meta}} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
        $this->execute("ALTER TABLE {{%ekipman_meta}} MODIFY ekipman_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci NOT NULL");
        $this->execute("ALTER TABLE {{%ekipman_meta}} MODIFY DURUM VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci NOT NULL DEFAULT 'AKTIF'");
    }
}
