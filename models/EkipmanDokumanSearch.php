<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class EkipmanDokumanSearch extends EkipmanDokuman
{
    public $globalSearch;

    public function rules()
    {
        return [
            [['ekipman_kodu', 'dokuman_turu', 'dokuman_adi', 'dosya_yolu', 'globalSearch'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = EkipmanDokuman::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeParam'   => 'per-page',
                'pageSizeLimit'   => [1, 500],
            ],
            'sort' => [
                'defaultOrder' => [
                    'ekipman_kodu' => SORT_ASC,
                    'dokuman_turu' => SORT_ASC,
                    'dokuman_adi' => SORT_ASC,
                ],
            ],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'ekipman_kodu', $this->ekipman_kodu])
            ->andFilterWhere(['like', 'dokuman_turu', $this->dokuman_turu])
            ->andFilterWhere(['like', 'dokuman_adi', $this->dokuman_adi])
            ->andFilterWhere(['like', 'dosya_yolu', $this->dosya_yolu]);

        if (!empty($this->globalSearch)) {
            $query->andWhere(['or',
                ['like', 'ekipman_kodu', $this->globalSearch],
                ['like', 'dokuman_turu', $this->globalSearch],
                ['like', 'dokuman_adi', $this->globalSearch],
                ['like', 'dosya_yolu', $this->globalSearch],
            ]);
        }

        return $dataProvider;
    }
}
