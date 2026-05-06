<?php

namespace app\models;

use yii\db\ActiveRecord;

class EkipmanDokuman extends ActiveRecord
{
    public static function tableName()
    {
        return 'ekipman_dokuman';
    }

    public function rules()
    {
        return [
            [['ekipman_kodu', 'dokuman_turu', 'dokuman_adi'], 'required'],
            [['ekipman_kodu'], 'string', 'max' => 50],
            [['dokuman_turu'], 'string', 'max' => 50],
            [['dokuman_adi'], 'string', 'max' => 255],
            [['dosya_yolu'], 'string', 'max' => 500],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }
}
