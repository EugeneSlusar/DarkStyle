<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<?php if (!$arResult['ITEMS']): ?>
    <div class="catalog-empty">Каталог готов к наполнению через административную часть Bitrix.</div>
<?php else: ?>
    <div class="cards product-grid">
        <?php foreach ($arResult['ITEMS'] as $item) require __DIR__ . '/_card.php'; ?>
    </div>
<?php endif; ?>
