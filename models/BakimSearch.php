<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class BakimSearch extends Bakim
{
    public $globalSearch;

    public function rules()
    {
        return [
            [['baslik', 'durum', 'globalSearch'], 'safe'],
        ];
    }

    public function search($params)
    {
        $query = Bakim::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeParam'   => 'per-page',
                'pageSizeLimit'   => [1, 500],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query
            ->andFilterWhere(['like', 'baslik', $this->baslik])
            ->andFilterWhere(['durum' => $this->durum]);

        if (!empty($this->globalSearch)) {
            $query->andWhere(['or',
                ['like', 'baslik', $this->globalSearch],
                ['like', 'ekipman_id', $this->globalSearch],
                ['like', 'durum', $this->globalSearch],
            ]);
        }

        return $dataProvider;
    }
}
