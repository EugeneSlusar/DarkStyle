<?php

declare(strict_types=1);

namespace DarkStyle\Order;

use Bitrix\Main\Loader;
use RuntimeException;

final class IblockOrderRepository
{
    public function __construct(private readonly int $iblockId)
    {
        if ($iblockId <= 0 || !Loader::includeModule('iblock')) {
            throw new RuntimeException('Не настроен инфоблок заказов.');
        }
    }

    public function save(array $order): int
    {
        $element = new \CIBlockElement();
        $properties = [];
        foreach ($order as $code => $value) {
            $properties[strtoupper($code)] = $value;
        }

        $orderId = $element->Add([
            'IBLOCK_ID' => $this->iblockId,
            'ACTIVE' => 'Y',
            'NAME' => sprintf('Заказ %s — %s', date('d.m.Y H:i'), $order['phone']),
            'PROPERTY_VALUES' => $properties,
        ]);

        if (!$orderId) {
            throw new RuntimeException('Не удалось сохранить заказ.');
        }

        return (int) $orderId;
    }
}
