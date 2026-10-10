<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

final class PaymentStatus
{
    public const WAITING = 'WAITING_PAYMENT';
    public const PAID = 'PAID';
    public const CANCELED = 'CANCELED';
    public const FAILED = 'PAYMENT_FAILED';
}
