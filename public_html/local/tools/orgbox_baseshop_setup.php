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
$getOption = static function (string $key): string {
    return Option::get('orgbox.baseshop', $key, '');
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

        $bannersId = $findIblock($typeId, 'orgbox_baseshop_home_banners');
        if ($bannersId <= 0) {
            $iblock = new CIBlock();
            $bannersId = (int) $iblock->Add([
                'ACTIVE' => 'Y',
                'NAME' => 'Баннеры на главной',
                'CODE' => 'orgbox_baseshop_home_banners',
                'IBLOCK_TYPE_ID' => $typeId,
                'LID' => [SITE_ID],
                'SORT' => 150,
                'VERSION' => 2,
                'GROUP_ID' => ['1' => 'X', '2' => 'R'],
            ]);
            if ($bannersId <= 0) {
                throw new RuntimeException('Не удалось создать инфоблок баннеров.');
            }
        }
        $bannersIblock = new CIBlock();
        if (!$bannersIblock->Update($bannersId, [
            'IBLOCK_TYPE_ID' => $typeId,
            'CODE' => 'orgbox_baseshop_home_banners',
        ])) {
            throw new RuntimeException('Не удалось обновить идентификаторы инфоблока баннеров: ' . $bannersIblock->LAST_ERROR);
        }
        $bannerProperties = [
            ['SUBTITLE', 'Надзаголовок', 'S', false],
            ['BUTTON_TEXT', 'Текст основной кнопки', 'S', false],
            ['BUTTON_LINK', 'Ссылка основной кнопки', 'S', false],
            ['SECOND_BUTTON_TEXT', 'Текст дополнительной ссылки', 'S', false],
            ['SECOND_BUTTON_LINK', 'Ссылка дополнительной ссылки', 'S', false],
        ];
        foreach ($bannerProperties as [$code, $name, $type, $multiple]) {
            $ensureProperty($bannersId, $code, $name, $type, $multiple);
        }

        if (!CIBlockElement::GetList([], ['IBLOCK_ID' => $bannersId])->Fetch()) {
            $picturePath = $_SERVER['DOCUMENT_ROOT'] . '/local/assets/site/process-application-v2.jpg';
            $bannerDefinitions = [
                ['Новый уровень приватности', "Съёмная тонировка\nТвой стиль без границ", 'Измени облик авто за считанные минуты и верни заводской вид в любой момент.', 100],
                ['Комфорт в каждой поездке', "Тонировка\nбез лишних усилий", 'Готовый комплект для автомобиля — установка занимает считанные минуты.', 200],
                ['Стиль, который можно менять', "Съёмная плёнка\nдля вашего авто", 'Выберите светопропускаемость и оформите заказ с доставкой.', 300],
            ];
            foreach ($bannerDefinitions as [$subtitle, $name, $description, $sort]) {
                $element = new CIBlockElement();
                if (!$element->Add([
                    'IBLOCK_ID' => $bannersId,
                    'ACTIVE' => 'Y',
                    'NAME' => $name,
                    'PREVIEW_TEXT' => $description,
                    'PREVIEW_TEXT_TYPE' => 'text',
                    'PREVIEW_PICTURE' => is_file($picturePath) ? CFile::MakeFileArray($picturePath) : null,
                    'SORT' => $sort,
                    'PROPERTY_VALUES' => [
                        'SUBTITLE' => $subtitle,
                        'BUTTON_TEXT' => 'Смотреть каталог',
                        'BUTTON_LINK' => '/catalog/',
                        'SECOND_BUTTON_TEXT' => 'Как это работает',
                        'SECOND_BUTTON_LINK' => '#primenenie',
                    ],
                ])) {
                    throw new RuntimeException('Не удалось создать демонстрационный баннер: ' . $element->LAST_ERROR);
                }
            }
        }

        $ordersId = $findIblock($typeId, 'orgbox_baseshop_orders');
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
        Option::set('orgbox.baseshop', 'banners_iblock_id', (string) $bannersId);
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
            $site = new \CSite();
            if (!$site->Update(SITE_ID, [
                'TEMPLATE' => [['CONDITION' => '', 'SORT' => 1, 'TEMPLATE' => 'orgbox_baseshop']],
            ])) {
                $messages[] = 'Назначьте шаблон orgbox_baseshop сайту вручную в административной части.';
            }
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

        $messages[] = sprintf('Настройка завершена. Инфоблок товаров: %d, баннеров: %d, заказов: %d.', $productsId, $bannersId, $ordersId);
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
    <p>Скрипт безопасно запускается повторно: существующие инфоблоки и свойства не дублируются.</p>
    <form method="post" style="display:grid;gap:16px">
        <?php echo bitrix_sessid_post(); ?>
        <label>Email менеджера <input name="manager_email" type="email" required value="<?=htmlspecialcharsbx($getOption('manager_email'))?>" style="display:block;width:100%;padding:10px"></label>
        <label>Телефон сайта <input name="phone" type="text" value="<?=htmlspecialcharsbx($getOption('phone'))?>" style="display:block;width:100%;padding:10px"></label>
        <label><input name="assign_template" type="checkbox" value="1" checked> Назначить шаблон orgbox_baseshop этому сайту</label>
        <label><input name="demo" type="checkbox" value="1" checked> Создать один демонстрационный товар</label>
        <button type="submit" style="padding:14px;background:#00f2ff;border:0;font-weight:bold">СОЗДАТЬ / ОБНОВИТЬ</button>
    </form>
</div>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
