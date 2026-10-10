<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'OrgBox\\BaseShop\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
use OrgBox\BaseShop\Delivery\RussianPostDeliveryProvider;
use OrgBox\BaseShop\Delivery\DeliveryRequest;
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$calls = [];
$reply = ['version_api' => 2, 'id' => 4030, 'from' => 445027, 'to' => 443000,
    'paynds' => 81234, 'delivery' => ['min' => 2, 'max' => 4]];
$transport = static function (string $url) use (&$calls, &$reply): array {
    check(str_starts_with($url, 'https://tariff.pochta.ru/v2/calculate/tariff/delivery?'), 'Wrong API');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $calls[] = $query;
    return $reply;
};
$request = new DeliveryRequest('Самара', '443000', 1400.2, 24.2, 16, 10, 2580);
$provider = new RussianPostDeliveryProvider('445027', $transport);
$result = $provider->calculate($request);
check($result->success && $result->price === 812.34 && $result->estimatedDays === '2–4 дней', 'Wrong tariff units/term');
check($calls[0] === ['json' => '', 'object' => '4030', 'from' => '445027', 'to' => '443000', 'weight' => '1401', 'size' => '25x16x10', 'closed' => '0', 'errorcode' => '1'], 'Wrong request');
$reply['from'] = 445028;
check((new RussianPostDeliveryProvider('445028', $transport))->calculate($request)->success, 'Origin must be configurable');
check(end($calls)['from'] === '445028', 'Configured origin must reach API');
$reply['from'] = 445027;
$valid = $reply;
foreach ([[], ['errors' => [['msg' => 'bad index']]], ['paynds' => null], ['paynds' => '81234'], ['paynds' => 0], ['delivery' => ['min' => 5, 'max' => 2]], ['id' => 4020], ['from' => 101000], ['to' => 190005], ['version_api' => 1]] as $change) {
    $reply = $change === [] ? [] : array_replace($valid, $change);
    check(!$provider->calculate($request)->success, 'Incomplete or mismatched response must fail');
}
$before = count($calls);
foreach ([new RussianPostDeliveryProvider('', $transport), new RussianPostDeliveryProvider('44502', $transport)] as $bad) {
    check(!$bad->calculate($request)->success, 'Invalid origin must block');
}
foreach ([['44300', 1000, 24,16,10], ['443000', NAN,24,16,10], ['443000',10001,24,16,10], ['443000',1000,INF,16,10], ['443000',1000,1e30,16,10], ['443000',1000,60,60,1], ['443000',1000,23,16,10], ['443000',1000,24,15,10]] as $args) {
    check(!$provider->calculate(new DeliveryRequest('Самара', ...[...$args, 1000]))->success, 'Invalid package/index must block');
}
check(count($calls) === $before, 'Invalid request must not reach transport');
$broken = new RussianPostDeliveryProvider('445027', static function (): array { throw new RuntimeException('internal-detail'); });
check(!$broken->calculate($request)->success && !str_contains($broken->calculate($request)->error, 'internal-detail'), 'No transport details in user error');
echo "Russian Post delivery tests passed\n";
