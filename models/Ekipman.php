<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use app\models\PlanliBakim;
use app\models\PeriyodikKontrol;

class Ekipman extends ActiveRecord
{
    public $DURUM;
    public $ENLEM;
    public $BOYLAM;
    public $TANITIM_FOTO;
    public $besleme_kaynagi_id;
    public $salter_kodu;
    public $salter_akim;

    public static function tableName()
    {
        return 'ekipman';
    }

    public function rules()
    {
        return [
            [['id'], 'required', 'message' => 'Ekipman kodu (ID) boş olamaz.'],
            [['id'], 'unique', 'message' => 'Bu ekipman kodu (ID) zaten kullanılıyor.'],
            [['id', 'EKIPMAN_YERI', 'EKIPMAN_CINSI', 'EKIPMAN_TURU', 'MALZEMENIN_TANIMI', 'MARKA', 'SERI_NO', 'TIP', 'VARSA_DIGER_TANITICI_BILGI', 'NOTLAR', 'DURUM'], 'string'],
            [['MIKTAR'], 'safe'],
            [['IMAL_YILI'], 'integer', 'min' => 1900, 'max' => (int)date('Y') + 5, 'skipOnEmpty' => true],
            [['ENLEM', 'BOYLAM'], 'number'],
            [['ENLEM', 'BOYLAM', 'TANITIM_FOTO', 'besleme_kaynagi_id', 'salter_kodu', 'salter_akim'], 'safe'],  
        ];
    }

    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $imalYili = trim((string)$this->IMAL_YILI);
        if ($imalYili === '' || $imalYili === '0' || $imalYili === '0000') {
            $this->IMAL_YILI = null;
        }

        if (empty($this->DURUM)) {
            $this->DURUM = 'AKTIF';
        } else {
            $this->DURUM = strtoupper((string)$this->DURUM);
        }

        return true;
    }

    public function afterFind()
    {
        parent::afterFind();

        $ek = $this->ekipmanEk;
        if ($ek) {
            $this->DURUM = !empty($ek->DURUM) ? strtoupper((string)$ek->DURUM) : 'AKTIF';
            $this->ENLEM = $ek->ENLEM;
            $this->BOYLAM = $ek->BOYLAM;
            $this->TANITIM_FOTO = $ek->TANITIM_FOTO;
            $this->besleme_kaynagi_id = $ek->besleme_kaynagi_id;
            $this->salter_kodu = $ek->salter_kodu;
            $this->salter_akim = $ek->salter_akim;
            return;
        }

        if (empty($this->DURUM)) {
            $this->DURUM = 'AKTIF';
        }
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $ek = EkipmanMeta::findOne(['ekipman_id' => $this->id]);
        if ($ek === null) {
            $ek = new EkipmanMeta();
            $ek->ekipman_id = (string)$this->id;
        }

        $ek->DURUM = !empty($this->DURUM) ? strtoupper((string)$this->DURUM) : 'AKTIF';
        $ek->ENLEM = ($this->ENLEM === '' || $this->ENLEM === null) ? null : $this->ENLEM;
        $ek->BOYLAM = ($this->BOYLAM === '' || $this->BOYLAM === null) ? null : $this->BOYLAM;
        $ek->TANITIM_FOTO = ($this->TANITIM_FOTO === '' || $this->TANITIM_FOTO === null) ? null : (string)$this->TANITIM_FOTO;
        $ek->besleme_kaynagi_id = ($this->besleme_kaynagi_id === '' || $this->besleme_kaynagi_id === null) ? null : (string)$this->besleme_kaynagi_id;
        $ek->salter_kodu = ($this->salter_kodu === '' || $this->salter_kodu === null) ? null : (string)$this->salter_kodu;
        $ek->salter_akim = ($this->salter_akim === '' || $this->salter_akim === null) ? null : (string)$this->salter_akim;
        $ek->updated_at = date('Y-m-d H:i:s');
        $ek->save(false);

        Yii::$app->cache->delete('site.map.items.v1');
    }

    public function attributeLabels()
    {
        return [
            'id' => 'Ekipman Kodu (ID)',
            'ENLEM' => 'Enlem',
            'BOYLAM' => 'Boylam',
            'TANITIM_FOTO' => 'Tanıtım Fotoğrafı',
            'EKIPMAN_YERI' => 'Ekipman Yeri',
            'EKIPMAN_CINSI' => 'Ekipman Cinsi',
            'EKIPMAN_TURU' => 'Ekipman Türü',
            'MALZEMENIN_TANIMI' => 'Malzemenin Tanımı',
            'MARKA' => 'Marka',
            'SERI_NO' => 'Seri No',
            'TIP' => 'Tip',
            'VARSA_DIGER_TANITICI_BILGI' => 'Diğer Bilgi',
            'MIKTAR' => 'Miktar',
            'IMAL_YILI' => 'İmal Yılı',
            'NOTLAR' => 'Notlar',
            'DURUM' => 'Durum (AKTIF / HURDA)',
            'besleme_kaynagi_id' => 'Enerji Kaynağı',
            'salter_kodu' => 'Şalter Kodu',
            'salter_akim' => 'Şalter Akım',
        ];
    }

    /**
     * Bu ekipmana ait planlı bakımları döndürür.
     * planlibakim tablosundaki "kodu" sütunu, ekipman tablosundaki "id" ile eşleştirildi.
     */
    public function getPlanliBakimlar()
    {
        // 'tarihi' alanı VARCHAR olarak 'YYYY-MM-DD' formatında tutuluyor.
        // Bu format zaten alfabetik olarak da doğru sırayı verdiği için
        // doğrudan DESC sıralama yeterli.
        return $this->hasMany(PlanliBakim::class, ['kodu' => 'id'])
            ->orderBy(['tarihi' => SORT_DESC]);
    }

    public function getEkipmanEk()
    {
        return $this->hasOne(EkipmanMeta::class, ['ekipman_id' => 'id']);
    }

    public function getPeriyodikKontroller()
    {
        return $this->hasMany(PeriyodikKontrol::class, ['ekipman_id' => 'id'])
            ->orderBy(['gelecek_kontrol_tarihi' => SORT_ASC]);
    }

    /**
     * Enerji kaynağı zincirini döndürür (max 3 seviye geriye).
     * Sonuç: [en üst kaynak, ..., doğrudan kaynak, kendisi]
     */
    public function getEnerjiKaynagiZinciri($maxDepth = 3)
    {
        $chain = [['id' => $this->id, 'tanim' => $this->MALZEMENIN_TANIMI, 'salter_kodu' => null, 'salter_akim' => null]];
        $visited = [$this->id => true];
        $current = $this;

        for ($i = 0; $i < $maxDepth; $i++) {
            $ek = $current->ekipmanEk;
            if ($ek === null || empty($ek->besleme_kaynagi_id)) {
                break;
            }
            // Şalter bilgisi: "current" ekipmandaki şalter, onu kaynağa bağlayan şalterdir
            $salterKodu = $ek->salter_kodu;
            $salterAkim = $ek->salter_akim;
            $parentId = $ek->besleme_kaynagi_id;
            if (isset($visited[$parentId])) {
                break;
            }
            $parent = self::findOne($parentId);
            if ($parent === null) {
                break;
            }
            $visited[$parentId] = true;
            // Şalter bilgisini zincirdeki önceki (kaynak) düğüme ok etiketi olarak ekle
            $chain[0]['salter_kodu'] = $salterKodu;
            $chain[0]['salter_akim'] = $salterAkim;
            array_unshift($chain, ['id' => $parent->id, 'tanim' => $parent->MALZEMENIN_TANIMI, 'salter_kodu' => null, 'salter_akim' => null]);
            $current = $parent;
        }

        return $chain;
    }
}
