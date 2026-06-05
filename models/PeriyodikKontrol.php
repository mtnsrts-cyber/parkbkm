<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class PeriyodikKontrol extends ActiveRecord
{
    public $is_eski = 0;

    public static function tableName()
    {
        return 'periyodik_kontrol';
    }

    public function rules()
    {
        return [
            [['ekipman_id', 'cihaz_adi'], 'required'],
            [['adet'], 'integer'],
            [['son_kontrol_tarihi', 'gelecek_kontrol_tarihi'], 'safe'],
            [['ekipman_id'], 'string', 'max' => 50],
            [['cihaz_adi', 'bulundugu_yer', 'kabul_degerleri', 'olcum_degerleri'], 'string', 'max' => 255],
            [['rapor_no'], 'string', 'max' => 100],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ekipman_id' => 'Ekipman Kodu',
            'cihaz_adi' => 'Cihaz Adı',
            'rapor_no' => 'Rapor No',
            'bulundugu_yer' => 'Bulunduğu Yer',
            'adet' => 'Adet',
            'kabul_degerleri' => 'Kabul Değerleri',
            'olcum_degerleri' => 'Ölçüm Değerleri',
            'son_kontrol_tarihi' => 'Son Kontrol Tarihi',
            'gelecek_kontrol_tarihi' => 'Gelecek Kontrol Tarihi',
        ];
    }

    public function getEkipman()
    {
        return $this->hasOne(Ekipman::class, ['id' => 'ekipman_id']);
    }
}
