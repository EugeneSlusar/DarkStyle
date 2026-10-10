<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Catalog {
    final class ProductRepository {
        public function getProductById(int $id): ?array {
            return ['ID' => $id, 'NAME' => 'Товар', 'PRICE' => 1290.0, 'WEIGHT' => 700.0, 'LENGTH' => 24.0, 'WIDTH' => 16.0, 'HEIGHT' => 15.0];
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
    $tariff = 22570;
    $provider = new \OrgBox\BaseShop\Delivery\RussianPostDeliveryProvider('445027',
        static function (string $url) use (&$tariff): array {
            parse_str(parse_url($url, PHP_URL_QUERY), $data);
            check($data['from'] === '445027' && $data['weight'] === '1400' && $data['size'] === '24x16x30', 'Server must compute origin/quantity/package');
            return ['version_api' => 2, 'id' => 4030, 'from' => 445027, 'to' => 443000,
                'paynds' => $tariff, 'delivery' => ['min' => 2, 'max' => 4]];
        });
    $orders = new \OrgBox\BaseShop\Order\IblockOrderRepository();
    $notifier = new class implements \OrgBox\BaseShop\Notification\OrderNotifierInterface {
        public array $sent = [];
        public function notify(array $order): bool { $this->sent[] = $order; return true; }
    };
    $service = new \OrgBox\BaseShop\Order\OrderService(new \OrgBox\BaseShop\Catalog\ProductRepository(), $orders,
        new \OrgBox\BaseShop\Delivery\DeliveryManager($provider), $notifier, new \OrgBox\BaseShop\Order\OrderDataValidator());
    $input = ['product_id' => 1, 'quantity' => 2, 'customer_name' => 'Иван', 'phone' => '+79990000000', 'city' => 'Самара',
        'delivery_provider' => 'russian_post', 'delivery_quote' => '225.7',
        'address' => 'Адрес получателя', 'postal_code' => '443000', 'price' => '1', 'origin_postal_code' => '101000'];
    $quote = $service->deliveryOptions(1, 2, 'Самара', '443000', 'russian_post');
    check($quote[0]['price'] === 225.7, 'Wrong quote');
    $result = $service->place($input);
    check($result['order_id'] === 42 && $result['total'] === 2805.7, 'Server total must ignore browser price');
    check($orders->saved[0]['address'] === 'Адрес получателя' && $orders->saved[0]['postal_code'] === '443000', 'Lost recipient');
    $tariff = 30000;
    rejects(fn() => $service->place($input), 'Changed tariff must reject stale quote');
    $tariff = 0;
    rejects(fn() => $service->place($input), 'Invalid tariff must reject order');
    $input['postal_code'] = '44300';
    rejects(fn() => $service->place($input), 'Five-digit postcode must reject order');
    check(count($orders->saved) === 1 && count($notifier->sent) === 1, 'Rejected order must not save or notify');
    echo "Russian Post order tests passed\n";
}
