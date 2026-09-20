<?php

declare(strict_types=1);

namespace Orgbox\BaseShop\Delivery;

use InvalidArgumentException;

final class DeliveryManager
{
    /** @var array<string, DeliveryProviderInterface> */
    private array $providers = [];

    public function __construct(DeliveryProviderInterface ...$providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->code()] = $provider;
        }
    }

    public function calculateAll(DeliveryRequest $request): array
    {
        $results = [];
        foreach ($this->providers as $provider) {
            try {
                $results[] = $provider->calculate($request);
            } catch (\Throwable) {
                $results[] = new DeliveryResult($provider->code(), '', 0, '', '', false, 'Сервис доставки временно недоступен.');
            }
        }

        return $results;
    }

    public function calculate(string $providerCode, DeliveryRequest $request): DeliveryResult
    {
        if (!isset($this->providers[$providerCode])) {
            throw new InvalidArgumentException('Неизвестный способ доставки.');
        }

        return $this->providers[$providerCode]->calculate($request);
    }
}
