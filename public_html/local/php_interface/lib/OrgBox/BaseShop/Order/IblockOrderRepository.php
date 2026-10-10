<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Order;

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

    public function update(int $orderId, array $values): void
    {
        if ($orderId <= 0) {
            throw new RuntimeException('Некорректный номер заказа.');
        }
        $properties = [];
        foreach ($values as $code => $value) {
            $properties[strtoupper((string) $code)] = (string) $value;
        }
        \CIBlockElement::SetPropertyValuesEx($orderId, $this->iblockId, $properties);
    }

    public function find(int $orderId): ?array
    {
        $row = \CIBlockElement::GetList([], ['ID' => $orderId, 'IBLOCK_ID' => $this->iblockId], false, false, ['ID', 'NAME'])->Fetch();
        if (!$row) {
            return null;
        }
        $result = ['order_id' => (int) $row['ID']];
        $properties = \CIBlockElement::GetProperty($this->iblockId, $orderId);
        while ($property = $properties->Fetch()) {
            $result[strtolower((string) $property['CODE'])] = (string) $property['VALUE'];
        }
        return $result;
    }
}
