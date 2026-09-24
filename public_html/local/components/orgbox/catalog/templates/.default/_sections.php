<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$sections = $sections ?? [];
$productCountLabel = static function (int $count): string {
    $lastTwo = $count % 100;
    $last = $count % 10;
    if ($lastTwo >= 11 && $lastTwo <= 14) {
        return $count . ' товаров';
    }
    if ($last === 1) {
        return $count . ' товар';
    }
    if ($last >= 2 && $last <= 4) {
        return $count . ' товара';
    }

    return $count . ' товаров';
};
?>
<?php if ($sections === []): ?>
    <div class="catalog-empty">Категории пока не добавлены.</div>
<?php else: ?>
    <div class="catalog-section-grid">
        <?php foreach ($sections as $index => $section): ?>
            <?php $image = $section['PICTURE'] ?: '/local/assets/product-placeholder.svg'; ?>
            <a class="catalog-section-card" href="<?=htmlspecialcharsbx($section['URL'])?>">
                <img src="<?=htmlspecialcharsbx($image)?>"
                     alt="<?=htmlspecialcharsbx($section['NAME'])?>"
                     loading="<?=$index === 0 ? 'eager' : 'lazy'?>">
                <span class="catalog-section-shade"></span>
                <span class="catalog-section-info">
                    <strong><?=htmlspecialcharsbx($section['NAME'])?></strong>
                    <small><?=$productCountLabel((int) $section['COUNT'])?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
