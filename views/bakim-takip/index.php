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
    .bakim-takip-index .grid-view table,
    .bakim-takip-index .grid-view thead,
    .bakim-takip-index .grid-view tbody,
    .bakim-takip-index .grid-view th,
    .bakim-takip-index .grid-view td,
    .bakim-takip-index .grid-view tr {
        display: block;
        width: 100%;
    }

    .bakim-takip-index .grid-view thead {
        display: none;
    }

    .bakim-takip-index .grid-view tbody tr {
        margin-bottom: 1rem;
        border: 1px solid rgba(255,255,255,.08);
        border-radius: .5rem;
        overflow: hidden;
        background: rgba(255,255,255,.02);
    }

    .bakim-takip-index .grid-view tbody td {
        border: 0;
        border-bottom: 1px solid rgba(255,255,255,.06);
        padding: .6rem .75rem;
    }

    .bakim-takip-index .grid-view tbody td:last-child {
        border-bottom: 0;
    }

    .bakim-takip-index .grid-view tbody td::before {
        content: attr(data-label);
        display: block;
        font-size: .78rem;
        color: #9aa4ad;
        margin-bottom: .3rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .bakim-takip-index .grid-view tbody td[data-label=""]::before {
        display: none;
    }

    .bakim-is-ozet,
    .bakim-ekipman-hucre {
        min-width: 0;
        max-width: none;
    }

    .bakim-is-preview.is-clamped {
        -webkit-line-clamp: 4;
    }
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
    

   <?php if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin','editor'])): ?>
       <?= Html::a('Excel İndir', ['/bakim-takip/export-excel'], [
           'class' => 'btn btn-primary',
           'data-pjax' => 0,
           'id' => 'bakimtakip-export-btn',
           'data-base-url' => Url::to(['/bakim-takip/export-excel']),
       ]) ?>
    <?php endif; ?>

    </p>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <input type="text" id="bakimtakip-live-search" class="form-control mb-2" placeholder="🔍 Ara... (Sistem/cihaz, yapılan iş, yer, işi yapanlar...)" value="<?= Html::encode($searchModel->globalSearch ?? '') ?>" autocomplete="off">

    <?= \app\widgets\PageSizeWidget::widget() ?>

    <?php Pjax::begin(['id' => 'bakimtakip-pjax', 'enablePushState' => false]); ?>

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
