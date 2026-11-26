'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/app.js','utf8');
const ctx=vm.createContext({Date,Intl,nightIdle:300000,nightHourFormat:new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',hourCycle:'h23'})});
vm.runInContext(source.slice(source.indexOf('function nightWindow('),source.indexOf('function renderNightClock(')),ctx);
const check=(iso,expected)=>assert.equal(vm.runInContext('nightWindow('+Date.parse(iso)+')',ctx),expected,iso);
for(const [iso,expected] of [['2026-11-26T21:59:59+01:00',false],['2026-11-26T22:00:00+01:00',true],['2026-11-27T09:59:59+01:00',true],['2026-11-27T10:00:00+01:00',false],['2027-07-01T22:00:00+02:00',true],['2027-07-02T10:00:00+02:00',false]])check(iso,expected);
ctx.now=Date.parse('2026-11-26T23:00:00+01:00');
for(const [age,initial,booking,modal,expected] of [[0,true,false,false,true],[299999,false,false,false,false],[300000,false,false,false,true],[999999,false,true,false,false],[999999,false,false,true,false]]){
 assert.equal(vm.runInContext('nightShouldSleep(now,now-'+age+','+initial+','+booking+','+modal+')',ctx),expected);
}
console.log('NTC night mode: Berlin time, DST, five-minute inactivity and booking/dialog protection OK');
