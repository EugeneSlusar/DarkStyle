<?php

declare(strict_types=1);

namespace Orgbox\BaseShop\Delivery;

final class CdekDeliveryProvider implements DeliveryProviderInterface
{
    public function code(): string
    {
        return 'cdek';
    }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        $chargeableWeight = max($request->weight / 1000, $request->volumeWeight());
        $price = ceil(max(390, 290 + $chargeableWeight * 85) / 10) * 10;

        return new DeliveryResult(
            $this->code(),
            'СДЭК: пункт выдачи',
            $price,
            '3–7 дней',
            'Предварительный fallback-тариф. Финальная стоимость подтверждается менеджером.'
        );
    }
}
