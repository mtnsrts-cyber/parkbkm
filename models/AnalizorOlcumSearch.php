<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class AnalizorOlcumSearch extends Model
{
    public $ekipman_id;
    public $tarih_baslangic;
    public $tarih_bitis;

    public function rules()
    {
        return [
            ['ekipman_id', 'string'],
            [['tarih_baslangic', 'tarih_bitis'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function search($params)
    {
        $query = AnalizorOlcum::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['ekipman_id' => $this->ekipman_id]);

        if ($this->tarih_baslangic) {
            $query->andFilterWhere(['>=', 'created_at', $this->tarih_baslangic . ' 00:00:00']);
        }
        if ($this->tarih_bitis) {
            $query->andFilterWhere(['<=', 'created_at', $this->tarih_bitis . ' 23:59:59']);
        }

        return $dataProvider;
    }
}
