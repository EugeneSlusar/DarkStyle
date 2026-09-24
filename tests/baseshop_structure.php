<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/public_html';
$requiredPaths = [
    '/local/templates/orgbox_baseshop/header.php',
    '/include/site-logo.php',
    '/local/templates/orgbox_baseshop/footer.php',
    '/local/components/orgbox/catalog/class.php',
    '/local/components/orgbox/checkout/class.php',
    '/local/php_interface/lib/OrgBox/BaseShop/Config.php',
    '/local/php_interface/orgbox_baseshop/config.php',
    '/local/ajax/orgbox-baseshop-checkout.php',
    '/local/tools/orgbox_baseshop_setup.php',
    '/local/tools/orgbox_baseshop_import_catalog.php',
    '/local/css/themes/dark.css',
];

foreach ($requiredPaths as $path) {
    assert(is_file($root . $path), 'Missing BaseShop file: ' . $path);
}

$legacyAdapters = [
    '/local/templates/darkstyle/header.php',
    '/local/templates/darkstyle/footer.php',
    '/local/components/darkstyle/catalog/class.php',
    '/local/components/darkstyle/checkout/class.php',
    '/local/ajax/darkstyle-checkout.php',
    '/local/tools/darkstyle_setup.php',
    '/local/tools/darkstyle_import_catalog.php',
];

foreach ($legacyAdapters as $path) {
    assert(!file_exists($root . $path), 'Legacy adapter still exists: ' . $path);
}

$runtimeFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/local'));
foreach ($runtimeFiles as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
        continue;
    }
    $content = (string) file_get_contents($file->getPathname());
    assert(stripos($content, 'darkstyle') === false, 'Legacy identifier found in ' . $file->getPathname());
    assert(!str_contains($content, 'Orgbox'), 'Incorrect PHP brand casing in ' . $file->getPathname());
    assert(!str_contains($content, 'orgboxBaseShop'), 'Incorrect JavaScript brand casing in ' . $file->getPathname());
}

$entryPoints = [
    '/index.php' => 'orgbox:catalog',
    '/catalog/index.php' => 'orgbox:catalog',
    '/order/index.php' => 'orgbox:checkout',
];

foreach ($entryPoints as $path => $component) {
    $content = (string) file_get_contents($root . $path);
    assert(str_contains($content, $component), 'Unexpected component in ' . $path);
}

$templateDescription = (string) file_get_contents($root . '/local/templates/orgbox_baseshop/description.php');
assert(str_contains($templateDescription, 'orgBox: BaseShop'));

$templateHeader = (string) file_get_contents($root . '/local/templates/orgbox_baseshop/header.php');
assert(str_contains($templateHeader, "'/include/site-logo.php'"));
assert(str_contains($templateHeader, "'NAME' => 'Текст логотипа'"));
assert(str_contains($templateHeader, "'SHOW_BORDER' => true"));

$catalogData = (string) file_get_contents($root . '/local/js/catalog-data.js');
$catalogScript = (string) file_get_contents($root . '/local/js/catalog.js');
assert(str_starts_with($catalogData, 'window.orgBoxBaseShopCatalog='));
assert(str_contains($catalogScript, 'window.orgBoxBaseShopCatalog'));

$theme = (string) file_get_contents($root . '/local/css/themes/dark.css');
assert(str_contains($theme, '--theme-primary:'));
assert(str_contains($theme, '--theme-accent:'));

echo "BaseShop structure tests passed\n";
