<?php

declare(strict_types=1);

use Bitrix\Main\Context;
use OrgBox\BaseShop\Catalog\ProductRepository;
use OrgBox\BaseShop\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class OrgBoxBaseShopCatalogComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        global $APPLICATION;

        try {
            $requestedIblockId = (int) ($this->arParams['IBLOCK_ID'] ?? 0);
            $requestedIblock = $requestedIblockId > 0
                ? \CIBlock::GetList([], [
                    'ID' => $requestedIblockId,
                    'TYPE' => 'orgbox_baseshop',
                    'CODE' => 'orgbox_baseshop_products',
                ])->Fetch()
                : false;
            $iblockId = $requestedIblock ? $requestedIblockId : Config::getIblockId('products');
            $repository = new ProductRepository($iblockId);
            $this->addProductsAdminButton($iblockId);
            $mode = (string) ($this->arParams['MODE'] ?? 'catalog');

            if ($mode === 'home') {
                $limit = max(1, (int) ($this->arParams['LIMIT'] ?? 8));
                $this->arResult = ['SECTIONS' => array_slice($repository->getSections(), 0, $limit)];
                $this->includeComponentTemplate('home');
                return;
            }

            $requestPath = (string) parse_url(Context::getCurrent()->getRequest()->getRequestUri(), PHP_URL_PATH);
            $relativePath = preg_replace('#^catalog/?#', '', trim($requestPath, '/')) ?? '';
            if ($relativePath === 'index.php') {
                $relativePath = '';
            }
            $segments = array_map('rawurldecode', array_values(array_filter(explode('/', $relativePath))));

            if (count($segments) > 2) {
                $this->notFound();
                return;
            }

            if (count($segments) >= 2) {
                $item = $repository->getProduct((string) $segments[0], (string) $segments[1]);
                if ($item === null) {
                    $this->notFound();
                    return;
                }
                $seoH1 = $this->applyElementSeo($iblockId, (int) $item['ID'], (string) $item['NAME']);
                $this->arResult = ['ITEM' => $item, 'SEO_H1' => $seoH1];
                $this->includeComponentTemplate('detail');
                return;
            }

            $sectionCode = $segments[0] ?? null;
            $sections = $repository->getSections();
            if ($sectionCode === null) {
                $catalogTitle = 'Каталог съёмной тонировки — Тёмный стиль';
                $APPLICATION->SetTitle($catalogTitle);
                $APPLICATION->SetPageProperty('title', $catalogTitle);
                $this->arResult = ['SECTIONS' => $sections];
                $this->includeComponentTemplate('sections');
                return;
            }

            $currentSection = null;
            foreach ($sections as $section) {
                if ($section['CODE'] === $sectionCode) {
                    $currentSection = $section;
                    break;
                }
            }
            if ($sectionCode !== null && $currentSection === null) {
                $this->notFound();
                return;
            }

            $items = $repository->getProducts($sectionCode);
            $sectionName = (string) ($currentSection['NAME'] ?? 'Каталог');
            $seoH1 = $this->applySectionSeo($iblockId, (int) $currentSection['ID'], $sectionName);
            $this->arResult = ['ITEMS' => $items, 'SECTIONS' => $sections, 'SECTION' => $currentSection, 'SEO_H1' => $seoH1];
            \Bitrix\Main\Page\Asset::getInstance()->addJs('/local/components/orgbox/catalog/templates/.default/script.js');
            $this->includeComponentTemplate('list');
        } catch (Throwable $exception) {
            $this->arResult = ['ERROR' => $exception->getMessage()];
            $this->includeComponentTemplate('error');
        }
    }

    private function addProductsAdminButton(int $iblockId): void
    {
        global $USER;

        if (!is_object($USER) || !$USER->IsAdmin()) {
            return;
        }

        $iblockType = (string) \CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID');
        if ($iblockType === '') {
            return;
        }

        $adminUrl = '/bitrix/admin/iblock_element_admin.php?' . http_build_query([
            'IBLOCK_ID' => $iblockId,
            'type' => $iblockType,
            'lang' => defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru',
            'find_section_section' => 0,
        ], '', '&', PHP_QUERY_RFC3986);

        $this->AddIncludeAreaIcon([
            'URL' => "javascript:window.open('" . \CUtil::JSEscape($adminUrl) . "', '_blank', 'noopener');void(0);",
            'ICON' => 'bx-context-toolbar-edit-icon',
            'TITLE' => 'Редактировать товары',
        ]);
    }

    private function notFound(): void
    {
        \CHTTP::SetStatus('404 Not Found');
        @define('ERROR_404', 'Y');
        $this->arResult = ['ERROR' => 'Страница не найдена.'];
        $this->includeComponentTemplate('error');
    }

    private function applySectionSeo(int $iblockId, int $sectionId, string $fallbackH1): string
    {
        return $this->applyInheritedSeo(
            new \Bitrix\Iblock\InheritedProperty\SectionValues($iblockId, $sectionId),
            'SECTION',
            $fallbackH1
        );
    }

    private function applyElementSeo(int $iblockId, int $elementId, string $fallbackH1): string
    {
        return $this->applyInheritedSeo(
            new \Bitrix\Iblock\InheritedProperty\ElementValues($iblockId, $elementId),
            'ELEMENT',
            $fallbackH1
        );
    }

    private function applyInheritedSeo(object $valuesProvider, string $prefix, string $fallbackH1): string
    {
        global $APPLICATION;

        try {
            $values = $valuesProvider->getValues();
            $metaTitle = trim((string) ($values[$prefix . '_META_TITLE'] ?? ''));
            $metaDescription = trim((string) ($values[$prefix . '_META_DESCRIPTION'] ?? ''));
            $metaKeywords = trim((string) ($values[$prefix . '_META_KEYWORDS'] ?? ''));
            $pageTitle = trim((string) ($values[$prefix . '_PAGE_TITLE'] ?? ''));

            $APPLICATION->SetTitle($metaTitle !== '' ? $metaTitle : $fallbackH1);
            $APPLICATION->SetPageProperty('title', $metaTitle !== '' ? $metaTitle : $fallbackH1);
            if ($metaDescription !== '') {
                $APPLICATION->SetPageProperty('description', $metaDescription);
            }
            if ($metaKeywords !== '') {
                $APPLICATION->SetPageProperty('keywords', $metaKeywords);
            }

            return $pageTitle !== '' ? $pageTitle : $fallbackH1;
        } catch (\Throwable) {
            $APPLICATION->SetTitle($fallbackH1);
            $APPLICATION->SetPageProperty('title', $fallbackH1);
            return $fallbackH1;
        }
    }
}
