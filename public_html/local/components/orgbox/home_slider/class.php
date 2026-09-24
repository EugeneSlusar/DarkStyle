<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use OrgBox\BaseShop\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class OrgBoxBaseShopHomeSliderComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('iblock')) {
            $this->arResult = ['ITEMS' => [], 'ERROR' => 'Модуль iblock недоступен.'];
            $this->includeComponentTemplate();
            return;
        }

        $iblockId = (int) ($this->arParams['IBLOCK_ID'] ?: Config::getIblockId('banners'));
        $items = $this->getItems($iblockId);
        $iblockType = $iblockId > 0 ? (string) CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID') : '';
        $this->arResult = [
            'ITEMS' => $items !== [] ? $items : $this->getFallbackItems(),
            'IBLOCK_ID' => $iblockId,
            'ADD_LINK' => $iblockId > 0 ? $this->elementEditUrl($iblockId, $iblockType) : '',
            'AUTOPLAY_DELAY' => max(0, (int) ($this->arParams['AUTOPLAY_DELAY'] ?? 5000)),
        ];
        $this->addBannerAdminMenu((string) $this->arResult['ADD_LINK']);
        $this->includeComponentTemplate();
    }

    private function getItems(int $iblockId): array
    {
        if ($iblockId <= 0) {
            return [];
        }

        $items = [];
        $query = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'SORT']
        );

        $iblockType = (string) CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID');
        while ($element = $query->GetNextElement()) {
            $fields = $element->GetFields();
            $properties = $element->GetProperties();
            $id = (int) $fields['ID'];
            $items[] = [
                'ID' => $id,
                'NAME' => (string) $fields['NAME'],
                'DESCRIPTION' => (string) $fields['PREVIEW_TEXT'],
                'IMAGE' => $fields['PREVIEW_PICTURE'] ? (string) CFile::GetPath((int) $fields['PREVIEW_PICTURE']) : '/local/assets/site/process-application-v2.jpg',
                'SUBTITLE' => (string) ($properties['SUBTITLE']['VALUE'] ?? ''),
                'BUTTON_TEXT' => (string) ($properties['BUTTON_TEXT']['VALUE'] ?? ''),
                'BUTTON_LINK' => (string) ($properties['BUTTON_LINK']['VALUE'] ?? ''),
                'SECOND_BUTTON_TEXT' => (string) ($properties['SECOND_BUTTON_TEXT']['VALUE'] ?? ''),
                'SECOND_BUTTON_LINK' => (string) ($properties['SECOND_BUTTON_LINK']['VALUE'] ?? ''),
                'EDIT_LINK' => $this->elementEditUrl($iblockId, $iblockType, $id),
                'DELETE_LINK' => $this->elementDeleteUrl($iblockId, $iblockType, $id),
            ];
        }

        $this->applyDemoImages($items);

        return $items;
    }

    private function applyDemoImages(array &$items): void
    {
        if (count($items) !== 3) {
            return;
        }

        $images = array_values(array_unique(array_column($items, 'IMAGE')));
        if (count($images) !== 1) {
            return;
        }

        $demoImages = [
            'https://cdn-ru.bitrix24.ru/b21728636/ai/6cc/6cc0a82d4221ab6c976fc2fc3d588dbe/RP9k8c2e6BeSuz0oM9Nw5HwDTnQVZQk4.jpg',
            '/local/assets/site/process-application-v2.jpg',
            '/local/assets/site/window-tint-night-woman-wide.png',
        ];
        foreach ($items as $index => &$item) {
            $item['IMAGE'] = $demoImages[$index];
        }
        unset($item);
    }

    private function getFallbackItems(): array
    {
        return [[
            'ID' => 0,
            'NAME' => "Съёмная тонировка:\nТвой стиль без границ",
            'DESCRIPTION' => 'Запустите настройку BaseShop, чтобы создать управляемые баннеры главной страницы.',
            'IMAGE' => 'https://cdn-ru.bitrix24.ru/b21728636/ai/6cc/6cc0a82d4221ab6c976fc2fc3d588dbe/RP9k8c2e6BeSuz0oM9Nw5HwDTnQVZQk4.jpg',
            'SUBTITLE' => 'Новый уровень приватности',
            'BUTTON_TEXT' => 'Смотреть каталог',
            'BUTTON_LINK' => '/catalog/',
            'SECOND_BUTTON_TEXT' => 'Как это работает',
            'SECOND_BUTTON_LINK' => '#primenenie',
            'EDIT_LINK' => '',
            'DELETE_LINK' => '',
        ]];
    }

    private function addBannerAdminMenu(string $addLink): void
    {
        global $APPLICATION, $USER;

        if (
            $addLink === ''
            || !is_object($USER)
            || !$USER->IsAdmin()
            || !$APPLICATION->GetShowIncludeAreas()
        ) {
            return;
        }

        $this->AddIncludeAreaIcons([
            [
                'ID' => 'orgbox-baseshop-banner-add',
                'TITLE' => 'Добавить баннер',
                'URL' => "javascript:window.open('" . CUtil::JSEscape($addLink) . "', '_blank', 'noopener');void(0);",
                'ICON' => 'menu-add',
                'IN_PARAMS_MENU' => true,
            ],
            [
                'ID' => 'orgbox-baseshop-banner-edit',
                'TITLE' => 'Изменить активный баннер',
                'URL' => $this->activeBannerScript('edit'),
                'ICON' => 'menu-edit',
                'IN_PARAMS_MENU' => true,
            ],
            [
                'ID' => 'orgbox-baseshop-banner-delete',
                'TITLE' => 'Удалить активный баннер',
                'URL' => $this->activeBannerScript('delete'),
                'ICON' => 'menu-delete',
                'IN_PARAMS_MENU' => true,
            ],
        ]);
    }

    private function activeBannerScript(string $action): string
    {
        $attribute = $action === 'delete' ? 'data-banner-delete-url' : 'data-banner-edit-url';
        $confirmation = $action === 'delete'
            ? "if(!window.confirm('Удалить активный баннер?'))return;"
            : '';
        $navigation = $action === 'delete'
            ? 'window.location.href=url;'
            : "window.open(url, '_blank', 'noopener');";

        return "javascript:(function(){var slide=document.querySelector('[data-home-slider-slide].is-active');"
            . "var url=slide?slide.getAttribute('" . $attribute . "'):'';if(!url)return;"
            . $confirmation . $navigation . '})();void(0);';
    }

    private function elementEditUrl(int $iblockId, string $iblockType, int $elementId = 0): string
    {
        $query = [
            'IBLOCK_ID' => $iblockId,
            'type' => $iblockType,
            'lang' => defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru',
            'find_section_section' => 0,
        ];
        if ($elementId > 0) {
            $query['ID'] = $elementId;
        }

        return '/bitrix/admin/iblock_element_edit.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function elementDeleteUrl(int $iblockId, string $iblockType, int $elementId): string
    {
        return '/bitrix/admin/iblock_element_admin.php?' . http_build_query([
            'IBLOCK_ID' => $iblockId,
            'type' => $iblockType,
            'lang' => defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru',
            'action' => 'delete',
            'ID' => $elementId,
            'sessid' => bitrix_sessid(),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
