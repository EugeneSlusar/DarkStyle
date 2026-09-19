const catalogProducts=window.darkStyleCatalog||[];
const escapeHtml=value=>String(value||'').replace(/[&<>"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
const catalogGrid=document.querySelector('.product-grid');

const carGroups=[
  {id:'vaz-2104-2107',name:'ВАЗ 2104–2107',match:title=>/2107|2105|2104/i.test(title)},
  {id:'vaz-2109-2114',name:'ВАЗ 2109–2114',match:title=>/2114|2115|2109|21099/i.test(title)},
  {id:'chevrolet-niva-travel',name:'Chevrolet Niva Travel',match:title=>/Niva Travel|шевроле нива/i.test(title)},
  {id:'kia-rio-hyundai-solaris',name:'Kia Rio / Hyundai Solaris',match:title=>/Kia Rio|Hyundai Solaris/i.test(title)},
  {id:'lada-granta-kalina',name:'Lada Granta / Kalina',match:title=>/Granta|Гранта|Kalina|Калина|Datsun/i.test(title)},
  {id:'lada-niva',name:'Lada Niva',match:title=>/лада Нива|Lada Niva/i.test(title)},
  {id:'lada-priora',name:'Lada Priora',match:title=>/Priora|Приора|2110|2111|2112/i.test(title)},
  {id:'lada-vesta',name:'Lada Vesta',match:title=>/Vesta|Веста/i.test(title)}
];

const renderProduct=(product,group)=>{
  const images=Array.from({length:product.n},(_,index)=>`assets/catalog-products/${product.s}/${String(index+1).padStart(2,'0')}.${index===0?product.e:'jpg'}`);
  const controls=images.length>1?`<button class="gallery-arrow gallery-prev" type="button" aria-label="Предыдущее фото">‹</button><button class="gallery-arrow gallery-next" type="button" aria-label="Следующее фото">›</button>`:'';
  const dots=images.length>1?`<div class="gallery-dots">${images.map((src,index)=>`<button type="button" class="gallery-dot${index===0?' active':''}" data-src="${src}" aria-label="Фото ${index+1}"></button>`).join('')}</div>`:'';
  return `<article class="card product-card" data-sku="${product.s}">
    <div class="product-gallery"><img class="gallery-main" src="${images[0]}" alt="${escapeHtml(product.t)}" loading="lazy">${controls}${dots}</div>
    <div class="product-info"><small>АРТИКУЛ ${product.s}</small><h3>${escapeHtml(product.t)}</h3><p class="price">${product.p}${product.o?` <s>${product.o}</s>`:''}</p><p class="rating">★ Товар бренда «Тёмный стиль»</p><a href="/catalog/${group.id}/product-${product.s}/">ПОДРОБНЕЕ →</a></div>
  </article>`;
};

if(catalogGrid){
  const tagCloud=document.createElement('nav');
  tagCloud.className='catalog-tags';
  tagCloud.setAttribute('aria-label','Марки автомобилей');
  tagCloud.innerHTML=`<span class="catalog-tags-label">Выберите автомобиль</span><div class="catalog-tags-list">${carGroups.map(group=>`<a href="#${group.id}">${group.name}</a>`).join('')}</div>`;
  catalogGrid.before(tagCloud);

  catalogGrid.innerHTML=carGroups.map((group,index)=>{
    const products=catalogProducts.filter(product=>group.match(product.t));
    if(!products.length)return '';
    return `<header class="catalog-group-title" id="${group.id}"><span>${String(index+1).padStart(2,'0')}</span><h3>${group.name}</h3><small>${products.length} ${products.length===4?'товара':'товаров'}</small></header>${products.map(product=>renderProduct(product,group)).join('')}`;
  }).join('');

  catalogGrid.addEventListener('click',event=>{
    const card=event.target.closest('.product-card');
    if(!card)return;
    const dots=[...card.querySelectorAll('.gallery-dot')];
    if(!dots.length)return;
    let index=Math.max(0,dots.findIndex(dot=>dot.classList.contains('active')));
    const selected=event.target.closest('.gallery-dot');
    if(selected)index=dots.indexOf(selected);
    else if(event.target.closest('.gallery-next'))index=(index+1)%dots.length;
    else if(event.target.closest('.gallery-prev'))index=(index-1+dots.length)%dots.length;
    else return;
    dots.forEach((dot,i)=>dot.classList.toggle('active',i===index));
    card.querySelector('.gallery-main').src=dots[index].dataset.src;
  });
}
