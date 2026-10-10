<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

final class OtherDeliveryProvider implements DeliveryProviderInterface
{
    public function code(): string { return 'other'; }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        return new DeliveryResult($this->code(), 'Другая служба доставки', 0, '', '', false,
            'Служба доставки пока не подключена. Выберите СДЭК.');
    }
}
