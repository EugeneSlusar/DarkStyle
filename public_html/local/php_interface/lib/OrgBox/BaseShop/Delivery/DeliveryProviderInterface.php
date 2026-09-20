<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

interface DeliveryProviderInterface
{
    public function code(): string;

    public function calculate(DeliveryRequest $request): DeliveryResult;
}
