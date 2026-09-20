<?php

declare(strict_types=1);

namespace OrgBox\BaseShop\Notification;

final class EmailOrderNotifier implements OrderNotifierInterface
{
    public function __construct(private readonly string $recipient)
    {
    }

    public function notify(array $order): bool
    {
        if ($this->recipient === '') {
            return false;
        }

        $fields = [];
        foreach ($order as $code => $value) {
            $fields[strtoupper((string) $code)] = (string) $value;
        }
        $fields['EMAIL_TO'] = $this->recipient;

        $result = \CEvent::SendImmediate('ORGBOX_BASESHOP_NEW_ORDER', SITE_ID, $fields);

        return in_array($result, ['Y', 'P'], true);
    }
}
