<?php

declare(strict_types=1);

use Bitrix\Main\Page\Asset;
use DarkStyle\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$asset = Asset::getInstance();
$asset->addCss('/local/css/style.css');
$asset->addCss('/local/css/hero-title.css');
$asset->addCss('/local/css/products.css');
$asset->addCss('/local/css/template.css');
$asset->addJs('/local/js/site.js');
$requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/order(?:/|$)#', $requestPath)) {
    $asset->addCss('/local/css/order.css');
    $asset->addJs('/local/js/checkout.js');
}
$siteName = (string) Config::get('site_name', 'Тёмный стиль');
?>
<!doctype html>
<html lang="<?=LANGUAGE_ID?>">
<head>
    <meta charset="<?=LANG_CHARSET?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Russo+One&display=swap" rel="stylesheet">
    <?php $APPLICATION->ShowHead(); ?>
    <title><?php $APPLICATION->ShowTitle(); ?> — <?=htmlspecialcharsbx($siteName)?></title>
</head>
<body class="darkstyle-page">
<?php $APPLICATION->ShowPanel(); ?>
<div class="darkstyle-site">
<header class="header" id="glavnaya">
    <nav class="nav wrap">
        <a class="brand" href="/"><?=htmlspecialcharsbx(mb_strtoupper($siteName))?></a>
        <button class="menu-button" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
        <div class="menu">
            <a href="/#preimushchestva">Преимущества</a><a href="/catalog/">Каталог</a><a href="/#galereya">Галерея</a><a href="/#primenenie">Применение</a><a href="/#voprosy">FAQ</a>
            <a class="nav-cta" href="/catalog/">Выбрать товар</a>
        </div>
    </nav>
</header>
<main>
