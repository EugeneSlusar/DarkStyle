<?php

/** @var CBitrixComponentTemplate $this */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$items = $arResult['ITEMS'] ?? [];
if ($items === []) {
    return;
}

global $APPLICATION, $USER;
$showBannerActions = is_object($USER)
    && $USER->IsAdmin()
    && $APPLICATION->GetShowIncludeAreas()
    && ($arResult['ADD_LINK'] ?? '') !== '';
?>
<section class="home-slider" data-home-slider data-autoplay-delay="<?=intval($arResult['AUTOPLAY_DELAY'] ?? 7000)?>">
    <div class="home-slider-track" data-home-slider-track>
    <?php foreach ($items as $index => $item): ?>
        <article class="hero home-slider-slide<?=$index === 0 ? ' is-active' : ''?>"
                 data-home-slider-slide
                 data-banner-edit-url="<?=htmlspecialcharsbx($item['EDIT_LINK'])?>"
                 data-banner-delete-url="<?=htmlspecialcharsbx($item['DELETE_LINK'])?>"
                 aria-hidden="<?=$index === 0 ? 'false' : 'true'?>">
            <img class="hero-bg" src="<?=htmlspecialcharsbx($item['IMAGE'])?>" alt="<?=htmlspecialcharsbx($item['NAME'])?>">
            <div class="hero-shade"></div><div class="grid-overlay"></div>
            <div class="wrap hero-content">
                <?php if ($item['SUBTITLE'] !== ''): ?><div class="eyebrow"><span></span><?=htmlspecialcharsbx($item['SUBTITLE'])?></div><?php endif; ?>
                <h1><?=nl2br(htmlspecialcharsbx($item['NAME']))?></h1>
                <?php if ($item['DESCRIPTION'] !== ''): ?><p><?=nl2br(htmlspecialcharsbx($item['DESCRIPTION']))?></p><?php endif; ?>
                <?php if ($item['BUTTON_TEXT'] !== '' || $item['SECOND_BUTTON_TEXT'] !== ''): ?>
                    <div class="hero-actions">
                        <?php if ($item['BUTTON_TEXT'] !== ''): ?><a class="button primary" href="<?=htmlspecialcharsbx($item['BUTTON_LINK'] ?: '/catalog/') ?>"><?=htmlspecialcharsbx($item['BUTTON_TEXT'])?><b>→</b></a><?php endif; ?>
                        <?php if ($item['SECOND_BUTTON_TEXT'] !== ''): ?><a class="text-link" href="<?=htmlspecialcharsbx($item['SECOND_BUTTON_LINK'] ?: '#primenenie') ?>"><?=htmlspecialcharsbx($item['SECOND_BUTTON_TEXT'])?><b>↘</b></a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <?php if ($showBannerActions): ?>
        <div class="home-slider-edit-actions" aria-label="Управление баннерами">
            <a href="<?=htmlspecialcharsbx($arResult['ADD_LINK'])?>" target="_blank" rel="noopener">Добавить баннер</a>
            <button type="button" data-home-slider-edit>Изменить баннер</button>
            <button class="is-danger" type="button" data-home-slider-delete>Удалить баннер</button>
        </div>
    <?php endif; ?>
    <?php if (count($items) > 1): ?>
        <div class="home-slider-controls wrap">
            <button class="home-slider-arrow home-slider-prev" type="button" data-home-slider-prev aria-label="Предыдущий баннер">←</button>
            <div class="home-slider-dots" role="tablist" aria-label="Баннеры главной страницы">
                <?php foreach ($items as $index => $item): ?><button class="<?=$index === 0 ? 'is-active' : ''?>" type="button" data-home-slider-dot="<?=$index?>" aria-label="Баннер <?=($index + 1)?>" aria-selected="<?=$index === 0 ? 'true' : 'false'?>"></button><?php endforeach; ?>
            </div>
            <button class="home-slider-arrow home-slider-next" type="button" data-home-slider-next aria-label="Следующий баннер">→</button>
        </div>
    <?php endif; ?>
</section>
