<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

use Bitrix\Main\Web\HttpClient;
use Closure;
use RuntimeException;

final class CdekDeliveryProvider implements DeliveryProviderInterface
{
    private Closure $transport;
    private ?string $token = null;

    public function __construct(
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly int $originCode = 0,
        private readonly bool $sandbox = false,
        ?Closure $transport = null
    ) {
        $this->transport = $transport ?? static function (string $url, array $headers, string $body): array {
            $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 15, 'redirect' => false]);
            foreach ($headers as $name => $value) {
                $http->setHeader($name, $value);
            }
            $response = $http->post($url, $body);
            if ($response === false || $http->getStatus() < 200 || $http->getStatus() >= 300) {
                throw new RuntimeException('CDEK request failed');
            }
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !empty($data['errors'])) {
                throw new RuntimeException('Invalid CDEK response');
            }
            return $data;
        };
    }

    public function code(): string
    {
        return 'cdek';
    }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->originCode <= 0) {
            return $this->failure('Расчёт СДЭК пока недоступен. Свяжитесь с менеджером.');
        }
        foreach ([$request->weight, $request->length, $request->width, $request->height] as $value) {
            if (!is_finite($value) || $value <= 0) {
                return $this->failure('Не заданы параметры упаковки товара. Свяжитесь с менеджером.');
            }
        }
        try {
            $baseUrl = $this->sandbox ? 'https://api.edu.cdek.ru/v2' : 'https://api.cdek.ru/v2';
            if ($this->token === null) {
                $auth = ($this->transport)($baseUrl . '/oauth/token',
                    ['Content-Type' => 'application/x-www-form-urlencoded'],
                    http_build_query(['grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret]));
                if (!is_string($auth['access_token'] ?? null) || $auth['access_token'] === '') {
                    throw new RuntimeException('Missing CDEK token');
                }
                $this->token = $auth['access_token'];
            }
            // 137: посылка, склад отправителя — дверь получателя.
            $data = ($this->transport)($baseUrl . '/calculator/tariff',
                ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->token],
                json_encode([
                    'type' => 1, 'currency' => 1, 'tariff_code' => 137,
                    'from_location' => ['code' => $this->originCode],
                    'to_location' => ['city' => $request->destination, 'postal_code' => $request->postalCode, 'country_code' => 'RU'],
                    'packages' => [[
                        'weight' => (int) ceil($request->weight),
                        'length' => (int) ceil($request->length),
                        'width' => (int) ceil($request->width),
                        'height' => (int) ceil($request->height),
                    ]],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $price = $data['total_sum'] ?? null;
            $min = $data['period_min'] ?? null;
            $max = $data['period_max'] ?? null;
            if (!empty($data['errors']) || !is_numeric($price) || !is_finite((float) $price) || (float) $price <= 0
                || !is_numeric($min) || !is_numeric($max) || (int) $min < 0 || (int) $max < (int) $min) {
                throw new RuntimeException('Incomplete CDEK tariff');
            }
            if ($this->sandbox) {
                return $this->failure('СДЭК работает в тестовом режиме. Оформление временно недоступно.');
            }
            return new DeliveryResult($this->code(), 'СДЭК: курьер до двери', round((float) $price, 2),
                (int) $min . '–' . (int) $max . ' дней', 'Тариф СДЭК по маршруту и параметрам упаковки.');
        } catch (\Throwable) {
            $this->token = null;
            return $this->failure('Не удалось получить тариф СДЭК. Проверьте город и индекс или повторите позже.');
        }
    }

    private function failure(string $message): DeliveryResult
    {
        return new DeliveryResult($this->code(), 'СДЭК: курьер до двери', 0, '', '', false, $message);
    }
}
