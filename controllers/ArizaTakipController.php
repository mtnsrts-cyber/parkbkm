<?php

namespace app\controllers;

use app\models\ArizaTakip;
use app\models\ArizaTakipSearch;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\web\Response;
use yii\db\Expression;
use yii\web\UploadedFile;

class ArizaTakipController extends Controller
{
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
                            'allow' => true,
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
                            'actions' => ['view'],
                            'allow' => true, // Herkes (Misafir + Üye)
                        ],
                        [
                            'actions' => ['create'],
                            'allow' => true,
                            'roles' => ['@'],
                            'matchCallback' => function ($rule, $action) {
                                return in_array(\Yii::$app->user->identity->role, ['admin','editor']);
                            }
                        ],
                        [
                            'actions' => ['update', 'delete', 'toplu-aktar'],
                            'allow' => true,
                            'roles' => ['@'],
                            'matchCallback' => function ($rule, $action) {
                                return Yii::$app->user->identity->role === 'admin';
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

    public function actionIndex()
    {
        $searchModel = new ArizaTakipSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        // Varsayılan sıralama: önce son duruma göre, sonra BİLDİRİM tarihine göre yeni -> eski
        if ($this->request->get('sort') === null) {
            $dataProvider->query->orderBy(new Expression(
                "CASE ".
                " WHEN ARIZANIN_SON_DURUMU = 'gayri faal' THEN 0".
                " WHEN ARIZANIN_SON_DURUMU = 'arızalı faal' THEN 1".
                " ELSE 2".
                " END ASC, " .
                "ARIZA_BILDIRIM_TARIHI DESC, " .
                "id DESC"
            ));
        }

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionExportExcel()
    {
        $searchModel = new ArizaTakipSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);
        $dataProvider->pagination = false;
        // Excel çıktısında tarih bazlı eski -> yeni sıralama
        if ($dataProvider->sort !== false) {
            $dataProvider->sort->defaultOrder = [
                'ARIZA_TARIHI' => SORT_ASC,
                'id' => SORT_ASC,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Sıra No',
            'Bildirim Tarihi',
            'Arıza Tarihi',
            'Arızayı Bildiren',
            'Arızaya Sebebiyet Veren Firma',
            'Arızalanan Makine Adı',
            'Arızalanan Makine Kodu',
            'Arızalanan Parça',
            'Arızanın Meydana Geldiği Bölüm',
            'Arıza Kök Nedeni',
            'Kalıcı Aksiyon',
            'Arıza Sebebi',
            'Arızanın Giderildiği Tarih',
            'Arızanın Son Durumu',
            'Arızalı Kaldığı Süre (Saat)',
            'Yedek Parça Bekleme Süresi (Saat)',
            'Malzeme Tutarı',
            'İşçilik Fiyatı',
            'Maliyet (TL)',
            'Arızanın Ayrıntılı Açıklaması',
        ];

        $columnLetters = range('A', 'T');

        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columnLetters[$index] . '1', $header);
        }

        $row = 2;
        foreach ($dataProvider->getModels() as $model) {
            /** @var ArizaTakip $model */
            $sheet->setCellValue('A' . $row, $model->id);
            $sheet->setCellValue('B' . $row, \Yii::$app->formatter->asDate($model->ARIZA_BILDIRIM_TARIHI, 'php:d.m.Y'));
            $sheet->setCellValue('C' . $row, \Yii::$app->formatter->asDate($model->ARIZA_TARIHI, 'php:d.m.Y'));
            $sheet->setCellValue('D' . $row, $model->ARIZAYI_BILDIREN);
            $sheet->setCellValue('E' . $row, $model->ARIZAYA_SEBEBIYET_VEREN_FIRMA);
            $sheet->setCellValue('F' . $row, $model->ARIZALANAN_MAKINE_ADI);
            $sheet->setCellValue('G' . $row, $model->ARIZALANAN_MAKINE_KODU);
            $sheet->setCellValue('H' . $row, $model->ARIZALANAN_PARCA);
            $sheet->setCellValue('I' . $row, $model->ARIZANIN_MEYDANA_GELDIGI_BOLUM);
            $sheet->setCellValue('J' . $row, $model->ARIZA_KOK_NEDENI);
            $sheet->setCellValue('K' . $row, $model->KALICI_AKSIYON);
            $sheet->setCellValue('L' . $row, $model->ARIZA_SEBEBI);
            $sheet->setCellValue('M' . $row, \Yii::$app->formatter->asDate($model->ARIZANIN_GIDERILDIGI_TARIH, 'php:d.m.Y'));
            $sheet->setCellValue('N' . $row, $model->ARIZANIN_SON_DURUMU);
            $sheet->setCellValue('O' . $row, $model->ARIZALI_KALDIGI_SURE_SAAT);
            $sheet->setCellValue('P' . $row, $model->YEDEK_PARCA_BEKLEME_SURESI_SAAT);
            $sheet->setCellValue('Q' . $row, $model->MALZEME_TUTARI);
            $sheet->setCellValue('R' . $row, $model->ISCILIK_FIYATI);
            $sheet->setCellValue('S' . $row, $model->MALIYET_TL);
            $sheet->setCellValue('T' . $row, $model->ARIZANIN_AYRINTILI_ACIKLAMASI);
            $row++;
        }

        // Q, R, S sütunlarına para birimi formatı uygula
        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $currencyFormat = '#,##0.00\ "₺"';
            foreach (['Q', 'R', 'S'] as $col) {
                $sheet->getStyle($col . '2:' . $col . $lastRow)
                    ->getNumberFormat()
                    ->setFormatCode($currencyFormat);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'ariza_takip_' . date('Ymd_His') . '.xlsx';
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

        $response->on(Response::EVENT_AFTER_SEND, function () use ($path) {
            @unlink($path);
        });

        return $response;
    }

    public function actionTopluAktar()
    {
        $file = UploadedFile::getInstanceByName('ariza_excel');
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
                $reader->setDelimiter($this->detectArizaCsvDelimiter($file->tempName));
                $reader->setInputEncoding('UTF-8');
                $spreadsheet = $reader->load($file->tempName);
            } else {
                $spreadsheet = IOFactory::load($file->tempName);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $rows = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, true);

            $headerRowNumber = $this->findArizaImportHeaderRow($rows);
            if ($headerRowNumber === null) {
                throw new \RuntimeException('Başlık satırı bulunamadı. En az Bildirim Tarihi, Makine Adı/Kodu ve Son Durum başlıkları olmalı.');
            }

            $columnMap = $this->buildArizaImportColumnMap($rows[$headerRowNumber]);
            $created = 0;
            $skipped = 0;
            $errors = [];

            for ($rowNumber = $headerRowNumber + 1; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $rows[$rowNumber] ?? [];
                if ($this->isArizaImportRowEmpty($row)) {
                    continue;
                }

                $model = new ArizaTakip();
                $model->loadDefaultValues();

                foreach ([
                    'ARIZAYI_BILDIREN',
                    'ARIZAYA_SEBEBIYET_VEREN_FIRMA',
                    'ARIZALANAN_MAKINE_ADI',
                    'ARIZALANAN_MAKINE_KODU',
                    'ARIZALANAN_PARCA',
                    'ARIZANIN_MEYDANA_GELDIGI_BOLUM',
                    'ARIZA_KOK_NEDENI',
                    'KALICI_AKSIYON',
                    'ARIZA_SEBEBI',
                    'ARIZANIN_AYRINTILI_ACIKLAMASI',
                ] as $attribute) {
                    $value = $this->getArizaImportCellValue($row, $columnMap, $attribute);
                    if ($value !== null) {
                        $model->$attribute = trim((string)$value);
                    }
                }

                $model->ARIZA_BILDIRIM_TARIHI = $this->normalizeArizaImportDate($this->getArizaImportCellValue($row, $columnMap, 'ARIZA_BILDIRIM_TARIHI'));
                $model->ARIZA_TARIHI = $this->normalizeArizaImportDate($this->getArizaImportCellValue($row, $columnMap, 'ARIZA_TARIHI'));
                $model->ARIZANIN_GIDERILDIGI_TARIH = $this->normalizeArizaImportDate($this->getArizaImportCellValue($row, $columnMap, 'ARIZANIN_GIDERILDIGI_TARIH'));
                $model->ARIZANIN_SON_DURUMU = $this->normalizeArizaImportStatus($this->getArizaImportCellValue($row, $columnMap, 'ARIZANIN_SON_DURUMU'));

                foreach (['ARIZALI_KALDIGI_SURE_SAAT', 'YEDEK_PARCA_BEKLEME_SURESI_SAAT', 'MALZEME_TUTARI', 'ISCILIK_FIYATI', 'MALIYET_TL'] as $attribute) {
                    $model->$attribute = $this->normalizeArizaImportNumber($this->getArizaImportCellValue($row, $columnMap, $attribute));
                }

                if (!$model->save()) {
                    $skipped++;
                    $message = implode(' | ', array_map(static function ($items) {
                        return implode(', ', $items);
                    }, $model->getErrors()));
                    $errors[] = $rowNumber . '. satır kaydedilemedi: ' . $message;
                    continue;
                }

                $created++;
            }

            $transaction->commit();

            $message = "Toplu arıza aktarımı tamamlandı. Yeni: {$created}, hatalı atlanan: {$skipped}.";
            if (!empty($errors)) {
                $message .= '<br>İlk uyarılar:<br>' . implode('<br>', array_slice(array_map('htmlspecialchars', $errors), 0, 10));
            }
            Yii::$app->session->setFlash($skipped > 0 ? 'warning' : 'success', $message);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Toplu arıza aktarımı sırasında hata oluştu: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new ArizaTakip();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = ArizaTakip::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    private function detectArizaCsvDelimiter(string $path): string
    {
        $line = '';
        $handle = fopen($path, 'r');
        if ($handle !== false) {
            $line = (string)fgets($handle);
            fclose($handle);
        }

        $delimiters = [';', ',', "\t"];
        $bestDelimiter = ';';
        $bestCount = 0;
        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function findArizaImportHeaderRow(array $rows): ?int
    {
        foreach ($rows as $rowNumber => $row) {
            $map = $this->buildArizaImportColumnMap($row);
            if (isset($map['ARIZA_BILDIRIM_TARIHI'], $map['ARIZALANAN_MAKINE_ADI'], $map['ARIZALANAN_MAKINE_KODU'], $map['ARIZANIN_SON_DURUMU'])) {
                return (int)$rowNumber;
            }
        }

        return null;
    }

    private function buildArizaImportColumnMap(array $headerRow): array
    {
        $aliases = [
            'ARIZA_BILDIRIM_TARIHI' => ['ARIZA_BILDIRIM_TARIHI', 'Bildirim Tarihi', 'Arıza Bildirim Tarihi'],
            'ARIZA_TARIHI' => ['ARIZA_TARIHI', 'Arıza Tarihi'],
            'ARIZAYI_BILDIREN' => ['ARIZAYI_BILDIREN', 'Arızayı Bildiren'],
            'ARIZAYA_SEBEBIYET_VEREN_FIRMA' => ['ARIZAYA_SEBEBIYET_VEREN_FIRMA', 'Arızaya Sebebiyet Veren Firma'],
            'ARIZALANAN_MAKINE_ADI' => ['ARIZALANAN_MAKINE_ADI', 'Arızalanan Makine Adı', 'Makine Adı'],
            'ARIZALANAN_MAKINE_KODU' => ['ARIZALANAN_MAKINE_KODU', 'Arızalanan Makine Kodu', 'Makine Kodu', 'Ekipman Kodu', 'Kodu'],
            'ARIZALANAN_PARCA' => ['ARIZALANAN_PARCA', 'Arızalanan Parça'],
            'ARIZANIN_MEYDANA_GELDIGI_BOLUM' => ['ARIZANIN_MEYDANA_GELDIGI_BOLUM', 'Arızanın Meydana Geldiği Bölüm', 'Bölüm'],
            'ARIZA_KOK_NEDENI' => ['ARIZA_KOK_NEDENI', 'Arıza Kök Nedeni'],
            'KALICI_AKSIYON' => ['KALICI_AKSIYON', 'Kalıcı Aksiyon'],
            'ARIZA_SEBEBI' => ['ARIZA_SEBEBI', 'Arıza Sebebi'],
            'ARIZANIN_GIDERILDIGI_TARIH' => ['ARIZANIN_GIDERILDIGI_TARIH', 'Arızanın Giderildiği Tarih', 'Giderildiği Tarih'],
            'ARIZANIN_SON_DURUMU' => ['ARIZANIN_SON_DURUMU', 'Arızanın Son Durumu', 'Son Durum', 'Durum'],
            'ARIZALI_KALDIGI_SURE_SAAT' => ['ARIZALI_KALDIGI_SURE_SAAT', 'Arızalı Kaldığı Süre (Saat)', 'Arızalı Kaldığı Süre'],
            'YEDEK_PARCA_BEKLEME_SURESI_SAAT' => ['YEDEK_PARCA_BEKLEME_SURESI_SAAT', 'Yedek Parça Bekleme Süresi (Saat)'],
            'MALZEME_TUTARI' => ['MALZEME_TUTARI', 'Malzeme Tutarı'],
            'ISCILIK_FIYATI' => ['ISCILIK_FIYATI', 'İşçilik Fiyatı', 'İşçilik Birim Fiyatı'],
            'MALIYET_TL' => ['MALIYET_TL', 'Maliyet (TL)', 'Maliyet'],
            'ARIZANIN_AYRINTILI_ACIKLAMASI' => ['ARIZANIN_AYRINTILI_ACIKLAMASI', 'Arızanın Ayrıntılı Açıklaması', 'Açıklama'],
        ];

        $normalizedAliases = [];
        foreach ($aliases as $attribute => $labels) {
            foreach ($labels as $label) {
                $normalizedAliases[$this->normalizeArizaImportHeader($label)] = $attribute;
            }
        }

        $map = [];
        foreach ($headerRow as $column => $label) {
            $normalizedLabel = $this->normalizeArizaImportHeader((string)$label);
            if (isset($normalizedAliases[$normalizedLabel])) {
                $map[$normalizedAliases[$normalizedLabel]] = $column;
            }
        }

        return $map;
    }

    private function normalizeArizaImportHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = strtr($value, [
            'Ç' => 'c', 'ç' => 'c', 'Ğ' => 'g', 'ğ' => 'g', 'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ö' => 'o', 'ö' => 'o', 'Ş' => 's', 'ş' => 's', 'Ü' => 'u', 'ü' => 'u',
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim((string)$value);
    }

    private function isArizaImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function getArizaImportCellValue(array $row, array $columnMap, string $attribute)
    {
        if (!array_key_exists($attribute, $columnMap)) {
            return null;
        }

        return $row[$columnMap[$attribute]] ?? null;
    }

    private function normalizeArizaImportDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizeArizaImportStatus($value): string
    {
        $value = $this->normalizeArizaImportHeader((string)$value);
        $statuses = [
            'faal' => 'FAAL',
            'gayri faal' => 'GAYRI_FAAL',
            'gayrifaal' => 'GAYRI_FAAL',
            'gayri_faal' => 'GAYRI_FAAL',
            'arizali faal' => 'ARIZALI_FAAL',
            'arizalifaal' => 'ARIZALI_FAAL',
            'arizali_faal' => 'ARIZALI_FAAL',
        ];

        return $statuses[$value] ?? 'FAAL';
    }

    private function normalizeArizaImportNumber($value): ?float
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return (float)str_replace(',', '.', $value);
    }
}
