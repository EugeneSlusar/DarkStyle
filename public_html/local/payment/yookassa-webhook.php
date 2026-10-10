<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use OrgBox\BaseShop\Config;
use OrgBox\BaseShop\Notification\EmailOrderNotifier;
use OrgBox\BaseShop\Notification\EmailCustomerPaidNotifier;
use OrgBox\BaseShop\Order\IblockOrderRepository;
use OrgBox\BaseShop\Payment\PaymentService;

try {
    $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $orderId = (int) ($payload['object']['metadata']['order_id'] ?? 0);
    if ($orderId <= 0) throw new RuntimeException('Order metadata is missing.');
    (new PaymentService(new IblockOrderRepository(Config::getIblockId('orders')), new EmailOrderNotifier((string) Config::get('manager_email', '')), new EmailCustomerPaidNotifier()))->refreshYooKassa($orderId);
    http_response_code(200);
} catch (Throwable) {
    http_response_code(400);
}
