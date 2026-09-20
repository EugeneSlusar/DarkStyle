<?php

$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_ID' => ['PARENT' => 'BASE', 'NAME' => 'ID инфоблока товаров', 'TYPE' => 'STRING'],
        'MODE' => ['PARENT' => 'BASE', 'NAME' => 'Режим', 'TYPE' => 'LIST', 'VALUES' => ['catalog' => 'Каталог', 'home' => 'Главная']],
        'LIMIT' => ['PARENT' => 'BASE', 'NAME' => 'Лимит', 'TYPE' => 'STRING', 'DEFAULT' => '8'],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ],
];
