<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Payment;

interface PaymentProviderInterface
{
    public function code(): string;

    /** @return array{payment_id:string,confirmation_url:string,status:string} */
    public function create(array $order): array;

    /** @return array{payment_id:string,status:string,paid_at?:string} */
    public function fetch(string $paymentId): array;
}
