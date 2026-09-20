<?php

declare(strict_types=1);

namespace OrgBox\BaseShop;

use Bitrix\Main\Config\Option;

final class Config
{
    private const MODULE_ID = 'orgbox.baseshop';
    private const LEGACY_MODULE_ID = 'darkstyle.core';

    private static ?array $values = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $environmentValue = getenv('ORGBOX_BASESHOP_' . strtoupper($key));
        if ($environmentValue === false || $environmentValue === '') {
            $environmentValue = getenv('DARKSTYLE_' . strtoupper($key));
        }
        if ($environmentValue !== false && $environmentValue !== '') {
            return self::cast($environmentValue, $default);
        }

        $option = Option::get(self::MODULE_ID, $key, '');
        if ($option === '') {
            $option = Option::get(self::LEGACY_MODULE_ID, $key, '');
        }
        if ($option !== '') {
            return self::cast($option, $default);
        }

        $values = self::values();

        return $values[$key] ?? $default;
    }

    private static function values(): array
    {
        if (self::$values === null) {
            $file = dirname(__DIR__, 2) . '/orgbox_baseshop/config.php';
            self::$values = is_file($file) ? (array) require $file : [];
        }

        return self::$values;
    }

    private static function cast(string $value, mixed $default): mixed
    {
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }
        if (is_int($default)) {
            return (int) $value;
        }
        if (is_float($default)) {
            return (float) $value;
        }

        return $value;
    }
}
