<?php

declare(strict_types=1);

use OrgBox\BaseShop\Admin\ShopSettings;
use OrgBox\BaseShop\Config;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
global $APPLICATION, $USER;
if (!is_object($USER) || !$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ разрешён только администратору.');
    exit;
}
// Query string selects the section; POST cannot save fields from another section.
$section = in_array($_GET['section'] ?? '', ['delivery', 'payment'], true) ? (string) $_GET['section'] : 'general';
$APPLICATION->SetTitle($section === 'delivery' ? 'orgBox: настройки доставки' : ($section === 'payment' ? 'orgBox: настройки оплаты' : 'orgBox: настройки'));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_bitrix_sessid()) {
        $error = 'Сессия истекла. Обновите страницу и повторите сохранение.';
    } else {
        try {
            ShopSettings::save($_POST, $section);
            LocalRedirect('/local/admin/orgbox_baseshop_settings.php?lang=' . urlencode(LANGUAGE_ID) . '&section=' . $section . '&saved=Y');
            exit;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable) {
            $error = 'Не удалось сохранить настройки. Повторите позже.';
        }
    }
}
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') { CAdminMessage::ShowMessage($error); }
elseif (($_GET['saved'] ?? '') === 'Y') { CAdminMessage::ShowMessage(['MESSAGE' => 'Настройки сохранены.', 'TYPE' => 'OK']); }
?>
<form method="post" action="/local/admin/orgbox_baseshop_settings.php?lang=<?=htmlspecialcharsbx(LANGUAGE_ID)?>&amp;section=<?=$section?>" autocomplete="off">
    <?=bitrix_sessid_post()?>
    <p>Здесь настраиваются магазин и доставка. Пустое поле ключа сохраняет прежний ключ; для удаления отметьте «Удалить». Значения из переменных окружения меняются на сервере.</p>
    <?php foreach (ShopSettings::fields($section) as $group => $fields): ?>
        <h2><?=htmlspecialcharsbx($group)?></h2>
        <?php if ($group === 'Почта России'): ?><p>Для расчёта ключи не нужны. Отправления и накладные создаются отдельно.</p><?php endif; ?>
        <?php if (in_array($group, ['СДЭК', 'Яндекс Доставка'], true)): ?><p>Тестовый режим блокирует оформление заказа по тестовой цене.</p><?php endif; ?>
        <table class="adm-detail-content-table edit-table">
        <?php foreach ($fields as $key => [$label, $type, $default]):
            $locked = ShopSettings::locked($key);
            $value = Config::get($key, $default);
            // Never output a stored API credential, even in HTML attributes.
            $display = $type === 'secret' ? '' : $value;
            if ($error !== '' && !$locked && $type !== 'secret') {
                $posted = $_POST[$key] ?? ($type === 'bool' ? 'N' : $display);
                if (is_scalar($posted)) { $display = $type === 'bool' ? $posted === 'Y' : (string) $posted; }
            }
        ?>
            <tr><td width="40%"><label for="<?=htmlspecialcharsbx($key)?>"><?=htmlspecialcharsbx($label)?></label></td><td>
            <?php if ($type === 'bool'): ?>
                <input id="<?=$key?>" name="<?=$key?>" type="checkbox" value="Y"<?=$display ? ' checked' : ''?><?=$locked ? ' disabled' : ''?>>
            <?php elseif ($type === 'theme'): ?>
                <select id="<?=$key?>" name="<?=$key?>"<?=$locked ? ' disabled' : ''?>><option value="dark">Тёмная</option></select>
            <?php elseif ($type === 'payment_provider'): ?>
                <select id="<?=$key?>" name="<?=$key?>"<?=$locked ? ' disabled' : ''?>><option value="yookassa"<?=$display === 'yookassa' ? ' selected' : ''?>>ЮKassa</option><option value="robokassa"<?=$display === 'robokassa' ? ' selected' : ''?>>Робокасса</option></select>
            <?php else: ?>
                <input id="<?=$key?>" name="<?=$key?>" type="<?=$type === 'secret' ? 'password' : ($type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text'))?>" value="<?=htmlspecialcharsbx((string) $display)?>" size="50"<?=$type === 'number' ? ' min="0" max="999999999"' : ''?><?=$type === 'postcode' ? ' maxlength="6" pattern="[1-9][0-9]{5}"' : ''?><?=$type === 'secret' ? ' autocomplete="new-password"' : ''?><?=$locked ? ' disabled' : ''?>>
                <?php if ($type === 'secret'): ?>
                    <small><?=$value !== '' ? 'Ключ задан' : 'Ключ не задан'?></small>
                    <?php if (!$locked): ?><label><input type="checkbox" name="clear_<?=$key?>" value="Y"> Удалить</label><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($locked): ?><br><small>Задано на сервере: <?=htmlspecialcharsbx(ShopSettings::environmentKey($key))?></small><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
    <input class="adm-btn-save" type="submit" value="Сохранить">
</form>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
