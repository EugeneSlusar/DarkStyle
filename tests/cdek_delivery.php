<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'OrgBox\\BaseShop\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/public_html/local/php_interface/lib/OrgBox/BaseShop/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use OrgBox\BaseShop\Delivery\CdekDeliveryProvider;
use OrgBox\BaseShop\Delivery\DeliveryRequest;

function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

$request = new DeliveryRequest('Самара', '443000', 1400.2, 80.2, 15, 30, 2580);
$calls = [];
$transport = static function (string $url, array $headers, string $body) use (&$calls): array {
    $calls[] = [$url, $headers, $body];
    if (str_ends_with($url, '/oauth/token')) {
        parse_str($body, $auth);
        check($auth['client_secret'] === 'secret&value', 'OAuth must encode credentials');
        return ['access_token' => 'test-token'];
    }
    check($headers['Authorization'] === 'Bearer test-token', 'Missing bearer token');
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    check($payload['tariff_code'] === 137, 'Wrong courier tariff');
    check($payload['from_location']['code'] === 123, 'Wrong origin');
    check($payload['to_location']['city'] === 'Самара' && $payload['to_location']['postal_code'] === '443000', 'Wrong route');
    check($payload['packages'][0] === ['weight' => 1401, 'length' => 81, 'width' => 15, 'height' => 30], 'Wrong package units or rounding');
    return ['total_sum' => 812.34, 'period_min' => 2, 'period_max' => 4];
};
$provider = new CdekDeliveryProvider('client', 'secret&value', 123, false, $transport);
$result = $provider->calculate($request);
check($result->success && $result->price === 812.34 && $result->estimatedDays === '2–4 дней', 'Wrong tariff result');
$provider->calculate($request);
check(count($calls) === 3, 'Token should be reused within request');
check(!(new CdekDeliveryProvider())->calculate($request)->success, 'Missing configuration must block quote');
check(!(new CdekDeliveryProvider('client', 'secret&value', 123, true, $transport))->calculate($request)->success, 'Sandbox must block orders');
$broken = new CdekDeliveryProvider('id', 'secret', 123, false, static function (): array { throw new RuntimeException('secret'); });
check(!$broken->calculate($request)->success && !str_contains($broken->calculate($request)->error, 'secret'), 'Transport error must not expose secrets');
foreach ([[], ['access_token' => ''], ['access_token' => 't', 'total_sum' => -1], ['access_token' => 't', 'total_sum' => 100, 'period_min' => 4, 'period_max' => 2]] as $response) {
    $invalid = new CdekDeliveryProvider('id', 'secret', 123, false, static fn (): array => $response);
    check(!$invalid->calculate($request)->success, 'Incomplete/invalid response must block quote');
}
$zero = new DeliveryRequest('Самара', '443000', 0, 80, 15, 15, 1290);
check(!$provider->calculate($zero)->success, 'Missing package must block quote');
echo "CDEK delivery tests passed\n";
