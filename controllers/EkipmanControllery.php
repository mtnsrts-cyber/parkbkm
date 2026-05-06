
<?php

namespace app\controllers;

use Yii;
use app\models\Ekipman;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\helpers\ArrayHelper;
use yii\web\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\filters\AccessControl;

class EkipmanController extends Controller
{


public function behaviors()
{
    return [
        'access' => [
            'class' => AccessControl::class,
            'only' => ['index', 'view', 'create', 'update', 'delete'],
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@'], // sadece giriş yapmış kullanıcılar
                ],
                [
                    'allow' => true,
                    'actions' => ['index', 'view'],
                    'roles' => ['atolyeSorumlusu'], // özel rol
                ],
                [
                    'allow' => false, // diğerleri erişemez
                ],
            ],
        ],
    ];
}

public function actionAddBakim()
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    $post = Yii::$app->request->post();
    $file = \yii\web\UploadedFile::getInstanceByName('dosya');

    if (!$post['id'] || !$post['islem'] || !$post['personel'] || !$post['tarih']) {
        return ['success' => false, 'message' => 'Eksik bilgi'];
    }

    $dosyaYolu = null;
    if ($file) {
        $klasor = Yii::getAlias('@webroot/uploads/bakim/');
        if (!is_dir($klasor)) mkdir($klasor, 0775, true);

        $dosyaAdi = 'bakim_' . time() . '_' . $file->baseName . '.' . $file->extension;
        $dosyaYolu = $klasor . $dosyaAdi;
        $file->saveAs($dosyaYolu);
    }

    // Burada model kaydı yapılabilir (örnek)
    // $bakim = new Bakim([...]);
    // $bakim->dosya = '/uploads/bakim/' . $dosyaAdi;

    return [
        'success' => true,
        'message' => 'Bakım kaydı eklendi',
        'dosya' => $dosyaYolu ? '/uploads/bakim/' . $dosyaAdi : null,
    ];
}

}