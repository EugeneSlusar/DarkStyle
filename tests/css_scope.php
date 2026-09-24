<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$cssDirectory = $projectRoot . '/public_html/local/css';
$cssFiles = array_merge(
    glob($cssDirectory . '/*.css') ?: [],
    glob($cssDirectory . '/themes/*.css') ?: [],
    [
        $projectRoot . '/public_html/local/components/orgbox/home_slider/templates/.default/style.css',
        $projectRoot . '/public_html/local/components/orgbox/catalog/templates/.default/style.css',
    ]
);

assert($cssFiles !== []);

foreach ($cssFiles as $cssFile) {
    $css = (string) file_get_contents($cssFile);
    preg_match_all('/(?:^|[{}])\s*([^{}]+)\{/u', $css, $matches);

    foreach ($matches[1] as $selectorList) {
        $selectorList = trim($selectorList);
        if ($selectorList === '' || str_starts_with($selectorList, '@')) {
            continue;
        }

        foreach (explode(',', $selectorList) as $selector) {
            $selector = trim($selector);
            $isBodyMarginReset = $selector === 'body.orgbox-baseshop-page';
            assert($isBodyMarginReset || str_contains($selector, '.orgbox-baseshop'));
        }
    }
}

$header = (string) file_get_contents($projectRoot . '/public_html/local/templates/orgbox_baseshop/header.php');
$footer = (string) file_get_contents($projectRoot . '/public_html/local/templates/orgbox_baseshop/footer.php');

$panelPosition = strpos($header, '$APPLICATION->ShowPanel()');
$sitePosition = strpos($header, '<div class="orgbox-baseshop">');
assert($panelPosition !== false && $sitePosition !== false && $panelPosition < $sitePosition);
assert((bool) preg_match('~</footer>\s*</div>\s*</body>~', $footer));

echo "CSS scope tests passed\n";
