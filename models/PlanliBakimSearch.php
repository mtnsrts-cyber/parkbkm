<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * PlanliBakimSearch, `app\\models\\PlanliBakim` için arama modelidir.
 */
class PlanliBakimSearch extends PlanliBakim
{
    public $globalSearch;

    public function rules()
    {
        return [
            [['kodu', 'tanimi', 'periyodu', 'tarihi', 'durumu', 'globalSearch'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = PlanliBakim::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeParam'   => 'per-page',
                'pageSizeLimit'   => [1, 500],
            ],
            'sort' => [
                'defaultOrder' => [
                    'tarihi' => SORT_DESC,
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'kodu', $this->kodu])
              ->andFilterWhere(['like', 'tanimi', $this->tanimi])
              ->andFilterWhere(['like', 'periyodu', $this->periyodu])
              ->andFilterWhere(['like', 'durumu', $this->durumu]);

        if (!empty($this->tarihi)) {
            $query->andFilterWhere(['tarihi' => $this->tarihi]);
        }

        if (!empty($this->globalSearch)) {
            $query->andWhere(['or',
                ['like', 'kodu', $this->globalSearch],
                ['like', 'tanimi', $this->globalSearch],
                ['like', 'periyodu', $this->globalSearch],
                ['like', 'durumu', $this->globalSearch],
            ]);
        }

        return $dataProvider;
    }
}
