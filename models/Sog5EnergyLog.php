<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class Sog5EnergyLog extends ActiveRecord
{
    public static function tableName()
    {
        return 'sog5_energy_logs';
    }

    public function rules()
    {
        return [
            [['e_l1_kwh', 'e_l2_kwh', 'e_l3_kwh', 'e_total_kwh', 'q_ind_kvarh', 'q_cap_kvarh',
              'e_l1_reactive_ind_kvarh', 'e_l2_reactive_ind_kvarh', 'e_l3_reactive_ind_kvarh',
              'e_l1_reactive_cap_kvarh', 'e_l2_reactive_cap_kvarh', 'e_l3_reactive_cap_kvarh'], 'safe'],
        ];
    }

    public static function getConsumption()
    {
        $db = Yii::$app->db;
        
        $latest = (new \yii\db\Query())
            ->select('*')
            ->from('sog5_energy_logs')
            ->orderBy('log_date DESC')
            ->limit(1)
            ->one($db);

        if (!$latest) {
            return ['hourly' => null, 'daily' => null, 'monthly' => null];
        }

        $hourLog = (new \yii\db\Query())
            ->select('*')
            ->from('sog5_energy_logs')
            ->where(['<', 'log_date', $latest['log_date']])
            ->orderBy('log_date DESC')
            ->limit(1)
            ->one($db);

        $todayLog = (new \yii\db\Query())
            ->select('*')
            ->from('sog5_energy_logs')
            ->where(['<', 'log_date', date('Y-m-d 00:00:00')])
            ->orderBy('log_date DESC')
            ->limit(1)
            ->one($db);

        $monthLog = (new \yii\db\Query())
            ->select('*')
            ->from('sog5_energy_logs')
            ->where(['<', 'log_date', date('Y-m-01 00:00:00')])
            ->orderBy('log_date DESC')
            ->limit(1)
            ->one($db);

        $calc = function($c, $p) {
            if (!$p) return null;
            return round(($c['e_total_kwh'] ?? 0) - ($p['e_total_kwh'] ?? 0), 1);
        };

        return [
            'hourly' => [
                'e_kwh' => $calc($latest, $hourLog),
            ],
            'daily' => [
                'e_kwh' => $calc($latest, $todayLog),
            ],
            'monthly' => [
                'e_kwh' => $calc($latest, $monthLog),
            ],
            'raw' => [
                'e_l1_reactive_ind' => $latest['e_l1_reactive_ind_kvarh'] ?? null,
                'e_l2_reactive_ind' => $latest['e_l2_reactive_ind_kvarh'] ?? null,
                'e_l3_reactive_ind' => $latest['e_l3_reactive_ind_kvarh'] ?? null,
                'e_l1_reactive_cap' => $latest['e_l1_reactive_cap_kvarh'] ?? null,
                'e_l2_reactive_cap' => $latest['e_l2_reactive_cap_kvarh'] ?? null,
                'e_l3_reactive_cap' => $latest['e_l3_reactive_cap_kvarh'] ?? null,
            ],
        ];
    }
}