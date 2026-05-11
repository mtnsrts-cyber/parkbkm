<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Enerji analizörü cihaz tanımı.
 *
 * @property int     $id
 * @property string  $ekipman_kodu
 * @property string  $ip
 * @property int     $port
 * @property int     $device_id
 * @property string  $model
 * @property ?string $aciklama
 * @property bool    $aktif
 */
class AnalizorCihaz extends ActiveRecord
{
    public static function tableName()
    {
        return 'analizor_cihazlar';
    }

    public function rules()
    {
        return [
            [['ekipman_kodu', 'ip', 'model'], 'required'],
            ['ekipman_kodu', 'string', 'max' => 30],
            ['ekipman_kodu', 'unique'],
            ['ip', 'string', 'max' => 15],
            ['port', 'integer', 'min' => 1, 'max' => 65535],
            ['device_id', 'integer', 'min' => 1, 'max' => 255],
            ['model', 'string', 'max' => 50],
            ['aciklama', 'string', 'max' => 255],
            ['aktif', 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'ekipman_kodu' => 'Ekipman Kodu',
            'ip'           => 'IP Adresi',
            'port'         => 'Port',
            'device_id'    => 'Modbus Device ID',
            'model'        => 'Model',
            'aciklama'     => 'Açıklama',
            'aktif'        => 'Aktif',
        ];
    }

    /**
     * Aktif analizörleri getir.
     */
    public static function getAktifListesi(): array
    {
        $rows = self::find()->where(['aktif' => true])->asArray()->all();
        $result = [];
        foreach ($rows as $r) {
            $result[$r['ekipman_kodu']] = [
                'ip'        => $r['ip'],
                'port'      => (int)$r['port'],
                'device_id' => (int)$r['device_id'],
                'model'     => $r['model'],
                'aciklama'  => $r['aciklama'],
            ];
        }
        return $result;
    }

    /**
     * Tek bir ekipman için config döndürür.
     */
    public static function getConfig(string $ekipmanKodu): ?array
    {
        $r = self::findOne(['ekipman_kodu' => $ekipmanKodu, 'aktif' => true]);
        if (!$r) {
            return null;
        }
        return [
            'ip'        => $r->ip,
            'port'      => (int)$r->port,
            'device_id' => (int)$r->device_id,
            'model'     => $r->model,
            'aciklama'  => $r->aciklama,
        ];
    }
}
