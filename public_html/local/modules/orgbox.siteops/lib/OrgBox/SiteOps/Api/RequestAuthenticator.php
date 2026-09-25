<?php

declare(strict_types=1);

namespace OrgBox\SiteOps\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;
use RuntimeException;

final class RequestAuthenticator
{
    private const MODULE_ID = 'orgbox.siteops';
    private const MAX_CLOCK_SKEW = 300;

    public function assertValid(string $method, string $uri, string $body, array $server): void
    {
        if (Option::get(self::MODULE_ID, 'api_enabled', 'N') !== 'Y') {
            throw new RuntimeException('API отключён.');
        }

        $keyId = (string) ($server['HTTP_X_ORGBOX_KEY'] ?? '');
        $timestamp = (int) ($server['HTTP_X_ORGBOX_TIMESTAMP'] ?? 0);
        $nonce = (string) ($server['HTTP_X_ORGBOX_NONCE'] ?? '');
        $signature = (string) ($server['HTTP_X_ORGBOX_SIGNATURE'] ?? '');
        $expectedKeyId = Option::get(self::MODULE_ID, 'api_key_id', 'siteops');
        $secret = Option::get(self::MODULE_ID, 'api_secret', '');

        if ($secret === '' || !hash_equals($expectedKeyId, $keyId)) {
            throw new RuntimeException('Неверный ключ API.');
        }
        if ($timestamp < time() - self::MAX_CLOCK_SKEW || $timestamp > time() + self::MAX_CLOCK_SKEW) {
            throw new RuntimeException('Недопустимое время запроса.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)) {
            throw new RuntimeException('Недопустимый одноразовый идентификатор.');
        }

        $payload = strtoupper($method) . "\n" . $uri . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expectedSignature, strtolower($signature))) {
            throw new RuntimeException('Неверная подпись запроса.');
        }

        $cache = Cache::createInstance();
        $cacheId = 'orgbox.siteops.nonce.' . hash('sha256', $keyId . ':' . $nonce);
        if ($cache->initCache(self::MAX_CLOCK_SKEW, $cacheId, '/orgbox/siteops/nonces')) {
            throw new RuntimeException('Повторный запрос отклонён.');
        }
        if ($cache->startDataCache()) {
            $cache->endDataCache(['used_at' => time()]);
        }
    }
}
