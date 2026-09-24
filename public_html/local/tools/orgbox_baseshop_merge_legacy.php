<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

global $USER;
if (!$USER->IsAdmin()) {
    \CHTTP::SetStatus('403 Forbidden');
    die('Доступ только для администратора.');
}
if (!Loader::includeModule('iblock')) {
    die('Модуль iblock недоступен.');
}

$targetTypeId = 'orgbox_baseshop';
$targetType = \CIBlockType::GetByID($targetTypeId)->Fetch();
if (!$targetType) {
    die('Тип orgbox_baseshop не найден.');
}

$types = [];
$typeList = \CIBlockType::GetList([], []);
while ($type = $typeList->Fetch()) {
    if ($type['ID'] !== $targetTypeId && mb_strtolower(trim((string) $type['NAME'])) === mb_strtolower('Темный стиль')) {
        $types[] = $type;
    }
}
if (count($types) !== 1) {
    $candidates = [];
    $iblocks = \CIBlock::GetList(['ID' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($iblock = $iblocks->Fetch()) {
        $typeId = (string) $iblock['IBLOCK_TYPE_ID'];
        if ($typeId === $targetTypeId) {
            continue;
        }
        $hasProductFields = \CIBlockProperty::GetList([], ['IBLOCK_ID' => (int) $iblock['ID'], 'CODE' => 'PRICE'])->Fetch()
            && \CIBlockProperty::GetList([], ['IBLOCK_ID' => (int) $iblock['ID'], 'CODE' => 'ARTICLE'])->Fetch();
        $hasOrderFields = \CIBlockProperty::GetList([], ['IBLOCK_ID' => (int) $iblock['ID'], 'CODE' => 'PRODUCT_ID'])->Fetch()
            && \CIBlockProperty::GetList([], ['IBLOCK_ID' => (int) $iblock['ID'], 'CODE' => 'TOTAL'])->Fetch();
        if ($hasProductFields || $hasOrderFields) {
            $candidates[$typeId] = true;
        }
        $name = mb_strtolower(trim((string) $iblock['NAME']));
        if (in_array($name, ['товары', 'заказы'], true)) {
            $candidates[$typeId] = true;
        }
    }
    if (count($candidates) === 1) {
        $legacyTypeId = (string) array_key_first($candidates);
        $legacyType = \CIBlockType::GetByID($legacyTypeId)->Fetch();
        $types = $legacyType ? [$legacyType] : [];
    }
}
if (count($types) !== 1) {
    die('Не найден единственный старый тип инфоблоков. Найдено: ' . count($types));
}
$legacyType = $types[0];

$getIblocks = static function (string $typeId): array {
    $result = [];
    $rows = \CIBlock::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['TYPE' => $typeId]);
    while ($row = $rows->Fetch()) {
        $row['ID'] = (int) $row['ID'];
        $elements = \CIBlockElement::GetList([], ['IBLOCK_ID' => $row['ID']], false, false, ['ID']);
        $row['ELEMENT_COUNT'] = 0;
        while ($elements->Fetch()) {
            $row['ELEMENT_COUNT']++;
        }
        $result[] = $row;
    }
    return $result;
};

$legacyBlocks = $getIblocks((string) $legacyType['ID']);
$targetBlocks = $getIblocks($targetTypeId);
$legacyByName = [];
foreach ($legacyBlocks as $block) {
    $legacyByName[mb_strtolower(trim((string) $block['NAME']))] = $block;
}
$targetByCode = [];
foreach ($targetBlocks as $block) {
    $targetByCode[(string) $block['CODE']] = $block;
}

$sources = [];
$targetsToDelete = [];
foreach ([['Товары', 'orgbox_baseshop_products'], ['Заказы', 'orgbox_baseshop_orders']] as [$name, $code]) {
    $source = $legacyByName[mb_strtolower($name)] ?? null;
    $target = $targetByCode[$code] ?? null;
    if (!$source || !$target) {
        die('Не найден ожидаемый инфоблок: ' . $name);
    }
    if ((int) $target['ELEMENT_COUNT'] > 0) {
        die('В целевом инфоблоке «' . $target['NAME'] . '» уже есть элементы — остановлено без изменений.');
    }
    $sources[] = [$source, $code];
    $targetsToDelete[] = $target;
}

$confirm = ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && ($_POST['confirm'] ?? '') === 'MERGE');
if ($confirm) {
    foreach ($sources as [$source, $code]) {
        $iblock = new \CIBlock();
        if (!$iblock->Update((int) $source['ID'], ['IBLOCK_TYPE_ID' => $targetTypeId, 'CODE' => $code])) {
            throw new RuntimeException($iblock->LAST_ERROR ?: 'Не удалось перенести инфоблок.');
        }
    }
    foreach ($targetsToDelete as $target) {
        $iblock = new \CIBlock();
        if (!$iblock->Delete((int) $target['ID'])) {
            throw new RuntimeException('Не удалось удалить пустой дубликат «' . $target['NAME'] . '».');
        }
    }
    if (!\CIBlockType::Update((string) $legacyType['ID'], ['NAME' => 'Архивный тип'])) {
        throw new RuntimeException('Не удалось переименовать старый тип перед удалением.');
    }
    if (!\CIBlockType::Delete((string) $legacyType['ID'])) {
        throw new RuntimeException('Не удалось удалить пустой старый тип.');
    }
    Option::set('orgbox.baseshop', 'products_iblock_id', (string) $sources[0][0]['ID']);
    Option::set('orgbox.baseshop', 'orders_iblock_id', (string) $sources[1][0]['ID']);
    echo 'Объединение завершено. Обновите страницу.';
    exit;
}
?><!doctype html>
<meta charset="utf-8">
<title>Объединение orgBox: BaseShop</title>
<h1>Объединение разделов</h1>
<p>Будет сохранён тип <b>orgBox: BaseShop</b>, товары и заказы перенесены в него, пустые дубликаты удалены.</p>
<ul><?php foreach ($sources as [$source, $code]): ?><li><?=htmlspecialcharsbx($source['NAME'])?>: <?=htmlspecialcharsbx((string) $source['ELEMENT_COUNT'])?> элементов</li><?php endforeach; ?></ul>
<form method="post"><input type="hidden" name="sessid" value="<?=htmlspecialcharsbx(bitrix_sessid())?>"><input type="hidden" name="confirm" value="MERGE"><button type="submit">Подтвердить объединение</button></form>
