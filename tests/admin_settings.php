<?php

declare(strict_types=1);

namespace Bitrix\Main\Config {
    final class Option {
        public static array $values = [];
        public static function get(string $module, string $key, string $default): string { return self::$values[$key] ?? $default; }
        public static function set(string $module, string $key, string $value): void { self::$values[$key] = $value; }
    }
}
namespace {
    use OrgBox\BaseShop\Admin\ShopSettings;
    use OrgBox\BaseShop\Config;
    use Bitrix\Main\Config\Option;
    spl_autoload_register(static function (string $class): void {
        $prefix = 'OrgBox\\BaseShop\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
    function check(bool $value, string $message): void { if (!$value) { throw new RuntimeException($message); } }
    function rejects(Closure $run): void { try { $run(); } catch (InvalidArgumentException) { return; } throw new RuntimeException('Must reject invalid input'); }
    $general = ['site_name' => 'Магазин', 'phone' => '+79990000000', 'manager_email' => 'orders@example.test',
        'origin_city' => 'Тольятти', 'theme' => 'dark', 'products_iblock_id' => '0', 'orders_iblock_id' => '10', 'banners_iblock_id' => '0'];
    $delivery = ['cdek_origin_code' => '424', 'cdek_sandbox' => 'Y', 'yandex_origin_station_id' => 'station',
        'russian_post_origin_postal_code' => '445027'];
    Option::$values = ['yandex_token' => 'stored-secret', 'cdek_client_secret' => 'existing-secret'];
    ShopSettings::save($general + ['yandex_token' => 'injected'], 'general');
    check(Option::$values['yandex_token'] === 'stored-secret', 'General page must not change delivery settings');
    ShopSettings::save($delivery, 'delivery');
    check(Config::get('cdek_sandbox', false) === true && Config::get('yandex_sandbox', false) === false, 'Checkboxes must read as booleans');
    check(Option::$values['yandex_token'] === 'stored-secret', 'Empty field must preserve secret');
    ShopSettings::save($delivery + ['yandex_token' => 'replacement-secret'], 'delivery');
    check(Option::$values['yandex_token'] === 'replacement-secret', 'Replace secret');
    ShopSettings::save($delivery + ['clear_yandex_token' => 'Y'], 'delivery');
    check(Config::get('yandex_token', 'fallback-secret') === '', 'Explicit clear must override fallback');
    $before = Option::$values;
    rejects(fn() => ShopSettings::save(array_replace($delivery, ['russian_post_origin_postal_code' => '12345']), 'delivery'));
    check(Option::$values === $before, 'Invalid input must not partially save');
    rejects(fn() => ShopSettings::normalize(array_replace($general, ['manager_email' => 'bad']), 'general'));
    rejects(fn() => ShopSettings::normalize(array_replace($general, ['orders_iblock_id' => '-1']), 'general'));
    rejects(fn() => ShopSettings::normalize(array_replace($general, ['site_name' => ['bad']]), 'general'));
    $env = ShopSettings::environmentKey('russian_post_origin_postal_code');
    $previous = getenv($env);
    try {
        putenv($env . '=445028');
        ShopSettings::save(array_replace($delivery, ['russian_post_origin_postal_code' => 'forged']), 'delivery');
        check(Config::get('russian_post_origin_postal_code', '') === '445028' && Option::$values['russian_post_origin_postal_code'] === '445027', 'Locked environment setting must not be overwritten');
    } finally { putenv($previous === false ? $env : $env . '=' . $previous); }
    define('LANGUAGE_ID', 'ru');
    $USER = new class { public bool $admin = true; public function IsAdmin(): bool { return $this->admin; } };
    $APPLICATION = new class {
        public function SetAdditionalCSS(string $path): void {}
        public function SetTitle(string $title): void {}
        public function AuthForm(string $message): void { throw new RuntimeException('denied'); }
    };
    $global = ['existing' => ['sort' => 100]]; $menu = [];
    ShopSettings::menu($global, $menu);
    check(isset($global['existing']) && $global['global_menu_orgbox']['text'] === 'orgBox' && $global['global_menu_orgbox']['sort'] > 100, 'Separate root must be last and preserve existing menu');
    check(array_column($menu, 'text') === ['Настройки', 'Настройки доставки'], 'Wrong children');
    $USER->admin = false; $noGlobal = []; $noMenu = [];
    ShopSettings::menu($noGlobal, $noMenu);
    check($noGlobal === [] && $noMenu === [], 'Only administrators see menu');
    $USER->admin = true;
    function htmlspecialcharsbx(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function bitrix_sessid_post(): string { return '<input name="sessid" value="session">'; }
    function check_bitrix_sessid(): bool { return false; }
    class CAdminMessage { public static function ShowMessage(mixed $message): void { echo htmlspecialcharsbx(is_array($message) ? $message['MESSAGE'] : $message); } }
    $root = sys_get_temp_dir() . '/orgbox-admin-' . bin2hex(random_bytes(6));
    $prolog = $root . '/bitrix/modules/main/include'; mkdir($prolog, 0700, true);
    foreach (['prolog_admin_before.php', 'prolog_admin_after.php', 'epilog_admin.php'] as $file) { file_put_contents($prolog . '/' . $file, '<?php'); }
    $_SERVER['DOCUMENT_ROOT'] = $root;
    $page = dirname(__DIR__) . '/public_html/local/admin/orgbox_baseshop_settings.php';
    try {
        Option::$values['yandex_token'] = 'never-render-this-secret';
        $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['section' => 'delivery']; $_POST = [];
        ob_start(); include $page; $html = ob_get_clean();
        check(!str_contains($html, 'never-render-this-secret') && str_contains($html, 'Ключ задан'), 'Saved secrets must never appear in HTML');
        check(str_contains($html, 'name="russian_post_origin_postal_code"') && !str_contains($html, 'name="site_name"'), 'Page must contain only delivery fields');
        $before = Option::$values;
        $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $delivery;
        ob_start(); include $page; $html = ob_get_clean();
        check(Option::$values === $before && str_contains($html, 'Сессия истекла'), 'CSRF must block saving');
        $USER->admin = false; $denied = false;
        try { include $page; } catch (RuntimeException $exception) { $denied = $exception->getMessage() === 'denied'; }
        check($denied && Option::$values === $before, 'Non-admin cannot read or save settings');
    } finally {
        foreach (['prolog_admin_before.php', 'prolog_admin_after.php', 'epilog_admin.php'] as $file) { unlink($prolog . '/' . $file); }
        rmdir($prolog); rmdir(dirname($prolog)); rmdir(dirname($prolog, 2)); rmdir(dirname($prolog, 3)); rmdir($root);
    }
    echo "Admin settings tests passed\n";
}
