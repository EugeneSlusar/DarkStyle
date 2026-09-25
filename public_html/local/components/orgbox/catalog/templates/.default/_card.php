<?php
/** @var array $item */
$images = array_values(array_filter((array) ($item['GALLERY'] ?? [])));
if ($images === [] && $item['IMAGE'] !== '') {
    $images[] = $item['IMAGE'];
}
if ($images === []) {
    $images[] = '/local/assets/product-placeholder.svg';
}
$images = array_slice(array_values(array_unique($images)), 0, 5);
$image = $images[0];
$hasGallery = count($images) > 1;
?>
<article class="card product-card">
    <div class="product-gallery"<?= $hasGallery ? ' data-product-gallery' : '' ?>>
        <a class="product-gallery-link" href="<?=htmlspecialcharsbx($item['URL'])?>">
            <img class="gallery-main" src="<?=htmlspecialcharsbx($image)?>" alt="<?=htmlspecialcharsbx($item['NAME'])?>" loading="lazy" data-product-gallery-image>
            <?php if ($item['BADGE'] !== ''): ?><span class="product-badge"><?=htmlspecialcharsbx($item['BADGE'])?></span><?php endif; ?>
        </a>
        <?php if ($hasGallery): ?>
            <div class="product-gallery-segments" style="--product-gallery-count: <?=count($images)?>" aria-label="Изображения товара">
                <?php foreach ($images as $index => $galleryImage): ?>
                    <button
                        class="product-gallery-segment<?= $index === 0 ? ' is-active' : '' ?>"
                        type="button"
                        data-product-gallery-image-src="<?=htmlspecialcharsbx($galleryImage)?>"
                        aria-label="Изображение <?=($index + 1)?>"
                        aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                    ></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="product-info">
        <small class="product-meta"><?=htmlspecialcharsbx($item['SECTION']['NAME'])?></small>
        <h3><a href="<?=htmlspecialcharsbx($item['URL'])?>"><?=htmlspecialcharsbx($item['NAME'])?></a></h3>
        <div class="product-price-row">
            <p class="price"><?=number_format($item['PRICE'], 0, ',', ' ')?> ₽<?php if ($item['OLD_PRICE'] > $item['PRICE']): ?> <s><?=number_format($item['OLD_PRICE'], 0, ',', ' ')?> ₽</s><?php endif; ?></p>
            <?php if ($item['ARTICLE'] !== ''): ?><p class="product-article">Арт.: <?=htmlspecialcharsbx($item['ARTICLE'])?></p><?php endif; ?>
        </div>
        <p class="rating"><?=htmlspecialcharsbx($item['PREVIEW_TEXT'])?></p>
    </div>
</article>
