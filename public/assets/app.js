'use strict';
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
const $ = id => document.getElementById(id);
const pilot = location.hostname === 'clubdisplay.ntc93.de';
const personalDemo=!pilot&&location.hostname==='clubdisplay.tennilo.de'&&new URLSearchParams(location.search).get('customize')==='1';
let personalBrand=null;
let premiereActive=false,premiereIndex=0,premiereDeadline=0,premiereRemaining=0,premierePaused=false,premiereLastInput=0,premiereMode='full';
const premiereDwell=18000,feedbackUrl='https://clubdisplay.ntc93.de/feedback.php';
if(pilot){const logo=document.getElementById('brand-logo');logo.src='assets/ntc93-logo.png';logo.alt='Neuenhagener Tennisclub 93 e. V.';}
let lang='de', view='info', slide=0, last=Date.now(), started=Date.now(), court=null, message='';
const dwell=12000, idle=60000;
const nightIdle=300000;
let nightActive=false,nightLastMinute=-1,nightLastInput=0,nightPreserveBooking=false;
// Rest mode uses the NTC venue calendar and the published gastro hours.
const infoPauseLimit=25000;
let infoPaused=false,infoElapsed=0,infoPauseUntil=0;
const dictionary={
de:{club:pilot?'Neuenhagener Tennisclub 93 e. V.':'TENNILO · Entwicklungsdemo',info:'Informationsmodus',demo:'PROTOTYP · BEISPIELDATEN',notice:'Keine echten Buchungen oder Vereinsdaten.',welcome:'Willkommen im Clubhaus',intro:'Alles Wichtige rund um deinen Tennisverein. Berühre den Bildschirm und entdecke die Funktionen.',start:'Touch-Menü öffnen',today:'Heute auf der Anlage',courts:'Plätze',calendar:'Termine',teams:'Punktspiele',news:'Neuigkeiten',home:'Start',back:'Infomodus',available:'Frei',busy:'Belegt',court:'Platz',booking:'Platzbuchung',select:'Platz auswählen',slot:'Beispielzeit: 15:00–16:00',confirm:'Buchung simulieren',success:'Simulation erfolgreich. Es wurde nichts tatsächlich gebucht oder gespeichert.',upcoming:'Nächste Termine',events:['Vereinstraining','Mannschaftsspieltag','Clubturnier'],eventTimes:['Heute · 17:00','Samstag · 10:00','Sonntag · 11:00'],matches:'Punktspiele',matchNames:['Herren 30','Damen','Jugend U14'],matchDetails:['Heimspiel · Samstag','Auswärts · Sonntag','Training · Mittwoch'],announcements:'Neues aus dem Club',newsItems:['Willkommen zur neuen Saison','Aktuelle Platzhinweise','Informationen zu Vereinsveranstaltungen'],newsText:'Beispiel für eine Vereinsmeldung. Die späteren Inhalte werden zentral verwaltet.',partner:'Partner & Sponsoren',partnerText:'Dieser Bereich ist für freigegebene Vereinswerbung vorgesehen.',reset:'Zurück zur Auswahl'},
en:{club:pilot?'Neuenhagener Tennisclub 93 e. V.':'TENNILO · Development demo',info:'Information mode',demo:'PROTOTYPE · SAMPLE DATA',notice:'No real bookings or club data.',welcome:'Welcome to the clubhouse',intro:'Everything you need to know about your tennis club. Touch the screen to explore.',start:'Open touch menu',today:'Today at the club',courts:'Courts',calendar:'Events',teams:'League matches',news:'News',home:'Home',back:'Info mode',available:'Available',busy:'Occupied',court:'Court',booking:'Court booking',select:'Select a court',slot:'Example time: 15:00–16:00',confirm:'Simulate booking',success:'Simulation successful. Nothing was actually booked or stored.',upcoming:'Upcoming events',events:['Club training','Team match day','Club tournament'],eventTimes:['Today · 17:00','Saturday · 10:00','Sunday · 11:00'],matches:'League matches',matchNames:['Men 30','Women','Junior U14'],matchDetails:['Home · Saturday','Away · Sunday','Training · Wednesday'],announcements:'Club updates',newsItems:['Welcome to the new season','Current court information','Club event information'],newsText:'Example club announcement. Future content will be managed centrally.',partner:'Partners & sponsors',partnerText:'Reserved for approved club advertising.',reset:'Back to selection'}
};
const t=()=>dictionary[lang];
const baseScreens=['welcome','weather','calendar','teams','news','restaurant','partner'];
const screens=[...baseScreens];
let greetingMatches=new Map(),greetingEvents=new Map();
let leagueData=null, matchFilter='home', matchPage=0, fetching=false;
const sourceUrl='https://tvbb.liga.nu/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=36381';
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const tr=(de,en)=>lang==='de'?de:en;
function shortClub(name){return String(name??'').replace(/^Neuenhagener\s+(?:Tennisclub|TC)\s+93(?=\s|$)/iu,'NTC 93');}
// Fictional TENNILO demo data; never query private NTC booking data.
const demoStart=Math.ceil(Date.now()/900000)*900000;
function demoLeague(){
  const club=typeof personalBrand!=='undefined'&&personalBrand?personalBrand.name:'TC Demo';
  return {status:'ok',fetchedAt:Math.floor(Date.now()/1000),matches:[
    {date:new Date(demoStart).toISOString(),home:club+' I',away:'TC Beispielpark I',league:'H30 VL',isHome:true,score:null,courts:[1,2,3]},
    {date:new Date(demoStart+86400000).toISOString(),home:club+' I',away:'TC Musterstadt I',league:'D30 BL',isHome:true,score:null,courts:[4,5,6]},
    {date:new Date(demoStart+172800000).toISOString(),home:'TC Beispielpark II',away:club+' II',league:'H40 BL',isHome:false,score:null},
    {date:new Date(demoStart-86400000).toISOString(),home:club+' II',away:'TC Musterstadt II',league:'H BL',isHome:true,score:'6:3'}
  ]};
}
function demoOccupancy(hours,now=Date.now()){
  const labels={outdoor:[[tr('Demo-Spieler A / B','Demo players A / B'),null,null],[tr('Jugend U14','Juniors U14'),null,null],[null,null,null],[tr('Damen 30','Women 30'),null,tr('Demo-Spieler C / D','Demo players C / D')],[null,tr('Jugendtraining','Junior training'),tr('Jugendtraining','Junior training')],[tr('Platzpflege','Court maintenance'),null,null],[tr('Mixed-Training','Mixed training'),null,tr('Vereinstraining','Club training')]],indoor:[[tr('Damen 50','Women 50'),tr('Damen 50','Women 50'),null],[tr('Demo-Spieler E / F','Demo players E / F'),null,tr('Herren 40','Men 40')]]};
  const slots=[];
  for(const area of Object.keys(labels))labels[area].forEach((row,ci)=>hours.forEach((hour,i)=>{
    let label=row[i],category=ci===4?'junior':ci===5?'blocked':ci===6||ci===1&&area==='outdoor'?'training':'regular',status=label?'occupied':'free',from=Date.parse(hour.start),to=Date.parse(hour.end);
    if(area==='outdoor'&&ci<3&&from<demoStart+7200000&&to>demoStart){
      label=tr('Herren 30 · Heimspiel (Demo)','Men 30 · home match (demo)');category='match';from=Math.max(from,demoStart);to=Math.min(to,demoStart+7200000);status=from===Date.parse(hour.start)&&to===Date.parse(hour.end)?'occupied':'partial';
    }
    if(area==='outdoor'&&ci===5&&i===0)status='blocked';
    slots.push({area,court:ci+1,...hour,status,bookings:label?[{displayLabel:label,category,start:new Date(from).toISOString(),end:new Date(to).toISOString()}]:[]});
  }));
  return {status:'ok',fetchedAt:Math.floor(now/1000),slots};
}
function demoNews(){
  const items=[
    [tr('Willkommen zur neuen Saison','Welcome to the new season'),tr('Gemeinsamer Start in die Tennissaison.','Starting the tennis season together.'),tr('Zum Saisonauftakt laden wir alle Mitglieder zu einem gemeinsamen Tennisnachmittag ein. Auf den Außenplätzen finden Trainingsangebote und lockere Doppelrunden statt.','Members are invited to an afternoon of tennis, training and friendly doubles.'),tr('Gäste und neue Mitglieder erhalten eine Einführung in die Platzbuchung.','Guests and new members can learn about the booking system.'),'#b67a4c'],
    [tr('Clubturnier steht an','Club tournament coming up'),tr('Ein Spieltag für alle Altersklassen.','A day of tennis for all age groups.'),tr('Das Beispiel-Clubturnier bietet Einzel und Doppel in verschiedenen Spielklassen. Die Begegnungen werden im Clubhaus bekannt gegeben.','The sample tournament features singles and doubles. Fixtures are announced at the clubhouse.'),tr('Die Spielleitung hilft bei Fragen zum Ablauf.','The tournament team can answer questions.'),'#18747b'],
    [tr('Jugendtraining im Verein','Junior training at the club'),tr('Informationen zu den Trainingsgruppen.','Information about training groups.'),tr('In dieser Beispielmeldung stellen wir Trainingsangebote für Kinder und Jugendliche vor.','This sample announcement introduces training for children and juniors.'),tr('Die Gruppen werden nach Alter und Spielstärke zusammengestellt.','Groups are organized by age and experience.'),'#315a8c']
  ];
  return {status:'ok',posts:items.map((p,i)=>{
    const svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 320"><rect width="800" height="320" fill="'+p[4]+'"/><g stroke="#fff" fill="none" stroke-width="3" opacity=".85"><rect x="90" y="35" width="620" height="250"/><path d="M90 75h620M90 245h620M280 75v170M520 75v170M280 160h240M400 20v280"/></g><circle cx="400" cy="160" r="37" fill="#d7e96b"/><path d="M374 135q38 22 4 52m45-52q-38 22-4 52" fill="none" stroke="#fff" stroke-width="4"/><text x="20" y="305" font-size="18" font-family="sans-serif" fill="white">TENNILO · DEMO</text></svg>';
    return {date:new Date(demoStart-i*86400000).toISOString(),title:p[0],excerpt:p[1],article:'<p>'+esc(p[2])+'</p><h2>'+tr('Weitere Informationen','More information')+'</h2><p>'+esc(p[3])+'</p><p>'+tr('Fiktiver Beispielartikel der TENNILO-Produktdemo.','Fictional sample article in the TENNILO product demo.')+'</p>',image:'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svg),imageAlt:tr('Tennisplatz-Illustration','Court illustration'),complete:true};
  })};
}
const demoSponsors=[['COURT & CO.','SPORT','#167d7f'],['MATCHPOINT','TENNIS SHOP','#30384c'],['CLUBKÜCHE','GASTRO','#a15b32'],['NETZWERK','IT','#2d6392'],['GRÜNWERK','GARTEN','#38794b'],['BASELINE','FITNESS','#b3473c'],['PUNKTGENAU','SERVICE','#484f6f'],['SPIELRAUM','DESIGN','#8f4f79'],['TENNISWERK','AUSRÜSTUNG','#bb772b'],['TEAMPLAY','SPORT','#167c84'],['AUFSCHLAG','EVENTS','#2c4961'],['COURTSIDE','MEDIA','#555'],['VEREINSWERK','PARTNER','#397657'],['TOPSPIN','TRAINING','#355ab3'],['FAIRPLAY','FÖRDERUNG','#8b633f']];
function demoSponsorCards(){
  return demoSponsors.map(([name,kind,color],i)=>'<figure class="sponsor-logo demo-sponsor-logo"><svg viewBox="0 0 240 115" role="img" aria-label="'+esc(name+' · Demo')+'"><g fill="'+color+'">'+(i%3===0?'<circle cx="120" cy="27" r="16"/><path d="M109 16l22 22M109 38l22-22" stroke="#fff" stroke-width="2"/>':i%3===1?'<path d="M100 42l20-31 20 31z"/><circle cx="120" cy="28" r="5" fill="white"/>':'<rect x="105" y="12" width="30" height="30" rx="7"/><path d="M111 27h18M120 18v18" stroke="white" stroke-width="2"/>')+'<text x="120" y="70" font-size="22" font-weight="850" font-family="system-ui,sans-serif" text-anchor="middle">'+esc(name)+'</text><text x="120" y="89" font-size="10" letter-spacing="2" font-family="system-ui,sans-serif" text-anchor="middle">'+esc(kind)+'</text></g><text x="120" y="107" font-size="8" fill="#777" text-anchor="middle" font-family="sans-serif">DEMO</text></svg></figure>').join('');
}

// Match welcome slides are generated only for the scheduled 60-minute window.
function matchGreetingKey(m){
  return 'greeting:'+JSON.stringify([m.date,m.home,m.away,m.league]);
}
function activeMatchGreetings(data,now=Date.now()){
  if(!data||data.status==='unavailable'||!Array.isArray(data.matches))return [];
  const unique=new Map();
  for(const m of data.matches){
    if(m.isHome!==true||m.score||m.cancelled===true||m.cancelled==='true'||typeof m.home!=='string'||typeof m.away!=='string'||!m.home.trim()||!m.away.trim())continue;
    const start=Date.parse(m.date);
    if(!Number.isFinite(start)||now<start-1800000||now>=start+1800000)continue;
    unique.set(matchGreetingKey(m),m);
  }
  return [...unique.values()].sort((a,b)=>Date.parse(a.date)-Date.parse(b.date)||String(a.league).localeCompare(String(b.league)));
}
function eventGreetingKey(e){
  return 'event-greeting:'+JSON.stringify([e.title,e.start,e.end,e.venue||'']);
}
function activeEventGreetings(data,now=Date.now()){
  if(!data||data.status==='unavailable'||!Array.isArray(data.events))return [];
  const unique=new Map();
  for(const e of data.events){
    if(!e||typeof e.title!=='string'||!e.title.trim()||e.cancelled===true||e.cancelled==='true'||String(e.status||'').toLowerCase()==='cancelled')continue;
    const start=Date.parse(e.start),end=Date.parse(e.end);
    if(!Number.isFinite(start)||!Number.isFinite(end)||end<=start||now<start-1800000||now>=end)continue;
    unique.set(eventGreetingKey(e),e);
  }
  return [...unique.values()].sort((a,b)=>Date.parse(a.start)-Date.parse(b.start)||a.title.localeCompare(b.title));
}
function eventGreetingView(key){
  const e=greetingEvents.get(key);if(!e)return '';
  const locale=lang==='de'?'de-DE':'en-GB',timeZone='Europe/Berlin';
  const date=s=>new Date(s).toLocaleDateString(locale,{timeZone,day:'2-digit',month:'2-digit',year:'numeric'});
  const time=s=>new Date(s).toLocaleTimeString(locale,{timeZone,hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
  const first=date(e.start),last=date(e.end);
  const when=e.allDay?(first===last?first:first+' – '+last)+' · '+tr('Ganztägig','All day'):first+' · '+time(e.start)+' – '+(first===last?'':last+' · ')+time(e.end)+(lang==='de'?' Uhr':'');
  return '<div class="greeting-hero event-greeting-hero"><div class="greeting-kicker">'+tr('VERANSTALTUNG','EVENT')+'</div><div class="greeting-welcome">'+tr('Herzlich willkommen!','A warm welcome!')+'</div><h1 class="event-greeting-title">'+esc(e.title)+'</h1><div class="event-greeting-period">'+esc(when)+'</div>'+(e.venue?'<div class="event-greeting-venue">'+esc(e.venue)+'</div>':'')+'<p class="greeting-farewell">'+tr('Wir wünschen euch eine schöne Veranstaltung!','Enjoy the event!')+'</p></div>';
}
// Smart rotation: calendar dates are evaluated in the club's timezone, not the device timezone.
function smartDate(value){
  const ms=typeof value==='number'?value:Date.parse(value);
  if(!Number.isFinite(ms))return '';
  const fmt=smartDate.formatter||(smartDate.formatter=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}));
  return fmt.format(new Date(ms));
}
function smartSeason(now=Date.now()){
  const day=smartDate(now).slice(5);
  return day>='05-01'&&day<'10-01'?'summer':'winter';
}
// Fourth Advent is the Sunday strictly before Christmas Day, even when Christmas is a Sunday.
// Saturday and Sunday of each Advent weekend use the same local calendar date calculation.
function smartAdvent(now=Date.now()){
  const date=smartDate(now);
  if(!date)return 0;
  const year=Number(date.slice(0,4)),today=Date.parse(date+'T00:00:00Z')/86400000;
  const christmas=Date.UTC(year,11,25)/86400000;
  const fourth=christmas-(new Date(christmas*86400000).getUTCDay()||7);
  for(let n=1;n<=4;n++){
    const sunday=fourth-(4-n)*7;
    if(today===sunday-1||today===sunday)return n;
  }
  return 0;
}
// NTC only, using the Berlin calendar and the actual founding year.
function ntcBirthdayAge(now=Date.now()){
  const date=smartDate(now),year=Number(date.slice(0,4));
  return date.slice(5)==='12-06'&&year>=1993?year-1993:null;
}
function smartHoliday(now=Date.now()){
  const day=smartDate(now).slice(5);
  if(day>='12-24'&&day<='12-26')return 'holiday:christmas';
  if(day==='12-31')return 'holiday:eve';
  if(day>='01-01'&&day<='01-06')return 'holiday:newyear';
  const advent=smartAdvent(now);
  return advent?'holiday:advent:'+advent:'';
}
function smartScreenKey(key){return typeof key==='string'&&key.startsWith('priority:')?key.slice(9):key;}
function smartDayNumber(value){
  const day=smartDate(value);
  return day?Date.parse(day+'T00:00:00Z')/86400000:NaN;
}
function smartWeekend(matches,events,now=Date.now()){
  const day=smartDayNumber(now),weekday=new Date(day*86400000).getUTCDay();
  if(![4,5,6,0].includes(weekday))return null;
  const saturday=day+(weekday===0?-1:6-weekday),sunday=saturday+1;
  const rows=[];
  if(matches?.status!=='unavailable')for(const m of matches?.matches||[]){
    const d=smartDayNumber(m.date);
    if(m.isHome!==true||m.cancelled===true||m.cancelled==='true'||String(m.status||'').toLowerCase()==='cancelled'||!Number.isFinite(d)||d<saturday||d>sunday)continue;
    rows.push({kind:'match',date:m.date,match:m});
  }
  if(events?.status!=='unavailable')for(const e of events?.events||[]){
    const a=smartDayNumber(e.start),b=smartDayNumber(e.end);
    if(!e.title||e.cancelled===true||e.cancelled==='true'||String(e.status||'').toLowerCase()==='cancelled'||!Number.isFinite(a)||!Number.isFinite(b)||Date.parse(e.end)<Date.parse(e.start)||a>sunday||b<saturday)continue;
    rows.push({kind:'event',date:e.start,event:e});
  }
  const unique=new Map(rows.map(r=>[r.kind+':'+(r.kind==='match'?matchGreetingKey(r.match):eventGreetingKey(r.event)),r]));
  return {saturday,sunday,rows:[...unique.values()].sort((a,b)=>Date.parse(a.date)-Date.parse(b.date))};
}
function smartOpenCountdown(events,now=Date.now()){
  if(events?.status==='unavailable')return [];
  const today=smartDayNumber(now),unique=new Map();
  for(const e of events?.events||[]){
    if(!e||typeof e.title!=='string'||!(/^\s*(?:\d+\.?\s*)?NEUENHAGEN\s+(?:SENIOR\s+)?OPEN\s*$/i.test(e.title)||(e.demo===true&&e.title==='DEMO OPEN'))||e.cancelled===true||e.cancelled==='true'||String(e.status||'').toLowerCase()==='cancelled')continue;
    const start=Date.parse(e.start),end=Date.parse(e.end),days=smartDayNumber(e.start)-today;
    if(!Number.isFinite(days)||days<0||days>100||!Number.isFinite(end)||end<=start||now>=start-1800000)continue;
    unique.set(eventGreetingKey(e),{event:e,days});
  }
  return [...unique.values()].sort((a,b)=>Date.parse(a.event.start)-Date.parse(b.event.start));
}
function smartWeekendView(key){
  const data=smartWeekend(leagueData,eventData);
  if(!data)return '';
  const locale=lang==='de'?'de-DE':'en-GB';
  const day=n=>new Date(n*86400000).toLocaleDateString(locale,{timeZone:'UTC',day:'2-digit',month:'2-digit',year:'numeric'});
  const page=Number(key.split(':')[1])||0;
  const rows=data.rows.slice(page*5,page*5+5).map(r=>r.kind==='match'?fixtureMatchCard(r.match):fixtureEventCard(r.event,true)).join('');
  const incomplete=[leagueData,eventData].some(d=>!d||d.status!=='ok');
  return '<div class="smart-feature"><div class="match-heading"><h1>'+tr('Das Wochenende','This weekend')+'</h1><span>'+esc(day(data.saturday)+' – '+day(data.sunday))+'</span></div><div class="fixture-list smart-weekend-list">'+rows+'</div><p class="match-source">'+tr('Quelle: nuLiga und Veranstaltungskalender','Source: nuLiga and events calendar')+(incomplete?' · '+tr('Daten teilweise nicht aktuell oder verfügbar','Some data may be outdated or unavailable'):'')+(data.rows.length>5?' · '+(page+1)+' / '+Math.ceil(data.rows.length/5):'')+'</p></div>';
}
function smartCountdownView(){
  const rows=smartOpenCountdown(eventData);
  const fmt=s=>new Date(s).toLocaleDateString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',day:'2-digit',month:'2-digit',year:'numeric'});
  return '<div class="smart-feature smart-countdown"><div class="match-heading"><h1>'+tr('Turnier-Countdown','Tournament countdown')+'</h1></div><div class="smart-countdown-grid">'+rows.map(({event:e,days})=>'<article><h2>'+esc(e.title)+'</h2><div class="smart-countdown-number">'+esc(String(days))+'</div><div class="smart-countdown-unit">'+esc(days===0?tr('Heute','Today'):days===1?tr('Tag bis zum Start','day until start'):tr('Tage bis zum Start','days until start'))+'</div><p>'+esc(fmt(e.start)+' – '+fmt(e.end))+'</p></article>').join('')+'</div><p class="match-source">'+(pilot?tr('Quelle: NTC-Veranstaltungskalender','Source: NTC event calendar'):tr('Fiktiver Veranstaltungskalender · TENNILO-Demo','Fictional events · TENNILO demo'))+'</p></div>';
}
function smartRotation(base,matches,events,matchKeys,eventKeys,now=Date.now()){
  const today=smartDate(now);
  const matchDay=matches?.status!=='unavailable'&&(matches?.matches||[]).some(m=>m.isHome===true&&!m.cancelled&&!m.score&&smartDate(m.date)===today);
  const eventDay=events?.status!=='unavailable'&&(events?.events||[]).some(e=>e&&!e.cancelled&&String(e.status||'').toLowerCase()!=='cancelled'&&Number.isFinite(Date.parse(e.start))&&Date.parse(e.end)>Math.max(now,Date.parse(e.start))&&smartDate(e.start)<=today&&smartDate(e.end)>=today);
  const holiday=smartHoliday(now);
  const greetings=[...matchKeys,...eventKeys];
  const birthday=pilot&&ntcBirthdayAge(now)!==null;
  const out=[base[0],...greetings,...(birthday?['holiday:ntc-birthday']:[]),...(holiday?[holiday]:[])];
  if(base.includes('calendar')&&base.includes('teams')&&base.includes('news')){
    const weekend=smartWeekend(matches,events,now);
    if(weekend?.rows.length)for(let i=0;i<Math.ceil(weekend.rows.length/5);i++)out.push('weekend:'+i);
    if(smartOpenCountdown(events,now).length)out.push('open-countdown');
  }
  for(const key of base.slice(1)){
    out.push(key);
    if(key==='news'){
      if(matchDay&&base.includes('teams'))out.push('priority:teams');
      if(eventDay&&base.includes('calendar'))out.push('priority:calendar');
      // Repeat current welcomes once, while retaining every ordinary slide.
      out.push(...greetings.map(k=>'priority:'+k));
    }
  }
  return out;
}
function adventCandles(count){
  return '<svg viewBox="0 0 220 110" role="presentation"><g fill="none" stroke="#88ac79" stroke-width="2.5" stroke-linecap="round"><path d="M12 101Q110 72 208 101M30 98l-8-10m23 6-7-12m27 9-5-14m100 14 5-14m15 17 7-12m8 16 8-10"/></g>'+Array.from({length:4},(_,i)=>{
    const x=38+i*48,lit=i<count;
    return '<g><rect x="'+(x-10)+'" y="46" width="20" height="43" rx="3" fill="'+(lit?'#ebd3a0':'#b7b7a5')+'"/><path d="M'+x+' 46v-6" stroke="#573c28" stroke-width="2"/>'+(lit?'<path d="M'+x+' 13q-19 24 0 27q19-3 0-27Z" fill="#ffce72"/><path d="M'+x+' 26q-7 12 0 13q7-1 0-13Z" fill="#fff5c3"/>':'')+'</g>';
  }).join('')+'</svg>';
}
function smartHolidayView(key){
  if(key==='holiday:ntc-birthday'){
    const age=ntcBirthdayAge();
    if(!pilot||age===null)return '';
    return '<div class="greeting-hero event-greeting-hero holiday-hero holiday-birthday"><div class="birthday-sparkles" aria-hidden="true">'+Array.from({length:24},(_,i)=>'<i style="--x:'+((i*37+7)%100)+'%;--y:'+((i*19+5)%100)+'%;--turn:'+(i*29%180)+'deg"></i>').join('')+'</div><img class="birthday-logo" src="assets/ntc93-logo.png" alt="NTC 93"><div class="birthday-kicker">'+tr('HEUTE FEIERN WIR','TODAY WE CELEBRATE')+'</div><h1 class="birthday-age">'+age+' '+tr(age===1?'Jahr':'Jahre',age===1?'year':'years')+' NTC 93</h1><h2 class="birthday-title">'+tr('Herzlichen Glückwunsch, NTC 93!','Happy birthday, NTC 93!')+'</h2><p class="birthday-founded">'+tr('Gegründet am 6. Dezember 1993','Founded on 6 December 1993')+'</p><p class="birthday-story">'+tr('Unser NTC ist mehr als ein Tennisverein. Hier werden aus Mitspielern Freunde, aus Begegnungen Erinnerungen und aus vielen einzelnen Menschen eine Gemeinschaft. Ob auf dem Platz, im Clubhaus oder hinter den Kulissen – ihr alle macht unseren Verein zu dem, was er ist.','Our NTC is more than a tennis club. Here, playing partners become friends, encounters become memories, and individuals become a community. On court, in the clubhouse or behind the scenes – each of you makes our club what it is.')+'</p><p class="birthday-thanks">'+tr('Danke an alle, die den NTC seit 1993 mit Leben füllen. Auf viele weitere gemeinsame Jahre!','Thank you to everyone who has brought NTC to life since 1993. Here’s to many more years together!')+'</p></div>';
  }
  if(/^holiday:advent:[1-4]$/.test(key)){
    const n=Number(key.split(':')[2]);
    const heading=tr('Einen schönen '+n+'. Advent!','Happy '+['first','second','third','fourth'][n-1]+' Advent!');
    return '<div class="greeting-hero event-greeting-hero holiday-hero holiday-advent"><div class="holiday-art advent-art" aria-hidden="true">'+adventCandles(n)+'</div><h1 class="event-greeting-title">'+esc(heading)+'</h1><p class="greeting-farewell">'+tr('Wir wünschen euch ein gemütliches Adventswochenende.','Wishing you a lovely Advent weekend.')+'</p></div>';
  }
  const copy=key==='holiday:christmas'?[tr('Frohe Weihnachten!','Merry Christmas!'),tr('Wir wünschen euch und euren Familien schöne Feiertage.','Wishing you and your families a wonderful holiday.')]:key==='holiday:eve'?[tr('Guten Rutsch!','Happy New Year’s Eve!'),tr('Kommt gut ins neue Jahr.','Have a wonderful start to the new year.')]:[tr('Frohes neues Jahr!','Happy New Year!'),tr('Wir wünschen euch Gesundheit, Glück und viel Freude auf dem Tennisplatz.','Wishing you health, happiness and plenty of joy on the tennis court.')];
  return '<div class="greeting-hero event-greeting-hero holiday-hero '+(key==='holiday:christmas'?'holiday-christmas':'holiday-newyear')+'"><div class="holiday-art" aria-hidden="true">'+(key==='holiday:christmas'?'<svg viewBox="0 0 160 110"><path d="M80 8 53 43h15L38 75h26L28 99h104L96 75h26L92 43h15Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M80 0v12m-6-6h12" stroke="currentColor" stroke-width="2"/><circle cx="80" cy="52" r="3" fill="currentColor"/><circle cx="60" cy="80" r="3" fill="currentColor"/><circle cx="100" cy="80" r="3" fill="currentColor"/></svg>':'<svg viewBox="0 0 160 110" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M80 12v26m0 24v26M42 50h26m24 0h26M53 23l18 18m18 18 18 18M53 77l18-18m18-18 18-18M24 5v20m-10-10h20m102 65v20m-10-10h20"/><circle cx="80" cy="50" r="5"/></svg>')+'</div><h1 class="event-greeting-title">'+esc(copy[0])+'</h1><p class="greeting-farewell">'+esc(copy[1])+'</p></div>';
}
let partnerBoardData=null,partnerPage=0,partnerBusy=false;
function partnerBoardRows(now=Date.now()){
  if(!partnerBoardData?.enabled)return [];
  const groups={category:['singles','doubles','mixed','training'],skill:['any','beginner','recreational','league','advanced'],availability:['flexible','weekday','weekend','mon','tue','wed','thu','fri','sat','sun'],period:['any','morning','afternoon','evening']};
  return (Array.isArray(partnerBoardData.rows)?partnerBoardData.rows:[]).filter(r=>r&&/^[A-F0-9]{10}$/.test(r.ref)&&Number.isFinite(Number(r.expires))&&Number(r.expires)>now/1000&&Object.entries(groups).every(([k,values])=>typeof r[k]==='string'&&(k==='availability'||k==='period'?r[k].split(',').every(v=>values.includes(v)):values.includes(r[k])))).slice(0,30);
}
function partnerBoardScreens(now=Date.now()){
  const count=partnerBoardRows(now).length;
  return Array.from({length:partnerBoardData?.enabled?Math.max(1,Math.ceil(count/4)):0},(_,i)=>'partners:'+i);
}
// Fixed QR Model 2 version 4-L, byte mode, 80 data and 20 ECC codewords.
function partnerQrMatrix(url){
  const data=Array.from(url,c=>c.charCodeAt(0));
  if(data.length>78||data.some(v=>v>127))throw new Error('QR payload exceeds ASCII capacity');
  const bits=[];const append=(value,n)=>{for(let i=n-1;i>=0;i--)bits.push((value>>>i)&1);};
  append(4,4);append(data.length,8);data.forEach(v=>append(v,8));append(0,Math.min(4,640-bits.length));while(bits.length%8)bits.push(0);
  const code=[];for(let i=0;i<bits.length;i+=8)code.push(bits.slice(i,i+8).reduce((v,b)=>(v<<1)|b,0));for(let i=0;code.length<80;i++)code.push(i%2?0x11:0xec);
  const mul=(x,y)=>{let z=0;for(let i=7;i>=0;i--){z=(z<<1)^((z>>>7)*0x11d);z^=((y>>>i)&1)*x;}return z;};
  let divisor=[1],root=1;for(let i=0;i<20;i++){const next=Array(divisor.length+1).fill(0);divisor.forEach((v,j)=>{next[j]^=v;next[j+1]^=mul(v,root);});divisor=next;root=mul(root,2);}
  const ecc=Array(20).fill(0);for(const v of code){const f=v^ecc.shift();ecc.push(0);for(let i=0;i<20;i++)ecc[i]^=mul(divisor[i+1],f);}code.push(...ecc);
  const n=33,m=Array.from({length:n},()=>Array(n).fill(-1));const set=(x,y,v)=>{if(x>=0&&y>=0&&x<n&&y<n)m[y][x]=v?1:0;};
  for(let i=0;i<n;i++){set(6,i,i%2===0);set(i,6,i%2===0);}
  for(const [cx,cy] of [[3,3],[n-4,3],[3,n-4]])for(let dy=-4;dy<=4;dy++)for(let dx=-4;dx<=4;dx++){const d=Math.max(Math.abs(dx),Math.abs(dy));set(cx+dx,cy+dy,d!==2&&d!==4);}
  for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++)set(26+dx,26+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1);
  const fmt=0x77c4,b=i=>(fmt>>>i)&1;for(let i=0;i<=5;i++)set(8,i,b(i));set(8,7,b(6));set(8,8,b(7));set(7,8,b(8));for(let i=9;i<15;i++)set(14-i,8,b(i));for(let i=0;i<8;i++)set(n-1-i,8,b(i));for(let i=8;i<15;i++)set(8,n-15+i,b(i));set(8,n-8,1);
  let k=0;for(let right=n-1;right>=1;right-=2){if(right===6)right=5;for(let v=0;v<n;v++){const y=((right+1)&2)===0?n-1-v:v;for(let j=0;j<2;j++){const x=right-j;if(m[y][x]!==-1)continue;const bit=k<code.length*8?(code[k>>>3]>>>(7-(k&7)))&1:0;k++;m[y][x]=bit^((x+y)%2===0?1:0);}}}
  return m;
}
function partnerQrSvg(url,label){
  const m=partnerQrMatrix(url);
  const path=m.map((row,y)=>row.map((v,x)=>v?'M'+(x+4)+' '+(y+4)+'h1v1h-1z':'').join('')).join('');
  return '<svg viewBox="0 0 41 41" shape-rendering="crispEdges" role="img" aria-label="'+esc(label)+'"><rect width="41" height="41" fill="#fff"/><path d="'+path+'" fill="#000"/></svg>';
}

function demoPartnerRows(){
  const expires=Math.floor(Date.now()/1000)+1209600;
  return [
    ['DE00000001','singles','recreational','mon,wed','evening','16-20','40–49','30–49','Männlich','Egal'],
    ['DE00000002','doubles','league','weekend','morning','6-10','30–39, 40–49','30–39, 40–49','Weiblich, Männlich','Egal'],
    ['DE00000003','mixed','any','fri,sun','afternoon','','50–59','40–49, 50–59','Weiblich','Männlich'],
    ['DE00000004','training','beginner','tue,thu','afternoon,evening','21-25','18–29','18–29','Divers','Egal'],
    ['DE00000005','singles','advanced','flexible','any','1-5','60–69','50–59, 60–69','Männlich','Egal']
  ].map(([ref,category,skill,availability,period,lk,own_age_label,wanted_age_label,own_gender_label,wanted_gender_label])=>({ref,category,skill,availability,period,lk,own_age_label,wanted_age_label,own_gender_label,wanted_gender_label,expires}));
}
const demoQrBase='https://clubdisplay.tennilo.de/demo.php';
function partnerBoardView(active){
  const rows=partnerBoardRows(),pages=Math.max(1,Math.ceil(rows.length/4)),page=active==='partners'?Math.max(0,Math.min(partnerPage,pages-1)):Math.max(0,Number(active.split(':')[1])||0);
  const type={singles:tr('Einzelpartner gesucht','Singles partner wanted'),doubles:tr('Doppelpartner gesucht','Doubles partner wanted'),mixed:tr('Mixed-Partner gesucht','Mixed partner wanted'),training:tr('Trainingspartner gesucht','Practice partner wanted')};
  const skill={any:tr('Alle Spielstärken','Any level'),beginner:tr('Einsteiger','Beginner'),recreational:tr('Freizeitspieler','Recreational'),league:tr('Mannschaftsspieler','League player'),advanced:tr('Fortgeschritten','Advanced')};
  const availability={flexible:tr('Flexibel','Flexible'),weekday:tr('Werktags','Weekdays'),weekend:tr('Am Wochenende','Weekends'),mon:tr('Montag','Monday'),tue:tr('Dienstag','Tuesday'),wed:tr('Mittwoch','Wednesday'),thu:tr('Donnerstag','Thursday'),fri:tr('Freitag','Friday'),sat:tr('Samstag','Saturday'),sun:tr('Sonntag','Sunday')};
  const period={any:tr('Zeitlich flexibel','Any time'),morning:tr('Vormittags','Morning'),afternoon:tr('Nachmittags','Afternoon'),evening:tr('Abends','Evening')};
  const detail=(label,value)=>'<div><dt>'+esc(label)+'</dt><dd>'+esc(value)+'</dd></div>';
  const genderLabel=v=>String(v||'').split(', ').filter(Boolean).map(x=>tr(x,({'Männlich':'Male','Weiblich':'Female','Divers':'Diverse','Egal':'Any'})[x]||x)).join(', ');
  const ageLabel=v=>!v?tr('Nicht angegeben','Not specified'):v==='Geschützte Vermittlung'?tr('Geschützte Vermittlung','Protected mediation'):v+tr(' Jahre',' years');
  const cards=rows.slice(page*4,page*4+4).map(r=>'<article class="partner-board-card'+' has-request-qr'+'"><div class="partner-card-copy"><h2>'+esc(type[r.category])+'</h2><dl class="partner-card-details">'+detail(tr('Suchende','Seeking player(s)'),[ageLabel(r.own_age_label),genderLabel(r.own_gender_label)].filter(Boolean).join(' · '))+detail(tr('Gesucht','Wanted'),[ageLabel(r.wanted_age_label),genderLabel(r.wanted_gender_label)].filter(Boolean).join(' · '))+detail(tr('Spielstärke / LK','Level / rating'),skill[r.skill]+(/^(1-5|6-10|11-15|16-20|21-25)$/.test(r.lk||'')?' · LK '+r.lk.replace('-','–'):' · '+tr('LK k. A.','No LK')))+detail(tr('Wochentage','Days'),r.availability.split(',').map(v=>availability[v]).join(', '))+detail(tr('Tageszeiten','Times'),r.period.split(',').map(v=>period[v]).join(', '))+'</dl><small>'+tr('Referenz','Reference')+' · '+esc(r.ref)+'</small></div>'+('<div class="partner-request-code"><div class="partner-request-qr">'+partnerQrSvg(pilot?'https://clubdisplay.ntc93.de/spielpartner/?mode=interest&ref='+r.ref:demoQrBase+'?ref='+r.ref,tr('QR-Code zu Gesuch ','QR code for request ')+r.ref)+'</div><small>'+tr('Interesse anmelden','Register interest')+'</small></div>')+'</article>').join('');
  const qr='<div class="partner-board-qr">'+partnerQrSvg(pilot?'https://clubdisplay.ntc93.de/spielpartner/':demoQrBase+'?board=1',tr('QR-Code zur gesamten Spielpartnerbörse','QR code for the entire partner board'))+'</div>';
  const pager=active==='partners'&&pages>1?'<div class="actions"><button data-action="partners-prev" '+(page===0?'disabled':'')+'>‹</button><span>'+(page+1)+' / '+pages+'</span><button data-action="partners-next" '+(page>=pages-1?'disabled':'')+'>›</button></div>':pages>1?'<p class="match-source">'+(page+1)+' / '+pages+'</p>':'';
  return '<div class="smart-feature partner-board"><div class="match-heading"><h1>'+tr('Wer spielt mit? – Unsere Spielpartnerbörse','Find a playing partner – Our partner board')+'</h1></div><div class="partner-board-layout"><div class="partner-board-requests panel"><div class="partner-board-grid">'+(cards||'<div class="partner-board-empty">'+tr('Aktuell sind keine Gesuche veröffentlicht.','No published requests yet.')+'</div>')+'</div>'+('<p class="partner-board-scan-note">'+tr('Einzelnen QR-Code scannen, um direkt Interesse anzumelden.','Scan an individual QR code to register interest directly.')+'</p>')+'</div><aside class="partner-board-help">'+qr+'<div class="partner-board-qr-purpose"><strong>'+tr('Gesuch erstellen','Create a request')+'</strong><strong>'+tr('Alle Gesuche sehen','View all requests')+'</strong></div><small>'+tr('Gesuche und Kontaktanfragen werden vom Verein geprüft. Kontaktdaten bleiben privat.','Requests and contact enquiries are reviewed by the club. Contact details stay private.')+'</small><small>'+tr('Für alle Vereinsmitglieder · unter 18 mit zusätzlichem Schutz, unter 16 über Erziehungsberechtigte','For all club members · extra safeguards under 18, guardian participation under 16')+'</small>'+(pilot?'':'<small>'+tr('Demo · fiktive Gesuche, keine echten Kontaktanfragen','Demo · fictional requests, no real contact requests')+'</small>')+'</aside></div>'+pager+'</div>';
}
function sectionIcon(key){
  const paths={
    home:'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    courts:'<rect x="3" y="2.5" width="18" height="19" rx="1.5"/><path d="M7 2.5v19M17 2.5v19M3 12h18M7 7h10M7 17h10M12 7v10"/>',
    calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18M7 14h2m4 0h3m-9 4h2"/>',
    teams:'<path d="M7 3h10v7a5 5 0 0 1-10 0zM7 5H3v2a4 4 0 0 0 4 4m10-6h4v2a4 4 0 0 1-4 4M12 15v5m-5 1h10"/>',
    weather:'<path d="M12 3V1m6.4 4.6 1.4-1.4M6.4 5.6 5 4.2M21 11h2M8 9a4 4 0 1 1 7.8 1.2M6 20h12a4 4 0 0 0 0-8 5 5 0 0 0-9-1 4.5 4.5 0 0 0-3 9z"/>',
    news:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h10M7 11h10M7 15h4m-4 3h4"/><rect x="14" y="15" width="3" height="3" rx=".4"/>',
    partners:'<circle cx="8" cy="7" r="3"/><circle cx="17" cy="8" r="2.5"/><path d="M2 21v-3a6 6 0 0 1 12 0v3M16 14a5 5 0 0 1 6 5v2"/>',
    info:'<rect x="3" y="3" width="18" height="14" rx="2"/><path d="m10 7 5 3-5 3zM12 17v4m-5 0h10"/>'
  };
  return paths[key]?'<svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'+paths[key]+'</svg>':'';
}
function whatsappLogo(){
  return '<svg class="whatsapp-logo" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.04 2a9.9 9.9 0 0 0-8.57 14.85L2 22l5.29-1.39A9.99 9.99 0 1 0 12.04 2zm0 1.8a8.2 8.2 0 1 1-4.18 15.26l-.3-.18-3.1.82.83-3.01-.2-.32A8.2 8.2 0 0 1 12.04 3.8z"/><path fill="currentColor" d="M8.2 6.9c-.35 0-.69.18-.96.5-.31.37-.85 1.05-.85 2.23s.86 2.33.98 2.49c.13.16 1.73 2.73 4.29 3.72 2.13.83 2.56.66 3.03.61.46-.04 1.5-.62 1.72-1.22.22-.61.22-1.13.15-1.23-.08-.11-.28-.18-.58-.33l-1.89-.89c-.27-.1-.46-.14-.66.15-.19.29-.74.91-.91 1.1-.17.2-.34.22-.64.07-.29-.14-1.23-.45-2.34-1.43-.87-.77-1.46-1.72-1.63-2.01-.17-.29-.02-.44.12-.59l.44-.51c.15-.17.2-.29.29-.49.1-.19.05-.36-.02-.51L9 7.35c-.2-.47-.39-.45-.8-.45z"/></svg>';
}
function renderNav(){
  const l=t(),items=[['home',l.home],['courts',l.courts],['calendar',l.calendar],['teams',l.teams],['weather',tr('Wetter','Weather')],['news',l.news]];
  if(partnerBoardData?.enabled)items.push(['partners',tr('Spielpartner','Playing partners')]);
  items.push(['info',l.back]);
  $('nav').innerHTML=items.map(([k,v])=>'<button data-action="'+k+'" aria-pressed="'+(view===k)+'">'+sectionIcon(k)+'<span class="nav-label">'+esc(v)+'</span></button>').join('');
}
async function refreshPartnerBoard(){
  if(partnerBusy)return;partnerBusy=true;
  const before=JSON.stringify(partnerBoardData);
  try{
    if(!pilot){
      partnerBoardData={enabled:true,rows:demoPartnerRows()};
    }else{
      const res=await fetch('https://clubdisplay.tennilo.de/admin/?partner=feed&club=ntc93',{credentials:'omit',cache:'no-store',signal:AbortSignal.timeout(8000)});
      if(!res.ok)throw Error('board');
      const data=await res.json();partnerBoardData={enabled:data.enabled===true,rows:Array.isArray(data.rows)?data.rows:[]};
    }
  }catch{partnerBoardData=null;}
  finally{
    partnerBusy=false;
    if(before!==JSON.stringify(partnerBoardData)){
      syncMatchGreetings();
      if(view==='partners'&&!partnerBoardData?.enabled){go('home');return;}
      if(view==='home'||view==='partners'||(view==='info'&&smartScreenKey(screens[slide])?.startsWith('partners:')))render();else renderNav();
    }
  }
}
function syncMatchGreetings(now=Date.now()){
  const active=activeMatchGreetings(leagueData,now);
  const previous=screens[slide];
  const before=greetingMatches.get(smartScreenKey(previous))||greetingEvents.get(smartScreenKey(previous));
  greetingMatches=new Map(active.map(m=>[matchGreetingKey(m),m]));
  greetingEvents=new Map(activeEventGreetings(eventData,now).map(e=>[eventGreetingKey(e),e]));
  const next=smartRotation(baseScreens,leagueData,eventData,[...greetingMatches.keys()],[...greetingEvents.keys()],now);
  next.splice(Math.max(0,next.indexOf('partner')),0,...partnerBoardScreens(now));
  const changed=next.length!==screens.length||next.some((key,i)=>screens[i]!==key);
  if(changed){
    screens.splice(0,screens.length,...next);
    const index=screens.indexOf(previous);
    slide=index>=0?index:0;
    if(view==='info'&&index<0){
      started=now;infoElapsed=0;infoPaused=false;infoPauseUntil=0;
      render();
    }else if(view==='info')infoControls();
  }
  const current=greetingMatches.get(smartScreenKey(screens[slide]))||greetingEvents.get(smartScreenKey(screens[slide]));
  if(view==='info'&&before&&current&&JSON.stringify(before)!==JSON.stringify(current))render();
}
function fixtureAssignedCourts(m){
  const supplied=fixtureCourtNumbers(m.courts);
  if(supplied.length||!pilot||!m.isHome||typeof occupancyData==='undefined'||occupancyData?.status!=='ok'||Date.now()-occupancyData.fetchedAt*1000>180000)return supplied;
  const full=String(m.league??'').trim();
  const cat=full.match(/^(U\d{1,2}\s*[mw]?|[DH](?:\s*\d{2})?|Mixed)(?=\s|$)/iu);
  if(!cat)return [];
  const own=shortClub(m.home);
  const suffix=own.match(/^NTC 93(?:\s+(I{1,3}|IV|V|[1-5]))?$/u);
  if(!suffix)return [];
  const number=({'I':'1','II':'2','III':'3','IV':'4','V':'5'})[suffix[1]]||suffix[1]||'1';
  const team=cat[1].replace(/\s+/g,'').toUpperCase().replace(/^D00$/,'D')+':'+number;
  const start=Date.parse(m.date);
  if(!Number.isFinite(start))return [];
  const matches=Array.isArray(leagueData?.matches)?leagueData.matches:[];
  // A booking must explicitly identify the team and cover the fixture start.
  const rows=(occupancyData.matchBookings||[]).filter(b=>{
    if(b.team!==team||!['indoor','outdoor'].includes(b.area)||!Number.isInteger(b.court)||b.court<1||b.court>(b.area==='indoor'?2:7))return false;
    const a=Date.parse(b.start),z=Date.parse(b.end);
    if(!(a<=start&&start<z))return false;
    return !matches.some(other=>other!==m&&other.isHome&&!other.score&&matchGreetingKey(other)!==matchGreetingKey(m)&&shortClub(other.home)===own&&String(other.league??'').trim().match(/^(U\d{1,2}\s*[mw]?|[DH](?:\s*\d{2})?|Mixed)(?=\s|$)/iu)?.[1]?.replace(/\s+/g,'').toUpperCase()===cat[1].replace(/\s+/g,'').toUpperCase()&&a<=Date.parse(other.date)&&Date.parse(other.date)<z);
  });
  return [...new Set(rows.map(b=>b.area==='indoor'?'H'+b.court:String(b.court)))].sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));
}
function fixtureCourtLabel(n){return String(n).startsWith('H')?tr('Halle ','Indoor court ')+String(n).slice(1):tr('Platz ','Court ')+n;}
function matchGreetingView(key){
  const m=greetingMatches.get(key);
  if(!m)return '';
  const identity=teamAndLeague(m);
  const time=new Intl.DateTimeFormat(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(m.date));
  const courts=fixtureAssignedCourts(m);
  const courtList=courts.length?'<div class="greeting-courts">'+courts.map(n=>'<div class="greeting-court"><svg viewBox="0 0 120 68" aria-hidden="true"><rect x="1" y="1" width="118" height="66" rx="8" fill="#96603e"/><g fill="none" stroke="#fff" stroke-width="1.4"><rect x="8" y="8" width="104" height="52"/><path d="M8 16h104M8 52h104M32 16v36M88 16v36M32 34h56M60 4v60"/></g><circle cx="60" cy="34" r="18" fill="#073538"/><text x="60" y="35" dominant-baseline="middle" text-anchor="middle" font-size="24" font-weight="800" fill="#fff">'+esc(n)+'</text></svg><strong>'+esc(fixtureCourtLabel(n))+'</strong></div>').join('')+'</div>':'<p class="greeting-unassigned">'+tr('Platzzuordnung noch nicht verfügbar','Court assignment not yet available')+'</p>';
  return '<div class="greeting-hero"><div class="greeting-kicker"><span>'+tr('HEIMSPIEL','HOME MATCH')+'</span><span>'+esc(time)+(lang==='de'?' Uhr':'')+'</span></div><div class="greeting-welcome">'+(pilot?tr('Herzlich willkommen beim NTC 93!','Welcome to NTC 93!'):tr('Herzlich willkommen im Demo-Club!','Welcome to the demo club!'))+'</div><div class="greeting-category">'+esc(identity.team)+(identity.league?' · '+esc(identity.league):'')+'</div><div class="greeting-pair"><div class="greeting-home"><small>'+tr('Heim','Home')+'</small><strong>'+esc(shortClub(m.home))+'</strong></div><div class="greeting-versus">–</div><div class="greeting-away"><small>'+tr('Gast','Away')+'</small><strong>'+esc(shortClub(m.away))+'</strong></div></div><div class="greeting-assignment"><h2>'+tr('Eure Plätze','Your courts')+'</h2>'+courtList+'</div><p class="greeting-farewell">'+tr('Wir wünschen euch ein schönes und faires Match!','Have a great and fair match!')+'</p></div>';
}
function teamAndLeague(m){
  const full=String(m.league??'').trim();
  const match=full.match(/^(U\d{1,2}\s*[mw]?|[DH](?:\s*\d{2})?[mw]?|Mid|Mixed)(?=\s|$)\s*(.*)$/iu);
  const own=shortClub(m.isHome?m.home:m.away);
  const suffix=own.match(/^NTC 93\s+(.+)$/u)?.[1]??'';
  if(!match)return {team:own,league:full};
  const key=match[1].replace(/\s+/g,'').toUpperCase();
  let team=match[1];
  if(/^H(?:\d{2})?$/.test(key))team=tr('Herren','Men')+(key.length>1?' '+key.slice(1):'');
  else if(/^D(?:\d{2})?$/.test(key))team=tr('Damen','Women')+(key.length>1&&key.slice(1)!=='00'?' '+key.slice(1):'');
  else if(/^U\d+[MW]?$/.test(key))team=key.replace(/M$/,' m').replace(/W$/,' w');
  else if(key==='MID'||key==='MIXED')team='Mixed';
  return {team:team+(suffix?' '+suffix:''),league:match[2]||full};
}
// Only explicitly supplied court numbers; never guess an assignment.
function fixtureCourtNumbers(raw){
  const parts=Array.isArray(raw)?raw:typeof raw==='string'?raw.split(/[,;\/]/):[];
  const numbers=[];
  for(const item of parts){
    const value=String(item).trim().replace(/^(?:Platz|Court)\s+/i,'');
    if(/^\d{1,3}$/.test(value)){numbers.push(value);continue;}
    const range=value.match(/^(\d{1,3})\s*[-–]\s*(\d{1,3})$/);
    if(range&&Number(range[2])>=Number(range[1])&&Number(range[2])-Number(range[1])<=20){
      for(let n=Number(range[1]);n<=Number(range[2]);n++)numbers.push(String(n));
    }
  }
  return [...new Set(numbers)];
}
function matchView(){
  const d=leagueData, info=view==='info', filter=info?'home':matchFilter;
  const headings={home:tr('Nächste Heimspiele','Upcoming home matches'),away:tr('Nächste Auswärtsspiele','Upcoming away matches'),all:tr('Alle kommenden Spiele','All upcoming matches'),results:tr('Letzte Ergebnisse','Recent results')};
  const tabs=info?'':'<div class="tabs">'+Object.keys(headings).map(k=>'<button data-action="match:'+k+'" aria-pressed="'+(k===filter)+'">'+({home:tr('Heim','Home'),away:tr('Auswärts','Away'),all:tr('Alle','All'),results:tr('Ergebnisse','Results')}[k])+'</button>').join('')+'</div>';
  const title='<div class="match-heading"><h1>'+headings[filter]+'</h1>'+tabs+(pilot&&!info?'<button type="button" class="primary" data-action="nuscore-demo">nuScore 2.0 · Demo</button>':'')+'</div>';
  if(!d)return title+'<div class="panel"><p>'+tr('nuLiga-Daten werden geladen …','Loading nuLiga data …')+'</p></div>';
  const source='<p class="match-source">'+(pilot?tr('Quelle: nuLiga · TVBB','Source: nuLiga · TVBB'):tr('Fiktive Begegnungen · TENNILO-Demo','Fictional fixtures · TENNILO demo'))+(d.fetchedAt?' · '+tr('Stand ','Updated ')+esc(new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'})):'')+''+'</p>';
  if(d.status==='unavailable')return title+'<div class="panel"><p>'+tr('nuLiga momentan nicht verfügbar.','nuLiga currently unavailable.')+'</p></div>'+source;
  const warning=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Daten – Aktualisierung fehlgeschlagen.','Cached data – refresh failed.')+'</p>':'';
  const dateParts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
  const part=k=>dateParts.find(p=>p.type===k)?.value;
  const day=part('year')+'-'+part('month')+'-'+part('day');
  const future=m=>Number.isFinite(Date.parse(m.date))&&fixtureCalendarDay(Date.parse(m.date))>=fixtureCalendarDay(new Date());
  let rows=d.matches.filter(m=>filter==='results'?!!m.score:future(m)&&!m.score&&(filter==='all'||(filter==='home'?m.isHome:!m.isHome)));
  if(filter==='results')rows=rows.slice().reverse();
  if(!rows.length)return title+warning+'<div class="panel"><p>'+tr('Keine passenden Begegnungen im von nuLiga gelieferten Zeitraum.','No matching fixtures in the period supplied by nuLiga.')+'</p>'+(d.range?'<p>'+esc(d.range.from)+' – '+esc(d.range.to)+'</p>':'')+'</div>'+source;
  const cards=rows.map(m=>fixtureMatchCard(m)).join('');
  const pagination='<div class="actions match-pages"><button data-action="match-prev" aria-label="'+tr('Vorherige Seite','Previous page')+'">‹</button><span class="match-page-number"></span><button data-action="match-next" aria-label="'+tr('Nächste Seite','Next page')+'">›</button></div>';
  return title+warning+'<div class="fixture-list">'+cards+'</div>'+pagination+source;
}
function fixtureMatchCard(m){
  const when=new Date(m.date),locale=lang==='de'?'de-DE':'en-GB',zone='Europe/Berlin';
  const weekday=when.toLocaleDateString(locale,{timeZone:zone,weekday:'long'});
  const dayLabel=when.toLocaleDateString(locale,{timeZone:zone,day:'2-digit',month:'2-digit',year:'numeric'});
  const timeLabel=when.toLocaleTimeString(locale,{timeZone:zone,hour:'2-digit',minute:'2-digit'});
  const identity=teamAndLeague(m);
  const venueLabel=typeof m.venue==='string'?m.venue.trim():'';
  const courtNumbers=fixtureAssignedCourts(m);
  const courtStrip='<div class="fixture-court-strip'+(!m.isHome?' fixture-away-strip':'')+'" aria-label="'+(m.isHome?tr('Plätze','Courts'):tr('Auswärtsspiel','Away match'))+'">'+(!m.isHome?'<span class="fixture-away-label">'+tr('Auswärts','Away')+'</span>':courtNumbers.length?courtNumbers.map(n=>'<span class="fixture-court-icon" aria-label="'+esc(fixtureCourtLabel(n))+'"><svg viewBox="0 0 36 22" aria-hidden="true"><rect x="1" y="1" width="34" height="20" rx="3" fill="currentColor"/><path d="M5 4H31V18H5Z M18 4V18 M5 7H31 M5 15H31 M11 7V15 M25 7V15" fill="none" stroke="var(--court-icon-ink)" stroke-width=".8"/><rect x="10" y="3" width="16" height="16" rx="2" fill="currentColor"/><text x="18" y="15" text-anchor="middle" font-size="'+(n.length>2?'8':'13')+'" font-weight="800" fill="var(--court-icon-ink)">'+esc(n)+'</text></svg></span>').join(''):'<span class="fixture-court-unknown">'+tr('Plätze','Courts')+'<strong>–</strong></span>')+'</div>';
  const showVenue=venueLabel&&(!m.isHome||venueLabel.toLocaleLowerCase('de-DE')!=='neuenhagen');
  const meta=showVenue?'<div class="fixture-venue"><span>'+tr('Halle','Indoor venue')+'</span><strong>'+esc(venueLabel)+'</strong></div>':'';
  return '<article class="fixture-card"><time class="fixture-date-block" datetime="'+esc(m.date)+'"><span class="fixture-weekday">'+esc(weekday)+'</span><strong class="fixture-day">'+esc(dayLabel)+'</strong><span class="fixture-time">'+esc(timeLabel)+(lang==='de'?' Uhr':'')+'</span></time>'+courtStrip+'<div class="fixture-content fixture-match-content"><div class="fixture-match-main"><span class="fixture-identity">'+esc(identity.team)+'</span><div class="fixture-pair"><strong class="fixture-team '+(m.isHome?'fixture-own':'')+'">'+esc(shortClub(m.home))+'</strong><span class="fixture-versus">–</span><strong class="fixture-team '+(!m.isHome?'fixture-own':'')+'">'+esc(shortClub(m.away))+'</strong></div></div><div class="fixture-match-meta">'+(identity.league?'<span class="fixture-league">'+esc(identity.league)+'</span>':'')+meta+'</div></div>'+fixtureStatusMarkup(m)+'</article>';
}
function fixtureCalendarDay(value){
  const parts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date(value));
  const get=k=>Number(parts.find(p=>p.type===k)?.value);
  return Date.UTC(get('year'),get('month')-1,get('day'))/86400000;
}
function fixtureStatus(start,now=Date.now()){
  const when=Date.parse(start);
  if(!Number.isFinite(when))return {kind:'pending',label:tr('Spielbeginn','Start'),value:tr('Unbekannt','Unknown')};
  const days=fixtureCalendarDay(when)-fixtureCalendarDay(now);
  const remaining=when-now;
  if(remaining<=0){
    if(days===0)return {kind:'running',label:tr('Nach Zeitplan','Scheduled status'),value:tr('Läuft gerade','In progress')};
    return {kind:'pending',label:tr('Status','Status'),value:tr('Ergebnis ausstehend','Result pending')};
  }
  if(remaining<=3600000){
    const min=Math.ceil(remaining/60000);
    return {kind:'upcoming',label:tr('Spielbeginn','Start'),value:tr('In '+min+' Min.','In '+min+' min')};
  }
  return {kind:'upcoming',label:tr('Spielbeginn','Start'),value:days===0?tr('Heute','Today'):days===1?tr('Morgen','Tomorrow'):tr('In '+days+' Tagen','In '+days+' days')};
}
function fixtureStatusMarkup(m){
  if(m.score)return '<div class="fixture-result"><span>'+tr('Ergebnis','Result')+'</span><strong class="fixture-result-value">'+esc(m.score)+'</strong></div>';
  const status=fixtureStatus(m.date);
  return '<div class="fixture-result fixture-status fixture-status-'+status.kind+'" data-match-start="'+esc(m.date)+'"><span>'+esc(status.label)+'</span><strong>'+esc(status.value)+'</strong></div>';
}
function refreshFixtureStatuses(now=Date.now()){
  if(view!=='teams'&&!(view==='info'&&(smartScreenKey(screens[slide])==='teams'||smartScreenKey(screens[slide]).startsWith('weekend:'))))return;
  for(const el of document.querySelectorAll('[data-match-start]')){
    const status=fixtureStatus(el.dataset.matchStart,now);
    const label=el.querySelector('span'),value=el.querySelector('strong');
    if(label.textContent!==status.label)label.textContent=status.label;
    if(value.textContent!==status.value)value.textContent=status.value;
    el.classList.toggle('fixture-status-running',status.kind==='running');
    el.classList.toggle('fixture-status-upcoming',status.kind==='upcoming');
    el.classList.toggle('fixture-status-pending',status.kind==='pending');
  }
}
async function refreshLeague(){
  if(!pilot){leagueData=demoLeague();syncMatchGreetings();if(view==='teams'||(view==='info'&&(smartScreenKey(screens[slide])==='teams'||smartScreenKey(screens[slide]).startsWith('weekend:'))))render();return;}
  if(fetching)return;fetching=true;
  try{
    const response=await fetch('nuliga.php',{cache:'no-store',signal:AbortSignal.timeout(25000)});
    if(!response.ok)throw Error('HTTP');
    const data=await response.json();
    if(!Array.isArray(data.matches)||!['ok','stale','unavailable'].includes(data.status))throw Error('invalid feed');
    leagueData=data;
  }catch{leagueData=leagueData&&leagueData.status!=='unavailable'?{...leagueData,status:'stale'}:{status:'unavailable',matches:[]};}
  finally{fetching=false;syncMatchGreetings();if(view==='teams'||(view==='info'&&(smartScreenKey(screens[slide])==='teams'||smartScreenKey(screens[slide]).startsWith('weekend:'))))render();}
}
setInterval(()=>{if(!nightActive)refreshLeague();},300000);
setInterval(()=>{if(!nightActive)refreshFixtureStatuses();},10000);
let eventData=null,eventPage=0,eventFetching=false;
function eventView(){
  const info=view==='info',d=eventData,title='<div class="match-heading"><h1>'+t().upcoming+'</h1></div>';
  const source='<p class="match-source">'+tr('Quelle: NTC-Veranstaltungskalender','Source: NTC event calendar')+(d?.fetchedAt?' · '+tr('Stand ','Updated ')+esc(new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'})):'')+'</p>';
  if(!d)return title+'<div class="panel"><p>'+tr('Termine werden geladen …','Loading events …')+'</p></div>';
  if(d.status==='unavailable')return title+'<div class="panel"><p>'+tr('Vereinskalender momentan nicht verfügbar.','Club calendar currently unavailable.')+'</p></div>'+source;
  const warning=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Termine – Aktualisierung fehlgeschlagen.','Cached events – refresh failed.')+'</p>':'';
  const locale=lang==='de'?'de-DE':'en-GB', fmt=(s,options)=>new Date(s).toLocaleString(locale,{timeZone:'Europe/Berlin',...options});
  const parts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const val=k=>parts.find(p=>p.type===k)?.value;const today=val('year')+'-'+val('month')+'-'+val('day');
  const rows=d.events.filter(e=>e.end.slice(0,10)>=today);
  if(!rows.length)return title+warning+'<div class="panel"><p>'+tr('Keine kommenden Veranstaltungen im abgerufenen Zeitraum.','No upcoming events in the retrieved period.')+'</p>'+(d.to?'<p>'+tr('Zeitraum bis ','Period until ')+esc(d.to)+'</p>':'')+'</div>'+source;
  const cards=rows.map(e=>fixtureEventCard(e,info)).join('');
  const paging='<div class="actions match-pages"><button data-action="event-prev" aria-label="'+tr('Vorherige Seite','Previous page')+'">‹</button><span class="match-page-number"></span><button data-action="event-next" aria-label="'+tr('Nächste Seite','Next page')+'">›</button></div>';
  return title+warning+'<div class="fixture-list">'+cards+'</div>'+paging+source;
}
function fixtureEventCard(e,info){
  const locale=lang==='de'?'de-DE':'en-GB', fmt=(s,options)=>new Date(s).toLocaleString(locale,{timeZone:'Europe/Berlin',...options});
  const dayKey=s=>fmt(s,{year:'numeric',month:'2-digit',day:'2-digit'});
  const multi=dayKey(e.start)!==dayKey(e.end);
  const weekday=fmt(e.start,{weekday:'long'});
  const date=fmt(e.start,{day:'2-digit',month:'2-digit',year:'numeric'});
  const time=e.allDay?tr('Ganztägig','All day'):fmt(e.start,{hour:'2-digit',minute:'2-digit'})+(lang==='de'?' Uhr':'');
  const endTime=fmt(e.end,{hour:'2-digit',minute:'2-digit'});
  const endDate=fmt(e.end,{day:'2-digit',month:'2-digit',year:'numeric'});
  const dateBlock=multi?'<strong class="fixture-day">'+esc(date)+'</strong><span class="fixture-range-separator">'+tr('bis','to')+'</span><strong class="fixture-day">'+esc(endDate)+'</strong>':'<span class="fixture-weekday">'+esc(weekday)+'</span><strong class="fixture-day">'+esc(date)+'</strong>';
  const link=!info&&!pilot&&typeof e.url==='string'&&/^https:\/\/(?:www\.)?ntc93\.com\//i.test(e.url)?'<a class="event-link" href="'+esc(e.url)+'" target="_blank" rel="noopener noreferrer">'+tr('Details','Details')+'</a>':'';
  const period=e.allDay?tr('Ganztägig','All day'):fmt(e.start,{hour:'2-digit',minute:'2-digit'})+' – '+endTime+(lang==='de'?' Uhr':'');
  const periodLabel=tr('Zeitraum','Time');
  const cell=(label,value)=>'<div class="event-meta-cell"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong></div>';
  return '<article class="fixture-card event-card'+(link?' event-card-linked':'')+'"><time class="fixture-date-block'+(multi?' fixture-date-range':'')+'" datetime="'+esc(e.start)+'">'+dateBlock+(e.allDay?'':'<span class="fixture-time">'+esc(time)+'</span>')+'</time><div class="fixture-content event-content"><strong class="event-title">'+esc(e.title)+'</strong></div><div class="event-meta">'+cell(periodLabel,period)+cell(tr('Ort','Venue'),e.venue||'–')+'</div>'+link+'</article>';
}
async function refreshEvents(){
  if(!pilot){
    const d=new Date();d.setHours(17,0,0,0);
    const event=(title,offset,hours)=>({title,start:new Date(d.getTime()+offset*86400000).toISOString(),end:new Date(d.getTime()+offset*86400000+hours*3600000).toISOString()});
    eventData={status:'ok',events:[event('Clubabend (Demo)',0,3),event('Clubturnier (Demo)',2,4),{...event('DEMO OPEN',75,8),demo:true}]};
    syncMatchGreetings();if(view==='calendar'||(view==='info'&&(smartScreenKey(screens[slide])==='calendar'||smartScreenKey(screens[slide]).startsWith('weekend:'))))render();return;
  }
  if(eventFetching)return;eventFetching=true;
  try{const response=await fetch('events.php',{cache:'no-store',signal:AbortSignal.timeout(45000)});if(!response.ok)throw Error('HTTP');const data=await response.json();if(!Array.isArray(data.events)||!['ok','stale','unavailable'].includes(data.status))throw Error('feed');eventData=data;}
  catch{eventData=eventData&&eventData.status!=='unavailable'?{...eventData,status:'stale'}:{status:'unavailable',events:[]};}
  finally{eventFetching=false;if(pilot&&nightActive){nightLastMinute=-1;syncNightMode();}syncMatchGreetings();if(view==='calendar'||(view==='info'&&(smartScreenKey(screens[slide])==='calendar'||smartScreenKey(screens[slide]).startsWith('weekend:'))))render();}
}
// The venue calendar remains active in rest mode so new events can wake the display.
setInterval(refreshEvents,300000);

function button(label,action,cls=''){return '<button type="button" class="'+cls+'" data-action="'+action+'">'+label+'</button>'}
function line(a,b){return '<div class="line"><strong>'+a+'</strong><span class="muted">'+b+'</span></div>'}
function panel(title,names,details){return '<div class="panel"><h2>'+title+'</h2>'+names.map((n,i)=>line(n,details[i])).join('')+'</div>'}
function hero(title,description,extra=''){return '<div class="hero"><div><h1>'+title+'</h1><p>'+description+'</p><div class="actions">'+button(t().start,'home','primary large')+'</div></div>'+extra+'</div>'}
let terminalStatus=null,terminalChecking=false,courtMode='outdoor',bookingExpanded=false;
async function checkTerminal(){
  if(!pilot||terminalChecking)return;
  terminalChecking=true;
  try{
    const r=await fetch('terminal.php?status=1',{cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(8000)});
    const d=await r.json();terminalStatus=r.ok&&d.ready?'ready':d.configured?'unavailable':'unconfigured';
  }catch{terminalStatus='unavailable';}
  finally{terminalChecking=false;if(view==='courts'&&courtMode==='outdoor')render();}
}
function bookingExpandLabel(){
  return bookingExpanded?tr('Normale Ansicht','Normal view'):tr('Volle Höhe','Full height');
}
function interactiveCourtMode(){return courtMode==='outdoor'||courtMode==='widget';}
function bookingCanExpand(){return pilot&&view==='courts'&&(courtMode==='widget'||(courtMode==='outdoor'&&terminalStatus==='ready'));}
function syncBookingExpansion(){
  const active=bookingCanExpand();
  if(!active)bookingExpanded=false;
  document.body.classList.toggle('booking-expanded',active&&bookingExpanded);
  const control=$('booking-expand');
  if(control){
    control.setAttribute('aria-pressed',String(bookingExpanded));
    control.setAttribute('aria-label',bookingExpandLabel());
    control.title=bookingExpandLabel();
    control.querySelector('.booking-expand-label').textContent=bookingExpandLabel();
  }
}
function toggleBookingExpansion(){
  if(!bookingCanExpand())return;
  bookingExpanded=!bookingExpanded;
  // Resize the existing iframe using CSS. Never rerender or replace the booking session.
  syncBookingExpansion();
}
function ebusyView(){
  const head='<div class="booking-head"><h1>'+tr('Plätze','Courts')+'</h1>'+button(tr('Zurück','Back'),'home','primary')+'<div class="court-switch" role="group" aria-label="'+tr('Platzbereich','Court area')+'"><button data-action="court-mode:outdoor" aria-pressed="'+(courtMode==='outdoor')+'">'+tr('Außenplätze · PIN','Outdoor courts · PIN')+'</button><button data-action="court-mode:widget" aria-pressed="'+(courtMode==='widget')+'">'+tr('Außenplätze · Online','Outdoor courts · Online')+'</button><button data-action="court-mode:indoor" aria-pressed="'+(courtMode==='indoor')+'">'+tr('Hallenplätze · Belegung','Indoor courts · Availability')+'</button></div>'+(bookingCanExpand()?'<button type="button" id="booking-expand" class="booking-expand-button" data-action="booking-expand" aria-pressed="'+bookingExpanded+'" aria-label="'+esc(bookingExpandLabel())+'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="booking-expand-icon" d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/><path class="booking-collapse-icon" d="M3 8h5V3m13 5h-5V3M8 21v-5H3m13 5v-5h5"/></svg><span class="booking-expand-label">'+esc(bookingExpandLabel())+'</span></button>':'')+'</div>';
  if(courtMode==='widget')return head+'<iframe class="booking-frame booking-widget-frame" title="'+tr('Offizielles eBuSy-Buchungs-Widget','Official eBuSy booking widget')+'" src="booking-widget.php?lang='+lang+'" sandbox="allow-scripts allow-forms allow-same-origin allow-popups" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
  if(courtMode==='indoor')return head+'<div class="hall-readonly"><div class="hall-frame-wrap"><iframe class="booking-frame hall-frame" title="'+tr('Hallenbelegungsplan von eBuSy (nur Ansicht)','eBuSy indoor court schedule (view only)')+'" src="https://cd-ebusy.ntc93.de/embed/court-module/3076" sandbox="allow-scripts allow-same-origin" credentialless inert tabindex="-1" referrerpolicy="no-referrer"></iframe><div class="hall-input-shield" aria-hidden="true"></div></div><div class="hall-toolbar"><span>'+tr('Hallenbelegung · Nur Ansicht','Indoor court occupancy · View only')+'</span><button data-action="hall-refresh">'+tr('Belegungsplan aktualisieren','Refresh schedule')+'</button></div></div>';
  if(terminalStatus==='ready')return head+'<iframe class="booking-frame" title="'+tr('eBuSy Buchungsterminal','eBuSy booking terminal')+'" src="terminal.php" sandbox="allow-scripts allow-forms allow-same-origin" referrerpolicy="no-referrer"></iframe>';
  const label=terminalStatus==='unconfigured'?tr('eBuSy ist noch nicht konfiguriert.','eBuSy is not configured yet.'):terminalStatus==='unavailable'?tr('eBuSy vorübergehend nicht verfügbar.','eBuSy temporarily unavailable.'):tr('Buchungsplan wird geladen …','Loading booking plan …');
  return head+'<div class="panel"><p>'+label+'</p>'+(terminalStatus==='unavailable'||terminalStatus==='unconfigured'?button(tr('Erneut laden','Reload'),'terminal-retry'):'')+'</div>';
}
function courtList(){return '<div class="grid">'+[1,2,3,4].map(i=>'<button class="tile" data-action="court:'+i+'" '+(i===2?'disabled':'')+'><strong>'+t().court+' '+i+'</strong><span class="'+(i===2?'occupied':'availability')+'">'+(i===2?t().busy:t().available)+'</span><small>'+t().slot+'</small></button>').join('')+'</div>'}

let weatherData=null,radarData=null,baseMapData=null,weatherBusy=false,radarBusy=false,lastRadarAttempt=0;
const weatherLabels={'clear-day':['Sonnig','Sunny','☀'],'clear-night':['Klar','Clear','☾'],'partly-cloudy-day':['Heiter','Partly cloudy','⛅'],'partly-cloudy-night':['Teils bewölkt','Partly cloudy','☾'],'cloudy':['Bewölkt','Cloudy','☁'],'fog':['Nebel','Fog','≋'],'rain':['Regen','Rain','🌧'],'sleet':['Schneeregen','Sleet','🌨'],'snow':['Schnee','Snow','❄'],'hail':['Hagel','Hail','🌨'],'thunderstorm':['Gewitter','Thunderstorm','ϟ'],'wind':['Windig','Windy','≋']};
function weatherLabel(icon){const p=weatherLabels[icon]||['Keine Angabe','Unknown','–'];return tr(p[0],p[1]);}
function weatherIcon(icon){return (weatherLabels[icon]||['','','–'])[2];}
function wxNum(value,unit=''){return typeof value==='number'&&Number.isFinite(value)?Math.round(value)+unit:'–';}
function wxTime(value){return typeof value==='number'?new Date(value*1000).toLocaleTimeString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit'}):'–';}
function wxDay(value){return new Date(value+'T12:00:00').toLocaleDateString(lang==='de'?'de-DE':'en-GB',{weekday:'short',day:'numeric',month:'numeric'});}
function weatherActive(){return view==='weather'||(view==='info'&&smartScreenKey(screens[slide])==='weather');}
function headerWeatherSymbol(kind){
  const shapes={
    sunrise:'<path d="M2 18h20M4 21h16M6 18a6 6 0 0 1 12 0M12 2v6m-3-3 3-3 3 3M3 10l2 2m16-2-2 2"/>',
    sunset:'<path d="M2 18h20M4 21h16M6 18a6 6 0 0 1 12 0M12 2v6m-3-3 3 3 3-3M3 10l2 2m16-2-2 2"/>',
    wind:'<path d="M2 8h13a3 3 0 1 0-3-3M2 12h18a3 3 0 1 1-3 3M2 16h8a3 3 0 1 1-3 3"/>',
    rain:'<path d="M12 2c-2.5 4-7 8.8-7 13a7 7 0 1 0 14 0c0-4.2-4.5-9-7-13Z"/>'
  };
  return '<svg class="wx-head-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(shapes[kind]||'')+'</svg>';
}
// Decorative only: uses the same current-weather value as the header, never the rain probability.
function headerWeatherEffect(data,now=Date.now()){
  if(data?.status!=='ok'||!Number.isFinite(data.fetchedAt)||Math.abs(now/1000-data.fetchedAt)>1800)return '';
  const c=data.current;
  if(!c||!Number.isFinite(c.time)||Math.abs(now/1000-c.time)>10800)return '';
  const effects={'rain':'rain','sleet':'sleet','snow':'snow','hail':'hail','thunderstorm':'storm','fog':'fog','wind':'wind','clear-day':'sun','clear-night':'stars','partly-cloudy-day':'cloud','partly-cloudy-night':'cloud','cloudy':'cloud'};
  return Object.prototype.hasOwnProperty.call(effects,c.icon)?effects[c.icon]:'';
}
// Local sunrise/sunset controls the decorative sky, independently of precipitation.
function headerSky(data,now=Date.now()){
  const today=smartDate(now),day=data?.daily?.find(d=>d.date===today);
  const sun=day||data?.sun;
  const rise=sun?.sunrise,set=sun?.sunset,seconds=now/1000;
  if(Number.isFinite(rise)&&Number.isFinite(set)&&set>rise&&smartDate(rise*1000)===today&&smartDate(set*1000)===today){
    if(Math.abs(seconds-rise)<1800)return 'dawn';
    if(Math.abs(seconds-set)<1800)return 'dusk';
    return seconds>=rise&&seconds<set?'day':'night';
  }
  const effect=headerWeatherEffect(data,now);
  if(!effect)return '';
  const icon=data.current.icon;
  return icon.endsWith('-day')?'day':icon.endsWith('-night')?'night':'';
}
// Mean synodic cycle approximation; decorative phase, not moonrise or exact ephemeris.
function headerMoonPhase(now=Date.now()){
  const cycle=29.530588853*86400000;
  return ((now-Date.UTC(2000,0,6,18,14))%cycle+cycle)%cycle/cycle;
}
function headerMoonMarkup(phase){
  const angle=phase*2*Math.PI,cos=Math.cos(angle),waxing=phase<.5;
  const radius=Math.abs(cos)*24,outer=waxing?1:0,inner=waxing?(cos>=0?0:1):(cos>=0?1:0);
  const lit=(1-cos)/2;
  const path=lit<.001?'':lit>.999?'<circle cx="32" cy="32" r="24" fill="#e8efff"/>':'<path d="M32 8 A24 24 0 0 '+outer+' 32 56 '+(radius<.01?'L32 8':'A'+radius.toFixed(3)+' 24 0 0 '+inner+' 32 8')+' Z" fill="#e8efff"/>';
  return '<svg class="sky-moon" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="24" fill="#192d47" stroke="#8ba6c2" stroke-width=".7"/>'+path+'</svg>';
}
function headerSkyMarkup(sky,effect,phase){
  if(!sky)return '';
  const visible=['sun','stars','cloud'].includes(effect);
  if(!visible)return '';
  const night=sky==='night';
  const cloudy=effect==='cloud';
  const celestial=night?headerMoonMarkup(phase):'<svg class="sky-sun" viewBox="0 0 80 80" aria-hidden="true"><g stroke="#ffdb83" stroke-width="2.5" stroke-linecap="round"><path d="M40 3v10m0 54v10M3 40h10m54 0h10M14 14l7 7m38 38 7 7M14 66l7-7m38-38 7-7"/></g><circle cx="40" cy="40" r="21" fill="#ffda82"/></svg>';
  return '<div class="sky-celestial'+(cloudy?' sky-veiled':'')+'">'+celestial+'</div>';
}
function headerSeasonEffect(now=Date.now()){
  const key=smartHoliday(now);
  return key==='holiday:christmas'?'christmas':key.startsWith('holiday:advent:')?'advent-'+key.slice(-1):'';
}
function headerBirthdayLabel(now=Date.now()){
  const age=ntcBirthdayAge(now);
  return pilot&&age!==null?age+' '+tr(age===1?'Jahr':'Jahre',age===1?'year':'years')+' NTC 93':'';
}
function syncHeaderDecor(now=Date.now()){
  const header=document.querySelector('body > header'),layer=$('header-weather-effect'),season=$('header-season-effect');
  if(!header||!layer||!season)return;
  const effect=headerWeatherEffect(weatherData,now),festive=headerSeasonEffect(now);
  const candles=$('header-advent-candles'),advent=festive.startsWith('advent-')?Number(festive.slice(-1)):0;
  if(candles&&candles.dataset.count!==String(advent)){
    candles.dataset.count=String(advent);
    candles.hidden=!advent;
    candles.innerHTML=advent?adventCandles(advent):'';
    header.classList.toggle('has-advent-candles',!!advent);
  }
  const birthday=$('header-birthday'),birthdayLabel=headerBirthdayLabel(now);
  header.dataset.birthday=birthdayLabel?'true':'false';
  if(birthday){
    birthday.hidden=!birthdayLabel;
    const birthdayGreeting=tr('Alles Gute!','Happy birthday!');
    const birthdayCopy=birthdayLabel+'|'+birthdayGreeting;
    if(birthday.dataset.copy!==birthdayCopy){
      birthday.dataset.copy=birthdayCopy;
      birthday.innerHTML=birthdayLabel?'<strong>'+esc(birthdayLabel)+'</strong><small>'+esc(birthdayGreeting)+'</small>':'';
    }
  }
  const sky=headerSky(weatherData,now),phase=Math.round(headerMoonPhase(now)*96)/96;
  const skyKey=effect+'|'+sky+'|'+(sky==='night'?phase:'');
  header.dataset.sky=sky;
  if(header.dataset.skyKey!==skyKey){
    header.dataset.skyKey=skyKey;
    header.dataset.weatherEffect=effect;
    const count=({rain:38,sleet:32,snow:32,hail:25,storm:42,stars:36,wind:12})[effect]||0;
    layer.innerHTML='<div class="header-weather-particles">'+headerSkyMarkup(sky,effect,phase)+Array.from({length:count},(_,i)=>{
      const snow=effect==='snow'||(effect==='sleet'&&i%2===0);
      const kind=snow?'flake':effect==='hail'?'pellet':effect==='stars'?'star':effect==='wind'?'gust':'drop';
      return '<i class="'+kind+'" style="--x:'+((i*37+7)%100)+'%;--y:'+((i*19+5)%95)+'%;--delay:-'+((i*17)%90/10)+'s;--duration:'+(snow?4+i%5:effect==='stars'?5+i%4:1.6+i%4*.45)+'s;--size:'+(3+i%5)+'px"></i>';
    }).join('')+'</div>';
  }
  if(header.dataset.seasonEffect!==festive){
    header.dataset.seasonEffect=festive;
    season.innerHTML=festive?Array.from({length:12},(_,i)=>'<i style="--x:'+((i+0.5)*100/12)+'%;--tone:'+(i%3===0?'#ead9a1':i%3===1?'#e7b45e':'#c4d2a4')+'"></i>').join(''):'';
  }
}
function renderWeatherHeader(){
  syncHeaderDecor();
  const w=weatherData,c=w?.current,s=w?.sun,day=w?.daily?.[0];
  const upcoming=w?.hourly?.find(x=>x.time>=Date.now()/1000);
  const item=(kind,label,value)=>'<span class="wx-head-item">'+headerWeatherSymbol(kind)+'<span><small>'+label+'</small><b>'+value+'</b></span></span>';
  const condition=w?.status==='stale'?tr('Ältere Daten','Cached data'):c?weatherLabel(c.icon):tr('Wetter','Weather');
  $('weather-head').innerHTML='<span class="wx-head-main"><span class="wx-head-icon" aria-hidden="true">'+esc(weatherIcon(c?.icon))+'</span><span class="wx-head-summary"><strong>'+wxNum(c?.temperature,'°C')+'</strong><small>'+esc(condition)+'</small></span></span><span class="wx-head-range"><small>'+tr('Heute','Today')+'</small><b>'+wxNum(day?.min,'°')+' / '+wxNum(day?.max,'°')+'</b></span><span class="wx-head-extra">'+item('wind',tr('Wind','Wind'),wxNum(c?.wind,' km/h'))+item('rain',tr('Regen (nächste Std.)','Rain (next hour)'),wxNum(upcoming?.chance,'%'))+'</span><span class="wx-head-sun">'+item('sunrise',tr('Aufgang','Sunrise'),wxTime(s?.sunrise))+item('sunset',tr('Untergang','Sunset'),wxTime(s?.sunset))+'</span>';
  $('weather-head').title=tr('Wetter Neuenhagen – Details öffnen','Neuenhagen weather – details');
  $('weather-head').setAttribute('aria-label',tr('Wetterdetails öffnen','Open weather details')+' · '+wxNum(c?.temperature,'°C')+' · '+condition);
}
async function refreshWeather(){
  if(weatherBusy)return;weatherBusy=true;
  try{const r=await fetch('weather.php',{cache:'no-store',signal:AbortSignal.timeout(35000)});if(!r.ok)throw Error();weatherData=await r.json();}
  catch{if(weatherData?.fetchedAt&&Date.now()/1000-weatherData.fetchedAt<10800)weatherData.status='stale';else weatherData={status:'unavailable'};}
  finally{weatherBusy=false;renderWeatherHeader();if(weatherActive()&&!$('admin-dialog').open)render();}
}
async function refreshRadar(){
  if(radarBusy||Date.now()-lastRadarAttempt<300000)return;
  radarBusy=true;lastRadarAttempt=Date.now();
  async function request(kind){const r=await fetch('weather.php?'+kind+'=1',{cache:'no-store',signal:AbortSignal.timeout(40000)});if(!r.ok)throw Error();return r.json();}
  const results=await Promise.allSettled([request('radar'),baseMapData?.image&&Date.now()/1000-baseMapData.fetchedAt<86400?Promise.resolve(baseMapData):request('basemap')]);
  if(results[0].status==='fulfilled')radarData=results[0].value;
  else if(radarData?.fetchedAt&&Date.now()/1000-radarData.fetchedAt<1800)radarData.status='stale';else radarData={status:'unavailable'};
  if(results[1].status==='fulfilled')baseMapData=results[1].value;
  else if(!baseMapData?.image)baseMapData={status:'unavailable'};
  radarBusy=false;if(weatherActive()&&!$('admin-dialog').open)render();
}
function inspectRadarImage(){
  const img=$('wx-radar-overlay'),note=$('wx-radar-note');if(!img||!note)return;
  const check=()=>{
    if(!img.naturalWidth||!img.naturalHeight)return;
    try{
      const c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;
      const ctx=c.getContext('2d',{willReadFrequently:true});ctx.drawImage(img,0,0);
      const data=ctx.getImageData(0,0,c.width,c.height).data;
      let visible=false;
      for(let i=3;i<data.length;i+=4){if(data[i]>0){visible=true;break;}}
      if(!visible)note.textContent=tr('In diesem Radarbild sind keine Niederschlagsechos sichtbar.','No precipitation echoes are visible in this radar image.');
    }catch{}
  };
  img.addEventListener('load',check,{once:true});
  if(img.complete)check();
}
let weatherPage='all';
function weatherView(){
  const w=weatherData,c=w?.current,s=w?.sun,d=w?.daily||[],h=w?.hourly||[];
  const head='<div class="wx-title"><h1>'+tr('Wetter','Weather')+'</h1><span>Neuenhagen bei Berlin</span><div class="wx-pages" role="group" aria-label="'+tr('Wetteransichten','Weather views')+'">'+[['all',tr('Übersicht','Overview')],['now',tr('Aktuell','Current')],['hours',tr('12 Stunden','12 hours')],['days',tr('7 Tage','7 days')],['radar',tr('Radar','Radar')]].map(([k,label])=>'<button data-action="weather-page:'+k+'" aria-pressed="'+(weatherPage===k)+'">'+label+'</button>').join('')+'</div></div>';
  const source='<p class="match-source">'+tr('Quelle: Deutscher Wetterdienst · über Bright Sky · Sonnenzeiten berechnet','Source: German Weather Service · via Bright Sky · calculated solar times')+(w?.fetchedAt?' · '+tr('Abruf','Fetched')+' '+wxTime(w.fetchedAt):'')+'</p>';
  if(!w||w.status==='unavailable')return head+'<div class="panel">'+tr(w?'Wetterdaten derzeit nicht verfügbar.':'Wetterdaten werden geladen …',w?'Weather currently unavailable.':'Loading weather …')+'</div>'+source;
  const warn=w.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Wetterdaten – Aktualisierung fehlgeschlagen.','Cached weather – update failed.')+'</p>':'';
  const current='<div class="panel wx-current"><div class="wx-now"><span class="wx-large-icon">'+esc(weatherIcon(c?.icon))+'</span><div><strong>'+wxNum(c?.temperature,'°C')+'</strong><p>'+esc(weatherLabel(c?.icon))+'</p><small>'+tr(w.observed?'Stationsmessung':'Modellwert',w.observed?'Station observation':'Model estimate')+' · '+wxTime(c?.time)+'</small></div></div><div class="wx-metrics"><div><span>'+tr('Wind','Wind')+'</span><strong>'+wxNum(c?.wind,' km/h')+'</strong></div><div><span>'+tr('Böen','Gusts')+'</span><strong>'+wxNum(c?.gust,' km/h')+'</strong></div><div><span>'+tr('Luftfeuchte','Humidity')+'</span><strong>'+wxNum(c?.humidity,'%')+'</strong></div><div><span>'+tr('Sonnenaufgang','Sunrise')+'</span><strong>↑ '+wxTime(s?.sunrise)+'</strong></div><div><span>'+tr('Sonnenuntergang','Sunset')+'</span><strong>↓ '+wxTime(s?.sunset)+'</strong></div></div></div>';
  const hourly='<section class="panel wx-hours"><h2>'+tr('Nächste 12 Stunden','Next 12 hours')+'</h2><div class="wx-hour-list">'+h.slice(0,12).map(x=>'<div class="wx-hour"><small>'+wxTime(x.time)+'</small><span class="wx-symbol" title="'+esc(weatherLabel(x.icon))+'">'+esc(weatherIcon(x.icon))+'</span><strong>'+wxNum(x.temperature,'°')+'</strong><span class="wx-rain">'+wxNum(x.chance,'%')+'</span></div>').join('')+'</div><small>'+tr('Niederschlagswahrscheinlichkeit in %','Precipitation probability in %')+'</small></section>';
  const daily='<section class="panel wx-daily"><h2>'+tr('7-Tage-Vorhersage','7-day forecast')+'</h2><div class="wx-day-list">'+d.map(x=>'<div class="wx-day"><strong>'+esc(wxDay(x.date))+'</strong><span class="wx-symbol" title="'+esc(weatherLabel(x.icon))+'">'+esc(weatherIcon(x.icon))+'</span><span>'+wxNum(x.min,'°')+' / <b>'+wxNum(x.max,'°')+'</b></span><span class="wx-rain">'+wxNum(x.chance,'%')+'</span></div>').join('')+'</div><small>'+tr('Tagesminimum / -maximum · maximale stündliche Niederschlagswahrscheinlichkeit','Daily min / max · maximum hourly precipitation probability')+'</small></section>';
  const r=radarData,b=baseMapData;
  const pngOk=x=>typeof x?.image==='string'&&x.image.length>0&&/^[A-Za-z0-9+/=]+$/.test(x.image);
  const radarOk=pngOk(r),mapOk=pngOk(b);
  const map=mapOk?'<div class="wx-radar-map"><img class="wx-radar-base" alt="'+tr('Amtliche Karte Berlin-Brandenburg','Official Berlin-Brandenburg map')+'" src="data:image/png;base64,'+b.image+'">'+(radarOk?'<img id="wx-radar-overlay" class="wx-radar-overlay" alt="'+tr('DWD-Niederschlagsradar','DWD precipitation radar')+'" src="data:image/png;base64,'+r.image+'">':'')+'<span class="wx-radar-marker"><i></i>Neuenhagen</span></div>':'<div class="wx-radar-empty">'+tr(b?'Grundkarte derzeit nicht verfügbar.':'Karte wird geladen …',b?'Base map currently unavailable.':'Loading map …')+'</div>';
  const credits=mapOk?'<small class="wx-attribution">© GeoBasis-DE / '+(pilot?'BKG (www.bkg.bund.de)':'<a href="https://www.bkg.bund.de" target="_blank" rel="noopener noreferrer">BKG</a>')+' ('+esc(b.sourceYear||'')+') · '+(pilot?'CC BY 4.0 (creativecommons.org/licenses/by/4.0/)':'<a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>')+' · '+tr('Radarüberlagerung und Ortsmarkierung ergänzt','Radar overlay and location marker added')+'</small>':'';
  const note=radarOk?tr('Farbige Flächen zeigen Radarechos.','Colored areas indicate radar echoes.'):tr(r?'Radardaten momentan nicht verfügbar.':'Radar wird geladen …',r?'Radar data currently unavailable.':'Loading radar …');
  const radar='<section class="panel wx-radar"><h2>'+tr('Niederschlagsradar','Precipitation radar')+'</h2>'+map+'<p id="wx-radar-note" class="wx-radar-note">'+note+'</p>'+(radarOk?'<small>'+tr('Radarbild','Radar image')+' · '+wxTime(r.time)+(r.status==='stale'?' · '+tr('älterer Stand','cached'):'')+' · '+tr('Quelle: Deutscher Wetterdienst','Source: German Weather Service')+'</small>':'')+credits+'</section>';
  return head+warn+'<div class="wx-layout"><div class="wx-left">'+current+hourly+daily+'</div>'+radar+'</div>'+source;
}

let newsData=null,newsBusy=false,openedNews=null;
async function refreshNews(){
  if(!pilot||newsBusy)return;newsBusy=true;
  try{
    const response=await fetch('news.php',{cache:'no-store',signal:AbortSignal.timeout(30000)});
    if(!response.ok)throw Error('HTTP');
    const data=await response.json();
    if(!Array.isArray(data.posts)||!['ok','stale','unavailable'].includes(data.status))throw Error('feed');
    newsData=data;
  }catch{newsData=newsData?.posts?.length?{...newsData,status:'stale'}:{status:'unavailable',posts:[]};}
  finally{newsBusy=false;if((view==='news'&&!openedNews)||(view==='info'&&smartScreenKey(screens[slide])==='news'))render();}
}
function newsDate(p){return new Date(p.date).toLocaleDateString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',day:'2-digit',month:'2-digit',year:'numeric'});}
// NTC Matchball: deliberately public-safe editorial summaries of supplied member PDFs.
let matchballIssues=[],communityCodes=[],matchballBusy=false;
async function refreshMatchball(){
  if(!pilot){matchballIssues=demoNewsletterIssues();return;}
  if(matchballBusy)return;matchballBusy=true;const before=JSON.stringify(matchballIssues);
  try{
    const res=await fetch('https://clubdisplay.tennilo.de/admin/?matchball=feed&club=ntc93',{cache:'no-store',signal:AbortSignal.timeout(15000)});
    if(!res.ok)throw Error('feed');const data=await res.json();if(!Array.isArray(data.issues))throw Error('format');
    const oldCommunities=JSON.stringify(communityCodes);
    communityCodes=Array.isArray(data.communities)?data.communities.filter(c=>['youth','adults'].includes(c?.kind)&&typeof c.png==='string'&&c.png.length<=3000000&&/^[A-Za-z0-9+/]+={0,2}$/.test(c.png)):[];
    if(view==='home'&&oldCommunities!==JSON.stringify(communityCodes))render();
    matchballIssues=data.issues.filter(r=>r&&/^20\d{2}-(0[1-9]|1[0-2])$/.test(r.id)&&typeof r.headline==='string'&&typeof r.teaser==='string'&&Array.isArray(r.articles)&&r.articles.every(a=>Array.isArray(a)&&a.length===3&&a.every(v=>typeof v==='string')));
  }catch{matchballIssues=[];if(communityCodes.length){communityCodes=[];if(view==='home')render();}}
  finally{matchballBusy=false;if(before!==JSON.stringify(matchballIssues)&&view==='news'&&openedNews?.matchballId){if(!matchballIssues.some(r=>r.id===openedNews.matchballId))openedNews=null;render();}else if((view==='news'&&!openedNews)||(view==='info'&&smartScreenKey(screens[slide])==='news'))render();}
}
function demoNewsletterIssues(){
  return [
    {id:'2026-11',issue:'11/26',month:tr('November 2026','November 2026'),headline:tr('Winter im Demo-Club','Winter at the demo club'),teaser:tr('Beispielnewsletter · Hallensaison, Clubturnier und Vereinsleben','Sample newsletter · indoor season, club tournament and club life'),pages:2,articles:[],pdf_public:true},
    {id:'2026-10',issue:'10/26',month:tr('Oktober 2026','October 2026'),headline:tr('Rückblick auf die Saison','Season review'),teaser:tr('Fiktive Ausgabe · Sport und Gemeinschaft','Fictional issue · sport and community'),pages:2,articles:[],pdf_public:true}
  ];
}
function matchballCard(){
  const issue=matchballIssues[0];if(!issue)return '';
  return '<button type="button" class="panel news-card matchball-card" data-action="matchball-open:0"><span class="matchball-kicker">'+tr('Unser Vereinsnewsletter','Our club newsletter')+'</span><span class="matchball-brand">'+(pilot?'MATCHBALL':tr('CLUB-NEWS','CLUB NEWS'))+' <b>'+esc(issue.issue)+'</b></span><span class="news-date">'+esc(issue.month)+'</span><strong class="news-card-title">'+esc(issue.headline)+'</strong><span class="news-excerpt">'+esc(issue.teaser)+'</span><span class="news-read-more">'+(issue.pdf_public?tr('PDF lesen · Archiv','Read PDF · archive'):tr('Ausgabe ansehen · Archiv','View issue · archive'))+' →</span></button>';
}
function communityStart(){
  if(!pilot){
    const demoCards=[['youth',tr('Jugend-Community','Junior community')],['adults',tr('Aktive / Senioren','Adults / seniors')]];
    return '<section class="community-start panel"><div class="community-start-heading"><h2>'+whatsappLogo()+'<span>WhatsApp-Communitys</span></h2><p>'+tr('Demo-QR-Codes · keine echten Einladungen','Demo QR codes · no real invitations')+'</p></div><div class="community-start-cards">'+demoCards.map(([kind,label])=>'<div class="community-start-card"><strong>'+esc(label)+'</strong><div class="community-demo-qr">'+partnerQrSvg(demoQrBase+'?community='+kind,label)+'</div></div>').join('')+'</div></section>';
  }
  if(!communityCodes.length)return '';
  const labels={youth:tr('NTC Jugend Community','NTC Junior Community'),adults:tr('NTC Community Aktive / Senioren','NTC Adult / Senior Community')};
  return '<section class="community-start panel"><div class="community-start-heading"><h2>'+whatsappLogo()+'<span>WhatsApp-Communitys</span></h2><p>'+tr('Für Vereinsmitglieder · QR-Code mit WhatsApp scannen','For club members · scan the QR code with WhatsApp')+'</p></div><div class="community-start-cards">'+communityCodes.map(c=>'<div class="community-start-card"><strong>'+esc(labels[c.kind])+'</strong><img src="data:image/png;base64,'+c.png+'" alt="'+esc(labels[c.kind])+' QR-Code" draggable="false"></div>').join('')+'</div></section>';
}
function matchballReader(index){
  const issue=matchballIssues[index];if(!issue)return null;
  const archive=matchballIssues.map((p,i)=>'<button type="button" class="matchball-edition'+(i===index?' is-current':'')+'" data-action="matchball-open:'+i+'" aria-pressed="'+(i===index)+'">'+esc(p.month)+'</button>').join('');
  if(issue.pdf_public===true){
    const src=(pilot?'https://clubdisplay.tennilo.de/admin/?matchball-display-pdf=1&club=ntc93&month=':demoQrBase+'?pdf=')+encodeURIComponent(issue.id)+'#toolbar=1&navpanes=0&view=FitH';
    return '<div class="news-reader-head"><div><span class="matchball-kicker">'+tr('Vereinsnewsletter · Original-PDF','Club newsletter · Original PDF')+'</span><h1>'+(pilot?'MATCHBALL':'CLUB-NEWS · DEMO')+' '+esc(issue.issue)+' · '+esc(issue.month)+'</h1></div>'+button(tr('Zurück zu den Meldungen','Back to news'),'news-back','primary')+'</div><div class="matchball-archive" aria-label="'+tr('Ausgabenarchiv','Issue archive')+'">'+archive+'</div><div class="matchball-pdf-panel"><iframe class="matchball-pdf" src="'+esc(src)+'" title="'+esc('MATCHBALL '+issue.issue)+'" referrerpolicy="no-referrer"></iframe></div><div class="news-reader-foot"><span>'+tr('Original-PDF · Blättern und Zoomen über den PDF-Viewer','Original PDF · page and zoom controls in the PDF viewer')+'</span></div>';
  }
  const articles=issue.articles.map(([title,body,page])=>'<section class="matchball-story"><h2>'+esc(title)+'</h2><p>'+esc(body)+'</p><small>'+tr('Originalausgabe: ','Original issue: ')+esc(page)+'</small></section>').join('');
  return '<div class="news-reader-head"><div><span class="matchball-kicker">'+tr('Vereinsnewsletter · Zusammenfassung','Club newsletter · Summary')+'</span><h1>'+(pilot?'MATCHBALL':'CLUB-NEWS · DEMO')+' '+esc(issue.issue)+' · '+esc(issue.month)+'</h1></div>'+button(tr('Zurück zu den Meldungen','Back to news'),'news-back','primary')+'</div><div class="matchball-archive" aria-label="'+tr('Ausgabenarchiv','Issue archive')+'">'+archive+'</div><div id="news-article-scroll" class="panel news-article-scroll matchball-reader" tabindex="0"><h2>'+esc(issue.headline)+'</h2><p class="matchball-original">'+tr('Lesefassung für das ClubDisplay. Das vollständige Original (','ClubDisplay reading edition. The complete original (')+(issue.pages||tr('Seitenzahl nicht angegeben','page count not specified'))+tr(' Seiten) wurde als E-Mail-Newsletter verschickt.',' pages) was distributed by email.')+'</p><div class="matchball-stories">'+articles+'</div></div><div class="news-reader-foot"><span>'+tr('Quelle: NTC MATCHBALL · redaktionelle Zusammenfassung','Source: NTC MATCHBALL · editorial summary')+'</span><div><button data-action="news-up" aria-label="'+tr('Nach oben','Scroll up')+'">↑</button><button data-action="news-down" aria-label="'+tr('Weiterlesen','Read further')+'">↓</button></div></div>';
}
function newsArticleView(p){
  if(p.matchballId){const i=matchballIssues.findIndex(r=>r.id===p.matchballId);if(i>=0)return matchballReader(i);return '<p>'+tr('Ausgabe nicht verfügbar.','Issue unavailable.')+'</p>';}
  const source='<span class="news-origin">'+(pilot?tr('Originalbeitrag: ','Original article: ')+'ntc93.com':tr('Beispielartikel · TENNILO','Sample article · TENNILO'))+'</span>';
  const image=p.image&&!(p.article||'').includes(esc(p.image))?'<figure class="news-cover"><img src="'+esc(p.image)+'" alt="'+esc(p.imageAlt||'')+'" referrerpolicy="no-referrer"></figure>':'';
  const warning=!p.complete?'<p class="news-article-warning">'+tr('Der vollständige Beitrag ist momentan nicht über die Schnittstelle verfügbar. Angezeigt wird der vorhandene Inhalt.','The complete article is currently unavailable through the feed. Available content is shown.')+'</p>':'';
  return '<div class="news-reader-head"><div><time class="news-date" datetime="'+esc(p.date)+'">'+esc(newsDate(p))+'</time>'+source+'<h1>'+esc(p.title)+'</h1></div>'+button(tr('Zurück zu den Meldungen','Back to news'),'news-back','primary')+'</div><div id="news-article-scroll" class="panel news-article-scroll" tabindex="0" aria-label="'+tr('Artikelinhalt','Article content')+'">'+warning+image+(p.article||'<p>'+esc(p.excerpt)+'</p>')+'</div><div class="news-reader-foot"><span>'+(pilot?tr('Quelle: ntc93.com · Originaltext','Source: ntc93.com · Original text'):tr('Fiktiver Inhalt · TENNILO-Demo','Fictional content · TENNILO demo'))+'</span><div><button data-action="news-up" aria-label="'+tr('Nach oben','Scroll up')+'">↑</button><button data-action="news-down" aria-label="'+tr('Weiterlesen','Read further')+'">↓</button></div></div>';
}
function newsView(){
  if(view==='news'&&openedNews)return newsArticleView(openedNews);
  const title='<div class="match-heading"><h1>'+t().announcements+'</h1><span class="news-origin">'+(pilot?tr('Aktuelle Meldungen von ','Latest news from ')+'ntc93.com':tr('Fiktive Vereinsmeldungen','Fictional club news'))+'</span></div>';
  const d=pilot?newsData:demoNews();
  const stamp=d?.fetchedAt?new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'}):'';
  const source='<p class="match-source">'+(pilot?tr('Quelle: ntc93.com','Source: ntc93.com'):tr('Beispieldaten · TENNILO','Sample data · TENNILO'))+(stamp?' · '+tr('Stand ','Updated ')+esc(stamp):'')+(pilot&&lang==='en'?' · Original German articles':'')+'</p>';
  const newsletter=matchballCard();
  if(!d||d.status==='unavailable')return title+'<div class="news-grid'+(newsletter?' has-matchball':'')+'"><div class="panel news-feed-placeholder">'+tr(d?'Vereinsmeldungen momentan nicht verfügbar.':'Vereinsmeldungen werden geladen …',d?'Club news currently unavailable.':'Loading club news …')+'</div>'+newsletter+'</div>'+source;
  if(!d.posts.length)return title+'<div class="news-grid'+(newsletter?' has-matchball':'')+'"><div class="panel news-feed-placeholder">'+tr('Keine veröffentlichten Meldungen gefunden.','No published news found.')+'</div>'+newsletter+'</div>'+source;
  const warn=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Meldungen – Aktualisierung fehlgeschlagen.','Cached news – refresh failed.')+'</p>':'';
  const cards=d.posts.slice(0,3).map((p,i)=>'<button type="button" class="panel news-card" data-action="news-open:'+i+'"><time class="news-date" datetime="'+esc(p.date)+'">'+esc(newsDate(p))+'</time><strong class="news-card-title">'+esc(p.title)+'</strong><span class="news-excerpt">'+esc(p.excerpt)+'</span><span class="news-read-more">'+tr('Artikel lesen','Read article')+' →</span></button>').join('');
  return title+warn+'<div class="news-grid'+(newsletter?' has-matchball':'')+'">'+cards+newsletter+'</div>'+source;
}
// Snapshot of the NTC homepage sponsor section, verified 2026-11-25. Refresh only on request.
const ntcSponsors=[
  {
    "name": "Fördersiegel Neuenhagen",
    "url": "https://ntc93.com/wp-content/uploads/2026/05/Foerdersiegel-Neuenhagen_RZ-1-240x300.jpg"
  },
  {
    "name": "AMSICO",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/amsico_foto-300x276.png"
  },
  {
    "name": "Bruhn",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/logo_bruhn-300x99.jpg"
  },
  {
    "name": "Allianz Maxelon",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/Allianz-Maxelon-300x155.jpeg"
  },
  {
    "name": "Mutter Hoppe",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/mutterhoppe-300x271.png"
  },
  {
    "name": "Landessportbund",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/LSB_Logo_HZ_Standard_4C_270mm-LSB-300x73.png"
  },
  {
    "name": "TVBB",
    "url": "https://ntc93.com/wp-content/uploads/2023/06/TVBB-Logo-300x300.jpg"
  },
  {
    "name": "Eurailpool",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/logo_eurailpool.jpg"
  },
  {
    "name": "Sparkasse",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/Logo_Sparkasse_2021_R-300x97.jpg"
  },
  {
    "name": "Finwire",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/finwire-logo-white-subline-300x169.png"
  },
  {
    "name": "Robert Vermessung",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/Robert-Vermessung-300x100.jpg"
  },
  {
    "name": "Floraland Arnold",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/Logo_FloralandArnold_2014-outline-300x99.png"
  },
  {
    "name": "City Schuhe",
    "url": "https://ntc93.com/wp-content/uploads/2023/07/Logo-City-Schuhe.jpg"
  },
  {
    "name": "B1",
    "url": "assets/b1-logo.webp"
  },
  {
    "name": "Leben retten",
    "url": "https://ntc93.com/wp-content/uploads/2023/10/lebenretten-300x265.png"
  }
];
function sponsorView(){
  const cards=pilot?ntcSponsors.map(s=>'<figure class="sponsor-logo'+(s.name==='Allianz Maxelon'?' sponsor-logo-allianz':s.name==='B1'?' sponsor-logo-b1':'')+'"><img src="'+esc(s.url)+'" alt="'+esc(s.name)+'" decoding="async" referrerpolicy="no-referrer"></figure>').join(''):demoSponsorCards();
  return '<div class="match-heading sponsor-heading"><h1>'+tr('Partner & Sponsoren','Partners & sponsors')+'</h1><span class="news-origin">'+tr('Vielen Dank für die Unterstützung!','Thank you for your support!')+'</span></div><div class="sponsor-grid">'+cards+'</div><p class="match-source sponsor-source">'+(pilot?tr('Sponsoren und Partner des NTC 93 · Quelle: ntc93.com','NTC 93 sponsors and partners · Source: ntc93.com'):tr('Fiktive Logos · keine tatsächlichen Sponsoren','Fictional logos · not actual sponsors'))+'</p>';
}
// Court overview: only confirmed eBuSy data may mark a court free or occupied.
const occupancyCourts={outdoor:[1,2,3,4,5,6,7],indoor:[1,2]};
let occupancyData=null,occupancyHourKey='',occupancyBusy=false,occupancyRestricted=false;
async function refreshOccupancy(){
  if(!pilot||occupancyBusy)return;
  occupancyBusy=true;
  try{
    // Renew the existing browser registration, but authorize live data only on the server.
    fetch('devices.php?status=1',{cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(6000)}).catch(()=>{});
    const r=await fetch('ebusy-occupancy.php',{cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(55000)});
    occupancyRestricted=r.status===403;
    const d=r.ok?await r.json():null;
    occupancyData=d?.status==='ok'&&Array.isArray(d.slots)?d:null;
  }catch{occupancyData=null;occupancyRestricted=false;}
  finally{occupancyBusy=false;if(view==='teams'||(view==='info'&&(['welcome','teams'].includes(smartScreenKey(screens[slide]))||smartScreenKey(screens[slide]).startsWith('greeting:'))))render();}
}
function occupancyHours(now=Date.now()){
  // Align to the local Berlin hour and advance along the actual time axis (also at DST changes).
  const parts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',minute:'2-digit',second:'2-digit'}).formatToParts(new Date(now));
  const part=k=>Number(parts.find(p=>p.type===k).value);
  const start=now-part('minute')*60000-part('second')*1000-(now%1000);
  return Array.from({length:3},(_,i)=>({start:new Date(start+i*3600000).toISOString(),end:new Date(start+(i+1)*3600000).toISOString()}));
}
function occupancyTime(s){
  return new Intl.DateTimeFormat(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(s));
}
function occupancyRecord(area,court,hour,data=occupancyData){
  // Future adapter: only display-approved booking labels, never raw personal records.
  if(!data||data.status!=='ok'||!Number.isFinite(data.fetchedAt)||Date.now()-data.fetchedAt*1000>180000||!Array.isArray(data.slots))return null;
  return data.slots.find(s=>s.area===area&&s.court===court&&s.start===hour.start&&s.end===hour.end)||null;
}
function occupancySlot(area,court,hour,data=occupancyData){
  const slot=occupancyRecord(area,court,hour,data);
  return ['free','occupied','partial','blocked'].includes(slot?.status)?slot.status:'unknown';
}
function occupancyCourtGraphic(court){
  return '<svg class="occupancy-court-art" viewBox="0 0 120 56" aria-hidden="true"><rect class="court-surface" x="1" y="1" width="118" height="54" rx="5"/><g fill="none" stroke="currentColor" stroke-width="1.1"><rect x="7" y="6" width="106" height="44"/><path d="M7 12h106M7 44h106M31 12v32M89 12v32M31 28h58M60 3v50"/></g><circle class="court-number-disc" cx="60" cy="28" r="13"/><text class="court-number" x="60" y="29" dominant-baseline="middle" text-anchor="middle">'+court+'</text></svg>';
}
function occupancyCategory(value){
  return ['match','tournament','junior','training','blocked','guest','doubles','regular'].includes(value)?value:'regular';
}
function occupancyCategoryLabel(value){
  const labels={match:tr('Punktspiel','League match'),tournament:tr('Turnier','Tournament'),junior:tr('Jugendtraining','Junior training'),training:tr('Training','Training'),blocked:tr('Gesperrt','Closed'),guest:tr('Gastbuchung','Guest booking'),doubles:tr('Doppel','Doubles'),regular:tr('Buchung','Booking')};
  return labels[occupancyCategory(value)];
}
function occupancyBookingText(area,court,hour){
  const record=occupancyRecord(area,court,hour);
  const status=occupancySlot(area,court,hour);
  const fallback={unknown:tr('Noch keine Daten','No data yet'),free:pilot?tr('Nicht gebucht','Not booked'):tr('Frei','Free'),occupied:tr('Belegt','Occupied'),partial:tr('Teilbelegt','Partly occupied'),blocked:tr('Gesperrt','Closed')};
  // Each approved label may describe a player pair, team, training group or closure.
  const entries=(status==='occupied'||status==='partial'||status==='blocked')&&Array.isArray(record?.bookings)?record.bookings.filter(r=>typeof r?.displayLabel==='string'&&(r.displayLabel.trim()||r.category)):[];
  const body=entries.length?entries.map(r=>{
    const category=occupancyCategory(r.category||(status==='blocked'?'blocked':'regular'));
    const bookingType=lang==='de'&&typeof r.bookingType==='string'&&r.bookingType.trim()?r.bookingType.trim():occupancyCategoryLabel(category);
    const label=r.displayLabel.trim();
    const name=label&&label.toLocaleLowerCase('de-DE')!==bookingType.toLocaleLowerCase('de-DE')?'<span class="occupancy-booking-name">'+esc(label)+'</span>':'';
    const kind='<span class="occupancy-booking-type">'+esc(bookingType)+'</span>';
    const timed=typeof r.start==='string'&&typeof r.end==='string'&&Number.isFinite(Date.parse(r.start))&&Number.isFinite(Date.parse(r.end))&&Date.parse(r.start)<Date.parse(r.end);
    const extra=timed&&(entries.length>1||status==='partial')?'<span class="occupancy-booking-time">'+esc(occupancyTime(r.start)+'–'+occupancyTime(r.end))+'</span>':'';
    return '<div class="occupancy-booking-entry booking-kind-'+category+'">'+name+'<div class="occupancy-booking-meta">'+kind+extra+'</div></div>';
  }).join(''):'<span class="occupancy-booking-placeholder">'+fallback[status]+'</span>';
  const kinds=[...new Set(entries.map(r=>occupancyCategory(r.category||(status==='blocked'?'blocked':'regular'))))];
  return '<div class="occupancy-cell occupancy-'+status+(kinds.length===1?' booking-kind-'+kinds[0]:kinds.length>1?' booking-kind-multiple':'')+'">'+body+'</div>';
}
function occupancyView(){
  const hours=occupancyHours();occupancyHourKey=hours[0].start;
  if(!pilot)occupancyData=demoOccupancy(hours);
  const ready=hours.some(hour=>Object.keys(occupancyCourts).some(area=>occupancyCourts[area].some(court=>occupancySlot(area,court,hour)!=='unknown')));
  const header='<div class="occupancy-timeline-head"><span>'+tr('Platz','Court')+'</span>'+hours.map((hour,i)=>'<div class="occupancy-hour-head'+(i===0?' occupancy-hour-current':'')+'"><strong>'+occupancyTime(hour.start)+' – '+occupancyTime(hour.end)+'</strong><small>'+(i===0?tr('Aktuelle Stunde','Current hour'):tr('Danach','Upcoming'))+'</small></div>').join('')+'</div>';
  const group=(area,title)=>'<section class="occupancy-roster-group occupancy-roster-'+area+'"><div class="occupancy-roster-caption">'+title+'</div>'+occupancyCourts[area].map(court=>'<article class="occupancy-roster-row occupancy-court-'+area+'" aria-label="'+title+' · '+tr('Platz ','Court ')+court+'"><div class="occupancy-court-ident">'+occupancyCourtGraphic(court)+'<strong>'+tr('Platz ','Court ')+court+'</strong></div>'+hours.map(hour=>occupancyBookingText(area,court,hour)).join('')+'</article>').join('')+'</section>';
  return '<div class="match-heading occupancy-heading"><h1>'+tr('Wer spielt wo?','Who is playing where?')+'</h1><span class="occupancy-status">'+(!pilot?tr('Fiktive Buchungen · TENNILO-Demo','Fictional bookings · TENNILO demo'):ready?tr('Live-Belegung · eBuSy','Live occupancy · eBuSy'):occupancyRestricted?tr('Live-Belegung nur für freigegebene Geräte und Vereinsnetz','Live occupancy for authorized devices and club network only'):tr('Belegungsdaten derzeit nicht verfügbar','Occupancy data currently unavailable'))+'</span></div><div class="occupancy-roster">' +header+(smartSeason()==='winter'?group('indoor',tr('Hallenplätze','Indoor courts'))+group('outdoor',tr('Außenplätze','Outdoor courts')):group('outdoor',tr('Außenplätze','Outdoor courts'))+group('indoor',tr('Hallenplätze','Indoor courts')))+'</div><div class="occupancy-footer"><span class="occupancy-status">'+(pilot?tr('Nicht gebucht bedeutet nicht automatisch bespielbar.','Not booked does not guarantee availability.'):tr('Anzeige der gebuchten Spieler, Mannschaften und Trainingsgruppen.','Shows booked players, teams and training groups.'))+'</span>'+button(tr('Buchungsplan öffnen','Open booking plan'),'courts','primary')+'</div>';
}
function refreshOccupancyHour(now){
  if(view==='info'&&smartScreenKey(screens[slide])==='welcome'&&occupancyHours(now)[0].start!==occupancyHourKey)render();
}
// NTC-only restaurant slide. Official Due Fratelli assets and published regular hours.
function demoRestaurantView(){
  const row=(a,b)=>'<div class="df-info-row"><span>'+esc(a)+'</span><strong>'+esc(b)+'</strong></div>';
  return '<article class="df-simple-display"><header class="df-simple-header"><div class="df-simple-logo"><h1>CLUB BISTRO</h1><small>'+tr('Fiktiver Gastro-Partner','Fictional restaurant partner')+'</small></div><div class="df-simple-phone"><span>'+tr('Tischreservierung','Table reservations')+'</span><strong>DEMO</strong><span>'+tr('Vor Ort · telefonisch · online','In person · by phone · online')+'</span></div><div class="df-website-block"><strong class="df-website-address">clubdisplay.tennilo.de</strong><div class="df-website-qr">'+partnerQrSvg(demoQrBase+'?restaurant=1',tr('Gastronomie-Demo','Restaurant demo'))+'</div></div></header><div class="df-simple-panels"><section class="df-info-panel"><h2>'+tr('Wochenangebote','Weekly offers')+'</h2>'+row('Pizza Margherita','9,50 €')+row('Pasta al Pomodoro','10,50 €')+'<p>'+tr('Beispielpreise','Sample prices')+'</p></section><section class="df-info-panel"><h2>'+tr('Öffnungszeiten','Opening hours')+'</h2>'+row(tr('Montag','Monday'),tr('Geschlossen','Closed'))+row(tr('Dienstag – Freitag','Tuesday – Friday'),'14:00–22:00')+row(tr('Samstag – Sonntag','Saturday – Sunday'),'12:00–22:00')+'</section><section class="df-info-panel"><h2>'+tr('Sonderöffnungszeiten','Special opening hours')+'</h2>'+row('24.12.',tr('Geschlossen','Closed'))+row('25. / 26.12.','12:00–22:00')+'<p>'+tr('Nur Beispieldaten · keine echten Reservierungen','Sample data only · no real reservations')+'</p></section></div></article>';
}
function restaurantView(){
  const date=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  const row=(day,hours,closed=false)=>'<div class="df-info-row'+(closed?' df-closed':'')+'"><span>'+day+'</span><strong>'+hours+'</strong></div>';
  const weekday=new Intl.DateTimeFormat('en-US',{timeZone:'Europe/Berlin',weekday:'long'}).format(new Date());
  const today=day=>weekday===day?'<small class="df-display-today">'+tr('HEUTE','TODAY')+'</small>':'';
  const festive=date<='2027-01-01';
  const event=date<='2026-12-06';
  const holidays=festive?'<section class="df-info-panel df-holiday-panel"><h2>'+tr('Sonderöffnungszeiten','Holiday opening hours')+'</h2>'+row(tr('24.12. · Heiligabend','24 Dec · Christmas Eve'),tr('Geschlossen','Closed'),true)+row('25. / 26.12.',ntcGastroHoursText(ntcGastroSchedule.special['2026-12-25']))+row('31.12. / 01.01.',ntcGastroHoursText(ntcGastroSchedule.special['2026-12-31']))+(event?'<aside class="df-event-note"><div class="df-event-date"><strong>06.12.</strong><small>'+tr('Sonntag','Sunday')+'</small></div><div class="df-event-copy"><span>'+tr('Geschlossene Veranstaltung bis ca. 16 Uhr','Private event until approximately 16:00')+'</span><strong>'+tr('Reservierungen ab 16 Uhr','Reservations from 16:00')+'</strong></div></aside>':'')+'</section>':'';
  return '<article class="df-simple-display"><header class="df-simple-header"><div class="df-simple-logo"><img src="https://neu.due-fratelli-neuenhagen.de/media/logo.jpg" width="300" height="74" alt="Trattoria Due Fratelli" referrerpolicy="no-referrer"></div><div class="df-simple-phone"><span>'+tr('Tischreservierung','Table reservations')+'</span><strong>03342 203823</strong><span class="df-reservation-options">'+tr('Vor Ort · telefonisch · online','In person · by phone · online')+'</span></div><div class="df-website-block"><strong class="df-website-address">due-fratelli-neuenhagen.de</strong><div class="df-website-qr"><svg viewBox="0 0 37 37" shape-rendering="crispEdges" role="img" aria-label="'+tr('QR-Code zur Website von Due Fratelli','QR code for the Due Fratelli website')+'"><rect width="37" height="37" fill="#fff"/><path d="M4 4h1v1h-1zM5 4h1v1h-1zM6 4h1v1h-1zM7 4h1v1h-1zM8 4h1v1h-1zM9 4h1v1h-1zM10 4h1v1h-1zM13 4h1v1h-1zM14 4h1v1h-1zM16 4h1v1h-1zM18 4h1v1h-1zM22 4h1v1h-1zM23 4h1v1h-1zM26 4h1v1h-1zM27 4h1v1h-1zM28 4h1v1h-1zM29 4h1v1h-1zM30 4h1v1h-1zM31 4h1v1h-1zM32 4h1v1h-1zM4 5h1v1h-1zM10 5h1v1h-1zM15 5h1v1h-1zM20 5h1v1h-1zM21 5h1v1h-1zM22 5h1v1h-1zM26 5h1v1h-1zM32 5h1v1h-1zM4 6h1v1h-1zM6 6h1v1h-1zM7 6h1v1h-1zM8 6h1v1h-1zM10 6h1v1h-1zM13 6h1v1h-1zM14 6h1v1h-1zM15 6h1v1h-1zM18 6h1v1h-1zM19 6h1v1h-1zM21 6h1v1h-1zM23 6h1v1h-1zM24 6h1v1h-1zM26 6h1v1h-1zM28 6h1v1h-1zM29 6h1v1h-1zM30 6h1v1h-1zM32 6h1v1h-1zM4 7h1v1h-1zM6 7h1v1h-1zM7 7h1v1h-1zM8 7h1v1h-1zM10 7h1v1h-1zM13 7h1v1h-1zM14 7h1v1h-1zM15 7h1v1h-1zM16 7h1v1h-1zM17 7h1v1h-1zM22 7h1v1h-1zM26 7h1v1h-1zM28 7h1v1h-1zM29 7h1v1h-1zM30 7h1v1h-1zM32 7h1v1h-1zM4 8h1v1h-1zM6 8h1v1h-1zM7 8h1v1h-1zM8 8h1v1h-1zM10 8h1v1h-1zM13 8h1v1h-1zM16 8h1v1h-1zM26 8h1v1h-1zM28 8h1v1h-1zM29 8h1v1h-1zM30 8h1v1h-1zM32 8h1v1h-1zM4 9h1v1h-1zM10 9h1v1h-1zM12 9h1v1h-1zM13 9h1v1h-1zM14 9h1v1h-1zM16 9h1v1h-1zM19 9h1v1h-1zM20 9h1v1h-1zM22 9h1v1h-1zM23 9h1v1h-1zM26 9h1v1h-1zM32 9h1v1h-1zM4 10h1v1h-1zM5 10h1v1h-1zM6 10h1v1h-1zM7 10h1v1h-1zM8 10h1v1h-1zM9 10h1v1h-1zM10 10h1v1h-1zM12 10h1v1h-1zM14 10h1v1h-1zM16 10h1v1h-1zM18 10h1v1h-1zM20 10h1v1h-1zM22 10h1v1h-1zM24 10h1v1h-1zM26 10h1v1h-1zM27 10h1v1h-1zM28 10h1v1h-1zM29 10h1v1h-1zM30 10h1v1h-1zM31 10h1v1h-1zM32 10h1v1h-1zM14 11h1v1h-1zM16 11h1v1h-1zM17 11h1v1h-1zM21 11h1v1h-1zM22 11h1v1h-1zM24 11h1v1h-1zM4 12h1v1h-1zM7 12h1v1h-1zM9 12h1v1h-1zM10 12h1v1h-1zM12 12h1v1h-1zM14 12h1v1h-1zM15 12h1v1h-1zM18 12h1v1h-1zM19 12h1v1h-1zM20 12h1v1h-1zM23 12h1v1h-1zM25 12h1v1h-1zM27 12h1v1h-1zM4 13h1v1h-1zM5 13h1v1h-1zM7 13h1v1h-1zM8 13h1v1h-1zM9 13h1v1h-1zM11 13h1v1h-1zM12 13h1v1h-1zM17 13h1v1h-1zM21 13h1v1h-1zM25 13h1v1h-1zM26 13h1v1h-1zM29 13h1v1h-1zM32 13h1v1h-1zM4 14h1v1h-1zM5 14h1v1h-1zM7 14h1v1h-1zM10 14h1v1h-1zM11 14h1v1h-1zM13 14h1v1h-1zM14 14h1v1h-1zM16 14h1v1h-1zM17 14h1v1h-1zM18 14h1v1h-1zM20 14h1v1h-1zM23 14h1v1h-1zM25 14h1v1h-1zM27 14h1v1h-1zM28 14h1v1h-1zM29 14h1v1h-1zM30 14h1v1h-1zM31 14h1v1h-1zM4 15h1v1h-1zM6 15h1v1h-1zM9 15h1v1h-1zM13 15h1v1h-1zM14 15h1v1h-1zM15 15h1v1h-1zM17 15h1v1h-1zM19 15h1v1h-1zM20 15h1v1h-1zM22 15h1v1h-1zM23 15h1v1h-1zM24 15h1v1h-1zM25 15h1v1h-1zM26 15h1v1h-1zM27 15h1v1h-1zM28 15h1v1h-1zM30 15h1v1h-1zM31 15h1v1h-1zM4 16h1v1h-1zM5 16h1v1h-1zM6 16h1v1h-1zM7 16h1v1h-1zM8 16h1v1h-1zM9 16h1v1h-1zM10 16h1v1h-1zM13 16h1v1h-1zM15 16h1v1h-1zM18 16h1v1h-1zM20 16h1v1h-1zM21 16h1v1h-1zM23 16h1v1h-1zM24 16h1v1h-1zM25 16h1v1h-1zM26 16h1v1h-1zM29 16h1v1h-1zM31 16h1v1h-1zM32 16h1v1h-1zM9 17h1v1h-1zM12 17h1v1h-1zM17 17h1v1h-1zM18 17h1v1h-1zM23 17h1v1h-1zM24 17h1v1h-1zM27 17h1v1h-1zM5 18h1v1h-1zM7 18h1v1h-1zM8 18h1v1h-1zM10 18h1v1h-1zM11 18h1v1h-1zM12 18h1v1h-1zM13 18h1v1h-1zM16 18h1v1h-1zM17 18h1v1h-1zM18 18h1v1h-1zM20 18h1v1h-1zM26 18h1v1h-1zM28 18h1v1h-1zM29 18h1v1h-1zM30 18h1v1h-1zM31 18h1v1h-1zM32 18h1v1h-1zM5 19h1v1h-1zM6 19h1v1h-1zM9 19h1v1h-1zM12 19h1v1h-1zM14 19h1v1h-1zM15 19h1v1h-1zM19 19h1v1h-1zM20 19h1v1h-1zM21 19h1v1h-1zM23 19h1v1h-1zM24 19h1v1h-1zM25 19h1v1h-1zM26 19h1v1h-1zM29 19h1v1h-1zM31 19h1v1h-1zM5 20h1v1h-1zM7 20h1v1h-1zM8 20h1v1h-1zM10 20h1v1h-1zM12 20h1v1h-1zM14 20h1v1h-1zM16 20h1v1h-1zM17 20h1v1h-1zM21 20h1v1h-1zM24 20h1v1h-1zM25 20h1v1h-1zM31 20h1v1h-1zM8 21h1v1h-1zM9 21h1v1h-1zM12 21h1v1h-1zM16 21h1v1h-1zM17 21h1v1h-1zM18 21h1v1h-1zM20 21h1v1h-1zM21 21h1v1h-1zM25 21h1v1h-1zM26 21h1v1h-1zM27 21h1v1h-1zM29 21h1v1h-1zM32 21h1v1h-1zM4 22h1v1h-1zM8 22h1v1h-1zM10 22h1v1h-1zM11 22h1v1h-1zM12 22h1v1h-1zM14 22h1v1h-1zM18 22h1v1h-1zM20 22h1v1h-1zM22 22h1v1h-1zM24 22h1v1h-1zM26 22h1v1h-1zM27 22h1v1h-1zM28 22h1v1h-1zM31 22h1v1h-1zM32 22h1v1h-1zM6 23h1v1h-1zM7 23h1v1h-1zM9 23h1v1h-1zM14 23h1v1h-1zM15 23h1v1h-1zM16 23h1v1h-1zM18 23h1v1h-1zM21 23h1v1h-1zM24 23h1v1h-1zM26 23h1v1h-1zM31 23h1v1h-1zM32 23h1v1h-1zM4 24h1v1h-1zM6 24h1v1h-1zM8 24h1v1h-1zM10 24h1v1h-1zM11 24h1v1h-1zM15 24h1v1h-1zM16 24h1v1h-1zM19 24h1v1h-1zM20 24h1v1h-1zM23 24h1v1h-1zM24 24h1v1h-1zM25 24h1v1h-1zM26 24h1v1h-1zM27 24h1v1h-1zM28 24h1v1h-1zM30 24h1v1h-1zM12 25h1v1h-1zM14 25h1v1h-1zM15 25h1v1h-1zM16 25h1v1h-1zM18 25h1v1h-1zM21 25h1v1h-1zM23 25h1v1h-1zM24 25h1v1h-1zM28 25h1v1h-1zM30 25h1v1h-1zM31 25h1v1h-1zM32 25h1v1h-1zM4 26h1v1h-1zM5 26h1v1h-1zM6 26h1v1h-1zM7 26h1v1h-1zM8 26h1v1h-1zM9 26h1v1h-1zM10 26h1v1h-1zM14 26h1v1h-1zM15 26h1v1h-1zM16 26h1v1h-1zM22 26h1v1h-1zM24 26h1v1h-1zM26 26h1v1h-1zM28 26h1v1h-1zM31 26h1v1h-1zM4 27h1v1h-1zM10 27h1v1h-1zM12 27h1v1h-1zM14 27h1v1h-1zM18 27h1v1h-1zM20 27h1v1h-1zM22 27h1v1h-1zM24 27h1v1h-1zM28 27h1v1h-1zM29 27h1v1h-1zM30 27h1v1h-1zM32 27h1v1h-1zM4 28h1v1h-1zM6 28h1v1h-1zM7 28h1v1h-1zM8 28h1v1h-1zM10 28h1v1h-1zM13 28h1v1h-1zM19 28h1v1h-1zM20 28h1v1h-1zM21 28h1v1h-1zM24 28h1v1h-1zM25 28h1v1h-1zM26 28h1v1h-1zM27 28h1v1h-1zM28 28h1v1h-1zM32 28h1v1h-1zM4 29h1v1h-1zM6 29h1v1h-1zM7 29h1v1h-1zM8 29h1v1h-1zM10 29h1v1h-1zM12 29h1v1h-1zM15 29h1v1h-1zM21 29h1v1h-1zM23 29h1v1h-1zM26 29h1v1h-1zM28 29h1v1h-1zM29 29h1v1h-1zM30 29h1v1h-1zM31 29h1v1h-1zM4 30h1v1h-1zM6 30h1v1h-1zM7 30h1v1h-1zM8 30h1v1h-1zM10 30h1v1h-1zM13 30h1v1h-1zM15 30h1v1h-1zM16 30h1v1h-1zM17 30h1v1h-1zM18 30h1v1h-1zM23 30h1v1h-1zM25 30h1v1h-1zM28 30h1v1h-1zM29 30h1v1h-1zM30 30h1v1h-1zM32 30h1v1h-1zM4 31h1v1h-1zM10 31h1v1h-1zM16 31h1v1h-1zM17 31h1v1h-1zM18 31h1v1h-1zM19 31h1v1h-1zM20 31h1v1h-1zM21 31h1v1h-1zM22 31h1v1h-1zM23 31h1v1h-1zM24 31h1v1h-1zM27 31h1v1h-1zM31 31h1v1h-1zM4 32h1v1h-1zM5 32h1v1h-1zM6 32h1v1h-1zM7 32h1v1h-1zM8 32h1v1h-1zM9 32h1v1h-1zM10 32h1v1h-1zM12 32h1v1h-1zM15 32h1v1h-1zM16 32h1v1h-1zM17 32h1v1h-1zM18 32h1v1h-1zM21 32h1v1h-1zM24 32h1v1h-1zM27 32h1v1h-1zM28 32h1v1h-1zM29 32h1v1h-1zM31 32h1v1h-1z" fill="#000"/></svg></div></div></header><div class="df-simple-panels'+(festive?'':' df-no-holidays')+'"><section class="df-info-panel df-offer-panel"><h2>'+tr('Wochenaktion','Weekly offer')+'</h2><div class="df-offer'+(weekday==='Tuesday'?' is-today':'')+'"><img class="df-offer-dish" src="https://neu.due-fratelli-neuenhagen.de/media/gericht-pizza.jpg" alt="'+tr('Pizza aus der Küche von Due Fratelli','Pizza from Due Fratelli')+'" decoding="async" referrerpolicy="no-referrer"><span>MARTEDÌ '+today('Tuesday')+'</span><strong>'+tr('Pizza-Dienstag','Pizza Tuesday')+'</strong><b>9,90 €</b></div><div class="df-offer'+(weekday==='Wednesday'?' is-today':'')+'"><img class="df-offer-dish" src="https://neu.due-fratelli-neuenhagen.de/media/gericht-pasta.jpg" alt="'+tr('Pastagericht aus der Küche von Due Fratelli','Pasta from Due Fratelli')+'" decoding="async" referrerpolicy="no-referrer"><span>MERCOLEDÌ '+today('Wednesday')+'</span><strong>'+tr('Pasta-Mittwoch','Pasta Wednesday')+'</strong><b>9,90 €</b></div><p class="df-offer-note">'+tr('Welche Gerichte zur Aktion gehören, erfahrt ihr direkt im Restaurant.','Ask the restaurant which dishes are included in the offer.')+'</p></section><section class="df-info-panel df-regular-panel"><h2>'+tr('Öffnungszeiten','Opening hours')+'</h2>'+row(tr('Montag','Monday'),tr('Ruhetag','Closed'),true)+row(tr('Dienstag – Freitag','Tuesday – Friday'),ntcGastroHoursText(ntcGastroSchedule.weekday))+row(tr('Samstag – Sonntag','Saturday – Sunday'),ntcGastroHoursText(ntcGastroSchedule.weekend))+'<p class="df-offer-note">'+tr('An Feiertagen können die Zeiten abweichen.','Hours may vary on public holidays.')+'</p></section>'+holidays+'</div></article>';
}
function infoControls(){
  const visible=view==='info';
  $('info-controls').hidden=!visible;$('info-controls').setAttribute('aria-label',tr('Informationsmodus','Information mode'));
  if(!visible)return;
  const prev=tr('Vorherige Infoseite','Previous info page'),next=tr('Nächste Infoseite','Next info page');
  for(const [id,label] of [['info-prev',prev],['info-next',next]]){
    $(id).setAttribute('aria-label',label);$(id).title=label;
  }
  const toggle=$('info-toggle'),label=infoPaused?tr('Automatik fortsetzen','Resume automatic rotation'):tr('Automatik anhalten','Pause automatic rotation');
  toggle.setAttribute('aria-label',label);toggle.title=label;toggle.setAttribute('aria-pressed',String(infoPaused));
  $('info-pause-icon').hidden=infoPaused;$('info-play-icon').hidden=!infoPaused;
  $('info-position').textContent=(slide+1)+' / '+screens.length;
  $('info-position').setAttribute('aria-label',tr('Infoseite ','Info page ')+(slide+1)+' / '+screens.length);
  $('mode').textContent=infoPaused?tr('Informationsmodus · angehalten','Information mode · paused'):t().info;
  updateInfoCountdown();
}
function updateInfoCountdown(now=Date.now()){
  const counter=$('info-countdown');
  counter.hidden=view!=='info';
  if(counter.hidden)return;
  const remaining=Math.max(0,Math.ceil((infoPaused?infoPauseUntil-now:dwell-(now-started))/1000));
  const label=infoPaused?tr('Pause','Pause'):tr('Weiter in','Next in');
  const text=label+': '+remaining+' s';
  if(counter.textContent!==text)counter.textContent=text;
  counter.dataset.paused=String(infoPaused);
  counter.title=infoPaused?tr('Automatische Fortsetzung nach 25 Sekunden ohne Eingabe','Automatically resumes after 25 seconds of inactivity'):tr('Zeit bis zur nächsten Infoseite','Time until next info page');
}
function tickInfo(now=Date.now()){
  if(infoPaused&&now>=infoPauseUntil){
    started=now-infoElapsed;infoPaused=false;infoPauseUntil=0;
    infoControls();
  }
  if(!infoPaused&&now-started>=dwell)stepInfo(1);
  updateInfoProgress(now);updateInfoCountdown(now);
}
function infoInput(){
  last=Date.now();
  if(pilot&&!nightActive)nightLastInput=last;
  if(view==='info'&&infoPaused){infoPauseUntil=last+infoPauseLimit;updateInfoCountdown(last);}
}
function updateInfoProgress(now=Date.now()){
  const elapsed=infoPaused?infoElapsed:now-started;
  $('progress').style.setProperty('--progress',Math.max(0,Math.min(100,elapsed/dwell*100))+'%');
}
function stepInfo(direction){
  if(view!=='info')return;
  slide=(slide+direction+screens.length)%screens.length;
  started=Date.now();infoElapsed=0;
  render();updateInfoProgress(started);updateInfoCountdown(started);
}
function toggleInfoPause(){
  if(view!=='info')return;
  const now=Date.now();
  if(infoPaused){started=now-infoElapsed;infoPaused=false;infoPauseUntil=0;}
  else{infoElapsed=Math.max(0,Math.min(dwell,now-started));infoPaused=true;infoPauseUntil=now+infoPauseLimit;}
  infoControls();updateInfoProgress(now);
}
function languageButtonContent(current){
  return current==='de'?'<svg class="language-flag" viewBox="0 0 60 36" aria-hidden="true" focusable="false"><path fill="#012169" d="M0 0h60v36H0z"/><path stroke="#fff" stroke-width="8" d="m0 0 60 36M60 0 0 36"/><path stroke="#C8102E" stroke-width="3" d="m0 0 30 18m0 0 30 18m0-36L30 18m0 0L0 36"/><path fill="#fff" d="M24 0h12v36H24zM0 12h60v12H0z"/><path fill="#C8102E" d="M27 0h6v36h-6zM0 15h60v6H0z"/></svg><span>EN</span>':'<svg class="language-flag" viewBox="0 0 60 36" aria-hidden="true" focusable="false"><path fill="#000" d="M0 0h60v12H0z"/><path fill="#DD0000" d="M0 12h60v12H0z"/><path fill="#FFCE00" d="M0 24h60v12H0z"/></svg><span>DE</span>';
}
function syncLanguageButton(){
  const button=$('language'),label=lang==='de'?'Switch to English':'Auf Deutsch wechseln';
  button.innerHTML=languageButtonContent(lang);
  button.setAttribute('aria-label',label);
  button.title=label;
}
function render(){if(nightActive||premiereActive)return;syncPremiereLaunch();const l=t();document.documentElement.lang=lang;$('club').textContent=personalBrand?personalBrand.name:l.club;$('mode').textContent=view==='info'?l.info:'';$('mode').hidden=view!=='info';$('mode-dot').hidden=view!=='info';$('demo').textContent=pilot?tr('PILOT · TEILWEISE BEISPIELDATEN','PILOT · PARTIAL SAMPLE DATA'):tr('TENNILO · BEISPIELDATEN','TENNILO · SAMPLE DATA');$('notice').textContent=pilot?tr('Vereinsmeldungen von ntc93.com · Buchung über eBuSy.','Club news from ntc93.com · Booking via eBuSy.'):tr('Buchungen, Mannschaften, News und Sponsoren sind fiktiv.','Bookings, teams, news and sponsors are fictional.');$('credit-label').textContent=tr('Design & Entwicklung','Design & development');const productLink=$('product-link');if(productLink){productLink.hidden=location.hostname!=='clubdisplay.tennilo.de';productLink.textContent=tr('Zur Produktseite ↗','Product page ↗');productLink.href=lang==='de'?'https://tennilo.de/clubdisplay/':'https://tennilo.de/en/clubdisplay/';}syncLanguageButton();syncFullscreen();renderWeatherHeader();infoControls();
renderNav();
const active=view==='info'?smartScreenKey(screens[slide]):view;document.body.classList.toggle('match-screen',active==='teams');document.body.classList.toggle('weather-screen',active==='weather');document.body.classList.toggle('court-screen',active==='courts');document.body.classList.toggle('event-screen',active==='calendar');document.body.classList.toggle('weekend-screen',active.startsWith('weekend:'));document.body.classList.toggle('sponsor-screen',active==='partner');document.body.classList.toggle('occupancy-screen',active==='welcome');document.body.classList.toggle('greeting-screen',greetingMatches.has(active)||greetingEvents.has(active)||active.startsWith('holiday:'));document.body.classList.toggle('restaurant-screen',active==='restaurant');let html='';
if(greetingMatches.has(active))html=matchGreetingView(active);else if(greetingEvents.has(active))html=eventGreetingView(active);else if(active.startsWith('holiday:'))html=smartHolidayView(active);else if(active.startsWith('weekend:'))html=smartWeekendView(active);else if(active==='open-countdown')html=smartCountdownView();else if(active==='partners'||active.startsWith('partners:'))html=partnerBoardView(active);else switch(active){
case 'welcome':html=occupancyView();break;
case 'home':{
  const tiles=[
    ['courts',l.booking,pilot?tr('Außenplätze buchen und die Belegung der Hallenplätze ansehen.','Book outdoor courts and view indoor court availability.'):tr('Platzbelegung ansehen und eine Buchung ausprobieren.','View court availability and try a simulated booking.')],
    ['calendar',l.upcoming,tr('Veranstaltungen, Vereinsfeiern und weitere Clubtermine entdecken.','Explore upcoming events, club celebrations and other dates.')],
    ['teams',l.matches,tr('Heim- und Auswärtsspiele, Spielzeiten und Ergebnisse ansehen.','View home and away matches, match times and results.')],
    ['weather',tr('Wetter','Weather'),tr('Aktuelles Wetter, Vorhersage und Niederschlagsradar für Neuenhagen.','Current weather, forecasts and precipitation radar for Neuenhagen.')],
    ['news',l.announcements,tr('Vereinsmeldungen, Hinweise und Informationen aus dem Club.','Read club announcements, notices and information.')]
  ];
  if(partnerBoardData?.enabled)tiles.push(['partners',tr('Spielpartner gesucht','Find a partner'),tr('Gesuch erstellen oder Spielpartner finden.','Create a request or find a partner.')]);
  html='<div class="home-welcome"><h1>'+esc(l.welcome)+'</h1><p>'+esc(l.intro)+'</p></div><div class="home-tiles'+(tiles.length===6?' home-tiles-six':'')+'">'+tiles.map(([key,title,description])=>'<button type="button" class="tile home-tile" data-action="'+key+'"><span class="home-tile-top"><span class="home-tile-label">'+sectionIcon(key)+'<strong>'+esc(title)+'</strong></span><svg class="home-tile-arrow" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span><span class="home-tile-description">'+esc(description)+'</span></button>').join('')+'</div>'+communityStart()+(pilot?'<div class="premiere-home-links"><button data-action="intro">'+tr('So funktioniert ClubDisplay','How ClubDisplay works')+'</button><button data-action="feedback">'+tr('Feedback geben','Give feedback')+'</button></div>':'');
  break;
}
case 'courts':if(pilot){html=ebusyView();break;}html='<h1>'+l.booking+'</h1><p>'+l.select+'</p>'+courtList();if(court!==null)html+='<div class="panel" style="margin-top:1rem"><h2>'+l.court+' '+court+'</h2><p>'+l.slot+'</p><div class="actions">'+button(l.confirm,'confirm','primary')+button(l.reset,'reset')+'</div><p role="status" class="message">'+message+'</p></div>';break;
case 'calendar':html=eventView();break;
case 'teams':html=matchView();break;
case 'weather':html=weatherView();if(!radarBusy)refreshRadar();break;
case 'news':html=newsView();break;
case 'restaurant':html=pilot?restaurantView():demoRestaurantView();break;
case 'partner':html=sponsorView();break;
}
$('language').disabled=pilot&&view==='courts'&&interactiveCourtMode();const embeddedBooking=pilot&&active==='courts',readingNews=active==='news'&&view==='news'&&!!openedNews;document.body.classList.toggle('news-reading',readingNews);$('screen').innerHTML=embeddedBooking||readingNews?html:'<div class="fit-content">'+html+'</div>';syncBookingExpansion();if(!embeddedBooking&&!readingNews)fitActiveScreen();if(active==='weather')inspectRadarImage();clock();}

// Allocate space using rendered content, not viewport-size guesses. Recompute on resize and data changes.
function fitRestaurantScreen(screen,content,available){
  content.style.height='auto';content.style.minHeight='0';content.style.transform='';content.style.transformOrigin='top center';
  const measure=()=>Math.max(content.scrollHeight,content.getBoundingClientRect().height);
  content.dataset.restaurantDensity=available<420?'compact':'normal';
  let natural=measure();
  if(natural>available){content.dataset.restaurantDensity='compact';natural=measure();}
  if(natural>available){content.dataset.restaurantDensity='tight';natural=measure();}
  const scale=Math.min(1,(available-1)/Math.max(1,natural),screen.clientWidth/Math.max(1,content.scrollWidth));
  if(scale<1)content.style.transform='scale('+Math.max(.01,scale)+')';
  else content.style.minHeight=available+'px';
}
$('screen').addEventListener('load',event=>{
  if(event.target.tagName==='IMG'&&document.body.classList.contains('restaurant-screen'))requestAnimationFrame(fitActiveScreen);
},true);
function fitActiveScreen(){
  const screen=$('screen'), content=screen.querySelector('.fit-content');
  if(!content)return;
  content.style.transform='';content.style.width='100%';
  screen.dataset.weatherLayout='';
  const available=screen.clientHeight;
  if(available<=0)return;
  if(document.body.classList.contains('restaurant-screen')){fitRestaurantScreen(screen,content,available);return;}
  if(document.body.classList.contains('match-screen')||document.body.classList.contains('event-screen')){
    const isEvent=document.body.classList.contains('event-screen'),prefix=isEvent?'event':'match';
    const list=content.querySelector('.fixture-list'),pager=content.querySelector('.match-pages');
    if(list&&pager){
      const cards=Array.from(list.children);
      for(const card of cards)card.hidden=false;
      pager.hidden=false;
      const gap=parseFloat(getComputedStyle(list).rowGap)||0;
      // Include all actual rendered chrome (heading, filter, source, pagination, margins).
      const total=content.getBoundingClientRect().height;
      const budget=Math.max(1,available-(total-list.getBoundingClientRect().height)-4);
      const groups=[];let group=[],used=0;
      for(const card of cards){
        const height=card.getBoundingClientRect().height;
        if(group.length&&used+gap+height>budget){groups.push(group);group=[];used=0;}
        used+=(group.length?gap:0)+height;group.push(card);
      }
      if(group.length)groups.push(group);
      const page=view==='info'?0:Math.max(0,Math.min(isEvent?eventPage:matchPage,groups.length-1));
      if(view!=='info'){if(isEvent)eventPage=page;else matchPage=page;}
      const shown=new Set(groups[page]||[]);
      for(const card of cards)card.hidden=!shown.has(card);
      pager.hidden=groups.length<=1||view==='info';
      pager.querySelector('.match-page-number').textContent=(page+1)+' / '+groups.length;
      pager.querySelector('[data-action="'+prefix+'-prev"]').disabled=page<=0;
      pager.querySelector('[data-action="'+prefix+'-next"]').disabled=page>=groups.length-1;
    }
  }else{
    const layout=content.querySelector('.wx-layout'), controls=content.querySelector('.wx-pages');
    if(layout&&controls){
      const panels={now:layout.querySelector('.wx-current'),hours:layout.querySelector('.wx-hours'),days:layout.querySelector('.wx-daily'),radar:layout.querySelector('.wx-radar')};
      const map=layout.querySelector('.wx-radar-map');
      if(map){
        map.style.width='';
        // Size the square map by the remaining vertical space; never stretch its projection.
        const panel=panels.radar;
        const overhead=panel.getBoundingClientRect().height-map.getBoundingClientRect().height;
        const chrome=content.getBoundingClientRect().height-layout.getBoundingClientRect().height;
        const left=layout.querySelector('.wx-left');
        const overview=view==='info'||weatherPage==='all';
        const leftHeight=screen.clientWidth>=920&&overview&&left&&[panels.now,panels.hours,panels.days].every(p=>p&&!p.hidden)?left.getBoundingClientRect().height:Infinity;
        map.style.width=Math.max(80,Math.min(map.clientWidth,available-chrome-overhead-8,leftHeight-overhead))+'px';
      }
      // Keep the full dashboard whenever it fits. Otherwise expose full-sized pages.
      const needsPages=screen.clientWidth<920||content.getBoundingClientRect().height>available-2;
      const selected=view==='info'?'all':weatherPage;
      let shown=selected;
      if(needsPages&&selected==='all')shown='now';
      screen.dataset.weatherLayout=shown;
      controls.hidden=!needsPages&&selected==='all';
      for(const [key,panel] of Object.entries(panels))if(panel)panel.hidden=shown!=='all'&&shown!==key;
      for(const button of controls.querySelectorAll('button')){
        const key=button.dataset.action.split(':')[1];
        button.hidden=key==='all'&&needsPages;
        button.setAttribute('aria-pressed',String(key===shown));
      }
      if(map&&shown==='radar'){
        map.style.width='';
        const overhead=panels.radar.getBoundingClientRect().height-map.getBoundingClientRect().height;
        const chrome=content.getBoundingClientRect().height-layout.getBoundingClientRect().height;
        map.style.width=Math.max(80,Math.min(map.clientWidth,available-chrome-overhead-8))+'px';
      }
    }
  }
  // Extreme viewport sizes and unusually long names: keep all remaining content visible.
  const natural=content.getBoundingClientRect().height;
  if(natural>available&&natural>0){
    const scale=available/natural;
    // A uniformly scaled partner board must still meet both screen edges.
    // Keep its right-hand QR panel and title aligned with the navigation.
    if(content.querySelector('.partner-board')){
      content.style.width=(screen.clientWidth/scale)+'px';
      const adjusted=content.getBoundingClientRect().height;
      if(adjusted>natural){
        const smaller=available/adjusted;
        content.style.width=(screen.clientWidth/smaller)+'px';
        content.style.transform='scale('+smaller+')';
        return;
      }
    }
    content.style.transform='scale('+scale+')';
  }
}
let fitFrame=0;
function scheduleScreenFit(){
  cancelAnimationFrame(fitFrame);
  fitFrame=requestAnimationFrame(()=>{
    if(!(pilot&&view==='courts')&&!openedNews)render();
  });
}

function premiereAvailable(now=Date.now()){
  return pilot&&(smartDate(now)==='2026-12-06'||new URLSearchParams(location.search).get('premiere')==='1');
}
function syncPremiereLaunch(now=Date.now()){
  const b=$('premiere-launch');if(b){b.hidden=!premiereAvailable(now);b.textContent=tr('Premiere','Premiere');}
}
function premierePages(){
  return [
    ['birthday','33 Jahre NTC 93','6. Dezember 1993 – 6. Dezember 2026',tr('Danke an alle, die unseren Verein mit Leben füllen.','Thank you to everyone who brings our club to life.')],
    ['welcome',tr('Ein neues Kapitel im Clubhaus','A new chapter in the clubhouse'),tr('Willkommen beim NTC ClubDisplay','Welcome to NTC ClubDisplay'),tr('Unser digitales Informations- und Buchungsterminal. Für unseren Verein. Für euch.','Our digital information and booking terminal. For our club. For you.')],
    ['touch',tr('Einfach antippen','Simply tap'),tr('1 · Vom Infomodus zum Menü','1 · From info mode to menu'),tr('Berührt den Bildschirm und entdeckt die verschiedenen Bereiche.','Touch the screen to discover the different sections.')],
    ['courts',tr('Plätze im Blick','Courts at a glance'),tr('2 · Platzbelegung und Buchung','2 · Availability and booking'),tr('Im Vereinsnetz die Außenplätze buchen und die Belegung der Plätze ansehen.','Book outdoor courts and see court availability on the club network.')],
    ['teams',tr('Immer informiert','Stay informed'),tr('3 · Das Vereinsleben auf einen Blick','3 · Club life at a glance'),tr('Punktspiele, Termine, Meldungen, Wetter, Gastronomie und Spielpartnerbörse.','League matches, events, news, weather, restaurant and playing partners.')],
    ['info',tr('Zurück zum Infomodus','Back to information mode'),tr('Automatische Anzeige','Automatic display'),tr('Über die Navigation oder nach einer kurzen Pause wechselt ClubDisplay zurück in den Infomodus.','Use the navigation or wait a short while to return to information mode.')],
    ['feedback',tr('Eure Meinung zählt','Your opinion matters'),tr('Helft uns, ClubDisplay weiterzuentwickeln.','Help us improve ClubDisplay.'),tr('QR-Code scannen und Ideen oder Anregungen senden. Ohne Namen und E-Mail-Adresse.','Scan the QR code and send ideas or suggestions. No name or email required.')],
    ['end',tr('Viel Spaß beim Entdecken!','Enjoy exploring!'),tr('Ein Display. Unser Verein.','One display. Our club.'),tr('Entwickelt für den NTC – mit TENNILO ClubDisplay.','Developed for NTC – with TENNILO ClubDisplay.')]
  ];
}
let premiereSelection=null;
function premiereRender(){
  const pages=premiereSelection||premierePages(),p=pages[premiereIndex];if(!p)return;
  const [kind,title,sub,copy]=p;
  const icons={welcome:'home',touch:'home',courts:'courts',teams:'teams',info:'info',end:'home'};
  const art=kind==='birthday'?'<div class="premiere-confetti" aria-hidden="true">'+Array.from({length:36},(_,i)=>'<i style="--x:'+((i*37+7)%100)+'%;--delay:-'+(i%9*.6)+'s;--turn:'+(i*29%180)+'deg"></i>').join('')+'</div><img class="premiere-logo" src="assets/ntc93-logo.png" alt="NTC 93">':kind==='feedback'?'<div class="premiere-qr">'+partnerQrSvg(feedbackUrl,tr('QR-Code zum Feedback','Feedback QR code'))+'</div>':'<div class="premiere-icon">'+sectionIcon(icons[kind]||'home')+'</div>';
  $('premiere-content').innerHTML='<article class="premiere-page premiere-'+kind+'">'+art+'<div class="premiere-copy"><p class="premiere-kicker">'+(premiereMode==='full'?tr('NTC CLUBDISPLAY · VEREINSPREMIERE','NTC CLUBDISPLAY · CLUB PREMIERE'):premiereMode==='intro'?tr('NTC CLUBDISPLAY · KURZANLEITUNG','NTC CLUBDISPLAY · QUICK GUIDE'):tr('NTC CLUBDISPLAY · FEEDBACK','NTC CLUBDISPLAY · FEEDBACK'))+'</p><h1>'+esc(title)+'</h1><h2>'+esc(sub)+'</h2><p>'+esc(copy)+'</p>'+(kind==='feedback'?'<a href="'+feedbackUrl+'" target="_blank" rel="noopener noreferrer">clubdisplay.ntc93.de/feedback.php</a>':'')+'</div></article>';
  $('premiere-position').textContent=(premiereIndex+1)+' / '+pages.length;
  $('premiere-prev').disabled=premiereIndex===0;
  $('premiere-pause').textContent=premierePaused?tr('Fortsetzen','Resume'):tr('Pause','Pause');
  $('premiere-next').textContent=premiereIndex===pages.length-1?tr('Fertig','Done'):tr('Weiter ›','Next ›');
  $('premiere-stop').textContent=tr('Beenden','Exit');
}
function startPremiere(mode='full'){
  if(!pilot||nightActive||premiereActive||adminDialog.open||(mode==='full'&&!premiereAvailable())||(view==='courts'&&interactiveCourtMode()))return;
  const pages=premierePages();premiereSelection=mode==='intro'?pages.slice(2,6):mode==='feedback'?[pages[6]]:pages;
  premiereIndex=0;premiereActive=true;premiereMode=mode;premiereLastInput=Date.now();premierePaused=mode==='feedback';premiereRemaining=premiereDwell;premiereDeadline=Date.now()+premiereDwell;
  $('premiere-overlay').hidden=false;document.body.classList.add('premiere-active');
  for(const el of document.querySelectorAll('body>header,body>main,body>nav,body>footer'))el.inert=true;
  premiereRender();$('premiere-stop').focus();
}
function stopPremiere(){
  if(!premiereActive)return;
  premiereActive=false;premiereSelection=null;$('premiere-overlay').hidden=true;document.body.classList.remove('premiere-active');
  for(const el of document.querySelectorAll('body>header,body>main,body>nav,body>footer'))el.inert=false;
  nightLastInput=Date.now();go('info');
}
function premiereStep(delta,now=Date.now()){
  if(!premiereActive)return;
  const next=premiereIndex+delta;
  if(next>=premiereSelection.length){stopPremiere();return;}
  if(next<0)return;
  premiereIndex=next;premiereRemaining=premiereDwell;premiereDeadline=now+premiereDwell;premiereRender();
}
function premiereTick(now=Date.now()){
  if(!premiereActive)return false;
  if(now-premiereLastInput>=300000){stopPremiere();return false;}
  if(!premierePaused&&now>=premiereDeadline)premiereStep(1,now);
  return premiereActive;
}
// Single schedule for the NTC gastro and rest mode; times are Berlin local.
const ntcGastroSchedule={
  weekday:[14,22],weekend:[12,22],
  special:{'2026-12-06':[16,22],'2026-12-24':null,'2026-12-25':[12,22],'2026-12-26':[12,22],'2026-12-31':[14,22],'2027-01-01':[14,22]}
};
function restLocalParts(ms){
  const fmt=restLocalParts.fmt||(restLocalParts.fmt=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}));
  return Object.fromEntries(fmt.formatToParts(new Date(ms)).filter(p=>p.type!=='literal').map(p=>[p.type,Number(p.value)]));
}
function restDateKey(ms){const p=restLocalParts(ms);return p.year+'-'+String(p.month).padStart(2,'0')+'-'+String(p.day).padStart(2,'0');}
function restDateAt(day,hour=0,minute=0){
  const [y,m,d]=day.split('-').map(Number),target=Date.UTC(y,m-1,d,hour,minute);let guess=target;
  for(let i=0;i<3;i++){const p=restLocalParts(guess),actual=Date.UTC(p.year,p.month-1,p.day,p.hour,p.minute,p.second);if(actual===target)break;guess+=target-actual;}
  return guess;
}
function restShiftDate(day,delta){return new Date(Date.parse(day+'T00:00:00Z')+delta*86400000).toISOString().slice(0,10);}
function ntcGastroHours(day){
  if(Object.prototype.hasOwnProperty.call(ntcGastroSchedule.special,day))return ntcGastroSchedule.special[day];
  const weekday=new Date(day+'T00:00:00Z').getUTCDay();return weekday===1?null:weekday===0||weekday===6?ntcGastroSchedule.weekend:ntcGastroSchedule.weekday;
}
function ntcGastroHoursText(hours){return hours.map(h=>String(h).padStart(2,'0')+':00').join('–');}
function restClubhouseEvent(e){
  return e&&typeof e.title==='string'&&e.title.trim()&&e.cancelled!==true&&e.cancelled!=='true'&&String(e.status||'').toLowerCase()!=='cancelled'&&/\b(?:clubhaus|klubhaus|vereinsheim|clubhouse)\b/i.test(String(e.venue||''));
}
function restSchedule(now=Date.now(),data=eventData){
  const minute=Math.floor(now/60000);
  if(restSchedule.cache?.minute===minute&&restSchedule.cache.data===data)return restSchedule.cache.value;
  const today=restDateKey(now),ranges=[];
  for(let i=-1;i<=8;i++){
    const day=restShiftDate(today,i),hours=ntcGastroHours(day);
    if(hours)ranges.push([restDateAt(day,hours[0]),restDateAt(day,hours[1])]);
  }
  const events=data?.status!=='unavailable'&&Array.isArray(data?.events)?data.events:[];
  let birthdayInCalendar=false;
  for(const e of events){
    if(!restClubhouseEvent(e))continue;
    const start=Date.parse(e.start),end=Date.parse(e.end);
    if(!Number.isFinite(start)||!Number.isFinite(end)||end<=start)continue;
    if(restDateKey(start)==='2026-12-06'&&/geburtstag/i.test(e.title)){
      birthdayInCalendar=true;
      // The confirmed private restaurant event extends until approximately 16:00.
      ranges.push([start-1800000,Math.max(end,restDateAt('2026-12-06',16))]);
    }else ranges.push([start-1800000,end]);
  }
  // Confirmed one-off club birthday; retain the appointment if the calendar cannot supply it.
  if(!birthdayInCalendar&&data?.status!=='ok')ranges.push([restDateAt('2026-12-06',11)-1800000,restDateAt('2026-12-06',16)]);
  // The confirmed 06.12 restaurant closure is a private event, not an ordinary closed period.
  // If the calendar is available but the birthday is absent, do not invent a cancelled appointment.
  const value={active:ranges.some(([a,z])=>now>=a&&now<z),next:ranges.map(r=>r[0]).filter(a=>a>now).sort((a,b)=>a-b)[0]??null};
  restSchedule.cache={minute,data,value};return value;
}
function nightWindow(now=Date.now()){
  return !restSchedule(now).active;
}
function nightShouldSleep(now,lastInput,initial,modal){
  return nightWindow(now)&&!modal&&(initial||now-lastInput>=nightIdle);
}
function renderNightClock(now=Date.now()){
  const minute=Math.floor(now/60000);if(minute===nightLastMinute)return;nightLastMinute=minute;
  const locale=lang==='de'?'de-DE':'en-GB',date=new Date(now);
  $('night-clock').textContent=new Intl.DateTimeFormat(locale,{hour:'2-digit',minute:'2-digit',hourCycle:'h23',timeZone:'Europe/Berlin'}).format(date);
  $('night-date').textContent=new Intl.DateTimeFormat(locale,{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Berlin'}).format(date);
  $('night-hint').textContent=tr('Bildschirm berühren zum Aufwecken','Touch the screen to wake');
  const next=restSchedule(now).next;
  const nextText=next===null?'':new Intl.DateTimeFormat(locale,{timeZone:'Europe/Berlin',...(restDateKey(next)===restDateKey(now)?{}:{weekday:'short',day:'numeric',month:'numeric'}),hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(next));
  $('night-label').textContent=tr('Ruhemodus','Rest mode')+(nextText?' · '+tr('wieder aktiv ab ','active again at ')+nextText+(lang==='de'?' Uhr':''):'');
  const shift=Math.floor(minute/10)%5; $('night-content').style.setProperty('--shift-x',((shift-2)*9)+'px');$('night-content').style.setProperty('--shift-y',((shift*3%5-2)*7)+'px');
}
function refreshDayData(){
  refreshLeague();refreshEvents();refreshWeather();refreshNews();refreshMatchball();refreshPartnerBoard();if(pilot)refreshOccupancy();
}
function leaveNightMode(interactive=false){
  if(!nightActive)return;
  nightActive=false;document.body.classList.remove('night-mode');$('night-screen').hidden=true;
  for(const el of document.querySelectorAll('body>header,body>main,body>nav,body>footer'))el.inert=false;
  nightLastMinute=-1;
  if(interactive)nightLastInput=Date.now();
  if(nightPreserveBooking){
    // Leave the existing iframe attached: do not reload or reset a booking session.
    nightPreserveBooking=false;last=Date.now();clock();refreshDayData();return;
  }
  go(interactive?'home':'info');clock();refreshDayData();
}
function syncNightMode(now=Date.now(),initial=false){
  if(!pilot||premiereActive||(typeof nuscoreDemoActive!=='undefined'&&nuscoreDemoActive()))return false;
  if(nightActive){if(!nightWindow(now)){leaveNightMode();return false;}renderNightClock(now);return true;}
  const modal=adminDialog.open||$('personal-demo-dialog')?.open;
  if(!nightShouldSleep(now,nightLastInput,initial,modal))return false;
  // Cover a booking view without removing its iframe; never force an auto-update during that session.
  nightPreserveBooking=view==='courts'&&interactiveCourtMode()&&!!document.querySelector('#screen iframe.booking-frame');
  if(!nightPreserveBooking&&view!=='info'){view='info';slide=0;openedNews=null;court=null;message='';}
  infoPaused=false;infoElapsed=0;infoPauseUntil=0;started=now;
  if(!nightPreserveBooking)last=Math.min(last,now-nightIdle);
  nightActive=true;document.body.classList.add('night-mode');$('night-screen').hidden=false;
  for(const el of document.querySelectorAll('body>header,body>main,body>nav,body>footer'))el.inert=true;
  renderNightClock(now);return true;
}
function clock(){const now=new Date();const locale=lang==='de'?'de-DE':'en-GB';$('clock').textContent=new Intl.DateTimeFormat(locale,{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Berlin'}).format(now);$('date').textContent=new Intl.DateTimeFormat(locale,{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Berlin'}).format(now);}
function go(next){openedNews=null;if(next!=='courts'||view!=='courts')bookingExpanded=false;if(next==='weather'&&view!=='weather')weatherPage='all';if(pilot&&next==='courts'&&view!=='courts'){courtMode='outdoor';terminalStatus=null;checkTerminal();}if(next==='calendar')eventPage=0;if(next==='teams'){matchFilter='home';matchPage=0;}view=next;court=null;message='';last=started=Date.now();if(next==='info'){slide=0;infoPaused=false;infoElapsed=0;infoPauseUntil=0;}render();}
document.addEventListener('click',e=>{if(nightActive||premiereActive)return;infoInput();const b=e.target.closest('[data-action]');if(!b){if(e.target.closest('a'))return;if(view==='info'&&e.target.closest('#screen'))go('home');return;}const a=b.dataset.action;if(a==='nuscore-demo'&&pilot&&view==='teams'){openNuscoreDemo();}else if(a==='intro'||a==='feedback'){startPremiere(a);}else if(a==='info-prev'){stepInfo(-1);}else if(a==='info-next'){stepInfo(1);}else if(a==='info-toggle'){toggleInfoPause();}else if(a.startsWith('news-open:')&&(view==='news'||(view==='info'&&smartScreenKey(screens[slide])==='news'))){const index=Number(a.split(':')[1]);const p=(pilot?newsData:demoNews())?.posts?.[index];if(p){openedNews=p;view='news';render();}}else if(a.startsWith('matchball-open:')&&(view==='news'||(view==='info'&&smartScreenKey(screens[slide])==='news'))){const index=Number(a.split(':')[1]);if(Number.isInteger(index)&&matchballIssues[index]){openedNews={matchballId:matchballIssues[index].id};view='news';render();}}else if(a==='news-back'){openedNews=null;go('news');}else if(a==='news-up'||a==='news-down'){const reader=$('news-article-scroll');if(reader)reader.scrollBy({top:(a==='news-up'?-1:1)*reader.clientHeight*.85,behavior:'smooth'});}else if(a.startsWith('weather-page:')&&weatherActive()){const next=a.split(':')[1];if(['all','now','hours','days','radar'].includes(next)){view='weather';weatherPage=next;render();}}else if(a==='booking-expand'){toggleBookingExpansion();}else if(a.startsWith('court-mode:')&&pilot&&view==='courts'){const next=a.split(':')[1];if(['outdoor','widget','indoor'].includes(next)&&next!==courtMode){courtMode=next;bookingExpanded=false;if(next==='outdoor'){terminalStatus=null;checkTerminal();}render();}}else if(a==='hall-refresh'&&pilot&&view==='courts'&&courtMode==='indoor'){render();}else if(a==='terminal-retry'&&pilot&&view==='courts'){checkTerminal();}else if(a==='partners-prev'&&view==='partners'){partnerPage=Math.max(0,partnerPage-1);render();}else if(a==='partners-next'&&view==='partners'){partnerPage++;render();}else if(a==='partners'&&partnerBoardData?.enabled){partnerPage=0;go('partners');}else if(a==='event-prev'){eventPage=Math.max(0,eventPage-1);render();}else if(a==='event-next'){eventPage++;render();}else if(a.startsWith('match:')){matchFilter=a.split(':')[1];matchPage=0;render();}else if(a==='match-prev'){matchPage=Math.max(0,matchPage-1);render();}else if(a==='match-next'){matchPage++;render();}else if(a.startsWith('court:')){court=Number(a.split(':')[1]);message='';render();}else if(a==='confirm'&&court!==null){message=t().success;render();}else if(a==='reset'){court=null;message='';render();}else if(['info','home','courts','calendar','teams','news','weather'].includes(a))go(a);});
for(const event of ['pointerdown','keydown','wheel','touchmove'])document.addEventListener(event,()=>{if(!nightActive)infoInput();});
$('night-screen').addEventListener('click',e=>{e.stopPropagation();leaveNightMode(true);});
// Moving focus into the embedded booking frame counts as real interaction, not an indefinite exemption.
window.addEventListener('blur',()=>{if(pilot&&!nightActive)setTimeout(()=>{if(view==='courts'&&document.activeElement?.matches('iframe.booking-frame'))nightLastInput=Date.now();},0);});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)syncNightMode();});
document.addEventListener('keydown',e=>{if(nightActive&&(e.key==='Enter'||e.key===' ')){e.preventDefault();leaveNightMode(true);}});
$('language').addEventListener('click',()=>{if(pilot&&view==='courts'&&interactiveCourtMode())return;lang=lang==='de'?'en':'de';message='';render();});

const adminDialog=$('admin-dialog'), adminForm=$('admin-form'), adminPin=$('admin-pin'), adminStatus=$('admin-status');
const nuscoreDialog=$('nuscore-demo-dialog');
const nuscoreDemoUrl='https://tende-appsdemo.liga.nu/nuliga/nuscore-tennis2/welcome';
function nuscoreDemoActive(){return !!nuscoreDialog?.open;}
function openNuscoreDemo(){
  if(!pilot||nightActive||premiereActive||!nuscoreDialog||nuscoreDialog.open)return;
  $('nuscore-demo-title').textContent=tr('nuScore 2.0 · Testzugang','nuScore 2.0 · Test access');
  $('nuscore-demo-note').textContent=tr('Nur Testdaten verwenden. Vor dem Verlassen in nuScore speichern. Die Demo-Adresse ist noch nicht bestätigt.','Use test data only. Save in nuScore before leaving. Demo address is not yet confirmed.');
  $('nuscore-demo-back').textContent=tr('Zurück zu ClubDisplay','Back to ClubDisplay');
  $('nuscore-demo-end').textContent=tr('Testansicht schließen','Close test view');
  if(!$('nuscore-demo-frame')){
    const frame=document.createElement('iframe');frame.id='nuscore-demo-frame';frame.title='nuScore 2.0 Demo';frame.setAttribute('sandbox','allow-scripts allow-forms allow-same-origin');frame.referrerPolicy='no-referrer';frame.src=nuscoreDemoUrl;$('nuscore-demo-content').append(frame);
  }
  nuscoreDialog.showModal();infoInput();
}
nuscoreDialog.addEventListener('cancel',e=>{e.preventDefault();nuscoreDialog.close();});
nuscoreDialog.addEventListener('close',()=>infoInput());
$('nuscore-demo-back').addEventListener('click',()=>nuscoreDialog.close());
$('nuscore-demo-end').addEventListener('click',()=>{
  if(!window.confirm(tr('Testansicht schließen? Bitte zuerst in nuScore speichern. Lokal gespeicherte Spielberichte werden dadurch nicht gelöscht.','Close test view? Save in nuScore first. Locally stored reports will not be deleted.')))return;
  $('nuscore-demo-frame')?.remove();nuscoreDialog.close();
});
function syncFullscreen(){
  const active=!!document.fullscreenElement;
  $('fullscreen').textContent=active?'🔒':'⛶';
  $('fullscreen').setAttribute('aria-label',active?tr('Vollbild verlassen – Admin-PIN erforderlich','Exit fullscreen – admin PIN required'):tr('Vollbild starten','Enter fullscreen'));
  $('fullscreen').title=$('fullscreen').getAttribute('aria-label');
}
document.addEventListener('fullscreenchange',syncFullscreen);
$('fullscreen').addEventListener('click',async()=>{
  if(!document.fullscreenElement){
    try{await document.documentElement.requestFullscreen();}catch{ /* Browser may block fullscreen. */ }
    return;
  }
  openAdmin();
});
function openAdmin(){
  if(adminDialog.open)return;
  $('admin-title').textContent=tr('Admin-Freigabe','Admin approval');
  $('admin-label').textContent=tr('Admin-PIN','Admin PIN');
  $('admin-submit').textContent=tr('Vollbild verlassen','Exit fullscreen');
  $('admin-cancel').textContent=tr('Abbrechen','Cancel');
  adminPin.value='';adminStatus.textContent='';adminDialog.showModal();adminPin.focus();
}
$('admin-cancel').addEventListener('click',()=>{adminPin.value='';adminDialog.close();});
adminDialog.addEventListener('close',()=>{adminPin.value='';adminStatus.textContent='';last=Date.now();});
adminForm.addEventListener('submit',async e=>{
  e.preventDefault();
  if($('admin-submit').disabled)return;
  $('admin-submit').disabled=true;
  const pin=adminPin.value;adminPin.value='';
  adminStatus.textContent=tr('Prüfe …','Checking …');
  try{
    const res=await fetch('admin-pin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pin}),cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(8000)});
    const data=await res.json();
    if(!adminDialog.open)return;
    if(res.ok&&data.ok===true){adminDialog.close();if(document.fullscreenElement)await document.exitFullscreen();return;}
    adminStatus.textContent=res.status===429?tr('Zu viele Versuche. Bitte später erneut versuchen.','Too many attempts. Try again later.'):res.status===503?tr('Admin-PIN noch nicht eingerichtet.','Admin PIN not configured.'):tr('PIN nicht korrekt.','Incorrect PIN.');
  }catch{if(adminDialog.open)adminStatus.textContent=tr('Prüfung nicht verfügbar.','Verification unavailable.');}
  finally{$('admin-submit').disabled=false;if(adminDialog.open)adminPin.focus();}
});
syncFullscreen();
$('premiere-launch').addEventListener('click',()=>startPremiere());
$('premiere-prev').addEventListener('click',()=>premiereStep(-1));
$('premiere-next').addEventListener('click',()=>premiereStep(1));
$('premiere-stop').addEventListener('click',stopPremiere);
for(const type of ['pointerdown','keydown','touchmove'])$('premiere-overlay').addEventListener(type,()=>{if(premiereActive)premiereLastInput=Date.now();});
$('premiere-pause').addEventListener('click',()=>{if(!premiereActive)return;const now=Date.now();if(premierePaused){premiereDeadline=now+premiereRemaining;premierePaused=false;}else{premiereRemaining=Math.max(0,premiereDeadline-now);premierePaused=true;}premiereRender();});
document.addEventListener('keydown',e=>{if(!premiereActive)return;if(e.key==='Escape'){e.preventDefault();stopPremiere();}else if(e.key==='ArrowRight'){e.preventDefault();premiereStep(1);}else if(e.key==='ArrowLeft'){e.preventDefault();premiereStep(-1);}});
let lastSmartSecond=-1;
setInterval(()=>{const now=Date.now();if(premiereTick(now))return;if(syncNightMode(now))return;clock();const second=Math.floor(now/1000);if(second!==lastSmartSecond){lastSmartSecond=second;syncMatchGreetings(now);if(second%60===0){syncHeaderDecor(now);syncPremiereLaunch(now);}}if(adminDialog.open||$('personal-demo-dialog')?.open||nuscoreDemoActive())return;if(view==='info'){tickInfo(now);refreshOccupancyHour(now);}else{if(!(pilot&&view==='courts'&&interactiveCourtMode())&&now-last>=idle)go('info');$('progress').style.setProperty('--progress','0%');}},250);
window.addEventListener('resize',scheduleScreenFit);
document.addEventListener('fullscreenchange',scheduleScreenFit);
if(document.fonts)document.fonts.ready.then(scheduleScreenFit);
function applyPersonalBrand(brand){
  if(!personalDemo)return;
  personalBrand=brand;
  const root=document.documentElement;
  root.dataset.personalDemo='true';
  root.style.setProperty('--club-color',brand.color);
  const title=$('ntc-brand-title');title.replaceChildren(document.createTextNode(brand.name+' '));
  const small=document.createElement('small');small.textContent='ClubDisplay';title.append(small);
  const logo=$('brand-logo');logo.src=brand.logo||'assets/clubdisplay.png';logo.alt=brand.logo?brand.name:'TENNILO ClubDisplay';
  document.title=brand.name+' · ClubDisplay Demo';
  leagueData=demoLeague();
  syncMatchGreetings();go('home');
}
function initPersonalDemo(){
  if(!personalDemo)return;
  const dialog=$('personal-demo-dialog'),form=$('personal-demo-form'),edit=$('personal-demo-edit');
  edit.hidden=false;
  const open=()=>{last=Date.now();if(personalBrand){$('personal-name').value=personalBrand.name;$('personal-color').value=personalBrand.color;}if(!dialog.open)dialog.showModal();};
  edit.addEventListener('click',open);
  $('personal-cancel').addEventListener('click',()=>dialog.close());
  form.addEventListener('submit',async e=>{
    e.preventDefault();
    const name=$('personal-name').value.trim(),color=$('personal-color').value,file=$('personal-logo').files[0],status=$('personal-status'),submit=$('personal-submit');
    if(!name||name.length>60||!/^#[0-9a-f]{6}$/i.test(color))return;
    status.textContent='';submit.disabled=true;
    try{
      let logo=$('personal-remove-logo').checked?'':personalBrand?.logo||'';
      if(file){
        if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>5*1024*1024)throw Error('Bitte PNG, JPG oder WebP bis 5 MB wählen.');
        const bitmap=await createImageBitmap(file);
        const scale=Math.min(1,512/Math.max(bitmap.width,bitmap.height)),canvas=document.createElement('canvas');
        canvas.width=Math.max(1,Math.round(bitmap.width*scale));canvas.height=Math.max(1,Math.round(bitmap.height*scale));
        canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close();
        logo=canvas.toDataURL('image/png');
      }
      applyPersonalBrand({name,color,logo});
      dialog.close();$('personal-logo').value='';$('personal-remove-logo').checked=false;
    }catch(err){status.textContent=err.message||'Logo konnte nicht gelesen werden.';}finally{submit.disabled=false;}
  });
  open();
}
if(!pilot)leagueData=demoLeague();syncMatchGreetings();render();initPersonalDemo();if(!syncNightMode(Date.now(),true))refreshDayData();else refreshEvents();if(pilot)setInterval(()=>{if(!nightActive)refreshOccupancy();},60000);setInterval(()=>{if(!nightActive)refreshMatchball();},60000);setInterval(()=>{if(!nightActive)refreshPartnerBoard();},60000);setInterval(()=>{if(!nightActive)refreshNews();},900000);setInterval(()=>{if(!nightActive)refreshWeather();},600000);setInterval(()=>{if(!nightActive&&weatherActive())refreshRadar();},300000);
