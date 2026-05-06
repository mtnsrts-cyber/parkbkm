<?php

use yii\db\Migration;

/**
 * ENTES MPR-45S-V2 profil log kayıtları tablosu.
 * 15 dakikalık periyotlarla cihazdan çekilen enerji profil verileri.
 */
class m260420_150000_create_entes_profil_log_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('entes_profil_log', [
            'id' => $this->primaryKey(),
            'ekipman_id' => $this->string(30)->notNull()->comment('Cihaz tanımlayıcı (ör: A107)'),
            'start_date' => $this->dateTime()->notNull()->comment('Kayıt başlangıç zamanı'),
            'end_date' => $this->dateTime()->notNull()->comment('Kayıt bitiş zamanı'),
            'field_0' => $this->float()->comment('Profil alan 0'),
            'field_1' => $this->float()->comment('Profil alan 1'),
            'field_2' => $this->float()->comment('Profil alan 2'),
            'field_3' => $this->float()->comment('Profil alan 3'),
            'field_4' => $this->float()->comment('Profil alan 4'),
            'field_5' => $this->float()->comment('Profil alan 5'),
            'field_6' => $this->float()->comment('Profil alan 6'),
            'field_7' => $this->float()->comment('Profil alan 7'),
            'field_8' => $this->float()->comment('Profil alan 8'),
            'field_9' => $this->float()->comment('Profil alan 9'),
            'field_10' => $this->float()->comment('Profil alan 10'),
            'field_11' => $this->float()->comment('Profil alan 11'),
            'synced_at' => $this->dateTime()->notNull()->comment('Veritabanına aktarılma zamanı'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8');

        // Aynı cihaz + aynı zaman dilimi tekrarsız olmalı
        $this->createIndex(
            'idx_entes_profil_log_unique',
            'entes_profil_log',
            ['ekipman_id', 'start_date'],
            true
        );

        // Tarih bazlı sorgular için
        $this->createIndex(
            'idx_entes_profil_log_start',
            'entes_profil_log',
            'start_date'
        );
    }

    public function safeDown()
    {
        $this->dropTable('entes_profil_log');
    }
}
