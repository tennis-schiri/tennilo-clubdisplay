'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/app.js','utf8');
const begin=source.indexOf('function matchGreetingKey('),end=source.indexOf('function teamAndLeague(',begin);
assert(begin>=0&&end>begin);
const start=Date.parse('2027-05-09T10:00:00+02:00');
const home={date:'2027-05-09T10:00:00+02:00',home:'NTC 93',away:'Testclub',league:'H30',isHome:true,score:null};
const away={...home,isHome:false};
const second={...home,away:'Testclub 2',league:'D30'};
const data={status:'ok',matches:[home,away,second,{...home,score:'5:1'},{...home,cancelled:true},home]};
const ctx=vm.createContext({Date,Intl,data,start,home,second,pilot:true,leagueData:data,baseScreens:['welcome','weather','teams'],screens:['welcome','weather','teams'],slide:1,view:'info',greetingMatches:new Map(),greetingEvents:new Map(),eventData:null,started:start,infoPaused:false,infoElapsed:0,infoPauseUntil:0,render(){},infoControls(){},lang:'de',tr:(de,en)=>de,esc:s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'),shortClub:s=>s,teamAndLeague:()=>({team:'Herren 30',league:'VL'}),fixtureCourtNumbers:r=>r||[]});
vm.runInContext(source.slice(begin,end),ctx);
const run=s=>vm.runInContext(s,ctx);
assert.equal(run('activeMatchGreetings(data,start-1800001).length'),0);
assert.equal(run('activeMatchGreetings(data,start-1800000).length'),2);
assert.equal(run('activeMatchGreetings(data,start+1799999).length'),2);
assert.equal(run('activeMatchGreetings(data,start+1800000).length'),0);
run('syncMatchGreetings(start)');
assert.equal(ctx.screens.length,5);
assert.equal(ctx.screens[ctx.slide],'weather');
assert.ok(run('matchGreetingView(matchGreetingKey(home)).includes("Platzzuordnung noch nicht verfügbar")'));
run('home.courts=["1","2","3"];syncMatchGreetings(start)');
assert.ok(run('matchGreetingView(matchGreetingKey(home)).includes("Platz 3")'));
run('slide=screens.indexOf(matchGreetingKey(home));infoPaused=true;syncMatchGreetings(start+1800000)');
assert.equal(ctx.screens.length,3);
assert.equal(ctx.screens[ctx.slide],'welcome');
assert.equal(ctx.infoPaused,false);
run('pilot=false;syncMatchGreetings(start)');
assert.equal(ctx.screens.length,5);
assert.ok(run('matchGreetingView(matchGreetingKey(home)).includes("Demo-Club")'));
console.log('Match greeting tests OK');

const event={title:'Regel-Workshop <Test>',start:'2027-05-09T10:00:00+02:00',end:'2027-05-09T12:00:00+02:00',venue:'Clubhaus'};
ctx.eventData={status:'ok',events:[event,event,{...event,cancelled:true},{...event,title:'Ohne Ende',end:event.start},{...event,title:'Ungültig',end:'invalid'}]};
assert.equal(run('activeEventGreetings(eventData,start-1800001).length'),0);
assert.equal(run('activeEventGreetings(eventData,start-1800000).length'),1);
assert.equal(run('activeEventGreetings(eventData,start+7199999).length'),1);
assert.equal(run('activeEventGreetings(eventData,start+7200000).length'),0);
run('syncMatchGreetings(start)');
assert.equal(ctx.screens.length,6);
assert.ok(run('eventGreetingView(eventGreetingKey(eventData.events[0])).includes("Regel-Workshop &lt;Test&gt;")'));
assert.ok(run('eventGreetingView(eventGreetingKey(eventData.events[0])).includes("09.05.2027 · 10:00 – 12:00 Uhr")'));
run('slide=screens.indexOf(eventGreetingKey(eventData.events[0]));infoPaused=true;syncMatchGreetings(start+1800000)');
assert.ok(ctx.screens[ctx.slide].startsWith('event-greeting:'));
run('syncMatchGreetings(start+7200000)');
assert.equal(ctx.screens[ctx.slide],'welcome');assert.equal(ctx.infoPaused,false);
console.log('Event greetings: timing, duplicates, cancellation, escaping and rotation OK');

ctx.eventData={status:'ok',events:[
 {title:'Jahreswechsel',start:'2026-12-31T23:00:00+01:00',end:'2027-01-01T02:00:00+01:00'},
 {title:'Neujahrsempfang',start:'2027-01-01T00:00:00+01:00',end:'2027-01-01T01:00:00+01:00'}
]};
ctx.cross=Date.parse('2027-01-01T00:15:00+01:00');
assert.equal(run('activeEventGreetings(eventData,cross).length'),2);
run('syncMatchGreetings(cross)');
assert.ok(run('eventGreetingView(eventGreetingKey(eventData.events[0])).includes("31.12.2026 · 23:00 – 01.01.2027 · 02:00 Uhr")'));
assert.equal(run('activeEventGreetings(eventData,cross+3600000).length'),1);
assert.equal(run('activeEventGreetings({...eventData,status:"unavailable"},cross).length'),0);
console.log('Overlapping and overnight event greetings OK');

ctx.fullBase=['welcome','weather','calendar','teams','news','partner'];
ctx.testNow=Date.parse('2027-05-09T09:00:00+02:00');
ctx.eventDayData={status:'ok',events:[{title:'Turnier',start:'2027-05-09T11:00:00+02:00',end:'2027-05-09T16:00:00+02:00'}]};
assert.equal(run('smartSeason(Date.parse("2027-04-30T22:00:00Z"))'),'summer');
assert.equal(run('smartSeason(Date.parse("2027-09-30T22:00:00Z"))'),'winter');
assert.equal(run('smartHoliday(Date.parse("2027-12-24T12:00:00+01:00"))'),'holiday:christmas');
assert.equal(run('smartHoliday(Date.parse("2027-12-27T12:00:00+01:00"))'),'');
assert.equal(run('smartHoliday(Date.parse("2027-12-31T12:00:00+01:00"))'),'holiday:eve');
assert.equal(run('smartHoliday(Date.parse("2028-01-01T12:00:00+01:00"))'),'holiday:newyear');
assert.equal(run('smartScreenKey("priority:teams")'),'teams');
assert.equal(run('smartScreenKey("priority:greeting:test")'),'greeting:test');
assert.equal(run('smartRotation(fullBase,data,eventDayData,["greeting:test"],[],testNow).filter(k=>smartScreenKey(k)==="teams").length'),2);
assert.equal(run('smartRotation(fullBase,data,eventDayData,[],[],testNow).filter(k=>smartScreenKey(k)==="calendar").length'),2);
assert.equal(run('smartRotation(fullBase,data,eventDayData,["greeting:test"],[],testNow).includes("priority:greeting:test")'),true);
assert.equal(run('smartRotation(fullBase,{status:"unavailable"},null,[],[],testNow).length'),6);
assert.equal(run('smartRotation(fullBase,null,eventDayData,[],[],testNow+9*3600000).includes("priority:calendar")'),false);
assert.equal(run('smartRotation(fullBase,null,null,[],[],Date.parse("2027-12-25T12:00:00+01:00")).includes("holiday:christmas")'),true);
console.log('Smart seasonal dates, priorities, expiry and alias routing OK');

ctx.juneEvents={status:'ok',events:[{title:'22. NEUENHAGEN OPEN',start:'2027-06-15T00:00:00+02:00',end:'2027-06-20T23:59:59+02:00'},{title:'10. NEUENHAGEN SENIOR OPEN',start:'2027-06-18T00:00:00+02:00',end:'2027-06-23T23:59:59+02:00'}]};
assert.equal(run('smartOpenCountdown(juneEvents,Date.parse("2027-03-06T12:00:00+01:00")).length'),0);
assert.equal(run('smartOpenCountdown(juneEvents,Date.parse("2027-03-07T12:00:00+01:00"))[0].days'),100);
assert.equal(run('smartOpenCountdown(juneEvents,Date.parse("2027-03-10T12:00:00+01:00")).length'),2);
assert.equal(run('smartOpenCountdown(juneEvents,Date.parse("2027-06-14T23:30:00+02:00")).length'),1);
assert.equal(run('smartOpenCountdown(juneEvents,Date.parse("2027-06-18T00:00:00+02:00")).length'),0);
ctx.weekNow=Date.parse('2027-05-06T12:00:00+02:00');
assert.equal(run('smartWeekend(data,eventDayData,weekNow).rows.length'),3);
assert.equal(run('smartWeekend(data,eventDayData,weekNow-86400000)'),null);
assert.equal(run('smartRotation(fullBase,data,eventDayData,[],[],weekNow).includes("weekend:0")'),true);
assert.equal(run('smartRotation(fullBase,null,juneEvents,[],[],Date.parse("2027-03-07T12:00:00+01:00")).includes("open-countdown")'),true);
console.log('Weekend preview and individual 100-calendar-day countdowns OK');

ctx.boardTime=Date.parse('2027-06-15T12:00:00+02:00');run('partnerBoardData={enabled:true,rows:[{ref:"ABCDEF1234",category:"mixed",skill:"any",availability:"weekend",period:"morning",expires:boardTime/1000+60,contact:"SECRET"},{ref:"INVALID",category:"mixed",skill:"any",availability:"weekend",period:"morning",expires:boardTime/1000+60}]}');assert.equal(run('partnerBoardRows(boardTime).length'),1);assert.equal(run('partnerBoardScreens(boardTime)[0]'),'partners:0');assert.equal(run('partnerBoardScreens(boardTime+60000).length'),1);run('partnerBoardData=null');assert.equal(run('partnerBoardScreens(boardTime).length'),0);console.log('Partner board display filtering and expiration OK');

// Advent weekends: local Berlin day, all four candles, Christmas priority and year boundaries.
for(const [date,expected] of [
 ['2026-11-27T23:59:59+01:00',''],['2026-11-28T00:00:00+01:00','holiday:advent:1'],
 ['2026-11-29T23:59:59+01:00','holiday:advent:1'],['2026-11-30T00:00:00+01:00',''],
 ['2026-12-05T12:00:00+01:00','holiday:advent:2'],['2026-12-13T12:00:00+01:00','holiday:advent:3'],
 ['2026-12-20T12:00:00+01:00','holiday:advent:4'],['2026-12-24T00:00:00+01:00','holiday:christmas'],
 ['2027-12-24T12:00:00+01:00','holiday:christmas'],['2028-12-24T12:00:00+01:00','holiday:christmas'],
 ['2022-12-18T12:00:00+01:00','holiday:advent:4'],['2022-12-25T12:00:00+01:00','holiday:christmas']]){
  ctx.checkTime=Date.parse(date);assert.equal(run('smartHoliday(checkTime)'),expected,date);
}
for(let n=1;n<=4;n++){
  ctx.candles=n;
  assert.equal((run('adventCandles(candles)').match(/fill="#ffce72"/g)||[]).length,n);
  assert.match(run('smartHolidayView("holiday:advent:"+candles)'),/holiday-advent/);
}
vm.runInContext(source.slice(source.indexOf('function headerWeatherEffect('),source.indexOf('function syncHeaderDecor(')),ctx);
ctx.weatherNow=Date.parse('2026-11-28T12:00:00+01:00');
for(const [icon,effect] of [['rain','rain'],['snow','snow'],['sleet','sleet'],['hail','hail'],['thunderstorm','storm'],['fog','fog'],['wind','wind'],['clear-day','sun'],['clear-night','stars'],['cloudy','cloud'],['unknown',''],['__proto__','']]){
  ctx.weatherTest={status:'ok',fetchedAt:ctx.weatherNow/1000,current:{time:ctx.weatherNow/1000,icon}};
  assert.equal(run('headerWeatherEffect(weatherTest,weatherNow)'),effect,icon);
}
ctx.weatherTest={status:'stale',fetchedAt:ctx.weatherNow/1000,current:{time:ctx.weatherNow/1000,icon:'rain'}};
assert.equal(run('headerWeatherEffect(weatherTest,weatherNow)'),'');
ctx.weatherTest.status='ok';ctx.weatherTest.fetchedAt-=1801;
assert.equal(run('headerWeatherEffect(weatherTest,weatherNow)'),'');
assert.equal(run('headerSeasonEffect(weatherNow)'),'advent-1');
assert.equal(run('headerSeasonEffect(Date.parse("2026-12-25T12:00:00+01:00"))'),'christmas');
assert.equal(run('headerSeasonEffect(Date.parse("2026-12-27T12:00:00+01:00"))'),'');
console.log('Advent and holiday dates, candle count, live weather decorations and stale data safeguards OK');

// NTC anniversary is club-specific, repeats annually, and coexists with Advent.
ctx.birthdayTime=Date.parse('2026-12-06T12:00:00+01:00');
assert.equal(run('ntcBirthdayAge(birthdayTime)'),33);
assert.equal(run('ntcBirthdayAge(Date.parse("2027-12-06T12:00:00+01:00"))'),34);
assert.equal(run('ntcBirthdayAge(Date.parse("2026-12-05T23:59:59+01:00"))'),null);
assert.equal(run('ntcBirthdayAge(Date.parse("2026-12-07T00:00:00+01:00"))'),null);
assert.equal(run('ntcBirthdayAge(Date.parse("1992-12-06T12:00:00+01:00"))'),null);
run('pilot=true');
const birthdayRotation=run('smartRotation(fullBase,null,null,[],[],birthdayTime)');
assert.ok(birthdayRotation.includes('holiday:ntc-birthday'));
assert.ok(birthdayRotation.includes('holiday:advent:2'));
assert.ok(birthdayRotation.indexOf('holiday:ntc-birthday')<birthdayRotation.indexOf('holiday:advent:2'));
assert.equal(run('headerSeasonEffect(birthdayTime)'),'advent-2');
run('pilot=false');
assert.ok(!run('smartRotation(fullBase,null,null,[],[],birthdayTime)').includes('holiday:ntc-birthday'));
run('pilot=true');
const realDate=ctx.Date;
ctx.Date=class extends Date{static now(){return ctx.birthdayTime;}};
assert.match(run('smartHolidayView("holiday:ntc-birthday")'),/33 Jahre NTC 93/);
assert.match(run('smartHolidayView("holiday:ntc-birthday")'),/6\. Dezember 1993/);
ctx.Date=realDate;
console.log('NTC birthday, yearly age, demo isolation and Advent coexistence OK');

// Visible header badge is NTC-only, localized, and independent of the Advent label.
vm.runInContext(source.slice(source.indexOf('function headerBirthdayLabel('),source.indexOf('function syncHeaderDecor(')),ctx);
run('pilot=true');
assert.equal(run('headerBirthdayLabel(birthdayTime)'),'33 Jahre NTC 93');
assert.equal(run('headerBirthdayLabel(Date.parse("2026-12-07T00:00:00+01:00"))'),'');
assert.equal(run('headerSeasonEffect(birthdayTime)'),'advent-2');
run('pilot=false');
assert.equal(run('headerBirthdayLabel(birthdayTime)'),'');
run('pilot=true');
ctx.Date=class extends Date{static now(){return ctx.birthdayTime;}};
const birthdayCopy=run('smartHolidayView("holiday:ntc-birthday")');
assert.match(birthdayCopy,/aus Mitspielern Freunde/);
assert.match(birthdayCopy,/Danke an alle/);
ctx.Date=realDate;
assert.match(fs.readFileSync('public/index.html','utf8'),/id="header-birthday"[^>]*hidden/);
console.log('Visible anniversary header and community birthday copy OK');
