<?php

declare(strict_types=1);

use DarkStyle\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$siteName = (string) Config::get('site_name', 'Тёмный стиль');
$phone = (string) Config::get('phone', '');
$managerEmail = (string) Config::get('manager_email', '');
?>
</main>
<footer>
    <div class="wrap footer-grid">
        <div><a class="brand" href="/"><?=htmlspecialcharsbx(mb_strtoupper($siteName))?></a><p>Съёмная тонировка для вашего автомобиля. Простой выбор, понятная доставка и заказ напрямую у производителя.</p></div>
        <div><h3>Контакты</h3><?php if ($phone !== ''): ?><a href="tel:<?=htmlspecialcharsbx(preg_replace('/[^+\d]/', '', $phone))?>"><?=htmlspecialcharsbx($phone)?></a><?php endif; ?><?php if ($managerEmail !== ''): ?><a href="mailto:<?=htmlspecialcharsbx($managerEmail)?>"><?=htmlspecialcharsbx($managerEmail)?></a><?php endif; ?></div>
        <div><h3>Навигация</h3><a href="/">Главная</a><a href="/catalog/">Каталог</a><a href="/#voprosy">FAQ</a></div>
        <div><h3>Заказ</h3><p>Выберите товар в каталоге, рассчитайте доставку и оформите заказ без регистрации.</p></div>
    </div>
    <div class="wrap copyright"><span>© <?=date('Y')?> <?=htmlspecialcharsbx($siteName)?>. Все права защищены.</span></div>
</footer>
</div>
</body>
</html>
