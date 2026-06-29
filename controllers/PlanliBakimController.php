<?php

namespace app\controllers;

use app\models\PlanliBakim;
use app\models\PlanliBakimSearch;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class PlanliBakimController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['create', 'update'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return !\Yii::$app->user->isGuest
                                && in_array(\Yii::$app->user->identity->role, ['admin', 'editor']);
                        },
                    ],
                    [
                        'actions' => ['delete', 'toplu-aktar'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            return !Yii::$app->user->isGuest
                                && Yii::$app->user->identity->role === 'admin';
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'toplu-aktar' => ['POST'],
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

    public function actionTopluAktar()
    {
        $file = UploadedFile::getInstanceByName('planli_bakim_csv');
        if ($file === null) {
            Yii::$app->session->setFlash('error', 'Lütfen CSV dosyası seçiniz.');
            return $this->redirect(['index']);
        }

        $extension = strtolower((string)$file->extension);
        if (!in_array($extension, ['csv', 'txt'], true)) {
            Yii::$app->session->setFlash('error', 'Sadece CSV veya TXT dosyası yüklenebilir.');
            return $this->redirect(['index']);
        }

        $handle = fopen($file->tempName, 'r');
        if ($handle === false) {
            Yii::$app->session->setFlash('error', 'CSV dosyası açılamadı.');
            return $this->redirect(['index']);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $delimiter = $this->detectPlanliBakimCsvDelimiter($file->tempName);
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false) {
                throw new \RuntimeException('CSV dosyası boş görünüyor.');
            }

            $columnMap = $this->buildPlanliBakimImportColumnMap($header);
            foreach (['kodu', 'tanimi', 'periyodu', 'tarihi'] as $requiredAttribute) {
                if (!array_key_exists($requiredAttribute, $columnMap)) {
                    throw new \RuntimeException('CSV başlıklarında kodu, tanimi, periyodu ve tarihi alanları bulunmalıdır.');
                }
            }

            $rowNumber = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;
                if ($this->isPlanliBakimImportRowEmpty($row)) {
                    continue;
                }

                $model = new PlanliBakim();
                $model->kodu = trim((string)$this->getPlanliBakimImportValue($row, $columnMap, 'kodu'));
                $model->tanimi = trim((string)$this->getPlanliBakimImportValue($row, $columnMap, 'tanimi'));
                $model->periyodu = trim((string)$this->getPlanliBakimImportValue($row, $columnMap, 'periyodu'));
                $model->tarihi = $this->normalizePlanliBakimImportDate($this->getPlanliBakimImportValue($row, $columnMap, 'tarihi'));
                $model->durumu = trim((string)$this->getPlanliBakimImportValue($row, $columnMap, 'durumu'));

                $missing = [];
                foreach (['kodu', 'tanimi', 'periyodu', 'tarihi'] as $attribute) {
                    if (trim((string)$model->$attribute) === '') {
                        $missing[] = $attribute;
                    }
                }

                if (!empty($missing)) {
                    $skipped++;
                    $errors[] = $rowNumber . '. satır atlandı: Zorunlu alan eksik (' . implode(', ', $missing) . ').';
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

                $created++;
            }

            $transaction->commit();

            $message = "Planlı bakım CSV aktarımı tamamlandı. Yeni: {$created}, hatalı atlanan: {$skipped}.";
            if (!empty($errors)) {
                $message .= '<br>İlk uyarılar:<br>' . implode('<br>', array_slice(array_map('htmlspecialchars', $errors), 0, 10));
            }
            Yii::$app->session->setFlash($skipped > 0 ? 'warning' : 'success', $message);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Planlı bakım CSV aktarımı sırasında hata oluştu: ' . $e->getMessage());
        } finally {
            fclose($handle);
        }

        return $this->redirect(['index']);
    }

    private function detectPlanliBakimCsvDelimiter(string $path): string
    {
        $line = '';
        $handle = fopen($path, 'r');
        if ($handle !== false) {
            $line = (string)fgets($handle);
            fclose($handle);
        }

        $delimiters = [";", ",", "\t"];
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

    private function buildPlanliBakimImportColumnMap(array $header): array
    {
        $aliases = [
            'kodu' => ['kodu', 'kod', 'ekipman kodu', 'ekipman_kodu', 'ekipman id', 'ekipman_id'],
            'tanimi' => ['tanimi', 'tanım', 'tanimi', 'bakim tanimi', 'bakım tanımı', 'aciklama', 'açıklama'],
            'periyodu' => ['periyodu', 'periyot', 'periyod', 'bakim periyodu', 'bakım periyodu'],
            'tarihi' => ['tarihi', 'tarih', 'bakim tarihi', 'bakım tarihi', 'planlanan tarih'],
            'durumu' => ['durumu', 'durum', 'status'],
        ];

        $normalizedAliases = [];
        foreach ($aliases as $attribute => $labels) {
            foreach ($labels as $label) {
                $normalizedAliases[$this->normalizePlanliBakimImportHeader($label)] = $attribute;
            }
        }

        $map = [];
        foreach ($header as $index => $label) {
            $normalizedLabel = $this->normalizePlanliBakimImportHeader((string)$label);
            if (isset($normalizedAliases[$normalizedLabel])) {
                $map[$normalizedAliases[$normalizedLabel]] = $index;
            }
        }

        return $map;
    }

    private function normalizePlanliBakimImportHeader(string $value): string
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

    private function isPlanliBakimImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function getPlanliBakimImportValue(array $row, array $columnMap, string $attribute)
    {
        if (!array_key_exists($attribute, $columnMap)) {
            return null;
        }

        return $row[$columnMap[$attribute]] ?? null;
    }

    private function normalizePlanliBakimImportDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
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

    protected function findModel($id)
    {
        if (($model = PlanliBakim::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Kayıt bulunamadı.');
    }
}
