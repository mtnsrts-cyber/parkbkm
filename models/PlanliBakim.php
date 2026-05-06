<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\db\Expression;

class PlanliBakim extends ActiveRecord
{
    public const DURUM_OTELEME = 'ötelendi';

    /**
     * Öteleme işlemi için kaynak planlı bakım kaydı
     * @var int|null
     */
    public $kaynak_planli_id;

    /**
     * Create ekranında bakım öteleme seçeneği
     * @var bool
     */
    public $bakim_ertele = false;

    /**
     * Öteleme yapılacak yeni tarih
     * @var string|null
     */
    public $ertelenen_tarih;

    public static function tableName()
    {
        // Veritabanındaki tablo adı
        return 'planlibakim';
    }

    // Temel validasyon kuralları (yönetim ekranı için)
    public function rules()
    {
        return [
            [['kodu', 'tanimi', 'periyodu', 'tarihi', 'durumu'], 'safe'],
            [['kaynak_planli_id'], 'integer'],
            [['bakim_ertele'], 'boolean'],
            [['ertelenen_tarih'], 'date', 'format' => 'php:Y-m-d'],
            [['ertelenen_tarih'], 'required',
                'when' => function ($model) {
                    return (bool)$model->bakim_ertele;
                },
                'whenClient' => "function () { return $('#planlibakim-bakim_ertele').is(':checked'); }",
                'message' => 'Bakım erteleniyorsa yeni tarih zorunludur.',
            ],
        ];
    }

    /**
     * Kaydedilmeden önce seçilen tarihe göre "durumu" alanını otomatik hesapla.
     *
     * Kural:
     * - Bir önceki bakım tarihi (aynı ekipman + tanım + periyot) bulunur.
     * - Bu tarihten periyoda göre beklenen planlanan tarih hesaplanır.
     * - Periyot süresinin %10'u kadar bir erken başlama eşiği alınır.
     *   - Gerçek tarih, planlanan tarihten periyot*0.10'dan daha erken ise: "plan öncesi"
     *   - Bu eşikten planlanan tarihe kadar ise: "plan dahilinde"
     *   - Planlanan tarihten sonra ise: "Plan sonrası"
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // Gerçek bakım kaydedildiğinde aynı gruptaki öteleme kayıtlarını temizle.
        // Öteleme kaydının kendisi tetiklememeli.
        if ((string)$this->durumu !== self::DURUM_OTELEME
            && !empty($this->kodu) && !empty($this->tanimi) && !empty($this->periyodu)
        ) {
            self::deleteAll([
                'and',
                ['kodu'    => $this->kodu],
                ['tanimi'  => $this->tanimi],
                ['periyodu' => $this->periyodu],
                ['durumu'  => self::DURUM_OTELEME],
                // Sadece bu bakım kaydından önce oluşturulmuş öteleme kayıtlarını sil
                ['<', 'id', $this->id],
            ]);
        }
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Görünmeyen boşluk farklılıkları aynı ekipman/tanım eşleştirmesini bozmasın.
        $this->kodu = trim((string)$this->kodu);
        $this->tanimi = self::normalizeSpaces(trim((string)$this->tanimi));
        $this->periyodu = trim((string)$this->periyodu);

        // Öteleme kaydı, bakım yapılmış kaydı değildir.
        // Durum alanı özellikle "ötelendi" olarak bırakılır ve otomatik durum hesaplaması uygulanmaz.
        if ((string)$this->durumu === self::DURUM_OTELEME) {
            return true;
        }

        if (!empty($this->tarihi) && !empty($this->periyodu) && !empty($this->kodu) && !empty($this->tanimi)) {
            $basePerformedQuery = self::find()
                ->where([
                    'kodu'     => $this->kodu,
                    'periyodu' => $this->periyodu,
                ])
                ->andWhere(['<>', 'durumu', self::DURUM_OTELEME]);

            if (!$this->isNewRecord && $this->id) {
                $basePerformedQuery->andWhere(['<>', 'id', $this->id]);
            }

            $exactPerformedQuery = (clone $basePerformedQuery)
                ->andWhere('REPLACE(TRIM(tanimi), "  ", " ") = :tanimi', [':tanimi' => $this->tanimi]);

            $useBroadFallback = false;
            if (!$exactPerformedQuery->exists()) {
                $distinctTanimiCount = (int)(clone $basePerformedQuery)
                    ->select(new Expression('COUNT(DISTINCT REPLACE(TRIM(tanimi), "  ", " "))'))
                    ->scalar();

                // Alt bileşenler (motor/pano vb.) varsa tanım karışmamalı.
                // Sadece tek bir tanım varyantı varsa geniş fallback güvenlidir.
                $useBroadFallback = $distinctTanimiCount <= 1;
            }

            // Aktif öteleme kontrolü: bu grup için en son öteleme kaydını bul
            $activePostponementQuery = self::find()
                ->where([
                    'kodu'     => $this->kodu,
                    'periyodu' => $this->periyodu,
                    'durumu'   => self::DURUM_OTELEME,
                ])
                ->orderBy(['id' => SORT_DESC]);

            if (!$useBroadFallback) {
                $activePostponementQuery->andWhere('TRIM(tanimi) = :tanimi', [':tanimi' => $this->tanimi]);
            }

            $activePostponement = $activePostponementQuery->one();

            if ($activePostponement !== null) {
                // Bu gruptan en son gerçek bakım kaydını bul (kendisi hariç)
                $lastPerformedQuery = $useBroadFallback ? (clone $basePerformedQuery) : (clone $exactPerformedQuery);
                $lastPerformed = $lastPerformedQuery->orderBy(['id' => SORT_DESC])->one();

                // Öteleme kaydı, son gerçek bakımdan sonra oluşturulmuşsa aktiftir
                if ($lastPerformed === null || $activePostponement->id > $lastPerformed->id) {
                    try {
                        $gercek  = new \DateTime($this->tarihi);
                        $oteleme = new \DateTime($activePostponement->tarihi);
                    } catch (\Exception $e) {
                        $gercek = $oteleme = null;
                    }
                    if ($gercek !== null && $oteleme !== null) {
                        $this->durumu = $gercek <= $oteleme
                            ? 'Ötelenmiş - Plan dahilinde'
                            : 'Ötelenmiş - Plan sonrası';
                        return true;
                    }
                }
            }

            $query = $useBroadFallback ? (clone $basePerformedQuery) : (clone $exactPerformedQuery);

            $previous = $query
                ->andWhere(['<=', 'tarihi', $this->tarihi])
                ->orderBy(['tarihi' => SORT_DESC])
                ->one();

            if ($previous && !empty($previous->tarihi)) {
                $planlananStr = self::calculateNextDate($previous->tarihi, $this->periyodu);

                if ($planlananStr !== null) {
                    try {
                        $planlanan = new \DateTime($planlananStr);
                        $gercek = new \DateTime($this->tarihi);
                        $onceki = new \DateTime($previous->tarihi);
                    } catch (\Exception $e) {
                        return true; // Tarih hatası durumunda var olan ak tolu bozma
                    }

                    $periodDays = max(1, $onceki->diff($planlanan)->days);
                    $offsetDays = (int)ceil($periodDays * 0.10);

                    $esik = clone $planlanan;
                    $esik->modify('-' . $offsetDays . ' days');

                    if ($gercek < $esik) {
                        $this->durumu = 'plan öncesi';
                    } elseif ($gercek <= $planlanan) {
                        $this->durumu = 'plan dahilinde';
                    } else {
                        $this->durumu = 'Plan sonrası';
                    }

                    return true;
                }
            }

            // Önceki kayıt bulunamazsa bu kombinasyonun ilk bakımıdır
            $this->durumu = 'İlk Bakım';
        }

        return true;
    }

    /**
     * Belirli bir ekipman için, her periyot türüne göre
     * en son yapılan bakım tarihini ve buna göre hesaplanan
     * bir sonraki bakım tarihini döner.
     *
     * @param int|string $ekipmanId
     * @return array<int, array{periyodu:string, son_tarih:string, sonraki_tarih:string}>
     */
    public static function getNextDueDatesByPeriodForEkipman($ekipmanId): array
    {
        $rows = self::find()
            ->select(['id', 'tanimi', 'periyodu', 'tarihi', 'durumu'])
            ->where(['kodu' => $ekipmanId])
            ->orderBy([
                'tanimi' => SORT_ASC,
                'periyodu' => SORT_ASC,
                'tarihi' => SORT_DESC,
                'id' => SORT_DESC,
            ])
            ->asArray()
            ->all();

        $grouped = [];

        foreach ($rows as $row) {
            if (empty($row['periyodu']) || empty($row['tarihi'])) {
                continue;
            }

            $tanimKey = self::normalizeSpaces(trim((string)($row['tanimi'] ?? '')));
            $groupKey = $tanimKey . '||' . $row['periyodu'];
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'tanimi' => (string)($row['tanimi'] ?? ''),
                    'periyodu' => $row['periyodu'],
                    'performed_tarihi' => null,
                    'performed_id' => null,
                    'postponed_tarihi' => null,
                    'postponed_id' => null,
                ];
            }

            if ((string)$row['durumu'] === self::DURUM_OTELEME) {
                if ($grouped[$groupKey]['postponed_tarihi'] === null) {
                    $grouped[$groupKey]['postponed_tarihi'] = $row['tarihi'];
                    $grouped[$groupKey]['postponed_id'] = (int)$row['id'];
                }
                continue;
            }

            if ($grouped[$groupKey]['performed_tarihi'] === null) {
                $grouped[$groupKey]['performed_tarihi'] = $row['tarihi'];
                $grouped[$groupKey]['performed_id'] = (int)$row['id'];
                if (!empty($row['tanimi'])) {
                    $grouped[$groupKey]['tanimi'] = (string)$row['tanimi'];
                }
            }
        }

        $result = [];

        foreach ($grouped as $group) {
            if (empty($group['periyodu']) || empty($group['tanimi']) || empty($group['performed_tarihi'])) {
                continue;
            }

            $nextDate = self::calculateNextDate($group['performed_tarihi'], $group['periyodu']);

            if ($nextDate === null) {
                continue;
            }

            if (!empty($group['postponed_tarihi']) && strcmp($group['postponed_tarihi'], $nextDate) >= 0
                && ($group['performed_id'] === null || (int)$group['performed_id'] < (int)$group['postponed_id'])
            ) {
                // Öteleme tarihi, mevcut planlı tarihi ileri taşır fakat bakım yapılmış sayılmaz.
                // Ötelemeden SONRA bakım yapıldıysa (performed_id > postponed_id) öteleme geçersizdir.
                $nextDate = $group['postponed_tarihi'];
            }

            $result[] = [
                'tanimi' => $group['tanimi'],
                'periyodu' => $group['periyodu'],
                'son_tarih' => $group['performed_tarihi'],
                'sonraki_tarih' => $nextDate,
            ];
        }
        // Sonraki bakıma en yakın olanlar en üstte olacak şekilde sırala
        usort($result, function (array $a, array $b): int {
            return strcmp($a['sonraki_tarih'], $b['sonraki_tarih']);
        });

        return $result;
    }

    /**
    * Tüm ekipmanlar için (kodu + periyodu bazında)
     * en son bakım ve buna göre hesaplanan sonraki bakımı döner.
     * Ana sayfada yaklaşan bakımları listelemek için kullanılır.
     *
     * @param int $limit
     * @return array<int, array{ekipman_id:mixed,planli_id:int,tanimi:string,periyodu:string,son_tarih:string,sonraki_tarih:string}>
     */
    public static function getAllUpcomingNextDueDates(int $limit = 50): array
    {
        $rows = self::find()
            ->select(['id', 'kodu', 'tanimi', 'periyodu', 'tarihi', 'durumu'])
            ->orderBy([
                'kodu' => SORT_ASC,
                'tanimi' => SORT_ASC,
                'periyodu' => SORT_ASC,
                'tarihi' => SORT_DESC,
                'id' => SORT_DESC,
            ])
            ->asArray()
            ->all();

        $grouped = [];

        foreach ($rows as $row) {
            if (empty($row['kodu']) || empty($row['periyodu']) || empty($row['tarihi'])) {
                continue;
            }

            $tanimKey = self::normalizeSpaces(trim((string)($row['tanimi'] ?? '')));
            $groupKey = $row['kodu'] . '||' . $tanimKey . '||' . $row['periyodu'];
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'kodu' => $row['kodu'],
                    'tanimi' => (string)($row['tanimi'] ?? ''),
                    'periyodu' => $row['periyodu'],
                    'performed_tarihi' => null,
                    'performed_id' => null,
                    'postponed_tarihi' => null,
                    'postponed_id' => null,
                ];
            }

            if ((string)$row['durumu'] === self::DURUM_OTELEME) {
                if ($grouped[$groupKey]['postponed_tarihi'] === null) {
                    $grouped[$groupKey]['postponed_tarihi'] = $row['tarihi'];
                    $grouped[$groupKey]['postponed_id'] = (int)$row['id'];
                }
                continue;
            }

            if ($grouped[$groupKey]['performed_tarihi'] === null) {
                $grouped[$groupKey]['performed_tarihi'] = $row['tarihi'];
                $grouped[$groupKey]['performed_id'] = (int)$row['id'];
                if (!empty($row['tanimi'])) {
                    $grouped[$groupKey]['tanimi'] = (string)$row['tanimi'];
                }
            }
        }

        $result = [];

        foreach ($grouped as $group) {
            if (empty($group['periyodu']) || empty($group['tanimi']) || empty($group['kodu']) || empty($group['performed_tarihi'])) {
                continue;
            }

            $nextDate = self::calculateNextDate($group['performed_tarihi'], $group['periyodu']);

            if ($nextDate === null) {
                continue;
            }

            $sourceId = (int)$group['performed_id'];

            if (!empty($group['postponed_tarihi']) && strcmp($group['postponed_tarihi'], $nextDate) >= 0
                && ($group['performed_id'] === null || (int)$group['performed_id'] < (int)$group['postponed_id'])
            ) {
                // Öteleme uygulandıysa plan tarihi öteleme tarihine taşınır.
                // Ancak bakım yapılmış sayılmaz; "son_tarih" son gerçek bakımı gösterir.
                // Ötelemeden SONRA bakım yapıldıysa (performed_id > postponed_id) öteleme geçersizdir.
                $nextDate = $group['postponed_tarihi'];
                $sourceId = (int)$group['postponed_id'];
            }

            $result[] = [
                'ekipman_id' => $group['kodu'],
                'planli_id' => $sourceId,
                'tanimi' => $group['tanimi'],
                'periyodu' => $group['periyodu'],
                'son_tarih' => $group['performed_tarihi'],
                'sonraki_tarih' => $nextDate,
            ];
        }

        // Sonraki bakıma göre sırala (en yakın en üstte)
        usort($result, function (array $a, array $b): int {
            return strcmp($a['sonraki_tarih'], $b['sonraki_tarih']);
        });

        if ($limit > 0) {
            $result = array_slice($result, 0, $limit);
        }

        return $result;
    }

    /**
     * Tarih + periyot bilgisinden bir sonraki bakım tarihini hesaplar.
     */
    private static function calculateNextDate(string $tarihi, string $periyodu): ?string
    {
        try {
            $date = new \DateTime($tarihi);
        } catch (\Exception $e) {
            return null;
        }

        switch ($periyodu) {
            case 'Periyodik: 1 Ay':
                $date->modify('+1 month');
                break;
            case 'Periyodik: 3 Ay':
                $date->modify('+3 months');
                break;
            case 'Periyodik: 6 Ay':
                $date->modify('+6 months');
                break;
            case 'Periyodik: 1 Yıl':
                $date->modify('+1 year');
                break;
            default:
                return null;
        }

        return $date->format('Y-m-d');
    }

    public function attributeLabels()
    {
        return [
            'kodu' => 'EKİPMAN KODU',
            'tanimi' => 'TANIMI',
            'periyodu' => 'PERİYODU',
            'tarihi' => 'TARİHİ',
            'durumu' => 'DURUMU',
            'bakim_ertele' => 'Bakımı Ötele',
            'ertelenen_tarih' => 'Yeni Planlı Bakım Tarihi',
        ];
    }

    /**
     * Çoklu boşlukları tek boşluğa indirger.
     * Ekipman tanımlarındaki düzensiz boşluk farklılıklarının
     * gruplama/eşleştirme bozmasını önler.
     */
    public static function normalizeSpaces(string $value): string
    {
        return preg_replace('/\s+/', ' ', $value);
    }
}
