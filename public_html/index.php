<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('Съёмная тонировка для авто');

$include = static function (string $file, string $name) use ($APPLICATION): void {
    $APPLICATION->IncludeFile(
        SITE_DIR . 'include/orgbox_baseshop/home/' . $file,
        [],
        ['MODE' => 'html', 'NAME' => $name, 'SHOW_BORDER' => true]
    );
};

$APPLICATION->IncludeComponent('orgbox:home_slider', '', ['AUTOPLAY_DELAY' => 5000, 'CACHE_TIME' => 3600]);
$include('benefits.php', 'Преимущества');
?>
<section class="section catalog" id="katalog">
    <div class="wrap">
        <?php $include('catalog-heading.php', 'Заголовок каталога'); ?>
        <?php $APPLICATION->IncludeComponent('orgbox:catalog', '', ['MODE' => 'home', 'LIMIT' => 8, 'CACHE_TIME' => 3600]); ?>
        <div class="home-catalog-footer"><a class="button" href="/catalog/">Открыть весь каталог →</a></div>
    </div>
</section>
<?php
$include('portfolio.php', 'Портфолио');
$include('process.php', 'Применение');
$include('faq.php', 'Вопросы и ответы');
$include('order-cta.php', 'Призыв к заказу');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
