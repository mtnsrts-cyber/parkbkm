<?php

namespace app\models;

use yii\db\ActiveRecord;

class BakimTakipEkipman extends ActiveRecord
{
    public static function tableName()
    {
        return 'bakim_takip_ekipman';
    }

    public function rules()
    {
        return [
            [['bakim_id', 'ekipman_id'], 'required'],
            [['bakim_id'], 'integer'],
            [['ekipman_id'], 'string', 'max' => 50],
            [['bakim_id', 'ekipman_id'], 'unique', 'targetAttribute' => ['bakim_id', 'ekipman_id']],
        ];
    }
}
