<?php

declare(strict_types=1);

namespace DarkStyle\Delivery;

final class DeliveryResult
{
    public function __construct(
        public readonly string $provider,
        public readonly string $service,
        public readonly float $price,
        public readonly string $estimatedDays,
        public readonly string $description,
        public readonly bool $success = true,
        public readonly string $error = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'service' => $this->service,
            'price' => $this->price,
            'estimatedDays' => $this->estimatedDays,
            'description' => $this->description,
            'success' => $this->success,
            'error' => $this->error,
        ];
    }
}
