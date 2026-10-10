<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Admin;

use Bitrix\Main\Config\Option;
use InvalidArgumentException;

final class ShopSettings
{
    public static function fields(string $section = 'all'): array
    {
        $groups = [
            'Общие настройки' => [
                'site_name' => ['Название магазина', 'text', 'Мой магазин'],
                'phone' => ['Телефон', 'text', ''],
                'manager_email' => ['Email для заказов', 'email', ''],
                'origin_city' => ['Город отправления', 'text', 'Тольятти'],
                'theme' => ['Тема оформления', 'theme', 'dark'],
            ],
            'СДЭК' => [
                'cdek_client_id' => ['Идентификатор API (client_id)', 'secret', ''],
                'cdek_client_secret' => ['Пароль API (client_secret)', 'secret', ''],
                'cdek_origin_code' => ['Код города отправления СДЭК', 'number', 0],
                'cdek_sandbox' => ['Тестовый режим', 'bool', false],
            ],
            'Яндекс Доставка' => [
                'yandex_token' => ['Токен API', 'secret', ''],
                'yandex_origin_station_id' => ['ID станции отгрузки', 'text', ''],
                'yandex_sandbox' => ['Тестовый режим', 'bool', false],
            ],
            'Почта России' => [
                'russian_post_origin_postal_code' => ['Индекс отделения отправления', 'postcode', '445027'],
            ],
            'Инфоблоки' => [
                'products_iblock_id' => ['Инфоблок товаров (0 — автоопределение)', 'number', 0],
                'orders_iblock_id' => ['Инфоблок заказов (0 — автоопределение)', 'number', 0],
                'banners_iblock_id' => ['Инфоблок баннеров (0 — автоопределение)', 'number', 0],
            ],
        ];
        if ($section === 'all') { return $groups; }
        if (!in_array($section, ['general', 'delivery'], true)) { throw new InvalidArgumentException('Неизвестный раздел настроек.'); }
        return array_filter($groups, static fn(string $group): bool => $section === 'delivery'
            ? in_array($group, ['СДЭК', 'Яндекс Доставка', 'Почта России'], true)
            : in_array($group, ['Общие настройки', 'Инфоблоки'], true), ARRAY_FILTER_USE_KEY);
    }

    public static function environmentKey(string $key): string
    {
        return 'ORGBOX_BASESHOP_' . strtoupper($key);
    }

    public static function locked(string $key): bool
    {
        $value = getenv(self::environmentKey($key));
        return $value !== false && $value !== '';
    }

    public static function normalize(array $input, string $section = 'all'): array
    {
        $values = [];
        foreach (self::fields($section) as $fields) {
            foreach ($fields as $key => [$label, $type]) {
                if (self::locked($key)) { continue; }
                $raw = $input[$key] ?? '';
                if (!is_scalar($raw)) { throw new InvalidArgumentException('Некорректное значение: ' . $label); }
                $value = trim((string) $raw);
                if (strlen($value) > 4096) { throw new InvalidArgumentException('Слишком длинное значение: ' . $label); }
                if ($type === 'secret') {
                    if (($input['clear_' . $key] ?? '') === 'Y') { $values[$key] = ''; }
                    elseif ($value !== '') { $values[$key] = $value; }
                    continue; // Empty password input preserves the existing value.
                }
                if ($type === 'bool') { $value = $value === 'Y' ? '1' : '0'; }
                if ($type === 'number' && (!preg_match('/^[0-9]{1,9}$/D', $value))) {
                    throw new InvalidArgumentException('Введите целое неотрицательное число: ' . $label);
                }
                if ($type === 'postcode' && !preg_match('/^[1-9][0-9]{5}$/D', $value)) {
                    throw new InvalidArgumentException('Индекс отправления должен содержать шесть цифр.');
                }
                if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Укажите корректный email для заказов.');
                }
                if ($type === 'theme' && $value !== 'dark') { throw new InvalidArgumentException('Неизвестная тема оформления.'); }
                if (in_array($key, ['site_name', 'origin_city'], true) && $value === '') {
                    throw new InvalidArgumentException('Заполните поле: ' . $label);
                }
                $values[$key] = $value;
            }
        }
        return $values;
    }

    public static function save(array $input, string $section = 'all'): void
    {
        $values = self::normalize($input, $section); // Validate everything before changing options.
        foreach ($values as $key => $value) { Option::set('orgbox.baseshop', $key, $value); }
    }

    public static function menu(array &$globalMenu, array &$moduleMenu): void
    {
        global $USER;
        if (!is_object($USER) || !$USER->IsAdmin()) { return; }
        global $APPLICATION;
        $APPLICATION->SetAdditionalCSS('/local/admin/orgbox.css');
        $globalMenu['global_menu_orgbox'] = [
            'menu_id' => 'orgbox', 'sort' => 1000000, 'text' => 'orgBox',
            'title' => 'Настройки магазина orgBox',
            'url' => '/local/admin/orgbox_baseshop_settings.php?section=general&lang=' . LANGUAGE_ID,
            'items_id' => 'global_menu_orgbox', 'items' => [],
        ];
        foreach (['general' => 'Настройки', 'delivery' => 'Настройки доставки'] as $section => $label) {
            $moduleMenu[] = [
                'parent_menu' => 'global_menu_orgbox', 'sort' => $section === 'general' ? 10 : 20,
                'text' => $label, 'title' => $label,
                'url' => '/local/admin/orgbox_baseshop_settings.php?section=' . $section . '&lang=' . LANGUAGE_ID,
                'icon' => 'settings_menu_icon', 'page_icon' => 'settings_page_icon',
                'items_id' => 'orgbox_baseshop_' . $section, 'items' => [],
            ];
        }
    }
}
