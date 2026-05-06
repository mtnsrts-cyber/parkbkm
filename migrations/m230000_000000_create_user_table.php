<?php

use yii\db\Migration;

class m230000_000000_create_user_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(50)->notNull()->unique(),
            'password_hash' => $this->string()->notNull(),
            'auth_key' => $this->string(32),
            'role' => $this->string(20)->defaultValue('user'), // user / admin gibi
            'status' => $this->smallInteger()->defaultValue(1),
        ]);
    }

    public function down()
    {
        $this->dropTable('{{%user}}');
    }
}

