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
                return;
            }
            $this->arResult = ['PRODUCT' => $product];
            $this->includeComponentTemplate();
        } catch (Throwable) {
            // The catalog remains usable if checkout is not configured yet.
        }
    }
}
