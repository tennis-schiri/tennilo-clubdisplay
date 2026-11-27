'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/app.js','utf8');
const ctx=vm.createContext({Date,Intl,nightIdle:300000,eventData:{status:'ok',events:[]}});
vm.runInContext(source.slice(source.indexOf('const ntcGastroSchedule='),source.indexOf('function renderNightClock(')),ctx);
const check=(iso,expected)=>assert.equal(vm.runInContext('nightWindow('+Date.parse(iso)+')',ctx),expected,iso);
for(const [iso,expected] of [
 ['2026-11-26T21:59:59+01:00',false],['2026-11-26T22:00:00+01:00',true],['2026-11-27T13:59:59+01:00',true],['2026-11-27T14:00:00+01:00',false],
 ['2026-11-28T11:59:59+01:00',true],['2026-11-28T12:00:00+01:00',false],['2026-11-30T16:00:00+01:00',true],
 ['2026-12-24T18:00:00+01:00',true],['2026-12-25T12:00:00+01:00',false],['2026-12-31T13:59:00+01:00',true],['2027-01-01T14:00:00+01:00',false],
 ['2027-07-01T13:59:59+02:00',true],['2027-07-01T14:00:00+02:00',false],['2027-07-01T22:00:00+02:00',true]])check(iso,expected);
ctx.eventData={status:'ok',events:[{title:'NTC-Geburtstag',venue:'Clubhaus NTC 93',start:'2026-12-06T11:00:00+01:00',end:'2026-12-06T15:00:00+01:00'},{title:'Workshop',venue:'Clubhaus NTC 93',start:'2026-11-30T18:00:00+01:00',end:'2026-11-30T20:00:00+01:00'},{title:'Auswärts',venue:'Sporthalle',start:'2026-11-30T10:00:00+01:00',end:'2026-11-30T12:00:00+01:00'}]};
for(const [iso,expected] of [['2026-12-06T10:29:59+01:00',true],['2026-12-06T10:30:00+01:00',false],['2026-12-06T15:30:00+01:00',false],['2026-12-06T21:59:00+01:00',false],['2026-12-06T22:00:00+01:00',true],['2026-11-30T10:00:00+01:00',true],['2026-11-30T17:29:59+01:00',true],['2026-11-30T17:30:00+01:00',false],['2026-11-30T20:00:00+01:00',true]])check(iso,expected);
ctx.eventData={status:'unavailable',events:[]};check('2026-12-06T10:30:00+01:00',false);
ctx.now=Date.parse('2026-11-26T23:00:00+01:00');
for(const [age,initial,modal,expected] of [[0,true,false,true],[299999,false,false,false],[300000,false,false,true],[999999,false,true,false]])assert.equal(vm.runInContext('nightShouldSleep(now,now-'+age+','+initial+','+modal+')',ctx),expected);
console.log('NTC rest mode: gastro hours, holidays, Monday events, birthday, DST, touch and modal protection OK');
