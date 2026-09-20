<?php

declare(strict_types=1);

namespace Orgbox\BaseShop\Order;

use Orgbox\BaseShop\Catalog\ProductRepository;
use Orgbox\BaseShop\Delivery\DeliveryManager;
use Orgbox\BaseShop\Delivery\DeliveryRequest;
use Orgbox\BaseShop\Notification\OrderNotifierInterface;
use InvalidArgumentException;

final class OrderService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly IblockOrderRepository $orders,
        private readonly DeliveryManager $delivery,
        private readonly OrderNotifierInterface $notifier,
        private readonly OrderDataValidator $validator
    ) {
    }

    public function deliveryOptions(int $productId, int $quantity, string $city, string $postalCode): array
    {
        $product = $this->requireProduct($productId);
        $request = $this->deliveryRequest($product, $quantity, $city, $postalCode);

        return array_map(static fn ($result): array => $result->toArray(), $this->delivery->calculateAll($request));
    }

    public function place(array $input): array
    {
        $data = $this->validator->validate($input);
        $product = $this->requireProduct($data['product_id']);
        $request = $this->deliveryRequest($product, $data['quantity'], $data['city'], $data['postal_code']);
        $delivery = $this->delivery->calculate($data['delivery_provider'], $request);
        if (!$delivery->success) {
            throw new InvalidArgumentException($delivery->error ?: 'Не удалось рассчитать доставку.');
        }

        $price = (float) $product['PRICE'];
        $total = $price * $data['quantity'] + $delivery->price;
        $order = [
            'product_id' => $product['ID'],
            'product_name' => $product['NAME'],
            'price' => $price,
            'quantity' => $data['quantity'],
            'customer_name' => $data['customer_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'address' => $data['address'],
            'delivery_provider' => $delivery->provider,
            'delivery_service' => $delivery->service,
            'delivery_price' => $delivery->price,
            'comment' => $data['comment'],
            'total' => $total,
            'created_at' => date('c'),
            'status' => 'NEW',
        ];

        $orderId = $this->orders->save($order);
        $order['order_id'] = $orderId;
        $order['notification_sent'] = $this->notifier->notify($order);

        return $order;
    }

    private function requireProduct(int $productId): array
    {
        $product = $this->products->getProductById($productId);
        if ($product === null || $product['PRICE'] <= 0) {
            throw new InvalidArgumentException('Товар недоступен для заказа.');
        }

        return $product;
    }

    private function deliveryRequest(array $product, int $quantity, string $city, string $postalCode): DeliveryRequest
    {
        return new DeliveryRequest(
            trim($city),
            trim($postalCode),
            max(100, (float) $product['WEIGHT']) * max(1, $quantity),
            max(10, (float) $product['LENGTH']),
            max(10, (float) $product['WIDTH']),
            max(2, (float) $product['HEIGHT'] * max(1, $quantity)),
            (float) $product['PRICE'] * max(1, $quantity)
        );
    }
}
