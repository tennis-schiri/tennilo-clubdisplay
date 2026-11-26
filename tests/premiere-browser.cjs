'use strict';
const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch();try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.clock.install({time:new Date('2026-12-06T14:00:00+01:00')});
 await page.route('**/*',async route=>{
  const u=new URL(route.request().url());
  if(['clubdisplay.tennilo.de','clubdisplay.ntc93.de'].includes(u.hostname)){
   if(u.pathname.endsWith('.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'unavailable',matches:[],events:[],posts:[]})});
   const file=path.join(process.cwd(),'public',u.pathname==='/'?'index.html':u.pathname.slice(1));if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
  }return route.abort();
 });
 await page.goto('https://clubdisplay.ntc93.de/');
 const overlay=page.locator('#premiere-overlay');
 assert.ok(await overlay.isHidden());await page.locator('#premiere-launch').click();
 assert.ok(await overlay.isVisible());assert.match(await page.locator('#premiere-content').textContent(),/33 Jahre/);
 await page.clock.fastForward(18010);assert.equal(await page.locator('#premiere-position').textContent(),'2 / 8');
 await page.locator('#premiere-pause').click();await page.clock.fastForward(19000);assert.equal(await page.locator('#premiere-position').textContent(),'2 / 8');
 for(let i=0;i<5;i++)await page.locator('#premiere-next').click();
 assert.equal(await page.locator('#premiere-position').textContent(),'7 / 8');assert.equal(await page.locator('.premiere-qr svg').count(),1);
 await page.locator('#premiere-next').click();await page.locator('#premiere-next').click();assert.ok(await overlay.isHidden());
 await page.locator('#nav button[data-action="home"]').click();await page.locator('[data-action="intro"]').click();assert.equal(await page.locator('#premiere-position').textContent(),'1 / 4');await page.keyboard.press('Escape');
 assert.ok(await overlay.isHidden());
 await page.goto('https://clubdisplay.tennilo.de/?premiere=1');assert.ok(await page.locator('#premiere-launch').isHidden());
 console.log('NTC premiere: manual launch, autoplay, pause, feedback QR, intro, exit and demo isolation');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
