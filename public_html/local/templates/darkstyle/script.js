document.addEventListener('DOMContentLoaded',()=>{
  const menuButton=document.querySelector('.menu-button');
  const menu=document.querySelector('.menu');
  if(menuButton&&menu){
    menuButton.addEventListener('click',()=>{
      const open=menu.classList.toggle('open');
      menuButton.setAttribute('aria-expanded',String(open));
    });
    menu.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{
      menu.classList.remove('open');
      menuButton.setAttribute('aria-expanded','false');
    }));
  }

  document.querySelectorAll('.detail-thumb').forEach(button=>button.addEventListener('click',()=>{
    const gallery=button.closest('.detail-gallery');
    const main=gallery&&gallery.querySelector('.detail-main-image');
    if(!main)return;
    main.src=button.dataset.image||main.src;
    gallery.querySelectorAll('.detail-thumb').forEach(item=>item.classList.toggle('active',item===button));
  }));
});
