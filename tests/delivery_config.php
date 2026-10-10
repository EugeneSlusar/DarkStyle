<?php

declare(strict_types=1);

namespace Bitrix\Main\Config {
    final class Option {
        public static string $value = '';
        public static function get(string $module, string $key, string $default): string { return self::$value; }
    }
}
namespace {
    require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/Config.php';
    use OrgBox\BaseShop\Config;
    function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
    $key = 'ORGBOX_BASESHOP_RUSSIAN_POST_ORIGIN_POSTAL_CODE';
    $previous = getenv($key);
    try {
        putenv($key);
        check(Config::get('russian_post_origin_postal_code', '') === '445027', 'Default config must load from the correct directory');
        \Bitrix\Main\Config\Option::$value = '445028';
        check(Config::get('russian_post_origin_postal_code', '') === '445028', 'Module option must override config');
        putenv($key . '=445029');
        check(Config::get('russian_post_origin_postal_code', '') === '445029', 'Environment must override option');
    } finally {
        putenv($previous === false ? $key : $key . '=' . $previous);
    }
    echo "Delivery configuration tests passed\n";
}
