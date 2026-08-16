const ozonProducts=[
  {sku:'3347229013',tag:'LADA PRIORA · 50%',title:'Тонировка съемная 50% для Lada Priora, ВАЗ 2110–2112',price:'1 230 ₽',old:'7 000 ₽'},
  {sku:'1925249600',tag:'LADA PRIORA · 15%',title:'Тонировка съемная 15% для Lada Priora, ВАЗ 2110–2112',price:'1 225 ₽',old:'5 200 ₽'},
  {sku:'1925246516',tag:'LADA PRIORA · 5%',title:'Тонировка съемная 5% для Lada Priora, ВАЗ 2110–2112',price:'1 225 ₽',old:'5 200 ₽'},
  {sku:'1925251241',tag:'LADA PRIORA · 20%',title:'Тонировка съемная 20% для Lada Priora, ВАЗ 2110–2112',price:'1 225 ₽',old:'5 000 ₽'},
  {sku:'1925252342',tag:'LADA PRIORA · 35%',title:'Тонировка съемная 35% для Lada Priora, ВАЗ 2110–2112',price:'1 230 ₽',old:'5 200 ₽'},
  {sku:'1920412670',tag:'LADA GRANTA · 5%',title:'Тонировка съемная 5% для Lada Granta, Kalina, Datsun on-Do',price:'1 295 ₽',old:'5 200 ₽'},
  {sku:'1923293851',tag:'LADA GRANTA · 35%',title:'Тонировка съемная 35% для Lada Granta, Kalina, Datsun on-Do',price:'1 216 ₽',old:'5 200 ₽'},
  {sku:'1923291784',tag:'LADA GRANTA · 20%',title:'Тонировка съемная 20% для Lada Granta, Kalina, Datsun on-Do',price:'1 388 ₽',old:'5 200 ₽'}
];

const catalogGrid=document.querySelector('.product-grid');
if(catalogGrid){
  catalogGrid.innerHTML=ozonProducts.map(product=>{
    const images=Array.from({length:5},(_,index)=>`assets/ozon-products/${product.sku}/${String(index+1).padStart(2,'0')}.jpg`);
    return `<article class="card product-card" data-sku="${product.sku}">
      <div class="product-gallery">
        <img class="gallery-main" src="${images[0]}" alt="${product.title}" loading="lazy">
        <button class="gallery-arrow gallery-prev" type="button" aria-label="Предыдущее фото">‹</button>
        <button class="gallery-arrow gallery-next" type="button" aria-label="Следующее фото">›</button>
        <div class="gallery-dots">${images.map((src,index)=>`<button type="button" class="gallery-dot${index===0?' active':''}" data-src="${src}" aria-label="Фото ${index+1}"></button>`).join('')}</div>
      </div>
      <div class="product-info"><small>${product.tag}</small><h3>${product.title}</h3><p class="price">${product.price} <s>${product.old}</s></p><p class="rating">★ Оригинальные фотографии Ozon</p><a href="https://www.ozon.ru/product/${product.sku}/" target="_blank" rel="noopener">КУПИТЬ НА OZON →</a></div>
    </article>`;
  }).join('');

  catalogGrid.addEventListener('click',event=>{
    const card=event.target.closest('.product-card');
    if(!card)return;
    const dots=[...card.querySelectorAll('.gallery-dot')];
    let index=dots.findIndex(dot=>dot.classList.contains('active'));
    const selected=event.target.closest('.gallery-dot');
    if(selected)index=dots.indexOf(selected);
    else if(event.target.closest('.gallery-next'))index=(index+1)%dots.length;
    else if(event.target.closest('.gallery-prev'))index=(index-1+dots.length)%dots.length;
    else return;
    dots.forEach((dot,i)=>dot.classList.toggle('active',i===index));
    card.querySelector('.gallery-main').src=dots[index].dataset.src;
  });
}
