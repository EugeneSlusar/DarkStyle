<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

use Bitrix\Main\Web\HttpClient;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/** Yandex Delivery across Russia, self_pickup; never creates a paid shipment. */
final class YandexDeliveryProvider implements DeliveryProviderInterface, PickupPointProviderInterface
{
    private Closure $transport;

    public function __construct(
        private readonly string $token = '',
        private readonly string $originStationId = '',
        private readonly bool $sandbox = false,
        ?Closure $transport = null
    ) {
        $this->transport = $transport ?? static function (string $url, array $headers, string $body): array {
            $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 15, 'redirect' => false]);
            foreach ($headers as $name => $value) {
                $http->setHeader($name, $value);
            }
            $response = $http->post($url, $body);
            if ($response === false || $http->getStatus() < 200 || $http->getStatus() >= 300) {
                throw new RuntimeException('Yandex request failed');
            }
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !empty($data['error']) || !empty($data['errors'])) {
                throw new RuntimeException('Invalid Yandex response');
            }
            return $data;
        };
    }

    public function code(): string { return 'yandex'; }

    public function pickupPoints(string $city): array
    {
        $this->requireConfiguration();
        if (trim($city) === '') {
            throw new InvalidArgumentException('Укажите город для поиска ПВЗ.');
        }
        try {
            $detected = $this->call('/location/detect', ['location' => trim($city)]);
            $geoIds = [];
            foreach ($detected['variants'] ?? [] as $variant) {
                if (is_int($variant['geo_id'] ?? null) && $variant['geo_id'] > 0) {
                    $geoIds[$variant['geo_id']] = true;
                }
            }
            if (count($geoIds) !== 1) {
                throw new InvalidArgumentException('Город не определён однозначно. Укажите город и область.');
            }
            $geoId = (int) array_key_first($geoIds);
            $data = $this->call('/pickup-points/list', ['geo_id' => $geoId, 'type' => 'pickup_point']);
            if (!is_array($data['points'] ?? null)) {
                throw new RuntimeException('Missing pickup points');
            }
            $points = [];
            foreach ($data['points'] as $point) {
                $address = $point['address'] ?? [];
                $deactivated = array_filter([$point['deactivation_date'] ?? null, $point['deactivation_date_predicted_debt'] ?? null],
                    static fn ($date): bool => $date !== null && $date !== '' && $date !== 'null');
                // Reject foreign-city, inactive, warehouse and incomplete entries, even if the API ignored the filter.
                if (($point['type'] ?? '') !== 'pickup_point' || (int) ($address['geoId'] ?? 0) !== $geoId || $deactivated
                    || !is_string($point['id'] ?? null) || $point['id'] === ''
                    || !is_string($address['full_address'] ?? null) || trim($address['full_address']) === '') {
                    continue;
                }
                $points[$point['id']] = [
                    'id' => $point['id'],
                    'name' => (string) ($point['name'] ?? 'ПВЗ Яндекс Доставки'),
                    'address' => $address['full_address'],
                    'postalCode' => (string) ($address['postal_code'] ?? ''),
                    'schedule' => $this->schedule($point['schedule']['restrictions'] ?? []),
                ];
            }
            return array_values($points);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidArgumentException('Не удалось загрузить ПВЗ Яндекс Доставки. Повторите позже.');
        }
    }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        try {
            $this->requireConfiguration();
            foreach ([$request->weight, $request->length, $request->width, $request->height, $request->declaredPrice] as $value) {
                if (!is_finite($value) || $value <= 0 || $value > PHP_INT_MAX / 100) {
                    throw new InvalidArgumentException('Не заданы корректные параметры упаковки товара.');
                }
            }
            if ($request->pickupPointId === '') {
                throw new InvalidArgumentException('Выберите ПВЗ Яндекс Доставки.');
            }
            $selected = null;
            // Revalidate against the current official list on every quote and order, not against browser data.
            foreach ($this->pickupPoints($request->destination) as $point) {
                if ($point['id'] === $request->pickupPointId) { $selected = $point; break; }
            }
            if ($selected === null) {
                throw new InvalidArgumentException('ПВЗ недоступен или находится в другом городе. Выберите ПВЗ заново.');
            }
            $data = $this->call('/pricing-calculator', [
                'source' => ['platform_station_id' => $this->originStationId],
                'destination' => ['platform_station_id' => $selected['id']],
                'tariff' => 'self_pickup',
                'total_weight' => (int) ceil($request->weight),
                'total_assessed_price' => (int) round($request->declaredPrice * 100),
                // Pricing only. The checkout has no payment capture or shipment creation.
                'client_price' => 0, 'payment_method' => 'already_paid',
                'places' => [['physical_dims' => [
                    'weight_gross' => (int) ceil($request->weight),
                    'dx' => (int) ceil($request->length), 'dy' => (int) ceil($request->width), 'dz' => (int) ceil($request->height),
                ]]],
            ]);
            $price = $data['pricing_total'] ?? '';
            $days = $data['delivery_days'] ?? null;
            if (!is_string($price) || !preg_match('/^([0-9]+(?:\.[0-9]{1,2})?)\s+RUB$/D', trim($price), $matches)
                || !is_finite((float) $matches[1]) || (float) $matches[1] <= 0 || !is_int($days) || $days < 0) {
                throw new RuntimeException('Incomplete Yandex tariff');
            }
            if ($this->sandbox) {
                throw new InvalidArgumentException('Яндекс Доставка работает в тестовом режиме. Оформление временно недоступно.');
            }
            return new DeliveryResult($this->code(), 'Яндекс Доставка: в ПВЗ', round((float) $matches[1], 2),
                $days . ' дней', 'Предварительный тариф Яндекс Доставки до выбранного ПВЗ.', true, '',
                $selected['id'], $selected['address'], $selected['postalCode']);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($exception->getMessage());
        } catch (\Throwable) {
            return $this->failure('Не удалось получить тариф Яндекс Доставки. Повторите позже.');
        }
    }

    private function requireConfiguration(): void
    {
        if (trim($this->token) === '' || trim($this->originStationId) === '') {
            throw new InvalidArgumentException('Яндекс Доставка пока недоступна. Свяжитесь с менеджером.');
        }
    }

    private function call(string $path, array $payload): array
    {
        $host = $this->sandbox ? 'https://b2b.taxi.tst.yandex.net' : 'https://b2b-authproxy.taxi.yandex.net';
        return ($this->transport)($host . '/api/b2b/platform' . $path,
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->token],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function schedule(array $restrictions): string
    {
        $days = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
        $parts = [];
        foreach ($restrictions as $restriction) {
            $from = $restriction['time_from'] ?? null;
            $to = $restriction['time_to'] ?? null;
            if (!is_array($from) || !is_array($to)) { continue; }
            $labels = [];
            foreach ($restriction['days'] ?? [] as $day) {
                if (isset($days[$day])) { $labels[] = $days[$day]; }
            }
            if ($labels) {
                $parts[] = implode(', ', $labels) . sprintf(' %02d:%02d–%02d:%02d',
                    $from['hours'] ?? 0, $from['minutes'] ?? 0, $to['hours'] ?? 0, $to['minutes'] ?? 0);
            }
        }
        return implode('; ', $parts);
    }

    private function failure(string $message): DeliveryResult
    {
        return new DeliveryResult($this->code(), 'Яндекс Доставка: в ПВЗ', 0, '', '', false, $message);
    }
}
