<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

use OrgBox\BaseShop\Config;
use RuntimeException;

final class RobokassaProvider implements PaymentProviderInterface
{
    public function code(): string { return 'robokassa'; }

    public function create(array $order): array
    {
        $login = (string) Config::get('robokassa_merchant_login', '');
        $password = (string) Config::get('robokassa_password1', '');
        if ($login === '' || $password === '') throw new RuntimeException('Не заполнены настройки Робокассы.');
        $id = (string) (int) $order['order_id'];
        $sum = number_format((float) $order['total'], 2, '.', '');
        $extra = 'shp_order_id=' . $id;
        $signature = md5($login . ':' . $sum . ':' . $id . ':' . $password . ':' . $extra);
        $query = ['MerchantLogin' => $login, 'OutSum' => $sum, 'InvId' => $id, 'Description' => 'Заказ №' . $id, 'SignatureValue' => $signature, 'shp_order_id' => $id];
        $returnUrl = trim((string) Config::get('payment_return_url', ''));
        if ($returnUrl !== '') $query['SuccessURL'] = $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'order=' . rawurlencode($id);
        if ((bool) Config::get('robokassa_test_mode', false)) $query['IsTest'] = '1';
        return ['payment_id' => $id, 'confirmation_url' => 'https://auth.robokassa.ru/Merchant/Index.aspx?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), 'status' => PaymentStatus::WAITING];
    }

    public function fetch(string $paymentId): array
    {
        return ['payment_id' => $paymentId, 'status' => PaymentStatus::WAITING];
    }

    public function isValidNotification(string $sum, string $invoiceId, string $signature, string $orderId): bool
    {
        $password = (string) Config::get('robokassa_password2', '');
        if ($password === '' || $invoiceId !== $orderId) return false;
        return hash_equals(strtoupper(md5($sum . ':' . $invoiceId . ':' . $password . ':shp_order_id=' . $orderId)), strtoupper($signature));
    }
}
