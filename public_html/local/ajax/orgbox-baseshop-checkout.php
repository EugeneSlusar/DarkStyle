<?php

declare(strict_types=1);

define('PUBLIC_AJAX_MODE', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Context;
use OrgBox\BaseShop\Catalog\ProductRepository;
use OrgBox\BaseShop\Config;
use OrgBox\BaseShop\Delivery\CdekDeliveryProvider;
use OrgBox\BaseShop\Delivery\DeliveryManager;
use OrgBox\BaseShop\Delivery\RussianPostDeliveryProvider;
use OrgBox\BaseShop\Notification\EmailOrderNotifier;
use OrgBox\BaseShop\Order\IblockOrderRepository;
use OrgBox\BaseShop\Order\OrderDataValidator;
use OrgBox\BaseShop\Order\OrderService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    die();
};

try {
    $request = Context::getCurrent()->getRequest();
    if (!$request->isPost() && (string) $request->getQuery('action') === 'session') {
        $respond(['success' => true, 'sessid' => bitrix_sessid()]);
    }
    if (!$request->isPost() || !check_bitrix_sessid()) {
        $respond(['success' => false, 'error' => 'Сессия истекла. Обновите страницу.'], 403);
    }
    if (trim((string) $request->getPost('company')) !== '') {
        $respond(['success' => false, 'error' => 'Запрос отклонён.'], 400);
    }

    $service = new OrderService(
        new ProductRepository((int) Config::get('products_iblock_id', 0)),
        new IblockOrderRepository((int) Config::get('orders_iblock_id', 0)),
        new DeliveryManager(new CdekDeliveryProvider(), new RussianPostDeliveryProvider()),
        new EmailOrderNotifier((string) Config::get('manager_email', '')),
        new OrderDataValidator()
    );

    $action = (string) $request->getPost('action');
    if ($action === 'delivery') {
        $city = mb_substr(trim(strip_tags((string) $request->getPost('city'))), 0, 100);
        $postalCode = trim((string) $request->getPost('postal_code'));
        if ($city === '' || !preg_match('/^[0-9]{5,6}$/', $postalCode)) {
            throw new InvalidArgumentException('Укажите город и корректный почтовый индекс.');
        }
        $options = $service->deliveryOptions(
            (int) $request->getPost('product_id'),
            min(10, max(1, (int) $request->getPost('quantity'))),
            $city,
            $postalCode
        );
        $respond(['success' => true, 'options' => $options]);
    }

    if ($action === 'order') {
        if (isset($_SESSION['ORGBOX_BASESHOP_LAST_ORDER']) && time() - (int) $_SESSION['ORGBOX_BASESHOP_LAST_ORDER'] < 10) {
            throw new InvalidArgumentException('Заказ уже отправлен. Подождите несколько секунд.');
        }
        $order = $service->place($request->getPostList()->toArray());
        $_SESSION['ORGBOX_BASESHOP_LAST_ORDER'] = time();
        $respond(['success' => true, 'orderId' => $order['order_id']]);
    }

    throw new InvalidArgumentException('Неизвестное действие.');
} catch (InvalidArgumentException $exception) {
    $respond(['success' => false, 'error' => $exception->getMessage()], 422);
} catch (Throwable) {
    $respond(['success' => false, 'error' => 'Не удалось обработать запрос. Попробуйте позже.'], 500);
}
