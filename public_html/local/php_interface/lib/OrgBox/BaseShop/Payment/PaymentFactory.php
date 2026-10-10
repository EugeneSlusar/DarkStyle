<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

use InvalidArgumentException;
use OrgBox\BaseShop\Config;

final class PaymentFactory
{
    public static function active(): PaymentProviderInterface
    {
        return self::byCode((string) Config::get('payment_provider', 'yookassa'));
    }
    public static function byCode(string $code): PaymentProviderInterface
    {
        return match ($code) {
            'yookassa' => new YooKassaProvider(),
            'robokassa' => new RobokassaProvider(),
            default => throw new InvalidArgumentException('Выбран неизвестный платёжный провайдер.'),
        };
    }
}
