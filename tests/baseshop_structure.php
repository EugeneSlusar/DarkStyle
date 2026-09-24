<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/public_html';
$requiredPaths = [
    '/local/templates/orgbox_baseshop/header.php',
    '/include/orgbox_baseshop/logo/before.php',
    '/include/orgbox_baseshop/logo/icon.php',
    '/include/orgbox_baseshop/logo/text.php',
    '/include/orgbox_baseshop/home/benefits.php',
    '/include/orgbox_baseshop/home/catalog-heading.php',
    '/include/orgbox_baseshop/home/portfolio.php',
    '/include/orgbox_baseshop/home/process.php',
    '/include/orgbox_baseshop/home/faq.php',
    '/include/orgbox_baseshop/home/order-cta.php',
    '/local/templates/orgbox_baseshop/footer.php',
    '/local/components/orgbox/catalog/class.php',
    '/local/components/orgbox/checkout/class.php',
    '/local/components/orgbox/home_slider/class.php',
    '/local/php_interface/lib/OrgBox/BaseShop/Config.php',
    '/local/php_interface/orgbox_baseshop/config.php',
    '/local/ajax/orgbox-baseshop-checkout.php',
    '/local/tools/orgbox_baseshop_setup.php',
    '/local/tools/orgbox_baseshop_import_catalog.php',
    '/local/css/logo.css',
    '/local/components/orgbox/home_slider/templates/.default/style.css',
    '/local/components/orgbox/home_slider/templates/.default/script.js',
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
assert(str_contains($templateHeader, "'include/orgbox_baseshop/logo/before.php'"));
assert(str_contains($templateHeader, "'NAME' => 'Текст перед логотипом'"));
assert(str_contains($templateHeader, "'include/orgbox_baseshop/logo/icon.php'"));
assert(str_contains($templateHeader, "'NAME' => 'Знак логотипа'"));
assert(str_contains($templateHeader, "'include/orgbox_baseshop/logo/text.php'"));
assert(str_contains($templateHeader, "'NAME' => 'Текст логотипа'"));
assert(str_contains($templateHeader, "'SHOW_BORDER' => true"));

$homePage = (string) file_get_contents($root . '/index.php');
assert(str_contains($homePage, "SITE_DIR . 'include/orgbox_baseshop/home/'"));
assert(str_contains($homePage, "'orgbox:home_slider'"));
assert(!str_contains($homePage, "hero.php"));

$sliderComponent = (string) file_get_contents($root . '/local/components/orgbox/home_slider/class.php');
assert(str_contains($sliderComponent, "Config::getIblockId('banners')"));
assert(str_contains($sliderComponent, 'getFallbackItems'));
assert(str_contains($sliderComponent, 'applyDemoImages'));
assert(str_contains($sliderComponent, '/local/assets/site/process-application-v2.jpg'));
assert(!str_contains($sliderComponent, "'IN_PARAMS_MENU' => true"));
assert(str_contains($sliderComponent, "'ID' => 'orgbox-baseshop-banner-add'"));
assert(str_contains($sliderComponent, "'ID' => 'orgbox-baseshop-banner-edit'"));
assert(str_contains($sliderComponent, "'ID' => 'orgbox-baseshop-banner-delete'"));

$sliderTemplate = (string) file_get_contents($root . '/local/components/orgbox/home_slider/templates/.default/template.php');
assert(str_contains($sliderTemplate, 'data-banner-edit-url'));
assert(str_contains($sliderTemplate, 'data-banner-delete-url'));
assert(str_contains($sliderTemplate, 'class="home-slider-controls"'));
assert(!str_contains($sliderTemplate, 'home-slider-controls wrap'));

$sliderScript = (string) file_get_contents($root . '/local/components/orgbox/home_slider/templates/.default/script.js');
assert(str_contains($sliderScript, "slide.classList.toggle('is-active',active)"));
assert(str_contains($sliderScript, 'position=next+1'));
assert(!str_contains($sliderScript, 'position+='));
assert(!str_contains((string) file_get_contents($root . '/local/js/site.js'), '[data-home-slider]'));

$setup = (string) file_get_contents($root . '/local/tools/orgbox_baseshop_setup.php');
assert(str_contains($setup, "'orgbox_baseshop_home_banners'"));
assert(str_contains($setup, "'Баннеры на главной'"));

$catalogData = (string) file_get_contents($root . '/local/js/catalog-data.js');
$catalogScript = (string) file_get_contents($root . '/local/js/catalog.js');
assert(str_starts_with($catalogData, 'window.orgBoxBaseShopCatalog='));
assert(str_contains($catalogScript, 'window.orgBoxBaseShopCatalog'));

$theme = (string) file_get_contents($root . '/local/css/themes/dark.css');
assert(str_contains($theme, '--theme-primary:'));
assert(str_contains($theme, '--theme-accent:'));

echo "BaseShop structure tests passed\n";
