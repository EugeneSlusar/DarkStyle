<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

use OrgBox\BaseShop\Notification\OrderNotifierInterface;
use OrgBox\BaseShop\Notification\EmailCustomerPaidNotifier;
use OrgBox\BaseShop\Order\IblockOrderRepository;

final class PaymentService
{
    public function __construct(
        private readonly IblockOrderRepository $orders,
        private readonly OrderNotifierInterface $managerNotifier,
        private readonly ?EmailCustomerPaidNotifier $customerNotifier = null
    )
    {
    }

    /** @return array{payment_id:string,confirmation_url:string,status:string} */
    public function start(array $order): array
    {
        $provider = PaymentFactory::active();
        $payment = $provider->create($order);
        $this->orders->update((int) $order['order_id'], [
            'payment_provider' => $provider->code(), 'payment_id' => $payment['payment_id'],
            'payment_status' => $payment['status'], 'status' => $payment['status'],
        ]);
        return $payment;
    }

    public function refreshYooKassa(int $orderId): string
    {
        $order = $this->orders->find($orderId);
        if ($order === null || ($order['payment_provider'] ?? '') !== 'yookassa') return PaymentStatus::FAILED;
        $payment = (new YooKassaProvider())->fetch((string) $order['payment_id']);
        return $this->apply($order, $payment);
    }

    public function confirmRobokassa(int $orderId): string
    {
        $order = $this->orders->find($orderId);
        if ($order === null || ($order['payment_provider'] ?? '') !== 'robokassa') return PaymentStatus::FAILED;
        return $this->apply($order, ['status' => PaymentStatus::PAID, 'payment_id' => (string) $order['payment_id'], 'paid_at' => date('c')]);
    }

    public function status(int $orderId): string
    {
        $order = $this->orders->find($orderId);
        if ($order === null) return PaymentStatus::FAILED;
        if (($order['payment_provider'] ?? '') === 'yookassa') return $this->refreshYooKassa($orderId);
        return (string) ($order['payment_status'] ?? PaymentStatus::WAITING);
    }

    private function apply(array $order, array $payment): string
    {
        $status = (string) $payment['status'];
        $id = (int) $order['order_id'];
        if (($order['payment_status'] ?? '') === PaymentStatus::PAID) return PaymentStatus::PAID;
        $changes = ['payment_status' => $status, 'status' => $status];
        if (!empty($payment['paid_at'])) $changes['paid_at'] = $payment['paid_at'];
        if ($status === PaymentStatus::PAID && ($order['notification_sent'] ?? '') !== 'Y') {
            $order = array_merge($order, $changes);
            $changes['notification_sent'] = $this->managerNotifier->notify($order) ? 'Y' : 'N';
        }
        if ($status === PaymentStatus::PAID && ($order['customer_notification_sent'] ?? '') !== 'Y') {
            $changes['customer_notification_sent'] = $this->customerNotifier?->notify(array_merge($order, $changes)) ? 'Y' : 'N';
        }
        $this->orders->update($id, $changes);
        return $status;
    }
}
