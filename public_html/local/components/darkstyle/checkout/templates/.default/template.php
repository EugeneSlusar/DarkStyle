<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<?php if (!empty($arResult['ERROR'])): ?>
<section class="section checkout-page">
    <div class="wrap checkout-error">
        <span class="number">ОШИБКА</span>
        <h1><?=htmlspecialcharsbx($arResult['ERROR'])?></h1>
        <a class="button cyan" href="/catalog/">ВЕРНУТЬСЯ В КАТАЛОГ →</a>
    </div>
</section>
<?php return; endif; ?>
<?php
$product = $arResult['PRODUCT'];
$image = $product['IMAGE'] ?: '/local/assets/product-placeholder.svg';
?>
<section class="section checkout-page">
    <div class="wrap">
        <nav class="breadcrumbs"><a href="/catalog/">Каталог</a><span>→</span><a href="<?=htmlspecialcharsbx($product['URL'])?>"><?=htmlspecialcharsbx($product['NAME'])?></a><span>→</span><span>Оформление</span></nav>
        <div class="checkout-page-head"><span class="number">ОФОРМЛЕНИЕ ЗАКАЗА</span><h1>Проверьте товар<br>и заполните данные</h1></div>
        <div class="checkout-layout">
            <aside class="checkout-product">
                <a href="<?=htmlspecialcharsbx($product['URL'])?>"><img class="checkout-product-image" src="<?=htmlspecialcharsbx($image)?>" alt="<?=htmlspecialcharsbx($product['NAME'])?>"></a>
                <small><?=htmlspecialcharsbx($product['SECTION']['NAME'])?><?= $product['ARTICLE'] !== '' ? ' · ' . htmlspecialcharsbx($product['ARTICLE']) : '' ?></small>
                <h2><a href="<?=htmlspecialcharsbx($product['URL'])?>"><?=htmlspecialcharsbx($product['NAME'])?></a></h2>
                <p class="checkout-product-price"><?=number_format($product['PRICE'], 0, ',', ' ')?> ₽ <span>без доставки</span></p>
                <a class="checkout-product-back" href="<?=htmlspecialcharsbx($product['URL'])?>">← Вернуться к товару</a>
            </aside>
            <div class="checkout-panel">
                <div class="checkout-heading"><span class="number">ДАННЫЕ ПОКУПАТЕЛЯ</span><h2>Куда доставить заказ</h2><p>Стоимость доставки рассчитаем по адресу</p></div>
        <form class="checkout-form" novalidate>
            <?php bitrix_sessid_post(); ?>
            <input type="hidden" name="product_id" value="<?=(int) $product['ID']?>">
            <label class="checkout-honeypot" aria-hidden="true">Компания<input type="text" name="company" tabindex="-1" autocomplete="off"></label>
            <fieldset><legend>01 / Получатель</legend><div class="checkout-fields"><label>ИМЯ *<input name="customer_name" type="text" maxlength="100" required autocomplete="name"></label><label>ТЕЛЕФОН *<input name="phone" type="tel" maxlength="30" required autocomplete="tel" placeholder="+7 ___ ___-__-__"></label><label>EMAIL<input name="email" type="email" maxlength="150" autocomplete="email"></label><label>КОЛИЧЕСТВО<input name="quantity" type="number" value="1" min="1" max="10"></label></div></fieldset>
            <fieldset><legend>02 / Адрес</legend><div class="checkout-fields"><label>ГОРОД *<input name="city" type="text" maxlength="100" required autocomplete="address-level2"></label><label>ИНДЕКС *<input name="postal_code" type="text" maxlength="6" inputmode="numeric" required autocomplete="postal-code"></label><label class="checkout-wide">АДРЕС *<input name="address" type="text" maxlength="250" required autocomplete="street-address"></label></div><button class="checkout-calculate" type="button">РАССЧИТАТЬ ДОСТАВКУ</button></fieldset>
            <fieldset><legend>03 / Доставка</legend><div class="delivery-options"><p>Заполните адрес и рассчитайте доступные варианты.</p></div></fieldset>
            <label>КОММЕНТАРИЙ<textarea name="comment" maxlength="1000" rows="3"></textarea></label>
            <div class="checkout-summary"></div>
            <div class="checkout-message" role="status" aria-live="polite"></div>
            <button class="button magenta checkout-submit" type="submit" disabled>ОФОРМИТЬ ЗАКАЗ</button>
            <small>Нажимая кнопку, вы соглашаетесь на обработку данных для оформления заказа.</small>
        </form>
                <div class="checkout-success" hidden><span class="number">ГОТОВО</span><h2>Заказ принят</h2><p>Номер заказа: <strong></strong>. Менеджер свяжется с вами для подтверждения.</p><a class="button cyan" href="/catalog/">ВЕРНУТЬСЯ В КАТАЛОГ →</a></div>
            </div>
        </div>
    </div>
</section>
