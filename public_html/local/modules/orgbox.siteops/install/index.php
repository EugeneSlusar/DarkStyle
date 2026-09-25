<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\ModuleManager;

final class orgbox_siteops extends CModule
{
    public $MODULE_ID = 'orgbox.siteops';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'orgBox: SiteOps';
    public $MODULE_DESCRIPTION = 'Защищённый служебный API для управления данными сайта.';
    public $PARTNER_NAME = 'orgBox';

    public function __construct()
    {
        $arModuleVersion = [];
        require __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);
        Option::add($this->MODULE_ID, 'api_enabled', 'N');
        Option::add($this->MODULE_ID, 'api_key_id', 'siteops');
        Option::add($this->MODULE_ID, 'api_secret', '');
    }

    public function DoUninstall(): void
    {
        DeleteDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
