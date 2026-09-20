<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

global $USER;
if (!$USER->IsAdmin()) {
    \CHTTP::SetStatus('403 Forbidden');
    die('Доступ разрешён только администратору.');
}
if (!Loader::includeModule('iblock')) {
    die('Модуль iblock недоступен.');
}

$messages = [];
$errors = [];
$legacyModuleId = 'darkstyle.core';
$getOption = static function (string $key): string {
    $value = Option::get('orgbox.baseshop', $key, '');

    return $value !== '' ? $value : Option::get('darkstyle.core', $key, '');
};

$findIblock = static function (string $type, string $code): int {
    $row = \CIBlock::GetList([], ['TYPE' => $type, 'CODE' => $code])->Fetch();
    return (int) ($row['ID'] ?? 0);
};

$ensureProperty = static function (int $iblockId, string $code, string $name, string $type = 'S', bool $multiple = false): void {
    if (\CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch()) {
        return;
    }
    $property = new \CIBlockProperty();
    if (!$property->Add([
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'CODE' => $code,
        'PROPERTY_TYPE' => $type,
        'MULTIPLE' => $multiple ? 'Y' : 'N',
        'ACTIVE' => 'Y',
        'SORT' => 500,
    ])) {
        throw new RuntimeException('Не удалось создать свойство ' . $code . '.');
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    try {
        $settingKeys = [
            'site_name', 'theme', 'manager_email', 'phone', 'origin_city',
            'cdek_client_id', 'cdek_client_secret', 'russian_post_token',
            'russian_post_key', 'delivery_fallback_enabled',
        ];
        $legacyConfigFile = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/darkstyle/config.local.php';
        $legacyFileSettings = is_file($legacyConfigFile) ? require $legacyConfigFile : [];
        $legacyFileSettings = is_array($legacyFileSettings) ? $legacyFileSettings : [];
        foreach ($settingKeys as $key) {
            if (Option::get('orgbox.baseshop', $key, '') !== '') {
                continue;
            }
            $value = Option::get($legacyModuleId, $key, '');
            if ($value === '' && array_key_exists($key, $legacyFileSettings)) {
                $value = is_bool($legacyFileSettings[$key])
                    ? ($legacyFileSettings[$key] ? 'Y' : 'N')
                    : (string) $legacyFileSettings[$key];
            }
            if ($value !== '') {
                Option::set('orgbox.baseshop', $key, $value);
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
                throw new RuntimeException('Не удалось создать тип инфоблоков.');
            }
        }

        $productsId = $findIblock($typeId, 'orgbox_baseshop_products');
        if ($productsId <= 0) {
            $legacyProductsId = (int) Option::get($legacyModuleId, 'products_iblock_id', '0');
            $productsId = $legacyProductsId > 0 && \CIBlock::GetByID($legacyProductsId)->Fetch() ? $legacyProductsId : 0;
        }
        if ($productsId <= 0) {
            $iblock = new \CIBlock();
            $productsId = (int) $iblock->Add([
                'ACTIVE' => 'Y',
                'NAME' => 'Товары',
                'CODE' => 'orgbox_baseshop_products',
                'IBLOCK_TYPE_ID' => $typeId,
                'LID' => [SITE_ID],
                'SORT' => 100,
                'VERSION' => 2,
                'LIST_PAGE_URL' => '/catalog/',
                'SECTION_PAGE_URL' => '/catalog/#SECTION_CODE#/',
                'DETAIL_PAGE_URL' => '/catalog/#SECTION_CODE#/#ELEMENT_CODE#/',
                'GROUP_ID' => ['1' => 'X', '2' => 'R'],
            ]);
            if ($productsId <= 0) {
                throw new RuntimeException('Не удалось создать инфоблок товаров.');
            }
        }
        $productsIblock = new \CIBlock();
        if (!$productsIblock->Update($productsId, [
            'IBLOCK_TYPE_ID' => $typeId,
            'CODE' => 'orgbox_baseshop_products',
        ])) {
            throw new RuntimeException('Не удалось обновить идентификаторы инфоблока товаров: ' . $productsIblock->LAST_ERROR);
        }

        $productProperties = [
            ['PRICE', 'Цена', 'N', false], ['ARTICLE', 'Артикул', 'S', false], ['WEIGHT', 'Вес, г', 'N', false],
            ['LENGTH', 'Длина, см', 'N', false], ['WIDTH', 'Ширина, см', 'N', false], ['HEIGHT', 'Высота, см', 'N', false],
            ['OLD_PRICE', 'Старая цена', 'N', false], ['GALLERY', 'Галерея', 'F', true], ['BADGE', 'Метка', 'S', false],
        ];
        foreach ($productProperties as [$code, $name, $type, $multiple]) {
            $ensureProperty($productsId, $code, $name, $type, $multiple);
        }

        $ordersId = $findIblock($typeId, 'orgbox_baseshop_orders');
        if ($ordersId <= 0) {
            $legacyOrdersId = (int) Option::get($legacyModuleId, 'orders_iblock_id', '0');
            $ordersId = $legacyOrdersId > 0 && \CIBlock::GetByID($legacyOrdersId)->Fetch() ? $legacyOrdersId : 0;
        }
        if ($ordersId <= 0) {
            $iblock = new \CIBlock();
            $ordersId = (int) $iblock->Add([
                'ACTIVE' => 'Y',
                'NAME' => 'Заказы',
                'CODE' => 'orgbox_baseshop_orders',
                'IBLOCK_TYPE_ID' => $typeId,
                'LID' => [SITE_ID],
                'SORT' => 200,
                'VERSION' => 2,
                'GROUP_ID' => ['1' => 'X', '2' => 'D'],
            ]);
            if ($ordersId <= 0) {
                throw new RuntimeException('Не удалось создать инфоблок заказов.');
            }
        }
        $ordersIblock = new \CIBlock();
        if (!$ordersIblock->Update($ordersId, [
            'IBLOCK_TYPE_ID' => $typeId,
            'CODE' => 'orgbox_baseshop_orders',
        ])) {
            throw new RuntimeException('Не удалось обновить идентификаторы инфоблока заказов: ' . $ordersIblock->LAST_ERROR);
        }

        $orderProperties = [
            ['PRODUCT_ID', 'ID товара', 'N'], ['PRODUCT_NAME', 'Товар', 'S'], ['PRICE', 'Цена', 'N'], ['QUANTITY', 'Количество', 'N'],
            ['CUSTOMER_NAME', 'Получатель', 'S'], ['PHONE', 'Телефон', 'S'], ['EMAIL', 'Email', 'S'], ['CITY', 'Город', 'S'],
            ['POSTAL_CODE', 'Индекс', 'S'], ['ADDRESS', 'Адрес', 'S'], ['DELIVERY_PROVIDER', 'Провайдер доставки', 'S'],
            ['DELIVERY_SERVICE', 'Услуга доставки', 'S'], ['DELIVERY_PRICE', 'Цена доставки', 'N'], ['COMMENT', 'Комментарий', 'S'],
            ['TOTAL', 'Итого', 'N'], ['CREATED_AT', 'Создан', 'S'], ['STATUS', 'Статус', 'S'],
        ];
        foreach ($orderProperties as [$code, $name, $type]) {
            $ensureProperty($ordersId, $code, $name, $type);
        }

        Option::set('orgbox.baseshop', 'products_iblock_id', (string) $productsId);
        Option::set('orgbox.baseshop', 'orders_iblock_id', (string) $ordersId);
        Option::set('orgbox.baseshop', 'manager_email', trim((string) ($_POST['manager_email'] ?? '')));
        Option::set('orgbox.baseshop', 'phone', trim((string) ($_POST['phone'] ?? '')));

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

        if (!empty($_POST['assign_template'])) {
            if (is_callable(['CSite', 'SetTemplate'])) {
                \CSite::SetTemplate(SITE_ID, [['CONDITION' => '', 'SORT' => 1, 'TEMPLATE' => 'orgbox_baseshop']]);
            } else {
                $messages[] = 'Назначьте шаблон orgbox_baseshop сайту вручную в административной части.';
            }
        }

        if (!empty($_POST['remove_legacy'])) {
            if (!\CIBlock::GetList([], ['TYPE' => 'darkstyle'])->Fetch() && \CIBlockType::GetByID('darkstyle')->Fetch()) {
                \CIBlockType::Delete('darkstyle');
            }

            $by = 'id';
            $order = 'asc';
            $legacyMessages = \CEventMessage::GetList($by, $order, ['TYPE_ID' => 'DARKSTYLE_NEW_ORDER']);
            while ($legacyMessage = $legacyMessages->Fetch()) {
                \CEventMessage::Delete((int) $legacyMessage['ID']);
            }
            \CEventType::Delete('DARKSTYLE_NEW_ORDER');
            Option::delete($legacyModuleId);
            if (is_file($legacyConfigFile)) {
                @unlink($legacyConfigFile);
            }
            $messages[] = 'Старые настройки и служебные сущности darkstyle удалены.';
        }

        if (!empty($_POST['demo']) && !\CIBlockSection::GetList([], ['IBLOCK_ID' => $productsId, 'CODE' => 'lada-niva'])->Fetch()) {
            $section = new \CIBlockSection();
            $sectionId = (int) $section->Add(['IBLOCK_ID' => $productsId, 'ACTIVE' => 'Y', 'NAME' => 'Lada Niva', 'CODE' => 'lada-niva']);
            if ($sectionId > 0) {
                $element = new \CIBlockElement();
                $picturePath = $_SERVER['DOCUMENT_ROOT'] . '/local/assets/products/product-01.jpg';
                $element->Add([
                    'IBLOCK_ID' => $productsId,
                    'IBLOCK_SECTION_ID' => $sectionId,
                    'ACTIVE' => 'Y',
                    'NAME' => 'Съёмная тонировка для Lada Niva 5%',
                    'CODE' => 'semnaya-tonirovka-lada-niva-5',
                    'PREVIEW_TEXT' => 'Готовый комплект для установки без клея.',
                    'DETAIL_TEXT' => 'Многоразовая съёмная тонировка, подготовленная под форму стекла автомобиля.',
                    'PREVIEW_PICTURE' => is_file($picturePath) ? \CFile::MakeFileArray($picturePath) : null,
                    'DETAIL_PICTURE' => is_file($picturePath) ? \CFile::MakeFileArray($picturePath) : null,
                    'PROPERTY_VALUES' => ['PRICE' => 1290, 'ARTICLE' => 'DEMO-NIVA-05', 'WEIGHT' => 700, 'LENGTH' => 80, 'WIDTH' => 15, 'HEIGHT' => 15, 'BADGE' => 'Демо'],
                ]);
            }
        }

        $messages[] = sprintf('Настройка завершена. Инфоблок товаров: %d, заказов: %d.', $productsId, $ordersId);
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php';
?>
<div style="max-width:760px;margin:40px auto;padding:30px;background:#111;color:#fff;font:15px Arial;line-height:1.5">
    <h1>Настройка orgBox: BaseShop</h1>
    <?php foreach ($messages as $message): ?><p style="color:#00dca8"><?=htmlspecialcharsbx($message)?></p><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><p style="color:#ff7474"><?=htmlspecialcharsbx($error)?></p><?php endforeach; ?>
    <p>Скрипт безопасно запускается повторно: существующие инфоблоки и свойства не дублируются. При миграции данные сохраняются, меняются только технические идентификаторы.</p>
    <form method="post" style="display:grid;gap:16px">
        <?php echo bitrix_sessid_post(); ?>
        <label>Email менеджера <input name="manager_email" type="email" required value="<?=htmlspecialcharsbx($getOption('manager_email'))?>" style="display:block;width:100%;padding:10px"></label>
        <label>Телефон сайта <input name="phone" type="text" value="<?=htmlspecialcharsbx($getOption('phone'))?>" style="display:block;width:100%;padding:10px"></label>
        <label><input name="assign_template" type="checkbox" value="1" checked> Назначить шаблон orgbox_baseshop этому сайту</label>
        <label><input name="remove_legacy" type="checkbox" value="1" checked> Удалить старые настройки и служебные сущности darkstyle после переноса</label>
        <label><input name="demo" type="checkbox" value="1" checked> Создать один демонстрационный товар</label>
        <button type="submit" style="padding:14px;background:#00f2ff;border:0;font-weight:bold">СОЗДАТЬ / ОБНОВИТЬ</button>
    </form>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
