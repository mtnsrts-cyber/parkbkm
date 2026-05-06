<?php

namespace app\widgets;

use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Listeleme sayfalarında sayfa başına kayıt sayısını seçmeye yarayan küçük widget.
 * Kullanım: <?= \app\widgets\PageSizeWidget::widget() ?>
 */
class PageSizeWidget extends Widget
{
    /** @var int[] Seçilebilecek sayfa büyüklükleri */
    public array $sizes = [20, 50, 100];

    /** @var int Varsayılan sayfa büyüklüğü (pagination config ile aynı olmalı) */
    public int $defaultSize = 20;

    /** @var string URL parametre adı (pagination config'deki pageSizeParam ile aynı olmalı) */
    public string $pageSizeParam = 'per-page';

    public function run(): string
    {
        $current = (int)\Yii::$app->request->get($this->pageSizeParam, $this->defaultSize);
        $params  = \Yii::$app->request->queryParams;
        unset($params['page']); // Sayfa boyutu değişince 1. sayfaya dön

        $html  = '<div class="d-flex align-items-center gap-1 mb-2">';
        $html .= '<span class="me-2 text-muted small">Listele:</span>';
        $html .= '<div class="btn-group btn-group-sm">';

        foreach ($this->sizes as $size) {
            $params[$this->pageSizeParam] = $size;
            $url   = Url::current($params);
            $class = $current === $size ? 'btn btn-primary' : 'btn btn-outline-secondary';
            $html .= Html::a((string)$size, $url, ['class' => $class]);
        }

        $html .= '</div></div>';

        return $html;
    }
}
