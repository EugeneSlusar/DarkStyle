<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->IncludeComponent('orgbox:catalog', '', ['MODE' => 'catalog', 'CACHE_TIME' => 3600]);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
