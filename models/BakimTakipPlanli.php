<?php

namespace app\models;

use yii\db\ActiveRecord;

class BakimTakipPlanli extends ActiveRecord
{
    public const TYPE_GENERATED = 'generated';
    public const TYPE_SOURCE = 'source';

    public static function tableName()
    {
        return 'bakim_takip_planli';
    }

    public function rules()
    {
        return [
            [['bakim_id', 'planli_id'], 'required'],
            [['bakim_id', 'planli_id'], 'integer'],
            [['link_type'], 'string', 'max' => 20],
            [['created_at'], 'safe'],
            [['link_type'], 'in', 'range' => [self::TYPE_GENERATED, self::TYPE_SOURCE]],
            [['link_type'], 'default', 'value' => self::TYPE_GENERATED],
            [['bakim_id', 'planli_id'], 'unique', 'targetAttribute' => ['bakim_id', 'planli_id']],
        ];
    }
}
