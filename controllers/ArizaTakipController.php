<?php

namespace app\controllers;

use app\models\ArizaTakip;
use app\models\ArizaTakipSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\web\Response;
use yii\db\Expression;

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
                            'actions' => ['update', 'delete'],
                            'allow' => true,
                            'roles' => ['@'],
                            'matchCallback' => function ($rule, $action) {
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
}
