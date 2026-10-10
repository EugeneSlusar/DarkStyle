<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use OrgBox\BaseShop\Config;
use OrgBox\BaseShop\Notification\EmailOrderNotifier;
use OrgBox\BaseShop\Notification\EmailCustomerPaidNotifier;
use OrgBox\BaseShop\Order\IblockOrderRepository;
use OrgBox\BaseShop\Payment\PaymentService;
use OrgBox\BaseShop\Payment\PaymentStatus;

$APPLICATION->SetTitle('Статус оплаты');
$orderId = max(0, (int) ($_GET['order'] ?? 0));
$status = PaymentStatus::WAITING;
if ($orderId > 0) {
    try {
        $status = (new PaymentService(new IblockOrderRepository(Config::getIblockId('orders')), new EmailOrderNotifier((string) Config::get('manager_email', '')), new EmailCustomerPaidNotifier()))->status($orderId);
    } catch (Throwable) {
        $status = PaymentStatus::WAITING;
    }
}
?>
<section class="section"><div class="wrap checkout-success"><span class="number">ОПЛАТА</span>
<?php if ($status === PaymentStatus::PAID): ?>
    <h1>Оплата подтверждена</h1><p>Заказ №<?= (int) $orderId ?> принят. Подтверждение отправлено на указанную почту.</p>
<?php elseif ($status === PaymentStatus::CANCELED): ?>
    <h1>Оплата отменена</h1><p>Заказ №<?= (int) $orderId ?> не оплачен. Вы можете оформить его заново.</p>
<?php else: ?>
    <h1>Проверяем оплату</h1><p>Статус заказа №<?= (int) $orderId ?> обновится после подтверждения платёжного сервиса.</p>
<?php endif; ?>
<a class="button cyan" href="/catalog/">ВЕРНУТЬСЯ В КАТАЛОГ →</a></div></section>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
