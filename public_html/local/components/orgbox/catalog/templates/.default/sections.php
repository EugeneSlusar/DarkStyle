<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$sections = $arResult['SECTIONS'] ?? [];
?>
<section class="section catalog catalog-page catalog-sections-page">
    <div class="wrap">
        <div class="section-head">
            <div>
                <span class="number">КАТАЛОГ</span>
                <h1>Категории</h1>
            </div>
            <p>Выберите марку и модель автомобиля</p>
        </div>

        <?php require __DIR__ . '/_sections.php'; ?>
    </div>
</section>
