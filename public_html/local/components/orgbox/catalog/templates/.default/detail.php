<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
global $APPLICATION;
$item = $arResult['ITEM'];
$images = $item['GALLERY'] ?: ['/local/assets/product-placeholder.svg'];
?>
<section class="section product-detail">
    <div class="wrap">
        <nav class="breadcrumbs"><a href="/catalog/">Каталог</a><span>→</span><a href="/catalog/<?=rawurlencode($item['SECTION']['CODE'])?>/"><?=htmlspecialcharsbx($item['SECTION']['NAME'])?></a></nav>
        <div class="product-detail-grid">
            <div class="detail-gallery">
                <img class="detail-main-image" src="<?=htmlspecialcharsbx($images[0])?>" alt="<?=htmlspecialcharsbx($item['NAME'])?>">
                <?php if (count($images) > 1): ?><div class="detail-thumbs"><?php foreach ($images as $index => $image): ?><button type="button" class="detail-thumb<?=$index === 0 ? ' active' : ''?>" data-image="<?=htmlspecialcharsbx($image)?>"><img src="<?=htmlspecialcharsbx($image)?>" alt=""></button><?php endforeach; ?></div><?php endif; ?>
            </div>
            <div class="product-detail-info">
                <span class="number"><?=htmlspecialcharsbx($item['BADGE'] ?: $item['SECTION']['NAME'])?></span>
                <h1><?=htmlspecialcharsbx($arResult['SEO_H1'] ?? $item['NAME'])?></h1>
                <p class="detail-price"><?=number_format($item['PRICE'], 0, ',', ' ')?> ₽<?php if ($item['OLD_PRICE'] > $item['PRICE']): ?> <s><?=number_format($item['OLD_PRICE'], 0, ',', ' ')?> ₽</s><?php endif; ?></p>
                <p class="detail-description"><?=nl2br(htmlspecialcharsbx($item['DETAIL_TEXT'] ?: $item['PREVIEW_TEXT']))?></p>
                <dl class="product-specs">
                    <?php if ($item['ARTICLE'] !== ''): ?><div><dt>Артикул</dt><dd><?=htmlspecialcharsbx($item['ARTICLE'])?></dd></div><?php endif; ?>
                    <?php if ($item['WEIGHT'] > 0): ?><div><dt>Вес</dt><dd><?=number_format($item['WEIGHT'], 0, ',', ' ')?> г</dd></div><?php endif; ?>
                    <?php if ($item['LENGTH'] > 0): ?><div><dt>Размер упаковки</dt><dd><?=htmlspecialcharsbx($item['LENGTH'] . ' × ' . $item['WIDTH'] . ' × ' . $item['HEIGHT'])?> см</dd></div><?php endif; ?>
                </dl>
                <a class="button buy-button" href="/order/?product=<?=(int) $item['ID']?>">КУПИТЬ →</a>
            </div>
        </div>
    </div>
</section>
