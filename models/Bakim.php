<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class Bakim extends ActiveRecord
{
    public static function tableName()
    {
        return 'bakim';
    }

    public function rules()
    {
        return [
            [['ekipman_id', 'baslik', 'aciklama'], 'required'],
            [['ekipman_id'], 'integer'],
            [['aciklama'], 'string'],
            [['durum'], 'default', 'value' => 'Açık'],
            [['baslik'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ekipman_id' => 'Ekipman',
            'baslik' => 'Başlık',
            'aciklama' => 'Açıklama',
            'durum' => 'Durum',
        ];
    }
}
