<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use app\models\User;
use app\models\Ekipman;

/** @var yii\web\View $this */
/** @var app\models\BakimTakip $model */
/** @var yii\widgets\ActiveForm $form */

// User tablosundan kullanıcıları çek (username -> username eşleşmesi)
// Sadece role = 'user' veya 'editor' olanlar
$users = ArrayHelper::map(User::find()
    ->where(['in', 'role', ['user', 'editor']])
    ->orderBy(['username' => SORT_ASC])
    ->all(), 'username', 'username');

$canChooseBakimType = !Yii::$app->user->isGuest
    && in_array(Yii::$app->user->identity->role, ['admin', 'editor'], true);
?>
<?php
$this->registerCss('
.select2-results__option::before{content:"☐ ";display:inline-block;margin-right:6px;color:#666}
.select2-results__option[aria-selected=true]::before{content:"☑ ";color:#0d6efd}
.ekipman-checkbox{
    appearance:none;
    -webkit-appearance:none;
    -moz-appearance:none;
    width:1.1rem;
    height:1.1rem;
    border-radius:50%;
    border:2px solid #6c757d;
    display:inline-block;
    position:relative;
}
.ekipman-checkbox:checked{
    border-color:#0d6efd;
    background-color:#0d6efd;
}
');
?>

<div class="bakim-takip-form">

    <?php
    // Yeni kayıtlar varsayılan GENEL olarak açılır; admin/editor isterse değiştirebilir.
    if ($model->isNewRecord) {
        if (!$model->BAKIM_GENEL) {
            $model->BAKIM_GENEL = 'GENEL';
        }
        if (!$model->PERIYODIK_PLANLI) {
            $model->PERIYODIK_PLANLI = 'GENEL';
        }
        if (!$model->TARIH) {
            $model->TARIH = date('Y-m-d');
        }
    }
    $form = ActiveForm::begin();
    ?>

    <?= $form->errorSummary($model, ['class' => 'alert alert-danger']) ?>

    <?php if ($canChooseBakimType): ?>
        <div class="row">
            <div class="col-md-6">
                <?= $form->field($model, 'BAKIM_GENEL')->dropDownList([
                    'BAKIM' => 'BAKIM',
                    'GENEL' => 'GENEL',
                ], ['prompt' => 'Seçiniz...']) ?>
            </div>
            <div class="col-md-6">
                <?= $form->field($model, 'PERIYODIK_PLANLI')->dropDownList([
                    'GENEL' => 'GENEL',
                    'PERIYODIK' => 'PERİYODİK',
                    'PLANLI' => 'PLANLI',
                    'PLANLI: 1 Ay' => 'Planlı Bakım - 1 Aylık',
                    'PLANLI: 3 Ay' => 'Planlı Bakım - 3 Aylık',
                    'PLANLI: 6 Ay' => 'Planlı Bakım - 6 Aylık',
                    'PLANLI: 1 Yıl' => 'Planlı Bakım - 1 Yıllık',
                    'PLANLI TOPLU BAKIM' => 'Planlı Bakım - Toplu',
                ], ['prompt' => 'Seçiniz...']) ?>
            </div>
        </div>
    <?php else: ?>
        <?= Html::activeHiddenInput($model, 'BAKIM_GENEL', ['value' => 'GENEL']) ?>
        <?= Html::activeHiddenInput($model, 'PERIYODIK_PLANLI', ['value' => 'GENEL']) ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'TARIH')->textInput(['type' => 'date']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'BAKIM_SURESI_SAAT', [
                'template' => "{label}\n<div class=\"input-group\">{input}<span class=\"input-group-text\">saat</span></div>\n{error}\n{hint}",
            ])->input('number', [
                'step' => '0.25',
                'min' => 0,
                'placeholder' => 'Örn. 1.5',
            ])->hint('Saat cinsinden giriniz (0.5 = 30 dk, 1.5 = 1s 30dk).') ?>
        </div>
    </div>

    <?php // Sistem/Cihaz Özellik alanı çoklu ekipman seçimi ile yönetiliyor ?>

    <?php
    // Çoklu ekipman seçimi için gruplu liste (önce Cinsi, sonra Türü)
    $selectedIds = array_values(array_filter((array)$model->ekipmanIds));

    $ekipmanQuery = Ekipman::find()
        ->alias('e')
        ->leftJoin(['em' => 'ekipman_meta'], 'em.ekipman_id = e.id')
        ->where("COALESCE(NULLIF(em.DURUM, ''), 'AKTIF') = 'AKTIF'")
        ->orderBy([
            'e.EKIPMAN_CINSI' => SORT_ASC,
            'e.EKIPMAN_TURU' => SORT_ASC,
            'e.MALZEMENIN_TANIMI' => SORT_ASC,
        ]);

    // Güncellemede daha önce seçilmiş (sonradan hurda olmuş) ekipmanlar görünür kalsın.
    if (!empty($selectedIds)) {
        $ekipmanQuery->orWhere(['e.id' => $selectedIds]);
    }

    $ekipmanKayitlari = $ekipmanQuery->all();

    $canonicalize = static function (?string $value): string {
        $value = trim((string)$value);
        if ($value === '') {
            return 'diger';
        }

        // Eski verilerde görülen '?' bozulmasını gruplanırken normalize et.
        $value = str_replace('?', 'i', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = mb_strtolower($value, 'UTF-8');

        $value = strtr($value, [
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?: 'diger';
    };

    $pickDisplayLabel = static function (string $current, string $candidate): string {
        if ($current === '' || strpos($current, '?') !== false) {
            return $candidate;
        }
        if (strpos($candidate, '?') === false && mb_strlen($candidate, 'UTF-8') >= mb_strlen($current, 'UTF-8')) {
            return $candidate;
        }
        return $current;
    };

    $groupedByCanonical = [];
    foreach ($ekipmanKayitlari as $e) {
        $cinsRaw = trim((string)$e->EKIPMAN_CINSI);
        $turRaw = trim((string)$e->EKIPMAN_TURU);

        $cinsLabel = $cinsRaw !== '' ? $cinsRaw : 'Diğer';
        $turLabel = $turRaw !== '' ? $turRaw : 'Diğer';

        $cinsKey = $canonicalize($cinsLabel);
        $turKey = $canonicalize($turLabel);

        if (!isset($groupedByCanonical[$cinsKey])) {
            $groupedByCanonical[$cinsKey] = [
                'label' => $cinsLabel,
                'turler' => [],
            ];
        } else {
            $groupedByCanonical[$cinsKey]['label'] = $pickDisplayLabel($groupedByCanonical[$cinsKey]['label'], $cinsLabel);
        }

        if (!isset($groupedByCanonical[$cinsKey]['turler'][$turKey])) {
            $groupedByCanonical[$cinsKey]['turler'][$turKey] = [
                'label' => $turLabel,
                'list' => [],
            ];
        } else {
            $groupedByCanonical[$cinsKey]['turler'][$turKey]['label'] = $pickDisplayLabel(
                $groupedByCanonical[$cinsKey]['turler'][$turKey]['label'],
                $turLabel
            );
        }

        $groupedByCanonical[$cinsKey]['turler'][$turKey]['list'][] = $e;
    }

    $ekipmanGruplu = [];
    foreach ($groupedByCanonical as $cinsGroup) {
        $cinsLabel = $cinsGroup['label'];
        foreach ($cinsGroup['turler'] as $turGroup) {
            $ekipmanGruplu[$cinsLabel][$turGroup['label']] = $turGroup['list'];
        }
    }
    ?>

    <div class="mt-2 mb-2">
        <?= Html::label('Sistem/Cihaz Özellik', null, ['class' => 'form-label fw-bold']) ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bakimEkipmanModal">Ekipman Seç...</button>
            <div id="bakimEkipmanSummary" class="small text-muted"></div>
        </div>
        <div class="small text-muted mt-1">Bir bakım kaydına birden fazla ekipman ekleyebilirsiniz.</div>
    </div>

    <?= $form->field($model, 'SISTEM_CIHAZ_OZELLIK')
        ->textInput([
            'maxlength' => true,
            'placeholder' => 'Örn: Çelik sapanlar',
        ])
        ->hint('Bu alan grup adı/serbest tanımdır. Boş bırakırsanız sistem uygun bir grup adı üretir.') ?>

    <?php
    $yeriOptions = ['HAVUZ', 'RIHTIM', 'SAHA', 'ATÖLYELER'];
    $dynamicYerler = Ekipman::find()->select('EKIPMAN_YERI')->distinct()->column();
    foreach ($dynamicYerler as $v) {
        if ($v && !in_array($v, $yeriOptions, true)) {
            $yeriOptions[] = $v;
        }
    }
    $yeriDropdown = array_combine($yeriOptions, $yeriOptions);
    ?>
    <?= $form->field($model, 'YERI')->dropDownList($yeriDropdown, ['prompt' => 'Yer Seçiniz...']) ?>

    <?= $form->field($model, 'YAPILAN_IS')
        ->textarea([
            'rows' => 6,
            'placeholder' => 'Ek not yazabilirsiniz. Ekipman listesi planlı çoklu kayıtta otomatik üretilir.',
        ])
        ->hint('Ana sayfa planlı çoklu girişle uyumlu: çoklu planlı kayıtta ekipmanlar burada otomatik listelenir; yazdığınız metin ek not olarak korunur.') ?>

    <?= $this->render('_ekipman_modal', [
        'ekipmanGruplu' => $ekipmanGruplu,
        'selectedIds' => $selectedIds,
    ]) ?>

        <?php 
        // İşi Yapanlar - Modal çoklu seçim (sadece label + error)
        echo Html::activeLabel($model, 'ISI_YAPANLAR', ['class' => 'control-label']);
        echo Html::error($model, 'ISI_YAPANLAR', ['class' => 'help-block']);
        ?>
        <button type="button" class="btn btn-secondary mb-2" data-bs-toggle="modal" data-bs-target="#isiYapanlarModal">Personel Seçiniz...</button>
        <div id="isiYapanlarSummary" class="small text-muted mb-3"></div>

        <div class="modal fade" id="isiYapanlarModal" tabindex="-1" aria-labelledby="isiYapanlarLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="isiYapanlarLabel">İşi Yapanlar Seçimi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelectAllUsers">Tümünü Seç</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearUsers">Temizle</button>
                        </div>
                        <div class="list-group">
                            <?php foreach($users as $u => $label): ?>
                                <label class="list-group-item">
                                    <input class="form-check-input me-1 isi-yapanlar-checkbox" type="checkbox" name="BakimTakip[ISI_YAPANLAR][]" value="<?= Html::encode($u) ?>">
                                    <?= Html::encode($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="input-group">
                            <input type="text" class="form-control" id="isiYapanlarNewInput" placeholder="Yeni kişi ekle...">
                            <button class="btn btn-outline-success" type="button" id="isiYapanlarAddBtn">Ekle</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="isiYapanlarSaveBtn">Kaydet</button>
                    </div>
                </div>
            </div>
        </div>

    <div class="form-group mt-3">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
$this->registerJs('(function(){
    function updateSummary(){
        var names = [];
        $(".isi-yapanlar-checkbox:checked").each(function(){
            var label = $(this).closest("label").text().trim();
            names.push(label);
        });
        $("#isiYapanlarSummary").text(names.length ? ("Seçilen: " + names.join(", ")) : "Hiç seçim yapılmadı");
    }
    $(document).on("change", ".isi-yapanlar-checkbox", updateSummary);
    $("#btnSelectAllUsers").on("click", function(){
        $(".isi-yapanlar-checkbox").prop("checked", true).trigger("change");
    });
    $("#btnClearUsers").on("click", function(){
        $(".isi-yapanlar-checkbox").prop("checked", false).trigger("change");
    });
    $("#isiYapanlarAddBtn").on("click", function(){
        var val = $("#isiYapanlarNewInput").val().trim();
        if(!val) return;
        var safe = val.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        var item = $(
            "<label class=\"list-group-item\">"+
            "<input class=\"form-check-input me-1 isi-yapanlar-checkbox\" type=\"checkbox\" name=\"BakimTakip[ISI_YAPANLAR][]\" value=\""+safe+"\" checked>"+
            safe+
            "</label>"
        );
        $(this).closest(".modal-body").find(".list-group").append(item);
        $("#isiYapanlarNewInput").val("");
        updateSummary();
    });
    $("#isiYapanlarModal").on("shown.bs.modal", updateSummary);
})();');
?>
<?php // Ekipman seçim modalı ve JS kodları _ekipman_modal partial'ında ?>
