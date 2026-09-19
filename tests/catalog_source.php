<?php

declare(strict_types=1);

require dirname(__DIR__) . '/public_html/local/php_interface/lib/DarkStyle/Catalog/CatalogImportService.php';

use DarkStyle\Catalog\CatalogImportService;

$root = dirname(__DIR__) . '/public_html';
$content = (string) file_get_contents($root . '/catalog-data.js');
$json = rtrim(trim(substr($content, (int) strpos($content, '=') + 1)), "; \t\n\r\0\x0B");
$products = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
$sections = (new ReflectionClass(CatalogImportService::class))->getReflectionConstant('SECTIONS')->getValue();

assert(count($products) === 38);
assert(stripos($content, 'ozon') === false);

foreach ($products as $product) {
    assert(!isset($product['u']));
    assert(preg_match('/^\d+$/', (string) $product['s']) === 1);
    assert(array_filter($sections, static fn (array $section): bool => preg_match($section['pattern'], (string) $product['t']) === 1) !== []);

    $directory = $root . '/assets/catalog-products/' . $product['s'];
    assert(is_dir($directory));
    for ($index = 1; $index <= (int) $product['n']; $index++) {
        $base = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        assert((glob($directory . '/' . $base . '.*') ?: []) !== []);
    }
}

echo "Catalog source tests passed\n";
