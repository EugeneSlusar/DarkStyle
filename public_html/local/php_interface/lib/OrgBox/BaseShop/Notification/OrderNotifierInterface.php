<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Notification;

interface OrderNotifierInterface
{
    public function notify(array $order): bool;
}
