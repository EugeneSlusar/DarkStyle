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

  document.querySelectorAll('[data-home-slider]').forEach(slider=>{
    const slides=[...slider.querySelectorAll('[data-home-slider-slide]')];
    const dots=[...slider.querySelectorAll('[data-home-slider-dot]')];
    if(slides.length<2)return;
    let current=0;
    let timer=0;
    const delay=Math.max(0,Number(slider.dataset.autoplayDelay)||0);
    const show=index=>{
      current=(index+slides.length)%slides.length;
      slides.forEach((slide,itemIndex)=>{
        const active=itemIndex===current;
        slide.classList.toggle('is-active',active);
        slide.setAttribute('aria-hidden',String(!active));
      });
      dots.forEach((dot,itemIndex)=>{
        const active=itemIndex===current;
        dot.classList.toggle('is-active',active);
        dot.setAttribute('aria-selected',String(active));
      });
    };
    const stop=()=>{if(timer){window.clearInterval(timer);timer=0;}};
    const start=()=>{stop();if(delay&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches)timer=window.setInterval(()=>show(current+1),delay);};
    slider.querySelector('[data-home-slider-prev]')?.addEventListener('click',()=>{show(current-1);start();});
    slider.querySelector('[data-home-slider-next]')?.addEventListener('click',()=>{show(current+1);start();});
    dots.forEach((dot,index)=>dot.addEventListener('click',()=>{show(index);start();}));
    slider.addEventListener('mouseenter',stop);
    slider.addEventListener('mouseleave',start);
    slider.addEventListener('focusin',stop);
    slider.addEventListener('focusout',event=>{if(!slider.contains(event.relatedTarget))start();});
    start();
  });
});
