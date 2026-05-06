<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Enerji analizörü ölçüm kaydı.
 *
 * @property int    $id
 * @property string $ekipman_id
 * @property string $created_at
 * @property float  $v_l1n
 * @property float  $v_l2n
 * @property float  $v_l3n
 * @property float  $v_l1l2
 * @property float  $v_l2l3
 * @property float  $v_l3l1
 * @property float  $p_l1
 * @property float  $p_l2
 * @property float  $p_l3
 * @property float  $p_total_kw
 * @property float  $s_total_kva
 * @property float  $q_total_kvar
 * @property float  $i_avg_a
 * @property float  $i_n
 * @property float  $freq
 * @property float  $pf_l1
 * @property float  $pf_l2
 * @property float  $pf_l3
 * @property float  $pf_avg
 * @property float  $e_import_total_kwh
 * @property float  $e_export_total_kwh
 * @property string $tip  anlik|saatlik
 */
class AnalizorOlcum extends ActiveRecord
{
    public static function tableName()
    {
        return 'analizor_olcum';
    }

    public function rules()
    {
        return [
            [['ekipman_id', 'created_at'], 'required'],
            ['ekipman_id', 'string', 'max' => 30],
            ['tip', 'in', 'range' => ['anlik', 'saatlik']],
            [['v_l1n','v_l2n','v_l3n','v_l1l2','v_l2l3','v_l3l1',
              'p_l1','p_l2','p_l3','p_total_kw','s_total_kva','q_total_kvar',
              'i_avg_a','i_n','freq',
              'pf_l1','pf_l2','pf_l3','pf_avg',
              'e_import_total_kwh','e_export_total_kwh'], 'number'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'ekipman_id' => 'Ekipman',
            'created_at' => 'Ölçüm Zamanı',
            'v_l1n' => 'V L1-N', 'v_l2n' => 'V L2-N', 'v_l3n' => 'V L3-N',
            'v_l1l2' => 'V L1-L2', 'v_l2l3' => 'V L2-L3', 'v_l3l1' => 'V L3-L1',
            'p_l1' => 'P L1 (W)', 'p_l2' => 'P L2 (W)', 'p_l3' => 'P L3 (W)',
            'p_total_kw' => 'P Toplam (kW)',
            's_total_kva' => 'S Toplam (kVA)',
            'q_total_kvar' => 'Q Toplam (kVAR)',
            'i_avg_a' => 'I Ort. (A)', 'i_n' => 'I Nötr (mA)',
            'freq' => 'Frekans (Hz)',
            'pf_l1' => 'PF L1', 'pf_l2' => 'PF L2', 'pf_l3' => 'PF L3',
            'pf_avg' => 'PF Ort.',
            'e_import_total_kwh' => 'Toplam Tüketim (kWh)',
            'e_export_total_kwh' => 'Toplam İhraç (kWh)',
        ];
    }

    /**
     * Modbus parse sonucunu DB'ye kaydet.
     */
    public static function kaydet(string $ekipmanId, array $data): bool
    {
        $m = new self();
        $m->ekipman_id = $ekipmanId;
        $m->tip = 'anlik';
        $m->created_at = $data['timestamp'] ?? date('Y-m-d H:i:s');
        $m->v_l1n  = $data['V_L1N'];
        $m->v_l2n  = $data['V_L2N'];
        $m->v_l3n  = $data['V_L3N'];
        $m->v_l1l2 = $data['V_L1L2'];
        $m->v_l2l3 = $data['V_L2L3'];
        $m->v_l3l1 = $data['V_L3L1'];
        $m->p_l1   = $data['P_L1'];
        $m->p_l2   = $data['P_L2'];
        $m->p_l3   = $data['P_L3'];
        $m->p_total_kw  = $data['P_total_kW'] ?? null;
        $m->s_total_kva = $data['S_total_kVA'] ?? null;
        $m->q_total_kvar = $data['Q_total_kVAR'] ?? null;
        $m->i_avg_a = $data['I_avg_A'] ?? null;
        $m->i_n     = $data['I_N'];
        $m->freq    = $data['Freq'];
        $m->pf_l1   = $data['PF_L1'];
        $m->pf_l2   = $data['PF_L2'];
        $m->pf_l3   = $data['PF_L3'];
        $m->pf_avg  = $data['PF_avg'];
        $m->e_import_total_kwh = $data['E_import_total_kWh'] ?? null;
        $m->e_export_total_kwh = $data['E_export_total_kWh'] ?? null;

        return $m->save(false);
    }
}
