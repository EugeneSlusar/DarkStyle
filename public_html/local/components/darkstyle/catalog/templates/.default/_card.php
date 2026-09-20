<?php
/** @var array $item */
$image = $item['IMAGE'] ?: '/local/assets/product-placeholder.svg';
?>
<article class="card product-card">
    <a class="product-gallery" href="<?=htmlspecialcharsbx($item['URL'])?>">
        <img class="gallery-main" src="<?=htmlspecialcharsbx($image)?>" alt="<?=htmlspecialcharsbx($item['NAME'])?>" loading="lazy">
        <?php if ($item['BADGE'] !== ''): ?><span class="product-badge"><?=htmlspecialcharsbx($item['BADGE'])?></span><?php endif; ?>
    </a>
    <div class="product-info">
        <small><?=htmlspecialcharsbx($item['SECTION']['NAME'])?><?= $item['ARTICLE'] !== '' ? ' · ' . htmlspecialcharsbx($item['ARTICLE']) : '' ?></small>
        <h3><a href="<?=htmlspecialcharsbx($item['URL'])?>"><?=htmlspecialcharsbx($item['NAME'])?></a></h3>
        <p class="price"><?=number_format($item['PRICE'], 0, ',', ' ')?> ₽<?php if ($item['OLD_PRICE'] > $item['PRICE']): ?> <s><?=number_format($item['OLD_PRICE'], 0, ',', ' ')?> ₽</s><?php endif; ?></p>
        <p class="rating"><?=htmlspecialcharsbx($item['PREVIEW_TEXT'])?></p>
        <a class="product-more" href="<?=htmlspecialcharsbx($item['URL'])?>">ПОДРОБНЕЕ →</a>
    </div>
</article>
