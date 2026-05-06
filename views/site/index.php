<?php
/** @var yii\web\View $this */
$this->title = 'ParkBkm - Bakım Portalı';

use yii\helpers\Html;
use yii\grid\GridView;
use yii\grid\CheckboxColumn;
use yii\helpers\ArrayHelper;
use app\models\User;

/** @var array $summary */

$canTopluBakimIsle = !Yii::$app->user->isGuest
    && in_array((string)(Yii::$app->user->identity->role ?? ''), ['admin', 'editor'], true);

$bakimYapanlarOptions = [];
if ($canTopluBakimIsle) {
    $bakimYapanlarOptions = ArrayHelper::map(
        User::find()
            ->where(['in', 'role', ['user', 'editor']])
            ->orderBy(['username' => SORT_ASC])
            ->all(),
        'username',
        'username'
    );
}
?>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-body p-2">
                <div class="small text-muted">Toplam Ekipman</div>
                <div class="h4 mb-0"><?= Html::a((string)(int)($summary['toplamEkipman'] ?? 0), ['/ekipman/index'], ['class' => 'text-white text-decoration-none']) ?></div>
                <div class="small text-muted">Aktif: <?= Html::a((string)(int)($summary['aktifEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'AKTIF'], ['class' => 'text-decoration-none']) ?> · Hurda: <?= Html::a((string)(int)($summary['hurdaEkipman'] ?? 0), ['/ekipman/index', 'EkipmanSearch[DURUM]' => 'HURDA'], ['class' => 'text-decoration-none text-warning']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-body p-2">
                <div class="small text-muted">Açık Arıza</div>
                <div class="h4 mb-0 text-danger"><?= Html::a((string)(int)($summary['acikAriza'] ?? 0), ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'open'], ['class' => 'text-danger text-decoration-none']) ?></div>
                <div class="small text-muted">Toplam: <?= Html::a((string)(int)($summary['toplamAriza'] ?? 0), ['/ariza-takip/index'], ['class' => 'text-decoration-none']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-body p-2">
                <div class="small text-muted">Bu Ay Bakım</div>
                <div class="h4 mb-0 text-info"><?= Html::a((string)(int)($summary['buAyBakim'] ?? 0), ['/bakim-takip/index', 'BakimTakipSearch[quickFilter]' => 'this-month'], ['class' => 'text-info text-decoration-none']) ?></div>
                <div class="small text-muted">Toplam: <?= Html::a((string)(int)($summary['toplamBakim'] ?? 0), ['/bakim-takip/index'], ['class' => 'text-decoration-none']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-body p-2">
                <div class="small text-muted">Planlı Gecikmiş</div>
                <div class="h4 mb-0 text-warning"><?= Html::a((string)(int)($summary['planliGecikmis'] ?? 0), ['/planli-bakim/index'], ['class' => 'text-warning text-decoration-none']) ?></div>
                <div class="small text-muted">10 Gün Penceresi: <?= Html::a((string)(int)($summary['planliYaklasan10'] ?? 0), ['/site/index', '#' => 'home-planli-grid'], ['class' => 'text-decoration-none']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-body p-2">
                <div class="small text-muted">Periyodik Gecikmiş</div>
                <div class="h4 mb-0 text-warning"><?= Html::a((string)(int)($summary['periyodikGecikmis'] ?? 0), ['/site/periyodik-kontroller', 'quick' => 'gecikmis'], ['class' => 'text-warning text-decoration-none']) ?></div>
                <div class="small text-muted">30 Gün Yaklaşan: <?= Html::a((string)(int)($summary['periyodikYaklasan30'] ?? 0), ['/site/periyodik-kontroller', 'quick' => 'yaklasan-30'], ['class' => 'text-decoration-none']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-body p-2 d-flex flex-column justify-content-between">
                <div>
                    <div class="small text-muted">Arıza Maliyeti (Bu Ay)</div>
                    <div class="h5 mb-0 text-success"><?= Html::a(number_format((float)($summary['buAyMaliyet'] ?? 0), 2, ',', '.') . ' ₺', ['/ariza-takip/index', 'ArizaTakipSearch[quickFilter]' => 'this-month'], ['class' => 'text-success text-decoration-none']) ?></div>
                    <div class="small text-muted">Toplam: <?= Html::a(number_format((float)($summary['toplamMaliyet'] ?? 0), 2, ',', '.') . ' ₺', ['/ariza-takip/index'], ['class' => 'text-decoration-none']) ?></div>
                </div>
                <div class="mt-2">
                    <?= Html::a('Detay KPI', ['/site/kpi'], ['class' => 'btn btn-sm btn-outline-warning w-100']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">

    <!-- Planlı bakımı yaklaşan ekipmanlar (10 gün içinde) -->
    <div class="col-12 col-md-8 col-lg-6 bg-dark text-white p-2" style="margin: 0 auto; max-height: 80vh; overflow-y: auto;">
        <h5 class="text-warning mb-2">🛠 Planlı Bakımı Yaklaşanlar</h5>

        <?php if ($canTopluBakimIsle): ?>
            <?php echo Html::beginForm(['/site/toplu-bakim-isle'], 'post', ['id' => 'home-toplu-bakim-form']); ?>
        <?php endif; ?>

        <?php if ($canTopluBakimIsle): ?>
            <div id="home-bulk-toolbar" class="d-none mb-2 p-2 border border-secondary rounded">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <div class="small text-muted mb-1">Seçili kayıt</div>
                        <div id="home-selected-count" class="fw-bold">0</div>
                    </div>
                    <div>
                        <?= Html::label('Bakım Tarihi', 'home_toplu_tarih', ['class' => 'form-label mb-1 small']) ?>
                        <?= Html::input('date', 'toplu_tarih', date('Y-m-d'), ['class' => 'form-control form-control-sm', 'id' => 'home_toplu_tarih']) ?>
                    </div>
                    <div class="form-check form-switch mb-1 ms-2">
                        <?= Html::checkbox('bakim_takip_ekle', true, [
                            'class' => 'form-check-input',
                            'id' => 'home_bakim_takip_ekle',
                            'value' => 1,
                        ]) ?>
                        <?= Html::label('Bakım Takip\'e de işle', 'home_bakim_takip_ekle', ['class' => 'form-check-label small']) ?>
                    </div>
                    <div id="home-bakim-takip-fields" class="d-flex flex-wrap align-items-end gap-2 w-100 mt-2">
                        <div class="w-100">
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="bakim_takip_kayit_turu" id="home_kayit_turu_tek" value="ayri" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="home_kayit_turu_tek">Tek Tek İşle</label>
                                <input type="radio" class="btn-check" name="bakim_takip_kayit_turu" id="home_kayit_turu_grup" value="grup" autocomplete="off" checked>
                                <label class="btn btn-outline-secondary" for="home_kayit_turu_grup">Grup İşle</label>
                            </div>
                        </div>
                        <div>
                            <?= Html::label('Bakım Süresi (Saat)', 'home_bakim_suresi_saat', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::input('number', 'bakim_suresi_saat', '', [
                                'class' => 'form-control form-control-sm',
                                'id' => 'home_bakim_suresi_saat',
                                'step' => '0.25',
                                'min' => 0,
                                'placeholder' => 'Örn: 1.5',
                            ]) ?>
                        </div>
                        <div style="min-width: 260px;">
                            <?= Html::label('Bakımı Yapanlar', 'home_bakim_isi_yapanlar', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::dropDownList('bakim_isi_yapanlar[]', [], $bakimYapanlarOptions, [
                                'id' => 'home_bakim_isi_yapanlar',
                                'multiple' => true,
                                'class' => 'form-select form-select-sm',
                                'size' => 4,
                            ]) ?>
                        </div>
                        <div class="flex-grow-1" style="min-width: 280px;">
                            <?= Html::label('Yapılan İş - İlave Not (Opsiyonel)', 'home_bakim_yapilan_is_ek', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::textInput('bakim_yapilan_is_ek', '', [
                                'class' => 'form-control form-control-sm',
                                'id' => 'home_bakim_yapilan_is_ek',
                                'placeholder' => 'Otomatik metne eklenecek ilave not',
                            ]) ?>
                        </div>
                        <div id="home-bakim-grup-baslik-wrap" class="flex-grow-1" style="min-width: 280px;">
                            <?= Html::label('Grup Başlığı', 'home_bakim_grup_basligi', ['class' => 'form-label mb-1 small']) ?>
                            <?= Html::textInput('bakim_grup_basligi', '', [
                                'class' => 'form-control form-control-sm',
                                'id' => 'home_bakim_grup_basligi',
                                'placeholder' => 'Örn: Yüzer havuz valf grupları',
                            ]) ?>
                            <div class="small text-muted mt-1">Yalnızca Grup İşle modunda gerekli</div>
                        </div>
                    </div>
                    <div class="pb-0">
                        <?= Html::submitButton('Seçilenleri Bakım İşle', [
                            'class' => 'btn btn-sm btn-warning',
                            'data-confirm' => 'Seçilen kayıtlar işlenecek. Bakım Takip işaretliyse her ekipman için ayrı bakım takip kaydı da oluşturulacak. Devam edilsin mi?',
                        ]) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?= GridView::widget([
            'id' => 'home-planli-grid',
            'dataProvider' => $dataProvider,
            'summary' => false,
            'tableOptions' => ['class' => 'table table-sm table-dark table-hover'],
            'columns' => [
                [
                    'class' => CheckboxColumn::class,
                    'name' => 'selection',
                    'visible' => $canTopluBakimIsle,
                    'checkboxOptions' => function ($item) {
                        return ['value' => $item['planli_id']];
                    },
                ],
                [
                    'label' => 'Ekipman / Tanım',
                    'format' => 'raw',
                    'value' => function ($item) {
                        $ekipmanLink = Html::a(
                            Html::encode($item['tanimi']),
                            ['/ekipman/view', 'id' => $item['ekipman_id']],
                            ['class' => 'text-info fw-bold ekipman-tanim-link', 'title' => 'Ekipman detayını aç']
                        );

                        // Yeni planlı bakım kaydı için aynı emoji ile ikon / tooltip'li buton
                        $icon = '🛠';
                        $planliButton = Html::a(
                            $icon,
                            ['/planli-bakim/create', 'planli_id' => $item['planli_id']],
                            [
                                'class' => 'btn btn-sm btn-outline-warning ms-2 p-1 px-2',
                                'title' => 'Bakımı işle',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'left',
                            ]
                        );

                        return '<div class="d-flex justify-content-between align-items-center">'
                            . '<div>' . $ekipmanLink . '</div>'
                            . '<div>' . $planliButton . '</div>'
                            . '</div>';
                    },
                ],
                [
                    'label' => 'Periyot',
                    'value' => function ($item) {
                        return str_replace('Periyodik: ', '', $item['periyodu']);
                    },
                ],
              
                [
                    'label' => 'Planlı Bakım',
                    'format' => 'raw',
                    'value' => function ($item) {
                        try {
                            $sonTarih = new \DateTime($item['son_tarih']);
                            $sonrakiTarih = new \DateTime($item['sonraki_tarih']);
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

                        $progress = '<div class="progress position-relative" style="height: 22px; margin-bottom: 0;">';
                        $progress .= '<div class="progress-bar ' . $barClass . '" role="progressbar" style="width: ' . $percent . '%; opacity: 0.4;" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"></div>';
                        $progress .= '<span class="position-absolute w-100 text-center small" style="color: #fff; line-height: 22px;">' . Html::encode($label) . '</span>';
                        $progress .= '</div>';

                        return $progress;
                    },
                ],
            ],
            'emptyText' => '<span class="text-muted small">Önümüzdeki 10 gün içinde planlı bakım bulunmuyor.</span>',
        ]); ?>

        <?php if ($canTopluBakimIsle): ?>
            <?php echo Html::endForm(); ?>
        <?php endif; ?>
    </div>
</div>

<?php
$this->registerJs('(function(){
    function toggleBakimTakipFields(){
        var enabled = $("#home_bakim_takip_ekle").is(":checked");
        $("#home-bakim-takip-fields").toggleClass("d-none", !enabled);
        toggleGrupBaslik();
    }

    function toggleGrupBaslik(){
        var isGrup = $("#home_kayit_turu_grup").is(":checked");
        $("#home-bakim-grup-baslik-wrap").toggle(isGrup);
    }

    function updateBulkToolbar(){
        if(!$("#home-bulk-toolbar").length){
            return;
        }

        var selected = $("#home-planli-grid").find("input[name=\"selection[]\"]:checked").length;
        if(selected > 0){
            $("#home-bulk-toolbar").removeClass("d-none");
        } else {
            $("#home-bulk-toolbar").addClass("d-none");
        }
        $("#home-selected-count").text(selected);
    }

    $(document).on("change", "#home-planli-grid input[type=checkbox]", updateBulkToolbar);
    $(document).on("change", "#home_bakim_takip_ekle", toggleBakimTakipFields);
    $(document).on("change", "input[name=\'bakim_takip_kayit_turu\']", toggleGrupBaslik);

    $("#home-toplu-bakim-form").on("submit", function(e){
        var selected = $("#home-planli-grid").find("input[name=\"selection[]\"]:checked").length;
        var tarih = $("#home_toplu_tarih").val();
        var bakimTakipEkle = $("#home_bakim_takip_ekle").is(":checked");
        var bakimSuresi = $("#home_bakim_suresi_saat").val();
        var yapanlar = $("#home_bakim_isi_yapanlar").val() || [];
        var grupBasligi = $("#home_bakim_grup_basligi").val().trim();

        if(selected === 0){
            e.preventDefault();
            alert("Lütfen en az bir kayıt seçiniz.");
            return false;
        }

        if(!tarih){
            e.preventDefault();
            alert("Lütfen bakım tarihini seçiniz.");
            return false;
        }

        if(bakimTakipEkle){
            if(!bakimSuresi || parseFloat(bakimSuresi) <= 0){
                e.preventDefault();
                alert("Bakım Takip için geçerli bir bakım süresi giriniz.");
                return false;
            }

            if(yapanlar.length === 0){
                e.preventDefault();
                alert("Bakım Takip için en az bir personel seçiniz.");
                return false;
            }

            var isGrup = $("#home_kayit_turu_grup").is(":checked");
            if(isGrup && !grupBasligi){
                e.preventDefault();
                alert("Grup başlığı giriniz.");
                return false;
            }
        }
    });

    updateBulkToolbar();
    toggleBakimTakipFields();
    toggleGrupBaslik();
})();');
?>
