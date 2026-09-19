document.addEventListener('DOMContentLoaded',()=>{
  const endpoint='/local/ajax/darkstyle-checkout.php';
  const money=value=>`${new Intl.NumberFormat('ru-RU').format(Number(value)||0)} ₽`;
  const setModal=(modal,open)=>{
    modal.hidden=!open;
    modal.setAttribute('aria-hidden',String(!open));
    document.body.classList.toggle('checkout-open',open);
    if(open)setTimeout(()=>modal.querySelector('input:not([type="hidden"])')?.focus(),20);
  };

  document.addEventListener('click',event=>{
    const opener=event.target.closest('[data-checkout-open]');
    if(opener){const modal=document.getElementById(opener.dataset.checkoutOpen);if(modal)setModal(modal,true);return;}
    const closer=event.target.closest('[data-checkout-close]');
    if(closer){const modal=closer.closest('.checkout-modal');if(modal)setModal(modal,false);}
  });

  document.addEventListener('keydown',event=>{
    if(event.key==='Escape'){const modal=document.querySelector('.checkout-modal:not([hidden])');if(modal)setModal(modal,false);}
  });

  document.querySelectorAll('.checkout-form').forEach(form=>{
    const options=form.querySelector('.delivery-options');
    const submit=form.querySelector('.checkout-submit');
    const message=form.querySelector('.checkout-message');
    const summary=form.querySelector('.checkout-summary');
    const calculate=form.querySelector('.checkout-calculate');

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
      message.textContent='';submit.disabled=true;calculate.disabled=true;calculate.textContent='РАССЧИТЫВАЕМ…';
      try{
        const payload=await request('delivery');
        const available=payload.options.filter(option=>option.success);
        if(!available.length)throw new Error('Нет доступных способов доставки.');
        options.innerHTML=available.map((option,index)=>`<label class="delivery-option"><input type="radio" name="delivery_provider" value="${option.provider}" ${index===0?'checked':''}><span><b>${option.service}</b><small>${option.estimatedDays} · ${option.description}</small></span><strong>${money(option.price)}</strong></label>`).join('');
        submit.disabled=false;
        const selected=available[0];summary.textContent=`Товар и доставка: стоимость будет повторно проверена при отправке заказа. Доставка ${money(selected.price)}.`;
      }catch(error){message.textContent=error.message;options.innerHTML='<p>Расчёт не выполнен.</p>';}
      finally{calculate.disabled=false;calculate.textContent='ПЕРЕСЧИТАТЬ ДОСТАВКУ';}
    });

    options.addEventListener('change',event=>{
      if(event.target.matches('[name="delivery_provider"]'))summary.textContent='Товар, доставка и итог будут повторно рассчитаны на сервере.';
    });

    form.addEventListener('submit',async event=>{
      event.preventDefault();message.textContent='';submit.disabled=true;submit.textContent='ОФОРМЛЯЕМ…';
      try{
        const payload=await request('order');
        const modal=form.closest('.checkout-modal');
        form.hidden=true;
        const success=modal.querySelector('.checkout-success');
        success.querySelector('strong').textContent=payload.orderId;
        success.hidden=false;
      }catch(error){message.textContent=error.message;submit.disabled=false;submit.textContent='ОФОРМИТЬ ЗАКАЗ';}
    });
  });
});
