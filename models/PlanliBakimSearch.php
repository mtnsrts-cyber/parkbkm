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
        $query = PlanliBakim::find()
            ->alias('pb')
            ->innerJoin(['e' => Ekipman::tableName()], 'e.id = pb.kodu')
            ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
            ->andWhere("COALESCE(NULLIF(em.DURUM, ''), 'AKTIF') = 'AKTIF'")
            ->orderBy(['pb.tarihi' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeParam'   => 'per-page',
                'pageSizeLimit'   => [1, 500],
            ],
            'sort' => false,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'pb.kodu', $this->kodu])
              ->andFilterWhere(['like', 'pb.tanimi', $this->tanimi])
              ->andFilterWhere(['like', 'pb.periyodu', $this->periyodu])
              ->andFilterWhere(['like', 'pb.durumu', $this->durumu]);

        if (!empty($this->tarihi)) {
            $query->andFilterWhere(['pb.tarihi' => $this->tarihi]);
        }

        if (!empty($this->globalSearch)) {
            $query->andWhere(['or',
                ['like', 'pb.kodu', $this->globalSearch],
                ['like', 'pb.tanimi', $this->globalSearch],
                ['like', 'pb.periyodu', $this->globalSearch],
                ['like', 'pb.durumu', $this->globalSearch],
            ]);
        }

        return $dataProvider;
    }
}
