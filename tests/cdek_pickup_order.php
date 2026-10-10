<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Catalog {
    final class ProductRepository {
        public function getProductById(int $id): ?array {
            return ['ID' => $id, 'NAME' => 'Товар', 'PRICE' => 1290.0, 'WEIGHT' => 700.0, 'LENGTH' => 80.0, 'WIDTH' => 15.0, 'HEIGHT' => 15.0];
        }
    }
}
namespace OrgBox\BaseShop\Order {
    final class IblockOrderRepository {
        public array $saved = [];
        public function save(array $order): int { $this->saved[] = $order; return 42; }
    }
}
namespace {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'OrgBox\\BaseShop\\';
        if (str_starts_with($class, $prefix)) {
            require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/'
                . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    });
    function check(bool $value, string $message): void { if (!$value) { throw new \RuntimeException($message); } }
    function rejects(\Closure $run, string $message): void {
        try { $run(); } catch (\InvalidArgumentException) { return; }
        throw new \RuntimeException($message);
    }
    $tariff = 225.7;
    $pointId = 'SAM1';
    $provider = new \OrgBox\BaseShop\Delivery\CdekDeliveryProvider('id', 'secret', 123, false,
        static function (string $url, array $headers, string $body) use (&$tariff, &$pointId): array {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path === '/v2/oauth/token') { return ['access_token' => 'token']; }
            if ($path === '/v2/location/cities') { return [['code' => 44, 'city' => 'Самара', 'country_code' => 'RU']]; }
            if ($path === '/v2/deliverypoints') { return [[
                'code' => $pointId, 'type' => 'PVZ', 'status' => 'ACTIVE', 'is_handout' => true,
                'location' => ['city_code' => 44, 'country_code' => 'RU', 'address' => 'ул. Настоящая, 1',
                    'address_full' => 'Самара, настоящий ПВЗ', 'postal_code' => '443000'],
            ]]; }
            $data = json_decode($body, true);
            check($data['packages'][0]['weight'] === 1400, 'Server quantity/package');
            check($data['packages'][0]['height'] === 30 && $data['delivery_point'] === 'SAM1', 'Wrong package/point');
            return ['total_sum' => $tariff, 'period_min' => 2, 'period_max' => 4];
        }, true);
    $orders = new \OrgBox\BaseShop\Order\IblockOrderRepository();
    $notifier = new class implements \OrgBox\BaseShop\Notification\OrderNotifierInterface {
        public array $sent = [];
        public function notify(array $order): bool { $this->sent[] = $order; return true; }
    };
    $service = new \OrgBox\BaseShop\Order\OrderService(new \OrgBox\BaseShop\Catalog\ProductRepository(), $orders,
        new \OrgBox\BaseShop\Delivery\DeliveryManager($provider), $notifier, new \OrgBox\BaseShop\Order\OrderDataValidator());
    $input = ['product_id' => 1, 'quantity' => 2, 'customer_name' => 'Иван', 'phone' => '+79990000000', 'city' => 'Самара',
        'delivery_provider' => 'cdek_pickup', 'pickup_point_id' => 'SAM1', 'delivery_quote' => '225.7',
        'address' => 'Подменённый адрес', 'postal_code' => '999999', 'price' => '1'];
    $quote = $service->deliveryOptions(1, 2, 'Самара', '', 'cdek_pickup', 'SAM1');
    check($quote[0]['price'] === 225.7 && $quote[0]['pickupPointId'] === 'SAM1', 'Quote must include exact point');
    $result = $service->place($input);
    check($result['order_id'] === 42 && $result['total'] === 2805.7, 'Server total must ignore browser price');
    check($orders->saved[0]['address'] === 'Самара, настоящий ПВЗ (ПВЗ: SAM1)', 'Order must store official address and ID');
    check($orders->saved[0]['postal_code'] === '443000', 'Order must ignore browser postcode');
    check($notifier->sent[0]['address'] === $orders->saved[0]['address'], 'Manager must receive selected point');
    $tariff = 300.0;
    rejects(fn() => $service->place($input), 'Changed tariff must reject stale quote');
    check(count($orders->saved) === 1, 'Changed price must not save order');
    $tariff = 225.7; $pointId = 'another-point';
    rejects(fn() => $service->place($input), 'Unavailable point must reject order');
    check(count($orders->saved) === 1 && count($notifier->sent) === 1, 'Rejected order must not save or notify');
    echo "CDEK pickup order tests passed\n";
}
