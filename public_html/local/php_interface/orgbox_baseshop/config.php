<?php

declare(strict_types=1);

$defaults = [
    'products_iblock_id' => 0,
    'orders_iblock_id' => 0,
    'banners_iblock_id' => 0,
    'manager_email' => '',
    'site_name' => 'Мой магазин',
    'theme' => 'dark',
    'phone' => '',
    'origin_city' => 'Тольятти',
    'cdek_client_id' => '',
    'cdek_client_secret' => '',
    'cdek_origin_code' => 0, // Код города отправления в справочнике СДЭК.
    'cdek_sandbox' => false,
    'yandex_token' => '',
    'yandex_origin_station_id' => '', // ID склада отправки в Яндекс Доставке.
    'yandex_sandbox' => false,
    'russian_post_origin_postal_code' => '445027', // Индекс отправления; можно изменить для другого отделения.
    'russian_post_token' => '', // Зарезервировано для будущего API Отправка, калькулятор не использует.
    'russian_post_key' => '',
    'delivery_fallback_enabled' => true,
    'payment_provider' => 'yookassa',
    'payment_return_url' => '',
    'yookassa_shop_id' => '',
    'yookassa_secret_key' => '',
    'yookassa_test_mode' => false,
    'robokassa_merchant_login' => '',
    'robokassa_password1' => '',
    'robokassa_password2' => '',
    'robokassa_test_mode' => false,
];

$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];

return array_replace(
    $defaults,
    is_array($local) ? $local : []
);
