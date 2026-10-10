<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'OrgBox\\BaseShop\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
use OrgBox\BaseShop\Delivery\CdekDeliveryProvider;
use OrgBox\BaseShop\Delivery\DeliveryRequest;
use OrgBox\BaseShop\Order\OrderDataValidator;
function check(bool $value, string $message): void { if (!$value) { throw new RuntimeException($message); } }
function rejects(Closure $run): void { try { $run(); } catch (InvalidArgumentException) { return; } throw new RuntimeException('Must reject request'); }
$point = ['code' => 'SAM1', 'type' => 'PVZ', 'status' => 'ACTIVE', 'is_handout' => true,
    'work_time' => 'пн-пт 9-18', 'weight_min' => 0, 'weight_max' => 5, 'length_max' => 100, 'width_max' => 40, 'height_max' => 20,
    'location' => ['city_code' => 44, 'country_code' => 'RU', 'address' => 'ул. Тестовая, 1',
        'address_full' => 'Россия, Самара, ул. Тестовая, 1', 'postal_code' => '443000']];
$city = ['code' => 44, 'city' => 'Самара', 'region' => 'Самарская область', 'country_code' => 'RU'];
$cities = [$city]; $points = [$point]; $calls = [];
$tariff = ['total_sum' => 225.7, 'period_min' => 2, 'period_max' => 4];
$transport = static function (string $url, array $headers, string $body, string $method = 'POST') use (&$calls, &$cities, &$points, &$tariff): array {
    $calls[] = [$url, $method];
    if (str_ends_with($url, '/oauth/token')) { return ['access_token' => 'token']; }
    check($headers['Authorization'] === 'Bearer token', 'Auth missing');
    $path = parse_url($url, PHP_URL_PATH); parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    if ($path === '/v2/location/cities') {
        check($method === 'GET' && $query['city'] === 'Самара' && $query['country_codes'] === 'RU', 'Wrong city query'); return $cities;
    }
    if ($path === '/v2/deliverypoints') {
        check($method === 'GET' && $body === '' && $query['city_code'] === '44' && $query['type'] === 'PVZ' && $query['is_handout'] === 'true', 'Wrong points filter');
        if (isset($query['code'])) { check($query['code'] === 'SAM1', 'Must re-fetch selected point'); }
        return $points;
    }
    check($path === '/v2/calculator/tariff' && $method === 'POST', 'Never create a shipment');
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    check($data['tariff_code'] === 136 && $data['delivery_point'] === 'SAM1', 'Must quote pickup tariff and exact point');
    check($data['from_location'] === ['code' => 123] && $data['to_location'] === ['code' => 44, 'country_code' => 'RU', 'address' => 'ул. Тестовая, 1'], 'Use official point location');
    check($data['packages'][0] === ['weight' => 1401, 'length' => 81, 'width' => 15, 'height' => 30], 'Package units/rounding');
    return $tariff;
};
$provider = new CdekDeliveryProvider('id', 'secret', 123, false, $transport, true);
$request = new DeliveryRequest('Самара', '999999', 1400.2, 80.2, 15, 30, 2580, 'SAM1');
$list = $provider->pickupPoints('Самара');
check(count($list) === 1 && $list[0]['id'] === 'SAM1' && $list[0]['schedule'] === 'пн-пт 9-18', 'Wrong pickup list');
$result = $provider->calculate($request);
check($result->success && $result->price === 225.7 && $result->provider === 'cdek_pickup', 'Wrong quote');
check($result->pickupPointAddress === $point['location']['address_full'] && $result->pickupPointPostalCode === '443000', 'Authoritative address/index missing');
foreach ([['status' => 'CLOSED'], ['is_handout' => false], ['type' => 'POSTAMAT'], ['location' => array_replace($point['location'], ['city_code' => 99])], ['location' => array_replace($point['location'], ['address_full' => ''])]] as $change) {
    $points = [array_replace($point, $change)];
    check($provider->pickupPoints('Самара') === [] && !$provider->calculate($request)->success, 'Unusable point must fail');
}
foreach ([['weight_max' => 1], ['weight_min' => 1.401], ['length_max' => 80], ['width_max' => 10], ['weight_max' => 'bad']] as $change) {
    $points = [array_replace($point, $change)]; check(!$provider->calculate($request)->success, 'Invalid package/point limits must block');
}
$points = [$point];
$tariff['warnings'] = [['code' => 'POINT_REPLACED']];
check(!$provider->calculate($request)->success, 'Never accept substitution warning'); unset($tariff['warnings']);
foreach ([[], ['total_sum' => 0, 'period_min' => 2, 'period_max' => 4], ['total_sum' => 225.7, 'period_min' => 4, 'period_max' => 2], ['total_sum' => 225.7, 'period_min' => INF, 'period_max' => INF], ['errors' => [['code' => 'FAIL']]] ] as $badTariff) {
    $tariff = $badTariff; check(!$provider->calculate($request)->success, 'Bad tariff must fail');
}
$tariff = ['total_sum' => 225.7, 'period_min' => 2, 'period_max' => 4];
$cities[] = array_replace($city, ['code' => 99, 'region' => 'Другой регион']);
rejects(fn() => $provider->pickupPoints('Самара'));
check(count($provider->pickupPoints('Самара, Самарская обл.')) === 1, 'Region must resolve ambiguity');
$cities = [$city]; $points = [];
check(!$provider->calculate($request)->success, 'Removed point must fail');
$points = [$point];
check(!(new CdekDeliveryProvider('id', 'secret', 123, true, $transport, true))->calculate($request)->success, 'Sandbox must block checkout');
rejects(fn() => (new CdekDeliveryProvider('', '', 0, false, $transport, true))->pickupPoints('Самара'));
rejects(fn() => (new CdekDeliveryProvider('id', 'secret', 123, false, $transport))->pickupPoints('Самара'));
check(!$provider->calculate(new DeliveryRequest('Самара', '', 1000, 10,10,10,100))->success, 'Pickup ID is mandatory');
$input = ['product_id' => 1, 'customer_name' => 'Иван', 'phone' => '+79990000000', 'city' => 'Самара', 'delivery_provider' => 'cdek_pickup', 'pickup_point_id' => 'SAM1'];
check((new OrderDataValidator())->validate($input)['address'] === '', 'Pickup must not require home address');
$input['pickup_point_id'] = ''; rejects(fn() => (new OrderDataValidator())->validate($input));
$points = array_fill(0, 1000, $point);
rejects(fn() => $provider->pickupPoints('Самара')); // No silent truncation at the pagination safety limit.
echo "CDEK pickup tests passed\n";
