<?php

use yii\db\Migration;

class m260103_130000_create_ariza_takip_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%ariza_takip}}', [
            'id' => $this->primaryKey(),
            'ARIZA_BILDIRIM_TARIHI' => $this->date()->null(),
            'ARIZA_TARIHI' => $this->date()->null(),
            'ARIZAYI_BILDIREN' => $this->string(255)->null(),
            'ARIZAYA_SEBEBIYET_VEREN_FIRMA' => $this->string(255)->null(),
            'ARIZALANAN_MAKINE_ADI' => $this->string(255)->null(),
            'ARIZALANAN_MAKINE_KODU' => $this->string(100)->null(),
            'ARIZALANAN_PARCA' => $this->string(255)->null(),
            'ARIZANIN_MEYDANA_GELDIGI_BOLUM' => $this->string(255)->null(),
            'ARIZA_KOK_NEDENI' => $this->text()->null(),
            'KALICI_AKSIYON' => $this->text()->null(),
            'ARIZA_SEBEBI' => $this->text()->null(),
            'ARIZANIN_GIDERILDIGI_TARIH' => $this->date()->null(),
            'ARIZANIN_SON_DURUMU' => $this->string(255)->null(),
            'ARIZALI_KALDIGI_SURE_SAAT' => $this->decimal(10,2)->null(),
            'YEDEK_PARCA_BEKLEME_SURESI_SAAT' => $this->decimal(10,2)->null(),
            'MALZEME_TUTARI' => $this->decimal(12,2)->null(),
            'ISCILIK_FIYATI' => $this->decimal(12,2)->null(),
            'MALIYET_TL' => $this->decimal(12,2)->null(),
            'ARIZANIN_AYRINTILI_ACIKLAMASI' => $this->text()->null(),
            'created_by' => $this->string(255)->null(),
        ]);
    }

    public function down()
    {
        $this->dropTable('{{%ariza_takip}}');
    }
}
