<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\models\Ekipman;
use yii\data\ArrayDataProvider;
use yii\data\ActiveDataProvider;
use app\models\LoginForm;
use app\models\PlanliBakim;
use app\models\PeriyodikKontrol;
use app\models\ArizaTakip;
use app\models\BakimTakip;
use app\models\BakimTakipPlanli;
class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout', 'toplu-bakim-isle'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['toplu-bakim-isle'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return !Yii::$app->user->isGuest
                                && in_array(Yii::$app->user->identity->role, ['admin', 'editor']);
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'toplu-bakim-isle' => ['post'],
                ],
            ],
        ];
    }
    
    
        public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionEnergy()
    {
        $analizorler = \app\models\AnalizorCihaz::getAktifListesi();
        return $this->render('energy', [
            'analizorler' => $analizorler,
        ]);
    }
    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    } 
    
public function actionMap()
{
    $items = Yii::$app->cache->getOrSet('site.map.items.v1', function () {
        return Ekipman::find()
            ->alias('e')
            ->select([
                'e.id',
                'e.MALZEMENIN_TANIMI',
                'e.EKIPMAN_YERI',
                'em.ENLEM',
                'em.BOYLAM',
            ])
            ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
            ->where(['not', ['em.ENLEM' => null]])
            ->andWhere(['not', ['em.BOYLAM' => null]])
            ->asArray()
            ->all();
    }, 120);

    return $this->render('map', [
        'items' => $items
    ]);
}


public function actionIndex()
{
    $allUpcoming = PlanliBakim::getAllUpcomingNextDueDates(200);
    $hurdaSet = $this->getHurdaEkipmanSet();

    $today = new \DateTime('today');
    $filtered = [];

    foreach ($allUpcoming as $item) {
        // Hurdaya ayrılmış ekipmanları ana sayfa listesinden çıkar
        if (isset($hurdaSet[(string)$item['ekipman_id']])) {
            continue;
        }

        try {
            $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
        } catch (\Exception $e) {
            continue;
        }

        // Gecikmiş veya önümüzdeki 10 gün içinde olanlar
        if ($sonrakiTarih <= $today) {
            $filtered[] = $item;
        } else {
            $diffDays = $today->diff($sonrakiTarih)->days;
            if ($diffDays <= 10) {
                $filtered[] = $item;
            }
        }
    }

    $dataProvider = new ArrayDataProvider([
        'allModels' => $filtered,
        'pagination' => false,
    ]);
    $dataProvider->sort = false;

    return $this->render('index', [
        'dataProvider' => $dataProvider,
        'summary' => $this->buildSummaryMetrics($filtered, $today),
    ]);
}

public function actionKpi()
{
    $today = new \DateTime('today');
    $todayStr = $today->format('Y-m-d');
    $plus30 = (clone $today)->modify('+30 days');
    $plus90Str = (clone $today)->modify('+90 days')->format('Y-m-d');
    $hurdaSet = $this->getHurdaEkipmanSet();

    $allUpcoming = PlanliBakim::getAllUpcomingNextDueDates(1000);
    $homeWindow = [];

    foreach ($allUpcoming as $item) {
        if (isset($hurdaSet[(string)$item['ekipman_id']])) {
            continue;
        }

        try {
            $nextDate = new \DateTime($item['sonraki_tarih']);
        } catch (\Exception $e) {
            continue;
        }

        if ($nextDate <= $today) {
            $homeWindow[] = $item;
            continue;
        }

        $diffDays = $today->diff($nextDate)->days;
        if ($diffDays <= 10) {
            $homeWindow[] = $item;
        }
    }

    $arizaDurumDagilim = ArizaTakip::find()
        ->select(['ARIZANIN_SON_DURUMU', 'adet' => 'COUNT(*)'])
        ->groupBy('ARIZANIN_SON_DURUMU')
        ->orderBy(['adet' => SORT_DESC])
        ->asArray()
        ->all();

    $planliPeriyotDagilim = PlanliBakim::find()
        ->select(['periyodu', 'adet' => 'COUNT(*)'])
        ->where(['<>', 'durumu', PlanliBakim::DURUM_OTELEME])
        ->groupBy('periyodu')
        ->orderBy(['adet' => SORT_DESC])
        ->asArray()
        ->all();

    $planliPeriyotCounter = [];
    foreach ($allUpcoming as $item) {
        if (isset($hurdaSet[(string)$item['ekipman_id']])) {
            continue;
        }

        try {
            $nextDate = new \DateTime((string)$item['sonraki_tarih']);
        } catch (\Exception $e) {
            continue;
        }

        if ($nextDate < $today || $nextDate > $plus30) {
            continue;
        }

        $periyot = trim((string)($item['periyodu'] ?? ''));
        if ($periyot === '') {
            $periyot = 'Belirtilmemiş';
        }

        if (!isset($planliPeriyotCounter[$periyot])) {
            $planliPeriyotCounter[$periyot] = 0;
        }
        $planliPeriyotCounter[$periyot]++;
    }

    arsort($planliPeriyotCounter);
    $planliPeriyotDagilim30 = [];
    foreach ($planliPeriyotCounter as $periyot => $adet) {
        $planliPeriyotDagilim30[] = [
            'periyodu' => $periyot,
            'adet' => $adet,
        ];
    }

    $minus90Str = (clone $today)->modify('-90 days')->format('Y-m-d');
    $planliDurumDagilim90 = PlanliBakim::find()
        ->select(['durumu', 'adet' => 'COUNT(*)'])
        ->where(['>=', 'tarihi', $minus90Str])
        ->andWhere(['<=', 'tarihi', $todayStr])
        ->groupBy('durumu')
        ->orderBy(['adet' => SORT_DESC])
        ->asArray()
        ->all();

    $periyodikGecikmisAdet = (int)PeriyodikKontrol::find()
        ->where(['<', 'gelecek_kontrol_tarihi', $todayStr])
        ->count();

    $periyodikYaklasan90Adet = (int)PeriyodikKontrol::find()
        ->where(['between', 'gelecek_kontrol_tarihi', $todayStr, $plus90Str])
        ->count();

    $bakimTakipDagilim = BakimTakip::find()
        ->select(['BAKIM_GENEL', 'adet' => 'COUNT(*)'])
        ->where(['>=', 'TARIH', $minus90Str])
        ->andWhere(['<=', 'TARIH', $todayStr])
        ->groupBy('BAKIM_GENEL')
        ->orderBy(['adet' => 'DESC'])
        ->asArray()
        ->all();

    return $this->render('kpi', [
        'summary' => $this->buildSummaryMetrics($homeWindow, $today),
        'arizaDurumDagilim' => $arizaDurumDagilim,
        'planliPeriyotDagilim' => $planliPeriyotDagilim,
        'planliPeriyotDagilim30' => $planliPeriyotDagilim30,
        'planliDurumDagilim90' => $planliDurumDagilim90,
        'bakimTakipDagilim' => $bakimTakipDagilim,
        'periyodikGecikmisAdet' => $periyodikGecikmisAdet,
        'periyodikYaklasan90Adet' => $periyodikYaklasan90Adet,
    ]);
}

public function actionPeriyodikKontroller()
{
    $query = PeriyodikKontrol::find()
        ->orderBy(['gelecek_kontrol_tarihi' => SORT_ASC, 'ekipman_id' => SORT_ASC]);

    $searchTerm = trim((string)Yii::$app->request->get('q', ''));
    $quickFilter = trim((string)Yii::$app->request->get('quick', ''));
    $todayStr = date('Y-m-d');
    $plus30Str = date('Y-m-d', strtotime('+30 days'));
    $plus90Str = date('Y-m-d', strtotime('+90 days'));

    if ($quickFilter === 'gecikmis') {
        $query->andWhere(['<', 'gelecek_kontrol_tarihi', $todayStr]);
    } elseif ($quickFilter === 'yaklasan-30') {
        $query->andWhere(['between', 'gelecek_kontrol_tarihi', $todayStr, $plus30Str]);
    } elseif ($quickFilter === 'yaklasan-90') {
        $query->andWhere(['between', 'gelecek_kontrol_tarihi', $todayStr, $plus90Str]);
    }

    if ($searchTerm !== '') {
        $query->andFilterWhere(['or',
            ['like', 'ekipman_id', $searchTerm],
            ['like', 'cihaz_adi', $searchTerm],
            ['like', 'bulundugu_yer', $searchTerm],
            ['like', 'rapor_no', $searchTerm],
        ]);
    }

    $dataProvider = new ActiveDataProvider([
        'query' => $query,
        'pagination' => [
            'defaultPageSize' => 20,
            'pageSizeParam'   => 'per-page',
            'pageSizeLimit'   => [1, 500],
        ],
    ]);
    $dataProvider->sort = false;

    return $this->render('periyodik-kontroller', [
        'dataProvider' => $dataProvider,
        'searchTerm' => $searchTerm,
        'quickFilter' => $quickFilter,
    ]);
}

public function actionRapor($file)
{
    // Sadece beklenen formatta dosya adlarına izin ver (güvenlik için)
    if (!preg_match('/^\d{6}\.\d{4}\.\d{1,2}\.pdf$/', $file)) {
        throw new NotFoundHttpException('Geçersiz dosya adı.');
    }

    $path = Yii::getAlias('@webroot/uploads/periyodik-raporlar/' . $file);
    if (!is_file($path)) {
        throw new NotFoundHttpException('Rapor dosyası bulunamadı.');
    }

    return Yii::$app->response->sendFile($path, $file, ['inline' => true]);
}

public function actionTopluBakimIsle()
{
    $request = Yii::$app->request;
    $selectedIds = array_filter((array)$request->post('selection', []));
    $topluTarih = trim((string)$request->post('toplu_tarih', ''));
    $bakimTakipEkle = (int)$request->post('bakim_takip_ekle', 0) === 1;
    $bakimTakipKayitTuru = trim((string)$request->post('bakim_takip_kayit_turu', 'grup'));
    $bakimSuresiSaatRaw = trim((string)$request->post('bakim_suresi_saat', ''));
    $bakimYapilanIsEk = trim((string)$request->post('bakim_yapilan_is_ek', ''));
    $bakimGrupBasligi = trim((string)$request->post('bakim_grup_basligi', ''));
    $bakimIsiYapanlar = array_values(array_filter(array_map('trim', (array)$request->post('bakim_isi_yapanlar', []))));

    if (empty($selectedIds)) {
        Yii::$app->session->setFlash('error', 'Lütfen en az bir planlı bakım kaydı seçiniz.');
        return $this->redirect(['index']);
    }

    if ($topluTarih === '') {
        Yii::$app->session->setFlash('error', 'Lütfen bakım tarihini seçiniz.');
        return $this->redirect(['index']);
    }

    $date = \DateTime::createFromFormat('Y-m-d', $topluTarih);
    $isValidDate = $date && $date->format('Y-m-d') === $topluTarih;
    if (!$isValidDate) {
        Yii::$app->session->setFlash('error', 'Geçerli bir bakım tarihi giriniz.');
        return $this->redirect(['index']);
    }

    if ($bakimTakipEkle) {
        if (!in_array($bakimTakipKayitTuru, ['ayri', 'grup'], true)) {
            $bakimTakipKayitTuru = 'grup';
        }

        if ($bakimSuresiSaatRaw === '' || !is_numeric($bakimSuresiSaatRaw) || (float)$bakimSuresiSaatRaw <= 0) {
            Yii::$app->session->setFlash('error', 'Bakım Takip için geçerli bir bakım süresi giriniz.');
            return $this->redirect(['index']);
        }

        if (empty($bakimIsiYapanlar)) {
            Yii::$app->session->setFlash('error', 'Bakım Takip için en az bir personel seçiniz.');
            return $this->redirect(['index']);
        }

    }

    $kaynakKayitlar = PlanliBakim::find()->where(['id' => $selectedIds])->all();
    if (count($kaynakKayitlar) === 0) {
        Yii::$app->session->setFlash('error', 'Seçilen kayıtlar bulunamadı.');
        return $this->redirect(['index']);
    }

    $transaction = Yii::$app->db->beginTransaction();
    try {
        $olusan = 0;
        $bakimTakipOlusan = 0;
        $bakimSuresiSaat = (float)$bakimSuresiSaatRaw;
        $grupEkipmanIds = [];
        $grupPeriyotlar = [];
        $grupPlanliIds = [];
        foreach ($kaynakKayitlar as $kaynak) {
            $yeni = new PlanliBakim();
            $yeni->kodu = $kaynak->kodu;
            $yeni->tanimi = $kaynak->tanimi;
            $yeni->periyodu = $kaynak->periyodu;
            $yeni->tarihi = $topluTarih;

            if (!$yeni->save()) {
                $hata = implode(' | ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $yeni->getErrors()));
                throw new \RuntimeException('Toplu işlem başarısız: ' . $hata);
            }

            $olusan++;
            $grupPlanliIds[] = (int)$yeni->id;

            $grupEkipmanIds[] = (string)$kaynak->kodu;
            $grupPeriyotlar[] = (string)$kaynak->periyodu;

            if ($bakimTakipEkle && $bakimTakipKayitTuru !== 'grup') {
                $bakimTakip = new BakimTakip();
                $bakimTakip->BAKIM_GENEL = 'BAKIM';
                $bakimTakip->PERIYODIK_PLANLI = $this->normalizePlanliPeriyotForBakim($kaynak->periyodu);
                $bakimTakip->TARIH = $topluTarih;
                $bakimTakip->BAKIM_SURESI_SAAT = $bakimSuresiSaat;
                $bakimTakip->YAPILAN_IS = 'Planlı bakımları talimata uygun yapıldı.' . ($bakimYapilanIsEk !== '' ? "\n" . $bakimYapilanIsEk : '');
                $bakimTakip->ISI_YAPANLAR = $bakimIsiYapanlar;
                $bakimTakip->ekipmanIds = [(string)$kaynak->kodu];

                $ekipman = Ekipman::findOne((string)$kaynak->kodu);
                if ($ekipman && !empty($ekipman->EKIPMAN_YERI)) {
                    $bakimTakip->YERI = (string)$ekipman->EKIPMAN_YERI;
                }

                if (!$bakimTakip->save()) {
                    $hata = implode(' | ', array_map(static function ($errors) {
                        return implode(', ', $errors);
                    }, $bakimTakip->getErrors()));
                    throw new \RuntimeException('Bakım takip kaydı oluşturulamadı: ' . $hata);
                }

                $link = new BakimTakipPlanli();
                $link->bakim_id = (int)$bakimTakip->id;
                $link->planli_id = (int)$yeni->id;
                $link->link_type = BakimTakipPlanli::TYPE_GENERATED;
                $link->created_at = date('Y-m-d H:i:s');

                if (!$link->save()) {
                    $hata = implode(' | ', array_map(static function ($errors) {
                        return implode(', ', $errors);
                    }, $link->getErrors()));
                    throw new \RuntimeException('Bakım-planlı bağlantısı kaydedilemedi: ' . $hata);
                }

                $bakimTakipOlusan++;
            }
        }

        if ($bakimTakipEkle && $bakimTakipKayitTuru === 'grup') {
            $groupBakimTakip = new BakimTakip();
            $groupBakimTakip->BAKIM_GENEL = 'BAKIM';
            $groupBakimTakip->PERIYODIK_PLANLI = $this->buildGroupPlanliPeriyot($grupPeriyotlar);
            $groupBakimTakip->TARIH = $topluTarih;
            $groupBakimTakip->BAKIM_SURESI_SAAT = $bakimSuresiSaat;
            $groupBakimTakip->SISTEM_CIHAZ_OZELLIK = $bakimGrupBasligi;
            $groupBakimTakip->YAPILAN_IS = 'Planlı bakımları talimata uygun yapıldı.' . ($bakimYapilanIsEk !== '' ? "\n" . $bakimYapilanIsEk : '');
            $groupBakimTakip->ISI_YAPANLAR = $bakimIsiYapanlar;
            $groupBakimTakip->ekipmanIds = array_values(array_unique(array_filter($grupEkipmanIds)));
            $groupBakimTakip->YERI = $this->buildGroupYerLabel($groupBakimTakip->ekipmanIds);

            if (!$groupBakimTakip->save()) {
                $hata = implode(' | ', array_map(static function ($errors) {
                    return implode(', ', $errors);
                }, $groupBakimTakip->getErrors()));
                throw new \RuntimeException('Grup bakım takip kaydı oluşturulamadı: ' . $hata);
            }

            foreach ($grupPlanliIds as $planliId) {
                $link = new BakimTakipPlanli();
                $link->bakim_id = (int)$groupBakimTakip->id;
                $link->planli_id = (int)$planliId;
                $link->link_type = BakimTakipPlanli::TYPE_GENERATED;
                $link->created_at = date('Y-m-d H:i:s');

                if (!$link->save()) {
                    $hata = implode(' | ', array_map(static function ($errors) {
                        return implode(', ', $errors);
                    }, $link->getErrors()));
                    throw new \RuntimeException('Grup bakım-planlı bağlantısı kaydedilemedi: ' . $hata);
                }
            }

            $bakimTakipOlusan = 1;
        }

        $transaction->commit();
        $mesaj = $olusan . ' kayıt için bakım işlemi tamamlandı.';
        if ($bakimTakipEkle) {
            if ($bakimTakipKayitTuru === 'grup') {
                $mesaj .= ' Bakım Takip altında 1 grup kaydı oluşturuldu.';
            } else {
                $mesaj .= ' Bakım Takip altında ' . $bakimTakipOlusan . ' ayrı kayıt oluşturuldu.';
            }
        }
        Yii::$app->session->setFlash('success', $mesaj);
    } catch (\Throwable $e) {
        $transaction->rollBack();
        Yii::$app->session->setFlash('error', 'Toplu bakım işlemi sırasında hata oluştu: ' . $e->getMessage());
    }

    return $this->redirect(['index']);
}

private function normalizePlanliPeriyotForBakim(?string $periyot): string
{
    $value = trim((string)$periyot);
    if ($value === '') {
        return 'PLANLI';
    }

    $value = preg_replace('/^Periyodik\s*:/iu', 'PLANLI:', $value);
    $value = preg_replace('/^Planlı\s*:/iu', 'PLANLI:', $value);

    if (mb_stripos($value, 'PLANLI') === false) {
        $value = 'PLANLI: ' . $value;
    }

    return $value;
}

private function buildGroupPlanliPeriyot(array $periyotlar): string
{
    $normalized = array_values(array_unique(array_filter(array_map(function ($item) {
        return $this->normalizePlanliPeriyotForBakim((string)$item);
    }, $periyotlar))));

    if (count($normalized) === 1) {
        return $normalized[0];
    }

    return 'PLANLI TOPLU BAKIM';
}

private function buildGroupYerLabel(array $ekipmanIds): string
{
    $yerler = Ekipman::find()
        ->select('EKIPMAN_YERI')
        ->where(['id' => $ekipmanIds])
        ->andWhere(['is not', 'EKIPMAN_YERI', null])
        ->distinct()
        ->column();

    $yerler = array_values(array_filter(array_map('trim', $yerler)));
    if (count($yerler) === 1) {
        return (string)$yerler[0];
    }

    return 'TOPLU BAKIM';
}

private function getHurdaEkipmanSet(): array
{
    $hurdaIds = Ekipman::find()
        ->alias('e')
        ->select(['e.id'])
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where("UPPER(COALESCE(em.DURUM, 'AKTIF')) = 'HURDA'")
        ->column();

    return array_fill_keys(array_map('strval', $hurdaIds), true);
}

private function buildSummaryMetrics(array $homeUpcomingItems, \DateTimeInterface $today): array
{
    $todayStr = $today->format('Y-m-d');
    $plus30Str = (new \DateTime($todayStr))->modify('+30 days')->format('Y-m-d');
    $monthStart = (new \DateTime('first day of this month'))->format('Y-m-d');
    $monthEnd = (new \DateTime('last day of this month'))->format('Y-m-d');

    $toplamEkipman = (int)Ekipman::find()->count();
    $hurdaEkipman = (int)count($this->getHurdaEkipmanSet());
    $aktifEkipman = max(0, $toplamEkipman - $hurdaEkipman);

    $toplamAriza = (int)ArizaTakip::find()->count();
    $acikAriza = (int)ArizaTakip::find()
        ->where(['or', ['ARIZANIN_GIDERILDIGI_TARIH' => null], ['ARIZANIN_GIDERILDIGI_TARIH' => '']])
        ->count();
    $buAyAriza = (int)ArizaTakip::find()
        ->where(['between', 'ARIZA_TARIHI', $monthStart, $monthEnd])
        ->count();

    $toplamBakim = (int)BakimTakip::find()->count();
    $buAyBakim = (int)BakimTakip::find()
        ->where(['between', 'TARIH', $monthStart, $monthEnd])
        ->count();

    $toplamMaliyet = (float)(ArizaTakip::find()->sum('MALIYET_TL') ?: 0);
    $buAyMaliyet = (float)(ArizaTakip::find()
        ->where(['between', 'ARIZA_TARIHI', $monthStart, $monthEnd])
        ->sum('MALIYET_TL') ?: 0);

    $planliGecikmis = 0;
    foreach ($homeUpcomingItems as $item) {
        try {
            if ((new \DateTime($item['sonraki_tarih'])) <= $today) {
                $planliGecikmis++;
            }
        } catch (\Exception $e) {
            continue;
        }
    }

    $toplamPeriyodik = (int)PeriyodikKontrol::find()->count();
    $periyodikGecikmis = (int)PeriyodikKontrol::find()
        ->where(['<', 'gelecek_kontrol_tarihi', $todayStr])
        ->count();
    $periyodikYaklasan = (int)PeriyodikKontrol::find()
        ->where(['between', 'gelecek_kontrol_tarihi', $todayStr, $plus30Str])
        ->count();

    return [
        'toplamEkipman' => $toplamEkipman,
        'aktifEkipman' => $aktifEkipman,
        'hurdaEkipman' => $hurdaEkipman,
        'toplamAriza' => $toplamAriza,
        'acikAriza' => $acikAriza,
        'buAyAriza' => $buAyAriza,
        'toplamBakim' => $toplamBakim,
        'buAyBakim' => $buAyBakim,
        'planliYaklasan10' => count($homeUpcomingItems),
        'planliGecikmis' => $planliGecikmis,
        'toplamPeriyodik' => $toplamPeriyodik,
        'periyodikGecikmis' => $periyodikGecikmis,
        'periyodikYaklasan30' => $periyodikYaklasan,
        'toplamMaliyet' => $toplamMaliyet,
        'buAyMaliyet' => $buAyMaliyet,
    ];
}




}
