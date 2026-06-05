<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\BakimTakip;

/**
 * BakimTakipSearch represents the model behind the search form of `app\models\BakimTakip`.
 */
class BakimTakipSearch extends BakimTakip
{
    public $TARIH_from;
    public $TARIH_to;
    public $globalSearch;
    public $quickFilter;
    public $activityKind;
    public $planliPeriod;
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['BAKIM_GENEL', 'PERIYODIK_PLANLI', 'TARIH', 'YERI', 'SISTEM_CIHAZ_OZELLIK', 'YAPILAN_IS', 'ISI_YAPANLAR', 'TARIH_from', 'TARIH_to', 'globalSearch', 'quickFilter', 'activityKind', 'planliPeriod'], 'safe'],
            [['BAKIM_SURESI_SAAT'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = BakimTakip::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 20,
                'pageSizeParam'   => 'per-page',
                'pageSizeLimit'   => [1, 500],
            ],
            'sort' => [
                'defaultOrder' => [
                    'TARIH' => SORT_DESC,
                    'id' => SORT_DESC,
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        if ($this->quickFilter === 'this-month') {
            $query->andWhere(['between', 'TARIH', date('Y-m-01'), date('Y-m-t')]);
        }

        if ($this->activityKind === 'general') {
            $query->andWhere(['not in', 'id', BakimTakipPlanli::find()->select('bakim_id')])
                ->andWhere([
                    'or',
                    ['PERIYODIK_PLANLI' => null],
                    ['PERIYODIK_PLANLI' => ''],
                    ['not like', 'PERIYODIK_PLANLI', 'PLANLI'],
                ]);
        } elseif ($this->activityKind === 'planli') {
            $planliSubQuery = BakimTakipPlanli::find()
                ->alias('btp')
                ->select('btp.bakim_id')
                ->innerJoin(['pb' => PlanliBakim::tableName()], 'pb.id = btp.planli_id');

            if (trim((string)$this->planliPeriod) !== '') {
                $planliSubQuery->andWhere(['pb.periyodu' => $this->planliPeriod]);
            }

            $query->andWhere(['in', 'id', $planliSubQuery]);
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'BAKIM_SURESI_SAAT' => $this->BAKIM_SURESI_SAAT,
        ]);

        // Tarih aralığı filtresi
        if (!empty($this->TARIH_from) && !empty($this->TARIH_to)) {
            $query->andFilterWhere(['between', 'TARIH', $this->TARIH_from, $this->TARIH_to]);
        } elseif (!empty($this->TARIH_from)) {
            $query->andFilterWhere(['>=', 'TARIH', $this->TARIH_from]);
        } elseif (!empty($this->TARIH_to)) {
            $query->andFilterWhere(['<=', 'TARIH', $this->TARIH_to]);
        } else {
            // Tek bir tarih değeri verilirse
            $query->andFilterWhere(['TARIH' => $this->TARIH]);
        }

        $query->andFilterWhere(['like', 'BAKIM_GENEL', $this->BAKIM_GENEL])
            ->andFilterWhere(['like', 'PERIYODIK_PLANLI', $this->PERIYODIK_PLANLI])
            ->andFilterWhere(['like', 'YERI', $this->YERI])
            ->andFilterWhere(['like', 'SISTEM_CIHAZ_OZELLIK', $this->SISTEM_CIHAZ_OZELLIK])
            ->andFilterWhere(['like', 'YAPILAN_IS', $this->YAPILAN_IS])
            ->andFilterWhere(['like', 'ISI_YAPANLAR', $this->ISI_YAPANLAR]);

        if (!empty($this->globalSearch)) {
            $query->andWhere(['or',
                ['like', 'SISTEM_CIHAZ_OZELLIK', $this->globalSearch],
                ['like', 'YAPILAN_IS', $this->globalSearch],
                ['like', 'YERI', $this->globalSearch],
                ['like', 'ISI_YAPANLAR', $this->globalSearch],
                ['like', 'BAKIM_GENEL', $this->globalSearch],
            ]);
        }

        return $dataProvider;
    }
}
