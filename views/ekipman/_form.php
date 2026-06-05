<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Ekipman $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="ekipman-form">

    <?php $form = ActiveForm::begin(); ?>
    <?php if ($model->isNewRecord): ?>
        <?= $form->field($model, 'id')->textInput([
            'maxlength' => 50,
            'placeholder' => 'Ekipman kodu (zorunlu)',
        ]) ?>
    <?php else: ?>
        <?= $form->field($model, 'id')->textInput([
            'maxlength' => 50,
            'readonly' => true,
        ]) ?>
    <?php endif; ?>

    <?= $form->field($model, 'MALZEMENIN_TANIMI')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'EKIPMAN_YERI')->textInput(['maxlength' => 50, 'placeholder' => 'En fazla 50 karakter']) ?>

    <!-- Kısa metin alanları (textInput) -->
    <?= $form->field($model, 'EKIPMAN_CINSI')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'EKIPMAN_TURU')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'MARKA')->textInput(['maxlength' => 25]) ?>
    <?= $form->field($model, 'SERI_NO')->textInput(['maxlength' => 25]) ?>
    <?= $form->field($model, 'TIP')->textInput(['maxlength' => 25]) ?>
    <?= $form->field($model, 'VARSA_DIGER_TANITICI_BILGI')->textInput(['maxlength' => 50]) ?>
    <?= $form->field($model, 'MIKTAR')->textInput(['type' => 'number', 'min' => 0]) ?>
    <?= $form->field($model, 'IMAL_YILI')->textInput(['type' => 'number', 'min' => 1900, 'max' => date('Y') + 5 ,
        'placeholder' => 'Bilinmiyorsa boş bırakın',
    ]) ?>

    <?= $form->field($model, 'DURUM')->dropDownList(\app\models\Ekipman::durumSecenekleri(), [
        'prompt' => 'Durum seçiniz...',
    ]) ?>

    <!-- Uzun metin alanları (textarea) -->
    
    <?= $form->field($model, 'NOTLAR')->textarea(['rows' => 4]) ?>

    <!-- Besleme Grubu -->
    <?php
    $beslemeSecenekleri = \yii\helpers\ArrayHelper::map(
        \app\models\Ekipman::find()
            ->select(['id', 'MALZEMENIN_TANIMI'])
            ->where(['!=', 'id', (string)$model->id])
            ->andWhere([
                'or',
                ['EKIPMAN_CINSI' => ['ELEKTRİK PANOLARI', 'KESİNTİSİZ GÜÇ KAYNAĞI', 'TRAFOLAR', 'JENERATÖRLER', 'DİZEL JENERATÖR']],
                ['like', 'id', '-GEN-'],
                ['like', 'MALZEMENIN_TANIMI', 'JENERAT'],
            ])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all(),
        'id',
        function ($item) {
            return $item['id'] . ' — ' . $item['MALZEMENIN_TANIMI'];
        }
    );
    $beslemeGirisleri = $model->getBeslemeGirisleri();
    $beslemeGirisleri[] = ['kaynak_id' => '', 'salter_kodu' => '', 'salter_akim' => '', 'hedef_salter_kodu' => '', 'kaynak_giris_no' => '', 'gerilim_seviyesi' => \app\models\Ekipman::GERILIM_AG, 'rol' => '', 'not' => ''];
    if (count($beslemeGirisleri) < 3) {
        $beslemeGirisleri[] = ['kaynak_id' => '', 'salter_kodu' => '', 'salter_akim' => '', 'hedef_salter_kodu' => '', 'kaynak_giris_no' => '', 'gerilim_seviyesi' => \app\models\Ekipman::GERILIM_AG, 'rol' => '', 'not' => ''];
    }
    ?>
    <?= $form->field($model, 'besleme_grubu_tipi')->dropDownList(\app\models\Ekipman::beslemeGrubuTipleri(), [
        'class' => 'form-control',
    ]) ?>

    <?php if ($model->isTrafo()): ?>
        <?= $form->field($model, 'trafo_donusum_yonu')->dropDownList(\app\models\Ekipman::trafoDonusumYonleri(), [
            'class' => 'form-control',
        ])->hint('Şebeke trafolarında genelde YG → AG, jeneratör çıkış trafolarında genelde AG → YG, orta gerilim kademe trafolarında YG → YG seçilir.') ?>
        <?= $form->field($model, 'trafo_gerilim_degeri')->textInput([
            'maxlength' => 50,
            'placeholder' => 'Örn: 34,5/0,4 kV',
            'list' => 'trafo-gerilim-tipleri',
        ])->hint('Örnekler: 34,5/0,4 kV, 0,4/34,5 kV, 34,5/6,3 kV, 6,3/0,4 kV') ?>
        <datalist id="trafo-gerilim-tipleri">
            <option value="34,5/0,4 kV"></option>
            <option value="0,4/34,5 kV"></option>
            <option value="34,5/6,3 kV"></option>
            <option value="6,3/0,4 kV"></option>
        </datalist>
    <?php endif; ?>

    <div class="card bg-dark border-secondary mb-3">
        <div class="card-body py-3">
            <div class="font-weight-bold mb-2">Besleme Girişleri</div>
            <div class="small text-muted mb-2">
                Tek girişli ekipmanlarda sadece ilk satırı doldurun. Çift besleme, transfer veya senkron kaynaklarda her giriş için ayrı satır ekleyin.
                Kaynak çift girişliyse "Kaynakta Takip Edilecek Giriş" alanına zincirin devam edeceği kaynak giriş numarasını yazın; tek girişli kaynaklarda boş bırakabilirsiniz.
            </div>
            <div id="besleme-girisleri">
                <?php foreach ($beslemeGirisleri as $i => $giris): ?>
                    <div class="besleme-giris-row border border-secondary rounded p-2 mb-2">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="small text-muted mb-1">Kaynak</label>
                                <?= Html::dropDownList("Ekipman[besleme_girisleri][$i][kaynak_id]", $giris['kaynak_id'] ?? '', $beslemeSecenekleri, [
                                    'prompt' => 'Kaynak seçiniz...',
                                    'class' => 'form-control',
                                ]) ?>
                            </div>
                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Kaynak Şalteri</label>
                                <?= Html::textInput("Ekipman[besleme_girisleri][$i][salter_kodu]", $giris['salter_kodu'] ?? '', ['class' => 'form-control', 'placeholder' => 'Örn: Q5', 'maxlength' => 30]) ?>
                            </div>
                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Akım / Gerilim</label>
                                <?= Html::textInput("Ekipman[besleme_girisleri][$i][salter_akim]", $giris['salter_akim'] ?? '', ['class' => 'form-control', 'placeholder' => 'Örn: 63A', 'maxlength' => 30]) ?>
                            </div>
                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Hedef Şalter</label>
                                <?= Html::textInput("Ekipman[besleme_girisleri][$i][hedef_salter_kodu]", $giris['hedef_salter_kodu'] ?? '', ['class' => 'form-control', 'placeholder' => 'Örn: QF1', 'maxlength' => 30]) ?>
                            </div>
                            <div class="col-md-1">
                                <label class="small text-muted mb-1">Kaynakta Takip</label>
                                <?= Html::textInput("Ekipman[besleme_girisleri][$i][kaynak_giris_no]", $giris['kaynak_giris_no'] ?? '', ['class' => 'form-control', 'placeholder' => 'Boş/1/2', 'maxlength' => 3, 'title' => 'Kaynak ekipman çift girişliyse zincirin kaynakta hangi girişten devam edeceğini yazın. Tek girişli kaynaklarda boş bırakın.']) ?>
                            </div>
                            <div class="col-md-1">
                                <label class="small text-muted mb-1">Gerilim</label>
                                <?= Html::dropDownList("Ekipman[besleme_girisleri][$i][gerilim_seviyesi]", $giris['gerilim_seviyesi'] ?? \app\models\Ekipman::GERILIM_AG, \app\models\Ekipman::gerilimSeviyeleri(), ['class' => 'form-control']) ?>
                            </div>
                            <div class="col-md-1">
                                <label class="small text-muted mb-1">Rol</label>
                                <?= Html::dropDownList("Ekipman[besleme_girisleri][$i][rol]", $giris['rol'] ?? '', [
                                    'ana' => 'Ana',
                                    'yedek' => 'Yedek',
                                    'paralel' => 'Paralel',
                                    'giris-1' => 'Giriş 1',
                                    'giris-2' => 'Giriş 2',
                                ], ['prompt' => 'Rol...', 'class' => 'form-control']) ?>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="small text-muted mb-1">Not</label>
                            <?= Html::textInput("Ekipman[besleme_girisleri][$i][not]", $giris['not'] ?? '', ['class' => 'form-control', 'placeholder' => 'Örn: ADP QF12 üzerinden / senkron bara', 'maxlength' => 255]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-info" id="besleme-giris-ekle">Giriş Ekle</button>
        </div>
    </div>

    <?php
    $beslemeOptionsJson = json_encode($beslemeSecenekleri, JSON_UNESCAPED_UNICODE);
    $this->registerJs(<<<JS
var beslemeGirisIndex = document.querySelectorAll('.besleme-giris-row').length;
var beslemeOptions = {$beslemeOptionsJson};
function beslemeEscapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (chr) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[chr];
    });
}
document.getElementById('besleme-giris-ekle') && document.getElementById('besleme-giris-ekle').addEventListener('click', function () {
    var wrapper = document.getElementById('besleme-girisleri');
    var i = beslemeGirisIndex++;
    var optionHtml = '<option value="">Kaynak seçiniz...</option>';
    Object.keys(beslemeOptions).forEach(function (key) {
        optionHtml += '<option value="' + beslemeEscapeHtml(key) + '">' + beslemeEscapeHtml(beslemeOptions[key]) + '</option>';
    });
    var html = '<div class="besleme-giris-row border border-secondary rounded p-2 mb-2">' +
        '<div class="row">' +
        '<div class="col-md-3"><label class="small text-muted mb-1">Kaynak</label><select name="Ekipman[besleme_girisleri][' + i + '][kaynak_id]" class="form-control">' + optionHtml + '</select></div>' +
        '<div class="col-md-2"><label class="small text-muted mb-1">Kaynak Şalteri</label><input type="text" name="Ekipman[besleme_girisleri][' + i + '][salter_kodu]" class="form-control" placeholder="Örn: Q5" maxlength="30"></div>' +
        '<div class="col-md-2"><label class="small text-muted mb-1">Akım / Gerilim</label><input type="text" name="Ekipman[besleme_girisleri][' + i + '][salter_akim]" class="form-control" placeholder="Örn: 63A" maxlength="30"></div>' +
        '<div class="col-md-2"><label class="small text-muted mb-1">Hedef Şalter</label><input type="text" name="Ekipman[besleme_girisleri][' + i + '][hedef_salter_kodu]" class="form-control" placeholder="Örn: QF1" maxlength="30"></div>' +
        '<div class="col-md-1"><label class="small text-muted mb-1">Kaynakta Takip</label><input type="text" name="Ekipman[besleme_girisleri][' + i + '][kaynak_giris_no]" class="form-control" placeholder="Boş/1/2" maxlength="3" title="Kaynak ekipman çift girişliyse zincirin kaynakta hangi girişten devam edeceğini yazın. Tek girişli kaynaklarda boş bırakın."></div>' +
        '<div class="col-md-1"><label class="small text-muted mb-1">Gerilim</label><select name="Ekipman[besleme_girisleri][' + i + '][gerilim_seviyesi]" class="form-control"><option value="yg">YG - Yüksek Gerilim</option><option value="ag" selected>AG - Alçak Gerilim</option></select></div>' +
        '<div class="col-md-1"><label class="small text-muted mb-1">Rol</label><select name="Ekipman[besleme_girisleri][' + i + '][rol]" class="form-control"><option value="">Rol...</option><option value="ana">Ana</option><option value="yedek">Yedek</option><option value="paralel">Paralel</option><option value="giris-1">Giriş 1</option><option value="giris-2">Giriş 2</option></select></div>' +
        '</div><div class="mt-2"><label class="small text-muted mb-1">Not</label><input type="text" name="Ekipman[besleme_girisleri][' + i + '][not]" class="form-control" placeholder="Örn: ADP QF12 üzerinden / senkron bara" maxlength="255"></div>' +
        '</div>';
    wrapper.insertAdjacentHTML('beforeend', html);
});
JS);
    ?>

    <!-- Koordinatlar -->
    <?= $form->field($model, 'ENLEM')->textInput(['step' => 'any', 'type' => 'number', 'placeholder' => 'Örn: 41.0082']) ?>
    <?= $form->field($model, 'BOYLAM')->textInput(['step' => 'any', 'type' => 'number', 'placeholder' => 'Örn: 28.9784']) ?>

    <div class="form-group">
        <?= Html::submitButton('Kaydet', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
