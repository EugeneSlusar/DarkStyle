<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    return;
}

$moduleId = 'orgbox.baseshop';
$legacyModuleId = 'darkstyle.core';
$siteId = defined('SITE_ID') ? SITE_ID : (string) \CSite::GetDefSite();
if ($siteId !== '') {
    $site = new \CSite();
    $site->Update($siteId, [
        'TEMPLATE' => [['CONDITION' => '', 'SORT' => 1, 'TEMPLATE' => 'orgbox_baseshop']],
    ]);
}

if (Option::get($moduleId, 'migration_completed', '') === 'Y' || !Loader::includeModule('iblock')) {
    return;
}

$findIblock = static function (string $type, string $code): int {
    $row = \CIBlock::GetList([], ['TYPE' => $type, 'CODE' => $code])->Fetch();

    return (int) ($row['ID'] ?? 0);
};

$legacyConfigFile = __DIR__ . '/../darkstyle/config.local.php';
$legacyFileSettings = is_file($legacyConfigFile) ? require $legacyConfigFile : [];
$legacyFileSettings = is_array($legacyFileSettings) ? $legacyFileSettings : [];
$settingKeys = [
    'site_name', 'theme', 'manager_email', 'phone', 'origin_city',
    'cdek_client_id', 'cdek_client_secret', 'russian_post_token',
    'russian_post_key', 'delivery_fallback_enabled',
];

foreach ($settingKeys as $key) {
    if (Option::get($moduleId, $key, '') !== '') {
        continue;
    }
    $value = Option::get($legacyModuleId, $key, '');
    if ($value === '' && array_key_exists($key, $legacyFileSettings)) {
        $value = is_bool($legacyFileSettings[$key])
            ? ($legacyFileSettings[$key] ? 'Y' : 'N')
            : (string) $legacyFileSettings[$key];
    }
    if ($value !== '') {
        Option::set($moduleId, $key, $value);
    }
}

$typeId = 'orgbox_baseshop';
if (!\CIBlockType::GetByID($typeId)->Fetch()) {
    $type = new \CIBlockType();
    if (!$type->Add([
        'ID' => $typeId,
        'SECTIONS' => 'Y',
        'IN_RSS' => 'N',
        'SORT' => 100,
        'LANG' => [
            'ru' => ['NAME' => 'orgBox: BaseShop', 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Элементы'],
            'en' => ['NAME' => 'orgBox: BaseShop', 'SECTION_NAME' => 'Sections', 'ELEMENT_NAME' => 'Elements'],
        ],
    ])) {
        return;
    }
}

$productsId = $findIblock($typeId, 'orgbox_baseshop_products');
if ($productsId <= 0) {
    $productsId = (int) Option::get($legacyModuleId, 'products_iblock_id', '0');
}
if ($productsId <= 0) {
    $productsId = $findIblock('darkstyle', 'darkstyle_products');
}

$ordersId = $findIblock($typeId, 'orgbox_baseshop_orders');
if ($ordersId <= 0) {
    $ordersId = (int) Option::get($legacyModuleId, 'orders_iblock_id', '0');
}
if ($ordersId <= 0) {
    $ordersId = $findIblock('darkstyle', 'darkstyle_orders');
}

if ($productsId <= 0 || $ordersId <= 0 || !\CIBlock::GetByID($productsId)->Fetch() || !\CIBlock::GetByID($ordersId)->Fetch()) {
    return;
}

$productsIblock = new \CIBlock();
$ordersIblock = new \CIBlock();
if (
    !$productsIblock->Update($productsId, ['IBLOCK_TYPE_ID' => $typeId, 'CODE' => 'orgbox_baseshop_products'])
    || !$ordersIblock->Update($ordersId, ['IBLOCK_TYPE_ID' => $typeId, 'CODE' => 'orgbox_baseshop_orders'])
) {
    return;
}

Option::set($moduleId, 'products_iblock_id', (string) $productsId);
Option::set($moduleId, 'orders_iblock_id', (string) $ordersId);

if (!\CEventType::GetList(['TYPE_ID' => 'ORGBOX_BASESHOP_NEW_ORDER', 'LID' => LANGUAGE_ID])->Fetch()) {
    $eventType = new \CEventType();
    $eventType->Add([
        'LID' => LANGUAGE_ID,
        'EVENT_NAME' => 'ORGBOX_BASESHOP_NEW_ORDER',
        'NAME' => 'Новый заказ интернет-магазина',
        'DESCRIPTION' => "#ORDER_ID# - номер заказа\n#PRODUCT_NAME# - товар\n#TOTAL# - итог\n#CUSTOMER_NAME# - получатель\n#PHONE# - телефон\n#EMAIL# - email\n#CITY# - город\n#ADDRESS# - адрес\n#DELIVERY_SERVICE# - доставка\n#COMMENT# - комментарий",
    ]);
}

if (!\CEventMessage::GetList($by = 'id', $order = 'desc', ['TYPE_ID' => 'ORGBOX_BASESHOP_NEW_ORDER'])->Fetch()) {
    $eventMessage = new \CEventMessage();
    $eventMessage->Add([
        'ACTIVE' => 'Y',
        'EVENT_NAME' => 'ORGBOX_BASESHOP_NEW_ORDER',
        'LID' => [SITE_ID],
        'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
        'EMAIL_TO' => '#EMAIL_TO#',
        'SUBJECT' => 'Новый заказ №#ORDER_ID#: #PRODUCT_NAME#',
        'BODY_TYPE' => 'text',
        'MESSAGE' => "Заказ №#ORDER_ID#\n\nТовар: #PRODUCT_NAME#\nКоличество: #QUANTITY#\nИтого: #TOTAL# руб.\n\nПолучатель: #CUSTOMER_NAME#\nТелефон: #PHONE#\nEmail: #EMAIL#\nАдрес: #POSTAL_CODE#, #CITY#, #ADDRESS#\nДоставка: #DELIVERY_SERVICE# (#DELIVERY_PRICE# руб.)\n\nКомментарий: #COMMENT#",
    ]);
}

Option::set($moduleId, 'migration_completed', 'Y');
