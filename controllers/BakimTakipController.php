<?php

namespace app\controllers;

use app\models\BakimTakip;
use app\models\BakimTakipPlanli;
use app\models\BakimTakipSearch;
use app\models\Ekipman;
use app\models\PlanliBakim;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * BakimTakipController implements the CRUD actions for BakimTakip model.
 */
class BakimTakipController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => \yii\filters\AccessControl::class,
                    'rules' => [
                        [
                            'actions' => ['index'],
                            'allow' => true, // Herkes (Misafir + Üye)
                        ],
                        [
                            'actions' => ['export-excel'],
                            'allow' => true,
                            'roles' => ['@'],
                            'matchCallback' => function ($rule, $action) {
                                return in_array(\Yii::$app->user->identity->role, ['admin','editor']);
                            }
                        ],
                        [
                            'actions' => ['view', 'ekipmanlar'],
                            'allow' => true, // Herkes (Misafir + Üye)
                        ],
                        [
                            'actions' => ['create'],
                            'allow' => true,
                            'roles' => ['@'], // Giriş yapmış kullanıcılar
                        ],
                        [
                            'actions' => ['update', 'delete', 'toplu-aktar'],
                            'allow' => true,
                            'roles' => ['@'],
                            'matchCallback' => function ($rule, $action) {
                                // Sadece admin rolü olanlar
                                return \Yii::$app->user->identity->role === 'admin';
                            }
                        ],
                    ],
                ],
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                        'toplu-aktar' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all BakimTakip models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new BakimTakipSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Exports BakimTakip list to Excel (XLSX).
     */
    public function actionExportExcel()
    {
        $searchModel = new BakimTakipSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);
        // Tüm kayıtları almak için sayfalama kapat
        $dataProvider->pagination = false;
        // Excel çıktısında eski kayıttan yeniye doğru sırala
        if ($dataProvider->sort !== false) {
            $dataProvider->sort->defaultOrder = [
                'TARIH' => SORT_ASC,
                'id' => SORT_ASC,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Başlıklar
        $headers = [
            'BAKIM_GENEL',
            'PERIYODIK_PLANLI',
            'TARIH',
            'BAKIM_SURESI_SAAT',
            'YERI',
            'SISTEM_CIHAZ_OZELLIK',
            'YAPILAN_IS',
            'ISI_YAPANLAR',
        ];

        // Basitlik ve uyumluluk için doğrudan sütun harfleri ile yazalım
        $columnLetters = ['A','B','C','D','E','F','G','H'];

        // Başlık satırı
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columnLetters[$index] . '1', $header);
        }

        // Veri satırları
        $row = 2;
        foreach ($dataProvider->getModels() as $model) {
            $sheet->setCellValue('A' . $row, $model->BAKIM_GENEL);
            $sheet->setCellValue('B' . $row, $model->PERIYODIK_PLANLI);
            $sheet->setCellValue('C' . $row, \Yii::$app->formatter->asDate($model->TARIH, 'php:d.m.Y'));
            $sheet->setCellValue('D' . $row, $model->BAKIM_SURESI_SAAT);
            $sheet->setCellValue('E' . $row, $model->YERI);
            $sheet->setCellValue('F' . $row, $model->SISTEM_CIHAZ_OZELLIK);
            $sheet->setCellValue('G' . $row, $model->YAPILAN_IS);
            $isiYapanlar = is_array($model->ISI_YAPANLAR) ? implode(', ', $model->ISI_YAPANLAR) : $model->ISI_YAPANLAR;
            $sheet->setCellValue('H' . $row, $isiYapanlar);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        $filename = 'bakim_takip_' . date('Ymd_His') . '.xlsx';

        // Runtime'a yaz ve dosyadan gönder (barındırıcı izinlerine daha uygun)
        $path = \Yii::getAlias('@runtime') . '/' . $filename;
        $writer->save($path);

        \Yii::$app->response->format = Response::FORMAT_RAW;
        $response = \Yii::$app->response->sendFile(
            $path,
            $filename,
            [
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'inline' => false,
            ]
        );

        // Gönderimden sonra dosyayı sil
        $response->on(Response::EVENT_AFTER_SEND, function() use ($path) {
            @unlink($path);
        });

        return $response;
    }

    public function actionTopluAktar()
    {
        $file = UploadedFile::getInstanceByName('bakim_excel');
        if ($file === null) {
            Yii::$app->session->setFlash('error', 'Lütfen Excel veya CSV dosyası seçiniz.');
            return $this->redirect(['index']);
        }

        $extension = strtolower((string)$file->extension);
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            Yii::$app->session->setFlash('error', 'Sadece .xlsx, .xls veya .csv dosyası yüklenebilir.');
            return $this->redirect(['index']);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setDelimiter($this->detectBakimCsvDelimiter($file->tempName));
                $reader->setInputEncoding('UTF-8');
                $spreadsheet = $reader->load($file->tempName);
            } else {
                $spreadsheet = IOFactory::load($file->tempName);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $rows = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, true);

            $headerRowNumber = $this->findBakimImportHeaderRow($rows);
            if ($headerRowNumber === null) {
                throw new \RuntimeException('Başlık satırı bulunamadı. En az Tarih veya Yapılan İş başlığı olmalı.');
            }

            $columnMap = $this->buildBakimImportColumnMap($rows[$headerRowNumber]);
            $created = 0;
            $skipped = 0;
            $errors = [];

            for ($rowNumber = $headerRowNumber + 1; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $rows[$rowNumber] ?? [];
                if ($this->isBakimImportRowEmpty($row)) {
                    continue;
                }

                $model = new BakimTakip();
                foreach (['BAKIM_GENEL', 'PERIYODIK_PLANLI', 'YERI', 'SISTEM_CIHAZ_OZELLIK', 'YAPILAN_IS', 'ISI_YAPANLAR'] as $attribute) {
                    $value = $this->getBakimImportCellValue($row, $columnMap, $attribute);
                    if ($value !== null) {
                        $model->$attribute = trim((string)$value);
                    }
                }

                $model->TARIH = $this->normalizeBakimImportDate($this->getBakimImportCellValue($row, $columnMap, 'TARIH'));
                $sure = trim((string)$this->getBakimImportCellValue($row, $columnMap, 'BAKIM_SURESI_SAAT'));
                $model->BAKIM_SURESI_SAAT = $sure === '' ? 0 : str_replace(',', '.', $sure);
                $model->ekipmanIds = $this->parseBakimImportEkipmanIds($this->getBakimImportCellValue($row, $columnMap, 'ekipmanIds'));
                if (empty($model->ekipmanIds)) {
                    $model->ekipmanIds = $this->extractBakimImportEkipmanIdsFromText((string)$model->SISTEM_CIHAZ_OZELLIK);
                }

                if (trim((string)$model->TARIH) === '' && trim((string)$model->YAPILAN_IS) === '') {
                    $skipped++;
                    $errors[] = $rowNumber . '. satır atlandı: Tarih veya Yapılan İş boş.';
                    continue;
                }

                if (!$model->save()) {
                    $skipped++;
                    $message = implode(' | ', array_map(static function ($items) {
                        return implode(', ', $items);
                    }, $model->getErrors()));
                    $errors[] = $rowNumber . '. satır kaydedilemedi: ' . $message;
                    continue;
                }

                $this->syncGeneratedPlanliBakimKayitlari($model);
                $created++;
            }

            $transaction->commit();

            $message = "Toplu bakım aktarımı tamamlandı. Yeni: {$created}, hatalı atlanan: {$skipped}.";
            if (!empty($errors)) {
                $message .= '<br>İlk uyarılar:<br>' . implode('<br>', array_slice(array_map('htmlspecialchars', $errors), 0, 10));
            }
            Yii::$app->session->setFlash($skipped > 0 ? 'warning' : 'success', $message);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Toplu bakım aktarımı sırasında hata oluştu: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    /**
     * Displays a single BakimTakip model.
     * @param int $id S.No
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionEkipmanlar($id)
    {
        $model = $this->findModel($id);
        $ekipmanIds = array_values(array_filter((array)$model->ekipmanIds));

        $dataProvider = new ActiveDataProvider([
            'query' => Ekipman::find()
                ->where(['id' => $ekipmanIds])
                ->orderBy(['id' => SORT_ASC]),
            'pagination' => false,
            'sort' => false,
        ]);

        return $this->render('ekipmanlar', [
            'model' => $model,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Creates a new BakimTakip model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new BakimTakip();
        $planliId = (int)$this->request->get('planli_id', 0);

        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if ($model->save()) {
                        $this->syncGeneratedPlanliBakimKayitlari($model);

                        if ($planliId > 0 && PlanliBakim::find()->where(['id' => $planliId])->exists()) {
                            $linkVarMi = BakimTakipPlanli::find()
                                ->where(['bakim_id' => (int)$model->id, 'planli_id' => $planliId])
                                ->exists();

                            if (!$linkVarMi) {
                                $link = new BakimTakipPlanli();
                                $link->bakim_id = (int)$model->id;
                                $link->planli_id = $planliId;
                                $link->link_type = BakimTakipPlanli::TYPE_SOURCE;
                                $link->created_at = date('Y-m-d H:i:s');
                                $link->save(false);
                            }
                        }

                        $transaction->commit();
                        return $this->redirect(['view', 'id' => $model->id]);
                    }

                    $transaction->rollBack();
                } catch (\Throwable $e) {
                    $transaction->rollBack();
                    Yii::$app->session->setFlash('error', 'Bakım kaydı planlı bakıma işlenemedi: ' . $e->getMessage());
                }
            }
        } else {
            $model->loadDefaultValues();

            // Ana sayfadaki "Bakım Yap" butonundan gelen ön değerler
            $ekipmanId = $this->request->get('ekipman_id');
            $planliId = $this->request->get('planli_id');

            if ($ekipmanId) {
                $model->ekipmanIds = [(string)$ekipmanId];
            }

            if ($planliId) {
                $planli = PlanliBakim::findOne($planliId);
                if ($planli) {
                // Periyodik/planlı bilgisini ve başlık niteliğini otomatik doldur
                $model->PERIYODIK_PLANLI = $planli->periyodu;
                $model->BAKIM_GENEL = 'Planlı Bakım: ' . $planli->tanimi;
                // Varsayılan tarih: bugün
                $model->TARIH = date('Y-m-d');
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing BakimTakip model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id S.No
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $this->hydrateBakimFromGeneratedPlanliLinks($model);
        $oldEkipmanIds = array_values(array_filter((array)$model->ekipmanIds, static fn($value): bool => $value !== '' && $value !== null));
        $oldPeriyot = (string)$model->PERIYODIK_PLANLI;
        $oldTarih = (string)$model->TARIH;

        if ($this->request->isPost && $model->load($this->request->post())) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($model->save()) {
                    $this->attachLegacyGeneratedPlanliBakimLinks($model, $oldEkipmanIds, $oldPeriyot, $oldTarih);
                    $this->syncGeneratedPlanliBakimKayitlari($model);
                    $transaction->commit();
                    return $this->redirect(['view', 'id' => $model->id]);
                }
                $transaction->rollBack();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'Bakım revizyonu planlı bakıma yansıtılamadı: ' . $e->getMessage());
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing BakimTakip model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id S.No
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $ekipmanIds = array_values(array_unique(array_filter((array)$model->ekipmanIds, static fn($v): bool => $v !== '' && $v !== null)));
        $periyot = $this->normalizeBakimPeriyoduToPlanli((string)$model->PERIYODIK_PLANLI);
        $tarih = (string)$model->TARIH;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $linkler = BakimTakipPlanli::find()
                ->where(['bakim_id' => (int)$model->id, 'link_type' => BakimTakipPlanli::TYPE_GENERATED])
                ->all();

            foreach ($linkler as $link) {
                PlanliBakim::deleteAll(['id' => (int)$link->planli_id]);
            }

            // Geçmiş kayıtlar link tablosuna yazılmamış olabilir.
            // Bu durumda aynı bakımın üreteceği planlı satırları kodu+tarih+periyot ile temizle.
            if (empty($linkler) && $periyot !== null && $tarih !== '' && !empty($ekipmanIds)) {
                PlanliBakim::deleteAll([
                    'and',
                    ['kodu' => $ekipmanIds],
                    ['periyodu' => $periyot],
                    ['tarihi' => $tarih],
                    ['<>', 'durumu', PlanliBakim::DURUM_OTELEME],
                ]);
            }

            $model->delete();

            $transaction->commit();
            Yii::$app->session->setFlash('success', 'Bakım kaydı silindi. Bağlı planlı bakım kayıtları da temizlendi.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Silme sırasında hata oluştu: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    private function attachLegacyGeneratedPlanliBakimLinks(BakimTakip $bakim, array $oldEkipmanIds, string $oldPeriyot, string $oldTarih): void
    {
        $currentPeriyot = $this->normalizeBakimPeriyoduToPlanli((string)$bakim->PERIYODIK_PLANLI);
        if ($currentPeriyot === null || empty($bakim->TARIH)) {
            return;
        }

        $currentEkipmanIds = array_values(array_unique(array_filter((array)$bakim->ekipmanIds, static fn($id): bool => $id !== '' && $id !== null)));
        if (empty($currentEkipmanIds)) {
            return;
        }

        $oldPlanliPeriyot = $this->normalizeBakimPeriyoduToPlanli($oldPeriyot);
        $linkedPlanliIds = BakimTakipPlanli::find()
            ->select('planli_id')
            ->where(['bakim_id' => (int)$bakim->id, 'link_type' => BakimTakipPlanli::TYPE_GENERATED])
            ->column();

        $linkedKodu = [];
        if (!empty($linkedPlanliIds)) {
            $linkedKodu = PlanliBakim::find()
                ->select('kodu')
                ->where(['id' => $linkedPlanliIds])
                ->column();
            $linkedKodu = array_fill_keys(array_map('strval', $linkedKodu), true);
        }

        foreach ($currentEkipmanIds as $ekipmanId) {
            $ekipmanId = (string)$ekipmanId;
            if (isset($linkedKodu[$ekipmanId])) {
                continue;
            }

            $candidate = null;
            if ($oldPlanliPeriyot !== null && $oldTarih !== '' && in_array($ekipmanId, array_map('strval', $oldEkipmanIds), true)) {
                $candidate = PlanliBakim::find()
                    ->where([
                        'kodu' => $ekipmanId,
                        'periyodu' => $oldPlanliPeriyot,
                        'tarihi' => $oldTarih,
                    ])
                    ->andWhere(['<>', 'durumu', PlanliBakim::DURUM_OTELEME])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
            }

            if ($candidate === null) {
                $maxTarih = date('Y-m-d', strtotime((string)$bakim->TARIH . ' +7 days'));
                $candidate = PlanliBakim::find()
                    ->where([
                        'kodu' => $ekipmanId,
                        'periyodu' => $currentPeriyot,
                        'durumu' => 'Plan sonrası',
                    ])
                    ->andWhere(['>', 'tarihi', (string)$bakim->TARIH])
                    ->andWhere(['<=', 'tarihi', $maxTarih])
                    ->orderBy(['tarihi' => SORT_ASC, 'id' => SORT_DESC])
                    ->one();
            }

            if ($candidate === null) {
                continue;
            }

            $alreadyLinked = BakimTakipPlanli::find()
                ->where(['bakim_id' => (int)$bakim->id, 'planli_id' => (int)$candidate->id])
                ->exists();
            if ($alreadyLinked) {
                continue;
            }

            $link = new BakimTakipPlanli();
            $link->bakim_id = (int)$bakim->id;
            $link->planli_id = (int)$candidate->id;
            $link->link_type = BakimTakipPlanli::TYPE_GENERATED;
            $link->created_at = date('Y-m-d H:i:s');

            if (!$link->save()) {
                $hata = implode(' | ', array_map(static function ($errors) {
                    return implode(', ', $errors);
                }, $link->getErrors()));
                throw new \RuntimeException('Eski planlı bakım bağlantısı onarılamadı (ekipman: ' . $ekipmanId . '): ' . $hata);
            }
        }
    }

    private function hydrateBakimFromGeneratedPlanliLinks(BakimTakip $bakim): void
    {
        $planliRows = PlanliBakim::find()
            ->alias('pb')
            ->innerJoin(['btp' => BakimTakipPlanli::tableName()], 'btp.planli_id = pb.id')
            ->where([
                'btp.bakim_id' => (int)$bakim->id,
                'btp.link_type' => BakimTakipPlanli::TYPE_GENERATED,
            ])
            ->orderBy(['pb.kodu' => SORT_ASC])
            ->all();

        if (empty($planliRows)) {
            return;
        }

        if (empty(array_filter((array)$bakim->ekipmanIds, static fn($id): bool => $id !== '' && $id !== null))) {
            $bakim->ekipmanIds = array_values(array_unique(array_map(static function (PlanliBakim $planli): string {
                return (string)$planli->kodu;
            }, $planliRows)));
        }

        if (trim((string)$bakim->PERIYODIK_PLANLI) === '') {
            $periyotlar = array_values(array_unique(array_filter(array_map(static function (PlanliBakim $planli): string {
                return trim((string)$planli->periyodu);
            }, $planliRows))));

            if (count($periyotlar) === 1) {
                $bakim->PERIYODIK_PLANLI = $this->normalizePlanliPeriyotForBakimForm($periyotlar[0]);
            } elseif (count($periyotlar) > 1) {
                $bakim->PERIYODIK_PLANLI = 'PLANLI TOPLU BAKIM';
            }
        }
    }

    private function normalizePlanliPeriyotForBakimForm(string $periyot): string
    {
        $periyot = trim($periyot);
        if ($periyot === '') {
            return '';
        }

        return preg_replace('/^Periyodik\s*:/iu', 'PLANLI:', $periyot);
    }

    private function syncGeneratedPlanliBakimKayitlari(BakimTakip $bakim): void
    {
        $periyot = $this->normalizeBakimPeriyoduToPlanli((string)$bakim->PERIYODIK_PLANLI);
        if ($periyot === null || empty($bakim->TARIH)) {
            if (!empty($bakim->TARIH)) {
                $this->syncLinkedGeneratedPlanliDatesOnly($bakim);
            }
            return;
        }

        $ekipmanIds = array_values(array_unique(array_filter((array)$bakim->ekipmanIds, static fn($id): bool => $id !== '' && $id !== null)));
        if (empty($ekipmanIds)) {
            return;
        }

        $ekipmanlar = Ekipman::find()
            ->where(['id' => $ekipmanIds])
            ->indexBy('id')
            ->all();

        $linkler = BakimTakipPlanli::find()
            ->where(['bakim_id' => (int)$bakim->id, 'link_type' => BakimTakipPlanli::TYPE_GENERATED])
            ->all();

        $koduyaGorePlanli = [];
        foreach ($linkler as $link) {
            $planli = PlanliBakim::findOne((int)$link->planli_id);
            if ($planli === null) {
                $link->delete();
                continue;
            }

            $kodu = (string)$planli->kodu;
            if ($kodu === '') {
                continue;
            }

            $koduyaGorePlanli[$kodu] = $planli;
        }

        // Eksik olan ekipmanlar için planlı bakım satırı oluştur.
        // Daha önce hiç planlı kaydı yoksa model kuralı gereği durumu "İlk Bakım" olur.
        foreach ($ekipmanIds as $ekipmanId) {
            $ekipmanId = (string)$ekipmanId;
            if (isset($koduyaGorePlanli[$ekipmanId])) {
                continue;
            }

            $ekipman = $ekipmanlar[$ekipmanId] ?? null;

            // Aynı (kodu, periyodu) için daha önce oluşturulmuş planlibakim kaydı varsa
            // onun tanımını kullan. Yoksa MALZEMENIN_TANIMI'na düş.
            $mevcutPlanli = PlanliBakim::find()
                ->where(['kodu' => $ekipmanId, 'periyodu' => $periyot])
                ->andWhere(['<>', 'durumu', PlanliBakim::DURUM_OTELEME])
                ->orderBy(['id' => SORT_DESC])
                ->one();

            $planli = new PlanliBakim();
            $planli->kodu = $ekipmanId;
            $planli->tanimi = $mevcutPlanli !== null
                ? trim((string)$mevcutPlanli->tanimi)
                : trim((string)($ekipman?->MALZEMENIN_TANIMI ?: $ekipmanId));
            $planli->periyodu = $periyot;
            $planli->tarihi = $bakim->TARIH;

            if (!$planli->save()) {
                $hata = implode(' | ', array_map(static function ($errors) {
                    return implode(', ', $errors);
                }, $planli->getErrors()));
                throw new \RuntimeException('Planlı bakım oluşturulamadı (ekipman: ' . $ekipmanId . '): ' . $hata);
            }

            $link = new BakimTakipPlanli();
            $link->bakim_id = (int)$bakim->id;
            $link->planli_id = (int)$planli->id;
            $link->link_type = BakimTakipPlanli::TYPE_GENERATED;
            $link->created_at = date('Y-m-d H:i:s');
            if (!$link->save()) {
                $hata = implode(' | ', array_map(static function ($errors) {
                    return implode(', ', $errors);
                }, $link->getErrors()));
                throw new \RuntimeException('Bakım-planlı bağlantısı oluşturulamadı (ekipman: ' . $ekipmanId . '): ' . $hata);
            }

            $koduyaGorePlanli[$ekipmanId] = $planli;
        }

        // Seçimden çıkarılan ekipmanların bu bakımdan üretilmiş planlı kaydını temizle.
        foreach ($koduyaGorePlanli as $kodu => $planli) {
            if (in_array((string)$kodu, $ekipmanIds, true)) {
                continue;
            }

            BakimTakipPlanli::deleteAll([
                'bakim_id' => (int)$bakim->id,
                'planli_id' => (int)$planli->id,
                'link_type' => BakimTakipPlanli::TYPE_GENERATED,
            ]);
            PlanliBakim::deleteAll(['id' => (int)$planli->id]);
            unset($koduyaGorePlanli[$kodu]);
        }

        // Mevcut bağlı planlı kayıtları bakım revizyonuna göre güncelle.
        foreach ($koduyaGorePlanli as $kodu => $planli) {
            $ekipman = $ekipmanlar[$kodu] ?? null;
            $planli->tanimi = trim((string)($ekipman?->MALZEMENIN_TANIMI ?: $kodu));
            $planli->periyodu = $periyot;
            $planli->tarihi = $bakim->TARIH;

            if (!$planli->save()) {
                $hata = implode(' | ', array_map(static function ($errors) {
                    return implode(', ', $errors);
                }, $planli->getErrors()));
                throw new \RuntimeException('Planlı bakım güncellenemedi (ID: ' . (int)$planli->id . '): ' . $hata);
            }
        }
    }

    private function syncLinkedGeneratedPlanliDatesOnly(BakimTakip $bakim): void
    {
        $planliRows = PlanliBakim::find()
            ->alias('pb')
            ->innerJoin(['btp' => BakimTakipPlanli::tableName()], 'btp.planli_id = pb.id')
            ->where([
                'btp.bakim_id' => (int)$bakim->id,
                'btp.link_type' => BakimTakipPlanli::TYPE_GENERATED,
            ])
            ->andWhere(['<>', 'pb.durumu', PlanliBakim::DURUM_OTELEME])
            ->all();

        foreach ($planliRows as $planli) {
            $planli->tarihi = $bakim->TARIH;
            if (!$planli->save()) {
                $hata = implode(' | ', array_map(static function ($errors) {
                    return implode(', ', $errors);
                }, $planli->getErrors()));
                throw new \RuntimeException('Planlı bakım tarihi güncellenemedi (ID: ' . (int)$planli->id . '): ' . $hata);
            }
        }
    }

    private function findBakimImportHeaderRow(array $rows): ?int
    {
        foreach ($rows as $rowNumber => $row) {
            $map = $this->buildBakimImportColumnMap($row);
            if (!empty($map['TARIH']) || !empty($map['YAPILAN_IS'])) {
                return (int)$rowNumber;
            }
        }

        return null;
    }

    private function buildBakimImportColumnMap(array $headerRow): array
    {
        $aliases = [
            'BAKIM_GENEL' => ['bakim genel', 'bakım genel'],
            'PERIYODIK_PLANLI' => ['periyodik planli', 'periyodik planlı', 'planli', 'planlı'],
            'TARIH' => ['tarih', 'bakim tarihi', 'bakım tarihi'],
            'BAKIM_SURESI_SAAT' => ['bakim suresi saat', 'bakım süresi saat', 'bakim suresi', 'bakım süresi', 'sure', 'süre'],
            'YERI' => ['yeri', 'yer'],
            'SISTEM_CIHAZ_OZELLIK' => ['sistem cihaz ozellik', 'sistem cihaz özellik', 'sistem/cihaz ozellik', 'sistem/cihaz özellik', 'ekipman', 'cihaz'],
            'YAPILAN_IS' => ['yapilan is', 'yapılan iş', 'is', 'iş'],
            'ISI_YAPANLAR' => ['isi yapanlar', 'işi yapanlar', 'isi yapanlarin adi soyadi', 'işi yapanların adı soyadı', 'yapanlar'],
            'ekipmanIds' => ['ekipman id', 'ekipman ids', 'ekipman kodu', 'ekipman kodlari', 'ekipman kodları'],
        ];

        $normalizedAliases = [];
        foreach ($aliases as $attribute => $labels) {
            foreach ($labels as $label) {
                $normalizedAliases[] = ['attribute' => $attribute, 'label' => $this->normalizeBakimImportHeader($label)];
            }
        }

        usort($normalizedAliases, static fn($a, $b): int => strlen($b['label']) <=> strlen($a['label']));

        $map = [];
        foreach ($headerRow as $column => $label) {
            $normalizedLabel = $this->normalizeBakimImportHeader((string)$label);
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
                if (strlen($candidate['label']) > 3 && preg_match('/(^| )' . preg_quote($candidate['label'], '/') . '( |$)/', $normalizedLabel)) {
                    $map[$candidate['attribute']] = $column;
                    continue 2;
                }
            }
        }

        return $map;
    }

    private function normalizeBakimImportHeader(string $value): string
    {
        $value = strtr($value, [
            'İ' => 'I', 'I' => 'I', 'Ğ' => 'G', 'Ü' => 'U', 'Ş' => 'S', 'Ö' => 'O', 'Ç' => 'C',
            'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c',
        ]);
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim((string)$value);
    }

    private function detectBakimCsvDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'rb');
        $line = $handle ? (string)fgets($handle) : '';
        if ($handle) {
            fclose($handle);
        }

        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($delimiters);

        return (string)array_key_first($delimiters);
    }

    private function isBakimImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function getBakimImportCellValue(array $row, array $columnMap, string $attribute)
    {
        if (empty($columnMap[$attribute])) {
            return null;
        }

        return $row[$columnMap[$attribute]] ?? null;
    }

    private function normalizeBakimImportDate($value): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return SpreadsheetDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
        }

        $text = trim((string)$value);
        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $text);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($text);
        return $timestamp === false ? $text : date('Y-m-d', $timestamp);
    }

    private function parseBakimImportEkipmanIds($value): array
    {
        if ($value === null) {
            return [];
        }

        $parts = preg_split('/[;,\n\r\t]+/u', (string)$value) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $id = trim($part);
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function extractBakimImportEkipmanIdsFromText(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        preg_match_all('/[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+/', $text, $matches);
        $candidates = array_values(array_unique(array_map('trim', $matches[0] ?? [])));
        if (empty($candidates)) {
            return [];
        }

        return Ekipman::find()
            ->select('id')
            ->where(['id' => $candidates])
            ->orderBy(['id' => SORT_ASC])
            ->column();
    }

    private function normalizeBakimPeriyoduToPlanli(string $value): ?string
    {
        $metin = mb_strtolower(trim($value), 'UTF-8');
        if ($metin === '') {
            return null;
        }

        if (str_contains($metin, '1 ay')) {
            return 'Periyodik: 1 Ay';
        }
        if (str_contains($metin, '3 ay')) {
            return 'Periyodik: 3 Ay';
        }
        if (str_contains($metin, '6 ay')) {
            return 'Periyodik: 6 Ay';
        }
        if (str_contains($metin, '1 yıl') || str_contains($metin, '1 yil')) {
            return 'Periyodik: 1 Yıl';
        }

        return null;
    }

    /**
     * Finds the BakimTakip model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id S.No
     * @return BakimTakip the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BakimTakip::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
