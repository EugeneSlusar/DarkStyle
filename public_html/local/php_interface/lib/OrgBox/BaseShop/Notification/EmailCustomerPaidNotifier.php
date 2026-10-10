<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Notification;

final class EmailCustomerPaidNotifier
{
    public function notify(array $order): bool
    {
        $email = trim((string) ($order['email'] ?? ''));
        if ($email === '') return false;

        $fields = [];
        foreach ($order as $key => $value) {
            $fields[strtoupper((string) $key)] = is_scalar($value) ? (string) $value : '';
        }
        $fields['EMAIL_TO'] = $email;
        return \CEvent::SendImmediate('ORGBOX_BASESHOP_PAID_ORDER', SITE_ID, $fields) > 0;
    }
}
