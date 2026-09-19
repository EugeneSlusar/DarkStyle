<?php

declare(strict_types=1);

use DarkStyle\Catalog\ProductRepository;
use DarkStyle\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

final class DarkStyleCheckoutComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        try {
            $repository = new ProductRepository((int) Config::get('products_iblock_id', 0));
            $product = $repository->getProductById((int) ($this->arParams['PRODUCT_ID'] ?? 0));
            if ($product === null) {
                \CHTTP::SetStatus('404 Not Found');
                $this->arResult = ['ERROR' => 'Товар не найден или больше не доступен.'];
                $this->includeComponentTemplate();
                return;
            }
            $this->arResult = ['PRODUCT' => $product];
            $this->includeComponentTemplate();
        } catch (Throwable) {
            \CHTTP::SetStatus('500 Internal Server Error');
            $this->arResult = ['ERROR' => 'Не удалось открыть оформление заказа. Попробуйте ещё раз позднее.'];
            $this->includeComponentTemplate();
        }
    }
}
