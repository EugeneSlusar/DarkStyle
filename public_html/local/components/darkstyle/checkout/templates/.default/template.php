<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$product = $arResult['PRODUCT'];
$modalId = 'checkout-' . (int) $product['ID'];
?>
<button class="button buy-button" type="button" data-checkout-open="<?=$modalId?>">КУПИТЬ →</button>
<div class="checkout-modal" id="<?=$modalId?>" hidden aria-hidden="true">
    <div class="checkout-backdrop" data-checkout-close></div>
    <div class="checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="<?=$modalId?>-title">
        <button class="checkout-close" type="button" data-checkout-close aria-label="Закрыть">×</button>
        <div class="checkout-heading"><span class="number">ОФОРМЛЕНИЕ ЗАКАЗА</span><h2 id="<?=$modalId?>-title"><?=htmlspecialcharsbx($product['NAME'])?></h2><p><?=number_format($product['PRICE'], 0, ',', ' ')?> ₽ без доставки</p></div>
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
        <div class="checkout-success" hidden><span class="number">ГОТОВО</span><h2>Заказ принят</h2><p>Номер заказа: <strong></strong>. Менеджер свяжется с вами для подтверждения.</p><button class="button cyan" type="button" data-checkout-close>ЗАКРЫТЬ</button></div>
    </div>
</div>
