<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Order;

use InvalidArgumentException;

final class OrderDataValidator
{
    public function validate(array $input): array
    {
        $data = [
            'product_id' => (int) ($input['product_id'] ?? 0),
            'quantity' => min(10, max(1, (int) ($input['quantity'] ?? 1))),
            'customer_name' => $this->text($input['customer_name'] ?? '', 100),
            'phone' => $this->text($input['phone'] ?? '', 30),
            'email' => $this->text($input['email'] ?? '', 150),
            'city' => $this->text($input['city'] ?? '', 100),
            'postal_code' => $this->text($input['postal_code'] ?? '', 12),
            'address' => $this->text($input['address'] ?? '', 250),
            'pickup_point_id' => $this->text($input['pickup_point_id'] ?? '', 100),
            'delivery_provider' => $this->text($input['delivery_provider'] ?? '', 30),
            'comment' => $this->text($input['comment'] ?? '', 1000),
        ];

        if ($data['product_id'] <= 0) {
            throw new InvalidArgumentException('Товар не выбран.');
        }
        if (mb_strlen($data['customer_name']) < 2) {
            throw new InvalidArgumentException('Укажите имя получателя.');
        }
        if (strlen(preg_replace('/\D+/', '', $data['phone'])) < 10) {
            throw new InvalidArgumentException('Укажите корректный телефон.');
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Укажите корректный email.');
        }
        if ($data['city'] === '' || (!in_array($data['delivery_provider'], ['yandex', 'cdek_pickup'], true) && $data['address'] === '')) {
            throw new InvalidArgumentException('Укажите город и адрес доставки.');
        }
        if (!in_array($data['delivery_provider'], ['yandex', 'cdek_pickup'], true) && !preg_match('/^[0-9]{5,6}$/', $data['postal_code'])) {
            throw new InvalidArgumentException('Укажите корректный почтовый индекс.');
        }
        if ($data['delivery_provider'] === 'russian_post' && !preg_match('/^[1-9][0-9]{5}$/D', $data['postal_code'])) {
            throw new InvalidArgumentException('Укажите шестизначный почтовый индекс получателя.');
        }
        if (!in_array($data['delivery_provider'], ['cdek', 'cdek_pickup', 'russian_post', 'yandex'], true)) {
            throw new InvalidArgumentException('Выберите способ доставки.');
        }

        if (in_array($data['delivery_provider'], ['yandex', 'cdek_pickup'], true) && $data['pickup_point_id'] === '') {
            throw new InvalidArgumentException($data['delivery_provider'] === 'yandex' ? 'Выберите ПВЗ Яндекс Доставки.' : 'Выберите ПВЗ СДЭК.');
        }

        return $data;
    }

    private function text(mixed $value, int $maxLength): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $maxLength);
    }
}
