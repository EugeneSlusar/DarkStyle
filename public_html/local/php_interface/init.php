<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

Loader::registerNamespace(
    'OrgBox\\BaseShop\\',
    __DIR__ . '/lib/OrgBox/BaseShop'
);

AddEventHandler('main', 'OnBuildGlobalMenu', [\OrgBox\BaseShop\Admin\ShopSettings::class, 'menu']);
