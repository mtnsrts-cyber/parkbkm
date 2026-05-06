<?php

namespace app\helpers;

class IconHelper
{
    public static function generate($type)
    {
        if (!$type) {
            return '';
        }
        // Türkçe karakterleri ASCII'ye çevir ve küçük harfe dönüştür
        $type = iconv('UTF-8', 'ASCII//TRANSLIT', $type);
        $type = strtolower(trim($type));
        
        switch ($type) {
            case 'jenerator': return self::jenerator();
            case 'trafo': return self::trafo();
            case 'konvertor': return self::konvertor();
            case 'kesici': return self::kesici();
            case 'pompa': return self::pompa();
            case 'irgat': return self::irgat();
            case 'vinc': return self::vinc();
            case 'kompresor': return self::kompresor();
            case 'transpalet': return self::transpalet();
            case 'kriko': return self::kriko();
            case 'caraskal': return self::caraskal();
            case 'bez sapan': return self::bezsapan();
            case 'zincir sapan': return self::zincirsapan();
            case 'celik sapan': return self::celiksapan();
            case 'kurt agzi': return self::kurtagzi();
            case 'beton kesme makinasi': return self::betonkesme();
            case 'lastik tekerli': return self::lastiktekerli();
            case 'yikama makinasi': return self::yikamamakinesi();
            case 'su jeti': return self::sujeti();
            case 'separator': return self::separator();
            case 'buhar kazani': return self::buharkazani();
            case 'depolama tanki': return self::depolamatanki();
            case 'genlesme tanki': return self::genlesmetanki();
            case 'karistirici': return self::karistirici();
            case 'fan': return self::fan();
            case 'klima': return self::klima();
            case 'kurutucu': return self::kurutucu();
            case 'testere': return self::testere();
            case 'taslama': return self::taslama();
            case 'freze': return self::freze();
            case 'torna': return self::torna();
            case 'matkap': return self::matkap();
            case 'cnc plazma': return self::cncplazma();
            case 'balans makinasi': return self::balansmakinasi();
            case 'firin': return self::firin();
            case 'magnet': return self::magnet();
            case 'romork': return self::romork();
            case 'kopru basamak': return self::koprubasamak();
            case 'tasima sepeti': return self::tasimasepeti();
            case 'su sebili': return self::susebili();
            case 'silo': return self::silo();
            case 'kollektor': return self::kollektor();
            case 'sayac': return self::sayac();
            case 'olcu aleti': return self::olcualeti();
            case 'paratoner': return self::paratoner();
            case 'dolap': return self::dolap();
            case 'raf': return self::raf();
            case 'ofis ekipmani': return self::ofisekipmani();
            case 'elektrik panosu': return self::elektrikpanosu();
            case 'ups': return self::ups();
            case 'yangin sondurme': return self::yanginsondurme();
            case 'yangin algılama': return self::yanginalgılama();
            case 'tork anahtari': return self::torkanahtari();
            case 'elektrik supurgesi': return self::elektriksupurgesi();
            case 'perde kapi': return self::perdekapi();
            case 'mutfak': return self::mutfak();
            case 'kantar': return self::kantar();
            case 'terazi': return self::terazi();
            case 'kilit': return self::kilit();
            case 'seyyar kablo': return self::seyyarkablo();
            case 'pafta makinasi': return self::paftamakinesi();
            case 'perde kapi detay': return self::perdekapidetay();
            case 'celik sapan detay': return self::celiksapan_detay();
            case 'raf detay': return self::raf_detay();
            case 'mutfak detay': return self::mutfak_detay();
            case 'ofis detay': return self::ofisekipmani_detay();
            default: return '';
        }
    }

    // -------------------------
    // ICON FUNCTIONS
    // -------------------------

    public static function jenerator()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="3" y="8" width="26" height="16" rx="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="6" y="12" width="8" height="8" rx="1" fill="white" stroke="#2F6686" stroke-width="1.5"/>
  <circle cx="22" cy="16" r="4" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function trafo()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="8" width="20" height="16" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="10" y1="10" x2="10" y2="22" stroke="#2F6686" stroke-width="2"/>
  <line x1="22" y1="10" x2="22" y2="22" stroke="#2F6686" stroke-width="2"/>
  <line x1="6" y1="10" x2="26" y2="10" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function konvertor()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="4" y="10" width="24" height="12" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M10 14 L14 18" stroke="#2F6686" stroke-width="2" stroke-linecap="round"/>
  <path d="M14 14 L10 18" stroke="#2F6686" stroke-width="2" stroke-linecap="round"/>
  <circle cx="22" cy="16" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function kesici()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="9" y="6" width="14" height="20" rx="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="14" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
  <rect x="13" y="18" width="6" height="5" fill="white" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function pompa()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="10" cy="16" r="6" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="16" y="13" width="12" height="6" rx="2" fill="white" stroke="#2F6686" stroke-width="2"/>
  <line x1="28" y1="16" x2="31" y2="16" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function irgat()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="10" cy="16" r="5" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="10" cy="16" r="2" fill="#FE6F0C"/>
  <rect x="16" y="12" width="10" height="8" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function vinc()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="4" width="12" height="8" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="16" y1="12" x2="16" y2="20" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 20c0 4 -4 4 -4 7h8c0 -3 -4 -3 -4 -7z" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function kompresor()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="4" y="10" width="20" height="12" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="24" cy="22" r="4" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="12" cy="16" r="3" fill="white"/>
</svg>
SVG;
    }

    public static function transpalet()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="14" width="16" height="4" rx="1" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="10" cy="22" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="20" cy="22" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
  <line x1="14" y1="14" x2="14" y2="8" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function kriko()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="16" width="20" height="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M6 16 L16 10 L26 16" fill="none" stroke="#2F6686" stroke-width="2"/>
  <rect x="14" y="6" width="4" height="6" fill="white" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function caraskal()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="10" r="5" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="16" y1="15" x2="16" y2="22" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 22c0 3 -3 3 -3 6h6c0 -3 -3 -3 -3 -6z" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function bezsapan()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <path d="M10 6 L16 20 L22 6" fill="none" stroke="#2F6686" stroke-width="3" stroke-linecap="round"/>
  <circle cx="16" cy="20" r="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function zincirsapan()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="10" cy="8" r="4" fill="none" stroke="#2F6686" stroke-width="2"/>
  <circle cx="22" cy="8" r="4" fill="none" stroke="#2F6686" stroke-width="2"/>
  <line x1="10" y1="12" x2="16" y2="24" stroke="#2F6686" stroke-width="3"/>
  <line x1="22" y1="12" x2="16" y2="24" stroke="#2F6686" stroke-width="3"/>
  <circle cx="16" cy="24" r="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function celiksapan()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <path d="M12 6 L16 20 L20 6" stroke="#2F6686" stroke-width="2" fill="none"/>
  <circle cx="16" cy="20" r="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function kurtagzi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <path d="M8 10 L24 10 L20 16 L24 22 L8 22" fill="none" stroke="#2F6686" stroke-width="2"/>
  <circle cx="12" cy="16" r="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function betonkesme()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="16" r="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 6 L16 26" stroke="#2F6686" stroke-width="2"/>
  <path d="M6 16 L26 16" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function lastiktekerli()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="10" width="16" height="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="12" cy="22" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="20" cy="22" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function yikamamakinesi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="20" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="16" r="6" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function sujeti()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="10" width="20" height="12" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="12" cy="16" r="4" fill="white"/>
  <line x1="26" y1="16" x2="30" y2="16" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function separator()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="8" width="12" height="16" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="10" y1="16" x2="22" y2="16" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="12" r="2" fill="white"/>
  <circle cx="16" cy="20" r="2" fill="white"/>
</svg>
SVG;
    }

    public static function buharkazani()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="10" width="12" height="12" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 6 C12 8 20 8 16 10" stroke="#2F6686" stroke-width="2" fill="none"/>
</svg>
SVG;
    }

    public static function depolamatanki()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="20" rx="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="8" y1="10" x2="24" y2="10" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function genlesmetanki()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="14" r="8" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="12" y="22" width="8" height="4" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function karistirici()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="16" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="16" y1="8" x2="16" y2="24" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="18" r="3" fill="white"/>
</svg>
SVG;
    }

    public static function fan()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="16" r="12" fill="white" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 8c4 0 6 2 6 4s-2 3 -6 3" fill="#FE6F0C"/>
  <path d="M8 16c0 4 2 6 4 6s3-2 3-6" fill="#FE6F0C"/>
  <path d="M16 24c-4 0 -6 -2 -6 -4s2 -3 6 -3" fill="#FE6F0C"/>
  <circle cx="16" cy="16" r="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function klima()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="8" width="20" height="16" rx="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="16" r="5" fill="white" stroke="#2F6686" stroke-width="2"/>
  <line x1="10" y1="12" x2="10" y2="20" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function kurutucu()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="16" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="16" r="5" fill="white" stroke="#2F6686" stroke-width="2"/>
  <path d="M14 13 L18 19" stroke="#2F6686" stroke-width="2"/>
  <path d="M18 13 L14 19" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function testere()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="10" width="20" height="6" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M6 16 L26 16 L24 20 L22 16 L20 20 L18 16 L16 20 L14 16 L12 20 L10 16 L8 20 Z"
        fill="#2F6686"/>
</svg>
SVG;
    }

    public static function taslama()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="16" r="8" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="16" r="3" fill="white" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function freze()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="20" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="12" y1="10" x2="20" y2="18" stroke="#2F6686" stroke-width="2"/>
  <line x1="20" y1="10" x2="12" y2="18" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function torna()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="5" y="12" width="22" height="8" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="10" cy="16" r="2" fill="white" stroke="#2F6686" stroke-width="1.5"/>
  <circle cx="22" cy="16" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function matkap()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="10" width="14" height="8" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="20" y="12" width="6" height="4" fill="white" stroke="#2F6686" stroke-width="2"/>
  <line x1="26" y1="14" x2="29" y2="14" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function cncplazma()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="6" width="20" height="14" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 20 L16 26" stroke="#2F6686" stroke-width="2"/>
  <path d="M14 26 L18 26 L16 30 Z" fill="white" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function balansmakinasi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="12" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="22" r="5" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function firin()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="20" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="12" y="12" width="8" height="8" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function magnet()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <path d="M10 6 L22 6 L22 18 C22 24 10 24 10 18 Z" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="10" y1="10" x2="22" y2="10" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function romork()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="12" width="18" height="8" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="24" y1="16" x2="30" y2="16" stroke="#2F6686" stroke-width="2"/>
  <circle cx="12" cy="22" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function koprubasamak()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="8" y="14" width="16" height="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="8" y="20" width="16" height="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function tasimasepeti()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="10" width="20" height="12" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="6" y1="10" x2="16" y2="4" stroke="#2F6686" stroke-width="2"/>
  <line x1="26" y1="10" x2="16" y2="4" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function susebili()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="4" width="12" height="24" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="14" r="4" fill="white"/>
  <rect x="12" y="22" width="8" height="3" fill="white"/>
</svg>
SVG;
    }

    public static function silo()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="6" width="12" height="20" rx="1" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <polygon points="16,2 24,6 8,6" fill="#2F6686"/>
</svg>
SVG;
    }

    public static function kollektor()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="14" width="20" height="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="10" cy="22" r="2" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="22" r="2" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="22" cy="22" r="2" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function sayac()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="16" rx="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="16" y1="12" x2="16" y2="20" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function olcualeti()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="16" r="10" fill="white" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 16 L22 10" stroke="#FE6F0C" stroke-width="3"/>
</svg>
SVG;
    }

    public static function paratoner()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <line x1="16" y1="4" x2="16" y2="20" stroke="#2F6686" stroke-width="2"/>
  <polygon points="12,20 20,20 16,28" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function dolap()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="4" width="16" height="24" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="16" y1="4" x2="16" y2="28" stroke="#2F6686" stroke-width="2"/>
  <circle cx="12" cy="16" r="1.5" fill="white"/>
  <circle cx="20" cy="16" r="1.5" fill="white"/>
</svg>
SVG;
    }

    public static function raf()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <line x1="6" y1="10" x2="26" y2="10" stroke="#2F6686" stroke-width="3"/>
  <line x1="6" y1="16" x2="26" y2="16" stroke="#FE6F0C" stroke-width="3"/>
  <line x1="6" y1="22" x2="26" y2="22" stroke="#2F6686" stroke-width="3"/>
</svg>
SVG;
    }

    public static function ofisekipmani()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="12" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="10" y="20" width="12" height="4" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function elektrikpanosu()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="20" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <polygon points="14,12 18,12 16,16" fill="white" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function ups()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="6" width="12" height="20" rx="2" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="18" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function yanginsondurme()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="6" width="12" height="20" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M16 8 L12 14 L20 14 Z" fill="white"/>
</svg>
SVG;
    }

    public static function yanginalgılama()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="10" r="6" fill="white" stroke="#2F6686" stroke-width="2"/>
  <rect x="12" y="16" width="8" height="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function torkanahtari()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="14" width="20" height="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="26" cy="16" r="3" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function elektriksupurgesi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="20" r="8" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="14" y="6" width="4" height="8" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function perdekapi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="4" width="16" height="24" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="12" y1="4" x2="12" y2="28" stroke="#2F6686" stroke-width="2"/>
  <line x1="20" y1="4" x2="20" y2="28" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function mutfak()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="10" y="18" width="12" height="8" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function kantar()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="12" width="20" height="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="12" y="6" width="8" height="6" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function terazi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <line x1="16" y1="6" x2="16" y2="26" stroke="#2F6686" stroke-width="2"/>
  <circle cx="10" cy="18" r="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="22" cy="18" r="4" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function kilit()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="10" y="14" width="12" height="12" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <path d="M12 14 V10 A4 4 0 0 1 20 10 V14" fill="none" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function seyyarkablo()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <circle cx="16" cy="16" r="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="16" r="4" fill="white" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function paftamakinesi()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="10" width="20" height="12" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <line x1="10" y1="10" x2="22" y2="22" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function perdekapidetay()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="4" width="16" height="24" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="10" y="6" width="12" height="20" fill="white" stroke="#2F6686" stroke-width="1.5"/>
</svg>
SVG;
    }

    public static function celiksapan_detay()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <path d="M12 6 L16 26 L20 6" fill="none" stroke="#2F6686" stroke-width="3"/>
  <circle cx="16" cy="26" r="3" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
</svg>
SVG;
    }

    public static function raf_detay()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="6" y="8" width="20" height="4" fill="#FE6F0C"/>
  <rect x="6" y="14" width="20" height="4" fill="#2F6686"/>
  <rect x="6" y="20" width="20" height="4" fill="#FE6F0C"/>
</svg>
SVG;
    }

    public static function mutfak_detay()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="6" width="16" height="10" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="10" y="18" width="12" height="6" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="11" r="2" fill="white"/>
</svg>
SVG;
    }

    public static function ofisekipmani_detay()
    {
        return <<<'SVG'
<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
  <rect x="8" y="8" width="16" height="12" fill="#FE6F0C" stroke="#2F6686" stroke-width="2"/>
  <rect x="10" y="20" width="12" height="6" fill="white" stroke="#2F6686" stroke-width="2"/>
  <circle cx="16" cy="14" r="2" fill="white"/>
</svg>
SVG;
    }
}
