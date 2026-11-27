'use strict';
const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch();try{
const page=await browser.newPage({viewport:{width:1440,height:900}});
await page.clock.install({time:new Date('2026-11-27T14:00:00+01:00')});
let requests=0;
await page.route('**/*',async route=>{
 const u=new URL(route.request().url());
 if(u.hostname==='tende-appsdemo.liga.nu'){assert.equal(u.pathname,'/nuliga/nuscore-tennis2/welcome');requests++;return route.fulfill({contentType:'text/html',body:'<input id="example" value="mock only">'});}
 if(u.hostname==='clubdisplay.ntc93.de'||u.hostname==='clubdisplay.tennilo.de'){
  if(u.pathname.endsWith('.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'unavailable',ready:true,configured:true,matches:[],events:[],posts:[]})});
  const file=path.join(process.cwd(),'public',u.pathname==='/'?'index.html':u.pathname.slice(1));if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
 }return route.abort();
});
await page.goto('https://clubdisplay.ntc93.de/');
await page.locator('#nav button[data-action="teams"]').click();
await page.locator('[data-action="nuscore-demo"]').click();
const dialog=page.locator('#nuscore-demo-dialog'),frame=page.frameLocator('#nuscore-demo-frame'),input=frame.locator('#example');
await input.fill('saved mock state');assert.equal(requests,1);
await page.clock.fastForward(120000);assert.equal(await dialog.evaluate(el=>el.open),true);
await page.locator('#nuscore-demo-back').click();assert.equal(await dialog.evaluate(el=>el.open),false);
await page.locator('#nav button[data-action="info"]').click();await page.locator('#nav button[data-action="teams"]').click();
await page.locator('[data-action="nuscore-demo"]').click();assert.equal(await input.inputValue(),'saved mock state');assert.equal(requests,1);
page.once('dialog',d=>d.accept());await page.locator('#nuscore-demo-end').click();assert.equal(await page.locator('#nuscore-demo-frame').count(),0);
await page.goto('https://clubdisplay.tennilo.de/');await page.locator('#nav button[data-action="teams"]').click();assert.equal(await page.locator('[data-action="nuscore-demo"]').count(),0);
console.log('nuScore test dialog: demo URL (mock), no idle interruption, retained iframe, explicit close, NTC only');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
