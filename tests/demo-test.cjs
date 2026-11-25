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
