<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Order;

use OrgBox\BaseShop\Catalog\ProductRepository;
use OrgBox\BaseShop\Delivery\DeliveryManager;
use OrgBox\BaseShop\Delivery\DeliveryRequest;
use OrgBox\BaseShop\Notification\OrderNotifierInterface;
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

    public function deliveryOptions(int $productId, int $quantity, string $city, string $postalCode, string $provider, string $pickupPointId = ''): array
    {
        $product = $this->requireProduct($productId);
        $request = $this->deliveryRequest($product, $quantity, $city, $postalCode, $pickupPointId);

        return array_map(static fn ($result): array => $result->toArray(), [$this->delivery->calculate($provider, $request)]);
    }

    public function pickupPoints(string $provider, string $city): array
    {
        return $this->delivery->pickupPoints($provider, $city);
    }

    public function place(array $input, string $status = 'NEW', bool $notify = true): array
    {
        $data = $this->validator->validate($input);
        $product = $this->requireProduct($data['product_id']);
        $request = $this->deliveryRequest($product, $data['quantity'], $data['city'], $data['postal_code'], $data['pickup_point_id']);
        $delivery = $this->delivery->calculate($data['delivery_provider'], $request);
        if (!$delivery->success) {
            throw new InvalidArgumentException($delivery->error ?: 'Не удалось рассчитать доставку.');
        }

        if (!isset($input['delivery_quote']) || !is_numeric($input['delivery_quote'])
            || abs((float) $input['delivery_quote'] - $delivery->price) > 0.005) {
            throw new InvalidArgumentException('Стоимость доставки изменилась. Рассчитайте доставку повторно.');
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
            'postal_code' => $delivery->pickupPointId !== '' ? $delivery->pickupPointPostalCode : $data['postal_code'],
            'address' => $delivery->pickupPointId !== ''
                ? $delivery->pickupPointAddress . ' (ПВЗ: ' . $delivery->pickupPointId . ')' : $data['address'],
            'delivery_provider' => $delivery->provider,
            'delivery_service' => $delivery->service,
            'delivery_price' => $delivery->price,
            'comment' => $data['comment'],
            'total' => $total,
            'created_at' => date('c'),
            'status' => $status,
            'payment_status' => $status,
        ];

        $orderId = $this->orders->save($order);
        $order['order_id'] = $orderId;
        $order['notification_sent'] = $notify && $this->notifier->notify($order) ? 'Y' : 'N';

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

    private function deliveryRequest(array $product, int $quantity, string $city, string $postalCode, string $pickupPointId = ''): DeliveryRequest
    {
        return new DeliveryRequest(
            trim($city),
            trim($postalCode),
            (float) $product['WEIGHT'] * max(1, $quantity),
            (float) $product['LENGTH'],
            (float) $product['WIDTH'],
            (float) $product['HEIGHT'] * max(1, $quantity),
            (float) $product['PRICE'] * max(1, $quantity),
            $pickupPointId
        );
    }
}
