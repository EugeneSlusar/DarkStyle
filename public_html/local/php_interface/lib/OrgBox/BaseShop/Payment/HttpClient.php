<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

use RuntimeException;

final class HttpClient
{
    public static function request(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('На сервере не доступно расширение cURL для онлайн-оплаты.');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Не удалось подготовить запрос к платёжному сервису.');
        }
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Платёжный сервис временно недоступен' . ($error !== '' ? ': ' . $error : '.') );
        }
        try {
            return (array) json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Платёжный сервис вернул некорректный ответ.');
        }
    }
}
