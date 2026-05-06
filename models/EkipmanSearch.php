<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class EkipmanSearch extends Ekipman
{
    public $globalSearch; // Genel arama için yeni bir property
    public $idList; // Virgülle ayrılmış ekipman id listesi filtresi

    public function rules()
    {
        return [
            [['id', 'MALZEMENIN_TANIMI', 'EKIPMAN_YERI', 'MARKA', 'SERI_NO', 'DURUM', 'globalSearch', 'idList'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = Ekipman::find()->alias('e')
            ->joinWith(['ekipmanEk em'], false);

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

        if (!empty($this->idList)) {
            $ids = array_values(array_filter(array_map('trim', explode(',', (string)$this->idList))));
            if (!empty($ids)) {
                $query->andWhere(['in', 'e.id', $ids]);
            }
        }

        // Genel arama varsa (canlı arama için)
        if (!empty($this->globalSearch)) {
            $query->orFilterWhere(['like', 'e.MALZEMENIN_TANIMI', $this->globalSearch])
                  ->orFilterWhere(['like', 'e.EKIPMAN_YERI', $this->globalSearch])
                  ->orFilterWhere(['like', 'e.MARKA', $this->globalSearch])
                  ->orFilterWhere(['like', 'e.SERI_NO', $this->globalSearch])
                  ->orFilterWhere(['like', 'e.id', $this->globalSearch])
                  ->orWhere(['like', "COALESCE(NULLIF(em.DURUM, ''), 'AKTIF')", strtoupper((string)$this->globalSearch), false]);

            $this->applyDurumFilter($query);
        } else {
            // Normal filtreleme (her alan için ayrı)
            $query->andFilterWhere(['like', 'e.id', $this->id])
                  ->andFilterWhere(['like', 'e.MALZEMENIN_TANIMI', $this->MALZEMENIN_TANIMI])
                  ->andFilterWhere(['like', 'e.EKIPMAN_YERI', $this->EKIPMAN_YERI])
                  ->andFilterWhere(['like', 'e.MARKA', $this->MARKA])
                  ->andFilterWhere(['like', 'e.SERI_NO', $this->SERI_NO]);

            $this->applyDurumFilter($query);
        }

        return $dataProvider;
    }

    private function applyDurumFilter($query): void
    {
        if (empty($this->DURUM)) {
            return;
        }

        $durum = strtoupper((string)$this->DURUM);
        if ($durum === 'AKTIF') {
            $query->andWhere("COALESCE(NULLIF(em.DURUM, ''), 'AKTIF') = 'AKTIF'");
        } elseif ($durum === 'HURDA') {
            $query->andWhere("COALESCE(NULLIF(em.DURUM, ''), 'AKTIF') = 'HURDA'");
        }
    }
}