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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\web\Response;

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
                            'actions' => ['update', 'delete'],
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

        if ($this->request->isPost && $model->load($this->request->post())) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ($model->save()) {
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

    private function syncGeneratedPlanliBakimKayitlari(BakimTakip $bakim): void
    {
        $periyot = $this->normalizeBakimPeriyoduToPlanli((string)$bakim->PERIYODIK_PLANLI);
        if ($periyot === null || empty($bakim->TARIH)) {
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
