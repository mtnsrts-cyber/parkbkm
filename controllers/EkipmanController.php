<?php

namespace app\controllers;

use Yii;
use app\models\Ekipman;
use app\models\EkipmanDokuman;
use app\models\PlanliBakim;
use app\models\PeriyodikKontrol;
use app\models\BakimTakip;
use app\models\Sog5EnergyLog;
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
            'only' => ['create','update','delete','hurdaya-ayir','aktife-al','dokuman-ekle','dokuman-sil','tanitim-foto-yukle','tanitim-foto-sil','enerji-kaynagi-aktar','analizor-create','analizor-update','analizor-delete'],
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@'], // sadece login olan
                    'actions' => ['create', 'update', 'delete', 'analizor-create', 'analizor-update', 'analizor-delete'],
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

        $analizor = \app\models\AnalizorCihaz::findOne(['ekipman_kodu' => $id, 'aktif' => true]);
        if (!$analizor) {
            return ['success' => false, 'message' => 'Bu ekipman için analizör tanımı bulunamadı.'];
        }

        $regs = \app\helpers\ModbusHelper::readHoldingRegisters(
            $analizor->ip, (int)$analizor->port, (int)$analizor->device_id, 0, 100, 5
        );

        if ($regs === false) {
            // 1 kez daha dene
            $regs = \app\helpers\ModbusHelper::readHoldingRegisters(
                $analizor->ip, (int)$analizor->port, (int)$analizor->device_id, 0, 100, 5
            );
        }

        if ($regs === false) {
            Yii::warning("Analizör bağlantı hatası: {$analizor->ip}:{$analizor->port} (ekipman: {$id})", __METHOD__);
            return ['success' => false, 'message' => 'Analizöre bağlanılamadı. Cihaz erişilebilir durumda olmayabilir.'];
        }

$data = \app\helpers\ModbusHelper::parseEntesMpr45($regs);

        return [
            'success' => true,
            'model'   => $analizor->model,
            'data'    => $data,
        ];
    }

    /**
     * SOG5 Güç Kontrol Rölesi canlı verisini JSON olarak döndürür.
     * AJAX ile çağrılır: GET /ekipman/sog5-veri
     */
    public function actionSog5Veri()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $ip = '192.168.201.248';
        $port = 502;
        $unitId = 5;
        $timeout = 5;

        try {
            $readU16 = function($addr) use ($ip, $port, $unitId, $timeout) {
                $r = \app\helpers\ModbusHelper::readHoldingRegisters($ip, $port, $unitId, $addr, 1, $timeout);
                return $r[0] ?? null;
            };
            $readU32 = function($addr) use ($ip, $port, $unitId, $timeout) {
                $r = \app\helpers\ModbusHelper::readHoldingRegisters($ip, $port, $unitId, $addr, 2, $timeout);
                if (!$r || count($r) < 2) return null;
                $v = ($r[0] << 16) | $r[1];
                return $v;
            };
            $readS32 = function($addr) use ($ip, $port, $unitId, $timeout) {
                $r = \app\helpers\ModbusHelper::readHoldingRegisters($ip, $port, $unitId, $addr, 2, $timeout);
                if (!$r || count($r) < 2) return null;
                $v = ($r[0] << 16) | $r[1];
                return $v >= 0x80000000 ? ($v - 0x100000000) : $v;
            };

            $data = [
                'e_l1_import_kwh' => $readU32(0) / 1000,
                'e_l2_import_kwh' => $readU32(2) / 1000,
                'e_l3_import_kwh' => $readU32(4) / 1000,
                'e_l1_reactive_ind_kvarh' => $readU32(12) / 1000,
                'e_l2_reactive_ind_kvarh' => $readU32(14) / 1000,
                'e_l3_reactive_ind_kvarh' => $readU32(16) / 1000,
                'e_l1_reactive_cap_kvarh' => $readU32(18) / 1000,
                'e_l2_reactive_cap_kvarh' => $readU32(20) / 1000,
                'e_l3_reactive_cap_kvarh' => $readU32(22) / 1000,
                'p_l1_kw' => $readS32(24) / 1000,
                'p_l2_kw' => $readS32(26) / 1000,
                'p_l3_kw' => $readS32(28) / 1000,
                'q_ind_l1_kvar' => $readS32(30) / 1000,
                'q_ind_l2_kvar' => $readS32(32) / 1000,
                'q_ind_l3_kvar' => $readS32(34) / 1000,
                'q_cap_l1_var' => $readS32(36) / 1000,
                'q_cap_l2_var' => $readS32(38) / 1000,
'q_cap_l3_var' => $readS32(40) / 1000,
                'pf_l1' => $readU16(42) / 100,
                'pf_l2' => $readU16(43) / 100,
                'pf_l3' => $readU16(44) / 100,
                'f_l1_hz' => $readU16(47) / 10,
                'v_l1_v' => $readU16(56),
                'v_l2_v' => $readU16(57),
                'v_l3_v' => $readU16(58),
                'i_l1_a' => $readU32(59) / 100,
                'i_l2_a' => $readU32(61) / 100,
                'i_l3_a' => $readU32(63) / 100,
                'step_status_bits' => $readU32(73),
            ];

            $step = $data['step_status_bits'] ?? 0;
            for ($i = 1; $i <= 12; $i++) {
                $data['step_' . $i] = (bool)($step & (1 << ($i - 1)));
            }

            $data['v_l1_l2_v'] = isset($data['v_l1_v']) ? round($data['v_l1_v'] * 1.732) : null;
            $data['v_l2_l3_v'] = isset($data['v_l2_v']) ? round($data['v_l2_v'] * 1.732) : null;
            $data['v_l3_l1_v'] = isset($data['v_l3_v']) ? round($data['v_l3_v'] * 1.732) : null;

            $pTotal = ($data['p_l1_kw'] ?? 0) + ($data['p_l2_kw'] ?? 0) + ($data['p_l3_kw'] ?? 0);
            $data['p_total_kw'] = $pTotal;

            $pfValues = array_filter([$data['pf_l1'] ?? null, $data['pf_l2'] ?? null, $data['pf_l3'] ?? null]);
            $data['pf_average'] = !empty($pfValues) ? array_sum($pfValues) / count($pfValues) : null;

            $indTotal = (($data['q_ind_l1_kvar'] ?? 0) + ($data['q_ind_l2_kvar'] ?? 0) + ($data['q_ind_l3_kvar'] ?? 0));
            $capTotal = (($data['q_cap_l1_var'] ?? 0) + ($data['q_cap_l2_var'] ?? 0) + ($data['q_cap_l3_var'] ?? 0));
            $data['compensation_inductive_kvar'] = $indTotal;
            $data['compensation_capacitive_kvar'] = $capTotal;
            $data['compensation_total_kvar'] = round($indTotal - $capTotal, 2);
            
            $data['timestamp'] = date('Y-m-d H:i:s');
            
            // Raw veriyi kaydet (30 saniyede bir)
            self::logSog5Raw($data);
            
            self::logSog5Energy($data);

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private static function logSog5Energy(array $data, $force = false)
    {
        $now = date('Y-m-d H:00:00');
        $lastLog = Sog5EnergyLog::find()
            ->orderBy('log_date DESC')
            ->one();

        if (!$force && $lastLog && $lastLog->log_date === $now) {
            return;
        }

        $log = new Sog5EnergyLog();
        $log->log_date = $now;
        $log->e_l1_kwh = $data['e_l1_import_kwh'] ?? null;
        $log->e_l2_kwh = $data['e_l2_import_kwh'] ?? null;
        $log->e_l3_kwh = $data['e_l3_import_kwh'] ?? null;
        $log->e_total_kwh = ($data['e_l1_import_kwh'] ?? 0) + ($data['e_l2_import_kwh'] ?? 0) + ($data['e_l3_import_kwh'] ?? 0);
        $log->q_ind_kvarh = ($data['e_l1_reactive_ind_kvarh'] ?? 0) + ($data['e_l2_reactive_ind_kvarh'] ?? 0) + ($data['e_l3_reactive_ind_kvarh'] ?? 0);
        $log->q_cap_kvarh = ($data['e_l1_reactive_cap_kvarh'] ?? 0) + ($data['e_l2_reactive_cap_kvarh'] ?? 0) + ($data['e_l3_reactive_cap_kvarh'] ?? 0);
        
        $v1 = floatval($data['e_l1_reactive_ind_kvarh'] ?? 0);
        $v2 = floatval($data['e_l2_reactive_ind_kvarh'] ?? 0);
        $v3 = floatval($data['e_l3_reactive_ind_kvarh'] ?? 0);
        $log->setAttribute('e_l1_reactive_ind_kvarh', $v1);
        $log->setAttribute('e_l2_reactive_ind_kvarh', $v2);
        $log->setAttribute('e_l3_reactive_ind_kvarh', $v3);
        $log->setAttribute('e_l1_reactive_cap_kvarh', floatval($data['e_l1_reactive_cap_kvarh'] ?? 0));
        $log->setAttribute('e_l2_reactive_cap_kvarh', floatval($data['e_l2_reactive_cap_kvarh'] ?? 0));
        $log->setAttribute('e_l3_reactive_cap_kvarh', floatval($data['e_l3_reactive_cap_kvarh'] ?? 0));
        
        if (!$log->save()) {
            error_log('Sog5EnergyLog save error: ' . json_encode($log->getErrors()));
        } else {
            error_log('Sog5EnergyLog saved id=' . $log->id . ' l1_ind=' . $v1);
        }

        $monthAgo = date('Y-m-d H:00:00', strtotime('-35 days'));
        Sog5EnergyLog::deleteAll(['<', 'log_date', $monthAgo]);
    }
    
    private static function logSog5Raw(array $data)
    {
        $db = \Yii::$app->db;
        
        // Her 30 saniyede bir kaydet
        $second = (int)date('s');
        if ($second < 15 || $second > 45) return;
        
        $datetime = date('Y-m-d H:i:00');
        
        // En son kayıt var mı kontrol et
        $lastRaw = $db->createCommand('SELECT log_datetime FROM sog5_energy_logs_raw ORDER BY log_datetime DESC LIMIT 1')->queryOne();
        if ($lastRaw && $lastRaw['log_datetime'] === $datetime) {
            return;
        }
        
        $eTotal = ($data['e_l1_import_kwh'] ?? 0) + ($data['e_l2_import_kwh'] ?? 0) + ($data['e_l3_import_kwh'] ?? 0);
        
        $db->createCommand()->insert('sog5_energy_logs_raw', [
            'log_datetime' => $datetime,
            'e_total_kwh' => $eTotal,
            'e_l1_reactive_ind_kvarh' => $data['e_l1_reactive_ind_kvarh'] ?? 0,
            'e_l2_reactive_ind_kvarh' => $data['e_l2_reactive_ind_kvarh'] ?? 0,
            'e_l3_reactive_ind_kvarh' => $data['e_l3_reactive_ind_kvarh'] ?? 0,
            'e_l1_reactive_cap_kvarh' => $data['e_l1_reactive_cap_kvarh'] ?? 0,
            'e_l2_reactive_cap_kvarh' => $data['e_l2_reactive_cap_kvarh'] ?? 0,
            'e_l3_reactive_cap_kvarh' => $data['e_l3_reactive_cap_kvarh'] ?? 0,
        ])->execute();
        
        // Eski kayıtları temizle (son 48 saatten eskilerini sil)
        $cleanup = date('Y-m-d H:i:00', strtotime('-48 hours'));
        $db->createCommand('DELETE FROM sog5_energy_logs_raw WHERE log_datetime < :cleanup')->bindValue(':cleanup', $cleanup)->execute();
    }

public function actionSog5Tuketim()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $db = \Yii::$app->db;
        
        // Günlük hesaplama - dünkü aynı saatle kıyasla
        $todayHour = date('Y-m-d H');
        $yesterdayHour = date('Y-m-d H', strtotime('-24 hours'));
        
// Bugünkü son veri (dolu)
        $todayData = $db->createCommand('SELECT * FROM sog5_energy_logs_raw WHERE e_total_kwh > 1000000 ORDER BY log_datetime DESC LIMIT 1')->queryOne();
        
        // Dünkü aynı saat
        $yesterdayHour = date('Y-m-d H', strtotime('-24 hours'));
        $yesterdayData = $db->createCommand('SELECT * FROM sog5_energy_logs_raw WHERE log_datetime LIKE :yesterday AND e_total_kwh > 1000000 ORDER BY log_datetime DESC LIMIT 1')
            ->bindValue(':yesterday', $yesterdayHour . '%')->queryOne();
        
        // Dünkü aynı saat yoksa en son dünkü veriyi al
        if (!$yesterdayData) {
            $yesterdayData = $db->createCommand('SELECT * FROM sog5_energy_logs_raw WHERE log_datetime < :today AND e_total_kwh > 1000000 ORDER BY log_datetime DESC LIMIT 1')
                ->bindValue(':today', date('Y-m-d'))->queryOne();
        }
        
        $dailyE = null;
        $dailyQInd = null;
        $dailyQCap = null;
        
        if ($todayData && $yesterdayData && strtotime($todayData['log_datetime']) > strtotime($yesterdayData['log_datetime'])) {
            $eDiff = ($todayData['e_total_kwh'] ?? 0) - ($yesterdayData['e_total_kwh'] ?? 0);
            
            $calcDiff = function($new, $old) {
                if ($new <= 0 || $old <= 0) return 0;
                $d = $new - $old;
                return ($d > 0 && $d < 1000) ? $d : 0;
            };
            
            $qInd1 = $calcDiff($todayData['e_l1_reactive_ind_kvarh'], $yesterdayData['e_l1_reactive_ind_kvarh']);
            $qInd2 = $calcDiff($todayData['e_l2_reactive_ind_kvarh'], $yesterdayData['e_l2_reactive_ind_kvarh']);
            $qInd3 = $calcDiff($todayData['e_l3_reactive_ind_kvarh'], $yesterdayData['e_l3_reactive_ind_kvarh']);
            $qCap1 = $calcDiff($todayData['e_l1_reactive_cap_kvarh'], $yesterdayData['e_l1_reactive_cap_kvarh']);
            $qCap2 = $calcDiff($todayData['e_l2_reactive_cap_kvarh'], $yesterdayData['e_l2_reactive_cap_kvarh']);
            $qCap3 = $calcDiff($todayData['e_l3_reactive_cap_kvarh'], $yesterdayData['e_l3_reactive_cap_kvarh']);
            
            if ($eDiff > 0) $dailyE = round($eDiff, 1);
            if ($qInd1 + $qInd2 + $qInd3 > 0) $dailyQInd = round($qInd1 + $qInd2 + $qInd3, 1);
            if ($qCap1 + $qCap2 + $qCap3 > 0) $dailyQCap = round($qCap1 + $qCap2 + $qCap3, 1);
        }
        
        // Saatlik
        $hourAgo = date('Y-m-d H:00:00', strtotime('-1 hour'));
        $hourLog = $db->createCommand('SELECT * FROM sog5_energy_logs WHERE log_date < :hour ORDER BY log_date DESC LIMIT 1')
            ->bindValue(':hour', $hourAgo)->queryOne();
        $latest = $db->createCommand('SELECT * FROM sog5_energy_logs ORDER BY log_date DESC LIMIT 1')->queryOne();
        
        $hourlyE = null;
        $hourlyQInd = 0;
        $hourlyQCap = 0;
        
        // Raw tablodan saatlik hesapla - son 2 dolu kayıt
        $rawNow = $db->createCommand('SELECT * FROM sog5_energy_logs_raw WHERE e_total_kwh > 1000000 ORDER BY log_datetime DESC LIMIT 1')->queryOne();
        $rawPrev = $db->createCommand('SELECT * FROM sog5_energy_logs_raw WHERE e_total_kwh > 1000000 AND log_datetime < :now ORDER BY log_datetime DESC LIMIT 1')
            ->bindValue(':now', $rawNow['log_datetime'] ?? date('Y-m-d H:i:s'))->queryOne();
        
        $hourlyE = null;
        $hourlyQInd = 0;
        $hourlyQCap = 0;
        
        if ($rawNow && $rawPrev) {
            $eDiff = round(($rawNow['e_total_kwh'] ?? 0) - ($rawPrev['e_total_kwh'] ?? 0), 1);
            if ($eDiff > 0 && $eDiff < 1000) {
                $hourlyE = $eDiff;
                
                $qInd = ($rawNow['e_l1_reactive_ind_kvarh'] ?? 0) - ($rawPrev['e_l1_reactive_ind_kvarh'] ?? 0);
                $qCap = ($rawNow['e_l1_reactive_cap_kvarh'] ?? 0) - ($rawPrev['e_l1_reactive_cap_kvarh'] ?? 0);
                $hourlyQInd = ($qInd >= 0 && $qInd < 1000) ? round($qInd, 1) : 0;
                $hourlyQCap = ($qCap >= 0 && $qCap < 1000) ? round($qCap, 1) : 0;
            }
        }
        
        // Oranlar
        $calcOran = function($e, $q) {
            return $e > 0 ? round(($q ?? 0) / $e * 100, 1) : null;
        };
        
        $hourData = ['e_kwh' => $hourlyE, 'q_ind_total' => $hourlyQInd, 'q_cap_total' => $hourlyQCap,
'q_ind_oran' => $calcOran($hourlyE, $hourlyQInd), 'q_cap_oran' => $calcOran($hourlyE, $hourlyQCap)];
        $dailyData = ['e_kwh' => $dailyE, 'q_ind_total' => $dailyQInd, 'q_cap_total' => $dailyQCap,
            'q_ind_oran' => $calcOran($dailyE, $dailyQInd), 'q_cap_oran' => $calcOran($dailyE, $dailyQCap)];
        
// Aylık - sog5_energy_logs tablosundan (bu tablo silinmiyor)
        $monthlyE = null;
        $monthlyQInd = 0;
        $monthlyQCap = 0;
        
        $monthLogs = $db->createCommand('SELECT * FROM sog5_energy_logs ORDER BY log_date DESC LIMIT 1')->queryOne();
        $firstOfMonth = date('Y-m-01 00:00:00');
        
        if ($monthLogs) {
            // Ay başından ilk dolulu kaydı bul
            $firstLog = $db->createCommand('SELECT * FROM sog5_energy_logs 
                WHERE log_date >= :first 
                AND (e_l1_reactive_ind_kvarh > 0 OR e_l2_reactive_ind_kvarh > 0 OR e_l3_reactive_ind_kvarh > 0)
                ORDER BY log_date ASC LIMIT 1')
                ->bindValue(':first', $firstOfMonth)->queryOne();
            
            if ($firstLog && $monthLogs) {
                // Aktif - toplamdan fark
                if ($monthLogs['e_total_kwh'] > $firstLog['e_total_kwh'] && $firstLog['e_total_kwh'] > 0) {
                    $monthlyE = round($monthLogs['e_total_kwh'] - $firstLog['e_total_kwh'], 1);
                }
                
                // Reaktif Endüktif - her faz ayrı hesapla
                $qIndL1 = 0; $qIndL2 = 0; $qIndL3 = 0;
                if ($firstLog['e_l1_reactive_ind_kvarh'] > 0 && $monthLogs['e_l1_reactive_ind_kvarh'] > $firstLog['e_l1_reactive_ind_kvarh']) {
                    $qIndL1 = $monthLogs['e_l1_reactive_ind_kvarh'] - $firstLog['e_l1_reactive_ind_kvarh'];
                }
                if ($firstLog['e_l2_reactive_ind_kvarh'] > 0 && $monthLogs['e_l2_reactive_ind_kvarh'] > $firstLog['e_l2_reactive_ind_kvarh']) {
                    $qIndL2 = $monthLogs['e_l2_reactive_ind_kvarh'] - $firstLog['e_l2_reactive_ind_kvarh'];
                }
                if ($firstLog['e_l3_reactive_ind_kvarh'] > 0 && $monthLogs['e_l3_reactive_ind_kvarh'] > $firstLog['e_l3_reactive_ind_kvarh']) {
                    $qIndL3 = $monthLogs['e_l3_reactive_ind_kvarh'] - $firstLog['e_l3_reactive_ind_kvarh'];
                }
                $monthlyQInd = round($qIndL1 + $qIndL2 + $qIndL3, 1);
                
                // Reaktif Kapasitif - her faz ayrı hesapla
                $qCapL1 = 0; $qCapL2 = 0; $qCapL3 = 0;
                if ($firstLog['e_l1_reactive_cap_kvarh'] > 0 && $monthLogs['e_l1_reactive_cap_kvarh'] > $firstLog['e_l1_reactive_cap_kvarh']) {
                    $qCapL1 = $monthLogs['e_l1_reactive_cap_kvarh'] - $firstLog['e_l1_reactive_cap_kvarh'];
                }
                if ($firstLog['e_l2_reactive_cap_kvarh'] > 0 && $monthLogs['e_l2_reactive_cap_kvarh'] > $firstLog['e_l2_reactive_cap_kvarh']) {
                    $qCapL2 = $monthLogs['e_l2_reactive_cap_kvarh'] - $firstLog['e_l2_reactive_cap_kvarh'];
                }
                if ($firstLog['e_l3_reactive_cap_kvarh'] > 0 && $monthLogs['e_l3_reactive_cap_kvarh'] > $firstLog['e_l3_reactive_cap_kvarh']) {
                    $qCapL3 = $monthLogs['e_l3_reactive_cap_kvarh'] - $firstLog['e_l3_reactive_cap_kvarh'];
                }
                $monthlyQCap = round($qCapL1 + $qCapL2 + $qCapL3, 1);
            }
        }
        
        $monthlyData = ['e_kwh' => $monthlyE, 'q_ind_total' => $monthlyQInd, 'q_cap_total' => $monthlyQCap,
            'q_ind_oran' => $calcOran($monthlyE, $monthlyQInd), 'q_cap_oran' => $calcOran($monthlyE, $monthlyQCap)];
        
        return ['success' => true, 'data' => [
            'hourly' => $hourData,
            'daily' => $dailyData,
            'monthly' => $monthlyData,
            'raw' => ['q_ind_total' => null, 'q_cap_total' => null]
        ]];
    }
    
    /**
     * SOG5 grafik verileri - son 10 saatlik / günlük / aylık
     * GET /ekipman/sog5-grafik?type=hourly|daily|monthly
     */
    public function actionSog5Grafik()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $type = Yii::$app->request->get('type', 'hourly');
        $db = \Yii::$app->db;
        
        $labels = [];
        $aktifData = [];
        $qIndData = [];
        $qCapData = [];
        
        if ($type === 'hourly') {
            // Son 10 saat - sog5_energy_logs tablosundan
            for ($i = 9; $i >= 0; $i--) {
                $hour = date('Y-m-d H:00:00', strtotime("-{$i} hour"));
                $hourEnd = date('Y-m-d H:59:59', strtotime("-{$i} hour"));
                
                $log = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date BETWEEN :start AND :end AND e_total_kwh > 1000000
                    ORDER BY log_date DESC LIMIT 1')
                    ->bindValue(':start', $hour)->bindValue(':end', $hourEnd)->queryOne();
                
                $prevLog = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date < :start AND e_total_kwh > 1000000
                    ORDER BY log_date DESC LIMIT 1')
                    ->bindValue(':start', $hour)->queryOne();
                
                $labels[] = date('H:00', strtotime("-{$i} hour"));
                
                if ($log && $prevLog) {
                    $e = round($log['e_total_kwh'] - $prevLog['e_total_kwh'], 1);
                    $qind = round(($log['e_l1_reactive_ind_kvarh'] + $log['e_l2_reactive_ind_kvarh'] + $log['e_l3_reactive_ind_kvarh']) 
                        - ($prevLog['e_l1_reactive_ind_kvarh'] + $prevLog['e_l2_reactive_ind_kvarh'] + $prevLog['e_l3_reactive_ind_kvarh']), 1);
                    $qcap = round(($log['e_l1_reactive_cap_kvarh'] + $log['e_l2_reactive_cap_kvarh'] + $log['e_l3_reactive_cap_kvarh']) 
                        - ($prevLog['e_l1_reactive_cap_kvarh'] + $prevLog['e_l2_reactive_cap_kvarh'] + $prevLog['e_l3_reactive_cap_kvarh']), 1);
                    
                    $aktifData[] = $e > 0 && $e < 1000 ? $e : 0;
                    $qIndData[] = $qind > 0 && $qind < 500 ? $qind : 0;
                    $qCapData[] = $qcap > 0 && $qcap < 500 ? $qcap : 0;
                } else {
                    $aktifData[] = 0;
                    $qIndData[] = 0;
                    $qCapData[] = 0;
                }
            }
        } elseif ($type === 'daily') {
            // Son 10 gün - sog5_energy_logs tablosundan ( saatlik toplama )
            for ($i = 9; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-{$i} day"));
                $dayStart = $day . ' 00:00:00';
                $dayEnd = $day . ' 23:59:59';
                
                $logs = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date BETWEEN :start AND :end AND e_total_kwh > 1000000
                    ORDER BY log_date ASC')
                    ->bindValue(':start', $dayStart)->bindValue(':end', $dayEnd)->queryAll();
                
                $labels[] = date('d', strtotime("-{$i} day"));
                
                $eTotal = 0; $qIndTotal = 0; $qCapTotal = 0;
                $prev = null;
                foreach ($logs as $log) {
                    if ($prev) {
                        $e = $log['e_total_kwh'] - $prev['e_total_kwh'];
                        if ($e > 0 && $e < 1000) $eTotal += $e;
                        
                        $qind = ($log['e_l1_reactive_ind_kvarh'] + $log['e_l2_reactive_ind_kvarh'] + $log['e_l3_reactive_ind_kvarh'])
                            - ($prev['e_l1_reactive_ind_kvarh'] + $prev['e_l2_reactive_ind_kvarh'] + $prev['e_l3_reactive_ind_kvarh']);
                        if ($qind > 0 && $qind < 500) $qIndTotal += $qind;
                        
                        $qcap = ($log['e_l1_reactive_cap_kvarh'] + $log['e_l2_reactive_cap_kvarh'] + $log['e_l3_reactive_cap_kvarh'])
                            - ($prev['e_l1_reactive_cap_kvarh'] + $prev['e_l2_reactive_cap_kvarh'] + $prev['e_l3_reactive_cap_kvarh']);
                        if ($qcap > 0 && $qcap < 500) $qCapTotal += $qcap;
                    }
                    $prev = $log;
                }
                
                $aktifData[] = round($eTotal, 1);
                $qIndData[] = round($qIndTotal, 1);
                $qCapData[] = round($qCapTotal, 1);
            }
        } elseif ($type === 'monthly') {
            // Son 12 ay - sog5_energy_logs tablosundan (günlük toplamaların toplamı)
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-{$i} month"));
                $monthStart = $month . '-01 00:00:00';
                $monthEnd = $month . '-31 23:59:59';
                
                $firstLog = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date >= :start AND e_total_kwh > 1000000
                    ORDER BY log_date ASC LIMIT 1')
                    ->bindValue(':start', $monthStart)->queryOne();
                
                $lastLog = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date <= :end AND e_total_kwh > 1000000
                    ORDER BY log_date DESC LIMIT 1')
                    ->bindValue(':end', $monthEnd)->queryOne();
                
                $labels[] = date('M', strtotime("-{$i} month"));
                
                if ($firstLog && $lastLog) {
                    $e = round($lastLog['e_total_kwh'] - $firstLog['e_total_kwh'], 1);
                    $qind = round(($lastLog['e_l1_reactive_ind_kvarh'] + $lastLog['e_l2_reactive_ind_kvarh'] + $lastLog['e_l3_reactive_ind_kvarh'])
                        - ($firstLog['e_l1_reactive_ind_kvarh'] + $firstLog['e_l2_reactive_ind_kvarh'] + $firstLog['e_l3_reactive_ind_kvarh']), 1);
                    $qcap = round(($lastLog['e_l1_reactive_cap_kvarh'] + $lastLog['e_l2_reactive_cap_kvarh'] + $lastLog['e_l3_reactive_cap_kvarh'])
                        - ($firstLog['e_l1_reactive_cap_kvarh'] + $firstLog['e_l2_reactive_cap_kvarh'] + $firstLog['e_l3_reactive_cap_kvarh']), 1);
                    
                    $aktifData[] = $e > 0 ? $e : 0;
                    $qIndData[] = $qind >= 0 ? $qind : 0;
                    $qCapData[] = $qcap >= 0 ? $qcap : 0;
                } else {
                    $aktifData[] = 0;
                    $qIndData[] = 0;
                    $qCapData[] = 0;
                }
            }
        } elseif ($type === 'yearly') {
            // Son 5 yıl - sog5_energy_logs tablosundan
            for ($i = 4; $i >= 0; $i--) {
                $year = date('Y', strtotime("-{$i} year"));
                $yearStart = $year . '-01-01 00:00:00';
                $yearEnd = $year . '-12-31 23:59:59';
                
                $firstLog = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date >= :start AND e_total_kwh > 1000000
                    ORDER BY log_date ASC LIMIT 1')
                    ->bindValue(':start', $yearStart)->queryOne();
                
                $lastLog = $db->createCommand('SELECT * FROM sog5_energy_logs 
                    WHERE log_date <= :end AND e_total_kwh > 1000000
                    ORDER BY log_date DESC LIMIT 1')
                    ->bindValue(':end', $yearEnd)->queryOne();
                
                $labels[] = $year;
                
                if ($firstLog && $lastLog) {
                    $e = round($lastLog['e_total_kwh'] - $firstLog['e_total_kwh'], 1);
                    $qind = round(($lastLog['e_l1_reactive_ind_kvarh'] + $lastLog['e_l2_reactive_ind_kvarh'] + $lastLog['e_l3_reactive_ind_kvarh'])
                        - ($firstLog['e_l1_reactive_ind_kvarh'] + $firstLog['e_l2_reactive_ind_kvarh'] + $firstLog['e_l3_reactive_ind_kvarh']), 1);
                    $qcap = round(($lastLog['e_l1_reactive_cap_kvarh'] + $lastLog['e_l2_reactive_cap_kvarh'] + $lastLog['e_l3_reactive_cap_kvarh'])
                        - ($firstLog['e_l1_reactive_cap_kvarh'] + $firstLog['e_l2_reactive_cap_kvarh'] + $firstLog['e_l3_reactive_cap_kvarh']), 1);
                    
                    $aktifData[] = $e > 0 ? $e : 0;
                    $qIndData[] = $qind >= 0 ? $qind : 0;
                    $qCapData[] = $qcap >= 0 ? $qcap : 0;
                } else {
                    $aktifData[] = 0;
                    $qIndData[] = 0;
                    $qCapData[] = 0;
                }
            }
        }
        
        return ['success' => true, 'data' => [
            'labels' => $labels,
            'aktif' => $aktifData,
            'qind' => $qIndData,
            'qcap' => $qCapData
        ]];
    }

    /**
     * Enerji analizörü geçmiş ölçüm verilerini listeler.
     * GET /ekipman/analizor-gecmis?id=ESNT-ADP-03
     */
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
        $analizor = \app\models\AnalizorCihaz::findOne(['ekipman_kodu' => $ekipmanId, 'aktif' => true]);
        if (!$analizor) {
            // Geriye dönük uyum: config dosyasına da bak
            $config = require Yii::getAlias('@app/config/analizor.php');
            return $config[$ekipmanId] ?? null;
        }
        return [
            'ip'        => $analizor->ip,
            'port'      => (int)$analizor->port,
            'device_id' => (int)$analizor->device_id,
            'model'     => $analizor->model,
            'aciklama'  => $analizor->aciklama,
        ];
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

    /**
     * Enerji Analizörleri yönetimi
     */
    public function actionAnalizorIndex()
    {
        $models = \app\models\AnalizorCihaz::find()->orderBy(['ekipman_kodu' => SORT_ASC])->all();
        return $this->render('analizor_index', ['models' => $models]);
    }

    public function actionAnalizorCreate()
    {
        $model = new \app\models\AnalizorCihaz();
        $model->aktif = true;
        $model->port = 502;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Analizör eklendi.');
            return $this->redirect(['ekipman/analizor-index']);
        }

        return $this->render('analizor_form', ['model' => $model, 'title' => 'Yeni Enerji Analizörü']);
    }

    public function actionAnalizorUpdate($id)
    {
        $model = \app\models\AnalizorCihaz::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Analizör bulunamadı.');
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Analizör güncellendi.');
            return $this->redirect(['ekipman/analizor-index']);
        }

        return $this->render('analizor_form', ['model' => $model, 'title' => 'Analizör Düzenle']);
    }

    public function actionAnalizorDelete($id)
    {
        $model = \app\models\AnalizorCihaz::findOne($id);
        if ($model) {
            $model->delete();
            Yii::$app->session->setFlash('success', 'Analizör silindi.');
        }
        return $this->redirect(['ekipman/analizor-index']);
    }

}
