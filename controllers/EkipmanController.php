<?php

namespace app\controllers;

use Yii;
use app\models\Ekipman;
use app\models\EkipmanDokuman;
use app\models\PlanliBakim;
use app\models\PeriyodikKontrol;
use app\models\BakimTakip;
use app\models\ArizaTakip;
use yii\data\ArrayDataProvider;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\Response;
use yii\web\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class EkipmanController extends Controller
{
    
    public function behaviors()
{
    return [
        'access' => [
            'class' => \yii\filters\AccessControl::class,
            'only' => ['create','update','delete','hurdaya-ayir','aktife-al','dokuman-ekle','dokuman-sil','tanitim-foto-yukle','tanitim-foto-sil','enerji-kaynagi-aktar'],
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@'], // sadece login olan
                    'actions' => ['create', 'update', 'delete'],
                ],
                [
                    'allow' => true,
                    'roles' => ['@'],
                    'actions' => ['hurdaya-ayir', 'aktife-al', 'dokuman-ekle', 'dokuman-sil', 'tanitim-foto-yukle', 'tanitim-foto-sil', 'enerji-kaynagi-aktar'],
                    'matchCallback' => function () {
                        return !Yii::$app->user->isGuest
                            && in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true);
                    },
                ],
            ],
        ],
        'verbs' => [
            'class' => \yii\filters\VerbFilter::class,
            'actions' => [
                'delete' => ['post'],
                'hurdaya-ayir' => ['post'],
                'aktife-al' => ['post'],
                'dokuman-ekle' => ['post'],
                'dokuman-sil' => ['post'],
                'tanitim-foto-yukle' => ['post'],
                'tanitim-foto-sil' => ['post'],
                'enerji-kaynagi-aktar' => ['post'],
            ],
        ],
    ];
}

    
    public function actionIndex()
{
    $searchModel = new \app\models\EkipmanSearch();
    $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

    return $this->render('index', [
        'searchModel' => $searchModel,
        'dataProvider' => $dataProvider,
    ]);
}

    public function actionExportExcel()
{
    $models = Ekipman::find()->all();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->fromArray(array_keys($models[0]->attributes), NULL, 'A1');

    $row = 2;
    foreach ($models as $m) {
        $sheet->fromArray($m->attributes, NULL, 'A' . $row);
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    $filename = 'ekipman_listesi.xlsx';
    $path = Yii::getAlias('@webroot') . '/' . $filename;

    $writer->save($path);
    return Yii::$app->response->sendFile($path);
}
public function actionExportPdf()
{
    $models = Ekipman::find()->all();
    $html = $this->renderPartial('_pdf', ['models' => $models]);
    $pdf = new \Mpdf\Mpdf(['default_font' => 'dejavusans']);
    $pdf->WriteHTML($html);
    $pdf->Output('ekipman_listesi.pdf', 'D');
}


    public function actionView($id)
    {
        $model = $this->findModel($id);

        $planliBakimDataProvider = new ActiveDataProvider([
            'query' => PlanliBakim::find()
                ->where(['kodu' => $model->id])
                ->orderBy(['tarihi' => SORT_DESC]),
            'pagination' => false, // tüm kayıtlar, sayfalama yok
            'sort' => false,
        ]);

        // Bu ekipman için periyot bazlı son + sonraki bakım tarihleri
        $nextBakimlar = PlanliBakim::getNextDueDatesByPeriodForEkipman($model->id);

        $periyodikKontrolDataProvider = new ActiveDataProvider([
            'query' => PeriyodikKontrol::find()
                ->where(['ekipman_id' => $model->id])
                ->orderBy(['gelecek_kontrol_tarihi' => SORT_ASC]),
            'pagination' => false,
            'sort' => false,
        ]);

        $bakimTakipDataProvider = new ActiveDataProvider([
            'query' => BakimTakip::find()
                ->alias('b')
                ->innerJoin('bakim_takip_ekipman bte', 'bte.bakim_id = b.id')
                ->where(['bte.ekipman_id' => (string)$model->id])
                ->orderBy(['b.TARIH' => SORT_DESC, 'b.id' => SORT_DESC]),
            'pagination' => false,
            'sort' => false,
        ]);

        $arizaTakipDataProvider = new ActiveDataProvider([
            'query' => ArizaTakip::find()
                ->where(['ARIZALANAN_MAKINE_KODU' => (string)$model->id])
                ->orderBy(['ARIZA_TARIHI' => SORT_DESC, 'id' => SORT_DESC]),
            'pagination' => false,
            'sort' => false,
        ]);

        $nextPeriyodikKontrol = PeriyodikKontrol::find()
            ->where(['ekipman_id' => $model->id])
            ->andWhere(['IS NOT', 'gelecek_kontrol_tarihi', null])
            ->orderBy(['gelecek_kontrol_tarihi' => SORT_ASC])
            ->one();

        $dokumanlar = EkipmanDokuman::find()
            ->where(['ekipman_kodu' => $model->id])
            ->orderBy(['dokuman_turu' => SORT_ASC, 'dokuman_adi' => SORT_ASC])
            ->all();

        $bakimDokumanlari = array_values(array_filter($dokumanlar, function ($doc) {
            return in_array($doc->dokuman_turu, ['BAKIM FORMU', 'BAKIM TALİMATI'], true);
        }));

        $teknikDokumanlar = array_values(array_filter($dokumanlar, function ($doc) {
            return in_array($doc->dokuman_turu, ['ELEKTRİK PROJESİ', 'KULLANMA KLAVUZU', 'BROŞÜR'], true);
        }));

        $teknikDosyaSecenekleri = $this->getTeknikDosyaSecenekleri();

        $analizorConfig = self::getAnalizorConfig($model->id);

        return $this->render('view', [
            'model' => $model,
            'planliBakimDataProvider' => $planliBakimDataProvider,
            'nextBakimlar' => $nextBakimlar,
            'periyodikKontrolDataProvider' => $periyodikKontrolDataProvider,
            'bakimTakipDataProvider' => $bakimTakipDataProvider,
            'arizaTakipDataProvider' => $arizaTakipDataProvider,
            'nextPeriyodikKontrol' => $nextPeriyodikKontrol,
            'bakimDokumanlari' => $bakimDokumanlari,
            'teknikDokumanlar' => $teknikDokumanlar,
            'teknikDosyaSecenekleri' => $teknikDosyaSecenekleri,
            'analizorConfig' => $analizorConfig,
        ]);
    }

    public function actionTanitimFotoYukle($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->user->isGuest || !in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true)) {
            throw new ForbiddenHttpException('Bu işlem için yetkiniz yok.');
        }

        $uploadedFile = UploadedFile::getInstanceByName('tanitim_foto');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Yüklenecek bir fotoğraf seçilmedi.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower((string)$uploadedFile->extension);
        if (!in_array($extension, $allowedExtensions, true)) {
            Yii::$app->session->setFlash('error', 'Sadece JPG, JPEG, PNG veya WEBP formatında fotoğraf yükleyebilirsiniz.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        if ($uploadedFile->size > 10 * 1024 * 1024) {
            Yii::$app->session->setFlash('error', 'Fotoğraf boyutu 10 MB sınırını aşamaz.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        $uploadDir = Yii::getAlias('@app/web/uploads/ekipman-tanitim');
        FileHelper::createDirectory($uploadDir);

        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$model->id);
        $fileName = $safeId . '_' . date('Ymd_His') . '.' . $extension;
        $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = 'ekipman-tanitim/' . $fileName;

        if (!$uploadedFile->saveAs($absolutePath, false)) {
            Yii::$app->session->setFlash('error', 'Fotoğraf dosyası kaydedilemedi.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        if (@getimagesize($absolutePath) === false) {
            @unlink($absolutePath);
            Yii::$app->session->setFlash('error', 'Yüklenen dosya geçerli bir görsel değil.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        $oldRelativePath = (string)$model->TANITIM_FOTO;
        $model->TANITIM_FOTO = $relativePath;
        if (!$model->save(false)) {
            @unlink($absolutePath);
            Yii::$app->session->setFlash('error', 'Fotoğraf bilgisi kaydedilemedi.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        $this->deleteTanitimFotoFile($oldRelativePath);
        Yii::$app->session->setFlash('success', 'Tanıtım fotoğrafı yüklendi.');
        return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
    }

    public function actionTanitimFotoSil($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->user->isGuest || !in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true)) {
            throw new ForbiddenHttpException('Bu işlem için yetkiniz yok.');
        }

        $oldRelativePath = (string)$model->TANITIM_FOTO;
        if ($oldRelativePath === '') {
            Yii::$app->session->setFlash('info', 'Silinecek tanıtım fotoğrafı bulunamadı.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
        }

        $model->TANITIM_FOTO = null;
        $model->save(false);
        $this->deleteTanitimFotoFile($oldRelativePath);

        Yii::$app->session->setFlash('success', 'Tanıtım fotoğrafı kaldırıldı.');
        return $this->redirect(['view', 'id' => $model->id, '#' => 'tanitim-foto']);
    }

    public function actionDokumanEkle($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->user->isGuest || Yii::$app->user->identity->role !== 'admin') {
            throw new ForbiddenHttpException('Bu işlem için yetkiniz yok.');
        }

        $dokumanTuru = trim((string)Yii::$app->request->post('dokuman_turu', ''));
        $izinliTurler = ['ELEKTRİK PROJESİ', 'KULLANMA KLAVUZU', 'BROŞÜR', 'TEK HAT ŞEMASI'];
        if (!in_array($dokumanTuru, $izinliTurler, true)) {
            Yii::$app->session->setFlash('error', 'Geçerli bir döküman türü seçiniz.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
        }

        // Tür → hedef klasör eşlemesi
        $turKlasorMap = [
            'ELEKTRİK PROJESİ' => 'teknik-dokumanlar/elektrik-projeleri',
            'KULLANMA KLAVUZU'  => 'teknik-dokumanlar/kullanma-klavuzlari',
            'BROŞÜR'            => 'teknik-dokumanlar/brosurler',
            'TEK HAT ŞEMASI'   => 'teknik-dokumanlar/svg',
        ];

        $uploadedFile = \yii\web\UploadedFile::getInstanceByName('dokuman_dosya');
        $dosyaYolu = trim((string)Yii::$app->request->post('dosya_yolu', ''));

        // SVG dosyası yüklendi mi kontrol et
        $svgFile = \yii\web\UploadedFile::getInstanceByName('svg_dosya');
        if ($svgFile !== null && !$svgFile->hasError) {
            $ext = strtolower($svgFile->extension);
            if ($ext !== 'svg') {
                Yii::$app->session->setFlash('error', 'SVG alanına yalnızca .svg dosyası yüklenebilir.');
                return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
            }
            $hedefKlasor = Yii::getAlias('@app/web/uploads/teknik-dokumanlar/svg');
            $safeFileName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $svgFile->baseName) . '.svg';
            $fullPath = $hedefKlasor . '/' . $safeFileName;
            if ($svgFile->saveAs($fullPath)) {
                $dosyaYoluSvg = 'teknik-dokumanlar/svg/' . $safeFileName;
                $this->saveDokumanRecord($model->id, 'ELEKTRİK PROJESİ', $dosyaYoluSvg);
                Yii::$app->session->setFlash('success', 'SVG tek hat şeması yüklendi.');
            } else {
                Yii::$app->session->setFlash('error', 'SVG dosyası yüklenemedi.');
            }
            return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
        }

        // Bilgisayardan dosya yükleme
        if ($uploadedFile !== null && !$uploadedFile->hasError) {
            $izinliUzantilar = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'svg'];
            $ext = strtolower($uploadedFile->extension);
            if (!in_array($ext, $izinliUzantilar, true)) {
                Yii::$app->session->setFlash('error', 'İzin verilmeyen dosya türü: .' . $ext);
                return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
            }

            // SVG yüklenmişse her zaman svg klasörüne git
            if ($ext === 'svg') {
                $hedefKlasor = 'teknik-dokumanlar/svg';
            } else {
                $hedefKlasor = $turKlasorMap[$dokumanTuru];
            }

            $absKlasor = Yii::getAlias('@app/web/uploads/' . $hedefKlasor);
            if (!is_dir($absKlasor)) {
                \yii\helpers\FileHelper::createDirectory($absKlasor);
            }

            $safeFileName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $uploadedFile->baseName) . '.' . $ext;
            $fullPath = $absKlasor . '/' . $safeFileName;

            // Aynı isimde dosya varsa numaralandır
            $counter = 1;
            while (file_exists($fullPath)) {
                $safeFileName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $uploadedFile->baseName) . '_' . $counter . '.' . $ext;
                $fullPath = $absKlasor . '/' . $safeFileName;
                $counter++;
            }

            if ($uploadedFile->saveAs($fullPath)) {
                $dosyaYolu = $hedefKlasor . '/' . $safeFileName;
                $turForDb = ($ext === 'svg') ? 'ELEKTRİK PROJESİ' : $dokumanTuru;
                $this->saveDokumanRecord($model->id, $turForDb, $dosyaYolu);
                Yii::$app->session->setFlash('success', 'Döküman yüklendi ve ekipmana eklendi.');
            } else {
                Yii::$app->session->setFlash('error', 'Dosya yüklenemedi.');
            }
            return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
        }

        // Sunucu dosya seçme (mevcut davranış)
        if ($dosyaYolu === '') {
            Yii::$app->session->setFlash('error', 'Bir dosya seçiniz veya bilgisayarınızdan yükleyiniz.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
        }

        $dosyaYolu = str_replace('\\', '/', ltrim($dosyaYolu, '/'));
        $izinliPrefixler = ['teknik-dokumanlar/elektrik-projeleri/', 'teknik-dokumanlar/kullanma-klavuzlari/', 'teknik-dokumanlar/brosurler/', 'teknik-dokumanlar/svg/'];
        $gecerli = false;
        foreach ($izinliPrefixler as $p) {
            if (str_starts_with($dosyaYolu, $p)) {
                $gecerli = true;
                break;
            }
        }
        if (!$gecerli) {
            Yii::$app->session->setFlash('error', 'Geçersiz dosya yolu.');
            return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
        }

        $this->saveDokumanRecord($model->id, $dokumanTuru, $dosyaYolu);
        Yii::$app->session->setFlash('success', 'Döküman ekipmana eklendi.');
        return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
    }

    private function saveDokumanRecord(string $ekipmanId, string $dokumanTuru, string $dosyaYolu): void
    {
        $exists = EkipmanDokuman::find()
            ->where([
                'ekipman_kodu' => $ekipmanId,
                'dokuman_turu' => $dokumanTuru,
                'dosya_yolu' => $dosyaYolu,
            ])
            ->exists();

        if ($exists) {
            Yii::$app->session->setFlash('info', 'Bu döküman zaten ekipmana ekli.');
            return;
        }

        $dok = new EkipmanDokuman();
        $dok->ekipman_kodu = $ekipmanId;
        $dok->dokuman_turu = $dokumanTuru;
        $dok->dosya_yolu = $dosyaYolu;
        $dok->dokuman_adi = pathinfo($dosyaYolu, PATHINFO_FILENAME);
        $dok->created_at = date('Y-m-d H:i:s');
        $dok->updated_at = date('Y-m-d H:i:s');
        $dok->save(false);
    }

    public function actionDokumanSil($id, $dokumanId)
    {
        $model = $this->findModel($id);

        if (Yii::$app->user->isGuest || Yii::$app->user->identity->role !== 'admin') {
            throw new ForbiddenHttpException('Bu işlem için yetkiniz yok.');
        }

        $dok = EkipmanDokuman::findOne(['id' => $dokumanId, 'ekipman_kodu' => $model->id]);
        if ($dok) {
            $dok->delete();
            Yii::$app->session->setFlash('success', 'Döküman ekipmandan kaldırıldı.');
        }

        return $this->redirect(['view', 'id' => $model->id, '#' => 'dokumanlar']);
    }

    public function actionCreate()
    {
        $model = new Ekipman();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }
        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    public function actionHurdayaAyir($id)
    {
        $model = $this->findModel($id);
        $model->DURUM = 'HURDA';

        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', 'Ekipman hurdaya ayrıldı.');
        } else {
            Yii::$app->session->setFlash('error', 'Ekipman hurdaya ayrılamadı.');
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionAktifeAl($id)
    {
        $model = $this->findModel($id);
        $model->DURUM = 'AKTIF';

        if ($model->save(false)) {
            Yii::$app->session->setFlash('success', 'Ekipman tekrar aktife alındı.');
        } else {
            Yii::$app->session->setFlash('error', 'Ekipman aktife alınamadı.');
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    protected function findModel($id)
    {
        if (($model = Ekipman::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException("Kayıt bulunamadı.");
    }
    
  public function actionUpdateLocation($id)
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

    if (Yii::$app->user->isGuest || Yii::$app->user->identity->role !== 'admin') {
        return ['success' => false, 'message' => 'Bu işlem için yetkiniz yok.'];
    }

    $model = $this->findModel($id);
    $data = json_decode(Yii::$app->request->getRawBody(), true);

    if (isset($data['ENLEM']) && isset($data['BOYLAM'])) {
        $model->ENLEM = $data['ENLEM'];
        $model->BOYLAM = $data['BOYLAM'];
        if ($model->save(false)) {
            return ['success' => true];
        }
    }

    return ['success' => false, 'message' => 'Veri kaydedilemedi'];
}

    /**
     * CSV ile toplu enerji kaynağı aktarımı.
     * CSV formatı: ekipman_id;enerji_kaynagi_id (noktalı virgül ayraç)
     */
    public function actionEnerjiKaynagiAktar()
    {
        $file = UploadedFile::getInstanceByName('csv_file');
        if ($file === null) {
            Yii::$app->session->setFlash('error', 'Dosya seçilmedi.');
            return $this->redirect(['index']);
        }

        $ext = strtolower($file->extension);
        if (!in_array($ext, ['csv', 'txt'], true)) {
            Yii::$app->session->setFlash('error', 'Sadece CSV veya TXT dosyası yüklenebilir.');
            return $this->redirect(['index']);
        }

        $content = file_get_contents($file->tempName);
        if ($content === false) {
            Yii::$app->session->setFlash('error', 'Dosya okunamadı.');
            return $this->redirect(['index']);
        }

        // BOM temizle
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r?\n/', $content);

        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $lineNo => $line) {
            $line = trim($line);
            if ($line === '' || $lineNo === 0 && preg_match('/^ekipman/i', $line)) {
                continue; // boş satır veya başlık
            }

            // Noktalı virgül veya virgül veya tab ayraç
            $parts = preg_split('/[;\t,]/', $line);
            if (count($parts) < 2) {
                $errors[] = 'Satır ' . ($lineNo + 1) . ': Geçersiz format (' . mb_substr($line, 0, 50) . ')';
                $skipped++;
                continue;
            }

            $ekipmanId = trim($parts[0]);
            $kaynak = trim($parts[1]);
            $salterKodu = isset($parts[2]) ? trim($parts[2]) : null;
            $salterAkim = isset($parts[3]) ? trim($parts[3]) : null;

            // Boş kaynak → ilişkiyi sil
            if ($kaynak === '' || strtolower($kaynak) === 'null' || $kaynak === '-') {
                $kaynak = null;
            }

            // Ekipman var mı?
            $ekipman = Ekipman::findOne($ekipmanId);
            if ($ekipman === null) {
                $errors[] = 'Satır ' . ($lineNo + 1) . ': Ekipman bulunamadı (' . $ekipmanId . ')';
                $skipped++;
                continue;
            }

            // Kaynak varsa, geçerli ekipman mı?
            if ($kaynak !== null) {
                $kaynakEkipman = Ekipman::findOne($kaynak);
                if ($kaynakEkipman === null) {
                    $errors[] = 'Satır ' . ($lineNo + 1) . ': Enerji kaynağı bulunamadı (' . $kaynak . ')';
                    $skipped++;
                    continue;
                }
                // Kendine referans engelle
                if ($kaynak === $ekipmanId) {
                    $errors[] = 'Satır ' . ($lineNo + 1) . ': Ekipman kendine kaynak olamaz (' . $ekipmanId . ')';
                    $skipped++;
                    continue;
                }
            }

            // EkipmanMeta güncelle
            $meta = \app\models\EkipmanMeta::findOne($ekipmanId);
            if ($meta === null) {
                $meta = new \app\models\EkipmanMeta();
                $meta->ekipman_id = $ekipmanId;
            }
            $meta->besleme_kaynagi_id = $kaynak;
            $meta->salter_kodu = ($salterKodu === '' || $salterKodu === null || $salterKodu === '-') ? null : $salterKodu;
            $meta->salter_akim = ($salterAkim === '' || $salterAkim === null || $salterAkim === '-') ? null : $salterAkim;
            if ($meta->save(false)) {
                $updated++;
            } else {
                $errors[] = 'Satır ' . ($lineNo + 1) . ': Kayıt hatası (' . $ekipmanId . ')';
                $skipped++;
            }
        }

        $msg = $updated . ' kayıt güncellendi.';
        if ($skipped > 0) {
            $msg .= ' ' . $skipped . ' satır atlandı.';
        }
        if (!empty($errors)) {
            $msg .= "\n" . implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $msg .= "\n... ve " . (count($errors) - 10) . ' hata daha.';
            }
        }

        if ($skipped > 0) {
            Yii::$app->session->setFlash('warning', nl2br($msg));
        } else {
            Yii::$app->session->setFlash('success', $msg);
        }

        return $this->redirect(['index']);
    }

    private function getTeknikDosyaSecenekleri(): array
    {
        $roots = [
            Yii::getAlias('@app/web/uploads/teknik-dokumanlar/elektrik-projeleri'),
            Yii::getAlias('@app/web/uploads/teknik-dokumanlar/kullanma-klavuzlari'),
            Yii::getAlias('@app/web/uploads/teknik-dokumanlar/brosurler'),
            Yii::getAlias('@app/web/uploads/teknik-dokumanlar/svg'),
        ];

        $result = [];
        $uploadsRoot = str_replace('\\', '/', (string)realpath(Yii::getAlias('@app/web/uploads')));
        if ($uploadsRoot === '') {
            return $result;
        }
        if (!str_ends_with($uploadsRoot, '/')) {
            $uploadsRoot .= '/';
        }

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $files = FileHelper::findFiles($root);
            foreach ($files as $file) {
                $abs = str_replace('\\', '/', (string)realpath($file));
                if ($abs === '' || !str_starts_with($abs, $uploadsRoot)) {
                    continue;
                }

                $rel = ltrim(substr($abs, strlen($uploadsRoot)), '/');
                $result[$rel] = basename($file) . ' (' . $rel . ')';
            }
        }

        asort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * Enerji analizörü canlı verisini JSON olarak döndürür.
     * AJAX ile çağrılır: GET /ekipman/analizor-veri?id=ESNT-ADP-03
     */
    public function actionAnalizorVeri($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $config = require Yii::getAlias('@app/config/analizor.php');
        if (!isset($config[$id])) {
            return ['success' => false, 'message' => 'Bu ekipman için analizör tanımı bulunamadı.'];
        }

        $c = $config[$id];
        $regs = \app\helpers\ModbusHelper::readHoldingRegisters(
            $c['ip'], $c['port'], $c['device_id'], 0, 100, 3
        );

        if ($regs === false) {
            Yii::warning("Analizör bağlantı hatası: {$c['ip']}:{$c['port']} (ekipman: {$id})", __METHOD__);
            return ['success' => false, 'message' => 'Analizöre bağlanılamadı. Cihaz erişilebilir durumda olmayabilir.'];
        }

        $data = \app\helpers\ModbusHelper::parseEntesMpr45($regs);

        // Her okumada DB'ye kaydet (en fazla dakikada 1 kez)
        $sonKayit = \app\models\AnalizorOlcum::find()
            ->where(['ekipman_id' => $id])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if ($sonKayit === null || (time() - strtotime($sonKayit->created_at)) >= 60) {
            \app\models\AnalizorOlcum::kaydet($id, $data);
        }

        return [
            'success' => true,
            'model'   => $c['model'],
            'data'    => $data,
        ];
    }

    /**
     * Enerji analizörü geçmiş ölçüm verilerini listeler.
     * GET /ekipman/analizor-gecmis?id=ESNT-ADP-03
     */
    public function actionAnalizorGecmis($id)
    {
        $model = $this->findModel($id);
        $analizorConfig = self::getAnalizorConfig($id);
        if ($analizorConfig === null) {
            throw new NotFoundHttpException('Bu ekipman için analizör tanımı bulunamadı.');
        }

        $searchModel = new \app\models\AnalizorOlcumSearch();
        $searchModel->ekipman_id = $id;
        $queryParams = Yii::$app->request->queryParams;

        // Yeni ETES log akisi varsa (entes_log_ham), gorunumu bu birlesik veriden besle.
        $hamEkipmanId = Yii::$app->db->createCommand(
            "SELECT ekipman_id FROM entes_log_ham WHERE ekipman_id = :id LIMIT 1",
            [':id' => $id]
        )->queryScalar();

        if (!$hamEkipmanId) {
            // Geriye donuk uyum: senkron daha once cihaz ID (ornek A107) ile kaydetmis olabilir.
            $hamEkipmanId = Yii::$app->db->createCommand(
                "SELECT ekipman_id FROM entes_log_ham WHERE log_type = 'profile' ORDER BY synced_at DESC LIMIT 1"
            )->queryScalar();
        }

        if ($hamEkipmanId) {
            $searchModel->load($queryParams);

            $where = "p.ekipman_id = :eid AND p.log_type = 'profile'";
            $params = [':eid' => $hamEkipmanId];

            if (!empty($searchModel->tarih_baslangic)) {
                $where .= " AND p.start_date >= :tarihBas";
                $params[':tarihBas'] = $searchModel->tarih_baslangic . ' 00:00:00';
            }
            if (!empty($searchModel->tarih_bitis)) {
                $where .= " AND p.start_date <= :tarihBit";
                $params[':tarihBit'] = $searchModel->tarih_bitis . ' 23:59:59';
            }

            $rows = Yii::$app->db->createCommand(
                "SELECT
                    p.start_date AS created_at,
                    CASE WHEN v.field_4 > 0 THEN ROUND(v.field_4 / 10, 1) ELSE NULL END AS v_l1l2,
                    CASE WHEN v.field_5 > 0 THEN ROUND(v.field_5 / 10, 1) ELSE NULL END AS v_l2l3,
                    CASE WHEN v.field_6 > 0 THEN ROUND(v.field_6 / 10, 1) ELSE NULL END AS v_l3l1,
                    CASE WHEN p.field_2 > 0 THEN ROUND(p.field_2 / 1000, 2) ELSE NULL END AS p_total_kw,
                    CASE WHEN p.field_3 > 0 THEN ROUND(p.field_3 / 1000, 2) ELSE NULL END AS s_total_kva,
                    CASE
                        WHEN (c.field_0 + c.field_1 + c.field_2) > 0
                        THEN ROUND((c.field_0 + c.field_1 + c.field_2) / 30000, 1)
                        ELSE NULL
                    END AS i_avg_a,
                    CASE WHEN v.field_7 > 0 THEN ROUND(v.field_7 / 100, 2) ELSE NULL END AS freq,
                    CASE WHEN p.field_5 BETWEEN -1 AND 1 THEN ROUND(p.field_5, 3) ELSE NULL END AS pf_avg,
                    CASE WHEN p.field_0 > 0 THEN ROUND(p.field_0, 1) ELSE NULL END AS e_import_total_kwh
                FROM entes_log_ham p
                LEFT JOIN entes_log_ham c
                  ON c.ekipman_id = p.ekipman_id
                 AND c.log_type = 'current'
                 AND c.start_date = p.start_date
                LEFT JOIN entes_log_ham v
                  ON v.ekipman_id = p.ekipman_id
                 AND v.log_type = 'voltage'
                 AND v.start_date = p.start_date
                WHERE {$where}
                ORDER BY p.start_date DESC",
                $params
            )->queryAll();

            $dataProvider = new ArrayDataProvider([
                'allModels' => $rows,
                'pagination' => ['pageSize' => 50],
                'sort' => [
                    'attributes' => ['created_at'],
                    'defaultOrder' => ['created_at' => SORT_DESC],
                ],
            ]);

            // Gunluk ozet (ham veriden)
            $gunlukMap = [];
            foreach ($rows as $r) {
                $tarih = substr((string)$r['created_at'], 0, 10);
                if (!isset($gunlukMap[$tarih])) {
                    $gunlukMap[$tarih] = [
                        'tarih' => $tarih,
                        'kw_vals' => [],
                        'pf_vals' => [],
                        'freq_vals' => [],
                        'kwh_vals' => [],
                        'olcum_sayisi' => 0,
                    ];
                }
                $gunlukMap[$tarih]['olcum_sayisi']++;
                if ($r['p_total_kw'] !== null) {
                    $gunlukMap[$tarih]['kw_vals'][] = (float)$r['p_total_kw'];
                }
                if ($r['pf_avg'] !== null) {
                    $gunlukMap[$tarih]['pf_vals'][] = (float)$r['pf_avg'];
                }
                if ($r['freq'] !== null) {
                    $gunlukMap[$tarih]['freq_vals'][] = (float)$r['freq'];
                }
                if ($r['e_import_total_kwh'] !== null) {
                    $gunlukMap[$tarih]['kwh_vals'][] = (float)$r['e_import_total_kwh'];
                }
            }

            $gunlukOzet = [];
            foreach ($gunlukMap as $g) {
                $kw = $g['kw_vals'];
                $pf = $g['pf_vals'];
                $fr = $g['freq_vals'];
                $kwh = $g['kwh_vals'];

                $gunlukOzet[] = [
                    'tarih' => $g['tarih'],
                    'ort_kw' => !empty($kw) ? array_sum($kw) / count($kw) : 0,
                    'max_kw' => !empty($kw) ? max($kw) : 0,
                    'min_kw' => !empty($kw) ? min($kw) : 0,
                    'ort_pf' => !empty($pf) ? array_sum($pf) / count($pf) : 0,
                    'ort_freq' => !empty($fr) ? array_sum($fr) / count($fr) : 0,
                    'max_kwh' => !empty($kwh) ? max($kwh) : 0,
                    'min_kwh' => !empty($kwh) ? min($kwh) : 0,
                    'olcum_sayisi' => $g['olcum_sayisi'],
                ];
            }

            usort($gunlukOzet, static function ($a, $b) {
                return strcmp($b['tarih'], $a['tarih']);
            });
            $gunlukOzet = array_slice($gunlukOzet, 0, 30);

            return $this->render('analizor-gecmis', [
                'model' => $model,
                'analizorConfig' => $analizorConfig,
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
                'gunlukOzet' => $gunlukOzet,
            ]);
        }

        $dataProvider = $searchModel->search($queryParams);

        // Günlük özet (son 30 gün)
        $gunlukOzet = \app\models\AnalizorOlcum::find()
            ->select([
                'DATE(created_at) as tarih',
                'AVG(p_total_kw) as ort_kw',
                'MAX(p_total_kw) as max_kw',
                'MIN(p_total_kw) as min_kw',
                'AVG(pf_avg) as ort_pf',
                'AVG(freq) as ort_freq',
                'MAX(e_import_total_kwh) as max_kwh',
                'MIN(e_import_total_kwh) as min_kwh',
                'COUNT(*) as olcum_sayisi',
            ])
            ->where(['ekipman_id' => $id])
            ->groupBy('DATE(created_at)')
            ->orderBy(['tarih' => SORT_DESC])
            ->limit(30)
            ->asArray()
            ->all();

        return $this->render('analizor-gecmis', [
            'model' => $model,
            'analizorConfig' => $analizorConfig,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'gunlukOzet' => $gunlukOzet,
        ]);
    }

    /**
     * Belirli ekipman için analizör config'i var mı kontrol eder.
     */
    public static function getAnalizorConfig(string $ekipmanId): ?array
    {
        $config = require Yii::getAlias('@app/config/analizor.php');
        return $config[$ekipmanId] ?? null;
    }

    private function deleteTanitimFotoFile(?string $relativePath): void
    {
        $relativePath = trim((string)$relativePath);
        if ($relativePath === '') {
            return;
        }

        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
        if (!str_starts_with($relativePath, 'ekipman-tanitim/')) {
            return;
        }

        $absolutePath = Yii::getAlias('@app/web/uploads/' . $relativePath);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

}
