<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use OrgBox\BaseShop\Config;
use OrgBox\BaseShop\Notification\EmailOrderNotifier;
use OrgBox\BaseShop\Notification\EmailCustomerPaidNotifier;
use OrgBox\BaseShop\Order\IblockOrderRepository;
use OrgBox\BaseShop\Payment\PaymentService;
use OrgBox\BaseShop\Payment\RobokassaProvider;

$orderId = (int) ($_REQUEST['shp_order_id'] ?? 0);
$invoiceId = (string) ($_REQUEST['InvId'] ?? '');
$sum = (string) ($_REQUEST['OutSum'] ?? '');
$signature = (string) ($_REQUEST['SignatureValue'] ?? '');
if ($orderId <= 0 || !(new RobokassaProvider())->isValidNotification($sum, $invoiceId, $signature, (string) $orderId)) {
    http_response_code(400);
    exit('bad signature');
}
(new PaymentService(new IblockOrderRepository(Config::getIblockId('orders')), new EmailOrderNotifier((string) Config::get('manager_email', '')), new EmailCustomerPaidNotifier()))->confirmRobokassa($orderId);
header('Content-Type: text/plain; charset=UTF-8');
echo 'OK' . $invoiceId;
