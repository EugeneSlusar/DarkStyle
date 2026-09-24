<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$sectionPicture = (string) ($arResult['SECTION']['PICTURE'] ?? '');
?>
<section class="section catalog catalog-page catalog-section-page">
    <div class="wrap">
        <div class="catalog-section-hero"<?=$sectionPicture !== '' ? ' style="--catalog-section-background:url(\'' . htmlspecialcharsbx($sectionPicture) . '\')"' : ''?>>
            <div class="catalog-section-hero-content">
                <div class="section-head"><div><span class="number">КАТАЛОГ</span><h1><?=htmlspecialcharsbx($arResult['SECTION']['NAME'] ?? 'Каталог')?></h1></div></div>
                <?php if ($arResult['SECTIONS']): ?>
                    <nav class="catalog-tags" aria-label="Разделы каталога">
                        <span class="catalog-tags-label">Выберите автомобиль</span>
                        <div class="catalog-tags-list"><a href="/catalog/">Все</a><?php foreach ($arResult['SECTIONS'] as $section): ?><a href="<?=htmlspecialcharsbx($section['URL'])?>"><?=htmlspecialcharsbx($section['NAME'])?></a><?php endforeach; ?></div>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <div class="catalog-section-products">
            <?php if (!$arResult['ITEMS']): ?><div class="catalog-empty">В этом разделе пока нет товаров.</div><?php else: ?>
                <div class="cards product-grid"><?php foreach ($arResult['ITEMS'] as $item) require __DIR__ . '/_card.php'; ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>
