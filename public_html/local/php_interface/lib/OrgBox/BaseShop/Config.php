<?php

declare(strict_types=1);

namespace OrgBox\BaseShop;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

final class Config
{
    private const MODULE_ID = 'orgbox.baseshop';

    private static ?array $values = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $environmentValue = getenv('ORGBOX_BASESHOP_' . strtoupper($key));
        if ($environmentValue !== false && $environmentValue !== '') {
            return self::cast($environmentValue, $default);
        }

        $option = Option::get(self::MODULE_ID, $key, '');
        if ($option !== '') {
            return self::cast($option, $default);
        }

        $values = self::values();

        return $values[$key] ?? $default;
    }

    public static function getIblockId(string $kind): int
    {
        $definitions = [
            'products' => ['properties' => ['PRICE', 'ARTICLE'], 'code' => 'orgbox_baseshop_products'],
            'orders' => ['properties' => ['PRODUCT_ID', 'TOTAL', 'STATUS'], 'code' => 'orgbox_baseshop_orders'],
        ];
        if (!isset($definitions[$kind])) {
            throw new \InvalidArgumentException('Неизвестный тип инфоблока.');
        }

        $optionKey = $kind . '_iblock_id';
        $configuredId = (int) self::get($optionKey, 0);
        if ($configuredId > 0 || !Loader::includeModule('iblock')) {
            return $configuredId;
        }

        $definition = $definitions[$kind];
        $row = \CIBlock::GetList([], [
            'TYPE' => 'orgbox_baseshop',
            'CODE' => $definition['code'],
        ])->Fetch();
        $iblockId = (int) ($row['ID'] ?? 0);

        if ($iblockId <= 0) {
            $filter = ['ACTIVE' => 'Y'];
            if (defined('SITE_ID')) {
                $filter['SITE_ID'] = SITE_ID;
            }
            $candidates = [];
            $iblocks = \CIBlock::GetList(['ID' => 'ASC'], $filter);
            while ($iblock = $iblocks->Fetch()) {
                $candidateId = (int) $iblock['ID'];
                $matches = true;
                foreach ($definition['properties'] as $propertyCode) {
                    if (!\CIBlockProperty::GetList([], [
                        'IBLOCK_ID' => $candidateId,
                        'CODE' => $propertyCode,
                    ])->Fetch()) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $candidates[] = $candidateId;
                }
            }
            $iblockId = count($candidates) === 1 ? $candidates[0] : 0;
        }

        if ($iblockId > 0) {
            Option::set(self::MODULE_ID, $optionKey, (string) $iblockId);
        }

        return $iblockId;
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
