<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

final class RussianPostDeliveryProvider implements DeliveryProviderInterface
{
    public function code(): string
    {
        return 'russian_post';
    }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        $weightKg = max(0.1, $request->weight / 1000);
        $price = ceil(max(350, 230 + $weightKg * 110 + $request->declaredPrice * 0.01) / 10) * 10;

        return new DeliveryResult(
            $this->code(),
            'Почта России: посылка',
            $price,
            '5–12 дней',
            'Предварительный fallback-тариф. Финальная стоимость подтверждается менеджером.'
        );
    }
}
