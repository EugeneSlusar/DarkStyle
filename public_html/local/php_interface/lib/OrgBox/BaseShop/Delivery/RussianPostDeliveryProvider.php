<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Delivery;

use Bitrix\Main\Web\HttpClient;
use Closure;
use RuntimeException;

/** Public Tariff API v2. Basic parcel to the recipient's post office, own packaging. */
final class RussianPostDeliveryProvider implements DeliveryProviderInterface
{
    private const OBJECT_CODE = 4030;
    private Closure $transport;

    public function __construct(private readonly string $originPostalCode = '', ?Closure $transport = null)
    {
        $this->transport = $transport ?? static function (string $url): array {
            $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 15, 'redirect' => false]);
            $http->setHeader('Accept', 'application/json');
            $response = $http->get($url);
            if ($response === false || $http->getStatus() < 200 || $http->getStatus() >= 300) {
                throw new RuntimeException('Russian Post request failed');
            }
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { throw new RuntimeException('Invalid Russian Post response'); }
            return $data;
        };
    }

    public function code(): string { return 'russian_post'; }

    public function calculate(DeliveryRequest $request): DeliveryResult
    {
        if (!preg_match('/^[1-9][0-9]{5}$/D', $this->originPostalCode)) {
            return $this->failure('Расчёт Почты России пока недоступен. Свяжитесь с менеджером.');
        }
        if (!preg_match('/^[1-9][0-9]{5}$/D', $request->postalCode)) {
            return $this->failure('Укажите шестизначный почтовый индекс получателя.');
        }
        foreach ([$request->weight, $request->length, $request->width, $request->height] as $value) {
            if (!is_finite($value) || $value <= 0) {
                return $this->failure('Не заданы корректные параметры упаковки товара. Свяжитесь с менеджером.');
            }
        }
        if ($request->weight > 10000 || max($request->length, $request->width, $request->height) > 60) {
            return $this->failure('Для этого тарифа допустимы посылки до 10 кг и сторона до 60 см.');
        }
        $face = [$request->length, $request->width, $request->height];
        rsort($face);
        if ($face[0] < 24 || $face[1] < 16) {
            return $this->failure('Адресная сторона упаковки Почты России должна быть не меньше 24 × 16 см. Уточните упаковку у менеджера.');
        }
        $size = [(int) ceil($request->length), (int) ceil($request->width), (int) ceil($request->height)];
        // This stage supports the basic service only; never silently quote an oversized parcel as basic.
        if ($request->weight > 10000 || max($size) > 60 || array_sum($size) > 120) {
            return $this->failure('Этот вариант Почты России поддерживает посылки до 10 кг, со стороной до 60 см и суммой сторон до 120 см. Выберите другую службу или свяжитесь с менеджером.');
        }
        try {
            $query = http_build_query([
                'json' => '', 'object' => self::OBJECT_CODE,
                'from' => (int) $this->originPostalCode, 'to' => (int) $request->postalCode,
                'weight' => (int) ceil($request->weight), 'size' => implode('x', $size),
                'closed' => 0, 'errorcode' => 1,
            ], '', '&', PHP_QUERY_RFC3986);
            $data = ($this->transport)('https://tariff.pochta.ru/v2/calculate/tariff/delivery?' . $query);
            // Errors can accompany HTTP 200 and even a partial successful calculation.
            if (!empty($data['errors']) || ($data['version_api'] ?? null) !== 2
                || ($data['id'] ?? null) !== self::OBJECT_CODE
                || ($data['from'] ?? null) !== (int) $this->originPostalCode
                || ($data['to'] ?? null) !== (int) $request->postalCode) {
                throw new RuntimeException('Wrong or failed Russian Post calculation');
            }
            $price = $data['paynds'] ?? null;
            $min = $data['delivery']['min'] ?? null;
            $max = $data['delivery']['max'] ?? null;
            if (!is_int($price) || $price <= 0 || !is_int($min) || !is_int($max) || $min < 0 || $max < $min) {
                throw new RuntimeException('Incomplete Russian Post tariff');
            }
            return new DeliveryResult($this->code(), 'Почта России: в отделение', $price / 100,
                $min . '–' . $max . ' дней', 'Базовый тариф с НДС до отделения по индексу получателя. Без объявленной ценности и наложенного платежа.');
        } catch (\Throwable) {
            return $this->failure('Не удалось получить тариф Почты России. Проверьте индекс или повторите позже.');
        }
    }

    private function failure(string $message): DeliveryResult
    {
        return new DeliveryResult($this->code(), 'Почта России: в отделение', 0, '', '', false, $message);
    }
}
