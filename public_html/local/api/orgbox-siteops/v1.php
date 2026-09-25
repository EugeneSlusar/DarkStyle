<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use OrgBox\SiteOps\Api\Dispatcher;
use OrgBox\SiteOps\Api\RequestAuthenticator;

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Допускаются только POST-запросы.');
    }
    if (!Loader::includeModule('orgbox.siteops')) {
        throw new RuntimeException('Модуль orgBox: SiteOps не установлен.');
    }

    $body = (string) file_get_contents('php://input');
    (new RequestAuthenticator())->assertValid('POST', '/local/api/orgbox-siteops/v1.php', $body, $_SERVER);
    $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $result = (new Dispatcher())->handle($request);
    echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
