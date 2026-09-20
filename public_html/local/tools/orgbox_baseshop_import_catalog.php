<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Orgbox\BaseShop\Catalog\CatalogImportService;
use Orgbox\BaseShop\Config;

global $USER;
if (!$USER->IsAdmin()) {
    \CHTTP::SetStatus('403 Forbidden');
    die('Доступ разрешён только администратору.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'session') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'success' => true,
        'sessid' => bitrix_sessid(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

@set_time_limit(0);
$sourceFile = $_SERVER['DOCUMENT_ROOT'] . '/local/js/catalog-data.js';
$imagesRoot = $_SERVER['DOCUMENT_ROOT'] . '/local/assets/catalog-products';
$result = null;
$error = '';
$sourceCount = 0;

try {
    $importer = new CatalogImportService((int) Config::get('products_iblock_id', 0));
    $sourceCount = count($importer->readSource($sourceFile));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!check_bitrix_sessid()) {
            throw new RuntimeException('Сессия истекла. Обновите страницу.');
        }

        $defaults = [
            'weight' => max(1, (float) ($_POST['weight'] ?? 700)),
            'length' => max(1, (float) ($_POST['length'] ?? 80)),
            'width' => max(1, (float) ($_POST['width'] ?? 15)),
            'height' => max(1, (float) ($_POST['height'] ?? 15)),
        ];
        $result = $importer->import(
            $sourceFile,
            $imagesRoot,
            $defaults,
            !empty($_POST['replace_images']),
            !empty($_POST['delete_demo'])
        );
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php';
?>
<div style="max-width:850px;margin:40px auto;padding:32px;background:#111;color:#fff;font:15px Arial;line-height:1.5">
    <h1>Импорт каталога orgBox: BaseShop</h1>
    <p>Найдено товаров в исходном наборе: <strong><?= (int) $sourceCount ?></strong>.</p>
    <p>Импорт можно запускать повторно: существующие товары определяются по артикулу и обновляются, дубликаты не создаются.</p>

    <?php if ($error !== ''): ?><p style="padding:12px;background:#431b1b;color:#ffb3b3"><?=htmlspecialcharsbx($error)?></p><?php endif; ?>
    <?php if (is_array($result)): ?>
        <div style="padding:16px;background:#12352e;color:#b9ffe8">
            <strong>Импорт завершён.</strong><br>
            Создано: <?= (int) $result['created'] ?>;
            обновлено: <?= (int) $result['updated'] ?>;
            ошибок: <?= (int) $result['failed'] ?>;
            удалено демотоваров: <?= (int) $result['deleted_demo'] ?>.
        </div>
        <?php if ($result['errors']): ?><ul style="color:#ffb3b3"><?php foreach ($result['errors'] as $message): ?><li><?=htmlspecialcharsbx($message)?></li><?php endforeach; ?></ul><?php endif; ?>
        <p><a href="/catalog/" target="_blank" style="color:#00f2ff">Открыть каталог →</a></p>
    <?php endif; ?>

    <form id="orgbox-baseshop-catalog-import" method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:28px">
        <?php bitrix_sessid_post(); ?>
        <label>Вес по умолчанию, г<input name="weight" type="number" min="1" value="700" required style="display:block;width:100%;padding:10px"></label>
        <label>Длина упаковки, см<input name="length" type="number" min="1" value="80" required style="display:block;width:100%;padding:10px"></label>
        <label>Ширина упаковки, см<input name="width" type="number" min="1" value="15" required style="display:block;width:100%;padding:10px"></label>
        <label>Высота упаковки, см<input name="height" type="number" min="1" value="15" required style="display:block;width:100%;padding:10px"></label>
        <label style="grid-column:1/-1"><input name="delete_demo" type="checkbox" value="1" checked> Удалить демонстрационный товар</label>
        <label style="grid-column:1/-1"><input name="replace_images" type="checkbox" value="1"> Повторно загрузить изображения существующих товаров</label>
        <p style="grid-column:1/-1;color:#aaa">Размеры применяются ко всем импортируемым товарам. После импорта укажите точные характеристики у товаров, если они отличаются. Повторную загрузку изображений включайте только при их изменении.</p>
        <button type="submit" style="grid-column:1/-1;padding:15px;border:0;background:#00f2ff;color:#000;font-weight:bold;cursor:pointer">ИМПОРТИРОВАТЬ <?= (int) $sourceCount ?> ТОВАРОВ</button>
    </form>
</div>
<script>
(function () {
    const form = document.getElementById('orgbox-baseshop-catalog-import');
    if (!form) return;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        if (button.disabled) return;

        const initialText = button.textContent;
        button.disabled = true;
        button.textContent = 'ПОЛУЧАЕМ СВЕЖУЮ СЕССИЮ…';

        try {
            const response = await fetch(location.pathname + '?action=session&_=' + Date.now(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json();
            if (!response.ok || !payload.success || !payload.sessid) {
                throw new Error('Не удалось обновить сессию. Обновите страницу.');
            }

            let sessidInput = form.querySelector('input[name="sessid"]');
            if (!sessidInput) {
                sessidInput = document.createElement('input');
                sessidInput.type = 'hidden';
                sessidInput.name = 'sessid';
                form.appendChild(sessidInput);
            }
            sessidInput.value = payload.sessid;
            button.textContent = 'ИМПОРТИРУЕМ ТОВАРЫ…';
            HTMLFormElement.prototype.submit.call(form);
        } catch (error) {
            button.disabled = false;
            button.textContent = initialText;
            alert(error.message || 'Не удалось запустить импорт.');
        }
    });
})();
</script>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
