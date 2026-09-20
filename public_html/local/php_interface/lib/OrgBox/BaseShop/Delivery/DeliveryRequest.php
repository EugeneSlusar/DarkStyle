<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

final class DeliveryRequest
{
    public function __construct(
        public readonly string $destination,
        public readonly string $postalCode,
        public readonly float $weight,
        public readonly float $length,
        public readonly float $width,
        public readonly float $height,
        public readonly float $declaredPrice
    ) {
    }

    public function volumeWeight(): float
    {
        return max(0.1, ($this->length * $this->width * $this->height) / 5000);
    }
}
