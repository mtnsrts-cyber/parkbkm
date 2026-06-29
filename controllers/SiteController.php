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
use yii\db\Expression;
use yii\web\UploadedFile;
use app\models\LoginForm;
use app\models\PlanliBakim;
use app\models\PeriyodikKontrol;
use app\models\ArizaTakip;
use app\models\BakimTakip;
use app\models\BakimTakipPlanli;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
class SiteController extends Controller
{
    public function beforeAction($action)
    {
        if ($action->id === 'periyodik-rapor-upload') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout', 'toplu-bakim-isle', 'periyodik-kontrol-import', 'periyodik-rapor-upload', 'periyodik-kontrol-update', 'periyodik-kontrol-delete'],
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
                    [
                        'actions' => ['periyodik-kontrol-import', 'periyodik-rapor-upload', 'periyodik-kontrol-update', 'periyodik-kontrol-delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            return !Yii::$app->user->isGuest
                                && Yii::$app->user->identity->role === 'admin';
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'toplu-bakim-isle' => ['post'],
                    'periyodik-kontrol-import' => ['post'],
                    'periyodik-rapor-upload' => ['post'],
                    'periyodik-kontrol-delete' => ['post'],
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
    $pasifSet = $this->getPasifEkipmanSet();
    $quickFilter = trim((string)Yii::$app->request->get('quick', ''));

    $today = new \DateTime('today');
    $filtered = [];

    foreach ($allUpcoming as $item) {
        // Hurda veya kullanım dışı ekipmanları ana sayfa listesinden çıkar.
        if (isset($pasifSet[(string)$item['ekipman_id']])) {
            continue;
        }

        try {
            $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
        } catch (\Exception $e) {
            continue;
        }

        // Gecikmiş, bugün son günü olan veya önümüzdeki 10 gün içinde olanlar
        if ($sonrakiTarih <= $today) {
            $filtered[] = $item;
        } else {
            $diffDays = $today->diff($sonrakiTarih)->days;
            if ($diffDays <= 10) {
                $filtered[] = $item;
            }
        }
    }

    $displayItems = $filtered;
    if ($quickFilter === 'planli-gecikmis') {
        $displayItems = array_values(array_filter($filtered, function (array $item) use ($today): bool {
            try {
                return (new \DateTime($item['sonraki_tarih'])) < $today;
            } catch (\Exception $e) {
                return false;
            }
        }));
    } elseif ($quickFilter === 'planli-son-gun') {
        $displayItems = array_values(array_filter($filtered, function (array $item) use ($today): bool {
            try {
                return (new \DateTime($item['sonraki_tarih'])) == $today;
            } catch (\Exception $e) {
                return false;
            }
        }));
    } elseif ($quickFilter === 'planli-yarin') {
        $tomorrow = (clone $today)->modify('+1 day');
        $displayItems = array_values(array_filter($filtered, function (array $item) use ($tomorrow): bool {
            try {
                return (new \DateTime($item['sonraki_tarih']))->format('Y-m-d') === $tomorrow->format('Y-m-d');
            } catch (\Exception $e) {
                return false;
            }
        }));
    } elseif ($quickFilter === 'planli-7-gun') {
        $weekEnd = (clone $today)->modify('+7 days');
        $displayItems = array_values(array_filter($filtered, function (array $item) use ($today, $weekEnd): bool {
            try {
                $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
                return $sonrakiTarih > $today && $sonrakiTarih <= $weekEnd;
            } catch (\Exception $e) {
                return false;
            }
        }));
    }

    $dataProvider = new ArrayDataProvider([
        'allModels' => $displayItems,
        'pagination' => false,
    ]);
    $dataProvider->sort = false;

    return $this->render('index', [
        'dataProvider' => $dataProvider,
        'summary' => $this->buildSummaryMetrics($filtered, $today),
        'quickFilter' => $quickFilter,
    ]);
}

public function actionKpi()
{
    $today = new \DateTime('today');
    $todayStr = $today->format('Y-m-d');
    $periyodikGecikmisLimitStr = (clone $today)->modify('-30 days')->format('Y-m-d');
    $plus30 = (clone $today)->modify('+30 days');
    $plus90Str = (clone $today)->modify('+90 days')->format('Y-m-d');
    $pasifSet = $this->getPasifEkipmanSet();

    $allUpcoming = PlanliBakim::getAllUpcomingNextDueDates(1000);
    $homeWindow = [];

    foreach ($allUpcoming as $item) {
        if (isset($pasifSet[(string)$item['ekipman_id']])) {
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
        ->alias('pb')
        ->select(['periyodu' => 'pb.periyodu', 'adet' => 'COUNT(*)'])
        ->innerJoin(['e' => Ekipman::tableName()], 'e.id = pb.kodu')
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where(['<>', 'pb.durumu', PlanliBakim::DURUM_OTELEME])
        ->andWhere($this->activeEkipmanCondition('em'))
        ->groupBy('pb.periyodu')
        ->orderBy(['adet' => SORT_DESC])
        ->asArray()
        ->all();

    $planliPeriyotCounter = [];
    foreach ($allUpcoming as $item) {
        if (isset($pasifSet[(string)$item['ekipman_id']])) {
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
        ->alias('pb')
        ->select(['durumu' => 'pb.durumu', 'adet' => 'COUNT(*)'])
        ->innerJoin(['e' => Ekipman::tableName()], 'e.id = pb.kodu')
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where(['>=', 'pb.tarihi', $minus90Str])
        ->andWhere(['<=', 'pb.tarihi', $todayStr])
        ->andWhere($this->activeEkipmanCondition('em'))
        ->groupBy('pb.durumu')
        ->orderBy(['adet' => SORT_DESC])
        ->asArray()
        ->all();

    $periyodikGecikmisAdet = (int)PeriyodikKontrol::find()
        ->alias('pk')
        ->where(['<', 'pk.gelecek_kontrol_tarihi', $periyodikGecikmisLimitStr])
        ->andWhere($this->currentPeriyodikKontrolCondition('pk'))
        ->count();

    $periyodikYaklasan90Adet = (int)PeriyodikKontrol::find()
        ->alias('pk')
        ->where(['between', 'pk.gelecek_kontrol_tarihi', $todayStr, $plus90Str])
        ->andWhere($this->currentPeriyodikKontrolCondition('pk'))
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
        ->alias('pk')
        ->select([
            'pk.*',
            'is_eski' => new Expression('CASE WHEN ' . $this->latestPeriyodikKontrolCondition('pk') . ' THEN 0 ELSE 1 END'),
        ])
        ->innerJoin(['e' => Ekipman::tableName()], 'BINARY e.id = BINARY pk.ekipman_id')
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->andWhere($this->activeEkipmanCondition('em'))
        ->orderBy(['pk.gelecek_kontrol_tarihi' => SORT_ASC, 'pk.ekipman_id' => SORT_ASC]);

    $searchTerm = trim((string)Yii::$app->request->get('q', ''));
    $quickFilter = trim((string)Yii::$app->request->get('quick', ''));
    $scope = trim((string)Yii::$app->request->get('scope', 'active'));
    if (!in_array($scope, ['active', 'all', 'old'], true)) {
        $scope = 'active';
    }
    $todayStr = date('Y-m-d');
    $periyodikGecikmisLimitStr = date('Y-m-d', strtotime('-30 days'));
    $plus30Str = date('Y-m-d', strtotime('+30 days'));
    $plus90Str = date('Y-m-d', strtotime('+90 days'));

    if ($quickFilter === 'gecikmis') {
        $query->andWhere(['<', 'pk.gelecek_kontrol_tarihi', $periyodikGecikmisLimitStr])
            ->andWhere($this->currentPeriyodikKontrolCondition('pk'));
    } elseif ($quickFilter === 'yaklasan-30') {
        $query->andWhere(['between', 'pk.gelecek_kontrol_tarihi', $periyodikGecikmisLimitStr, $plus30Str])
            ->andWhere($this->currentPeriyodikKontrolCondition('pk'));
    } elseif ($quickFilter === 'yaklasan-90') {
        $query->andWhere(['between', 'pk.gelecek_kontrol_tarihi', $todayStr, $plus90Str])
            ->andWhere($this->currentPeriyodikKontrolCondition('pk'));
    } elseif ($scope === 'active') {
        $query->andWhere($this->latestPeriyodikKontrolCondition('pk'));
    } elseif ($scope === 'old') {
        $query->andWhere('NOT (' . $this->latestPeriyodikKontrolCondition('pk') . ')');
    }

    if ($searchTerm !== '') {
        $query->andFilterWhere(['or',
            ['like', 'pk.ekipman_id', $searchTerm],
            ['like', 'pk.cihaz_adi', $searchTerm],
            ['like', 'pk.bulundugu_yer', $searchTerm],
            ['like', 'pk.rapor_no', $searchTerm],
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
        'scope' => $scope,
    ]);
}

public function actionRapor($file)
{
    // Sadece beklenen formatta dosya adlarına izin ver (güvenlik için)
    if (!preg_match('/^\d{6}\.\d{4}\.\d+\.pdf$/', $file)) {
        throw new NotFoundHttpException('Geçersiz dosya adı.');
    }

    $path = Yii::getAlias('@webroot/uploads/periyodik-raporlar/' . $file);
    if (!is_file($path)) {
        throw new NotFoundHttpException('Rapor dosyası bulunamadı.');
    }

    return Yii::$app->response->sendFile($path, $file, ['inline' => true]);
}

public function actionPeriyodikKontrolImport()
{
    $file = UploadedFile::getInstanceByName('periyodik_excel');
    if ($file === null) {
        Yii::$app->session->setFlash('error', 'Lütfen Excel veya CSV dosyası seçiniz.');
        return $this->redirect(['periyodik-kontroller']);
    }

    $extension = strtolower((string)$file->extension);
    if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
        Yii::$app->session->setFlash('error', 'Sadece .xlsx, .xls veya .csv dosyası yüklenebilir.');
        return $this->redirect(['periyodik-kontroller']);
    }

    $transaction = Yii::$app->db->beginTransaction();
    try {
        if ($extension === 'csv') {
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter($this->detectCsvDelimiter($file->tempName));
            $reader->setInputEncoding('UTF-8');
            $spreadsheet = $reader->load($file->tempName);
        } else {
            $spreadsheet = IOFactory::load($file->tempName);
        }
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $rows = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, true);

        if (count($rows) < 2) {
            throw new \RuntimeException('Dosyada başlık ve veri satırı bulunamadı.');
        }

        $headerRowNumber = $this->findPeriyodikHeaderRow($rows);
        if ($headerRowNumber === null) {
            throw new \RuntimeException('Başlık satırı bulunamadı. En az Ekipman Kodu/Kodu ve Cihaz Adı başlıkları olmalı.');
        }

        $columnMap = $this->buildPeriyodikColumnMap($rows[$headerRowNumber]);
        if (empty($columnMap['ekipman_id']) || empty($columnMap['cihaz_adi'])) {
            throw new \RuntimeException('Zorunlu başlıklar eksik: Ekipman Kodu/Kodu ve Cihaz Adı.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        for ($rowNumber = $headerRowNumber + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $rows[$rowNumber] ?? [];
            if ($this->isSpreadsheetRowEmpty($row)) {
                continue;
            }

            $data = [];
            foreach ($columnMap as $attribute => $column) {
                $data[$attribute] = trim((string)($row[$column] ?? ''));
            }

            if (($data['ekipman_id'] ?? '') === '' || ($data['cihaz_adi'] ?? '') === '') {
                $skipped++;
                $errors[] = $rowNumber . '. satır atlandı: Ekipman kodu veya cihaz adı boş.';
                continue;
            }

            $data['son_kontrol_tarihi'] = $this->normalizePeriyodikImportDate($this->getSpreadsheetCellValue($row, $columnMap, 'son_kontrol_tarihi'));
            $data['gelecek_kontrol_tarihi'] = $this->normalizePeriyodikImportDate($this->getSpreadsheetCellValue($row, $columnMap, 'gelecek_kontrol_tarihi'));
            $data['adet'] = isset($data['adet']) && $data['adet'] !== '' ? (int)$data['adet'] : null;

            foreach (['periyodik_kontrol_gerektirir', 'periyodik_kontrol_gerektirmez'] as $booleanAttribute) {
                if (array_key_exists($booleanAttribute, $data)) {
                    $data[$booleanAttribute] = $this->parsePeriyodikImportBoolean($data[$booleanAttribute]);
                }
            }

            $model = $this->findExistingPeriyodikKontrol($data) ?: new PeriyodikKontrol();
            $isNew = $model->isNewRecord;
            foreach ($data as $attribute => $value) {
                if ($model->hasAttribute($attribute)) {
                    $model->$attribute = $value === '' ? null : $value;
                }
            }

            if (!$model->save()) {
                $skipped++;
                $message = implode(' | ', array_map(static function ($items) {
                    return implode(', ', $items);
                }, $model->getErrors()));
                $errors[] = $rowNumber . '. satır kaydedilemedi: ' . $message;
                continue;
            }

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        $transaction->commit();
        $message = "Periyodik kontrol import tamamlandı. Yeni: {$created}, güncellenen: {$updated}, atlanan: {$skipped}.";
        if (!empty($errors)) {
            $message .= ' İlk uyarılar: ' . implode(' ', array_slice($errors, 0, 5));
        }
        Yii::$app->session->setFlash($skipped > 0 ? 'warning' : 'success', $message);
    } catch (\Throwable $e) {
        $transaction->rollBack();
        Yii::$app->session->setFlash('error', 'Import sırasında hata oluştu: ' . $e->getMessage());
    }

    return $this->redirect(['periyodik-kontroller']);
}

public function actionPeriyodikRaporUpload()
{
    $contentLength = (int)(Yii::$app->request->headers->get('Content-Length') ?: 0);
    $postMaxSize = $this->phpSizeToBytes((string)ini_get('post_max_size'));
    if ($postMaxSize > 0 && $contentLength > $postMaxSize) {
        Yii::$app->session->setFlash('error', 'Yüklenen dosyaların toplam boyutu sunucu limitini aşıyor. Limit: ' . ini_get('post_max_size') . '. Daha az dosya seçerek tekrar deneyin.');
        return $this->redirect(['periyodik-kontroller']);
    }

    $files = UploadedFile::getInstancesByName('periyodik_raporlar');
    if (empty($files)) {
        Yii::$app->session->setFlash('error', 'Dosya alınamadı. Sunucu limitlerini kontrol edin: post_max_size=' . ini_get('post_max_size') . ', upload_max_filesize=' . ini_get('upload_max_filesize') . ', max_file_uploads=' . ini_get('max_file_uploads') . '.');
        return $this->redirect(['periyodik-kontroller']);
    }

    $targetDir = Yii::getAlias('@webroot/uploads/periyodik-raporlar');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        Yii::$app->session->setFlash('error', 'Rapor klasörü oluşturulamadı.');
        return $this->redirect(['periyodik-kontroller']);
    }

    $uploaded = 0;
    $overwritten = 0;
    $skipped = 0;
    $errors = [];

    foreach ($files as $file) {
        $originalName = (string)$file->name;
        $extension = strtolower((string)$file->extension);
        if ($extension !== 'pdf') {
            $skipped++;
            $errors[] = $originalName . ': PDF değil.';
            continue;
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        if (!preg_match('/^(\d{6}\.\d{4}\.\d+)$/', $baseName, $m)) {
            $skipped++;
            $errors[] = $originalName . ': Dosya adı rapor no formatında değil.';
            continue;
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $m[1] . '.pdf';
        $exists = is_file($targetPath);
        if (!$file->saveAs($targetPath)) {
            $skipped++;
            $errors[] = $originalName . ': Kaydedilemedi.';
            continue;
        }

        if ($exists) {
            $overwritten++;
        } else {
            $uploaded++;
        }
    }

    $message = "Periyodik rapor yükleme tamamlandı. Yeni: {$uploaded}, değiştirilen: {$overwritten}, atlanan: {$skipped}.";
    if (!empty($errors)) {
        $message .= ' İlk uyarılar: ' . implode(' ', array_slice($errors, 0, 5));
    }
    Yii::$app->session->setFlash($skipped > 0 ? 'warning' : 'success', $message);

    return $this->redirect(['periyodik-kontroller']);
}

private function phpSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $bytes = (int)$value;
    if ($unit === 'g') {
        return $bytes * 1024 * 1024 * 1024;
    }
    if ($unit === 'm') {
        return $bytes * 1024 * 1024;
    }
    if ($unit === 'k') {
        return $bytes * 1024;
    }

    return $bytes;
}

public function actionPeriyodikKontrolUpdate($id, $return = null)
{
    $model = $this->findPeriyodikKontrolModel((int)$id);

    if ($model->load(Yii::$app->request->post())) {
        foreach (['son_kontrol_tarihi', 'gelecek_kontrol_tarihi'] as $dateAttribute) {
            if ($model->$dateAttribute === '') {
                $model->$dateAttribute = null;
            }
        }
        if ($model->adet === '') {
            $model->adet = null;
        }

        if ($model->save()) {
            Yii::$app->session->setFlash('success', 'Periyodik kontrol kaydı güncellendi.');
            return $this->redirect($this->normalizeLocalReturnUrl($return) ?: ['periyodik-kontroller']);
        }
    }

    return $this->render('periyodik-kontrol-form', [
        'model' => $model,
        'return' => $this->normalizeLocalReturnUrl($return),
    ]);
}

public function actionPeriyodikKontrolDelete($id, $return = null)
{
    $model = $this->findPeriyodikKontrolModel((int)$id);
    $model->delete();

    Yii::$app->session->setFlash('success', 'Periyodik kontrol kaydı silindi.');
    return $this->redirect($this->normalizeLocalReturnUrl($return) ?: ['periyodik-kontroller']);
}

public function actionTopluBakimIsle()
{
    $request = Yii::$app->request;
    $selectedIds = array_filter((array)$request->post('selection', []));
    $topluTarih = trim((string)$request->post('toplu_tarih', ''));
    $bakimErtele = (int)$request->post('bakim_ertele', 0) === 1;
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

    if ($bakimTakipEkle && !$bakimErtele) {
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
            if ($bakimErtele) {
                $yeni->durumu = PlanliBakim::DURUM_OTELEME;
            }

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

            if ($bakimTakipEkle && !$bakimErtele && $bakimTakipKayitTuru !== 'grup') {
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

        if ($bakimTakipEkle && !$bakimErtele && $bakimTakipKayitTuru === 'grup') {
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
        $mesaj = $olusan . ' kayıt için ' . ($bakimErtele ? 'bakım öteleme işlemi' : 'bakım işlemi') . ' tamamlandı.';
        if ($bakimTakipEkle && !$bakimErtele) {
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

private function findPeriyodikHeaderRow(array $rows): ?int
{
    foreach ($rows as $rowNumber => $row) {
        $map = $this->buildPeriyodikColumnMap($row);
        if (!empty($map['ekipman_id']) && !empty($map['cihaz_adi'])) {
            return (int)$rowNumber;
        }
    }

    return null;
}

private function findPeriyodikKontrolModel(int $id): PeriyodikKontrol
{
    $model = PeriyodikKontrol::findOne($id);
    if ($model === null) {
        throw new NotFoundHttpException('Periyodik kontrol kaydı bulunamadı.');
    }

    return $model;
}

private function normalizeLocalReturnUrl(?string $return): ?string
{
    if ($return === null || $return === '' || preg_match('/^https?:\/\//i', $return)) {
        return null;
    }

    return $return;
}

private function buildPeriyodikColumnMap(array $headerRow): array
{
    $aliases = [
        'ekipman_id' => ['ekipman kodu', 'ekipman id', 'kodu', 'kod'],
        'cihaz_adi' => ['cihaz adi', 'cihaz adı', 'device name', 'malzemenin tanimi', 'malzemenin tanımı'],
        'rapor_no' => ['rapor no', 'rapor numarasi', 'rapor numarası'],
        'bulundugu_yer' => ['bulundugu yer', 'bulunduğu yer', 'location', 'yer'],
        'adet' => ['adet', 'pcs'],
        'kabul_degerleri' => ['kabul degerleri', 'kabul değerleri'],
        'olcum_degerleri' => ['olcum degerleri', 'ölçüm değerleri', 'olcum değerleri'],
        'son_kontrol_tarihi' => ['son kontrol tarihi', 'son kontrol tarih', 'kontrol tarihi', 'kontrol tarih'],
        'gelecek_kontrol_tarihi' => ['gelecek kontrol tarihi', 'gelecek kontrol tarih', 'sonraki kontrol tarihi', 'sonraki kontrol tarih', 'gelecek tarih'],
        'periyodik_kontrol_gerektirir' => ['periyodik kontrol gerektirir', 'kontrol gerektirir'],
        'periyodik_kontrol_gerektirmez' => ['periyodik kontrol gerektirmez', 'kontrol gerektirmez'],
    ];

    $normalizedAliases = [];
    foreach ($aliases as $attribute => $labels) {
        foreach ($labels as $candidate) {
            $normalizedAliases[] = [
                'attribute' => $attribute,
                'label' => $this->normalizePeriyodikImportHeader($candidate),
            ];
        }
    }

    usort($normalizedAliases, static function ($a, $b) {
        return strlen($b['label']) <=> strlen($a['label']);
    });

    $map = [];
    foreach ($headerRow as $column => $label) {
        $normalizedLabel = $this->normalizePeriyodikImportHeader((string)$label);
        if ($normalizedLabel === '') {
            continue;
        }

        foreach ($normalizedAliases as $candidate) {
            if ($normalizedLabel === $candidate['label']) {
                $map[$candidate['attribute']] = $column;
                continue 2;
            }
        }

        foreach ($normalizedAliases as $candidate) {
            if (strlen($candidate['label']) > 5 && preg_match('/(^| )' . preg_quote($candidate['label'], '/') . '( |$)/', $normalizedLabel)) {
                $map[$candidate['attribute']] = $column;
                continue 2;
            }
        }
    }

    return $map;
}

private function normalizePeriyodikImportHeader(string $value): string
{
    $value = strtr($value, [
        'İ' => 'I', 'I' => 'I', 'Ğ' => 'G', 'Ü' => 'U', 'Ş' => 'S', 'Ö' => 'O', 'Ç' => 'C',
        'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c',
    ]);
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

    return trim((string)$value);
}

private function detectCsvDelimiter(string $filePath): string
{
    $line = (string)fgets(fopen($filePath, 'rb'));
    $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
    arsort($delimiters);

    return (string)array_key_first($delimiters);
}

private function isSpreadsheetRowEmpty(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string)$value) !== '') {
            return false;
        }
    }

    return true;
}

private function getSpreadsheetCellValue(array $row, array $columnMap, string $attribute)
{
    if (empty($columnMap[$attribute])) {
        return null;
    }

    return $row[$columnMap[$attribute]] ?? null;
}

private function normalizePeriyodikImportDate($value): ?string
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }

    if (is_numeric($value)) {
        return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
    }

    $value = trim((string)$value);
    foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
        $date = \DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

private function parsePeriyodikImportBoolean(string $value): bool
{
    $value = $this->normalizePeriyodikImportHeader($value);
    return in_array($value, ['1', 'evet', 'e', 'yes', 'true', 'var', 'x'], true);
}

private function findExistingPeriyodikKontrol(array $data): ?PeriyodikKontrol
{
    $ekipmanId = trim((string)($data['ekipman_id'] ?? ''));
    $raporNo = trim((string)($data['rapor_no'] ?? ''));
    if ($ekipmanId === '') {
        return null;
    }

    if ($raporNo !== '') {
        return PeriyodikKontrol::findOne(['ekipman_id' => $ekipmanId, 'rapor_no' => $raporNo]);
    }

    return PeriyodikKontrol::find()
        ->where([
            'ekipman_id' => $ekipmanId,
            'cihaz_adi' => trim((string)($data['cihaz_adi'] ?? '')),
            'son_kontrol_tarihi' => $data['son_kontrol_tarihi'] ?? null,
            'gelecek_kontrol_tarihi' => $data['gelecek_kontrol_tarihi'] ?? null,
        ])
        ->one();
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

private function getPasifEkipmanSet(): array
{
    $pasifIds = Ekipman::find()
        ->alias('e')
        ->select(['e.id'])
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where("UPPER(COALESCE(em.DURUM, 'AKTIF')) IN ('HURDA', 'KULLANIM_DISI')")
        ->column();

    return array_fill_keys(array_map('strval', $pasifIds), true);
}

private function activeEkipmanCondition(string $metaAlias): string
{
    return "UPPER(COALESCE(NULLIF({$metaAlias}.DURUM, ''), 'AKTIF')) = 'AKTIF'";
}

private function buildSummaryMetrics(array $homeUpcomingItems, \DateTimeInterface $today): array
{
    $todayStr = $today->format('Y-m-d');
    $periyodikGecikmisLimitStr = (new \DateTime($todayStr))->modify('-30 days')->format('Y-m-d');
    $plus30Str = (new \DateTime($todayStr))->modify('+30 days')->format('Y-m-d');
    $monthStart = (new \DateTime('first day of this month'))->format('Y-m-d');
    $monthEnd = (new \DateTime('last day of this month'))->format('Y-m-d');

    $toplamEkipman = (int)Ekipman::find()->count();
    $hurdaEkipman = (int)Ekipman::find()
        ->alias('e')
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where("UPPER(COALESCE(NULLIF(em.DURUM, ''), 'AKTIF')) = 'HURDA'")
        ->count();
    $kullanimDisiEkipman = (int)Ekipman::find()
        ->alias('e')
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where("UPPER(COALESCE(NULLIF(em.DURUM, ''), 'AKTIF')) = 'KULLANIM_DISI'")
        ->count();
    $aktifEkipman = max(0, $toplamEkipman - $hurdaEkipman - $kullanimDisiEkipman);

    $toplamAriza = (int)ArizaTakip::find()->count();
    $acikAriza = (int)ArizaTakip::find()
        ->where(['or', ['ARIZANIN_GIDERILDIGI_TARIH' => null], ['ARIZANIN_GIDERILDIGI_TARIH' => '']])
        ->count();
    $arizaFaal = (int)ArizaTakip::find()
        ->where(['ARIZANIN_SON_DURUMU' => 'FAAL'])
        ->count();
    $arizaArizaliFaal = (int)ArizaTakip::find()
        ->where(['ARIZANIN_SON_DURUMU' => 'ARIZALI_FAAL'])
        ->count();
    $arizaGayriFaal = (int)ArizaTakip::find()
        ->where(['ARIZANIN_SON_DURUMU' => 'GAYRI_FAAL'])
        ->count();
    $buAyAriza = (int)ArizaTakip::find()
        ->where(['between', 'ARIZA_TARIHI', $monthStart, $monthEnd])
        ->count();

    $toplamBakim = (int)BakimTakip::find()->count();
    $buAyBakim = (int)BakimTakip::find()
        ->where(['between', 'TARIH', $monthStart, $monthEnd])
        ->count();
    $bakimFaaliyet = $this->buildBakimFaaliyetSummary($monthStart, $monthEnd, $todayStr);

    $toplamMaliyet = (float)(ArizaTakip::find()->sum('MALIYET_TL') ?: 0);
    $buAyMaliyet = (float)(ArizaTakip::find()
        ->where(['between', 'ARIZA_TARIHI', $monthStart, $monthEnd])
        ->sum('MALIYET_TL') ?: 0);

    $planliGecikmis = 0;
    $planliSonGun = 0;
    foreach ($homeUpcomingItems as $item) {
        try {
            $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
            if ($sonrakiTarih < $today) {
                $planliGecikmis++;
            } elseif ($sonrakiTarih == $today) {
                $planliSonGun++;
            }
        } catch (\Exception $e) {
            continue;
        }
    }

    $toplamPeriyodik = (int)PeriyodikKontrol::find()
        ->alias('pk')
        ->andWhere($this->currentPeriyodikKontrolCondition('pk'))
        ->count();
    $periyodikGecikmis = (int)PeriyodikKontrol::find()
        ->alias('pk')
        ->where(['<', 'pk.gelecek_kontrol_tarihi', $periyodikGecikmisLimitStr])
        ->andWhere($this->currentPeriyodikKontrolCondition('pk'))
        ->count();
    $periyodikYaklasan = (int)PeriyodikKontrol::find()
        ->alias('pk')
        ->where(['between', 'pk.gelecek_kontrol_tarihi', $periyodikGecikmisLimitStr, $plus30Str])
        ->andWhere($this->currentPeriyodikKontrolCondition('pk'))
        ->count();

    return [
        'toplamEkipman' => $toplamEkipman,
        'aktifEkipman' => $aktifEkipman,
        'hurdaEkipman' => $hurdaEkipman,
        'kullanimDisiEkipman' => $kullanimDisiEkipman,
        'toplamAriza' => $toplamAriza,
        'acikAriza' => $acikAriza,
        'arizaFaal' => $arizaFaal,
        'arizaArizaliFaal' => $arizaArizaliFaal,
        'arizaGayriFaal' => $arizaGayriFaal,
        'buAyAriza' => $buAyAriza,
        'toplamBakim' => $toplamBakim,
        'buAyBakim' => $buAyBakim,
        'bakimFaaliyet' => $bakimFaaliyet,
        'planliYaklasan10' => count($homeUpcomingItems),
        'planliGecikmis' => $planliGecikmis,
        'planliSonGun' => $planliSonGun,
        'toplamPeriyodik' => $toplamPeriyodik,
        'periyodikGecikmis' => $periyodikGecikmis,
        'periyodikYaklasan30' => $periyodikYaklasan,
        'toplamMaliyet' => $toplamMaliyet,
        'buAyMaliyet' => $buAyMaliyet,
    ];
}

private function buildBakimFaaliyetSummary(string $monthStart, string $monthEnd, string $todayStr): array
{
    $yearStart = (new \DateTime($todayStr))->modify('first day of January this year')->format('Y-m-d');
    $yearEnd = (new \DateTime($todayStr))->modify('last day of December this year')->format('Y-m-d');

    return [
        'month' => $this->buildBakimFaaliyetRange($monthStart, $monthEnd, false),
        'year' => $this->buildBakimFaaliyetRange($yearStart, $yearEnd, false),
        'all' => $this->buildBakimFaaliyetRange(null, null, true),
    ];
}

private function buildBakimFaaliyetRange(?string $startDate, ?string $endDate, bool $withPeriods): array
{
    $linkedBakimIds = BakimTakipPlanli::find()->select('bakim_id');

    $generalQuery = BakimTakip::find()
        ->where(['not in', 'id', $linkedBakimIds])
        ->andWhere([
            'or',
            ['PERIYODIK_PLANLI' => null],
            ['PERIYODIK_PLANLI' => ''],
            ['not like', 'PERIYODIK_PLANLI', 'PLANLI'],
        ]);

    $planliQuery = BakimTakip::find()
        ->alias('bt')
        ->innerJoin(['btp' => BakimTakipPlanli::tableName()], 'btp.bakim_id = bt.id')
        ->innerJoin(['pb' => PlanliBakim::tableName()], 'pb.id = btp.planli_id');

    if ($startDate !== null && $endDate !== null) {
        $generalQuery->andWhere(['between', 'TARIH', $startDate, $endDate]);
        $planliQuery->andWhere(['between', 'bt.TARIH', $startDate, $endDate]);
    }

    $general = (int)$generalQuery->count();
    $planli = (int)(clone $planliQuery)->count('DISTINCT bt.id');
    $periods = [];

    if ($withPeriods) {
        $periodRows = (clone $planliQuery)
            ->select(['periyodu' => 'pb.periyodu', 'adet' => 'COUNT(DISTINCT bt.id)'])
            ->groupBy('pb.periyodu')
            ->orderBy(['pb.periyodu' => SORT_ASC])
            ->asArray()
            ->all();

        foreach ($periodRows as $row) {
            $label = trim(str_replace('Periyodik: ', '', (string)($row['periyodu'] ?? '')));
            if ($label === '') {
                $label = 'Periyot belirtilmemiş';
            }
            $periods[] = [
                'label' => $label,
                'period' => (string)($row['periyodu'] ?? ''),
                'count' => (int)($row['adet'] ?? 0),
            ];
        }
    }

    return [
        'general' => $general,
        'planli' => $planli,
        'periods' => $periods,
        'total' => $general + $planli,
    ];
}

private function currentPeriyodikKontrolCondition(string $alias): string
{
    return $this->activePeriyodikKontrolEkipmanCondition($alias) . ' AND ' . $this->latestPeriyodikKontrolCondition($alias);
}

private function activePeriyodikKontrolEkipmanCondition(string $alias): string
{
    return "EXISTS (
        SELECT 1
        FROM ekipman e_current
        LEFT JOIN ekipman_meta em_current ON em_current.ekipman_id = e_current.id
        WHERE BINARY e_current.id = BINARY {$alias}.ekipman_id
          AND UPPER(COALESCE(NULLIF(em_current.DURUM, ''), 'AKTIF')) = 'AKTIF'
    )";
}

private function latestPeriyodikKontrolCondition(string $alias): string
{
    return "NOT (" . $this->obsoleteTopraklamaKontrolCondition($alias) . ") AND NOT EXISTS (
        SELECT 1
        FROM periyodik_kontrol pk_newer
        WHERE BINARY pk_newer.ekipman_id = BINARY {$alias}.ekipman_id
          AND COALESCE(pk_newer.cihaz_adi, '') = COALESCE({$alias}.cihaz_adi, '')
          AND (
              (pk_newer.gelecek_kontrol_tarihi IS NOT NULL AND ({$alias}.gelecek_kontrol_tarihi IS NULL OR pk_newer.gelecek_kontrol_tarihi > {$alias}.gelecek_kontrol_tarihi))
              OR (
                  pk_newer.gelecek_kontrol_tarihi = {$alias}.gelecek_kontrol_tarihi
                  AND pk_newer.son_kontrol_tarihi IS NOT NULL
                  AND ({$alias}.son_kontrol_tarihi IS NULL OR pk_newer.son_kontrol_tarihi > {$alias}.son_kontrol_tarihi)
              )
              OR (
                  pk_newer.gelecek_kontrol_tarihi = {$alias}.gelecek_kontrol_tarihi
                  AND (pk_newer.son_kontrol_tarihi = {$alias}.son_kontrol_tarihi OR (pk_newer.son_kontrol_tarihi IS NULL AND {$alias}.son_kontrol_tarihi IS NULL))
                  AND pk_newer.id > {$alias}.id
              )
          )
    )";
}

private function obsoleteTopraklamaKontrolCondition(string $alias): string
{
    return "{$alias}.cihaz_adi LIKE '%TOPRAKLAMA%'
        AND EXISTS (
            SELECT 1
            FROM periyodik_kontrol pk_panel_newer
            WHERE BINARY pk_panel_newer.ekipman_id = BINARY {$alias}.ekipman_id
              AND pk_panel_newer.id <> {$alias}.id
              AND pk_panel_newer.cihaz_adi NOT LIKE '%TOPRAKLAMA%'
              AND pk_panel_newer.son_kontrol_tarihi IS NOT NULL
              AND {$alias}.son_kontrol_tarihi IS NOT NULL
              AND pk_panel_newer.son_kontrol_tarihi > {$alias}.son_kontrol_tarihi
        )";
}




}
