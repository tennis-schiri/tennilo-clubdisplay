'use strict';
const assert=require('node:assert/strict');
const {cdCreateAutoUpdater}=require('../public/assets/auto-update.js');
const old='a'.repeat(40),next='b'.repeat(40);
function setup(){
  let safe=true,release=next,htmlBuild=next,fail=false,blockDuringPage=false,requests=[],nav=[];
  const updater=cdCreateAutoUpdater({
    build:old,safe:()=>safe,pageUrl:()=> 'https://clubdisplay.ntc93.de/?other=1#test',
    parseBuild:s=>s,navigate:u=>nav.push(u),
    request:async(u,opts)=>{
      requests.push(u);assert.equal(opts.cache,'no-store');assert.equal(opts.redirect,'error');
      if(fail)throw Error('offline');
      if(new URL(u).pathname==='/release.json')return {ok:true,json:async()=>({build:release})};
      if(blockDuringPage)safe=false;
      return {ok:true,text:async()=>htmlBuild};
    }
  });
  return {updater,requests,nav,setSafe:v=>safe=v,setRelease:v=>release=v,setHtml:v=>htmlBuild=v,setFail:v=>fail=v,block:()=>blockDuringPage=true};
}
(async()=>{
  let t=setup();t.setRelease(old);await t.updater.check();assert.equal(t.nav.length,0);assert.equal(t.requests.length,1);
  t=setup();t.setSafe(false);await t.updater.check();assert.equal(t.nav.length,0);assert.equal(t.requests.length,1);await t.updater.check(true);assert.equal(t.requests.length,1);
  t.setSafe(true);await t.updater.check(true);assert.equal(t.nav.length,1);const url=new URL(t.nav[0]);assert.equal(url.searchParams.get('cd-release'),next);assert.equal(url.searchParams.get('other'),'1');assert.equal(url.hash,'#test');
  await t.updater.check();assert.equal(t.nav.length,1);
  t=setup();t.setFail(true);await t.updater.check();assert.equal(t.nav.length,0);
  t=setup();t.setHtml(old);await t.updater.check();assert.equal(t.nav.length,0);
  t=setup();t.block();await t.updater.check();assert.equal(t.nav.length,0);
  t=setup();t.setRelease('invalid');await t.updater.check();assert.equal(t.nav.length,0);
  t=setup();await Promise.all([t.updater.check(),t.updater.check()]);assert.equal(t.nav.length,1);
  console.log('AutoUpdate: unchanged, deferred, offline, mismatch, concurrent checks, navigation OK');
})().catch(e=>{console.error(e);process.exitCode=1;});
