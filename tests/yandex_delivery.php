<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'OrgBox\\BaseShop\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use OrgBox\BaseShop\Delivery\YandexDeliveryProvider;
use OrgBox\BaseShop\Delivery\DeliveryRequest;
use OrgBox\BaseShop\Delivery\DeliveryManager;
use OrgBox\BaseShop\Order\OrderDataValidator;

function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function rejects(Closure $run, string $message): void {
    try { $run(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
}
$point = ['id' => 'point-1', 'name' => 'Яндекс', 'type' => 'pickup_point',
    'address' => ['geoId' => 51, 'full_address' => 'Самара, ул. Тестовая, 1', 'postal_code' => '443000'],
    'schedule' => ['restrictions' => [['days' => [1,2], 'time_from' => ['hours' => 9, 'minutes' => 0], 'time_to' => ['hours' => 20, 'minutes' => 30]]]]];
$foreign = $point; $foreign['id'] = 'foreign'; $foreign['address']['geoId'] = 99;
$inactive = $point; $inactive['id'] = 'inactive'; $inactive['deactivation_date'] = '2026-01-01';
$warehouse = $point; $warehouse['id'] = 'warehouse'; $warehouse['type'] = 'warehouse';
$badAddress = $point; $badAddress['id'] = 'bad'; unset($badAddress['address']['full_address']);
$calls = [];
$price = ['pricing_total' => '225.70 RUB', 'delivery_days' => 7];
$variants = [['geo_id' => 51, 'address' => 'Самара']];
$points = [$point, $foreign, $inactive, $warehouse, $badAddress];
$transport = static function (string $url, array $headers, string $body) use (&$calls, &$price, &$variants, &$points): array {
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $calls[] = [$url, $data];
    check($headers['Authorization'] === 'Bearer test-token', 'Wrong token');
    if (str_ends_with($url, '/location/detect')) { return ['variants' => $variants]; }
    if (str_ends_with($url, '/pickup-points/list')) {
        check($data === ['geo_id' => 51, 'type' => 'pickup_point'], 'Wrong points filter');
        return ['points' => $points];
    }
    check(str_ends_with($url, '/pricing-calculator'), 'Must not create/confirm shipment');
    check($data['source'] === ['platform_station_id' => 'togliatti-station'], 'Wrong origin station');
    check($data['destination'] === ['platform_station_id' => 'point-1'], 'Must quote exact point');
    check($data['tariff'] === 'self_pickup', 'Wrong tariff');
    check($data['total_weight'] === 1401 && $data['total_assessed_price'] === 258001, 'Wrong gram/kopeck conversion');
    check($data['places'][0]['physical_dims'] === ['weight_gross' => 1401, 'dx' => 81, 'dy' => 15, 'dz' => 30], 'Wrong dimensions');
    return $price;
};
$provider = new YandexDeliveryProvider('test-token', 'togliatti-station', false, $transport);
$request = new DeliveryRequest('Самара', '', 1400.2, 80.2, 15, 30, 2580.01, 'point-1');
$list = $provider->pickupPoints('Самара');
check(count($list) === 1 && $list[0]['id'] === 'point-1', 'Must filter unusable points');
check($list[0]['schedule'] === 'Пн, Вт 09:00–20:30', 'Wrong schedule');
$result = $provider->calculate($request);
check($result->success && $result->price === 225.7 && $result->estimatedDays === '7 дней', 'Wrong tariff');
check($result->pickupPointAddress === $point['address']['full_address'] && $result->pickupPointId === 'point-1', 'Lost authoritative point');
check($result->toArray()['pickupPointPostalCode'] === '443000', 'Lost postcode');
check(str_starts_with($calls[0][0], 'https://b2b-authproxy.taxi.yandex.net/'), 'Wrong production host');
check(!(new YandexDeliveryProvider())->calculate($request)->success, 'No keys must block calculation');
rejects(fn() => (new YandexDeliveryProvider())->pickupPoints('Самара'), 'No keys must block points');
check(!(new YandexDeliveryProvider('test-token', 'togliatti-station', true, $transport))->calculate($request)->success, 'Sandbox must block orders');
check(str_starts_with(end($calls)[0], 'https://b2b.taxi.tst.yandex.net/'), 'Wrong sandbox host');
$variants[] = ['geo_id' => 99, 'address' => 'Другая Самара'];
rejects(fn() => $provider->pickupPoints('Самара'), 'Ambiguous cities must not silently select first');
$variants = [['geo_id' => 51]];
$points = [$foreign];
check(!$provider->calculate($request)->success, 'Forged or foreign-city point must block quote');
$points = [$inactive];
check(!$provider->calculate($request)->success, 'Inactive point must block quote');
$points = [$point];
foreach ([[], ['pricing_total' => '225.70 USD', 'delivery_days' => 7], ['pricing_total' => '-1 RUB', 'delivery_days' => 7], ['pricing_total' => '0 RUB', 'delivery_days' => 7], ['pricing_total' => '100 RUB', 'delivery_days' => -1], ['pricing_total' => '100 RUB', 'delivery_days' => '7']] as $price) {
    check(!$provider->calculate($request)->success, 'Bad tariff must block quote');
}
$broken = new YandexDeliveryProvider('token', 'station', false, static function (): array { throw new RuntimeException('secret-value'); });
check(!$broken->calculate($request)->success && !str_contains($broken->calculate($request)->error, 'secret-value'), 'Must not expose transport secret');
$input = ['product_id' => 1, 'quantity' => 1, 'customer_name' => 'Иван', 'phone' => '+79990000000', 'city' => 'Самара', 'delivery_provider' => 'yandex', 'pickup_point_id' => 'point-1'];
$validator = new OrderDataValidator();
check($validator->validate($input)['pickup_point_id'] === 'point-1', 'Yandex must not require home address/postcode');
$input['pickup_point_id'] = '';
rejects(fn() => $validator->validate($input), 'Point selection is mandatory');
$input['delivery_provider'] = 'cdek';
rejects(fn() => $validator->validate($input), 'CDEK still requires home address');
$manager = new DeliveryManager($provider);
check(count($manager->pickupPoints('yandex', 'Самара')) === 1, 'Manager should route points');
rejects(fn() => $manager->pickupPoints('cdek', 'Самара'), 'No generic point fallback');
echo "Yandex delivery tests passed\n";
