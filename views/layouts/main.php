<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;



AppAsset::register($this);

// Bootstrap 5 tooltip'leri global olarak etkinleştir
$this->registerJs("var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=\\'tooltip\\']'));tooltipTriggerList.forEach(function (tooltipTriggerEl) {new bootstrap.Tooltip(tooltipTriggerEl);});");

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);

$this->title = 'ParkBkm Portalı';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" data-bs-theme="dark">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <style>
        body {
            background-color: #111417;
            color: #fff;
        }
        .navbar-dark {
            background-color: #0d1117;
        }
        .navbar-brand img {
            height: 50px;
            margin-right: 10px;
        }
        .navbar-nav .nav-link {
            color: #fff;
            transition: color 0.3s ease;
        }
        .navbar-nav .nav-link:hover,
        .navbar-brand:hover {
            color: #FE6B00;
        }
        .navbar-nav .nav-link.active {
            color: #FE6B00;
        }
    </style>
</head>
<body>
<?php $this->beginBody() ?>

<?php
NavBar::begin([
    'brandLabel' => Html::img('@web/uploads/PARKBKM_logo_compact.svg', ['alt' => 'ParkBkm Logo']) . 'ParkBkm',
    'brandUrl' => Yii::$app->homeUrl,
    'options' => [
        'class' => 'navbar navbar-expand-lg navbar-dark fixed-top shadow-sm',
    ],
]);

// Build navigation items dynamically
$navItems = [
    ['label' => 'Anasayfa', 'url' => ['/site/index']],
    ['label' => 'KPI', 'url' => ['/site/kpi']],
    ['label' => 'Bakım takip', 'url' => ['/bakim-takip/index']],
    ['label' => 'Arıza takip', 'url' => ['/ariza-takip/index']],
    ['label' => 'P. Kontroller', 'url' => ['/site/periyodik-kontroller']],
    // ['label' => 'Atölye İş Listesi', 'url' => ['/islistesi/index']],
    //['label' => 'Stoklar', 'url' => ['/stok/index']],
    ['label' => 'Ekipmanlar', 'url' => ['/ekipman/index']],
    //['label' => 'KPI Raporları', 'url' => ['/kpi/index']],
    ['label' => 'Tersane Kroki', 'url' => ['/site/map']],
];

// Add admin menu if user is admin
if (Yii::$app->user->identity && Yii::$app->user->identity->role === 'admin') {
    $navItems[] = ['label' => 'Kullanıcılar', 'url' => ['/user']];
}

// Planlı bakım yönetimi menüsü: admin ve editor rollerine açık
if (!Yii::$app->user->isGuest && in_array(Yii::$app->user->identity->role, ['admin', 'editor'])) {
    $navItems[] = ['label' => 'Planlı Bakımlar', 'url' => ['/planli-bakim/index']];
}

// Add auth menu
if (Yii::$app->user->isGuest) {
    $navItems[] = ['label' => 'Login', 'url' => ['/site/login']];
} else {
    $navItems[] = [
        'label' => 'Çıkış (' . Yii::$app->user->identity->username . ')',
        'url' => ['/site/logout'],
        'linkOptions' => ['data-method' => 'post'],
    ];
}

echo Nav::widget([
    'options' => ['class' => 'navbar-nav ms-auto'],
    'items' => $navItems,
]);
NavBar::end();
?>

<div class="container mt-5 pt-5">
    
    <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
        <?php endif ?>
        <?= Alert::widget() ?>
        
    <?= $content ?>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>

