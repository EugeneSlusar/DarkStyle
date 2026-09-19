<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/public_html/local/php_interface/lib/DarkStyle';
require $root . '/Delivery/DeliveryProviderInterface.php';
require $root . '/Delivery/DeliveryRequest.php';
require $root . '/Delivery/DeliveryResult.php';
require $root . '/Delivery/CdekDeliveryProvider.php';
require $root . '/Delivery/RussianPostDeliveryProvider.php';
require $root . '/Delivery/DeliveryManager.php';
require $root . '/Order/OrderDataValidator.php';

use DarkStyle\Delivery\CdekDeliveryProvider;
use DarkStyle\Delivery\DeliveryManager;
use DarkStyle\Delivery\DeliveryRequest;
use DarkStyle\Delivery\RussianPostDeliveryProvider;
use DarkStyle\Order\OrderDataValidator;

$request = new DeliveryRequest('Самара', '443000', 700, 80, 15, 15, 1290);
$manager = new DeliveryManager(new CdekDeliveryProvider(), new RussianPostDeliveryProvider());
$options = $manager->calculateAll($request);

assert(count($options) === 2);
assert($options[0]->success && $options[0]->price > 0);
assert($options[1]->success && $options[1]->price > 0);

$validator = new OrderDataValidator();
$data = $validator->validate([
    'product_id' => 1,
    'quantity' => 1,
    'customer_name' => 'Иван',
    'phone' => '+7 999 000-00-00',
    'email' => 'mail@example.test',
    'city' => 'Самара',
    'postal_code' => '443000',
    'address' => 'ул. Тестовая, 1',
    'delivery_provider' => 'cdek',
]);

assert($data['customer_name'] === 'Иван');
echo "Smoke tests passed\n";
