<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use app\models\Ekipman;

/** @var yii\web\View $this */
/** @var app\models\ArizaTakip $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="ariza-takip-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'ARIZA_BILDIRIM_TARIHI')->input('date') ?>
    <?= $form->field($model, 'ARIZA_TARIHI')->input('date') ?>
    <?= $form->field($model, 'ARIZAYI_BILDIREN')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'ARIZAYA_SEBEBIYET_VEREN_FIRMA')->textInput(['maxlength' => true]) ?>

    <?php
    // Arızalanan makine için ekipman listesi (canlı arama ile seçim yapılacak)
    $ekipmanKayitlari = Ekipman::find()
        ->orderBy(['MALZEMENIN_TANIMI' => SORT_ASC])
        ->all();
    ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'ARIZALANAN_MAKINE_KODU')->textInput([
                'maxlength' => true,
                'readonly' => true,
                'id' => 'arizatakip-arizalanan_makine_kodu',
                'placeholder' => 'Ekipman seçiniz...',
            ]) ?>
            <button type="button" class="btn btn-outline-secondary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#arizaEkipmanModal">
                Ekipman Seç...
            </button>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'ARIZALANAN_MAKINE_ADI')->textInput([
                'maxlength' => true,
                'readonly' => true,
                'id' => 'arizatakip-arizalanan_makine_adi',
            ]) ?>
        </div>
    </div>

    <?= $form->field($model, 'ARIZALANAN_PARCA')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'ARIZANIN_MEYDANA_GELDIGI_BOLUM')->textInput([
        'maxlength' => true,
        'id' => 'arizatakip-arizanin_meydana_geldigi_bolum',
    ]) ?>
    <?= $form->field($model, 'ARIZA_KOK_NEDENI')->textarea(['rows' => 3]) ?>
    <?= $form->field($model, 'KALICI_AKSIYON')->textarea(['rows' => 3]) ?>
    <?= $form->field($model, 'ARIZA_SEBEBI')->textarea(['rows' => 3]) ?>
    <?= $form->field($model, 'ARIZANIN_GIDERILDIGI_TARIH')->input('date') ?>
    <?= $form->field($model, 'ARIZANIN_SON_DURUMU')->dropDownList([
        'FAAL' => 'FAAL',
        'GAYRI_FAAL' => 'GAYRI FAAL',
        'ARIZALI_FAAL' => 'ARIZALI FAAL',
    ], [
        'prompt' => 'Seçiniz...',
        'required' => true,
    ]) ?>

    <?= $form->field($model, 'ARIZALI_KALDIGI_SURE_SAAT')->input('number', ['step' => 0.25]) ?>
    <?= $form->field($model, 'YEDEK_PARCA_BEKLEME_SURESI_SAAT')->input('number', ['step' => 0.25]) ?>
    <?= $form->field($model, 'MALZEME_TUTARI', [
        'template' => "{label}\n<div class=\"input-group\">{input}<span class=\"input-group-text\">₺</span></div>\n{error}\n{hint}",
    ])->input('number', ['step' => 0.01]) ?>
    <?= $form->field($model, 'ISCILIK_FIYATI', [
        'template' => "{label}\n<div class=\"input-group\">{input}<span class=\"input-group-text\">₺</span></div>\n{error}\n{hint}",
    ])->input('number', ['step' => 0.01]) ?>
    <?= $form->field($model, 'MALIYET_TL', [
        'template' => "{label}\n<div class=\"input-group\">{input}<span class=\"input-group-text\">₺</span></div>\n{error}\n{hint}",
    ])->input('number', ['step' => 0.01]) ?>

    <?= $form->field($model, 'ARIZANIN_AYRINTILI_ACIKLAMASI')->textarea(['rows' => 4]) ?>

    <div class="form-group mt-3">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
// Arızalanan makine için ekipman seçimi - canlı arama modali
?>

<div class="modal fade" id="arizaEkipmanModal" tabindex="-1" aria-labelledby="arizaEkipmanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="arizaEkipmanLabel">Arızalanan Makine için Ekipman Seçimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="arizaEkipmanSearch" placeholder="Ekipman ara (kod, isim, yer)...">
                </div>
                <div class="list-group" id="arizaEkipmanList">
                    <?php foreach ($ekipmanKayitlari as $e): ?>
                        <?php
                        $kod = (string)$e->id;
                        $isim = $e->MALZEMENIN_TANIMI;
                        $yeri = $e->EKIPMAN_YERI;
                        ?>
                        <button type="button"
                            class="list-group-item list-group-item-action ariza-ekipman-item"
                            data-kod="<?= Html::encode($kod) ?>"
                            data-isim="<?= Html::encode($isim) ?>"
                            data-yeri="<?= Html::encode($yeri) ?>"
                            data-ara="<?= Html::encode($kod . ' ' . $isim . ' ' . $yeri) ?>">
                            <div class="fw-semibold">
                                <?= Html::encode($kod . ' - ' . $isim) ?>
                            </div>
                            <?php if ($yeri): ?>
                                <div class="small text-muted">Yer: <?= Html::encode($yeri) ?></div>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs('(function(){
    function normalizeTr(str){
        return (str || "").toString()
            .replace(/Ç/g, "c").replace(/ç/g, "c")
            .replace(/Ğ/g, "g").replace(/ğ/g, "g")
            .replace(/İ/g, "i").replace(/I/g, "i").replace(/ı/g, "i")
            .replace(/Ö/g, "o").replace(/ö/g, "o")
            .replace(/Ş/g, "s").replace(/ş/g, "s")
            .replace(/Ü/g, "u").replace(/ü/g, "u")
            .toLowerCase();
    }

    var kodInput   = $("#arizatakip-arizalanan_makine_kodu");
    var adInput    = $("#arizatakip-arizalanan_makine_adi");
    var bolumInput = $("#arizatakip-arizanin_meydana_geldigi_bolum");

    // Kayıt güncellemede, mevcut değerleri arama listesi ile senkron tutma gerekmiyor,
    // sadece seçim yapıldığında alanları dolduruyoruz.

    $(document).on("click", ".ariza-ekipman-item", function(){
        var kod   = $(this).data("kod");
        var isim  = $(this).data("isim");
        var yeri  = $(this).data("yeri");
        kodInput.val(kod);
        adInput.val(isim);
        if (yeri && bolumInput.length && !bolumInput.val()) {
            bolumInput.val(yeri);
        }
        $("#arizaEkipmanModal").modal("hide");
    });

    $(document).on("input", "#arizaEkipmanSearch", function(){
        var qRaw = $(this).val();
        var q = normalizeTr(qRaw);
        $("#arizaEkipmanList .ariza-ekipman-item").each(function(){
            var haystack = normalizeTr($(this).data("ara"));
            var show = !q || (haystack && haystack.indexOf(q) !== -1);
            $(this).toggle(show);
        });
    });
})();');
?>
