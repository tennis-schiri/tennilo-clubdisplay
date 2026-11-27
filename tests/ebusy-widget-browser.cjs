'use strict';
const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
(async()=>{const browser=await chromium.launch();try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.clock.install({time:new Date('2026-11-27T14:00:00+01:00')});
 const wrapper=execFileSync('php',['-r','$_SERVER["HTTP_HOST"]="clubdisplay.ntc93.de";$_SERVER["REQUEST_METHOD"]="GET";include "public/ntc/booking-widget.php";'],{encoding:'utf8'});
 let requested=false,apiAvailable=true;
 await page.route('**/*',async route=>{
  const u=new URL(route.request().url());
  if(u.hostname==='cd-ebusy.ntc93.de'){
   if(u.pathname==='/resources/js/ebusy.api.js'){if(!apiAvailable)return route.abort();return route.fulfill({contentType:'application/javascript',body:'window.ebusy={createBookingView(id,url,opts){window.widgetOptions=opts;const f=document.createElement("iframe");f.src=url;document.getElementById(id).append(f);}};'});}
   if(u.pathname==='/embed/lite-module/1731'){requested=true;return route.fulfill({contentType:'text/html',body:'<input id="example" value="mock booking only">'});}
   return route.abort();
  }
  if(u.hostname==='clubdisplay.ntc93.de'||u.hostname==='clubdisplay.tennilo.de'){
   if(u.pathname==='/booking-widget.php')return route.fulfill({contentType:'text/html',body:wrapper});
   if(u.pathname==='/terminal.php'&&!u.search)return route.fulfill({contentType:'text/html',body:'<p>PIN terminal</p>'});
   if(u.pathname.endsWith('.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'unavailable',ready:true,configured:true,matches:[],events:[],posts:[]})});
   const file=path.join(process.cwd(),'public',u.pathname==='/'?'index.html':u.pathname.slice(1));if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
  }return route.abort();
 });
 await page.goto('https://clubdisplay.ntc93.de/');
 await page.locator('#nav button[data-action="courts"]').click();
 await page.locator('iframe[src="terminal.php"]').waitFor();assert.equal(requested,false);
 await page.locator('[data-action="court-mode:widget"]').click();
 const widget=page.frameLocator('iframe.booking-widget-frame');
 const input=widget.frameLocator('#booking-container iframe').locator('#example');
 await input.fill('preserved session');
 assert.equal(requested,true);
 assert.equal(await widget.locator('#booking-status').isHidden(),true);
 await page.locator('#booking-expand').click();assert.ok(await page.locator('body').evaluate(e=>e.classList.contains('booking-expanded')));
 assert.equal(await input.inputValue(),'preserved session');
 await page.locator('#booking-expand').click();assert.equal(await input.inputValue(),'preserved session');
 await page.locator('[data-action="court-mode:indoor"]').click();assert.equal(await page.locator('iframe.hall-frame').getAttribute('inert'),'');assert.equal(await page.locator('iframe.hall-frame').getAttribute('src'),'https://cd-ebusy.ntc93.de/embed/court-module/3076');
 await page.locator('[data-action="court-mode:outdoor"]').click();await page.locator('iframe[src="terminal.php"]').waitFor();
 apiAvailable=false;
 await page.locator('[data-action="court-mode:widget"]').click();await widget.locator('#booking-help').waitFor();
 await page.goto('https://clubdisplay.tennilo.de/');await page.locator('#nav button[data-action="courts"]').click();assert.equal(await page.locator('[data-action="court-mode:widget"]').count(),0);
 console.log('Official widget: vendor API contract (mocked), existing terminal, full-height session preservation, hall read-only, failure fallback, demo isolation');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
