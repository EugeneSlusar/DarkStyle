<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

Loader::registerNamespace(
    'Orgbox\\BaseShop\\',
    __DIR__ . '/lib/Orgbox/BaseShop'
);

spl_autoload_register(static function (string $legacyClass): void {
    $legacyPrefix = 'DarkStyle\\';
    if (!str_starts_with($legacyClass, $legacyPrefix)) {
        return;
    }

    $currentClass = 'Orgbox\\BaseShop\\' . substr($legacyClass, strlen($legacyPrefix));
    if (class_exists($currentClass) || interface_exists($currentClass) || trait_exists($currentClass)) {
        class_alias($currentClass, $legacyClass);
    }
});
