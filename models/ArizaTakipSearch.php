<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class ArizaTakipSearch extends ArizaTakip
{
    public $globalSearch;
    public $quickFilter;

    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['ARIZA_BILDIRIM_TARIHI', 'ARIZA_TARIHI', 'ARIZAYI_BILDIREN', 'ARIZAYA_SEBEBIYET_VEREN_FIRMA', 'ARIZALANAN_MAKINE_ADI', 'ARIZALANAN_MAKINE_KODU', 'ARIZALANAN_PARCA', 'ARIZANIN_MEYDANA_GELDIGI_BOLUM', 'ARIZA_KOK_NEDENI', 'KALICI_AKSIYON', 'ARIZA_SEBEBI', 'ARIZANIN_GIDERILDIGI_TARIH', 'ARIZANIN_SON_DURUMU', 'ARIZANIN_AYRINTILI_ACIKLAMASI', 'globalSearch', 'quickFilter'], 'safe'],
            [['ARIZALI_KALDIGI_SURE_SAAT', 'YEDEK_PARCA_BEKLEME_SURESI_SAAT', 'MALZEME_TUTARI', 'ISCILIK_FIYATI', 'MALIYET_TL'], 'number'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = ArizaTakip::find();

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

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        if ($this->quickFilter === 'open') {
            $query->andWhere(['or', ['ARIZANIN_GIDERILDIGI_TARIH' => null], ['ARIZANIN_GIDERILDIGI_TARIH' => '']]);
        } elseif ($this->quickFilter === 'this-month') {
            $query->andWhere(['between', 'ARIZA_TARIHI', $monthStart, $monthEnd]);
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'ARIZA_BILDIRIM_TARIHI' => $this->ARIZA_BILDIRIM_TARIHI,
            'ARIZA_TARIHI' => $this->ARIZA_TARIHI,
            'ARIZANIN_GIDERILDIGI_TARIH' => $this->ARIZANIN_GIDERILDIGI_TARIH,
            'ARIZALI_KALDIGI_SURE_SAAT' => $this->ARIZALI_KALDIGI_SURE_SAAT,
            'YEDEK_PARCA_BEKLEME_SURESI_SAAT' => $this->YEDEK_PARCA_BEKLEME_SURESI_SAAT,
            'MALZEME_TUTARI' => $this->MALZEME_TUTARI,
            'ISCILIK_FIYATI' => $this->ISCILIK_FIYATI,
            'MALIYET_TL' => $this->MALIYET_TL,
        ]);

        $query->andFilterWhere(['like', 'ARIZAYI_BILDIREN', $this->ARIZAYI_BILDIREN])
            ->andFilterWhere(['like', 'ARIZAYA_SEBEBIYET_VEREN_FIRMA', $this->ARIZAYA_SEBEBIYET_VEREN_FIRMA])
            ->andFilterWhere(['like', 'ARIZALANAN_MAKINE_ADI', $this->ARIZALANAN_MAKINE_ADI])
            ->andFilterWhere(['like', 'ARIZALANAN_MAKINE_KODU', $this->ARIZALANAN_MAKINE_KODU])
            ->andFilterWhere(['like', 'ARIZALANAN_PARCA', $this->ARIZALANAN_PARCA])
            ->andFilterWhere(['like', 'ARIZANIN_MEYDANA_GELDIGI_BOLUM', $this->ARIZANIN_MEYDANA_GELDIGI_BOLUM])
            ->andFilterWhere(['like', 'ARIZA_KOK_NEDENI', $this->ARIZA_KOK_NEDENI])
            ->andFilterWhere(['like', 'KALICI_AKSIYON', $this->KALICI_AKSIYON])
            ->andFilterWhere(['like', 'ARIZA_SEBEBI', $this->ARIZA_SEBEBI])
            ->andFilterWhere(['like', 'ARIZANIN_SON_DURUMU', $this->ARIZANIN_SON_DURUMU])
            ->andFilterWhere(['like', 'ARIZANIN_AYRINTILI_ACIKLAMASI', $this->ARIZANIN_AYRINTILI_ACIKLAMASI]);

        if (!empty($this->globalSearch)) {
            $query->andWhere(['or',
                ['like', 'ARIZALANAN_MAKINE_ADI', $this->globalSearch],
                ['like', 'ARIZALANAN_MAKINE_KODU', $this->globalSearch],
                ['like', 'ARIZAYI_BILDIREN', $this->globalSearch],
                ['like', 'ARIZANIN_MEYDANA_GELDIGI_BOLUM', $this->globalSearch],
                ['like', 'ARIZANIN_SON_DURUMU', $this->globalSearch],
                ['like', 'ARIZA_SEBEBI', $this->globalSearch],
                ['like', 'ARIZANIN_AYRINTILI_ACIKLAMASI', $this->globalSearch],
            ]);
        }

        return $dataProvider;
    }
}
