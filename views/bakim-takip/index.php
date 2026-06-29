<?php

use app\models\BakimTakip;
use app\models\Ekipman;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\widgets\Pjax;
// Export için PhpSpreadsheet tabanlı controller aksiyonu kullanılacak

/** @var yii\web\View $this */
/** @var app\models\BakimTakipSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Bakım Takip Listesi';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss(<<<CSS
.bakim-takip-index .table td,
.bakim-takip-index .table th {
    vertical-align: top;
}

.bakim-is-ozet {
    min-width: 280px;
    max-width: 520px;
    display: flex;
    flex-direction: column;
}

.bakim-is-preview {
    white-space: pre-line;
    line-height: 1.25;
}

.bakim-is-preview.is-clamped {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.bakim-is-full {
    white-space: pre-line;
    line-height: 1.3;
    margin-top: .35rem;
    padding-top: .35rem;
    border-top: 1px solid rgba(255,255,255,.08);
}

.bakim-is-toggle {
    align-self: flex-start;
    margin-top: .6rem;
    order: 2;
}

.bakim-is-ozet .collapse {
    order: 3;
}

.bakim-is-ozet.is-expanded .collapse {
    order: 2;
}

.bakim-is-ozet.is-expanded .bakim-is-toggle {
    order: 3;
    margin-top: .5rem;
}

.bakim-ekipman-hucre {
    min-width: 220px;
}

@media (max-width: 767.98px) {
    .bakim-is-ozet,
    .bakim-ekipman-hucre {
        min-width: 0;
        max-width: none;
    }

    .bakim-is-preview.is-clamped {
        -webkit-line-clamp: 4;
    }
}

.bakim-desktop-grid {}
.bakim-mobile-list { display: none; }
.bakim-mobile-summary { display: none; }
.bakim-mobile-pager { display: none; }
.bakim-mobile-card {
    display: block;
    color: #fff;
    text-decoration: none;
    border: 1px solid #495057;
    border-radius: 10px;
    background: #1f2428;
}
.bakim-mobile-card:hover {
    color: #fff;
    border-color: #ffc107;
    text-decoration: none;
}
.bakim-mobile-date {
    color: #ffc107;
    font-weight: 800;
    white-space: nowrap;
}
.bakim-mobile-title {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-weight: 700;
    line-height: 1.25;
}
.bakim-mobile-work {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    color: #cbd5e1;
    font-size: .86rem;
    white-space: pre-line;
}
.bakim-mobile-summary {
    color: #fff;
    font-weight: 700;
    font-size: .92rem;
}
.bakim-mobile-pager-inner {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: .45rem;
    align-items: center;
}
.bakim-mobile-page-status {
    border: 1px solid #495057;
    border-radius: 8px;
    padding: .45rem .7rem;
    color: #fff;
    font-weight: 800;
    text-align: center;
    white-space: nowrap;
    background: #1f2428;
}
.bakim-mobile-pager-extra {
    display: flex;
    justify-content: center;
    gap: .45rem;
    margin-top: .45rem;
}
@media (max-width: 767.98px) {
    .bakim-desktop-grid { display: none; }
    .bakim-mobile-list {
        display: grid;
        gap: .55rem;
    }
    .bakim-mobile-summary { display: block; }
    .bakim-mobile-pager { display: block; }
}
CSS);

$renderEkipmanBaglantisi = static function (BakimTakip $model): string {
    $ekipmanIds = array_values(array_filter((array)$model->ekipmanIds));
    if (empty($ekipmanIds)) {
        return nl2br(Html::encode((string)$model->SISTEM_CIHAZ_OZELLIK));
    }

    $ekipmanlar = Ekipman::find()
        ->where(['id' => $ekipmanIds])
        ->indexBy('id')
        ->all();

    $ozetParcalar = [];
    foreach ($ekipmanIds as $ekipmanId) {
        $ekipman = $ekipmanlar[$ekipmanId] ?? null;
        if ($ekipman === null) {
            $ozetParcalar[] = (string)$ekipmanId;
            continue;
        }

        $etiket = trim((string)$ekipman->id . ' - ' . (string)$ekipman->MALZEMENIN_TANIMI);
        if (!empty($ekipman->EKIPMAN_YERI)) {
            $etiket .= ' (' . (string)$ekipman->EKIPMAN_YERI . ')';
        }
        $ozetParcalar[] = $etiket;
    }

    if (count($ekipmanIds) === 1) {
        $tekId = $ekipmanIds[0];
        $tekEkipman = $ekipmanlar[$tekId] ?? null;
        if ($tekEkipman === null) {
            return Html::encode((string)$tekId);
        }

        return Html::a(
            Html::encode($ozetParcalar[0]),
            ['/ekipman/view', 'id' => $tekEkipman->id],
            ['class' => 'text-reset text-decoration-none', 'style' => 'color: inherit;']
        );
    }

    $ozellik = trim((string)$model->SISTEM_CIHAZ_OZELLIK);
    $otomatikOzet = trim(implode(', ', $ozetParcalar));
    if ($ozellik !== '' && $ozellik !== $otomatikOzet) {
        return Html::a(
            nl2br(Html::encode($ozellik)),
            ['/bakim-takip/ekipmanlar', 'id' => $model->id],
            ['class' => 'text-reset text-decoration-none', 'style' => 'color: inherit;']
        );
    }

    $parcalar = [];
    foreach ($ekipmanIds as $indeks => $ekipmanId) {
        $ekipman = $ekipmanlar[$ekipmanId] ?? null;
        if ($ekipman === null) {
            $parcalar[] = Html::encode((string)$ekipmanId);
            continue;
        }

        $parcalar[] = Html::a(
            Html::encode($ozetParcalar[$indeks]),
            ['/ekipman/view', 'id' => $ekipman->id],
            ['class' => 'text-reset text-decoration-none', 'style' => 'color: inherit;']
        );
    }

    return implode('<br>', $parcalar);
};

$renderYapilanIs = static function (BakimTakip $model): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$model->YAPILAN_IS));
    $displayText = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
    if ($text === '') {
        return '<span class="text-muted">-</span>';
    }

    $collapseId = 'bakim-is-' . (int)$model->id;
    $lineCount = preg_match_all('/\n/u', $displayText) + 1;
    $isLong = mb_strlen($displayText, 'UTF-8') > 120 || $lineCount > 3;
    $previewClass = 'bakim-is-preview' . ($isLong ? ' is-clamped' : '');

    $html = '<div class="bakim-is-ozet">';
    $html .= '<div class="' . $previewClass . '">' . nl2br(Html::encode($displayText)) . '</div>';

    if ($isLong) {
        $html .= Html::button(
            'Devamını gör',
            [
                'class' => 'btn btn-outline-secondary btn-sm bakim-is-toggle',
                'data-bs-toggle' => 'collapse',
                'data-bs-target' => '#' . $collapseId,
                'aria-expanded' => 'false',
                'aria-controls' => $collapseId,
                'type' => 'button',
            ]
        );
        $html .= '<div class="collapse" id="' . $collapseId . '">';
        $html .= '<div class="bakim-is-full">' . nl2br(Html::encode($displayText)) . '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
};
?>
<div class="bakim-takip-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($searchModel->quickFilter === 'this-month'): ?>
        <div class="alert alert-info py-2">KPI filtresi aktif: Bu ay yapılan bakımlar listeleniyor.</div>
    <?php endif; ?>

    <p>
        <?php 
        // Sadece giriş yapmış kullanıcılar görebilir (Controller'da zaten kısıtlı ama UI için)
        if (!Yii::$app->user->isGuest) {
            echo Html::a('Yeni Kayıt Ekle', ['create'], ['class' => 'btn btn-success']);
        }
        ?>
        <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
            <button type="button" class="btn btn-outline-warning ml-1" data-bs-toggle="collapse" data-bs-target="#bakimAktarPanel" aria-expanded="false" aria-controls="bakimAktarPanel">
                Toplu Bakım Aktar
            </button>
        <?php endif; ?>
    

   <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor'])): ?>
       <?= Html::a('Excel İndir', ['/bakim-takip/export-excel'], [
           'class' => 'btn btn-primary ml-1',
           'data-pjax' => 0,
           'id' => 'bakimtakip-export-btn',
           'data-base-url' => Url::to(['/bakim-takip/export-excel']),
       ]) ?>
    <?php endif; ?>

    </p>
    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin'): ?>
    <div id="bakimAktarPanel" class="collapse">
    <div class="card bg-dark border-secondary mb-3">
        <div class="card-body py-3">
            <div class="font-weight-bold mb-2">Toplu Bakım Aktar</div>
            <?= Html::beginForm(['bakim-takip/toplu-aktar'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'd-flex align-items-center flex-wrap mb-0']) ?>
                <input type="file" name="bakim_excel" accept=".xlsx,.xls,.csv" class="form-control-file mr-2 mb-2" style="max-width: 360px;" required>
                <button type="submit" class="btn btn-success btn-sm mb-2">Yükle</button>
                <a href="#" class="btn btn-outline-info btn-sm ml-2 mb-2" id="ornekBakimCsvIndir">Örnek CSV</a>
            <?= Html::endForm() ?>
        </div>
    </div>
    </div>
    <script>
    document.getElementById('ornekBakimCsvIndir') && document.getElementById('ornekBakimCsvIndir').addEventListener('click', function(e) {
        e.preventDefault();
        var csv = "BAKIM_GENEL;PERIYODIK_PLANLI;TARIH;BAKIM_SURESI_SAAT;YERI;SISTEM_CIHAZ_OZELLIK;YAPILAN_IS;ISI_YAPANLAR;EKIPMAN_ID\n" +
            "BAKIM;PLANLI;16.06.2026;1;SAHA;ORNEK EKIPMAN;Planlı bakım yapıldı.;Metin Sarıtaş;ORNEK-01\n";
        var blob = new Blob(["\uFEFF" + csv], {type: 'text/csv;charset=utf-8'});
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
        a.download = 'bakim_takip_toplu_aktar_ornek.csv'; a.click();
    });
    </script>
    <?php endif; ?>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <input type="text" id="bakimtakip-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Sistem/cihaz, yapılan iş, yer, işi yapanlar...)" value="<?= Html::encode($searchModel->globalSearch ?? '') ?>" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'bakimtakip-pjax', 'enablePushState' => false]); ?>
    <?php
    $pagination = $dataProvider->getPagination();
    $totalCount = $dataProvider->getTotalCount();
    $currentCount = count($dataProvider->getModels());
    $pageNumber = $pagination === false ? 1 : $pagination->getPage() + 1;
    $pageCount = $pagination === false ? 1 : max(1, $pagination->getPageCount());
    $firstItem = $totalCount === 0 || $pagination === false ? ($totalCount === 0 ? 0 : 1) : $pagination->getOffset() + 1;
    $lastItem = $totalCount === 0 ? 0 : $firstItem + $currentCount - 1;
    $summaryText = Yii::$app->formatter->asInteger($totalCount) . ' öğenin ' . Yii::$app->formatter->asInteger($firstItem) . '-' . Yii::$app->formatter->asInteger($lastItem) . ' arası gösteriliyor.';
    ?>

    <div class="bakim-desktop-grid">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'tableOptions' => ['class' => 'table table-sm table-hover table-dark'],
        'columns' => [
            [
                'class' => 'yii\grid\SerialColumn',
                'contentOptions' => ['data-label' => '#'],
            ],

            // 'id',
           // [
            //    'attribute' => 'BAKIM_GENEL',
             //   'filter' => ['BAKIM' => 'BAKIM', 'GENEL' => 'GENEL'],
           // ],
            // 'PERIYODIK_PLANLI',
            [
                'attribute' => 'TARIH',
                'contentOptions' => ['data-label' => 'Tarih'],
                'value' => function($model){
                    return Yii::$app->formatter->asDate($model->TARIH, 'php:d.m.Y');
                },
                'filter' =>
                    Html::input('date', 'BakimTakipSearch[TARIH_from]', $searchModel->TARIH_from, ['class' => 'form-control', 'style' => 'min-width:140px']) .
                    Html::input('date', 'BakimTakipSearch[TARIH_to]', $searchModel->TARIH_to, ['class' => 'form-control mt-1', 'style' => 'min-width:140px'])
            ],
           // 'BAKIM_SURESI_SAAT',
           // 'YERI',
            [
                'attribute' => 'SISTEM_CIHAZ_OZELLIK',
                'format' => 'raw',
                'contentOptions' => ['data-label' => 'Ekipman', 'class' => 'bakim-ekipman-hucre'],
                'value' => $renderEkipmanBaglantisi,
            ],
            [
                'attribute' => 'YAPILAN_IS',
                'format' => 'raw',
                'contentOptions' => ['data-label' => 'Yapılan İş'],
                'value' => $renderYapilanIs,
            ],
           // [
           //     'attribute' => 'ISI_YAPANLAR',
           //    'format' => 'ntext',
           //     'value' => function($model) {
                    // Array ise stringe çevir göster
            //        return is_array($model->ISI_YAPANLAR) ? implode(', ', $model->ISI_YAPANLAR) : $model->ISI_YAPANLAR;
            //    }
            // ],
            [
                'class' => ActionColumn::className(),
                'contentOptions' => ['data-label' => 'İşlemler'],
                'urlCreator' => function ($action, BakimTakip $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 },
                 'visibleButtons' => [
                    'update' => function ($model, $key, $index) {
                        return !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
                    },
                    'delete' => function ($model, $key, $index) {
                        return !Yii::$app->user->isGuest && Yii::$app->user->identity->role === 'admin';
                    },
                    'view' => true,
                 ]
            ],
        ],
    ]); ?>
    </div>

    <div class="bakim-mobile-summary mb-2">
        <?= Html::encode($summaryText) ?>
    </div>

    <div class="bakim-mobile-list">
        <?php foreach ($dataProvider->getModels() as $model): ?>
            <?php
            $ekipmanText = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $renderEkipmanBaglantisi($model))));
            $isText = trim((string)$model->YAPILAN_IS);
            ?>
            <?= Html::a(
                '<div class="d-flex justify-content-between align-items-start gap-2 mb-1">'
                    . '<div class="bakim-mobile-date">' . Html::encode(Yii::$app->formatter->asDate($model->TARIH, 'php:d.m.Y')) . '</div>'
                    . '<span class="btn btn-sm btn-outline-warning py-0 px-2">Detay</span>'
                . '</div>'
                . '<div class="bakim-mobile-title mb-1">' . Html::encode($ekipmanText !== '' ? $ekipmanText : (string)$model->SISTEM_CIHAZ_OZELLIK) . '</div>'
                . '<div class="bakim-mobile-work">' . Html::encode($isText !== '' ? $isText : '-') . '</div>',
                ['view', 'id' => $model->id],
                ['class' => 'bakim-mobile-card p-2']
            ) ?>
        <?php endforeach; ?>
    </div>

    <div class="bakim-mobile-pager mt-3">
        <?php if ($pagination !== false && $pageCount > 1): ?>
            <div class="bakim-mobile-pager-inner">
                <?= $pageNumber > 1
                    ? Html::a('Önceki', $pagination->createUrl($pageNumber - 2), ['class' => 'btn btn-outline-primary btn-sm'])
                    : Html::tag('span', 'Önceki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
                <div class="bakim-mobile-page-status">
                    Sayfa <?= Html::encode((string)$pageNumber) ?> / <?= Html::encode((string)$pageCount) ?>
                </div>
                <?= $pageNumber < $pageCount
                    ? Html::a('Sonraki', $pagination->createUrl($pageNumber), ['class' => 'btn btn-outline-primary btn-sm'])
                    : Html::tag('span', 'Sonraki', ['class' => 'btn btn-outline-secondary btn-sm disabled']) ?>
            </div>
            <div class="bakim-mobile-pager-extra">
                <?= $pageNumber > 1
                    ? Html::a('İlk sayfa', $pagination->createUrl(0), ['class' => 'btn btn-outline-secondary btn-sm'])
                    : '' ?>
                <?= $pageNumber < $pageCount
                    ? Html::a('Son sayfa', $pagination->createUrl($pageCount - 1), ['class' => 'btn btn-outline-secondary btn-sm'])
                    : '' ?>
            </div>
        <?php endif; ?>
    </div>


    <?php Pjax::end(); ?>

</div>

<script>
(function () {
    var input = document.getElementById('bakimtakip-live-search');
    if (!input) return;
    var timer;
    input.addEventListener('keyup', function () {
        clearTimeout(timer);
        var val = this.value.trim();
        timer = setTimeout(function () {
            $.pjax.reload({
                container: '#bakimtakip-pjax',
                data: { 'BakimTakipSearch[globalSearch]': val },
                replace: false, push: false, timeout: 5000
            });
        }, 300);
    });

    var exportBtn = document.getElementById('bakimtakip-export-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();

            var baseUrl = exportBtn.getAttribute('data-base-url') || exportBtn.getAttribute('href');
            var params = new URLSearchParams();

            var fromInput = document.querySelector('[name="BakimTakipSearch[TARIH_from]"]');
            var toInput = document.querySelector('[name="BakimTakipSearch[TARIH_to]"]');
            var liveSearchInput = document.getElementById('bakimtakip-live-search');

            if (fromInput && fromInput.value) {
                params.set('BakimTakipSearch[TARIH_from]', fromInput.value);
            }
            if (toInput && toInput.value) {
                params.set('BakimTakipSearch[TARIH_to]', toInput.value);
            }
            if (liveSearchInput && liveSearchInput.value.trim() !== '') {
                params.set('BakimTakipSearch[globalSearch]', liveSearchInput.value.trim());
            }

            var existing = new URLSearchParams(window.location.search || '');
            ['BakimTakipSearch[quickFilter]', 'per-page'].forEach(function (key) {
                if (existing.has(key)) {
                    params.set(key, existing.get(key));
                }
            });

            if (!params.toString()) {
                window.location.href = baseUrl;
                return;
            }

            var joiner = baseUrl.indexOf('?') === -1 ? '?' : '&';
            window.location.href = baseUrl + joiner + params.toString();
        });
    }

    document.addEventListener('shown.bs.collapse', function (event) {
        var target = event.target;
        if (!target || !target.id || target.id.indexOf('bakim-is-') !== 0) return;

        var wrapper = target.closest('.bakim-is-ozet');
        if (wrapper) {
            wrapper.classList.add('is-expanded');
        }

        var button = document.querySelector('[data-bs-target="#' + target.id + '"]');
        if (button) {
            button.textContent = 'Daha az göster';
            button.setAttribute('aria-expanded', 'true');
        }
    });

    document.addEventListener('hidden.bs.collapse', function (event) {
        var target = event.target;
        if (!target || !target.id || target.id.indexOf('bakim-is-') !== 0) return;

        var wrapper = target.closest('.bakim-is-ozet');
        if (wrapper) {
            wrapper.classList.remove('is-expanded');
        }

        var button = document.querySelector('[data-bs-target="#' + target.id + '"]');
        if (button) {
            button.textContent = 'Devamını gör';
            button.setAttribute('aria-expanded', 'false');
        }
    });

})();
</script>
