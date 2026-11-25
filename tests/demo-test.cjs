'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/app.js','utf8');
const a=source.indexOf('const demoStart='),b=source.indexOf('// Match welcome slides',a);assert(a>=0&&b>a);
const now=Date.parse('2026-11-25T14:29:00Z');class Clock extends Date{static now(){return now;}}
const ctx=vm.createContext({Date:Clock,Intl,tr:(de,en)=>de,esc:s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;')});
vm.runInContext(source.slice(a,b),ctx);const run=s=>vm.runInContext(s,ctx);
assert.equal(run('demoLeague().matches[0].date'),'2026-11-25T14:30:00.000Z');
assert.equal(run('demoLeague().matches[0].courts.length'),3);
assert.equal(run('demoNews().posts.length'),3);
assert.ok(run('demoNews().posts.every(p=>p.complete&&p.article.includes("Beispielartikel")&&p.image.startsWith("data:image/svg+xml"))'));
assert.equal(run('demoSponsorCards().match(/<figure/g).length'),15);
assert.ok(!run('demoSponsorCards().includes("<a ")'));
ctx.hours=Array.from({length:3},(_,i)=>({start:new Date(Date.parse('2026-11-25T14:00:00Z')+i*3600000).toISOString(),end:new Date(Date.parse('2026-11-25T14:00:00Z')+(i+1)*3600000).toISOString()}));
assert.equal(run('demoOccupancy(hours).slots.length'),27);
assert.equal(run('demoOccupancy(hours).slots.find(s=>s.area==="outdoor"&&s.court===1).status'),'partial');
assert.ok(run('demoOccupancy(hours).slots.find(s=>s.area==="outdoor"&&s.court===1).bookings[0].displayLabel.includes("Heimspiel")'));
assert.equal(run('demoOccupancy(hours).slots.filter(s=>s.area==="indoor").length'),6);
console.log('TENNILO demo tests OK');

// Kiosk visitors must never be able to open the administration portal from the display footer.
const displayHtml=fs.readFileSync('public/index.html','utf8');
assert.doesNotMatch(displayHtml, /id="admin-link"|class="admin-link"|href="[^"]*\/admin\/\?portal=club/);
assert.doesNotMatch(source, /admin-link|\/admin\/\?portal=club/);

// Exercise the real fixture/calendar rendering with dates spanning the new year.
const dateCtx=vm.createContext({Intl,Date:class extends Date{constructor(...args){super(...(args.length?args:['2026-11-25T12:00:00+01:00']));}},lang:'de',view:'info',matchFilter:'home',pilot:true,tr:(de,en)=>dateCtx.lang==='de'?de:en,t:()=>({upcoming:'Termine'}),esc:s=>String(s??''),shortClub:s=>s,teamAndLeague:()=>({team:'Herren',league:'VL'}),fixtureCourtNumbers:()=>[],leagueData:{status:'ok',matches:[{date:'2027-01-01T10:00:00+01:00',home:'NTC',away:'TC',isHome:true,score:null}]},eventData:{status:'ok',events:[{title:'Regel-Workshop',start:'2027-03-04T19:00:00+01:00',end:'2027-03-04T21:00:00+01:00'},{title:'Jahreswechsel',start:'2026-12-31T20:00:00+01:00',end:'2027-01-01T01:00:00+01:00'}]}});
for(const [start,end] of [['function matchView(){','async function refreshLeague()'],['function eventView(){','async function refreshEvents()']])vm.runInContext(source.slice(source.indexOf(start),source.indexOf(end)),dateCtx);
const dates=code=>[...vm.runInContext(code,dateCtx).matchAll(/class="fixture-day">([^<]+)</g)].map(m=>m[1]);
assert.deepEqual(dates('matchView()'),['01.01.2027']);
assert.deepEqual(dates('eventView()'),['04.03.2027','31.12.2026']);
assert.match(vm.runInContext('eventView()',dateCtx),/01\.01\.2027/);
dateCtx.lang='en';assert.deepEqual(dates('matchView()'),['01/01/2027']);assert.deepEqual(dates('eventView()'),['04/03/2027','31/12/2026']);
dateCtx.view='teams';dateCtx.matchFilter='results';dateCtx.leagueData.matches[0].score='6:3';assert.deepEqual(dates('matchView()'),['01/01/2027']);
console.log('Fixture and event year labels in DE/EN, results and cross-year dates OK');

const managedSource=fs.readFileSync('public/admin/player.js','utf8');
const managedCtx=vm.createContext({Date,Intl,s:{modules:['calendar'],feeds:{calendar:{status:'ok',rows:[{title:'Workshop',start:'2027-03-04T19:00:00+01:00',end:'2027-03-04T21:00:00+01:00'},{title:'Unvollständig',start:'2027-03-04T19:00:00+01:00'}]}}},start:Date.parse('2027-03-04T19:00:00+01:00')});
vm.runInContext(managedSource.slice(managedSource.indexOf('function activeEventGreetingRows('),managedSource.indexOf('function eventGreetingPeriod(')),managedCtx);
const managedRun=s=>vm.runInContext(s,managedCtx);
assert.equal(managedRun('activeEventGreetingRows(s,start-1800001).length'),0);
assert.equal(managedRun('activeEventGreetingRows(s,start-1800000).length'),1);
assert.equal(managedRun('activeEventGreetingRows(s,start+7199999).length'),1);
assert.equal(managedRun('activeEventGreetingRows(s,start+7200000).length'),0);
assert.equal(managedRun('activeEventGreetingRows({...s,modules:["welcome"]},start).length'),0);
console.log('Managed calendar module gating and welcome timing OK');
