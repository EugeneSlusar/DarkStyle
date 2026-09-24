(()=>{
  const init=()=>{
    document.querySelectorAll('[data-home-slider]').forEach(slider=>{
      if(slider.dataset.homeSliderInitialized==='true')return;

      const track=slider.querySelector('[data-home-slider-track]');
      const slides=track?[...track.querySelectorAll(':scope>[data-home-slider-slide]')]:[];
      const dots=[...slider.querySelectorAll('[data-home-slider-dot]')];
      if(!track||slides.length<2)return;

      slider.dataset.homeSliderInitialized='true';
      let current=0;
      let position=1;
      let timer=0;
      let moving=false;
      const delay=Math.max(0,Number(slider.dataset.autoplayDelay)||0);
      if(delay)slider.style.setProperty('--home-slider-progress-duration',`${delay}ms`);

      const updateState=()=>{
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

      const setPosition=(nextPosition,animated)=>{
        track.style.transition=animated?'transform .65s cubic-bezier(.22,.61,.36,1)':'none';
        track.style.transform=`translate3d(${-nextPosition*100}%,0,0)`;
        if(!animated)track.offsetHeight;
      };

      const firstClone=slides[0].cloneNode(true);
      const lastClone=slides[slides.length-1].cloneNode(true);
      [firstClone,lastClone].forEach(clone=>{
        clone.dataset.homeSliderClone='true';
        clone.classList.remove('is-active');
        clone.setAttribute('aria-hidden','true');
        clone.removeAttribute('data-banner-edit-url');
        clone.removeAttribute('data-banner-delete-url');
      });
      track.insertBefore(lastClone,slides[0]);
      track.append(firstClone);
      setPosition(position,false);
      updateState();

      const finishMove=()=>{
        if(!moving)return;
        moving=false;
        if(position===0){
          position=slides.length;
          setPosition(position,false);
        }
        if(position===slides.length+1){
          position=1;
          setPosition(position,false);
        }
      };
      track.addEventListener('transitionend',event=>{
        if(event.target===track&&event.propertyName==='transform')finishMove();
      });

      const show=(index,direction=0)=>{
        if(moving)return;
        const next=(index+slides.length)%slides.length;
        if(next===current)return;
        if(direction>0&&current===slides.length-1&&next===0){
          position=slides.length+1;
        }else if(direction<0&&current===0&&next===slides.length-1){
          position=0;
        }else{
          position=next+1;
        }
        current=next;
        moving=true;
        updateState();
        setPosition(position,true);
      };

      const stop=()=>{
        if(timer){
          window.clearInterval(timer);
          timer=0;
        }
      };
      const start=()=>{
        stop();
        if(delay&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
          timer=window.setInterval(()=>show(current+1,1),delay);
        }
      };

      slider.querySelector('[data-home-slider-prev]')?.addEventListener('click',()=>{
        show(current-1,-1);
        start();
      });
      slider.querySelector('[data-home-slider-next]')?.addEventListener('click',()=>{
        show(current+1,1);
        start();
      });
      dots.forEach((dot,index)=>dot.addEventListener('click',()=>{
        show(index);
        start();
      }));
      slider.addEventListener('mouseenter',stop);
      slider.addEventListener('mouseleave',start);
      slider.addEventListener('focusin',stop);
      slider.addEventListener('focusout',event=>{
        if(!slider.contains(event.relatedTarget))start();
      });
      start();
    });
  };

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',init,{once:true});
  }else{
    init();
  }
})();
