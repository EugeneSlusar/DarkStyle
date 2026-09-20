<?php

declare(strict_types=1);

$defaults = [
    'products_iblock_id' => 0,
    'orders_iblock_id' => 0,
    'manager_email' => '',
    'site_name' => 'Мой магазин',
    'theme' => 'dark',
    'phone' => '',
    'origin_city' => 'Тольятти',
    'cdek_client_id' => '',
    'cdek_client_secret' => '',
    'russian_post_token' => '',
    'russian_post_key' => '',
    'delivery_fallback_enabled' => true,
];

$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? require $localFile : [];

return array_replace(
    $defaults,
    is_array($local) ? $local : []
);
