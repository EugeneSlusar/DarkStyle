<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$sections = $arResult['SECTIONS'] ?? [];
require __DIR__ . '/_sections.php';
