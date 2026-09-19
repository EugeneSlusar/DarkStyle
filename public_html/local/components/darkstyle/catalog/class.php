<?php

declare(strict_types=1);

use Bitrix\Main\Context;
use DarkStyle\Catalog\ProductRepository;
use DarkStyle\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

final class DarkStyleCatalogComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        global $APPLICATION;

        try {
            $repository = new ProductRepository((int) ($this->arParams['IBLOCK_ID'] ?: Config::get('products_iblock_id', 0)));
            $mode = (string) ($this->arParams['MODE'] ?? 'catalog');

            if ($mode === 'home') {
                $this->arResult = ['ITEMS' => $repository->getProducts(null, max(1, (int) ($this->arParams['LIMIT'] ?? 8)))];
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
                $APPLICATION->SetTitle($item['NAME']);
                $this->arResult = ['ITEM' => $item];
                $this->includeComponentTemplate('detail');
                return;
            }

            $sectionCode = $segments[0] ?? null;
            $items = $repository->getProducts($sectionCode);
            $sections = $repository->getSections();
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

            $APPLICATION->SetTitle($currentSection['NAME'] ?? 'Каталог');
            $this->arResult = ['ITEMS' => $items, 'SECTIONS' => $sections, 'SECTION' => $currentSection];
            $this->includeComponentTemplate('list');
        } catch (Throwable $exception) {
            $this->arResult = ['ERROR' => $exception->getMessage()];
            $this->includeComponentTemplate('error');
        }
    }

    private function notFound(): void
    {
        \CHTTP::SetStatus('404 Not Found');
        @define('ERROR_404', 'Y');
        $this->arResult = ['ERROR' => 'Страница не найдена.'];
        $this->includeComponentTemplate('error');
    }
}
