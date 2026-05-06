<?php

namespace app\models;

use yii\db\ActiveRecord;
use app\models\Ekipman;

class EkipmanMeta extends ActiveRecord
{
    public static function tableName()
    {
        return 'ekipman_meta';
    }

    public function rules()
    {
        return [
            [['ekipman_id'], 'required'],
            [['ekipman_id', 'DURUM', 'TANITIM_FOTO', 'besleme_kaynagi_id', 'salter_kodu', 'salter_akim'], 'string', 'max' => 255],
            [['DURUM'], 'default', 'value' => 'AKTIF'],
            [['ENLEM', 'BOYLAM'], 'number'],
            [['updated_at', 'besleme_kaynagi_id', 'salter_kodu', 'salter_akim'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'ekipman_id' => 'Ekipman ID',
            'DURUM' => 'Durum',
            'ENLEM' => 'Enlem',
            'BOYLAM' => 'Boylam',
            'TANITIM_FOTO' => 'Tanıtım Fotoğrafı',
            'besleme_kaynagi_id' => 'Enerji Kaynağı',
            'salter_kodu' => 'Şalter Kodu',
            'salter_akim' => 'Şalter Akım',
            'updated_at' => 'Güncelleme Tarihi',
        ];
    }

    public function getBeslemeKaynagi()
    {
        return $this->hasOne(Ekipman::class, ['id' => 'besleme_kaynagi_id']);
    }
}
