<?php

declare(strict_types=1);

namespace DarkStyle\Order;

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
        if ($data['city'] === '' || $data['address'] === '') {
            throw new InvalidArgumentException('Укажите город и адрес доставки.');
        }
        if (!preg_match('/^[0-9]{5,6}$/', $data['postal_code'])) {
            throw new InvalidArgumentException('Укажите корректный почтовый индекс.');
        }
        if (!in_array($data['delivery_provider'], ['cdek', 'russian_post'], true)) {
            throw new InvalidArgumentException('Выберите способ доставки.');
        }

        return $data;
    }

    private function text(mixed $value, int $maxLength): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $maxLength);
    }
}
