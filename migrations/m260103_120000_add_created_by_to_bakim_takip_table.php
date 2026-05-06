<?php

use yii\db\Migration;

class m260103_120000_add_created_by_to_bakim_takip_table extends Migration
{
    public function up()
    {
        // Bakım kaydını oluşturan kullanıcı bilgisini tutmak için alan ekle
        $this->addColumn('{{%bakim_takip}}', 'created_by', $this->string(255)->null()->after('ISI_YAPANLAR'));
    }

    public function down()
    {
        $this->dropColumn('{{%bakim_takip}}', 'created_by');
    }
}
