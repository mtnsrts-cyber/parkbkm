<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use app\models\PlanliBakim;
use app\models\PeriyodikKontrol;

class Ekipman extends ActiveRecord
{
    public const DURUM_AKTIF = 'AKTIF';
    public const DURUM_HURDA = 'HURDA';
    public const DURUM_KULLANIM_DISI = 'KULLANIM_DISI';

    public $DURUM;
    public $ENLEM;
    public $BOYLAM;
    public $TANITIM_FOTO;
    public $besleme_kaynagi_id;
    public $salter_kodu;
    public $salter_akim;
    public $besleme_grubu_tipi;
    public $besleme_girisleri = [];
    public $trafo_donusum_yonu;
    public $trafo_gerilim_degeri;

    public const BESLEME_GRUBU_TEK = 'tek';
    public const BESLEME_GRUBU_TRANSFER = 'transfer';
    public const BESLEME_GRUBU_SENKRON = 'senkron';
    public const BESLEME_GRUBU_CIFT_GIRIS = 'cift_giris';
    public const GERILIM_YG = 'yg';
    public const GERILIM_AG = 'ag';
    public const TRAFO_YG_AG = 'yg_ag';
    public const TRAFO_AG_YG = 'ag_yg';
    public const TRAFO_YG_YG = 'yg_yg';

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
            [['ENLEM', 'BOYLAM', 'TANITIM_FOTO', 'besleme_kaynagi_id', 'salter_kodu', 'salter_akim', 'besleme_grubu_tipi', 'besleme_girisleri', 'trafo_donusum_yonu', 'trafo_gerilim_degeri'], 'safe'],  
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
            $this->DURUM = self::DURUM_AKTIF;
        } else {
            $this->DURUM = self::normalizeDurum((string)$this->DURUM);
        }

        return true;
    }

    public function afterFind()
    {
        parent::afterFind();

        $ek = $this->ekipmanEk;
        if ($ek) {
            $this->DURUM = !empty($ek->DURUM) ? self::normalizeDurum((string)$ek->DURUM) : self::DURUM_AKTIF;
            $this->ENLEM = $ek->ENLEM;
            $this->BOYLAM = $ek->BOYLAM;
            $this->TANITIM_FOTO = $ek->TANITIM_FOTO;
            $this->besleme_kaynagi_id = $ek->besleme_kaynagi_id;
            $this->salter_kodu = $ek->salter_kodu;
            $this->salter_akim = $ek->salter_akim;
            $this->besleme_grubu_tipi = $ek->hasAttribute('besleme_grubu_tipi') ? $ek->besleme_grubu_tipi : self::BESLEME_GRUBU_TEK;
            $this->besleme_girisleri = $ek->hasAttribute('besleme_girisleri_json') ? $this->decodeBeslemeGirisleri($ek->besleme_girisleri_json) : [];
            $this->trafo_donusum_yonu = $ek->hasAttribute('trafo_donusum_yonu') ? $this->normalizeTrafoDonusumYonu($ek->trafo_donusum_yonu) : self::TRAFO_YG_AG;
            $this->trafo_gerilim_degeri = $ek->hasAttribute('trafo_gerilim_degeri') ? $ek->trafo_gerilim_degeri : null;
            if (empty($this->besleme_girisleri)) {
                $this->besleme_girisleri = $this->legacyBeslemeGirisleri();
            }
            return;
        }

        if (empty($this->DURUM)) {
            $this->DURUM = self::DURUM_AKTIF;
        }

        if (empty($this->besleme_grubu_tipi)) {
            $this->besleme_grubu_tipi = self::BESLEME_GRUBU_TEK;
        }
        if (empty($this->trafo_donusum_yonu)) {
            $this->trafo_donusum_yonu = self::TRAFO_YG_AG;
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

        $ek->DURUM = !empty($this->DURUM) ? self::normalizeDurum((string)$this->DURUM) : self::DURUM_AKTIF;
        $ek->ENLEM = ($this->ENLEM === '' || $this->ENLEM === null) ? null : $this->ENLEM;
        $ek->BOYLAM = ($this->BOYLAM === '' || $this->BOYLAM === null) ? null : $this->BOYLAM;
        $ek->TANITIM_FOTO = ($this->TANITIM_FOTO === '' || $this->TANITIM_FOTO === null) ? null : (string)$this->TANITIM_FOTO;
        $girisler = $this->normalizeBeslemeGirisleri($this->besleme_girisleri);
        $hasPostedGirisler = is_array($this->besleme_girisleri) && count($this->besleme_girisleri) > 0;
        if (empty($girisler) && !$hasPostedGirisler && ($this->besleme_kaynagi_id !== '' && $this->besleme_kaynagi_id !== null)) {
            $girisler = $this->legacyBeslemeGirisleri();
        }

        $ilkGiris = $girisler[0] ?? null;
        $ek->besleme_kaynagi_id = $ilkGiris === null ? null : $ilkGiris['kaynak_id'];
        $ek->salter_kodu = $ilkGiris === null ? null : $ilkGiris['salter_kodu'];
        $ek->salter_akim = $ilkGiris === null ? null : $ilkGiris['salter_akim'];
        if ($ek->hasAttribute('besleme_grubu_tipi')) {
            $ek->besleme_grubu_tipi = $this->normalizeBeslemeGrubuTipi($this->besleme_grubu_tipi);
        }
        if ($ek->hasAttribute('besleme_girisleri_json')) {
            $ek->besleme_girisleri_json = empty($girisler) ? null : json_encode($girisler, JSON_UNESCAPED_UNICODE);
        }
        if ($ek->hasAttribute('trafo_donusum_yonu')) {
            $ek->trafo_donusum_yonu = $this->isTrafo() ? $this->normalizeTrafoDonusumYonu($this->trafo_donusum_yonu) : null;
        }
        if ($ek->hasAttribute('trafo_gerilim_degeri')) {
            $ek->trafo_gerilim_degeri = $this->isTrafo() ? $this->blankToNull($this->trafo_gerilim_degeri) : null;
        }
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
            'DURUM' => 'Durum',
            'besleme_kaynagi_id' => 'Enerji Kaynağı',
            'besleme_grubu_tipi' => 'Besleme Grubu',
            'besleme_girisleri' => 'Besleme Girişleri',
            'trafo_donusum_yonu' => 'Trafo Dönüşüm Yönü',
            'trafo_gerilim_degeri' => 'Trafo Gerilim Değeri',
            'salter_kodu' => 'Şalter Kodu',
            'salter_akim' => 'Şalter Akım',
        ];
    }

    public static function beslemeGrubuTipleri()
    {
        return [
            self::BESLEME_GRUBU_TEK => 'Tek Besleme',
            self::BESLEME_GRUBU_TRANSFER => 'Transfer / Şebeke-Jeneratör',
            self::BESLEME_GRUBU_SENKRON => 'Senkron / Paralel Kaynak',
            self::BESLEME_GRUBU_CIFT_GIRIS => 'Çift Giriş / Çift Hat',
        ];
    }

    public static function durumSecenekleri(): array
    {
        return [
            self::DURUM_AKTIF => 'AKTİF',
            self::DURUM_KULLANIM_DISI => 'KULLANIM DIŞI',
            self::DURUM_HURDA => 'HURDA',
        ];
    }

    public static function normalizeDurum(string $durum): string
    {
        $durum = trim(mb_strtoupper($durum, 'UTF-8'));
        $durum = strtr($durum, [
            'İ' => 'I', 'İ' => 'I', 'Ş' => 'S', 'Ğ' => 'G', 'Ü' => 'U', 'Ö' => 'O', 'Ç' => 'C',
        ]);
        $durum = preg_replace('/[^A-Z0-9]+/u', '_', $durum);
        $durum = trim((string)$durum, '_');

        if (in_array($durum, ['KULLANIM_DISI', 'KULLANIMDISI', 'ASKIDA', 'ASKIYA_ALINDI'], true)) {
            return self::DURUM_KULLANIM_DISI;
        }
        if ($durum === self::DURUM_HURDA) {
            return self::DURUM_HURDA;
        }

        return self::DURUM_AKTIF;
    }

    public function getDurumEtiketi(): string
    {
        return self::durumSecenekleri()[$this->DURUM] ?? self::durumSecenekleri()[self::DURUM_AKTIF];
    }

    public function isAktif(): bool
    {
        return self::normalizeDurum((string)$this->DURUM) === self::DURUM_AKTIF;
    }

    public static function gerilimSeviyeleri()
    {
        return [
            self::GERILIM_YG => 'YG - Yüksek Gerilim',
            self::GERILIM_AG => 'AG - Alçak Gerilim',
        ];
    }

    public static function trafoDonusumYonleri()
    {
        return [
            self::TRAFO_YG_AG => 'YG → AG (Düşürücü Trafo)',
            self::TRAFO_AG_YG => 'AG → YG (Yükseltici Trafo)',
            self::TRAFO_YG_YG => 'YG → YG (YG Kademe Trafosu)',
        ];
    }

    public function isTrafo()
    {
        return stripos((string)$this->EKIPMAN_CINSI, 'TRAFO') !== false;
    }

    public function getBeslemeGrubuTipiEtiketi()
    {
        $tipler = self::beslemeGrubuTipleri();
        $tip = $this->normalizeBeslemeGrubuTipi($this->besleme_grubu_tipi);
        return $tipler[$tip] ?? $tipler[self::BESLEME_GRUBU_TEK];
    }

    public function getBeslemeGirisleri()
    {
        $girisler = $this->normalizeBeslemeGirisleri($this->besleme_girisleri);
        if (!empty($girisler)) {
            return $girisler;
        }

        return $this->legacyBeslemeGirisleri();
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
        $chain = [['id' => $this->id, 'tanim' => $this->MALZEMENIN_TANIMI, 'cinsi' => $this->EKIPMAN_CINSI, 'trafo_donusum_yonu' => $this->trafo_donusum_yonu, 'trafo_gerilim_degeri' => $this->trafo_gerilim_degeri, 'salter_kodu' => null, 'salter_akim' => null]];
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
            array_unshift($chain, ['id' => $parent->id, 'tanim' => $parent->MALZEMENIN_TANIMI, 'cinsi' => $parent->EKIPMAN_CINSI, 'trafo_donusum_yonu' => $parent->trafo_donusum_yonu, 'trafo_gerilim_degeri' => $parent->trafo_gerilim_degeri, 'salter_kodu' => null, 'salter_akim' => null]);
            $current = $parent;
        }

        return $chain;
    }

    public function getBeslemeKaynagiZincirleri($maxDepth = 3)
    {
        $girisler = $this->getBeslemeGirisleri();
        if (empty($girisler)) {
            return [];
        }

        $zincirler = [];
        $expandedKaynaklar = [];
        $kaynakKullanimSayilari = [];
        foreach ($girisler as $giris) {
            $kaynakId = (string)($giris['kaynak_id'] ?? '');
            if ($kaynakId !== '') {
                $kaynakKullanimSayilari[$kaynakId] = ($kaynakKullanimSayilari[$kaynakId] ?? 0) + 1;
            }
        }
        $currentTip = $this->normalizeBeslemeGrubuTipi($this->besleme_grubu_tipi);

        foreach ($girisler as $giris) {
            $kaynakId = (string)($giris['kaynak_id'] ?? '');
            $kaynakGirisNo = (int)($giris['kaynak_giris_no'] ?? 0);
            $kaynak = $kaynakId !== '' ? self::findOne($kaynakId) : null;
            $kaynakTipi = $kaynak === null ? null : $kaynak->normalizeBeslemeGrubuTipi($kaynak->besleme_grubu_tipi);
            $expandKaynak = $kaynakGirisNo <= 0 && in_array($kaynakTipi, [self::BESLEME_GRUBU_SENKRON, self::BESLEME_GRUBU_TRANSFER], true);
            $expandKey = $expandKaynak ? $kaynakId : null;
            $sameSourceDoubleInput = $currentTip === self::BESLEME_GRUBU_CIFT_GIRIS && ($kaynakKullanimSayilari[$kaynakId] ?? 0) > 1;
            $forceDirectKaynak = $sameSourceDoubleInput || ($expandKey !== null && isset($expandedKaynaklar[$expandKey]));

            foreach ($this->buildBeslemeZincirleriFromGiris($giris, $maxDepth, [(string)$this->id => true], $forceDirectKaynak) as $chain) {
                if (empty($chain)) {
                    continue;
                }
                $zincirler[] = ['giris' => $giris, 'chain' => $chain];
            }
            if ($expandKey !== null) {
                $expandedKaynaklar[$expandKey] = true;
            }
        }

        return $zincirler;
    }

    private function buildBeslemeZincirleriFromGiris(array $giris, $maxDepth, array $visited, $forceDirectKaynak = false)
    {
        if (empty($giris['kaynak_id'])) {
            return [];
        }

        $kaynak = self::findOne((string)$giris['kaynak_id']);
        if ($kaynak === null) {
            return [];
        }

        $kaynakGirisNo = (int)($giris['kaynak_giris_no'] ?? 0);
        $kaynakGirisler = $kaynak->getBeslemeGirisleri();
        $kaynakTipi = $kaynak->normalizeBeslemeGrubuTipi($kaynak->besleme_grubu_tipi);

        $shouldExpandKaynakGirisleri = in_array($kaynakTipi, [self::BESLEME_GRUBU_SENKRON, self::BESLEME_GRUBU_TRANSFER], true);
        if (!$forceDirectKaynak && $kaynakGirisNo <= 0 && count($kaynakGirisler) > 0 && $maxDepth > 0) {
            $girislerToFollow = $shouldExpandKaynakGirisleri ? $kaynakGirisler : [reset($kaynakGirisler)];
            $chains = [];
            foreach ($girislerToFollow as $altGiris) {
                foreach ($kaynak->buildBeslemeZincirleriFromGiris($altGiris, $maxDepth - 1, $visited) as $chain) {
                    $chain[] = $this->buildSelfChainNode($giris);
                    $chains[] = $chain;
                }
            }
            if (!empty($chains)) {
                return $chains;
            }
        }

        $chain = $this->buildBeslemeZinciriFromGiris($giris, $maxDepth, $visited);
        return empty($chain) ? [] : [$chain];
    }

    private function buildBeslemeZinciriFromGiris(array $giris, $maxDepth, array $visited)
    {
        if (empty($giris['kaynak_id'])) {
            return [];
        }

        $kaynakId = (string)$giris['kaynak_id'];
        if (isset($visited[$kaynakId])) {
            return [];
        }

        $kaynak = self::findOne($kaynakId);
        if ($kaynak === null) {
            return [];
        }

        $visited[$kaynakId] = true;
        $kaynakGirisNo = (int)($giris['kaynak_giris_no'] ?? 0);
        $kaynakGirisler = $kaynak->getBeslemeGirisleri();

        if ($kaynakGirisNo > 0 && isset($kaynakGirisler[$kaynakGirisNo - 1]) && $maxDepth > 0) {
            $chain = $kaynak->buildBeslemeZinciriFromGiris($kaynakGirisler[$kaynakGirisNo - 1], $maxDepth - 1, $visited);
            if (empty($chain)) {
                $chain = [[
                    'id' => $kaynak->id,
                    'tanim' => $kaynak->MALZEMENIN_TANIMI,
                    'cinsi' => $kaynak->EKIPMAN_CINSI,
                    'trafo_donusum_yonu' => $kaynak->trafo_donusum_yonu,
                    'trafo_gerilim_degeri' => $kaynak->trafo_gerilim_degeri,
                    'salter_kodu' => null,
                    'salter_akim' => null,
                ]];
            }
        } else {
            $chain = $kaynak->getEnerjiKaynagiZinciri(max(0, $maxDepth - 1));
        }

        $chain[] = $this->buildSelfChainNode($giris);

        return $chain;
    }

    private function buildSelfChainNode(array $giris)
    {
        return [
            'id' => $this->id,
            'tanim' => $this->MALZEMENIN_TANIMI,
            'cinsi' => $this->EKIPMAN_CINSI,
            'trafo_donusum_yonu' => $this->trafo_donusum_yonu,
            'trafo_gerilim_degeri' => $this->trafo_gerilim_degeri,
            'salter_kodu' => $giris['salter_kodu'],
            'salter_akim' => $giris['salter_akim'],
            'rol' => $giris['rol'],
            'hedef_salter_kodu' => $giris['hedef_salter_kodu'],
            'kaynak_giris_no' => $giris['kaynak_giris_no'],
            'gerilim_seviyesi' => $giris['gerilim_seviyesi'],
            'not' => $giris['not'],
        ];
    }

    private function normalizeBeslemeGrubuTipi($tip)
    {
        $tip = (string)$tip;
        return array_key_exists($tip, self::beslemeGrubuTipleri()) ? $tip : self::BESLEME_GRUBU_TEK;
    }

    private function decodeBeslemeGirisleri($json)
    {
        if ($json === null || trim((string)$json) === '') {
            return [];
        }

        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $this->normalizeBeslemeGirisleri($decoded) : [];
    }

    private function normalizeBeslemeGirisleri($girisler)
    {
        if (!is_array($girisler)) {
            return [];
        }

        $normalized = [];
        foreach ($girisler as $giris) {
            if (!is_array($giris)) {
                continue;
            }

            $kaynakId = trim((string)($giris['kaynak_id'] ?? ''));
            if ($kaynakId === '' || $kaynakId === '-') {
                continue;
            }

            $normalized[] = [
                'kaynak_id' => $kaynakId,
                'salter_kodu' => $this->blankToNull($giris['salter_kodu'] ?? null),
                'salter_akim' => $this->blankToNull($giris['salter_akim'] ?? null),
                'hedef_salter_kodu' => $this->blankToNull($giris['hedef_salter_kodu'] ?? null),
                'kaynak_giris_no' => $this->normalizePositiveInt($giris['kaynak_giris_no'] ?? null),
                'gerilim_seviyesi' => $this->normalizeGerilimSeviyesi($giris['gerilim_seviyesi'] ?? null),
                'rol' => $this->blankToNull($giris['rol'] ?? null),
                'not' => $this->blankToNull($giris['not'] ?? null),
            ];
        }

        return array_values($normalized);
    }

    private function legacyBeslemeGirisleri()
    {
        if ($this->besleme_kaynagi_id === '' || $this->besleme_kaynagi_id === null) {
            return [];
        }

        return [[
            'kaynak_id' => (string)$this->besleme_kaynagi_id,
            'salter_kodu' => $this->blankToNull($this->salter_kodu),
            'salter_akim' => $this->blankToNull($this->salter_akim),
            'hedef_salter_kodu' => null,
            'kaynak_giris_no' => null,
            'gerilim_seviyesi' => self::GERILIM_AG,
            'rol' => null,
            'not' => null,
        ]];
    }

    private function blankToNull($value)
    {
        $value = trim((string)$value);
        return $value === '' || $value === '-' ? null : $value;
    }

    private function normalizePositiveInt($value)
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function normalizeGerilimSeviyesi($value)
    {
        $value = strtolower(trim((string)$value));
        return array_key_exists($value, self::gerilimSeviyeleri()) ? $value : self::GERILIM_AG;
    }

    private function normalizeTrafoDonusumYonu($value)
    {
        $value = strtolower(trim((string)$value));
        return array_key_exists($value, self::trafoDonusumYonleri()) ? $value : self::TRAFO_YG_AG;
    }
}
