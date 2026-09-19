<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Оформление заказа');
$productId = max(0, (int) ($_GET['product'] ?? 0));

$APPLICATION->IncludeComponent(
    'darkstyle:checkout',
    '',
    ['PRODUCT_ID' => $productId, 'CACHE_TIME' => 0]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
