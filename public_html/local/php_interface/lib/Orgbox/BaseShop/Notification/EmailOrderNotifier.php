<?php

declare(strict_types=1);

namespace Orgbox\BaseShop\Notification;

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

        $eventName = \CEventType::GetList([
            'TYPE_ID' => 'ORGBOX_BASESHOP_NEW_ORDER',
            'LID' => defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru',
        ])->Fetch() ? 'ORGBOX_BASESHOP_NEW_ORDER' : 'DARKSTYLE_NEW_ORDER';
        $result = \CEvent::SendImmediate($eventName, SITE_ID, $fields);

        return in_array($result, ['Y', 'P'], true);
    }
}
