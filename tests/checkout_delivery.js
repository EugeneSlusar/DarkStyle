const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const root = require('node:path').join(__dirname, '..');
const element = () => ({textContent:'',value:'',disabled:false,listeners:{},addEventListener(type,fn){this.listeners[type]=fn;}});
const elements = Object.fromEntries(['.delivery-options','.checkout-submit','.checkout-message','.checkout-summary','.checkout-calculate','[name="delivery_quote"]'].map(name=>[name,element()]));
const form = element();
form.dataset = {productPrice:'1290'};
form.elements = {quantity:{value:'2'}};
form.querySelector = name=>elements[name];
let reply = async()=>({success:true,options:[{success:true,price:812.34,estimatedDays:'2–4 дней'}]});
let orders = 0;
vm.runInNewContext(fs.readFileSync(root+'/public_html/local/js/checkout.js','utf8'), {
  document:{addEventListener(type,fn){fn();},querySelectorAll(){return [form];}},
  Intl, Number, String, Date,
  FormData:class {constructor(){this.data={};}set(key,value){this.data[key]=value;}},
  fetch:async(url,options)=>{
    if(!options.method)return {ok:true,json:async()=>({success:true,sessid:'session'})};
    if(options.body.data.action==='order')orders++;
    return {ok:true,json:()=>reply()};
  }
});
const calculate = elements['.checkout-calculate'].listeners.click;
const quote = elements['[name="delivery_quote"]'];
const submit = elements['.checkout-submit'];
const change = () => elements['.delivery-options'].listeners.change({target:{matches:()=>true}});
(async()=>{
  await calculate();
  assert.equal(quote.value,'812.34');assert.equal(submit.disabled,false);
  assert.match(elements['.checkout-summary'].textContent,/3\s*392,34/);
  form.listeners.input({target:{matches:()=>true}});
  assert.equal(quote.value,'');assert.equal(submit.disabled,true);
  await form.listeners.submit({preventDefault(){}});assert.equal(orders,0);
  reply=async()=>({success:true,options:[{success:false,error:'Служба пока не подключена'}]});
  await calculate();assert.equal(submit.disabled,true);
  assert.equal(elements['.checkout-message'].textContent,'Служба пока не подключена');
  let release;
  reply=()=>new Promise(resolve=>{release=resolve;});
  const pending=calculate();
  await new Promise(resolve=>setImmediate(resolve));
  change();
  release({success:true,options:[{success:true,price:900,estimatedDays:'2 дня'}]});
  await pending;
  assert.equal(quote.value,'');assert.equal(submit.disabled,true);
  assert.equal(elements['.checkout-calculate'].disabled,false);
  console.log('Checkout delivery tests passed');
})().catch(error=>{console.error(error);process.exitCode=1;});
