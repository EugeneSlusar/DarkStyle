<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

use OrgBox\BaseShop\Config;
use RuntimeException;

final class YooKassaProvider implements PaymentProviderInterface
{
    public function code(): string { return 'yookassa'; }

    public function create(array $order): array
    {
        $shopId = (string) Config::get('yookassa_shop_id', '');
        $secret = (string) Config::get('yookassa_secret_key', '');
        $returnUrl = (string) Config::get('payment_return_url', '');
        if ($shopId === '' || $secret === '' || $returnUrl === '') {
            throw new RuntimeException('Не заполнены настройки ЮKassa: магазин, секретный ключ или URL возврата.');
        }
        $response = HttpClient::request('POST', 'https://api.yookassa.ru/v3/payments', [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($shopId . ':' . $secret),
            'Idempotence-Key: orgbox-order-' . (int) $order['order_id'],
        ], [
            'amount' => ['value' => number_format((float) $order['total'], 2, '.', ''), 'currency' => 'RUB'],
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'order=' . (int) $order['order_id']],
            'description' => 'Заказ №' . (int) $order['order_id'] . ' — ' . (string) $order['product_name'],
            'metadata' => ['order_id' => (string) (int) $order['order_id']],
        ]);
        $id = (string) ($response['id'] ?? '');
        $url = (string) ($response['confirmation']['confirmation_url'] ?? '');
        if ($id === '' || $url === '') throw new RuntimeException('ЮKassa не вернула ссылку на оплату.');
        return ['payment_id' => $id, 'confirmation_url' => $url, 'status' => PaymentStatus::WAITING];
    }

    public function fetch(string $paymentId): array
    {
        $shopId = (string) Config::get('yookassa_shop_id', '');
        $secret = (string) Config::get('yookassa_secret_key', '');
        if ($shopId === '' || $secret === '') throw new RuntimeException('Не заполнены настройки ЮKassa.');
        $response = HttpClient::request('GET', 'https://api.yookassa.ru/v3/payments/' . rawurlencode($paymentId), [
            'Authorization: Basic ' . base64_encode($shopId . ':' . $secret),
        ]);
        $status = match ((string) ($response['status'] ?? '')) {
            'succeeded' => PaymentStatus::PAID,
            'canceled' => PaymentStatus::CANCELED,
            default => PaymentStatus::WAITING,
        };
        return ['payment_id' => (string) ($response['id'] ?? $paymentId), 'status' => $status, 'paid_at' => (string) ($response['captured_at'] ?? '')];
    }
}
