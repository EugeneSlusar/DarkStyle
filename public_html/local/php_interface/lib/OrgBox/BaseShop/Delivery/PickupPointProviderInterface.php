<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

interface PickupPointProviderInterface
{
    /** @return array<array{id: string, name: string, address: string, postalCode: string, schedule: string}> */
    public function pickupPoints(string $city): array;
}
