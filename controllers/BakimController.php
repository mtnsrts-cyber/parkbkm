<?php

namespace app\controllers;

use Yii;
use app\models\Bakim;
use app\models\BakimSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class BakimController extends Controller
{
    public function actionIndex()
    {
        $searchModel = new BakimSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider
        ]);
    }

    public function actionCreate()
    {
        $model = new Bakim();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        }

        return $this->render('update', ['model' => $model]);
    }

    protected function findModel($id)
    {
        if (($model = Bakim::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Kayıt bulunamadı.');
    }
}
