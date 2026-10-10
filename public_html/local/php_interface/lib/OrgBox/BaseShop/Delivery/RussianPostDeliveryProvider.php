<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

final class RussianPostDeliveryProvider implements DeliveryProviderInterface
{
    public function code(): string { return 'russian_post'; }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        return new DeliveryResult($this->code(), 'Почта России', 0, '', '', false,
            'Расчёт Почты России пока не подключён. Выберите СДЭК.');
    }
}
