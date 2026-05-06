<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "ariza_takip".
 *
 * @property int $id
 * @property string|null $ARIZA_BILDIRIM_TARIHI
 * @property string|null $ARIZA_TARIHI
 * @property string|null $ARIZAYI_BILDIREN
 * @property string|null $ARIZAYA_SEBEBIYET_VEREN_FIRMA
 * @property string|null $ARIZALANAN_MAKINE_ADI
 * @property string|null $ARIZALANAN_MAKINE_KODU
 * @property string|null $ARIZALANAN_PARCA
 * @property string|null $ARIZANIN_MEYDANA_GELDIGI_BOLUM
 * @property string|null $ARIZA_KOK_NEDENI
 * @property string|null $KALICI_AKSIYON
 * @property string|null $ARIZA_SEBEBI
 * @property string|null $ARIZANIN_GIDERILDIGI_TARIH
 * @property string|null $ARIZANIN_SON_DURUMU
 * @property float|null $ARIZALI_KALDIGI_SURE_SAAT
 * @property float|null $YEDEK_PARCA_BEKLEME_SURESI_SAAT
 * @property float|null $MALZEME_TUTARI
 * @property float|null $ISCILIK_FIYATI
 * @property float|null $MALIYET_TL
 * @property string|null $ARIZANIN_AYRINTILI_ACIKLAMASI
 * @property string|null $created_by
 */
class ArizaTakip extends ActiveRecord
{
    public static function tableName()
    {
        return 'ariza_takip';
    }

    public function rules()
    {
        return [
            // Zorunlu alanlar
            [
                ['ARIZA_BILDIRIM_TARIHI', 'ARIZA_TARIHI', 'ARIZALANAN_MAKINE_KODU', 'ARIZALANAN_MAKINE_ADI', 'ARIZANIN_SON_DURUMU'],
                'required',
                'message' => '{attribute} boş olamaz.',
            ],
            [['ARIZA_BILDIRIM_TARIHI', 'ARIZA_TARIHI', 'ARIZANIN_GIDERILDIGI_TARIH'], 'safe'],
            [['ARIZA_KOK_NEDENI', 'KALICI_AKSIYON', 'ARIZA_SEBEBI', 'ARIZANIN_AYRINTILI_ACIKLAMASI'], 'string'],
            [['ARIZALI_KALDIGI_SURE_SAAT', 'YEDEK_PARCA_BEKLEME_SURESI_SAAT', 'MALZEME_TUTARI', 'ISCILIK_FIYATI', 'MALIYET_TL'], 'number'],
            [['ARIZAYI_BILDIREN', 'ARIZAYA_SEBEBIYET_VEREN_FIRMA', 'ARIZALANAN_MAKINE_ADI', 'ARIZALANAN_PARCA', 'ARIZANIN_MEYDANA_GELDIGI_BOLUM', 'ARIZANIN_SON_DURUMU', 'created_by'], 'string', 'max' => 255],
            [['ARIZALANAN_MAKINE_KODU'], 'string', 'max' => 100],
            [['ARIZANIN_SON_DURUMU'], 'in', 'range' => ['FAAL', 'GAYRI_FAAL', 'ARIZALI_FAAL']],
        ];
    }

    public function loadDefaultValues($skipIfSet = true)
    {
        parent::loadDefaultValues($skipIfSet);

        if ($skipIfSet && $this->ARIZANIN_SON_DURUMU !== null && $this->ARIZANIN_SON_DURUMU !== '') {
            return $this;
        }

        $this->ARIZANIN_SON_DURUMU = 'faal';

        return $this;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert && !Yii::$app->user->isGuest) {
                $this->created_by = Yii::$app->user->identity->username;
            }
            return true;
        }
        return false;
    }

    public function attributeLabels()
    {
        return [
            'id' => 'Sıra No',
            'ARIZA_BILDIRIM_TARIHI' => 'Arıza Bildirim Tarihi',
            'ARIZA_TARIHI' => 'Arıza Tarihi',
            'ARIZAYI_BILDIREN' => 'Arızayı Bildiren',
            'ARIZAYA_SEBEBIYET_VEREN_FIRMA' => 'Arızaya Sebebiyet Veren Firma',
            'ARIZALANAN_MAKINE_ADI' => 'Arızalanan Makine Adı',
            'ARIZALANAN_MAKINE_KODU' => 'Arızalanan Makine Kodu',
            'ARIZALANAN_PARCA' => 'Arızalanan Parça',
            'ARIZANIN_MEYDANA_GELDIGI_BOLUM' => 'Arızanın Meydana Geldiği Bölüm',
            'ARIZA_KOK_NEDENI' => 'Arıza Kök Nedeni',
            'KALICI_AKSIYON' => 'Kalıcı Aksiyon',
            'ARIZA_SEBEBI' => 'Arıza Sebebi',
            'ARIZANIN_GIDERILDIGI_TARIH' => 'Arızanın Giderildiği Tarih',
            'ARIZANIN_SON_DURUMU' => 'Arızanın Son Durumu',
            'ARIZALI_KALDIGI_SURE_SAAT' => 'Arızalı Kaldığı Süre (Saat)',
            'YEDEK_PARCA_BEKLEME_SURESI_SAAT' => 'Yedek Parça Bekleme Süresi (Saat)',
            'MALZEME_TUTARI' => 'Malzeme Tutarı',
            'ISCILIK_FIYATI' => 'İşçilik Fiyatı',
            'MALIYET_TL' => 'Maliyet (TL)',
            'ARIZANIN_AYRINTILI_ACIKLAMASI' => 'Arızanın Ayrıntılı Açıklaması',
            'created_by' => 'Kaydı Oluşturan',
        ];
    }
}
