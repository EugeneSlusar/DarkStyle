<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

use Bitrix\Main\Web\HttpClient;
use Closure;
use RuntimeException;
use InvalidArgumentException;

final class CdekDeliveryProvider implements DeliveryProviderInterface, PickupPointProviderInterface
{
    private Closure $transport;
    private ?string $token = null;

    public function __construct(
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly int $originCode = 0,
        private readonly bool $sandbox = false,
        ?Closure $transport = null,
        private readonly bool $pickup = false
    ) {
        $this->transport = $transport ?? static function (string $url, array $headers, string $body, string $method = 'POST'): array {
            $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 15, 'redirect' => false]);
            foreach ($headers as $name => $value) {
                $http->setHeader($name, $value);
            }
            $response = $method === 'GET' ? $http->get($url) : $http->post($url, $body);
            if ($response === false || $http->getStatus() < 200 || $http->getStatus() >= 300) {
                throw new RuntimeException('CDEK request failed');
            }
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !empty($data['errors'])) {
                throw new RuntimeException('Invalid CDEK response');
            }
            return $data;
        };
    }

    public function code(): string
    {
        return $this->pickup ? 'cdek_pickup' : 'cdek';
    }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->originCode <= 0) {
            return $this->failure('Расчёт СДЭК пока недоступен. Свяжитесь с менеджером.');
        }
        foreach ([$request->weight, $request->length, $request->width, $request->height] as $value) {
            if (!is_finite($value) || $value <= 0 || $value > 2147483647) {
                return $this->failure('Не заданы параметры упаковки товара. Свяжитесь с менеджером.');
            }
        }
        try {
            $point = null;
            $location = ['city' => $request->destination, 'postal_code' => $request->postalCode, 'country_code' => 'RU'];
            if ($this->pickup) {
                if ($request->pickupPointId === '') { throw new InvalidArgumentException('Выберите ПВЗ СДЭК.'); }
                $cityCode = $this->cityCode($request->destination);
                $offices = $this->api('/deliverypoints', ['code' => $request->pickupPointId, 'city_code' => $cityCode,
                    'country_code' => 'RU', 'type' => 'PVZ', 'is_handout' => 'true'], 'GET');
                if (!array_is_list($offices)) { throw new RuntimeException('Invalid point lookup'); }
                foreach ($offices as $office) {
                    if (is_array($office) && ($office['code'] ?? '') === $request->pickupPointId && $this->usable($office, $cityCode)) {
                        $point = $office; break;
                    }
                }
                if ($point === null) { throw new InvalidArgumentException('ПВЗ СДЭК недоступен или находится в другом городе. Выберите другой пункт.'); }
                $this->checkPackage($point, $request);
                $location = ['code' => $cityCode, 'country_code' => 'RU', 'address' => $point['location']['address']];
            }
            // 136: склад — склад (ПВЗ), 137: склад — дверь.
            $payload = [
                    'type' => 1, 'currency' => 1, 'tariff_code' => $this->pickup ? 136 : 137,
                    'from_location' => ['code' => $this->originCode],
                    'to_location' => $location,
                    'packages' => [[
                        'weight' => (int) ceil($request->weight),
                        'length' => (int) ceil($request->length),
                        'width' => (int) ceil($request->width),
                        'height' => (int) ceil($request->height),
                    ]],
                ];
            if ($point !== null) { $payload['delivery_point'] = $point['code']; }
            $data = $this->api('/calculator/tariff', $payload);
            // CDEK can substitute an unsuitable point. Never accept warnings for pickup quotes.
            if ($this->pickup && !empty($data['warnings'])) { throw new InvalidArgumentException('СДЭК не подтвердил выбранный ПВЗ. Выберите другой пункт.'); }
            $price = $data['total_sum'] ?? null;
            $min = $data['period_min'] ?? null;
            $max = $data['period_max'] ?? null;
            if (!empty($data['errors']) || !is_numeric($price) || !is_finite((float) $price) || round((float) $price, 2) <= 0
                || !is_int($min) || !is_int($max) || (int) $min < 0 || (int) $max < (int) $min) {
                throw new RuntimeException('Incomplete CDEK tariff');
            }
            if ($this->sandbox) {
                return $this->failure('СДЭК работает в тестовом режиме. Оформление временно недоступно.');
            }
            return new DeliveryResult($this->code(), $this->service(), round((float) $price, 2),
                (int) $min . '–' . (int) $max . ' дней', 'Тариф СДЭК по маршруту и параметрам упаковки. Срок в рабочих днях.',
                true, '', $point['code'] ?? '', $point['location']['address_full'] ?? '', $point['location']['postal_code'] ?? '');
        } catch (InvalidArgumentException $exception) {
            return $this->failure($exception->getMessage());
        } catch (\Throwable) {
            $this->token = null;
            return $this->failure('Не удалось получить тариф СДЭК. Проверьте город и индекс или повторите позже.');
        }
    }

    private function service(): string { return $this->pickup ? 'СДЭК: в ПВЗ' : 'СДЭК: курьер до двери'; }

    private function api(string $path, array $payload, string $method = 'POST'): array
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->originCode <= 0) {
            throw new InvalidArgumentException('СДЭК пока не настроен. Свяжитесь с менеджером.');
        }
        $base = $this->sandbox ? 'https://api.edu.cdek.ru/v2' : 'https://api.cdek.ru/v2';
        if ($this->token === null) {
            $auth = ($this->transport)($base . '/oauth/token', ['Content-Type' => 'application/x-www-form-urlencoded'],
                http_build_query(['grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret]));
            if (!is_string($auth['access_token'] ?? null) || $auth['access_token'] === '') { throw new RuntimeException('Missing token'); }
            $this->token = $auth['access_token'];
        }
        $url = $base . $path;
        if ($method === 'GET') { $url .= '?' . http_build_query($payload, '', '&', PHP_QUERY_RFC3986); }
        $data = ($this->transport)($url, ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->token],
            $method === 'GET' ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $method);
        if (!empty($data['errors'])) { throw new RuntimeException('CDEK error'); }
        return $data;
    }

    private function cityCode(string $input): int
    {
        [$city, $region] = array_pad(explode(',', trim($input), 2), 2, '');
        $city = trim($city); $region = trim($region);
        if ($city === '') { throw new InvalidArgumentException('Укажите город для поиска ПВЗ СДЭК.'); }
        $rows = $this->api('/location/cities', ['city' => $city, 'country_codes' => 'RU', 'size' => 1000, 'page' => 0], 'GET');
        if (!array_is_list($rows) || count($rows) >= 1000) { throw new RuntimeException('Incomplete cities'); }
        $normalize = static fn(string $text): string => trim(preg_replace('/\s+/', ' ',
            preg_replace('/(?:область|обл\.?|республика|респ\.?)/u', '', mb_strtolower(trim($text)))));
        $matches = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['country_code'] ?? '') !== 'RU' || !is_int($row['code'] ?? null) || $row['code'] <= 0
                || mb_strtolower((string) ($row['city'] ?? '')) !== mb_strtolower($city)) { continue; }
            if ($region !== '' && $normalize((string) ($row['region'] ?? '')) !== $normalize($region)) { continue; }
            $matches[$row['code']] = $row['code'];
        }
        if (count($matches) !== 1) { throw new InvalidArgumentException('Город СДЭК не найден или неоднозначен. Укажите город и область через запятую.'); }
        return array_values($matches)[0];
    }

    private function usable(array $point, int $cityCode): bool
    {
        return is_string($point['code'] ?? null) && $point['code'] !== '' && ($point['type'] ?? '') === 'PVZ'
            && ($point['status'] ?? '') === 'ACTIVE' && ($point['is_handout'] ?? false) === true && empty($point['errors'])
            && ($point['location']['city_code'] ?? null) === $cityCode && ($point['location']['country_code'] ?? '') === 'RU'
            && is_string($point['location']['address_full'] ?? null) && trim($point['location']['address_full']) !== ''
            && is_string($point['location']['address'] ?? null) && trim($point['location']['address']) !== '';
    }

    private function checkPackage(array $point, DeliveryRequest $request): void
    {
        $kg = ceil($request->weight) / 1000;
        foreach (['weight_min', 'weight_max', 'length_max', 'width_max', 'height_max'] as $key) {
            if (isset($point[$key]) && (!is_numeric($point[$key]) || !is_finite((float) $point[$key]) || (float) $point[$key] < 0)) {
                throw new RuntimeException('Invalid point limits');
            }
        }
        if ((isset($point['weight_min']) && $kg <= (float) $point['weight_min'])
            || (isset($point['weight_max']) && $kg > (float) $point['weight_max'])) {
            throw new InvalidArgumentException('Выбранный ПВЗ СДЭК не принимает посылку такого веса. Выберите другой пункт.');
        }
        $size = [ceil($request->length), ceil($request->width), ceil($request->height)];
        $limits = array_map(static fn(string $key): float => isset($point[$key]) ? (float) $point[$key] : INF,
            ['length_max', 'width_max', 'height_max']);
        sort($size); sort($limits);
        foreach ($size as $index => $value) {
            if ($value > $limits[$index]) { throw new InvalidArgumentException('Посылка не подходит по габаритам для выбранного ПВЗ СДЭК. Выберите другой пункт.'); }
        }
    }

    public function pickupPoints(string $city): array
    {
        if (!$this->pickup) { throw new InvalidArgumentException('Выберите доставку СДЭК в ПВЗ.'); }
        try {
            $cityCode = $this->cityCode($city);
            $points = [];
            for ($page = 0; $page < 10; $page++) {
                $rows = $this->api('/deliverypoints', ['city_code' => $cityCode, 'country_code' => 'RU', 'type' => 'PVZ',
                    'is_handout' => 'true', 'size' => 1000, 'page' => $page], 'GET');
                if (!array_is_list($rows)) { throw new RuntimeException('Invalid point list'); }
                foreach ($rows as $row) {
                    if (!is_array($row) || !$this->usable($row, $cityCode)) { continue; }
                    $points[$row['code']] = ['id' => $row['code'], 'name' => 'СДЭК ' . $row['code'],
                        'address' => $row['location']['address_full'], 'postalCode' => $row['location']['postal_code'] ?? '',
                        'schedule' => is_string($row['work_time'] ?? null) ? $row['work_time'] : ''];
                }
                if (count($rows) < 1000) { return array_values($points); }
            }
            throw new RuntimeException('Too many points');
        } catch (InvalidArgumentException $exception) { throw $exception; }
        catch (\Throwable) {
            $this->token = null;
            throw new InvalidArgumentException('Не удалось получить ПВЗ СДЭК. Повторите позже.');
        }
    }

    private function failure(string $message): DeliveryResult
    {
        return new DeliveryResult($this->code(), $this->service(), 0, '', '', false, $message);
    }
}
