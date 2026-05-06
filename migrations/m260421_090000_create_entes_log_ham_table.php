<?php

use yii\db\Migration;

/**
 * ENTES log ham verileri (profil/akim/gerilim) icin ortak tablo.
 */
class m260421_090000_create_entes_log_ham_table extends Migration
{
    public function safeUp()
    {
        $columns = [
            'id' => $this->primaryKey(),
            'ekipman_id' => $this->string(30)->notNull()->comment('Cihaz kimligi (or: A107)'),
            'log_type' => $this->string(16)->notNull()->comment('profile/current/voltage'),
            'start_date' => $this->dateTime()->notNull()->comment('Kayit baslangic zamani'),
            'end_date' => $this->dateTime()->null()->comment('Kayit bitis zamani'),
        ];

        // En genis paket voltage oldugu icin 25 float alan ayrildi.
        for ($i = 0; $i < 25; $i++) {
            $columns['field_' . $i] = $this->float()->null()->comment('Ham alan ' . $i);
        }

        $columns['synced_at'] = $this->dateTime()->notNull()->comment('DB aktarim zamani');

        $this->createTable('entes_log_ham', $columns, 'ENGINE=InnoDB DEFAULT CHARSET=utf8');

        $this->createIndex(
            'idx_entes_log_ham_unique',
            'entes_log_ham',
            ['ekipman_id', 'log_type', 'start_date'],
            true
        );

        $this->createIndex(
            'idx_entes_log_ham_lookup',
            'entes_log_ham',
            ['ekipman_id', 'start_date']
        );
    }

    public function safeDown()
    {
        $this->dropTable('entes_log_ham');
    }
}
