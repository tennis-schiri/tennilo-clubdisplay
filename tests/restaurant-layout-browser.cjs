'use strict';
const {firefox}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await firefox.launch();try{
const page=await browser.newPage();
await page.clock.install({time:new Date('2026-11-25T16:01:00+01:00')});
await page.route('**/*',async route=>{
const u=new URL(route.request().url());
if(u.hostname==='clubdisplay.ntc93.de'){
if(u.pathname.endsWith('.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'unavailable',matches:[],events:[],posts:[]})});
const file=path.join(process.cwd(),'public',u.pathname==='/'?'index.html':u.pathname.slice(1));
if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
}
if(route.request().resourceType()==='image'){const logo=u.pathname.endsWith('/logo.jpg');return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="'+(logo?300:960)+'" height="'+(logo?74:960)+'"><rect width="100%" height="100%" fill="#cbb39a"/></svg>'});}
return route.abort();
});
await page.goto('https://clubdisplay.ntc93.de/');
for(const [width,height] of [[1649,770],[1440,550],[1280,480],[1024,450],[390,720]]){
await page.setViewportSize({width,height});
for(const language of ['de','en']){
await page.evaluate(language=>{lang=language;slide=screens.indexOf('restaurant');view='info';infoPaused=true;infoPauseUntil=Date.now()+25000;render();},language);
await page.locator('.df-simple-display').waitFor();
await page.evaluate(async()=>{await Promise.all(Array.from(document.querySelectorAll('.df-simple-display img')).map(i=>i.decode().catch(()=>{})));fitActiveScreen();});
const result=await page.evaluate(()=>{
const rect=e=>e.getBoundingClientRect(),screen=rect(document.querySelector('#screen')),board=rect(document.querySelector('.df-simple-display')),outside=[];
const check=(el,bounds)=>{const r=rect(el);if(r.top<bounds.top-2||r.bottom>bounds.bottom+2||r.left<bounds.left-2||r.right>bounds.right+2)outside.push(el.className||el.tagName);};
check(document.querySelector('.df-simple-display'),screen);
for(const el of document.querySelectorAll('.df-simple-header,.df-info-panel,.df-info-panel > *, .df-offer > *, .df-info-row > *, .df-event-note > *, .df-event-copy > *, .df-event-date > *')){check(el,board);const p=el.closest('.df-info-panel');if(p&&p!==el)check(el,rect(p));}
return {outside,screenHeight:screen.height,boardHeight:board.height,density:document.querySelector('.fit-content').dataset.restaurantDensity,offers:document.querySelectorAll('.df-offer').length,holidayRows:document.querySelectorAll('.df-holiday-panel .df-info-row').length,event:!!document.querySelector('.df-event-note')};
});
assert.equal(result.offers,2);assert.equal(result.holidayRows,3);assert.equal(result.event,true);assert.deepEqual(result.outside,[],JSON.stringify({width,height,language,...result}));console.log('Restaurant fits:',width,height,language,JSON.stringify(result));
}
}
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
