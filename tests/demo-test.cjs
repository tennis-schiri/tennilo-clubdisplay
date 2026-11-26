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
for(const [start,end] of [['function fixtureAssignedCourts(m){','function matchGreetingView(key){'],['function matchView(){','async function refreshLeague()'],['function eventView(){','async function refreshEvents()']])vm.runInContext(source.slice(source.indexOf(start),source.indexOf(end)),dateCtx);
const dates=code=>[...vm.runInContext(code,dateCtx).matchAll(/class="fixture-day">([^<]+)</g)].map(m=>m[1]);
assert.deepEqual(dates('matchView()'),['01.01.2027']);
assert.deepEqual(dates('eventView()'),['04.03.2027','31.12.2026','01.01.2027']);
assert.match(vm.runInContext('eventView()',dateCtx),/01\.01\.2027/);
dateCtx.lang='en';assert.deepEqual(dates('matchView()'),['01/01/2027']);assert.deepEqual(dates('eventView()'),['04/03/2027','31/12/2026','01/01/2027']);
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

dateCtx.lang='de';dateCtx.view='info';dateCtx.eventData.events.push({title:'NEUENHAGEN OPEN',start:'2027-06-15T00:00:00+02:00',end:'2027-06-20T23:59:59+02:00',allDay:true});
const multiHtml=vm.runInContext('eventView()',dateCtx);
const rangeBlocks=[...multiHtml.matchAll(/<time class="fixture-date-block fixture-date-range"[^>]*>(.*?)<\/time>/g)].map(m=>m[1]);
assert.equal(rangeBlocks.length,2);
assert.match(rangeBlocks[0],/31\.12\.2026.*bis.*01\.01\.2027/);
assert.match(rangeBlocks[1],/15\.06\.2027.*bis.*20\.06\.2027/);
assert.doesNotMatch(rangeBlocks[1],/Ganztägig|All day/);
assert.match(multiHtml,/<strong>Ganztägig<\/strong>/);
assert.doesNotMatch(multiHtml,/class="fixture-details event-details"/);
console.log('Multi-day dates fully inside leading date tiles OK');

for(const block of rangeBlocks)assert.doesNotMatch(block,/Mehrtägig|Multi-day/);
dateCtx.lang='en';assert.doesNotMatch(vm.runInContext('eventView()',dateCtx),/Multi-day/);

const enCalendar=vm.runInContext('eventView()',dateCtx);
for(const block of enCalendar.matchAll(/<time class="fixture-date-block[^>]*>(.*?)<\/time>/g))assert.doesNotMatch(block[1],/All day/);
assert.match(enCalendar,/<strong>All day<\/strong>/);

assert.doesNotMatch(source,/Interaktiver Modus|Interactive mode/);
assert.ok(source.includes("$('mode').hidden=view!=='info'"));
assert.ok(source.includes("$('mode-dot').hidden=view!=='info'"));
assert.match(displayHtml,/id="mode-dot"/);

// Home venue suppression and away labels apply to both languages and results.
dateCtx.lang='de';dateCtx.view='teams';dateCtx.matchFilter='all';
const baseFixture={date:'2027-01-02T10:00:00+01:00',home:'NTC',away:'TC',isHome:true,score:null};
dateCtx.leagueData.matches=[{...baseFixture,venue:' NEUENHAGEN '},{...baseFixture,venue:'Halle Hoppegarten'},{...baseFixture,home:'TC Gast',away:'NTC',isHome:false,venue:'Berlin',courts:[1,2]}];
const fixtureHtml=vm.runInContext('matchView()',dateCtx);
const fixtureCards=[...fixtureHtml.matchAll(/<article class="fixture-card">(.*?)<\/article>/g)].map(m=>m[1]);
assert.equal(fixtureCards.length,3);
assert.doesNotMatch(fixtureCards[0],/fixture-venue|Heimspiel|fixture-side|NEUENHAGEN/);
assert.match(fixtureCards[1],/fixture-match-meta.*fixture-venue.*Halle Hoppegarten/);
assert.match(fixtureCards[2],/fixture-away-strip.*fixture-away-label">Auswärts<.*fixture-match-meta.*fixture-venue.*Berlin/);
assert.doesNotMatch(fixtureCards[2],/fixture-court-icon|fixture-side/);
dateCtx.lang='en';assert.match(vm.runInContext('matchView()',dateCtx),/fixture-away-label">Away</);
console.log('Match card layout: venues and away markers OK');

dateCtx.lang='de';assert.match(vm.runInContext('matchView()',dateCtx),/class="fixture-venue"><span>Halle<\/span>/);
assert.doesNotMatch(vm.runInContext('matchView()',dateCtx),/Platzanlage/);
dateCtx.lang='en';assert.match(vm.runInContext('matchView()',dateCtx),/class="fixture-venue"><span>Indoor venue<\/span>/);

dateCtx.lang='de';
dateCtx.statusDate='2027-01-02T10:00:00+01:00';
dateCtx.statusStart=Date.parse(dateCtx.statusDate);
const statusAt=delta=>vm.runInContext('fixtureStatus(statusDate,statusStart+'+delta+').value',dateCtx);
assert.equal(statusAt(-5*86400000),'In 5 Tagen');
assert.equal(statusAt(-86400000),'Morgen');
assert.equal(statusAt(-7200000),'Heute');
assert.equal(statusAt(-3600000),'In 60 Min.');
assert.equal(statusAt(-2100000),'In 35 Min.');
assert.equal(statusAt(-1),'In 1 Min.');
assert.equal(statusAt(0),'Läuft gerade');
assert.equal(statusAt(7200000),'Läuft gerade');
assert.equal(statusAt(15*3600000),'Ergebnis ausstehend');
assert.match(vm.runInContext('fixtureStatusMarkup({date:statusDate,score:"4:2"})',dateCtx),/Ergebnis.*4:2/);
assert.doesNotMatch(vm.runInContext('fixtureStatusMarkup({date:statusDate,score:null})',dateCtx),/Ergebnis<\/span>|– : –/);
dateCtx.lang='en';assert.equal(statusAt(0),'In progress');assert.equal(statusAt(-2100000),'In 35 min');
console.log('Fixture countdown, scheduled running status and result precedence OK');

const style=fs.readFileSync('public/assets/app.css','utf8');assert.match(style,/\.event-meta-cell>strong\{[^}]*font-weight:600/);assert.match(style,/\.occupancy-roster-indoor \.occupancy-unknown\{[^}]*background:#284b69/);

// Two-column weather overview uses equal-height cards; the single radar page is untouched.
assert.match(style,/\.weather-screen #screen\[data-weather-layout="all"\] \.wx-layout\{align-items:stretch\}/);
assert.match(source,/leftHeight-overhead/);

const demoCtx=vm.createContext({Date,tr:(de,en)=>de});
vm.runInContext(source.slice(source.indexOf('function demoPartnerRows(){'),source.indexOf('function partnerBoardView(active){')),demoCtx);
assert.equal(vm.runInContext('demoPartnerRows().length',demoCtx),5);
assert.ok(vm.runInContext('demoPartnerRows().every(r=>r.own_gender_label&&r.wanted_gender_label&&r.own_age_label)',demoCtx));
assert.match(source,/demoQrBase\+'\?community='/);
assert.match(source,/demoQrBase\+'\?ref='/);
assert.match(source,/demoQrBase\+'\?pdf='/);
assert.match(source,/function demoRestaurantView\(/);
console.log('Demo parity: fictional partner requests, QR destinations, newsletter PDF and restaurant');

const iconCtx=vm.createContext({});
vm.runInContext(source.slice(source.indexOf('function sectionIcon(key){'),source.indexOf('function renderNav(){')),iconCtx);
for(const key of ['home','courts','calendar','teams','weather','news','partners','info']){
  const svg=vm.runInContext('sectionIcon('+JSON.stringify(key)+')',iconCtx);
  assert.match(svg,/^<svg class="section-icon"/);
  assert.match(svg,/aria-hidden="true"/);
  assert.match(svg,/<\/svg>$/);
}
assert.equal(vm.runInContext('sectionIcon("unknown")',iconCtx),'');
assert.match(vm.runInContext('whatsappLogo()',iconCtx),/class="whatsapp-logo"/);
assert.ok(source.includes('sectionIcon(key)')&&source.includes('sectionIcon(k)'));
console.log('Shared START and navigation icon markup OK');

const expandClasses=new Set(),expandAttrs={},expandLabel={textContent:''},expandButton={setAttribute(k,v){expandAttrs[k]=v},querySelector(){return expandLabel}};
const expandCtx=vm.createContext({pilot:true,view:'courts',tr:(de,en)=>de,esc:s=>String(s),button:()=>'<button>Zurück</button>',$:id=>id==='booking-expand'?expandButton:null,document:{body:{classList:{toggle(k,v){v?expandClasses.add(k):expandClasses.delete(k)}}}}});
vm.runInContext(source.slice(source.indexOf("let terminalStatus=null"),source.indexOf("function courtList()")),expandCtx);
const expandRun=s=>vm.runInContext(s,expandCtx);
expandRun("terminalStatus='ready'");
assert.match(expandRun("ebusyView()"),/data-action="booking-expand".*Volle Höhe/);
assert.match(expandRun("ebusyView()"),/src="terminal.php"/);
expandRun("toggleBookingExpansion()");
assert.ok(expandClasses.has('booking-expanded'));assert.equal(expandAttrs['aria-pressed'],'true');assert.equal(expandLabel.textContent,'Normale Ansicht');
expandRun("toggleBookingExpansion()");
assert.ok(!expandClasses.has('booking-expanded'));assert.equal(expandAttrs['aria-pressed'],'false');
expandRun("toggleBookingExpansion();view='home';syncBookingExpansion()");
assert.ok(!expandClasses.has('booking-expanded'));assert.equal(expandRun('bookingExpanded'),false);
expandRun("view='courts';courtMode='indoor';toggleBookingExpansion()");
assert.ok(!expandClasses.has('booking-expanded'));assert.doesNotMatch(expandRun('ebusyView()'),/data-action="booking-expand"/);
console.log('Booking terminal expansion toggle, in-place resize and reset OK');

 
// Weekend rotation uses exactly the same fixture and event cards as their own views.
dateCtx.lang='de';dateCtx.view='info';
dateCtx.weekendMatch={date:'2027-01-02T10:00:00+01:00',home:'NTC',away:'TC',isHome:true,score:null,courts:[1,2]};
dateCtx.weekendEvent={title:'Vereinstermin',start:'2027-01-02T12:00:00+01:00',end:'2027-01-03T14:00:00+01:00',venue:'Clubhaus'};
dateCtx.smartWeekend=()=>({saturday:20820,sunday:20821,rows:[{kind:'match',match:dateCtx.weekendMatch},{kind:'event',event:dateCtx.weekendEvent}]});
vm.runInContext(source.slice(source.indexOf('function smartWeekendView('),source.indexOf('function smartCountdownView(')),dateCtx);
const weekendHtml=vm.runInContext('smartWeekendView("weekend:0")',dateCtx);
assert.ok(weekendHtml.includes(vm.runInContext('fixtureMatchCard(weekendMatch)',dateCtx)));
assert.ok(weekendHtml.includes(vm.runInContext('fixtureEventCard(weekendEvent,true)',dateCtx)));
assert.match(weekendHtml,/fixture-date-range/);
assert.match(weekendHtml,/Clubhaus/);
assert.doesNotMatch(weekendHtml,/smart-weekend-row/);
dateCtx.lang='en';assert.match(vm.runInContext('smartWeekendView("weekend:0")',dateCtx),/This weekend/);
console.log('Weekend shares match and calendar card rendering in DE/EN');
