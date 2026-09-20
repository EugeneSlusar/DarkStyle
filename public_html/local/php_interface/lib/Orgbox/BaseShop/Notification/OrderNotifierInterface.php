<?php

declare(strict_types=1);

namespace Orgbox\BaseShop\Notification;

interface OrderNotifierInterface
{
    public function notify(array $order): bool;
}
