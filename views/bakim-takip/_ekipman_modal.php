<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $ekipmanGruplu */
?>

<div class="modal fade" id="bakimEkipmanModal" tabindex="-1" aria-labelledby="bakimEkipmanLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bakimEkipmanLabel">Ekipman Seçimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="bakimEkipmanSearch" placeholder="Ekipman ara (cins, tür, isim, yer)...">
                </div>

                <div class="accordion" id="bakimEkipmanAccordion">
                    <?php $cinsIndex = 0; foreach ($ekipmanGruplu as $cins => $turler): $cinsIndex++; $collapseId = 'cinsCollapse' . $cinsIndex; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?= $cinsIndex ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
                                    <input type="checkbox" class="form-check-input me-2 ekipman-cins-toggle" data-cins="<?= Html::encode($cins) ?>">
                                    <?= Html::encode($cins) ?>
                                </button>
                            </h2>
                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $cinsIndex ?>" data-bs-parent="#bakimEkipmanAccordion">
                                <div class="accordion-body">
                                    <?php $turIndex = 0; foreach ($turler as $tur => $list): $turIndex++; $groupId = 'turGroup' . $cinsIndex . '_' . $turIndex; ?>
                                        <div class="d-flex align-items-center mt-2 mb-1">
                                            <input type="checkbox" class="form-check-input me-2 ekipman-tur-toggle" data-cins="<?= Html::encode($cins) ?>" data-tur="<?= Html::encode($tur) ?>">
                                            <button class="btn btn-sm btn-link text-start flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $groupId ?>" aria-expanded="false" aria-controls="<?= $groupId ?>">
                                                <?= Html::encode($tur) ?>
                                            </button>
                                        </div>
                                        <div id="<?= $groupId ?>" class="collapse">
                                            <div class="list-group mb-2">
                                                <?php foreach ($list as $e): ?>
                                                    <label class="list-group-item">
                                                        <input class="form-check-input me-1 ekipman-checkbox" type="checkbox" name="BakimTakip[ekipmanIds][]" value="<?= Html::encode($e->id) ?>" data-cins="<?= Html::encode($cins) ?>" data-tur="<?= Html::encode($tur) ?>" data-yeri="<?= Html::encode($e->EKIPMAN_YERI) ?>">
                                                        <?= Html::encode($e->id . ' - ' . $e->MALZEMENIN_TANIMI) ?>
                                                        <?php if ($e->EKIPMAN_YERI): ?>
                                                            <span class="text-muted small">(<?= Html::encode($e->EKIPMAN_YERI) ?>)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Seçimi Kaydet</button>
            </div>
        </div>
    </div>
</div>

<?php
// Bakımda seçilen ekipman özetini, grup seçimlerini ve canlı aramayı yöneten JS
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

    function updateEkipmanSummary(){
        var count = $(".ekipman-checkbox:checked").length;
        if (count === 0) {
            $("#bakimEkipmanSummary").text("Hiç ekipman seçilmedi");
        } else {
            $("#bakimEkipmanSummary").text("Seçili ekipman sayısı: " + count);
        }
    }

    $(document).on("change", ".ekipman-checkbox", function(){
        updateEkipmanSummary();

        var first = $(".ekipman-checkbox:checked").first();
        if (first.length) {
            var yeri = first.data("yeri");
            if (yeri) {
                var yeriField = $("#bakimtakip-yeri");
                if (yeriField.length) {
                    yeriField.val(yeri);
                }
            }
        }
    });

    // Cins bazlı grup checkbox: bu cinsteki tüm ekipmanları seç / temizle
    $(document).on("change", ".ekipman-cins-toggle", function(e){
        e.stopPropagation();
        var cins = $(this).data("cins");
        var checked = $(this).is(":checked");
        $(".ekipman-checkbox").each(function(){
            var cb = $(this);
            if (cb.data("cins") === cins) {
                cb.prop("checked", checked);
            }
        });
        updateEkipmanSummary();
    });

    // Tür bazlı grup checkbox: ilgili cins+türdeki ekipmanları seç / temizle
    $(document).on("change", ".ekipman-tur-toggle", function(e){
        e.stopPropagation();
        var cins = $(this).data("cins");
        var tur = $(this).data("tur");
        var checked = $(this).is(":checked");
        $(".ekipman-checkbox").each(function(){
            var cb = $(this);
            if (cb.data("cins") === cins && cb.data("tur") === tur) {
                cb.prop("checked", checked);
            }
        });
        updateEkipmanSummary();
    });

    // Grup checkbox tiklenince sadece seçim değişsin, panel açılıp kapanmasın
    $(document).on("click", ".ekipman-cins-toggle, .ekipman-tur-toggle", function(e){
        e.stopPropagation();
    });

    // Canlı arama: girilen metne göre ekipman satırlarını filtrele
    $(document).on("input", "#bakimEkipmanSearch", function(){
        var raw = $(this).val();
        var q = normalizeTr(raw);
        var hasQuery = q.length > 0;

        // Tüm grupları aç/kapat
        if (hasQuery) {
            $("#bakimEkipmanAccordion .collapse").addClass("show");
        } else {
            $("#bakimEkipmanAccordion .collapse").removeClass("show");
        }

        $("#bakimEkipmanModal .list-group-item").each(function(){
            var text = normalizeTr($(this).text());
            $(this).toggle(!hasQuery || text.indexOf(q) !== -1);
        });
    });

    $("#bakimEkipmanModal").on("shown.bs.modal", updateEkipmanSummary);

    // Sayfa ilk açıldığında (özellikle güncellemede) özet bilgisini güncelle
    updateEkipmanSummary();
})();');
?>
