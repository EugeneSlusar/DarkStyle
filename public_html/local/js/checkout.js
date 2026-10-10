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
    const pickup=form.querySelector('.checkout-pickup');
    const loadPoints=form.querySelector('.checkout-load-points');
    const pointSelect=form.querySelector('[name="pickup_point_id"]');
    const pointDetails=form.querySelector('.checkout-point-details');
    const postNote=form.querySelector('.checkout-post-note');
    const provider=()=>form.querySelector('[name="delivery_provider"]:checked')?.value||'cdek';
    const isPickup=()=>['yandex','cdek_pickup'].includes(provider());
    let revision=0,pointsRevision=0,points=[];
    let submitting=false;
    const invalidate=()=>{revision++;quote.value='';submit.disabled=true;summary.textContent='Рассчитайте доставку для выбранного способа и адреса.';};
    const clearPoints=()=>{
      pointsRevision++;points=[];
      if(!pointSelect)return;
      pointSelect.replaceChildren(new Option('Сначала найдите ПВЗ',''));
      pointSelect.disabled=true;pointDetails.textContent='';
      loadPoints.disabled=false;loadPoints.textContent='НАЙТИ ПВЗ';
    };
    const updateProvider=()=>{
      if(!pickup)return;
      const pickupMode=isPickup();
      if(postNote)postNote.hidden=provider()!=='russian_post';
      pickup.hidden=!pickupMode;
      form.querySelector('.checkout-address').hidden=pickupMode;
      form.querySelector('.checkout-postal-code').hidden=pickupMode;
      form.querySelector('[name="address"]').disabled=pickupMode;
      form.querySelector('[name="postal_code"]').disabled=pickupMode;
      clearPoints();
    };
    updateProvider();
    form.addEventListener('input',event=>{
      if(event.target.matches('[name="city"], [name="postal_code"], [name="address"], [name="quantity"]'))invalidate();
      if(event.target.matches('[name="city"]'))clearPoints();
    });

    const request=async action=>{
      // Capture input before refreshing the session so a delayed response cannot quote new inputs under an old revision.
      const data=new FormData(form);data.set('action',action);
      const sessionResponse=await fetch(`${endpoint}?action=session&_=${Date.now()}`,{credentials:'same-origin',cache:'no-store'});
      const sessionPayload=await sessionResponse.json().catch(()=>({success:false}));
      if(!sessionResponse.ok||!sessionPayload.success||!sessionPayload.sessid)throw new Error('Не удалось обновить сессию. Перезагрузите страницу.');
      data.set('sessid',sessionPayload.sessid);
      const response=await fetch(endpoint,{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
      const payload=await response.json().catch(()=>({success:false,error:'Некорректный ответ сервера.'}));
      if(!response.ok||!payload.success)throw new Error(payload.error||'Не удалось выполнить запрос.');
      return payload;
    };

    if(loadPoints){
      loadPoints.addEventListener('click',async()=>{
        invalidate();clearPoints();
        const currentRevision=pointsRevision,currentProvider=provider();
        loadPoints.disabled=true;loadPoints.textContent='ЗАГРУЖАЕМ ПВЗ…';message.textContent='';
        try{
          const payload=await request('pickup_points');
          if(currentRevision!==pointsRevision||provider()!==currentProvider)return;
          points=payload.points||[];
          if(!points.length)throw new Error('В этом городе нет доступных ПВЗ выбранной службы.');
          pointSelect.replaceChildren(new Option('Выберите пункт выдачи',''));
          points.forEach(point=>pointSelect.add(new Option(`${point.name} — ${point.address}`,point.id)));
          pointSelect.disabled=false;
        }catch(error){if(currentRevision===pointsRevision)message.textContent=error.message;}
        finally{if(currentRevision===pointsRevision){loadPoints.disabled=false;loadPoints.textContent='ОБНОВИТЬ ПВЗ';}}
      });
      pointSelect.addEventListener('change',()=>{
        invalidate();message.textContent='';
        const point=points.find(item=>item.id===pointSelect.value);
        pointDetails.textContent=point?`${point.address}${point.schedule?'. Часы работы: '+point.schedule:''}`:'';
      });
    }

    calculate.addEventListener('click',async()=>{
      invalidate();
      const currentRevision=revision;
      message.textContent='';
      if(isPickup()&&!pointSelect?.value){message.textContent='Сначала выберите ПВЗ выбранной службы.';return;}
      calculate.disabled=true;calculate.textContent='РАССЧИТЫВАЕМ…';
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
      if(event.target.matches('[name="delivery_provider"]')){invalidate();updateProvider();message.textContent='';}
    });

    form.addEventListener('submit',async event=>{
      event.preventDefault();if(submitting)return;
      if(!quote.value){message.textContent='Сначала рассчитайте доставку.';return;}
      submitting=true;message.textContent='';submit.disabled=true;submit.textContent='ОФОРМЛЯЕМ…';
      const controls=Array.from(form.querySelectorAll('input, select, textarea, button'));
      const disabled=controls.map(control=>control.disabled);
      // request captures FormData synchronously; freeze controls only after the snapshot.
      const pending=request('order');controls.forEach(control=>{control.disabled=true;});
      try{
        const payload=await pending;
        if(payload.paymentUrl){window.location.assign(payload.paymentUrl);return;}
        const panel=form.closest('.checkout-panel');
        form.hidden=true;
        const success=panel.querySelector('.checkout-success');
        success.querySelector('strong').textContent=payload.orderId;
        success.hidden=false;
        success.scrollIntoView({behavior:'smooth',block:'center'});
      }catch(error){message.textContent=error.message;controls.forEach((control,index)=>{control.disabled=disabled[index];});invalidate();submit.textContent='ОФОРМИТЬ ЗАКАЗ';}
      finally{submitting=false;}
    });
  });
});
