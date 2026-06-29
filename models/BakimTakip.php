<?php

namespace app\models;

use Yii;
use yii\helpers\ArrayHelper;
use app\models\Ekipman;

/**
 * This is the model class for table "bakim_takip".
 *
 * @property int $id
 * @property string|null $BAKIM_GENEL
 * @property string|null $PERIYODIK_PLANLI
 * @property string|null $TARIH
 * @property float|null $BAKIM_SURESI_SAAT
 * @property string|null $YERI
 * @property string|null $SISTEM_CIHAZ_OZELLIK
 * @property string|null $YAPILAN_IS
 * @property string|null $ISI_YAPANLAR
 * @property string|null $created_by
 */
class BakimTakip extends \yii\db\ActiveRecord
{
    public const PLANLI_BAKIM_STANDART_METNI = 'Planlı bakımları talimata uygun yapıldı.';

    /**
     * Çoklu seçilen ekipman ID'leri (form için sanal alan)
     * @var array
     */
    public $ekipmanIds = [];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'bakim_takip';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['BAKIM_GENEL', 'SISTEM_CIHAZ_OZELLIK', 'YAPILAN_IS'], 'string'],
            [['TARIH'], 'safe'],
            [['BAKIM_SURESI_SAAT'], 'required', 'message' => 'Bakım süresi girilmelidir.'],
            [['BAKIM_SURESI_SAAT'], 'number', 'min' => 0],
            [['PERIYODIK_PLANLI'], 'string', 'max' => 100],
            [['YERI'], 'string', 'max' => 255],
            [['created_by'], 'string', 'max' => 255],
            [['ISI_YAPANLAR'], 'required', 'message' => 'İşi Yapanlar alanında en az bir kişi seçilmelidir.'],
            [['ekipmanIds'], 'safe'],
        ];
    }

    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            if (is_array($this->ISI_YAPANLAR)) {
                $this->ISI_YAPANLAR = implode(', ', $this->ISI_YAPANLAR);
            }
            return true;
        }
        return false;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // beforeValidate'de zaten string'e çevrildi ama yine de kontrol edelim
            if (is_array($this->ISI_YAPANLAR)) {
                $this->ISI_YAPANLAR = implode(', ', $this->ISI_YAPANLAR);
            }

            // Sistem/Cihaz Özellik boş bırakılırsa ekipman listesi yerine grup/başlık üret.
            if (trim((string)$this->SISTEM_CIHAZ_OZELLIK) === '' && is_array($this->ekipmanIds) && !empty($this->ekipmanIds)) {
                $this->SISTEM_CIHAZ_OZELLIK = $this->buildDefaultSistemCihazOzellik();
            }

            $this->syncGeneratedPlanliYapilanIs();

            // Kaydı oluşturan kullanıcıyı gizli alan olarak sakla
            if ($insert && Yii::$app->has('user', true) && !Yii::$app->user->isGuest) {
                $this->created_by = Yii::$app->user->identity->username;
            }
            return true;
        }
        return false;
    }

    public function afterFind()
    {
        parent::afterFind();
        if ($this->ISI_YAPANLAR) {
            // Formda çoklu seçim için array'e çevir
            $this->ISI_YAPANLAR = explode(', ', $this->ISI_YAPANLAR);
        }

        // İlişkili ekipman ID'lerini doldur
        $this->ekipmanIds = ArrayHelper::getColumn($this->bakimEkipmanlar, 'ekipman_id');

    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // Çoklu ekipman ilişkisini pivot tabloda senkronize et.
        // Planlı bakım bağlantıları controller tarafında ayrıca yönetilir; burada silinirse
        // bakım tarihi düzeltmelerinde bağlı planlı bakım satırı güncellenemez.
        BakimTakipEkipman::deleteAll(['bakim_id' => $this->id]);

        if (is_array($this->ekipmanIds)) {
            foreach ($this->ekipmanIds as $ekipmanId) {
                if ($ekipmanId === '' || $ekipmanId === null) {
                    continue;
                }
                $pivot = new BakimTakipEkipman();
                $pivot->bakim_id = $this->id;
                $pivot->ekipman_id = (string)$ekipmanId;
                $pivot->save(false);
            }
        }
    }

    public function afterDelete()
    {
        parent::afterDelete();

        // Veritabanında FK/cascade olmadığı için ilişkili kayıtları uygulama katmanında temizle.
        BakimTakipEkipman::deleteAll(['bakim_id' => $this->id]);
        BakimTakipPlanli::deleteAll(['bakim_id' => $this->id]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBakimEkipmanlar()
    {
        return $this->hasMany(BakimTakipEkipman::class, ['bakim_id' => 'id']);
    }

    public function getBakimPlanliBaglantilar()
    {
        return $this->hasMany(BakimTakipPlanli::class, ['bakim_id' => 'id']);
    }

    public function syncGeneratedPlanliYapilanIs(): void
    {
        if (!$this->shouldGeneratePlanliYapilanIs()) {
            return;
        }

        $yapilanIs = trim($this->normalizeMultilineText((string)$this->YAPILAN_IS));
        if ($yapilanIs === '') {
            $ekNot = '';
        } elseif ($this->looksLikeLegacyPlanliYapilanIs($yapilanIs)) {
            $ekNot = $this->extractLegacyPlanliEkNot();
        } else {
            $ekNot = $yapilanIs;
        }

        $this->YAPILAN_IS = $this->buildGeneratedPlanliYapilanIs($ekNot);
    }

    public function buildGeneratedPlanliYapilanIs(string $ekNot = ''): string
    {
        $satirlar = $this->buildSelectedEkipmanLabels();
        $parcalar = [];

        if (!empty($satirlar)) {
            $parcalar[] = implode("\n", $satirlar);
        }

        $parcalar[] = self::PLANLI_BAKIM_STANDART_METNI;

        $ekNot = trim($this->normalizeMultilineText($ekNot));
        if ($ekNot !== '') {
            $parcalar[] = $ekNot;
        }

        return implode("\n", array_values(array_filter($parcalar, static fn($parca): bool => trim((string)$parca) !== '')));
    }

    public function shouldGeneratePlanliYapilanIs(): bool
    {
        $ekipmanIds = array_values(array_filter((array)$this->ekipmanIds, static fn($id): bool => $id !== '' && $id !== null));
        if (count($ekipmanIds) <= 1) {
            return false;
        }

        if (!$this->isPlanliBakimRecord()) {
            return false;
        }

        return true;
    }

    private function buildDefaultSistemCihazOzellik(): string
    {
        $ekipmanIds = array_values(array_filter((array)$this->ekipmanIds, static fn($id): bool => $id !== '' && $id !== null));
        if (empty($ekipmanIds)) {
            return '';
        }

        $ekipmanlar = Ekipman::find()
            ->where(['id' => $ekipmanIds])
            ->indexBy('id')
            ->all();

        $turler = [];
        $cinsler = [];
        foreach ($ekipmanIds as $ekipmanId) {
            $ekipman = $ekipmanlar[$ekipmanId] ?? null;
            if ($ekipman === null) {
                continue;
            }

            $tur = trim((string)$ekipman->EKIPMAN_TURU);
            $cins = trim((string)$ekipman->EKIPMAN_CINSI);
            if ($tur !== '') {
                $turler[$tur] = true;
            }
            if ($cins !== '') {
                $cinsler[$cins] = true;
            }
        }

        if (count($turler) === 1) {
            $tur = (string)array_key_first($turler);
            return mb_convert_case($tur, MB_CASE_TITLE, 'UTF-8') . 'lar';
        }

        if (count($cinsler) === 1) {
            $cins = (string)array_key_first($cinsler);
            return mb_convert_case($cins, MB_CASE_TITLE, 'UTF-8');
        }

        return 'Seçili ekipman grubu';
    }

    public function isPlanliBakimRecord(): bool
    {
        return stripos((string)$this->PERIYODIK_PLANLI, 'PLANLI') !== false;
    }

    private function extractLegacyPlanliEkNot(): string
    {
        $yapilanIs = trim($this->normalizeMultilineText((string)$this->YAPILAN_IS));
        if ($yapilanIs === '' || !$this->looksLikeLegacyPlanliYapilanIs($yapilanIs)) {
            return '';
        }

        $prefix = self::PLANLI_BAKIM_STANDART_METNI . "\n";
        if (str_starts_with($yapilanIs, $prefix)) {
            return trim(substr($yapilanIs, strlen($prefix)));
        }

        return '';
    }

    private function looksLikeLegacyPlanliYapilanIs(string $text): bool
    {
        $text = trim($this->normalizeMultilineText($text));
        if ($text === '') {
            return false;
        }

        if ($text === self::PLANLI_BAKIM_STANDART_METNI || str_starts_with($text, self::PLANLI_BAKIM_STANDART_METNI . "\n")) {
            return true;
        }

        return stripos($this->normalizeTurkishText($text), 'talimata uygun') !== false;
    }

    private function normalizeTurkishText(string $text): string
    {
        return strtr(
            mb_strtolower($text, 'UTF-8'),
            [
                'ç' => 'c',
                'ğ' => 'g',
                'ı' => 'i',
                'ö' => 'o',
                'ş' => 's',
                'ü' => 'u',
            ]
        );
    }

    private function buildSelectedEkipmanLabels(): array
    {
        $ekipmanIds = array_values(array_filter((array)$this->ekipmanIds, static fn($id): bool => $id !== '' && $id !== null));
        if (empty($ekipmanIds)) {
            return [];
        }

        $ekipmanlar = Ekipman::find()
            ->where(['id' => $ekipmanIds])
            ->indexBy('id')
            ->all();

        $etiketler = [];
        foreach ($ekipmanIds as $ekipmanId) {
            $ekipman = $ekipmanlar[$ekipmanId] ?? null;
            if ($ekipman === null) {
                $etiketler[] = (string)$ekipmanId;
                continue;
            }

            $etiketler[] = trim((string)$ekipman->id . ' - ' . (string)$ekipman->MALZEMENIN_TANIMI);
        }

        return $etiketler;
    }

    private function normalizeMultilineText(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'S.No',
            'BAKIM_GENEL' => 'Bakım/Genel',
            'PERIYODIK_PLANLI' => 'Periyodik/Planlı',
            'TARIH' => 'Tarih',
            'BAKIM_SURESI_SAAT' => 'Bakım Süresi (Saat)',
            'YERI' => 'Yeri',
            'SISTEM_CIHAZ_OZELLIK' => 'Sistem/Cihaz Özellik',
            'YAPILAN_IS' => 'Yapılan İş',
            'ISI_YAPANLAR' => 'İşi Yapanlar',
            'created_by' => 'Kaydı Oluşturan',
        ];
    }
}
