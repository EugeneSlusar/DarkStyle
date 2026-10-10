const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const element=()=>({textContent:'',value:'',disabled:false,hidden:false,listeners:{},addEventListener(type,fn){this.listeners[type]=fn;},replaceChildren(...items){this.items=items;this.value='';},add(item){this.items.push(item);}});
const selectors=['.delivery-options','.checkout-submit','.checkout-message','.checkout-summary','.checkout-calculate','[name="delivery_quote"]','.checkout-pickup','.checkout-load-points','[name="pickup_point_id"]','.checkout-point-details','.checkout-address','.checkout-postal-code','[name="address"]','[name="postal_code"]','[name="delivery_provider"]:checked'];
const elements=Object.fromEntries(selectors.map(name=>[name,element()]));
const form=element();form.dataset={productPrice:'1290'};
form.elements={quantity:{value:'2'},city:{value:'Самара'}};
form.querySelector=name=>elements[name];form.querySelectorAll=()=>Object.values(elements);
elements['[name="delivery_provider"]:checked'].value='cdek_pickup';
const provider=elements['[name="delivery_provider"]:checked'];
let actions=[],reply=async action=>action==='pickup_points'?{success:true,points:[{id:'point-1',name:'Яндекс',address:'Самара, улица <script>',schedule:'09:00–21:00'}]}:{success:true,options:[{success:true,price:225.7,estimatedDays:'7 дней'}]};
let session=async()=>({success:true,sessid:'session'});
vm.runInNewContext(fs.readFileSync(__dirname+'/../public_html/local/js/checkout.js','utf8'),{
 document:{addEventListener(type,fn){fn();},querySelectorAll(){return [form];}},Intl,Number,String,Date,Array,
 Option:class{constructor(text,value){this.text=text;this.value=value;}},
 FormData:class{constructor(){this.data={city:form.elements.city.value,provider:provider.value,point:elements['[name="pickup_point_id"]'].value};}set(key,value){this.data[key]=value;}},
 fetch:async(url,options)=>{
  if(!options.method)return {ok:true,json:()=>session()};
  actions.push(options.body.data);return {ok:true,json:()=>reply(options.body.data.action)};
 }
});
const calculate=elements['.checkout-calculate'].listeners.click;
const load=elements['.checkout-load-points'].listeners.click;
const select=elements['[name="pickup_point_id"]'];
const quote=elements['[name="delivery_quote"]'];
const changeProvider=()=>elements['.delivery-options'].listeners.change({target:{matches:()=>true}});
const input=(name)=>form.listeners.input({target:{matches:selector=>selector.includes(`[name="${name}"]`)}});
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 assert.equal(elements['.checkout-pickup'].hidden,false);assert.equal(elements['[name="address"]'].disabled,true);
 await calculate();assert.equal(actions.length,0,'No point must not quote');
 await load();assert.equal(select.disabled,false);assert.equal(select.items.length,2);
 assert.match(select.items[1].text,/<script>/,'Point labels stay text');
 select.value='point-1';select.listeners.change();
 assert.match(elements['.checkout-point-details'].textContent,/09:00/);
 await calculate();assert.equal(quote.value,'225.7');assert.match(elements['.checkout-summary'].textContent,/2\s*805,7/);
 input('quantity');assert.equal(quote.value,'');assert.equal(select.value,'point-1','Quantity must keep point');
 await calculate();select.listeners.change();assert.equal(quote.value,'','Point change must invalidate quote');
 input('city');assert.equal(select.value,'');assert.equal(select.disabled,true);
 let release;reply=()=>new Promise(resolve=>{release=resolve;});
 const pending=load();await tick();provider.value='yandex';changeProvider();
 release({success:true,points:[{id:'old',name:'Old',address:'Old'}]});await pending;
 assert.equal(select.value,'');assert.equal(select.disabled,true);assert.equal(select.items.length,1,'Late points must not restore stale city');
 assert.equal(elements['[name="address"]'].disabled,true);
 provider.value='cdek';changeProvider();
 assert.equal(elements['[name="address"]'].disabled,false);
 provider.value='cdek_pickup';changeProvider();
 reply=async()=>({success:true,points:[]});await load();assert.equal(select.disabled,true);assert.match(elements['.checkout-message'].textContent,/нет доступных/);
 reply=async()=>({success:false,error:'Ключи не настроены'});await load();assert.equal(select.disabled,true);assert.equal(elements['.checkout-message'].textContent,'Ключи не настроены');
 // A delayed session response must submit the original city snapshot, then ignore results after a city change.
 let releaseSession;session=()=>new Promise(resolve=>{releaseSession=resolve;});
 form.elements.city.value='Самара';const waiting=load();await tick();form.elements.city.value='Москва';input('city');
 reply=async()=>({success:true,points:[{id:'old',name:'Old',address:'Old'}]});
 releaseSession({success:true,sessid:'session'});await waiting;
 assert.equal(actions.at(-1).city,'Самара');assert.equal(select.disabled,true);assert.equal(select.items.length,1);
 console.log('CDEK pickup checkout tests passed');
})().catch(error=>{console.error(error);process.exitCode=1;});
