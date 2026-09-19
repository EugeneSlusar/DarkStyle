<?php

declare(strict_types=1);

namespace DarkStyle\Notification;

interface OrderNotifierInterface
{
    public function notify(array $order): bool;
}
