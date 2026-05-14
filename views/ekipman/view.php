<?php
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;
use yii\grid\GridView;
use yii\bootstrap4\Tabs;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

$url = Yii::$app->urlManager->createAbsoluteUrl(['ekipman/view', 'id' => $model->id]);

// QR Code
try {
    $qrResult = Builder::create()
        ->writer(new SvgWriter())
        ->data($url)
        ->size(220)
        ->margin(10)
        ->build();

    $qrCode = $qrResult->getDataUri();
} catch (\Throwable $e) {
    Yii::error('QR kod oluşturma hatası: ' . $e->getMessage(), __METHOD__);
    $qrCode = null;
}

$tanitimFotoUrl = null;
if (!empty($model->TANITIM_FOTO)) {
    $tanitimFotoUrl = Yii::getAlias('@web/uploads/' . str_replace('%2F', '/', implode('/', array_map('rawurlencode', explode('/', ltrim((string)$model->TANITIM_FOTO, '/'))))));
}

$canManageTanitimFoto = !Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true);

if ($canManageTanitimFoto) {
    $this->registerJs("(function(){
        $(document).on('change', '#tanitimFotoFileInput, #tanitimFotoCameraInput', function(){
            if (this.files && this.files.length > 0) {
                $(this).closest('form').trigger('submit');
            }
        });
    })();");
}

// Mobil kart genişletme/daraltma
$this->registerJs("
$(document).on('click', '.card-expand', function(e) {
    if ($(e.target).closest('a,button').length) return;
    var t = document.getElementById($(this).data('target'));
    if (!t) return;
    var open = t.style.display === 'none';
    t.style.display = open ? '' : 'none';
    var arrow = $(this).find('.card-expand-arrow');
    if (arrow.length) arrow.text(open ? '▲' : '▼');
});
");

$this->title = $model->id . ' | | ' . $model->MALZEMENIN_TANIMI;
$this->params['breadcrumbs'][] = ['label' => 'Ekipman', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Asset'leri manuel ekleyin
$this->registerCssFile("https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css");
$this->registerJsFile("https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js");

// Leaflet
$this->registerJsFile(Yii::getAlias('@web/vendor/leaflet/leaflet.js'), ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile(Yii::getAlias('@web/vendor/leaflet/leaflet.css'));

// RESİM YOLU KONTROLÜ
$imageUrl = Yii::getAlias('@web/images/SahaPano.png');
$imageWidth = 6000;
$imageHeight = 1250;

// Park Yüzer Havuz resmi (detay katmanı)
$parkYuzerImage = Yii::getAlias('@web/images/ParkYuzerHavuz.png');

// Hangi resmi göstereceğiz: eğer ekipman YÜZER HAVUZ-3'e aitse detay resmi, değilse saha panoraması
$displayImage = $imageUrl;
$displayWidth = $imageWidth;
$displayHeight = $imageHeight;

$isYuzerHavuzEquipment = static function (?string $id, ?string $location = null): bool {
    if ($id !== null && $id !== '') {
        $idUpper = mb_strtoupper($id, 'UTF-8');
        $idUpper = strtr($idUpper, [
            'İ' => 'I',
            'İ' => 'I',
            'Ş' => 'S',
            'Ğ' => 'G',
            'Ü' => 'U',
            'Ö' => 'O',
            'Ç' => 'C',
        ]);
        $prefix = explode('-', $idUpper, 2)[0] ?? '';
        $prefix = preg_replace('/[^A-Z0-9]+/u', '', $prefix);

        if (strpos($prefix, 'YHY') === 0 || strpos($prefix, 'YHK') === 0 || strpos($prefix, 'YH') === 0) {
            return true;
        }
    }

    if ($location === null) {
        return false;
    }

    $normalized = mb_strtoupper($location, 'UTF-8');
    $normalized = strtr($normalized, [
        'İ' => 'I',
        'İ' => 'I',
        'Ş' => 'S',
        'Ğ' => 'G',
        'Ü' => 'U',
        'Ö' => 'O',
        'Ç' => 'C',
    ]);
    $normalized = preg_replace('/[^A-Z0-9]+/u', '', $normalized);

    return strpos($normalized, 'YUZERHAVUZ') !== false;
};

if ($isYuzerHavuzEquipment((string)$model->id, $model->EKIPMAN_YERI)) {
    // Dosya sisteminde gerçek dosya yolunu kontrol et (webroot/images)
    $parkPath = Yii::getAlias('@webroot/images/ParkYuzerHavuz.png');
    if (file_exists($parkPath)) {
        $displayImage = $parkYuzerImage;
        // Resim boyutlarını otomatik al (gerekirse)
        $size = @getimagesize($parkPath);
        if ($size && isset($size[0]) && isset($size[1])) {
            $displayWidth = $size[0];
            $displayHeight = $size[1];
        } else {
            // fallback boyutlar
            $displayWidth = 7864;
            $displayHeight = 5554;
        }
    } else {
        // Park resmi yoksa fallback olarak saha pano göster
        $displayImage = $imageUrl;
        $displayWidth = $imageWidth;
        $displayHeight = $imageHeight;
        $this->registerJs("console.warn('ParkYuzerHavuz.png bulunamadı; Saha pano gösteriliyor. Dosyayı web/images içine yükleyin.');");
    }
}

$markerX = !empty($model->BOYLAM) ? $model->BOYLAM : ($displayWidth / 2);
$markerY = !empty($model->ENLEM) ? $model->ENLEM : ($displayHeight / 2);
?>

<div class="ekipman-view">
    <div class="card bg-dark text-white border-secondary mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-lg-9 col-md-8 mb-3 mb-md-0">
                    <h2 class="mb-2"><?= Html::encode($this->title) ?></h2>
                    <div class="text-muted mb-3">
                        <?= Html::encode((string)$model->id) ?>
                        <?php if (!empty($model->EKIPMAN_YERI)): ?>
                            | <?= Html::encode((string)$model->EKIPMAN_YERI) ?>
                        <?php endif; ?>
                        <?php if (!empty($model->EKIPMAN_TURU)): ?>
                            | <?= Html::encode((string)$model->EKIPMAN_TURU) ?>
                        <?php endif; ?>
                    </div>

                    <p class="mb-0">
                        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
                            <?= Html::a('Güncelle', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
                            <?= Html::a('Sil', ['delete', 'id' => $model->id], [
                                'class' => 'btn btn-danger',
                                'data' => ['confirm' => 'Silmek istediğinize emin misiniz?', 'method' => 'post'],
                            ]) ?>
                            <?php if (strtoupper((string)$model->DURUM) === 'HURDA'): ?>
                                <?= Html::a('Aktife Al', ['aktife-al', 'id' => $model->id], [
                                    'class' => 'btn btn-success',
                                    'data' => ['confirm' => 'Bu ekipmanı tekrar aktif yapmak istiyor musunuz?', 'method' => 'post'],
                                ]) ?>
                            <?php else: ?>
                                <?= Html::a('Hurdaya Ayır', ['hurdaya-ayir', 'id' => $model->id], [
                                    'class' => 'btn btn-warning',
                                    'data' => ['confirm' => 'Bu ekipmanı hurdaya ayırmak istiyor musunuz?', 'method' => 'post'],
                                ]) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-lg-3 col-md-4 text-center">
                    <div class="border border-secondary rounded p-2 bg-black">
                        <?php if ($tanitimFotoUrl !== null): ?>
                            <a href="#" data-toggle="modal" data-target="#tanitimFotoPreviewModal" class="d-block" title="Büyük önizleme aç">
                                <img src="<?= Html::encode($tanitimFotoUrl . '?v=' . urlencode((string)($model->ekipmanEk->updated_at ?? time()))) ?>" alt="<?= Html::encode($model->MALZEMENIN_TANIMI) ?>" class="img-fluid rounded" style="max-height: 220px; width: 100%; object-fit: cover; cursor: zoom-in;">
                            </a>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center text-muted" style="min-height: 220px;">
                                Tanıtım fotoğrafı yok
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (Yii::$app->session->hasFlash('success')): ?>
                        <div class="alert alert-success py-2 mt-2 mb-0 text-left"><?= Html::encode(Yii::$app->session->getFlash('success')) ?></div>
                    <?php endif; ?>
                    <?php if (Yii::$app->session->hasFlash('error')): ?>
                        <div class="alert alert-danger py-2 mt-2 mb-0 text-left"><?= Html::encode(Yii::$app->session->getFlash('error')) ?></div>
                    <?php endif; ?>
                    <?php if (Yii::$app->session->hasFlash('info')): ?>
                        <div class="alert alert-info py-2 mt-2 mb-0 text-left"><?= Html::encode(Yii::$app->session->getFlash('info')) ?></div>
                    <?php endif; ?>

                    <?php if ($canManageTanitimFoto): ?>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary btn-sm btn-block" type="button" data-toggle="collapse" data-target="#tanitimFotoYonetimPanel" aria-expanded="false" aria-controls="tanitimFotoYonetimPanel">
                                Fotoğrafı Yönet
                            </button>

                            <div class="collapse mt-2" id="tanitimFotoYonetimPanel">
                                <div class="border border-secondary rounded p-2 text-left">
                                    <div class="small text-muted mb-2">Bir kaynak seçin. Dosya seçildiğinde yükleme otomatik başlar.</div>

                                    <form action="<?= Url::to(['tanitim-foto-yukle', 'id' => $model->id]) ?>" method="post" enctype="multipart/form-data" class="mb-2">
                                        <input type="hidden" name="_csrf" value="<?= Html::encode(Yii::$app->request->csrfToken) ?>">
                                        <input type="file" id="tanitimFotoFileInput" name="tanitim_foto" accept="image/jpeg,image/png,image/webp" class="d-none">
                                        <label for="tanitimFotoFileInput" class="btn btn-primary btn-sm btn-block mb-0">Dosyalarımdan Seç</label>
                                    </form>

                                    <form action="<?= Url::to(['tanitim-foto-yukle', 'id' => $model->id]) ?>" method="post" enctype="multipart/form-data" class="mb-0">
                                        <input type="hidden" name="_csrf" value="<?= Html::encode(Yii::$app->request->csrfToken) ?>">
                                        <input type="file" id="tanitimFotoCameraInput" name="tanitim_foto" accept="image/*" capture="environment" class="d-none">
                                        <label for="tanitimFotoCameraInput" class="btn btn-outline-info btn-sm btn-block mb-0">Kamera ile Çek</label>
                                    </form>

                                    <?php if ($tanitimFotoUrl !== null): ?>
                                        <form action="<?= Url::to(['tanitim-foto-sil', 'id' => $model->id]) ?>" method="post" class="mt-2 mb-0">
                                            <input type="hidden" name="_csrf" value="<?= Html::encode(Yii::$app->request->csrfToken) ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm btn-block" onclick="return confirm('Tanıtım fotoğrafını kaldırmak istiyor musunuz?');">Fotoğrafı Kaldır</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tanitimFotoUrl !== null): ?>
        <div class="modal fade" id="tanitimFotoPreviewModal" tabindex="-1" role="dialog" aria-labelledby="tanitimFotoPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                <div class="modal-content bg-dark text-white border-secondary">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title" id="tanitimFotoPreviewLabel">Tanıtım Fotoğrafı Önizleme</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Kapat">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center bg-black">
                        <img src="<?= Html::encode($tanitimFotoUrl . '?v=' . urlencode((string)($model->ekipmanEk->updated_at ?? time()))) ?>" alt="<?= Html::encode($model->MALZEMENIN_TANIMI) ?>" class="img-fluid rounded" style="max-height: 80vh; object-fit: contain;">
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="ekipmanTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="detaylar-tab" data-toggle="tab" href="#detaylar" role="tab">Detaylar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="planli-bakim-tab" data-toggle="tab" href="#planli-bakim" role="tab">Planlı Bakımlar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="islemler-tab" data-toggle="tab" href="#islemler" role="tab">İşlemler</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="dokumanlar-tab" data-toggle="tab" href="#dokumanlar" role="tab">Dökümanlar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="konum-tab" data-toggle="tab" href="#konum" role="tab">Konum</a>
        </li>
        </ul>
    
    <div class="tab-content" id="ekipmanTabContent">
        <div class="tab-pane fade show active" id="detaylar" role="tabpanel">
            <?= DetailView::widget([
                'model' => $model,
                'options' => ['class' => 'table table-sm table-hover table-dark'],
                'attributes' => [
                    'id',
                    [
                        'attribute' => 'DURUM',
                        'value' => strtoupper((string)$model->DURUM) === 'HURDA' ? 'HURDA' : 'AKTİF',
                    ],
                    'MALZEMENIN_TANIMI:ntext',
                    'EKIPMAN_YERI:ntext',
                    'EKIPMAN_CINSI:ntext',
                    'EKIPMAN_TURU:ntext','MARKA:ntext',
                    'SERI_NO:ntext','TIP:ntext','VARSA_DIGER_TANITICI_BILGI:ntext',
                    'MIKTAR:ntext',
                    [
                        'attribute' => 'IMAL_YILI',
                        'format' => 'raw',
                        'value' => function ($model) {
                            return empty($model->IMAL_YILI) ? '' : Html::encode((string)$model->IMAL_YILI);
                        },
                    ],
                    'NOTLAR:ntext',
                ],
            ]) ?>

            <!-- Periyodik Kontroller -->
            <div class="mt-4">
                <div class="d-flex align-items-center mb-2">
                    <h5 class="mb-0">Periyodik Kontroller</h5>
                    <?php if (isset($nextPeriyodikKontrol) && $nextPeriyodikKontrol): ?>
                        <?php
                        try {
                            $gelecek = new \DateTime($nextPeriyodikKontrol->gelecek_kontrol_tarihi);
                            $today = new \DateTime('today');

                            $son = null;
                            if (!empty($nextPeriyodikKontrol->son_kontrol_tarihi)) {
                                $son = new \DateTime($nextPeriyodikKontrol->son_kontrol_tarihi);
                            }

                            if ($gelecek <= $today) {
                                $remainingDays = 0;
                                $percent = 100;
                                $barClass = 'bg-danger';
                                $label = 'GECİKMİŞ';
                            } else {
                                if ($son) {
                                    $totalDays = max(1, $son->diff($gelecek)->days);
                                } else {
                                    $totalDays = max(1, $today->diff($gelecek)->days);
                                }

                                $remainingDays = $today->diff($gelecek)->days;
                                $completedDays = max(0, $totalDays - $remainingDays);
                                $percent = max(5, min(100, round($completedDays * 100 / $totalDays)));
                                $fractionRemaining = $remainingDays / $totalDays;

                                if ($fractionRemaining <= 0.2) {
                                    $barClass = 'bg-danger';
                                } elseif ($fractionRemaining <= 0.5) {
                                    $barClass = 'bg-warning';
                                } else {
                                    $barClass = 'bg-success';
                                }

                                $label = $remainingDays . ' gün kaldı';
                            }
                        } catch (\Exception $e) {
                            $percent = 0;
                            $barClass = 'bg-secondary';
                            $label = 'Tarih hesaplanamadı';
                        }
                        ?>
                        <div class="progress position-relative ml-3" style="width:260px; height: 22px;">
                            <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= $percent ?>%; opacity: 0.4;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            <span class="position-absolute w-100 text-center small" style="color: #fff; line-height: 22px;">
                                <?= Html::encode($label) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?= GridView::widget([
                    'dataProvider' => $periyodikKontrolDataProvider,
                    'tableOptions' => ['class' => 'table table-sm table-striped table-bordered d-none d-md-table'],
                    'rowOptions' => function ($model) {
                        if (!empty($model->gelecek_kontrol_tarihi)) {
                            try {
                                $gelecek = new \DateTime($model->gelecek_kontrol_tarihi);
                                $today = new \DateTime('today');

                                if ($gelecek < $today) {
                                    return ['class' => 'table-danger'];
                                }

                                $diffDays = $today->diff($gelecek)->days;
                                if ($diffDays <= 30) {
                                    return ['class' => 'table-warning'];
                                }

                                return ['class' => 'table-success'];
                            } catch (\Exception $e) {
                                return [];
                            }
                        }
                        return [];
                    },
                    'columns' => [
                        'cihaz_adi',
                        'simkal_kodu',
                        [
                            'attribute' => 'rapor_no',
                            'format' => 'raw',
                            'value' => function ($model) {
                                $raporNo = trim((string)$model->rapor_no);
                                if ($raporNo === '') {
                                    return '';
                                }
                                if (preg_match('/(\d{6}\.\d{4}\.\d{1,2})/', $raporNo, $m)) {
                                    $base = $m[1];
                                } else {
                                    $base = $raporNo;
                                }

                                $fileName = $base . '.pdf';
                                $filePath = Yii::getAlias('@webroot/uploads/periyodik-raporlar/' . $fileName);

                                if (file_exists($filePath)) {
                                    $url = Yii::getAlias('@web/uploads/periyodik-raporlar/' . $fileName);
                                    return Html::a(Html::encode($raporNo), $url, [
                                        'target' => '_blank',
                                        'data-pjax' => 0,
                                    ]);
                                }

                                return Html::encode($raporNo);
                            },
                        ],
                        'bulundugu_yer',
                        'adet',
                        'kabul_degerleri',
                        'olcum_degerleri',
                        [
                            'attribute' => 'son_kontrol_tarihi',
                            'format' => ['date', 'php:d.m.Y'],
                        ],
                        [
                            'attribute' => 'gelecek_kontrol_tarihi',
                            'format' => ['date', 'php:d.m.Y'],
                        ],
                    ],
                    'summary' => '',
                    'emptyText' => 'Bu ekipman için periyodik kontrol kaydı bulunmamaktadır.',
                ]) ?>

                <!-- Periyodik Kontrol: Mobil kart görünümü -->
                <div class="d-md-none">
                    <?php
                    $pkModels = $periyodikKontrolDataProvider->getModels();
                    if (empty($pkModels)): ?>
                        <span class="text-muted">Bu ekipman için periyodik kontrol kaydı bulunmamaktadır.</span>
                    <?php else: ?>
                        <?php foreach ($pkModels as $pk):
                            $pkUid = 'pk_' . $pk->id;
                            // Renk belirle
                            $pkBorder = 'border-secondary';
                            try {
                                if (!empty($pk->gelecek_kontrol_tarihi)) {
                                    $pkGelecek = new \DateTime($pk->gelecek_kontrol_tarihi);
                                    $pkToday = new \DateTime('today');
                                    if ($pkGelecek < $pkToday) {
                                        $pkBorder = 'border-danger';
                                    } elseif ($pkToday->diff($pkGelecek)->days <= 30) {
                                        $pkBorder = 'border-warning';
                                    } else {
                                        $pkBorder = 'border-success';
                                    }
                                }
                            } catch (\Exception $e) {}
                            // Rapor linki
                            $raporNo = trim((string)($pk->rapor_no ?? ''));
                            $raporHtml = Html::encode($raporNo);
                            if ($raporNo !== '') {
                                if (preg_match('/(\d{6}\.\d{4}\.\d{1,2})/', $raporNo, $m)) {
                                    $base = $m[1];
                                } else {
                                    $base = $raporNo;
                                }
                                $fileName = $base . '.pdf';
                                $fPath = Yii::getAlias('@webroot/uploads/periyodik-raporlar/' . $fileName);
                                if (file_exists($fPath)) {
                                    $fUrl = Yii::getAlias('@web/uploads/periyodik-raporlar/' . $fileName);
                                    $raporHtml = Html::a(Html::encode($raporNo), $fUrl, ['target' => '_blank', 'data-pjax' => 0, 'class' => 'text-info']);
                                }
                            }
                        ?>
                        <div class="card mb-2 bg-dark <?= $pkBorder ?> card-expand" data-target="<?= $pkUid ?>" style="cursor:pointer;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="min-width:0;" class="flex-grow-1">
                                        <span class="small font-weight-bold"><?= Html::encode($pk->cihaz_adi) ?></span>
                                        <?php if (!empty($pk->gelecek_kontrol_tarihi)): ?>
                                            <span class="badge <?= $pkBorder === 'border-danger' ? 'badge-danger' : ($pkBorder === 'border-warning' ? 'badge-warning' : 'badge-success') ?> ml-1" style="font-size:.65rem;"><?= Html::encode(Yii::$app->formatter->asDate($pk->gelecek_kontrol_tarihi, 'php:d.m.Y')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small card-expand-arrow">▼</span>
                                </div>
                                <div id="<?= $pkUid ?>" class="card-expand-detail mt-2" style="display:none;">
                                    <table class="table table-sm table-borderless mb-0" style="font-size:.75rem;">
                                        <tr><td class="text-muted py-0" style="width:100px;">Cihaz Adı</td><td class="py-0"><?= Html::encode($pk->cihaz_adi) ?></td></tr>
                                        <tr><td class="text-muted py-0">Simkal Kodu</td><td class="py-0"><?= Html::encode($pk->simkal_kodu) ?></td></tr>
                                        <tr><td class="text-muted py-0">Rapor No</td><td class="py-0"><?= $raporHtml ?></td></tr>
                                        <tr><td class="text-muted py-0">Yer</td><td class="py-0"><?= Html::encode($pk->bulundugu_yer) ?></td></tr>
                                        <tr><td class="text-muted py-0">Adet</td><td class="py-0"><?= Html::encode($pk->adet) ?></td></tr>
                                        <tr><td class="text-muted py-0">Kabul Değ.</td><td class="py-0"><?= Html::encode($pk->kabul_degerleri) ?></td></tr>
                                        <tr><td class="text-muted py-0">Ölçüm Değ.</td><td class="py-0"><?= Html::encode($pk->olcum_degerleri) ?></td></tr>
                                        <tr><td class="text-muted py-0">Son Kontrol</td><td class="py-0"><?= !empty($pk->son_kontrol_tarihi) ? Html::encode(Yii::$app->formatter->asDate($pk->son_kontrol_tarihi, 'php:d.m.Y')) : '' ?></td></tr>
                                        <tr><td class="text-muted py-0">Gelecek</td><td class="py-0"><?= !empty($pk->gelecek_kontrol_tarihi) ? Html::encode(Yii::$app->formatter->asDate($pk->gelecek_kontrol_tarihi, 'php:d.m.Y')) : '' ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QR Kod -->
            <div class="mt-4 mb-2 p-3 border border-secondary rounded text-center" style="max-width: 320px;">
                <h6 class="mb-2">QR Kod ile Aç</h6>
                <?php if ($qrCode): ?>
                    <img src="<?= Html::encode($qrCode) ?>" style="border:1px solid #ddd; padding:6px; background:white; max-width:180px;" />
                    <div class="mt-1 small"><?= Html::a($url, $url, ['target'=>'_blank', 'class' => 'text-info']) ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">QR kodu oluşturulamadı.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tab-pane fade" id="planli-bakim" role="tabpanel">
            <div style="margin-top:20px;">
                <?php
                $bakimFormlari = array_filter($bakimDokumanlari ?? [], function ($d) {
                    return $d->dokuman_turu === 'BAKIM FORMU' && !empty($d->dosya_yolu);
                });
                $bakimTalimatlari = array_filter($bakimDokumanlari ?? [], function ($d) {
                    return $d->dokuman_turu === 'BAKIM TALİMATI' && !empty($d->dosya_yolu);
                });

                $buildDocUrl = function ($relativePath) {
                    $path = ltrim(str_replace('\\', '/', (string)$relativePath), '/');
                    if (stripos($path, 'uploads/') !== 0) {
                        $path = 'uploads/' . $path;
                    }

                    $segments = array_map('rawurlencode', array_filter(explode('/', $path), function ($seg) {
                        return $seg !== '';
                    }));

                    return Yii::getAlias('@web/' . implode('/', $segments));
                };
                ?>

                <?php if (!empty($bakimFormlari) || !empty($bakimTalimatlari)): ?>
                    <div class="alert alert-info py-2 mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($bakimFormlari as $dok): ?>
                                        <li>
                                            <?= Html::a(
                                                Html::encode($dok->dokuman_adi),
                                                $buildDocUrl($dok->dosya_yolu),
                                                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-white']
                                            ) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($bakimTalimatlari as $dok): ?>
                                        <li>
                                            <?= Html::a(
                                                Html::encode($dok->dokuman_adi),
                                                $buildDocUrl($dok->dosya_yolu),
                                                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-white']
                                            ) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($nextBakimlar)): ?>
                    <h5>Periyot ve Tanıma Göre Sonraki Bakımlar</h5>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>TANIMI</th>
                                <th class="periyot-col">PERİYODU</th>
                                <th>SONRAKİ BAKIM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nextBakimlar as $nb): ?>
                                <?php
                                try {
                                    $sonTarih = new \DateTime($nb['son_tarih']);
                                    $sonrakiTarih = new \DateTime($nb['sonraki_tarih']);
                                    $totalDays = max(1, $sonTarih->diff($sonrakiTarih)->days);
                                    $today = new \DateTime('today');

                                    if ($sonrakiTarih <= $today) {
                                        $remainingDays = 0;
                                        $percent = 100;
                                        $barClass = 'bg-danger';
                                        $label = $sonrakiTarih->format('d.m.Y') . ' - GECİKMİŞ';
                                    } else {
                                        $remainingDays = $today->diff($sonrakiTarih)->days;
                                        $completedDays = $totalDays - $remainingDays;
                                        $percent = max(5, min(100, round($completedDays * 100 / $totalDays)));
                                        $fractionRemaining = $remainingDays / $totalDays;

                                        if ($fractionRemaining <= 0.2) {
                                            $barClass = 'bg-danger';
                                        } elseif ($fractionRemaining <= 0.5) {
                                            $barClass = 'bg-warning';
                                        } else {
                                            $barClass = 'bg-success';
                                        }

                                        $label = $sonrakiTarih->format('d.m.Y') . ' - ' . $remainingDays . ' gün kaldı';
                                    }
                                } catch (\Exception $e) {
                                    $percent = 0;
                                    $barClass = 'bg-secondary';
                                    $label = 'Tarih hesaplanamadı';
                                }
                                ?>
                                <tr>
                                    <td><?= Html::encode($nb['tanimi']) ?></td>
                                    <td class="periyot-col"><?= Html::encode(str_replace('Periyodik: ', '', $nb['periyodu'])) ?></td>
                                    <td>
                                        <div class="progress position-relative" style="height: 22px; margin-bottom: 0;">
                                            <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= $percent ?>%; opacity: 0.4;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            <span class="position-absolute w-100 text-center small" style="color: #fff; line-height: 22px;">
                                                <?= Html::encode($label) ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?= GridView::widget([
                    'dataProvider' => $planliBakimDataProvider,
                    'tableOptions' => ['class' => 'table table-sm table-striped table-bordered d-none d-md-table'],
                    'rowOptions' => function ($model) {
                        switch ($model->periyodu) {
                            case 'Periyodik: 1 Ay':
                                return ['class' => 'table-success'];
                            case 'Periyodik: 3 Ay':
                                return ['class' => 'table-info'];
                            case 'Periyodik: 6 Ay':
                                return ['class' => 'table-warning'];
                            case 'Periyodik: 1 Yıl':
                                return ['class' => 'table-secondary'];
                            default:
                                return [];
                        }
                    },
                    'columns' => [
                        'tanimi',
                        [
                            'attribute' => 'periyodu',
                            'value' => function ($model) {
                                return str_replace('Periyodik: ', '', $model->periyodu);
                            },
                            'headerOptions' => ['class' => 'periyot-col'],
                            'contentOptions' => ['class' => 'periyot-col'],
                        ],
                        [
                            'attribute' => 'tarihi',
                            'format' => ['date', 'php:d.m.Y'],
                        ],
                        'durumu',
                    ],
                    'summary' => '',
                ]) ?>

                <!-- Planlı Bakım: Mobil kart görünümü -->
                <div class="d-md-none">
                    <?php
                    $planliBakimModels = $planliBakimDataProvider->getModels();
                    if (empty($planliBakimModels)): ?>
                        <span class="text-muted">Planlı bakım kaydı bulunamadı.</span>
                    <?php else: ?>
                        <?php foreach ($planliBakimModels as $pb):
                            $periyotKisa = str_replace('Periyodik: ', '', $pb->periyodu);
                            switch ($pb->periyodu) {
                                case 'Periyodik: 1 Ay': $cardBorder = 'border-success'; break;
                                case 'Periyodik: 3 Ay': $cardBorder = 'border-info'; break;
                                case 'Periyodik: 6 Ay': $cardBorder = 'border-warning'; break;
                                case 'Periyodik: 1 Yıl': $cardBorder = 'border-secondary'; break;
                                default: $cardBorder = 'border-secondary';
                            }
                            $pbUid = 'pb_' . $pb->id;
                        ?>
                        <div class="card mb-2 bg-dark <?= $cardBorder ?> card-expand" data-target="<?= $pbUid ?>" style="cursor:pointer;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="min-width:0;" class="flex-grow-1">
                                        <span class="small font-weight-bold"><?= Html::encode(Yii::$app->formatter->asDate($pb->tarihi, 'php:d.m.Y')) ?></span>
                                        <span class="badge badge-secondary ml-1" style="font-size:.65rem;"><?= Html::encode($periyotKisa) ?></span>
                                        <span class="badge <?= $pb->durumu === 'Plan sonrası' ? 'badge-danger' : ($pb->durumu === 'plan dahilinde' || $pb->durumu === 'Plan dahilinde' ? 'badge-success' : 'badge-info') ?> ml-1" style="font-size:.65rem;"><?= Html::encode($pb->durumu) ?></span>
                                    </div>
                                    <span class="text-muted small card-expand-arrow">▼</span>
                                </div>
                                <div id="<?= $pbUid ?>" class="card-expand-detail mt-2" style="display:none;">
                                    <table class="table table-sm table-borderless mb-0" style="font-size:.75rem;">
                                        <tr><td class="text-muted py-0" style="width:80px;">Tanımı</td><td class="py-0"><?= Html::encode($pb->tanimi) ?></td></tr>
                                        <tr><td class="text-muted py-0">Periyodu</td><td class="py-0"><?= Html::encode($pb->periyodu) ?></td></tr>
                                        <tr><td class="text-muted py-0">Tarihi</td><td class="py-0"><?= Html::encode(Yii::$app->formatter->asDate($pb->tarihi, 'php:d.m.Y')) ?></td></tr>
                                        <tr><td class="text-muted py-0">Durumu</td><td class="py-0"><?= Html::encode($pb->durumu) ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="islemler" role="tabpanel">
            <div style="margin-top:20px;">
                <h4 class="mb-2">Bakım Takip Kayıtları</h4>
                <style>
                @media (max-width: 767px) {
                    .bakim-takip-tbl .d-none-mob { display: none !important; }
                }
                </style>
                <?= GridView::widget([
                    'dataProvider' => $bakimTakipDataProvider,
                    'tableOptions' => ['class' => 'table table-sm table-striped table-bordered bakim-takip-tbl d-none d-md-table'],
                    'columns' => [
                        [
                            'attribute' => 'TARIH',
                            'format' => ['date', 'php:d.m.Y'],
                            'headerOptions' => ['style' => 'width:90px;'],
                        ],
                        [
                            'attribute' => 'BAKIM_GENEL',
                            'headerOptions' => ['style' => 'width:120px;', 'class' => 'd-none-mob'],
                            'contentOptions' => ['class' => 'd-none-mob'],
                        ],
                        [
                            'attribute' => 'PERIYODIK_PLANLI',
                            'headerOptions' => ['style' => 'width:180px;', 'class' => 'd-none-mob'],
                            'contentOptions' => ['class' => 'd-none-mob'],
                        ],
                        [
                            'attribute' => 'BAKIM_SURESI_SAAT',
                            'label' => 'Toplam Süre (saat)',
                            'value' => static function ($row) {
                                $value = $row->BAKIM_SURESI_SAAT;
                                if ($value === null || $value === '') {
                                    return '';
                                }
                                return Yii::$app->formatter->asDecimal((float)$value, 2);
                            },
                            'headerOptions' => ['style' => 'width:140px;', 'class' => 'd-none-mob'],
                            'contentOptions' => ['class' => 'd-none-mob'],
                        ],
                        [
                            'label' => 'Birim Süre (hesap)',
                            'value' => static function ($row) {
                                $total = (float)($row->BAKIM_SURESI_SAAT ?? 0);
                                if ($total <= 0) {
                                    return '';
                                }

                                $ekipmanCount = max(1, count(array_values(array_filter((array)$row->ekipmanIds))));
                                $unit = $total / $ekipmanCount;
                                $formatted = Yii::$app->formatter->asDecimal($unit, 2);

                                return $ekipmanCount > 1
                                    ? $formatted . ' (' . $ekipmanCount . ' ekipman)'
                                    : $formatted;
                            },
                            'headerOptions' => ['style' => 'width:170px;', 'class' => 'd-none-mob'],
                            'contentOptions' => ['class' => 'd-none-mob'],
                        ],
                        [
                            'attribute' => 'YAPILAN_IS',
                            'label' => 'Yapılan İş',
                            'format' => 'raw',
                            'contentOptions' => ['style' => 'font-size:.75rem;'],
                            'value' => static function ($row) {
                                $text = trim((string)($row->YAPILAN_IS ?? ''));
                                if ($text === '') {
                                    return '';
                                }
                                $lines = preg_split('/\r?\n/', $text);
                                $lines = array_values(array_filter($lines, static fn($l) => trim($l) !== ''));
                                $maxShow = 3;
                                if (count($lines) <= $maxShow) {
                                    return '<span style="white-space:pre-line">' . Html::encode(implode("\n", $lines)) . '</span>';
                                }
                                $uid = 'yi_' . $row->id;
                                $shown = Html::encode(implode("\n", array_slice($lines, 0, $maxShow)));
                                $rest = Html::encode(implode("\n", array_slice($lines, $maxShow)));
                                $kalan = count($lines) - $maxShow;
                                return '<span style="white-space:pre-line">' . $shown . '</span>'
                                    . '<span id="' . $uid . '" style="display:none;white-space:pre-line">' . "\n" . $rest . '</span>'
                                    . '<br><a href="javascript:void(0)" class="small text-info" onclick="'
                                    . 'var el=document.getElementById(\'' . $uid . '\');'
                                    . 'if(el.style.display===\'none\'){el.style.display=\'inline\';this.textContent=\'Daralt\'}'
                                    . 'else{el.style.display=\'none\';this.textContent=\'... ve ' . $kalan . ' ekipman daha\'}">'
                                    . '... ve ' . $kalan . ' ekipman daha</a>';
                            },
                        ],
                        [
                            'attribute' => 'ISI_YAPANLAR',
                            'headerOptions' => ['class' => 'd-none-mob'],
                            'contentOptions' => ['class' => 'd-none-mob'],
                            'value' => static function ($row) {
                                return is_array($row->ISI_YAPANLAR) ? implode(', ', $row->ISI_YAPANLAR) : $row->ISI_YAPANLAR;
                            },
                        ],
                        [
                            'label' => 'Detay',
                            'format' => 'raw',
                            'value' => static function ($row) {
                                return Html::a('Aç', ['/bakim-takip/view', 'id' => $row->id], ['class' => 'btn btn-sm btn-outline-primary']);
                            },
                            'contentOptions' => ['style' => 'width:80px;'],
                        ],
                    ],
                    'summary' => '',
                    'emptyText' => '<span class="text-muted">Bu ekipman için bakım takip kaydı bulunamadı.</span>',
                ]) ?>

                <!-- Bakım Takip: Mobil kart görünümü -->
                <div class="d-md-none">
                    <?php
                    $bakimTakipModels = $bakimTakipDataProvider->getModels();
                    if (empty($bakimTakipModels)): ?>
                        <span class="text-muted">Bu ekipman için bakım takip kaydı bulunamadı.</span>
                    <?php else: ?>
                        <?php foreach ($bakimTakipModels as $bt):
                            $btUid = 'bt_' . $bt->id;
                            $ekipmanCount = count(array_values(array_filter((array)$bt->ekipmanIds)));
                            $yapilanIs = trim((string)($bt->YAPILAN_IS ?? ''));
                            $yapilanIsKisa = $yapilanIs !== '' ? mb_strimwidth(preg_replace('/\s+/', ' ', $yapilanIs), 0, 50, '…') : '';
                            $isiYapanlar = is_array($bt->ISI_YAPANLAR) ? implode(', ', $bt->ISI_YAPANLAR) : (string)$bt->ISI_YAPANLAR;
                            $toplamSure = ($bt->BAKIM_SURESI_SAAT !== null && $bt->BAKIM_SURESI_SAAT !== '')
                                ? Yii::$app->formatter->asDecimal((float)$bt->BAKIM_SURESI_SAAT, 2) . ' saat'
                                : '';
                        ?>
                        <div class="card mb-2 bg-dark border-primary card-expand" data-target="<?= $btUid ?>" style="cursor:pointer;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="min-width:0;" class="flex-grow-1">
                                        <span class="small font-weight-bold"><?= Html::encode(Yii::$app->formatter->asDate($bt->TARIH, 'php:d.m.Y')) ?></span>
                                        <?php if (!empty($bt->PERIYODIK_PLANLI)): ?>
                                            <span class="badge badge-info ml-1" style="font-size:.65rem;"><?= Html::encode($bt->PERIYODIK_PLANLI) ?></span>
                                        <?php endif; ?>
                                        <?php if ($ekipmanCount > 1): ?>
                                            <span class="badge badge-secondary ml-1" style="font-size:.65rem;"><?= $ekipmanCount ?> ekp.</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small card-expand-arrow">▼</span>
                                </div>
                                <?php if ($yapilanIsKisa): ?>
                                    <div class="text-muted" style="font-size:.7rem;"><?= Html::encode($yapilanIsKisa) ?></div>
                                <?php endif; ?>
                                <div id="<?= $btUid ?>" class="card-expand-detail mt-2" style="display:none;">
                                    <table class="table table-sm table-borderless mb-1" style="font-size:.75rem;">
                                        <tr><td class="text-muted py-0" style="width:90px;">Bakım/Genel</td><td class="py-0"><?= Html::encode($bt->BAKIM_GENEL) ?></td></tr>
                                        <tr><td class="text-muted py-0">Periyodik</td><td class="py-0"><?= Html::encode($bt->PERIYODIK_PLANLI) ?></td></tr>
                                        <?php if ($toplamSure): ?>
                                        <tr><td class="text-muted py-0">Süre</td><td class="py-0"><?= Html::encode($toplamSure) ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($isiYapanlar): ?>
                                        <tr><td class="text-muted py-0">İşi Yapanlar</td><td class="py-0"><?= Html::encode($isiYapanlar) ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($yapilanIs): ?>
                                        <tr><td class="text-muted py-0">Yapılan İş</td><td class="py-0" style="white-space:pre-line;font-size:.7rem;"><?= Html::encode($yapilanIs) ?></td></tr>
                                        <?php endif; ?>
                                    </table>
                                    <?= Html::a('Detay', ['/bakim-takip/view', 'id' => $bt->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h4 class="mt-4 mb-2">Arıza Takip Kayıtları</h4>
                <?= GridView::widget([
                    'dataProvider' => $arizaTakipDataProvider,
                    'tableOptions' => ['class' => 'table table-sm table-striped table-bordered d-none d-md-table'],
                    'columns' => [
                        [
                            'attribute' => 'ARIZA_TARIHI',
                            'format' => ['date', 'php:d.m.Y'],
                            'headerOptions' => ['style' => 'width:120px;'],
                        ],
                        [
                            'attribute' => 'ARIZANIN_SON_DURUMU',
                            'headerOptions' => ['style' => 'width:140px;'],
                        ],
                        [
                            'attribute' => 'ARIZA_SEBEBI',
                            'format' => 'ntext',
                        ],
                        [
                            'attribute' => 'ARIZANIN_AYRINTILI_ACIKLAMASI',
                            'format' => 'ntext',
                        ],
                        [
                            'label' => 'Detay',
                            'format' => 'raw',
                            'value' => static function ($row) {
                                return Html::a('Aç', ['/ariza-takip/view', 'id' => $row->id], ['class' => 'btn btn-sm btn-outline-danger']);
                            },
                            'contentOptions' => ['style' => 'width:80px;'],
                        ],
                    ],
                    'summary' => '',
                    'emptyText' => '<span class="text-muted">Bu ekipman için arıza takip kaydı bulunamadı.</span>',
                ]) ?>

                <!-- Arıza Takip: Mobil kart görünümü -->
                <div class="d-md-none">
                    <?php
                    $arizaModels = $arizaTakipDataProvider->getModels();
                    if (empty($arizaModels)): ?>
                        <span class="text-muted">Bu ekipman için arıza takip kaydı bulunamadı.</span>
                    <?php else: ?>
                        <?php foreach ($arizaModels as $ar):
                            $arUid = 'ar_' . $ar->id;
                            $durumBadge = ($ar->ARIZANIN_SON_DURUMU === 'AÇIK' || $ar->ARIZANIN_SON_DURUMU === 'Açık')
                                ? 'badge-danger' : 'badge-success';
                        ?>
                        <div class="card mb-2 bg-dark border-danger card-expand" data-target="<?= $arUid ?>" style="cursor:pointer;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="min-width:0;" class="flex-grow-1">
                                        <span class="small font-weight-bold"><?= Html::encode(Yii::$app->formatter->asDate($ar->ARIZA_TARIHI, 'php:d.m.Y')) ?></span>
                                        <span class="badge <?= $durumBadge ?> ml-1" style="font-size:.65rem;"><?= Html::encode($ar->ARIZANIN_SON_DURUMU) ?></span>
                                        <?php if (!empty($ar->ARIZA_SEBEBI)): ?>
                                            <span class="text-muted small ml-1" style="font-size:.7rem;"><?= Html::encode(mb_strimwidth($ar->ARIZA_SEBEBI, 0, 40, '…')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small card-expand-arrow">▼</span>
                                </div>
                                <div id="<?= $arUid ?>" class="card-expand-detail mt-2" style="display:none;">
                                    <table class="table table-sm table-borderless mb-1" style="font-size:.75rem;">
                                        <tr><td class="text-muted py-0" style="width:80px;">Sebep</td><td class="py-0"><?= Html::encode($ar->ARIZA_SEBEBI) ?></td></tr>
                                        <tr><td class="text-muted py-0">Açıklama</td><td class="py-0" style="white-space:pre-line;"><?= Html::encode($ar->ARIZANIN_AYRINTILI_ACIKLAMASI) ?></td></tr>
                                        <tr><td class="text-muted py-0">Durum</td><td class="py-0"><?= Html::encode($ar->ARIZANIN_SON_DURUMU) ?></td></tr>
                                    </table>
                                    <?= Html::a('Detay', ['/ariza-takip/view', 'id' => $ar->id], ['class' => 'btn btn-sm btn-outline-danger']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="dokumanlar" role="tabpanel">
            <div style="margin-top:20px;">

                <!-- Enerji Kaynağı Zinciri -->
                <?php
                $enerjiKaynagiZinciri = $model->getEnerjiKaynagiZinciri();
                $hasBesleme = count($enerjiKaynagiZinciri) > 1;
                ?>
                <?php if ($hasBesleme): ?>
                    <div class="mb-4">
                        <h6 class="mb-2">⚡ Enerji Kaynağı</h6>
                        <div class="d-flex align-items-center flex-nowrap" style="overflow-x: auto; -webkit-overflow-scrolling: touch; gap: 4px;">
                            <?php foreach ($enerjiKaynagiZinciri as $i => $node): ?>
                                <?php
                                $isSelf = ($node['id'] === $model->id);
                                $bgClass = $isSelf ? 'bg-danger' : 'bg-dark';
                                $borderClass = $isSelf ? 'border-danger' : 'border-secondary';
                                ?>
                                <div class="text-center px-2 py-1 border <?= $borderClass ?> rounded <?= $bgClass ?>" style="min-width: 0; white-space: nowrap; flex-shrink: 0;">
                                    <?php if ($isSelf): ?>
                                        <div class="font-weight-bold text-white small"><?= Html::encode($node['id']) ?></div>
                                        <div style="font-size:.65rem;" class="text-white-50"><?= Html::encode($node['tanim']) ?></div>
                                    <?php else: ?>
                                        <?= Html::a(
                                            '<div class="font-weight-bold small">' . Html::encode($node['id']) . '</div>'
                                            . '<div style="font-size:.65rem;" class="text-muted">' . Html::encode($node['tanim']) . '</div>',
                                            ['ekipman/view', 'id' => $node['id']],
                                            ['class' => 'text-white text-decoration-none']
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($i < count($enerjiKaynagiZinciri) - 1): ?>
                                    <?php
                                    // Sonraki düğümün şalter bilgisi (ok üzerinde etiket)
                                    $nextNode = $enerjiKaynagiZinciri[$i + 1];
                                    $salterLabel = '';
                                    if (!empty($nextNode['salter_kodu'])) {
                                        $salterLabel = $nextNode['salter_kodu'];
                                        if (!empty($nextNode['salter_akim'])) {
                                            $salterLabel .= ' ' . $nextNode['salter_akim'];
                                        }
                                    }
                                    ?>
                                    <div class="text-center" style="flex-shrink: 0; line-height: 1;">
                                        <?php if ($salterLabel): ?>
                                            <div style="font-size:.55rem;" class="text-info"><?= Html::encode($salterLabel) ?></div>
                                        <?php endif; ?>
                                        <div class="text-warning" style="font-size: 1rem;">▸</div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tek Hat Şemaları (SVG) — silsilenin hemen altında -->
                    <?php
                    $svgDokumanlar = array_values(array_filter($teknikDokumanlar ?? [], function ($d) {
                        return $d->dokuman_turu === 'ELEKTRİK PROJESİ'
                            && !empty($d->dosya_yolu)
                            && strtolower(pathinfo((string)$d->dosya_yolu, PATHINFO_EXTENSION)) === 'svg';
                    }));
                    ?>
                    <?php if (!empty($svgDokumanlar)): ?>
                        <div class="mb-4">
                            <button type="button" class="btn btn-outline-info btn-sm mb-2" id="tekHatToggleBtn">
                                ⚡ Tek Hat Şemasını Göster (<?= count($svgDokumanlar) ?> sayfa)
                            </button>
                            <div id="tekHatContainer" style="display:none;">
                                <!-- Sayfa seçici + Zoom kontrolleri -->
                                <div class="mb-2 d-flex align-items-center flex-wrap">
                                    <div class="btn-group mr-3" id="svgPageBtns">
                                        <?php foreach ($svgDokumanlar as $si => $svgDok): ?>
                                            <button type="button" class="btn btn-sm <?= $si === 0 ? 'btn-info' : 'btn-outline-info' ?> svg-page-btn" data-page="<?= $si ?>">
                                                Sayfa <?= $si + 1 ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
                                        <div class="dropdown mr-3 mb-1 mb-sm-0">
                                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="svgYonetimMenuBtn" data-toggle="dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                Yönetim
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right dropdown-menu-end" aria-labelledby="svgYonetimMenuBtn">
                                                <?php foreach ($svgDokumanlar as $si => $svgDok): ?>
                                                    <?= Html::a('Sayfa ' . ($si + 1) . ' kaldır', ['/ekipman/dokuman-sil', 'id' => $model->id, 'dokumanId' => $svgDok->id], [
                                                        'class' => 'dropdown-item text-danger',
                                                        'data-method' => 'post',
                                                        'data-confirm' => 'Sayfa ' . ($si + 1) . ' SVG dökümanını ekipmandan kaldırmak istediğinize emin misiniz?',
                                                    ]) ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-light mr-1" id="svgZoomIn" title="Yakınlaştır">➕</button>
                                    <button type="button" class="btn btn-sm btn-outline-light mr-1" id="svgZoomOut" title="Uzaklaştır">➖</button>
                                    <button type="button" class="btn btn-sm btn-outline-light mr-1" id="svgZoomFit" title="Sığdır">⊞</button>
                                    <button type="button" class="btn btn-sm btn-outline-light mr-2" id="svgZoomReset" title="Gerçek boyut">1:1</button>
                                    <span class="small text-muted" id="svgZoomLevel">100%</span>
                                    <span class="small text-muted ml-3">Tekerlek: zoom • Sürükle: kaydır</span>
                                </div>

                                <!-- SVG viewport — tek sayfa gösterilir -->
                                <div id="svgViewport" class="border border-secondary rounded bg-white" style="overflow: hidden; height: 600px; cursor: grab; position: relative; touch-action: none;">
                                    <div id="svgPanZoomContent" style="transform-origin: 0 0;">
                                        <?php foreach ($svgDokumanlar as $si => $svgDok): ?>
                                            <div class="svg-page-layer" data-page="<?= $si ?>" style="<?= $si === 0 ? '' : 'display:none;' ?>"
                                                 data-svg-url="<?= Html::encode($buildDocUrl($svgDok->dosya_yolu)) ?>">
                                                <div class="text-center text-muted p-3">Yükleniyor...</div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr class="border-secondary">
                <?php endif; ?>

                <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
                    <div class="mb-3">
                        <!-- Kaynak seçici: Bilgisayardan / Sunucudan -->
                        <div class="mb-2">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-light active" id="btn-kaynak-bilgisayar" onclick="dokKaynakSec('bilgisayar')">Bilgisayardan Yükle</button>
                                <button type="button" class="btn btn-outline-light" id="btn-kaynak-sunucu" onclick="dokKaynakSec('sunucu')">Sunucudan Seç</button>
                            </div>
                        </div>

                        <!-- Bilgisayardan yükleme formu -->
                        <div id="dok-form-bilgisayar">
                            <?= Html::beginForm(['/ekipman/dokuman-ekle', 'id' => $model->id], 'post', [
                                'class' => 'row g-2 align-items-end',
                                'enctype' => 'multipart/form-data',
                            ]) ?>
                                <div class="col-md-3">
                                    <?= Html::label('Tür', null, ['class' => 'form-label small']) ?>
                                    <?= Html::dropDownList('dokuman_turu', null, [
                                        'ELEKTRİK PROJESİ' => 'Elektrik Projesi',
                                        'KULLANMA KLAVUZU' => 'Kullanma Klavuzu',
                                        'BROŞÜR' => 'Broşür',
                                        'TEK HAT ŞEMASI' => 'Tek Hat Şeması (SVG)',
                                    ], ['class' => 'form-control form-control-sm', 'prompt' => 'Seçiniz...', 'id' => 'dok-tur-upload']) ?>
                                </div>
                                <div class="col-md-7">
                                    <?= Html::label('Dosya Seç', null, ['class' => 'form-label small']) ?>
                                    <?= Html::fileInput('dokuman_dosya', null, [
                                        'class' => 'form-control form-control-sm',
                                        'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.svg',
                                        'id' => 'dok-dosya-input',
                                    ]) ?>
                                </div>
                                <div class="col-md-2">
                                    <?= Html::submitButton('Yükle', ['class' => 'btn btn-sm btn-outline-light w-100']) ?>
                                </div>
                            <?= Html::endForm() ?>
                        </div>

                        <!-- Sunucudan seçme formu -->
                        <div id="dok-form-sunucu" style="display:none;">
                            <?= Html::beginForm(['/ekipman/dokuman-ekle', 'id' => $model->id], 'post', ['class' => 'row g-2 align-items-end']) ?>
                                <div class="col-md-3">
                                    <?= Html::label('Tür', null, ['class' => 'form-label small']) ?>
                                    <?= Html::dropDownList('dokuman_turu', null, [
                                        'ELEKTRİK PROJESİ' => 'Elektrik Projesi',
                                        'KULLANMA KLAVUZU' => 'Kullanma Klavuzu',
                                        'BROŞÜR' => 'Broşür',
                                    ], ['class' => 'form-control form-control-sm', 'prompt' => 'Seçiniz...']) ?>
                                </div>
                                <div class="col-md-7">
                                    <?= Html::label('Dosya', null, ['class' => 'form-label small']) ?>
                                    <?= Html::dropDownList('dosya_yolu', null, $teknikDosyaSecenekleri ?? [], [
                                        'class' => 'form-control form-control-sm',
                                        'prompt' => 'Teknik klasörlerden bir dosya seçiniz...',
                                    ]) ?>
                                </div>
                                <div class="col-md-2">
                                    <?= Html::submitButton('Ekle', ['class' => 'btn btn-sm btn-outline-light w-100']) ?>
                                </div>
                            <?= Html::endForm() ?>
                        </div>
                    </div>
                    <?php
                    $this->registerJs("
                        window.dokKaynakSec = function(kaynak) {
                            document.getElementById('dok-form-bilgisayar').style.display = kaynak === 'bilgisayar' ? '' : 'none';
                            document.getElementById('dok-form-sunucu').style.display = kaynak === 'sunucu' ? '' : 'none';
                            document.getElementById('btn-kaynak-bilgisayar').classList.toggle('active', kaynak === 'bilgisayar');
                            document.getElementById('btn-kaynak-sunucu').classList.toggle('active', kaynak === 'sunucu');
                        };
                    ");
                    ?>
                <?php endif; ?>

                <?php
                $elektrikProjeleri = array_values(array_filter($teknikDokumanlar ?? [], function ($d) {
                    return $d->dokuman_turu === 'ELEKTRİK PROJESİ' && !empty($d->dosya_yolu)
                        && strtolower(pathinfo((string)$d->dosya_yolu, PATHINFO_EXTENSION)) !== 'svg';
                }));

                $kilavuzlar = array_values(array_filter($teknikDokumanlar ?? [], function ($d) {
                    return $d->dokuman_turu === 'KULLANMA KLAVUZU' && !empty($d->dosya_yolu);
                }));

                $brosurler = array_values(array_filter($teknikDokumanlar ?? [], function ($d) {
                    return $d->dokuman_turu === 'BROŞÜR' && !empty($d->dosya_yolu);
                }));
                ?>

                <?php if (empty($elektrikProjeleri) && empty($kilavuzlar) && empty($brosurler)): ?>
                    <div class="text-muted">Bu ekipman için teknik döküman bulunamadı.</div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <h6>Elektrik Projeleri</h6>
                            <ul class="mb-0">
                                <?php foreach ($elektrikProjeleri as $dok): ?>
                                    <li>
                                        <?= Html::a(
                                            Html::encode($dok->dokuman_adi),
                                            $buildDocUrl($dok->dosya_yolu),
                                            ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-white']
                                        ) ?>
                                        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
                                            <?= Html::a('kaldır', ['/ekipman/dokuman-sil', 'id' => $model->id, 'dokumanId' => $dok->id], [
                                                'class' => 'text-danger small ms-2',
                                                'data' => ['method' => 'post', 'confirm' => 'Bu dökümanı kaldırmak istiyor musunuz?'],
                                            ]) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-4 mb-3">
                            <h6>Kullanma Klavuzları</h6>
                            <ul class="mb-0">
                                <?php foreach ($kilavuzlar as $dok): ?>
                                    <li>
                                        <?= Html::a(
                                            Html::encode($dok->dokuman_adi),
                                            $buildDocUrl($dok->dosya_yolu),
                                            ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-white']
                                        ) ?>
                                        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
                                            <?= Html::a('kaldır', ['/ekipman/dokuman-sil', 'id' => $model->id, 'dokumanId' => $dok->id], [
                                                'class' => 'text-danger small ms-2',
                                                'data' => ['method' => 'post', 'confirm' => 'Bu dökümanı kaldırmak istiyor musunuz?'],
                                            ]) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-4 mb-3">
                            <h6>Broşürler</h6>
                            <ul class="mb-0">
                                <?php foreach ($brosurler as $dok): ?>
                                    <li>
                                        <?= Html::a(
                                            Html::encode($dok->dokuman_adi),
                                            $buildDocUrl($dok->dosya_yolu),
                                            ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-white']
                                        ) ?>
                                        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
                                            <?= Html::a('kaldır', ['/ekipman/dokuman-sil', 'id' => $model->id, 'dokumanId' => $dok->id], [
                                                'class' => 'text-danger small ms-2',
                                                'data' => ['method' => 'post', 'confirm' => 'Bu dökümanı kaldırmak istiyor musunuz?'],
                                            ]) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

       <div class="tab-pane fade" id="konum" role="tabpanel">
    <div id="map" style="width:100%; height:600px; border:1px solid #ddd; margin-top:10px;"></div>

  
        <!-- Güncel konumu tutacak gizli alanlar -->
        <input type="hidden" id="enlem" value="<?= Html::encode($model->ENLEM) ?>">
        <input type="hidden" id="boylam" value="<?= Html::encode($model->BOYLAM) ?>">

        
   
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    var konumMap = null;

    // Tab functionality
    var tabTriggers = [].slice.call(document.querySelectorAll('#ekipmanTab a'));
    tabTriggers.forEach(function(tabTriggerEl) {
        tabTriggerEl.addEventListener('click', function (e) {
            e.preventDefault();
            
            var target = document.querySelector(this.getAttribute('href'));
            
            // Remove active class from all tabs
            document.querySelectorAll('#ekipmanTab .nav-link').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('#ekipmanTabContent .tab-pane').forEach(function(el) {
                el.classList.remove('show', 'active');
            });
            
            // Add active class to current tab
            this.classList.add('active');
            target.classList.add('show', 'active');
            
            // "Konum" sekmesi aktif olunca haritayı başlat
            if (this.id === 'konum-tab') {
                setTimeout(initMap, 100);
            }
        });
    });

function initMap() {
    if (konumMap) {
        setTimeout(function() {
            konumMap.invalidateSize();
        }, 50);
        return;
    }

    console.log('Initializing map...');

    konumMap = L.map('map', {
        crs: L.CRS.Simple,
        minZoom: -2,
        maxZoom: 5,
        zoomSnap: 0.25
    });

    var bounds = [[0, 0], [<?= $displayHeight ?>, <?= $displayWidth ?>]];

    // Tek overlay: ekipmana göre seçilen pano (Saha veya ParkYuzerHavuz)
    L.imageOverlay('<?= $displayImage ?>', bounds).addTo(konumMap);

    var isAdmin = <?= (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin') ? 'true' : 'false' ?>;

    // Marker'ı oluştur ve uygun katmana ekle (modelin EKIPMAN_YERI'sine göre)
    var marker = L.marker([<?= $markerY ?>, <?= $markerX ?>], { draggable: isAdmin });
    marker.bindPopup("<b><?= addslashes($model->MALZEMENIN_TANIMI) ?><br><?= addslashes($model->EKIPMAN_YERI) ?></b>");

    // Marker'ı doğrudan haritaya ekle
    marker.addTo(konumMap);

    // Marker konumuna zoomla ve merkeze al
    konumMap.setView([<?= $markerY ?>, <?= $markerX ?>], 1);

    // 🔥 Marker hareket edince gizli alanları güncelle
    if (isAdmin) {
        marker.on('dragend', function(e) {
            var pos = e.target.getLatLng();
            document.getElementById('enlem').value = pos.lat;
            document.getElementById('boylam').value = pos.lng;
        });

        // --- Custom Leaflet Save Button ---
        var saveControl = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function (map) {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');

                container.style.backgroundColor = '#28a745';
                container.style.width = '140px';
                container.style.height = '34px';
                container.style.color = 'white';
                container.style.cursor = 'pointer';
                container.style.display = 'flex';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
                container.style.fontWeight = 'bold';
                container.style.borderRadius = '4px';
                container.innerHTML = "📍 Kaydet";

                container.onclick = function () {
                    var enlem = document.getElementById('enlem').value;
                    var boylam = document.getElementById('boylam').value;

                    if (!enlem || !boylam) {
                        alert('Lütfen harita üzerinde bir konum seçiniz.');
                        return;
                    }

                    if (!confirm('Bu konumu kaydetmek istiyor musunuz?')) return;

                    fetch('<?= Url::to(['ekipman/update-location', 'id' => $model->id]) ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?= Yii::$app->request->csrfToken ?>'
                        },
                        body: JSON.stringify({ ENLEM: enlem, BOYLAM: boylam })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Konum başarıyla güncellendi ✅');
                        } else {
                            alert('Konum güncellenemedi: ' + (data.message || 'Bilinmeyen hata'));
                        }
                    })
                    .catch(error => {
                        console.error('Hata:', error);
                        alert('Sunucu hatası oluştu.');
                    });
                };

                L.DomEvent.disableClickPropagation(container);
                return container;
            }
        });

        konumMap.addControl(new saveControl());
    }

    console.log("Map initialized.");
}

    if (document.getElementById('konum-tab') && document.getElementById('konum-tab').classList.contains('active')) {
        setTimeout(initMap, 100);
    }

    
   
});

// SVG Tek Hat Şeması: katmanlı sayfa, SVG attribute zoom (vektörel kalite), pan
(function() {
    var ekipmanViewBase = '<?= Url::to(["ekipman/view"]) ?>';
    var ekipmanPattern = /^[A-ZÇĞİÖŞÜ]{2,5}-[A-ZÇĞİÖŞÜ0-9]{1,6}-\d{1,3}$/;
    var tekHatBtn = document.getElementById('tekHatToggleBtn');
    var tekHatContainer = document.getElementById('tekHatContainer');
    var viewport = document.getElementById('svgViewport');
    var content = document.getElementById('svgPanZoomContent');
    var loaded = false;
    var activePage = 0;

    if (!tekHatBtn || !tekHatContainer) return;

    var pages = tekHatContainer.querySelectorAll('.svg-page-layer');
    var pageStates = [];
    for (var i = 0; i < pages.length; i++) {
        pageStates.push({scale: 1, panX: 0, panY: 0, baseW: 0, baseH: 0});
    }

    var isPanning = false, startX = 0, startY = 0, startPanX = 0, startPanY = 0;
    var minScale = 0.1, maxScale = 10;

    function getState() { return pageStates[activePage]; }

    // Zoom: SVG width/height attribute değiştir → tarayıcı yeniden render eder → vektörler net
    // Pan: sadece CSS translate (scale yok)
    function applyTransform() {
        if (!content) return;
        var s = getState();
        var layer = pages[activePage];
        if (layer && s.baseW > 0) {
            var svg = layer.querySelector('svg');
            if (svg) {
                svg.setAttribute('width', String(Math.round(s.baseW * s.scale)));
                svg.setAttribute('height', String(Math.round(s.baseH * s.scale)));
            }
        }
        content.style.transform = 'translate(' + s.panX + 'px,' + s.panY + 'px)';
        var label = document.getElementById('svgZoomLevel');
        if (label) label.textContent = Math.round(s.scale * 100) + '%';
    }

    function switchPage(idx) {
        activePage = idx;
        pages.forEach(function(p, i) { p.style.display = (i === idx) ? '' : 'none'; });
        document.querySelectorAll('.svg-page-btn').forEach(function(b, i) {
            b.className = 'btn btn-sm ' + (i === idx ? 'btn-info' : 'btn-outline-info') + ' svg-page-btn';
        });
        applyTransform();
    }

    document.querySelectorAll('.svg-page-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            switchPage(parseInt(this.getAttribute('data-page'), 10));
        });
    });

    function fitToViewport() {
        if (!viewport) return;
        var s = getState();
        if (s.baseW <= 0 || s.baseH <= 0) return;
        var vpW = viewport.clientWidth;
        var vpH = viewport.clientHeight;
        if (vpW > 0 && vpH > 0) {
            s.scale = Math.min(vpW / s.baseW, vpH / s.baseH);
            s.panX = (vpW - s.baseW * s.scale) / 2;
            s.panY = (vpH - s.baseH * s.scale) / 2;
            applyTransform();
        }
    }

    if (viewport) {
        viewport.addEventListener('wheel', function(e) {
            e.preventDefault();
            var s = getState();
            var rect = viewport.getBoundingClientRect();
            var mouseX = e.clientX - rect.left;
            var mouseY = e.clientY - rect.top;
            var prevScale = s.scale;
            var delta = e.deltaY > 0 ? 0.9 : 1.1;
            s.scale = Math.min(maxScale, Math.max(minScale, s.scale * delta));
            s.panX = mouseX - (mouseX - s.panX) * (s.scale / prevScale);
            s.panY = mouseY - (mouseY - s.panY) * (s.scale / prevScale);
            applyTransform();
        }, {passive: false});

        viewport.addEventListener('mousedown', function(e) {
            if (e.button !== 0) return;
            isPanning = true;
            var s = getState();
            startX = e.clientX; startY = e.clientY;
            startPanX = s.panX; startPanY = s.panY;
            viewport.style.cursor = 'grabbing';
            e.preventDefault();
        });
        window.addEventListener('mousemove', function(e) {
            if (!isPanning) return;
            var s = getState();
            s.panX = startPanX + (e.clientX - startX);
            s.panY = startPanY + (e.clientY - startY);
            applyTransform();
        });
        window.addEventListener('mouseup', function() {
            if (isPanning) { isPanning = false; if (viewport) viewport.style.cursor = 'grab'; }
        });

        var lastTouchDist = 0;
        var pinchMidX = 0, pinchMidY = 0;
        viewport.addEventListener('touchstart', function(e) {
            e.preventDefault();
            var s = getState();
            if (e.touches.length === 1) {
                isPanning = true;
                startX = e.touches[0].clientX; startY = e.touches[0].clientY;
                startPanX = s.panX; startPanY = s.panY;
            } else if (e.touches.length === 2) {
                isPanning = false;
                lastTouchDist = Math.hypot(e.touches[1].clientX - e.touches[0].clientX, e.touches[1].clientY - e.touches[0].clientY);
                var rect = viewport.getBoundingClientRect();
                pinchMidX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left;
                pinchMidY = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top;
            }
        }, {passive: false});
        viewport.addEventListener('touchmove', function(e) {
            e.preventDefault();
            var s = getState();
            if (e.touches.length === 1 && isPanning) {
                s.panX = startPanX + (e.touches[0].clientX - startX);
                s.panY = startPanY + (e.touches[0].clientY - startY);
                applyTransform();
            } else if (e.touches.length === 2) {
                var dist = Math.hypot(e.touches[1].clientX - e.touches[0].clientX, e.touches[1].clientY - e.touches[0].clientY);
                if (lastTouchDist > 0) {
                    var prevScale = s.scale;
                    s.scale = Math.min(maxScale, Math.max(minScale, s.scale * (dist / lastTouchDist)));
                    // Pinch merkezine doğru zoom
                    s.panX = pinchMidX - (pinchMidX - s.panX) * (s.scale / prevScale);
                    s.panY = pinchMidY - (pinchMidY - s.panY) * (s.scale / prevScale);
                    applyTransform();
                }
                lastTouchDist = dist;
            }
        }, {passive: false});
        viewport.addEventListener('touchend', function(e) { e.preventDefault(); isPanning = false; lastTouchDist = 0; }, {passive: false});
    }

    var zoomInBtn = document.getElementById('svgZoomIn');
    var zoomOutBtn = document.getElementById('svgZoomOut');
    var zoomResetBtn = document.getElementById('svgZoomReset');
    var zoomFitBtn = document.getElementById('svgZoomFit');
    if (zoomInBtn) zoomInBtn.addEventListener('click', function() { var s = getState(); s.scale = Math.min(maxScale, s.scale * 1.25); applyTransform(); });
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', function() { var s = getState(); s.scale = Math.max(minScale, s.scale * 0.8); applyTransform(); });
    if (zoomResetBtn) zoomResetBtn.addEventListener('click', function() { var s = getState(); s.scale = 1; s.panX = 0; s.panY = 0; applyTransform(); });
    if (zoomFitBtn) zoomFitBtn.addEventListener('click', function() { fitToViewport(); });

    function loadSvgPage(layer, pageIdx) {
        var svgUrl = layer.getAttribute('data-svg-url');
        if (!svgUrl) return Promise.resolve();

        return fetch(svgUrl)
            .then(function(r) { return r.text(); })
            .then(function(svgText) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(svgText, 'image/svg+xml');
                var svg = doc.querySelector('svg');
                if (!svg) {
                    layer.innerHTML = '<div class="text-danger p-2">SVG yüklenemedi.</div>';
                    return;
                }

                var w = svg.getAttribute('width');
                var h = svg.getAttribute('height');
                var wNum = 800, hNum = 600;
                if (w && h) {
                    var wStr = w.toString().trim(), hStr = h.toString().trim();
                    wNum = parseFloat(wStr); hNum = parseFloat(hStr);
                    if (wStr.indexOf('mm') !== -1) { wNum *= 3.7795; }
                    if (hStr.indexOf('mm') !== -1) { hNum *= 3.7795; }
                    if (!svg.getAttribute('viewBox')) {
                        svg.setAttribute('viewBox', '0 0 ' + wNum + ' ' + hNum);
                    }
                }

                pageStates[pageIdx].baseW = wNum;
                pageStates[pageIdx].baseH = hNum;
                svg.setAttribute('width', String(Math.round(wNum)));
                svg.setAttribute('height', String(Math.round(hNum)));
                svg.style.display = 'block';

                svg.querySelectorAll('text').forEach(function(textEl) {
                    var txt = (textEl.textContent || '').trim();
                    if (ekipmanPattern.test(txt)) {
                        textEl.style.cursor = 'pointer';
                        textEl.style.fill = '#007bff';
                        textEl.style.textDecoration = 'underline';
                        textEl.setAttribute('title', txt + ' — Ekipman sayfasına git');
                        textEl.addEventListener('click', function(e) {
                            e.stopPropagation();
                            window.open(ekipmanViewBase + '&id=' + encodeURIComponent(txt), '_blank');
                        });
                        textEl.addEventListener('mouseenter', function() { this.style.fill = '#ff4444'; });
                        textEl.addEventListener('mouseleave', function() { this.style.fill = '#007bff'; });
                    }
                });

                layer.innerHTML = '';
                layer.appendChild(svg);
            })
            .catch(function(err) {
                layer.innerHTML = '<div class="text-danger p-2">SVG hata: ' + err.message + '</div>';
            });
    }

    tekHatBtn.addEventListener('click', function() {
        var isVisible = tekHatContainer.style.display !== 'none';
        if (isVisible) {
            tekHatContainer.style.display = 'none';
            tekHatBtn.textContent = tekHatBtn.textContent.replace('Gizle', 'Göster');
            return;
        }

        tekHatContainer.style.display = 'block';
        tekHatBtn.textContent = tekHatBtn.textContent.replace('Göster', 'Gizle');

        if (loaded) return;
        loaded = true;

        var promises = [];
        pages.forEach(function(layer, idx) { promises.push(loadSvgPage(layer, idx)); });
        Promise.all(promises).then(function() {
            setTimeout(function() { fitToViewport(); }, 50);
        });
    });
})();
</script>
