'use strict';
const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch();try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});const requests=[];
 await page.route('**/*',async route=>{
  const u=new URL(route.request().url());requests.push({host:u.hostname,path:u.pathname,method:route.request().method()});
  if(['clubdisplay.tennilo.de','clubdisplay.ntc93.de'].includes(u.hostname)){
   if(u.pathname.endsWith('.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'unavailable',matches:[],events:[],posts:[]})});
   const file=path.join(process.cwd(),'public',u.pathname==='/'?'index.html':u.pathname.slice(1));if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
  }return route.abort();
 });
 await page.goto('https://clubdisplay.tennilo.de/?customize=1');
 await page.locator('#personal-demo-dialog[open]').waitFor();
 await page.locator('#personal-name').fill('TC Muster <&>');
 await page.locator('#personal-color').fill('#cc0044');
 const png=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=32;c.height=32;const x=c.getContext('2d');x.fillStyle='#cc0044';x.fillRect(0,0,32,32);return c.toDataURL('image/png').split(',')[1];});
 await page.locator('#personal-logo').setInputFiles({name:'logo.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')});
 await page.locator('#personal-submit').click();
 await page.waitForFunction(()=>document.documentElement.dataset.personalDemo==='true'||document.querySelector('#personal-status').textContent!=='');
 assert.equal(await page.locator('#personal-status').textContent(),'');
 assert.equal(await page.locator('#club').textContent(),'TC Muster <&>');
 assert.match(await page.locator('#brand-logo').getAttribute('src'),/^data:image\/png/);
 assert.equal(await page.locator('#ntc-brand-title').textContent(),'TC Muster <&> ClubDisplay');
 const matches=await page.evaluate(async()=>{await refreshLeague();return leagueData.matches;});
 assert.equal(matches[0].home,'TC Muster <&> I');
 await page.locator('#nav button[data-action="teams"]').click();
 assert.ok((await page.locator('#screen').textContent()).includes('TC Muster <&> I'));
 await page.setViewportSize({width:390,height:720});assert.ok(await page.locator('#personal-demo-edit').isVisible());
 assert.ok(!requests.some(r=>r.method==='POST'),'No logo/name submission or real booking');
 await page.goto('https://clubdisplay.ntc93.de/?customize=1');
 assert.ok(await page.locator('#personal-demo-edit').isHidden());assert.ok(await page.locator('#personal-demo-dialog').isHidden());
 console.log('Personal demo: browser-local logo, safe name rendering, refresh persistence, NTC isolation');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
