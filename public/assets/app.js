'use strict';
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
const $ = id => document.getElementById(id);
const pilot = location.hostname === 'clubdisplay.ntc93.de';
if(pilot){const logo=document.getElementById('brand-logo');logo.src='assets/ntc93-logo.png';logo.alt='Neuenhagener Tennisclub 93 e. V.';}
let lang='de', view='info', slide=0, last=Date.now(), started=Date.now(), court=null, message='';
const dwell=12000, idle=60000;
const infoPauseLimit=25000;
let infoPaused=false,infoElapsed=0,infoPauseUntil=0;
const dictionary={
de:{club:pilot?'Neuenhagener Tennisclub 93 e. V.':'TENNILO · Entwicklungsdemo',info:'Informationsmodus',touch:'Interaktiver Modus',demo:'PROTOTYP · BEISPIELDATEN',notice:'Keine echten Buchungen oder Vereinsdaten.',welcome:'Willkommen im Clubhaus',intro:'Alles Wichtige rund um deinen Tennisverein. Berühre den Bildschirm und entdecke die Funktionen.',start:'Touch-Menü öffnen',today:'Heute auf der Anlage',courts:'Plätze',calendar:'Termine',teams:'Mannschaften',news:'Vereinsinfos',home:'Start',back:'Infomodus',available:'Frei',busy:'Belegt',court:'Platz',booking:'Platzbuchung',select:'Platz auswählen',slot:'Beispielzeit: 15:00–16:00',confirm:'Buchung simulieren',success:'Simulation erfolgreich. Es wurde nichts tatsächlich gebucht oder gespeichert.',upcoming:'Nächste Termine',events:['Vereinstraining','Mannschaftsspieltag','Clubturnier'],eventTimes:['Heute · 17:00','Samstag · 10:00','Sonntag · 11:00'],matches:'Mannschaftsspiele',matchNames:['Herren 30','Damen','Jugend U14'],matchDetails:['Heimspiel · Samstag','Auswärts · Sonntag','Training · Mittwoch'],announcements:'Neues aus dem Club',newsItems:['Willkommen zur neuen Saison','Aktuelle Platzhinweise','Informationen zu Vereinsveranstaltungen'],newsText:'Beispiel für eine Vereinsmeldung. Die späteren Inhalte werden zentral verwaltet.',partner:'Partner & Sponsoren',partnerText:'Dieser Bereich ist für freigegebene Vereinswerbung vorgesehen.',reset:'Zurück zur Auswahl'},
en:{club:pilot?'Neuenhagener Tennisclub 93 e. V.':'TENNILO · Development demo',info:'Information mode',touch:'Interactive mode',demo:'PROTOTYPE · SAMPLE DATA',notice:'No real bookings or club data.',welcome:'Welcome to the clubhouse',intro:'Everything you need to know about your tennis club. Touch the screen to explore.',start:'Open touch menu',today:'Today at the club',courts:'Courts',calendar:'Events',teams:'Teams',news:'Club news',home:'Home',back:'Info mode',available:'Available',busy:'Occupied',court:'Court',booking:'Court booking',select:'Select a court',slot:'Example time: 15:00–16:00',confirm:'Simulate booking',success:'Simulation successful. Nothing was actually booked or stored.',upcoming:'Upcoming events',events:['Club training','Team match day','Club tournament'],eventTimes:['Today · 17:00','Saturday · 10:00','Sunday · 11:00'],matches:'Team matches',matchNames:['Men 30','Women','Junior U14'],matchDetails:['Home · Saturday','Away · Sunday','Training · Wednesday'],announcements:'Club updates',newsItems:['Welcome to the new season','Current court information','Club event information'],newsText:'Example club announcement. Future content will be managed centrally.',partner:'Partners & sponsors',partnerText:'Reserved for approved club advertising.',reset:'Back to selection'}
};
const t=()=>dictionary[lang];
const baseScreens=['welcome','weather','calendar','teams','news','partner'];
const screens=[...baseScreens];
let greetingMatches=new Map();
let leagueData=null, matchFilter='home', matchPage=0, fetching=false;
const sourceUrl='https://tvbb.liga.nu/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=36381';
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const tr=(de,en)=>lang==='de'?de:en;
function shortClub(name){return String(name??'').replace(/^Neuenhagener\s+(?:Tennisclub|TC)\s+93(?=\s|$)/iu,'NTC 93');}
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
function syncMatchGreetings(now=Date.now()){
  const active=pilot?activeMatchGreetings(leagueData,now):[];
  const previous=screens[slide];
  const before=greetingMatches.get(previous);
  greetingMatches=new Map(active.map(m=>[matchGreetingKey(m),m]));
  const next=[baseScreens[0],...greetingMatches.keys(),...baseScreens.slice(1)];
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
  const current=greetingMatches.get(screens[slide]);
  if(view==='info'&&before&&current&&JSON.stringify(before)!==JSON.stringify(current))render();
}
function matchGreetingView(key){
  const m=greetingMatches.get(key);
  if(!m)return '';
  const identity=teamAndLeague(m);
  const time=new Intl.DateTimeFormat(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(m.date));
  const courts=fixtureCourtNumbers(m.courts);
  const courtList=courts.length?'<div class="greeting-courts">'+courts.map(n=>'<div class="greeting-court"><svg viewBox="0 0 120 68" aria-hidden="true"><rect x="1" y="1" width="118" height="66" rx="8" fill="#96603e"/><g fill="none" stroke="#fff" stroke-width="1.4"><rect x="8" y="8" width="104" height="52"/><path d="M8 16h104M8 52h104M32 16v36M88 16v36M32 34h56M60 4v60"/></g><circle cx="60" cy="34" r="18" fill="#073538"/><text x="60" y="35" dominant-baseline="middle" text-anchor="middle" font-size="24" font-weight="800" fill="#fff">'+esc(n)+'</text></svg><strong>'+tr('Platz ','Court ')+esc(n)+'</strong></div>').join('')+'</div>':'<p class="greeting-unassigned">'+tr('Platzzuordnung noch nicht verfügbar','Court assignment not yet available')+'</p>';
  return '<div class="greeting-hero"><div class="greeting-kicker"><span>'+tr('HEIMSPIEL','HOME MATCH')+'</span><span>'+esc(time)+(lang==='de'?' Uhr':'')+'</span></div><div class="greeting-welcome">'+tr('Herzlich willkommen beim NTC 93!','Welcome to NTC 93!')+'</div><div class="greeting-category">'+esc(identity.team)+(identity.league?' · '+esc(identity.league):'')+'</div><div class="greeting-pair"><div class="greeting-home"><small>'+tr('Heim','Home')+'</small><strong>'+esc(shortClub(m.home))+'</strong></div><div class="greeting-versus">–</div><div class="greeting-away"><small>'+tr('Gast','Away')+'</small><strong>'+esc(shortClub(m.away))+'</strong></div></div><div class="greeting-assignment"><h2>'+tr('Eure Plätze','Your courts')+'</h2>'+courtList+'</div><p class="greeting-farewell">'+tr('Wir wünschen euch ein schönes und faires Match!','Have a great and fair match!')+'</p></div>';
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
  const title='<div class="match-heading"><h1>'+headings[filter]+'</h1>'+tabs+'</div>';
  if(!d)return title+'<div class="panel"><p>'+tr('nuLiga-Daten werden geladen …','Loading nuLiga data …')+'</p></div>';
  const source='<p class="match-source">'+tr('Quelle: nuLiga · TVBB','Source: nuLiga · TVBB')+(d.fetchedAt?' · '+tr('Stand ','Updated ')+esc(new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'})):'')+(!info&&!pilot?' · <a href="'+sourceUrl+'" target="_blank" rel="noopener noreferrer">'+tr('Original-Spielplan','Original schedule')+'</a>':'')+'</p>';
  if(d.status==='unavailable')return title+'<div class="panel"><p>'+tr('nuLiga momentan nicht verfügbar.','nuLiga currently unavailable.')+'</p></div>'+source;
  const warning=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Daten – Aktualisierung fehlgeschlagen.','Cached data – refresh failed.')+'</p>':'';
  const dateParts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
  const part=k=>dateParts.find(p=>p.type===k)?.value;
  const day=part('year')+'-'+part('month')+'-'+part('day');
  const future=m=>m.date.slice(0,10)>=day;
  let rows=d.matches.filter(m=>filter==='results'?!!m.score:future(m)&&!m.score&&(filter==='all'||(filter==='home'?m.isHome:!m.isHome)));
  if(filter==='results')rows=rows.slice().reverse();
  if(!rows.length)return title+warning+'<div class="panel"><p>'+tr('Keine passenden Begegnungen im von nuLiga gelieferten Zeitraum.','No matching fixtures in the period supplied by nuLiga.')+'</p>'+(d.range?'<p>'+esc(d.range.from)+' – '+esc(d.range.to)+'</p>':'')+'</div>'+source;
  const cards=rows.map(m=>{
    const when=new Date(m.date),locale=lang==='de'?'de-DE':'en-GB',zone='Europe/Berlin';
    const weekday=when.toLocaleDateString(locale,{timeZone:zone,weekday:'long'});
    const dayLabel=when.toLocaleDateString(locale,{timeZone:zone,day:'2-digit',month:'2-digit'});
    const timeLabel=when.toLocaleTimeString(locale,{timeZone:zone,hour:'2-digit',minute:'2-digit'});
    const identity=teamAndLeague(m);
    const venueLabel=typeof m.venue==='string'?m.venue.trim():'';
    const courtNumbers=fixtureCourtNumbers(m.courts);
    const courtStrip='<div class="fixture-court-strip" aria-label="'+tr('Plätze','Courts')+'">'+(courtNumbers.length?courtNumbers.map(n=>'<span class="fixture-court-icon" aria-label="'+tr('Platz ','Court ')+esc(n)+'"><svg viewBox="0 0 36 22" aria-hidden="true"><rect x="1" y="1" width="34" height="20" rx="3" fill="currentColor"/><path d="M5 4H31V18H5Z M18 4V18 M5 7H31 M5 15H31 M11 7V15 M25 7V15" fill="none" stroke="var(--court-icon-ink)" stroke-width=".8"/><rect x="10" y="3" width="16" height="16" rx="2" fill="currentColor"/><text x="18" y="15" text-anchor="middle" font-size="'+(n.length>2?'8':'13')+'" font-weight="800" fill="var(--court-icon-ink)">'+esc(n)+'</text></svg></span>').join(''):m.isHome?'<span class="fixture-court-unknown">'+tr('Plätze','Courts')+'<strong>–</strong></span>':'<span class="fixture-court-unknown" aria-hidden="true">–</span>')+'</div>';
    const meta='<span class="fixture-side">'+(m.isHome?tr('Heimspiel','Home match'):tr('Auswärtsspiel','Away match'))+'</span>'+(venueLabel?'<span>'+tr('Platzanlage','Venue')+': <strong>'+esc(venueLabel)+'</strong></span>':'');
    return '<article class="fixture-card"><time class="fixture-date-block" datetime="'+esc(m.date)+'"><span class="fixture-weekday">'+esc(weekday)+'</span><strong class="fixture-day">'+esc(dayLabel)+'</strong><span class="fixture-time">'+esc(timeLabel)+(lang==='de'?' Uhr':'')+'</span></time>'+courtStrip+'<div class="fixture-content"><div class="fixture-top"><span class="fixture-identity">'+esc(identity.team)+'</span>'+(identity.league?'<span class="fixture-league">'+esc(identity.league)+'</span>':'')+'</div><div class="fixture-pair"><strong class="fixture-team '+(m.isHome?'fixture-own':'')+'">'+esc(shortClub(m.home))+'</strong><span class="fixture-versus">–</span><strong class="fixture-team '+(!m.isHome?'fixture-own':'')+'">'+esc(shortClub(m.away))+'</strong></div><div class="fixture-details">'+meta+'</div></div><div class="fixture-result"><span>'+tr('Ergebnis','Result')+'</span><strong class="'+(m.score?'fixture-result-value':'fixture-result-pending')+'">'+(m.score?esc(m.score):'– : –')+'</strong></div></article>';
  }).join('');
  const pagination='<div class="actions match-pages"><button data-action="match-prev" aria-label="'+tr('Vorherige Seite','Previous page')+'">‹</button><span class="match-page-number"></span><button data-action="match-next" aria-label="'+tr('Nächste Seite','Next page')+'">›</button></div>';
  return title+warning+'<div class="fixture-list">'+cards+'</div>'+pagination+source;
}
async function refreshLeague(){
  if(fetching)return;fetching=true;
  try{
    const response=await fetch('nuliga.php',{cache:'no-store',signal:AbortSignal.timeout(25000)});
    if(!response.ok)throw Error('HTTP');
    const data=await response.json();
    if(!Array.isArray(data.matches)||!['ok','stale','unavailable'].includes(data.status))throw Error('invalid feed');
    leagueData=data;
  }catch{leagueData=leagueData&&leagueData.status!=='unavailable'?{...leagueData,status:'stale'}:{status:'unavailable',matches:[]};}
  finally{fetching=false;syncMatchGreetings();if(view==='teams'||(view==='info'&&screens[slide]==='teams'))render();}
}
setInterval(refreshLeague,300000);
let eventData=null,eventPage=0,eventFetching=false;
function eventView(){
  const info=view==='info',d=eventData,title='<div class="match-heading"><h1>'+t().upcoming+'</h1></div>';
  const source='<p class="match-source">'+tr('Quelle: NTC-Veranstaltungskalender','Source: NTC event calendar')+(d?.fetchedAt?' · '+tr('Stand ','Updated ')+esc(new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'})):'')+(!info&&!pilot?' · <a href="https://ntc93.com/events/" target="_blank" rel="noopener noreferrer">'+tr('Original-Kalender','Original calendar')+'</a>':'')+'</p>';
  if(!d)return title+'<div class="panel"><p>'+tr('Termine werden geladen …','Loading events …')+'</p></div>';
  if(d.status==='unavailable')return title+'<div class="panel"><p>'+tr('Vereinskalender momentan nicht verfügbar.','Club calendar currently unavailable.')+'</p></div>'+source;
  const warning=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Termine – Aktualisierung fehlgeschlagen.','Cached events – refresh failed.')+'</p>':'';
  const locale=lang==='de'?'de-DE':'en-GB', fmt=(s,options)=>new Date(s).toLocaleString(locale,{timeZone:'Europe/Berlin',...options});
  const parts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const val=k=>parts.find(p=>p.type===k)?.value;const today=val('year')+'-'+val('month')+'-'+val('day');
  const rows=d.events.filter(e=>e.end.slice(0,10)>=today);
  if(!rows.length)return title+warning+'<div class="panel"><p>'+tr('Keine kommenden Veranstaltungen im abgerufenen Zeitraum.','No upcoming events in the retrieved period.')+'</p>'+(d.to?'<p>'+tr('Zeitraum bis ','Period until ')+esc(d.to)+'</p>':'')+'</div>'+source;
  const cards=rows.map(e=>{
    const dayKey=s=>fmt(s,{year:'numeric',month:'2-digit',day:'2-digit'});
    const multi=dayKey(e.start)!==dayKey(e.end);
    const weekday=fmt(e.start,{weekday:'long'});
    const date=fmt(e.start,{day:'2-digit',month:'2-digit'});
    const time=e.allDay?tr('Ganztägig','All day'):fmt(e.start,{hour:'2-digit',minute:'2-digit'})+(lang==='de'?' Uhr':'');
    const endTime=fmt(e.end,{hour:'2-digit',minute:'2-digit'});
    const dateRange=multi?fmt(e.start,{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric'})+' – '+fmt(e.end,{weekday:'long',day:'2-digit',month:'2-digit',year:'numeric'}):'';
    const link=!info&&!pilot&&typeof e.url==='string'&&/^https:\/\/(?:www\.)?ntc93\.com\//i.test(e.url)?'<a class="event-link" href="'+esc(e.url)+'" target="_blank" rel="noopener noreferrer">'+tr('Details','Details')+'</a>':'';
    const period=e.allDay?(multi?fmt(e.end,{day:'2-digit',month:'2-digit',year:'numeric'}):tr('Ganztägig','All day')):multi?fmt(e.end,{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})+(lang==='de'?' Uhr':''):fmt(e.start,{hour:'2-digit',minute:'2-digit'})+' – '+endTime+(lang==='de'?' Uhr':'');
    const periodLabel=multi?tr('Bis','Until'):tr('Zeitraum','Time');
    const cell=(label,value)=>'<div class="event-meta-cell"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong></div>';
    return '<article class="fixture-card event-card'+(link?' event-card-linked':'')+'"><time class="fixture-date-block" datetime="'+esc(e.start)+'"><span class="fixture-weekday">'+esc(weekday)+'</span><strong class="fixture-day">'+esc(date)+'</strong><span class="fixture-time">'+esc(time)+'</span></time><div class="fixture-content event-content"><strong class="event-title">'+esc(e.title)+'</strong>'+(dateRange?'<div class="fixture-details event-details">'+esc(dateRange)+'</div>':'')+'</div><div class="event-meta">'+cell(periodLabel,period)+cell(tr('Ort','Venue'),e.venue||'–')+'</div>'+link+'</article>';
  }).join('');
  const paging='<div class="actions match-pages"><button data-action="event-prev" aria-label="'+tr('Vorherige Seite','Previous page')+'">‹</button><span class="match-page-number"></span><button data-action="event-next" aria-label="'+tr('Nächste Seite','Next page')+'">›</button></div>';
  return title+warning+'<div class="fixture-list">'+cards+'</div>'+paging+source;
}
async function refreshEvents(){
  if(eventFetching)return;eventFetching=true;
  try{const response=await fetch('events.php',{cache:'no-store',signal:AbortSignal.timeout(45000)});if(!response.ok)throw Error('HTTP');const data=await response.json();if(!Array.isArray(data.events)||!['ok','stale','unavailable'].includes(data.status))throw Error('feed');eventData=data;}
  catch{eventData=eventData&&eventData.status!=='unavailable'?{...eventData,status:'stale'}:{status:'unavailable',events:[]};}
  finally{eventFetching=false;if(view==='calendar'||(view==='info'&&screens[slide]==='calendar'))render();}
}
setInterval(refreshEvents,300000);

function button(label,action,cls=''){return '<button type="button" class="'+cls+'" data-action="'+action+'">'+label+'</button>'}
function line(a,b){return '<div class="line"><strong>'+a+'</strong><span class="muted">'+b+'</span></div>'}
function panel(title,names,details){return '<div class="panel"><h2>'+title+'</h2>'+names.map((n,i)=>line(n,details[i])).join('')+'</div>'}
function hero(title,description,extra=''){return '<div class="hero"><div><h1>'+title+'</h1><p>'+description+'</p><div class="actions">'+button(t().start,'home','primary large')+'</div></div>'+extra+'</div>'}
let terminalStatus=null,terminalChecking=false,courtMode='outdoor';
async function checkTerminal(){
  if(!pilot||terminalChecking)return;
  terminalChecking=true;
  try{
    const r=await fetch('terminal.php?status=1',{cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(8000)});
    const d=await r.json();terminalStatus=r.ok&&d.ready?'ready':d.configured?'unavailable':'unconfigured';
  }catch{terminalStatus='unavailable';}
  finally{terminalChecking=false;if(view==='courts'&&courtMode==='outdoor')render();}
}
function ebusyView(){
  const head='<div class="booking-head"><h1>'+tr('Plätze','Courts')+'</h1>'+button(tr('Zurück','Back'),'home','primary')+'<div class="court-switch" role="group" aria-label="'+tr('Platzbereich','Court area')+'"><button data-action="court-mode:outdoor" aria-pressed="'+(courtMode==='outdoor')+'">'+tr('Außenplätze · Buchen','Outdoor courts · Book')+'</button><button data-action="court-mode:indoor" aria-pressed="'+(courtMode==='indoor')+'">'+tr('Hallenplätze · Belegung','Indoor courts · Availability')+'</button></div></div>';
  if(courtMode==='indoor')return head+'<div class="hall-readonly"><div class="hall-frame-wrap"><iframe class="booking-frame hall-frame" title="'+tr('Hallenbelegungsplan von eBuSy (nur Ansicht)','eBuSy indoor court schedule (view only)')+'" src="https://ntc93v8.ebusy.de/embed/court-module/3076" sandbox="allow-scripts allow-same-origin" credentialless inert tabindex="-1" referrerpolicy="no-referrer"></iframe><div class="hall-input-shield" aria-hidden="true"></div></div><div class="hall-toolbar"><span>'+tr('Hallenbelegung · Nur Ansicht','Indoor court occupancy · View only')+'</span><button data-action="hall-refresh">'+tr('Belegungsplan aktualisieren','Refresh schedule')+'</button></div></div>';
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
function weatherActive(){return view==='weather'||(view==='info'&&screens[slide]==='weather');}
function headerWeatherSymbol(kind){
  const shapes={
    sunrise:'<path d="M2 18h20M4 21h16M6 18a6 6 0 0 1 12 0M12 2v6m-3-3 3-3 3 3M3 10l2 2m16-2-2 2"/>',
    sunset:'<path d="M2 18h20M4 21h16M6 18a6 6 0 0 1 12 0M12 2v6m-3-3 3 3 3-3M3 10l2 2m16-2-2 2"/>',
    wind:'<path d="M2 8h13a3 3 0 1 0-3-3M2 12h18a3 3 0 1 1-3 3M2 16h8a3 3 0 1 1-3 3"/>',
    rain:'<path d="M12 2c-2.5 4-7 8.8-7 13a7 7 0 1 0 14 0c0-4.2-4.5-9-7-13Z"/>'
  };
  return '<svg class="wx-head-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(shapes[kind]||'')+'</svg>';
}
function renderWeatherHeader(){
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
  finally{newsBusy=false;if((view==='news'&&!openedNews)||(view==='info'&&screens[slide]==='news'))render();}
}
function newsDate(p){return new Date(p.date).toLocaleDateString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',day:'2-digit',month:'2-digit',year:'numeric'});}
function newsArticleView(p){
  const source='<span class="news-origin">'+tr('Originalbeitrag: ','Original article: ')+'ntc93.com</span>';
  const image=p.image&&!(p.article||'').includes(esc(p.image))?'<figure class="news-cover"><img src="'+esc(p.image)+'" alt="'+esc(p.imageAlt||'')+'" referrerpolicy="no-referrer"></figure>':'';
  const warning=!p.complete?'<p class="news-article-warning">'+tr('Der vollständige Beitrag ist momentan nicht über die Schnittstelle verfügbar. Angezeigt wird der vorhandene Inhalt.','The complete article is currently unavailable through the feed. Available content is shown.')+'</p>':'';
  return '<div class="news-reader-head"><div><time class="news-date" datetime="'+esc(p.date)+'">'+esc(newsDate(p))+'</time>'+source+'<h1>'+esc(p.title)+'</h1></div>'+button(tr('Zurück zu den Meldungen','Back to news'),'news-back','primary')+'</div><div id="news-article-scroll" class="panel news-article-scroll" tabindex="0" aria-label="'+tr('Artikelinhalt','Article content')+'">'+warning+image+(p.article||'<p>'+esc(p.excerpt)+'</p>')+'</div><div class="news-reader-foot"><span>'+tr('Quelle: ntc93.com · Originaltext','Source: ntc93.com · Original text')+'</span><div><button data-action="news-up" aria-label="'+tr('Nach oben','Scroll up')+'">↑</button><button data-action="news-down" aria-label="'+tr('Weiterlesen','Read further')+'">↓</button></div></div>';
}
function newsView(){
  if(pilot&&view==='news'&&openedNews)return newsArticleView(openedNews);
  const title='<div class="match-heading"><h1>'+t().announcements+'</h1><span class="news-origin">'+tr('Aktuelle Meldungen von ','Latest news from ')+'ntc93.com</span></div>';
  if(!pilot)return '<div class="match-heading"><h1>'+t().announcements+'</h1></div><div class="grid">'+t().newsItems.map(n=>'<article class="panel"><h2>'+esc(n)+'</h2><p>'+esc(t().newsText)+'</p></article>').join('')+'</div>';
  const d=newsData;
  const stamp=d?.fetchedAt?new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'}):'';
  const source='<p class="match-source">'+tr('Quelle: ntc93.com','Source: ntc93.com')+(stamp?' · '+tr('Stand ','Updated ')+esc(stamp):'')+(lang==='en'?' · Original German articles':'')+'</p>';
  if(!d||d.status==='unavailable')return title+'<div class="panel">'+tr(d?'Vereinsmeldungen momentan nicht verfügbar.':'Vereinsmeldungen werden geladen …',d?'Club news currently unavailable.':'Loading club news …')+'</div>'+source;
  if(!d.posts.length)return title+'<div class="panel">'+tr('Keine veröffentlichten Meldungen gefunden.','No published news found.')+'</div>'+source;
  const warn=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Meldungen – Aktualisierung fehlgeschlagen.','Cached news – refresh failed.')+'</p>':'';
  const cards=d.posts.slice(0,3).map((p,i)=>'<button type="button" class="panel news-card" data-action="news-open:'+i+'"><time class="news-date" datetime="'+esc(p.date)+'">'+esc(newsDate(p))+'</time><strong class="news-card-title">'+esc(p.title)+'</strong><span class="news-excerpt">'+esc(p.excerpt)+'</span><span class="news-read-more">'+tr('Artikel lesen','Read article')+' →</span></button>').join('');
  return title+warn+'<div class="news-grid">'+cards+'</div>'+source;
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
    "url": "https://ntc93.com/wp-content/uploads/2023/06/logob1.gif"
  },
  {
    "name": "Leben retten",
    "url": "https://ntc93.com/wp-content/uploads/2023/10/lebenretten-300x265.png"
  }
];
function sponsorView(){
  const cards=ntcSponsors.map(s=>'<figure class="sponsor-logo'+(s.name==='Allianz Maxelon'?' sponsor-logo-allianz':'')+'"><img src="'+esc(s.url)+'" alt="'+esc(s.name)+'" decoding="async" referrerpolicy="no-referrer"></figure>').join('');
  return '<div class="match-heading sponsor-heading"><h1>'+tr('Partner & Sponsoren','Partners & sponsors')+'</h1><span class="news-origin">'+tr('Vielen Dank für die Unterstützung!','Thank you for your support!')+'</span></div><div class="sponsor-grid">'+cards+'</div><p class="match-source sponsor-source">'+tr('Sponsoren und Partner des NTC 93 · Quelle: ntc93.com','NTC 93 sponsors and partners · Source: ntc93.com')+'</p>';
}
// Court overview: only confirmed eBuSy data may mark a court free or occupied.
const occupancyCourts={outdoor:[1,2,3,4,5,6,7],indoor:[1,2]};
let occupancyData=null,occupancyHourKey='';
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
function occupancyBookingText(area,court,hour){
  const record=occupancyRecord(area,court,hour);
  const status=occupancySlot(area,court,hour);
  const fallback={unknown:tr('Noch keine Daten','No data yet'),free:tr('Frei','Free'),occupied:tr('Belegt · Name nicht verfügbar','Occupied · name unavailable'),partial:tr('Teilbelegt','Partly occupied'),blocked:tr('Gesperrt','Closed')};
  // Each approved label may describe a player pair, team, training group or closure.
  const entries=(status==='occupied'||status==='partial'||status==='blocked')&&Array.isArray(record?.bookings)?record.bookings.filter(r=>typeof r?.displayLabel==='string'&&r.displayLabel.trim()):[];
  const body=entries.length?entries.map(r=>{
    const label=r.displayLabel.trim();
    const timed=typeof r.start==='string'&&typeof r.end==='string'&&Number.isFinite(Date.parse(r.start))&&Number.isFinite(Date.parse(r.end))&&Date.parse(r.start)<Date.parse(r.end);
    const extra=timed&&(entries.length>1||status==='partial')?'<span class="occupancy-booking-time">'+esc(occupancyTime(r.start)+'–'+occupancyTime(r.end))+'</span>':'';
    return '<div class="occupancy-booking-entry">'+extra+'<strong>'+esc(label)+'</strong></div>';
  }).join(''):'<span class="occupancy-booking-placeholder">'+fallback[status]+'</span>';
  return '<div class="occupancy-cell occupancy-'+status+'">'+body+'</div>';
}
function occupancyView(){
  const hours=occupancyHours();occupancyHourKey=hours[0].start;
  const ready=hours.some(hour=>Object.keys(occupancyCourts).some(area=>occupancyCourts[area].some(court=>occupancySlot(area,court,hour)!=='unknown')));
  const header='<div class="occupancy-timeline-head"><span>'+tr('Platz','Court')+'</span>'+hours.map((hour,i)=>'<div class="occupancy-hour-head'+(i===0?' occupancy-hour-current':'')+'"><strong>'+occupancyTime(hour.start)+' – '+occupancyTime(hour.end)+'</strong><small>'+(i===0?tr('Aktuelle Stunde','Current hour'):tr('Danach','Upcoming'))+'</small></div>').join('')+'</div>';
  const group=(area,title)=>'<section class="occupancy-roster-group occupancy-roster-'+area+'"><div class="occupancy-roster-caption">'+title+'</div>'+occupancyCourts[area].map(court=>'<article class="occupancy-roster-row occupancy-court-'+area+'" aria-label="'+title+' · '+tr('Platz ','Court ')+court+'"><div class="occupancy-court-ident">'+occupancyCourtGraphic(court)+'<strong>'+tr('Platz ','Court ')+court+'</strong></div>'+hours.map(hour=>occupancyBookingText(area,court,hour)).join('')+'</article>').join('')+'</section>';
  return '<div class="match-heading occupancy-heading"><h1>'+tr('Wer spielt wo?','Who is playing where?')+'</h1><span class="occupancy-status">'+(ready?tr('Buchungsübersicht · eBuSy','Booking overview · eBuSy'):tr('eBuSy-Anbindung noch ausstehend','Awaiting eBuSy integration'))+'</span></div><div class="occupancy-roster">'+header+group('outdoor',tr('Außenplätze','Outdoor courts'))+group('indoor',tr('Hallenplätze','Indoor courts'))+'</div><div class="occupancy-footer"><span class="occupancy-status">'+tr('Anzeige der gebuchten Spieler, Mannschaften und Trainingsgruppen.','Shows booked players, teams and training groups.')+'</span>'+button(tr('Buchungsplan öffnen','Open booking plan'),'courts','primary')+'</div>';
}
function refreshOccupancyHour(now){
  if(pilot&&view==='info'&&screens[slide]==='welcome'&&occupancyHours(now)[0].start!==occupancyHourKey)render();
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
function render(){const l=t();document.documentElement.lang=lang;$('club').textContent=l.club;$('mode').textContent=view==='info'?l.info:l.touch;$('demo').textContent=tr('PILOT · TEILWEISE BEISPIELDATEN','PILOT · PARTIAL SAMPLE DATA');$('notice').textContent=pilot?tr('Vereinsmeldungen von ntc93.com · Buchung über eBuSy.','Club news from ntc93.com · Booking via eBuSy.'):tr('Platzbuchung und Meldungen sind noch Beispiele.','Court booking and news are still examples.');$('language').textContent=lang==='de'?'EN':'DE';syncFullscreen();renderWeatherHeader();infoControls();
$('nav').innerHTML=[['home',l.home],['courts',l.courts],['calendar',l.calendar],['teams',l.teams],['weather',tr('Wetter','Weather')],['news',l.news],['info',l.back]].map(([k,v])=>'<button data-action="'+k+'" aria-pressed="'+(view===k)+'">'+v+'</button>').join('');
const active=view==='info'?screens[slide]:view;document.body.classList.toggle('match-screen',active==='teams');document.body.classList.toggle('weather-screen',active==='weather');document.body.classList.toggle('court-screen',active==='courts');document.body.classList.toggle('event-screen',active==='calendar');document.body.classList.toggle('sponsor-screen',pilot&&active==='partner');document.body.classList.toggle('occupancy-screen',pilot&&active==='welcome');document.body.classList.toggle('greeting-screen',pilot&&greetingMatches.has(active));let html='';
if(greetingMatches.has(active))html=matchGreetingView(active);else switch(active){
case 'welcome':html=pilot?occupancyView():hero(l.welcome,l.intro,panel(l.today,[l.court+' 1',l.court+' 2',l.court+' 3'],[l.available,l.busy,l.available]));break;
case 'home':{
  const tiles=[
    ['courts',l.booking,pilot?tr('Außenplätze buchen und die Belegung der Hallenplätze ansehen.','Book outdoor courts and view indoor court availability.'):tr('Platzbelegung ansehen und eine Buchung ausprobieren.','View court availability and try a simulated booking.')],
    ['calendar',l.upcoming,tr('Veranstaltungen, Vereinsfeiern und weitere Clubtermine entdecken.','Explore upcoming events, club celebrations and other dates.')],
    ['teams',l.matches,tr('Heim- und Auswärtsspiele, Spielzeiten und Ergebnisse ansehen.','View home and away matches, match times and results.')],
    ['weather',tr('Wetter','Weather'),tr('Aktuelles Wetter, Vorhersage und Niederschlagsradar für Neuenhagen.','Current weather, forecasts and precipitation radar for Neuenhagen.')],
    ['news',l.announcements,tr('Vereinsmeldungen, Hinweise und Informationen aus dem Club.','Read club announcements, notices and information.')]
  ];
  html='<div class="home-welcome"><h1>'+esc(l.welcome)+'</h1><p>'+esc(l.intro)+'</p></div><div class="home-tiles">'+tiles.map(([key,title,description])=>'<button type="button" class="tile home-tile" data-action="'+key+'"><span class="home-tile-top"><strong>'+esc(title)+'</strong><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span><span class="home-tile-description">'+esc(description)+'</span></button>').join('')+'</div>';
  break;
}
case 'courts':if(pilot){html=ebusyView();break;}html='<h1>'+l.booking+'</h1><p>'+l.select+'</p>'+courtList();if(court!==null)html+='<div class="panel" style="margin-top:1rem"><h2>'+l.court+' '+court+'</h2><p>'+l.slot+'</p><div class="actions">'+button(l.confirm,'confirm','primary')+button(l.reset,'reset')+'</div><p role="status" class="message">'+message+'</p></div>';break;
case 'calendar':html=eventView();break;
case 'teams':html=matchView();break;
case 'weather':html=weatherView();if(!radarBusy)refreshRadar();break;
case 'news':html=newsView();break;
case 'partner':html=pilot?sponsorView():hero(l.partner,l.partnerText);break;
}
$('language').disabled=pilot&&view==='courts'&&courtMode==='outdoor';const embeddedBooking=pilot&&active==='courts',readingNews=pilot&&active==='news'&&view==='news'&&!!openedNews;document.body.classList.toggle('news-reading',readingNews);$('screen').innerHTML=embeddedBooking||readingNews?html:'<div class="fit-content">'+html+'</div>';if(!embeddedBooking&&!readingNews)fitActiveScreen();if(active==='weather')inspectRadarImage();clock();}

// Allocate space using rendered content, not viewport-size guesses. Recompute on resize and data changes.
function fitActiveScreen(){
  const screen=$('screen'), content=screen.querySelector('.fit-content');
  if(!content)return;
  content.style.transform='';content.style.width='100%';
  screen.dataset.weatherLayout='';
  const available=screen.clientHeight;
  if(available<=0)return;
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
        map.style.width=Math.max(80,Math.min(map.clientWidth,available-chrome-overhead-8))+'px';
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

function clock(){const now=new Date();const locale=lang==='de'?'de-DE':'en-GB';$('clock').textContent=new Intl.DateTimeFormat(locale,{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Berlin'}).format(now);$('date').textContent=new Intl.DateTimeFormat(locale,{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Berlin'}).format(now);}
function go(next){openedNews=null;if(next==='weather'&&view!=='weather')weatherPage='all';if(pilot&&next==='courts'&&view!=='courts'){courtMode='outdoor';terminalStatus=null;checkTerminal();}if(next==='calendar')eventPage=0;if(next==='teams'){matchFilter='home';matchPage=0;}view=next;court=null;message='';last=started=Date.now();if(next==='info'){slide=0;infoPaused=false;infoElapsed=0;infoPauseUntil=0;}render();}
document.addEventListener('click',e=>{infoInput();const b=e.target.closest('[data-action]');if(!b){if(e.target.closest('a'))return;if(view==='info'&&e.target.closest('#screen'))go('home');return;}const a=b.dataset.action;if(a==='info-prev'){stepInfo(-1);}else if(a==='info-next'){stepInfo(1);}else if(a==='info-toggle'){toggleInfoPause();}else if(a.startsWith('news-open:')&&pilot&&(view==='news'||(view==='info'&&screens[slide]==='news'))){const index=Number(a.split(':')[1]);const p=newsData?.posts?.[index];if(p){openedNews=p;view='news';render();}}else if(a==='news-back'){openedNews=null;go('news');}else if(a==='news-up'||a==='news-down'){const reader=$('news-article-scroll');if(reader)reader.scrollBy({top:(a==='news-up'?-1:1)*reader.clientHeight*.85,behavior:'smooth'});}else if(a.startsWith('weather-page:')&&weatherActive()){const next=a.split(':')[1];if(['all','now','hours','days','radar'].includes(next)){view='weather';weatherPage=next;render();}}else if(a.startsWith('court-mode:')&&pilot&&view==='courts'){const next=a.split(':')[1];if(['outdoor','indoor'].includes(next)&&next!==courtMode){courtMode=next;if(next==='outdoor'){terminalStatus=null;checkTerminal();}render();}}else if(a==='hall-refresh'&&pilot&&view==='courts'&&courtMode==='indoor'){render();}else if(a==='terminal-retry'&&pilot&&view==='courts'){checkTerminal();}else if(a==='event-prev'){eventPage=Math.max(0,eventPage-1);render();}else if(a==='event-next'){eventPage++;render();}else if(a.startsWith('match:')){matchFilter=a.split(':')[1];matchPage=0;render();}else if(a==='match-prev'){matchPage=Math.max(0,matchPage-1);render();}else if(a==='match-next'){matchPage++;render();}else if(a.startsWith('court:')){court=Number(a.split(':')[1]);message='';render();}else if(a==='confirm'&&court!==null){message=t().success;render();}else if(a==='reset'){court=null;message='';render();}else if(['info','home','courts','calendar','teams','news','weather'].includes(a))go(a);});
for(const event of ['pointerdown','keydown','wheel','touchmove'])document.addEventListener(event,infoInput);
$('language').addEventListener('click',()=>{if(pilot&&view==='courts'&&courtMode==='outdoor')return;lang=lang==='de'?'en':'de';message='';render();});
const adminDialog=$('admin-dialog'), adminForm=$('admin-form'), adminPin=$('admin-pin'), adminStatus=$('admin-status');
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
setInterval(()=>{const now=Date.now();clock();syncMatchGreetings(now);if(adminDialog.open)return;if(view==='info'){tickInfo(now);refreshOccupancyHour(now);}else{if(!(pilot&&view==='courts'&&courtMode==='outdoor')&&now-last>=idle)go('info');$('progress').style.setProperty('--progress','0%');}},250);
window.addEventListener('resize',scheduleScreenFit);
document.addEventListener('fullscreenchange',scheduleScreenFit);
if(document.fonts)document.fonts.ready.then(scheduleScreenFit);
render();refreshLeague();refreshEvents();refreshWeather();refreshNews();setInterval(refreshNews,900000);setInterval(refreshWeather,600000);setInterval(()=>{if(weatherActive())refreshRadar();},300000);
