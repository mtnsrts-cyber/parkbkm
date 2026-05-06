<?php

namespace app\controllers;

use app\models\PlanliBakim;
use app\models\PlanliBakimSearch;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class PlanliBakimController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'create', 'update'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return !\Yii::$app->user->isGuest
                                && in_array(\Yii::$app->user->identity->role, ['admin', 'editor']);
                        },
                    ],
                    [
                        'actions' => ['delete'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return !\Yii::$app->user->isGuest
                                && \Yii::$app->user->identity->role === 'admin';
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new PlanliBakimSearch();
        $dataProvider = $searchModel->search(\Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionCreate()
    {
        $model = new PlanliBakim();

        // Ana sayfadan gelen hazır planlı bakım verileri
        $request = \Yii::$app->request;
        $fromPlanliId = $request->get('planli_id');

        if ($fromPlanliId && $model->isNewRecord) {
            $source = PlanliBakim::findOne($fromPlanliId);
            if ($source) {
                // Ekipman kodu, tanımı ve periyodu otomatik gelsin
                $model->kodu = $source->kodu;
                $model->tanimi = $source->tanimi;
                $model->periyodu = $source->periyodu;
                // Tarih alanı kullanıcı tarafından takvimden seçilecek
                $model->tarihi = null;
                $model->kaynak_planli_id = (int)$source->id;
            }
        }

        if ($model->load(\Yii::$app->request->post())) {
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                if ((bool)$model->bakim_ertele) {
                    $sourceId = (int)$model->kaynak_planli_id;
                    $source = $sourceId ? PlanliBakim::findOne($sourceId) : null;

                    if (!$source) {
                        $model->addError('bakim_ertele', 'Öteleme için kaynak planlı bakım kaydı bulunamadı.');
                        throw new \RuntimeException('Kaynak planlı bakım kaydı bulunamadı.');
                    }

                    $erteleme = new PlanliBakim();
                    $erteleme->kodu = $source->kodu;
                    $erteleme->tanimi = $source->tanimi;
                    $erteleme->periyodu = $source->periyodu;
                    $erteleme->tarihi = $model->ertelenen_tarih;
                    $erteleme->durumu = PlanliBakim::DURUM_OTELEME;

                    if (!$erteleme->save()) {
                        foreach ($erteleme->getFirstErrors() as $message) {
                            $model->addError('ertelenen_tarih', $message);
                        }
                        throw new \RuntimeException('Öteleme kaydı oluşturulamadı.');
                    }

                    $transaction->commit();
                    \Yii::$app->session->setFlash('success', 'Planlı bakım başarıyla ötelendi.');
                    return $this->redirect(['index']);
                }

                if (!$model->save()) {
                    throw new \RuntimeException('Planlı bakım kaydı oluşturulamadı.');
                }

                $transaction->commit();
                return $this->redirect(['index']);
            } catch (\Throwable $e) {
                $transaction->rollBack();
                if (!$model->hasErrors()) {
                    \Yii::$app->session->setFlash('error', 'Kayıt sırasında hata oluştu: ' . $e->getMessage());
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
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
        if (($model = PlanliBakim::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Kayıt bulunamadı.');
    }
}
