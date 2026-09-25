<?php

declare(strict_types=1);

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

global $APPLICATION, $USER;
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ разрешён только администратору.');
}
if (!Loader::includeModule('orgbox.siteops')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    CAdminMessage::ShowMessage('Модуль orgBox: SiteOps не установлен.');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

const ORGBOX_SITEOPS_MODULE_ID = 'orgbox.siteops';
$notice = '';
$newSecret = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $keyId = trim((string) ($_POST['api_key_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{3,64}$/', $keyId)) {
        $notice = 'Идентификатор ключа: от 3 до 64 символов A–Z, 0–9, _ или -.';
    } else {
        $newSecret = trim((string) ($_POST['api_secret'] ?? ''));
        if ($newSecret === '') {
            $newSecret = bin2hex(random_bytes(32));
        }
        Option::set(ORGBOX_SITEOPS_MODULE_ID, 'api_key_id', $keyId);
        Option::set(ORGBOX_SITEOPS_MODULE_ID, 'api_secret', $newSecret);
        Option::set(ORGBOX_SITEOPS_MODULE_ID, 'api_enabled', isset($_POST['api_enabled']) ? 'Y' : 'N');
        $notice = 'Настройки сохранены. Секрет отображён только сейчас — сохраните его в защищённом хранилище.';
    }
}

$enabled = Option::get(ORGBOX_SITEOPS_MODULE_ID, 'api_enabled', 'N') === 'Y';
$keyId = Option::get(ORGBOX_SITEOPS_MODULE_ID, 'api_key_id', 'siteops');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
$APPLICATION->SetTitle('orgBox: SiteOps — настройки API');
if ($notice !== '') {
    CAdminMessage::ShowMessage(['MESSAGE' => $notice, 'TYPE' => $newSecret === '' ? 'ERROR' : 'OK']);
}
?>
<form method="post" action="<?=htmlspecialcharsbx($APPLICATION->GetCurPageParam('', [], false))?>">
    <?=bitrix_sessid_post()?>
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%">Включить API:</td>
            <td><label><input type="checkbox" name="api_enabled" value="Y"<?=$enabled ? ' checked' : ''?>> Разрешить подписанные запросы</label></td>
        </tr>
        <tr>
            <td>Идентификатор ключа:</td>
            <td><input type="text" name="api_key_id" value="<?=htmlspecialcharsbx($keyId)?>" size="40"></td>
        </tr>
        <tr>
            <td>Новый секрет:</td>
            <td><input type="password" name="api_secret" value="" size="70" autocomplete="new-password"><br><small>Оставьте пустым, чтобы сгенерировать новый секрет. Это отзовёт предыдущий ключ.</small></td>
        </tr>
        <?php if ($newSecret !== ''): ?>
        <tr>
            <td>Секрет для MCP-коннектора:</td>
            <td><input type="text" value="<?=htmlspecialcharsbx($newSecret)?>" size="70" readonly onclick="this.select()"></td>
        </tr>
        <?php endif; ?>
    </table>
    <input class="adm-btn-save" type="submit" value="Сохранить">
</form>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
