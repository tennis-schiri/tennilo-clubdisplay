'use strict';
const {chromium}=require('playwright'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch();try{
  const page=await browser.newPage({viewport:{width:1440,height:900}});
  await page.route('**/*',async route=>{
    const u=new URL(route.request().url());
    if(u.hostname==='clubdisplay.ntc93.de'){
      if(u.pathname.endsWith('.php'))return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'unavailable',matches:[],events:[],posts:[]})});
      const file=path.join(process.cwd(),'public',u.pathname==='/'?'index.html':u.pathname.slice(1));
      if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
    }
    return route.abort();
  });
  await page.goto('https://clubdisplay.ntc93.de/');
  await page.waitForFunction(()=>typeof syncHeaderDecor==='function');
  for(const [width,height] of [[1920,1080],[1440,900],[390,720]]){
    await page.setViewportSize({width,height});
    for(const icon of ['cloudy','fog','rain','snow','clear-night','clear-day']){
      const now=Date.parse(icon==='clear-day'?'2026-11-26T12:00:00+01:00':'2026-11-26T19:00:00+01:00');
      await page.evaluate(({now,icon})=>{
        weatherData={status:'ok',fetchedAt:now/1000,current:{time:now/1000,icon},sun:{sunrise:Date.parse('2026-11-26T07:45:00+01:00')/1000,sunset:Date.parse('2026-11-26T15:59:00+01:00')/1000}};
        syncHeaderDecor(now);
      },{now,icon});
      for(let frame=0;frame<3;frame++){
        const g=await page.evaluate(()=>{
          const h=document.querySelector('body>header'),l=h.querySelector('.header-weather-effect'),p=l.querySelector('.header-weather-particles');
          const a=h.getBoundingClientRect(),b=l.getBoundingClientRect(),c=p.getBoundingClientRect(),s=getComputedStyle(l),q=getComputedStyle(p),border=parseFloat(getComputedStyle(h).borderBottomWidth);
          return {left:b.left-a.left,right:a.right-b.right,top:b.top-a.top,bottom:a.bottom-b.bottom,border,transform:s.transform,animation:s.animationName,clip:s.clipPath,particleClip:q.clipPath,particleOverflow:q.overflow,innerLeft:c.left-b.left,innerRight:b.right-c.right,innerTop:c.top-b.top,innerBottom:b.bottom-c.bottom,season:h.querySelector('.header-season-effect').getBoundingClientRect().top-a.top};
        });
        assert.ok(Math.abs(g.left)<1&&Math.abs(g.right)<1&&Math.abs(g.top)<1,JSON.stringify({width,icon,g}));
        assert.ok(Math.abs(g.bottom-g.border)<1,JSON.stringify({width,icon,g}));
        assert.equal(g.transform,'none');assert.equal(g.animation,'none');
        assert.equal(g.clip,'inset(0px)');assert.equal(g.particleClip,'inset(0px)');assert.equal(g.particleOverflow,'hidden');
        assert.ok(g.innerLeft>=7&&g.innerRight>=7&&g.innerTop>=4&&g.innerBottom>=6,JSON.stringify(g));
        await page.waitForTimeout(70);
      }
    }
  }
  console.log('Header weather: fixed full-width background, clipped inset particles and unobstructed border verified in Chromium');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
