document.addEventListener('DOMContentLoaded',()=>{
  const endpoint='/local/ajax/orgbox-baseshop-checkout.php';
  const money=value=>`${new Intl.NumberFormat('ru-RU').format(Number(value)||0)} ₽`;

  document.querySelectorAll('.checkout-form').forEach(form=>{
    const options=form.querySelector('.delivery-options');
    const submit=form.querySelector('.checkout-submit');
    const message=form.querySelector('.checkout-message');
    const summary=form.querySelector('.checkout-summary');
    const calculate=form.querySelector('.checkout-calculate');
    const quote=form.querySelector('[name="delivery_quote"]');
    let revision=0;
    const invalidate=()=>{revision++;quote.value='';submit.disabled=true;summary.textContent='Рассчитайте доставку для выбранного способа и адреса.';};
    form.addEventListener('input',event=>{
      if(event.target.matches('[name="city"], [name="postal_code"], [name="address"], [name="quantity"]'))invalidate();
    });

    const request=async action=>{
      const sessionResponse=await fetch(`${endpoint}?action=session&_=${Date.now()}`,{credentials:'same-origin',cache:'no-store'});
      const sessionPayload=await sessionResponse.json().catch(()=>({success:false}));
      if(!sessionResponse.ok||!sessionPayload.success||!sessionPayload.sessid)throw new Error('Не удалось обновить сессию. Перезагрузите страницу.');
      const data=new FormData(form);data.set('action',action);data.set('sessid',sessionPayload.sessid);
      const response=await fetch(endpoint,{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      const payload=await response.json().catch(()=>({success:false,error:'Некорректный ответ сервера.'}));
      if(!response.ok||!payload.success)throw new Error(payload.error||'Не удалось выполнить запрос.');
      return payload;
    };

    calculate.addEventListener('click',async()=>{
      invalidate();
      const currentRevision=revision;
      message.textContent='';calculate.disabled=true;calculate.textContent='РАССЧИТЫВАЕМ…';
      try{
        const payload=await request('delivery');
        if(currentRevision!==revision)return;
        const selected=payload.options[0];
        if(!selected||!selected.success)throw new Error(selected?.error||'Нет доступного тарифа доставки.');
        quote.value=String(selected.price);
        submit.disabled=false;
        const productTotal=Number(form.dataset.productPrice)*Number(form.elements.quantity.value);
        summary.textContent=`Товары: ${money(productTotal)}. Доставка: ${money(selected.price)} (${selected.estimatedDays}). Итого: ${money(productTotal+Number(selected.price))}.`;
      }catch(error){if(currentRevision===revision)message.textContent=error.message;}
      finally{calculate.disabled=false;calculate.textContent='ПЕРЕСЧИТАТЬ ДОСТАВКУ';}
    });

    options.addEventListener('change',event=>{
      if(event.target.matches('[name="delivery_provider"]')){invalidate();message.textContent='';}
    });

    form.addEventListener('submit',async event=>{
      event.preventDefault();if(!quote.value){message.textContent='Сначала рассчитайте доставку.';return;}message.textContent='';submit.disabled=true;submit.textContent='ОФОРМЛЯЕМ…';
      try{
        const payload=await request('order');
        const panel=form.closest('.checkout-panel');
        form.hidden=true;
        const success=panel.querySelector('.checkout-success');
        success.querySelector('strong').textContent=payload.orderId;
        success.hidden=false;
        success.scrollIntoView({behavior:'smooth',block:'center'});
      }catch(error){message.textContent=error.message;invalidate();submit.textContent='ОФОРМИТЬ ЗАКАЗ';}
    });
  });
});
