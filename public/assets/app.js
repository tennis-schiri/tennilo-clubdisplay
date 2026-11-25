'use strict';
// TENNILO ClubDisplay | Copyright © 2026 TENNILO | Alle Rechte vorbehalten.
const $ = id => document.getElementById(id);
const pilot = location.hostname === 'clubdisplay.ntc93.de';
if(pilot){const logo=document.getElementById('brand-logo');logo.src='assets/ntc93-logo.png';logo.alt='Neuenhagener Tennisclub 93 e. V.';}
let lang='de', view='info', slide=0, last=Date.now(), started=Date.now(), court=null, message='';
const dwell=12000, idle=60000;
const dictionary={
de:{club:pilot?'Neuenhagener Tennisclub 93 e. V.':'TENNILO · Entwicklungsdemo',info:'Informationsmodus',touch:'Interaktiver Modus',demo:'PROTOTYP · BEISPIELDATEN',notice:'Keine echten Buchungen oder Vereinsdaten.',welcome:'Willkommen im Clubhaus',intro:'Alles Wichtige rund um deinen Tennisverein. Berühre den Bildschirm und entdecke die Funktionen.',start:'Touch-Menü öffnen',today:'Heute auf der Anlage',courts:'Plätze',calendar:'Termine',teams:'Mannschaften',news:'Vereinsinfos',home:'Start',back:'Infomodus',available:'Frei',busy:'Belegt',court:'Platz',booking:'Platzbuchung',select:'Platz auswählen',slot:'Beispielzeit: 15:00–16:00',confirm:'Buchung simulieren',success:'Simulation erfolgreich. Es wurde nichts tatsächlich gebucht oder gespeichert.',upcoming:'Nächste Termine',events:['Vereinstraining','Mannschaftsspieltag','Clubturnier'],eventTimes:['Heute · 17:00','Samstag · 10:00','Sonntag · 11:00'],matches:'Mannschaftsspiele',matchNames:['Herren 30','Damen','Jugend U14'],matchDetails:['Heimspiel · Samstag','Auswärts · Sonntag','Training · Mittwoch'],announcements:'Neues aus dem Club',newsItems:['Willkommen zur neuen Saison','Aktuelle Platzhinweise','Informationen zu Vereinsveranstaltungen'],newsText:'Beispiel für eine Vereinsmeldung. Die späteren Inhalte werden zentral verwaltet.',partner:'Partner & Sponsoren',partnerText:'Dieser Bereich ist für freigegebene Vereinswerbung vorgesehen.',reset:'Zurück zur Auswahl'},
en:{club:pilot?'Neuenhagener Tennisclub 93 e. V.':'TENNILO · Development demo',info:'Information mode',touch:'Interactive mode',demo:'PROTOTYPE · SAMPLE DATA',notice:'No real bookings or club data.',welcome:'Welcome to the clubhouse',intro:'Everything you need to know about your tennis club. Touch the screen to explore.',start:'Open touch menu',today:'Today at the club',courts:'Courts',calendar:'Events',teams:'Teams',news:'Club news',home:'Home',back:'Info mode',available:'Available',busy:'Occupied',court:'Court',booking:'Court booking',select:'Select a court',slot:'Example time: 15:00–16:00',confirm:'Simulate booking',success:'Simulation successful. Nothing was actually booked or stored.',upcoming:'Upcoming events',events:['Club training','Team match day','Club tournament'],eventTimes:['Today · 17:00','Saturday · 10:00','Sunday · 11:00'],matches:'Team matches',matchNames:['Men 30','Women','Junior U14'],matchDetails:['Home · Saturday','Away · Sunday','Training · Wednesday'],announcements:'Club updates',newsItems:['Welcome to the new season','Current court information','Club event information'],newsText:'Example club announcement. Future content will be managed centrally.',partner:'Partners & sponsors',partnerText:'Reserved for approved club advertising.',reset:'Back to selection'}
};
const t=()=>dictionary[lang];
const screens=['welcome','calendar','teams','news','partner'];
let leagueData=null, matchFilter='home', matchPage=0, fetching=false;
const sourceUrl='https://tvbb.liga.nu/cgi-bin/WebObjects/nuLigaTENDE.woa/wa/clubMeetings?club=36381';
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const tr=(de,en)=>lang==='de'?de:en;
function shortClub(name){return String(name??'').replace(/^Neuenhagener\s+(?:Tennisclub|TC)\s+93(?=\s|$)/iu,'NTC 93');}
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
function matchView(){
  const d=leagueData, info=view==='info', filter=info?'home':matchFilter;
  const headings={home:tr('Nächste Heimspiele','Upcoming home matches'),away:tr('Nächste Auswärtsspiele','Upcoming away matches'),all:tr('Alle kommenden Spiele','All upcoming matches'),results:tr('Letzte Ergebnisse','Recent results')};
  const tabs=info?'':'<div class="tabs">'+Object.keys(headings).map(k=>'<button data-action="match:'+k+'" aria-pressed="'+(k===filter)+'">'+({home:tr('Heim','Home'),away:tr('Auswärts','Away'),all:tr('Alle','All'),results:tr('Ergebnisse','Results')}[k])+'</button>').join('')+'</div>';
  const title='<h1>'+headings[filter]+'</h1>'+tabs;
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
  const size=info?4:6, pages=Math.max(1,Math.ceil(rows.length/size)), page=info?0:Math.min(matchPage,pages-1);
  if(!rows.length)return title+warning+'<div class="panel"><p>'+tr('Keine passenden Begegnungen im von nuLiga gelieferten Zeitraum.','No matching fixtures in the period supplied by nuLiga.')+'</p>'+(d.range?'<p>'+esc(d.range.from)+' – '+esc(d.range.to)+'</p>':'')+'</div>'+source;
  const cards=rows.slice(page*size,(page+1)*size).map(m=>{
    const date=new Date(m.date).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',weekday:'short',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
    const identity=teamAndLeague(m);
    const venueLabel=typeof m.venue==='string'&&m.venue.trim()?m.venue.trim():'';
    const courtLabel=Array.isArray(m.courts)&&m.courts.length?m.courts.join(', '):typeof m.courts==='string'&&m.courts.trim()?m.courts:tr('nicht verfügbar','not available');
    return '<article class="match-row"><div class="match-meta"><strong class="match-identity">'+esc(identity.team)+'</strong><span class="match-league">'+esc(identity.league)+'</span><span class="match-date">'+esc(date)+'</span><span class="match-side">'+(m.isHome?tr('Heim','Home'):tr('Auswärts','Away'))+'</span></div><div class="match-teams"><div class="match-pair"><strong class="match-team '+(m.isHome?'own-team':'opponent-team')+'">'+esc(shortClub(m.home))+'</strong><span class="match-versus">'+(m.score?esc(m.score):'–')+'</span><strong class="match-team '+(!m.isHome?'own-team':'opponent-team')+'">'+esc(shortClub(m.away))+'</strong></div>'+(venueLabel?'<span class="match-venue">'+tr('Platzanlage','Venue')+': <strong>'+esc(venueLabel)+'</strong></span>':'')+(m.isHome?'<span class="match-court">'+tr('Platz / Plätze','Court(s)')+': '+esc(courtLabel)+'</span>':'')+'</div></article>';
  }).join('');
  const pagination=!info&&pages>1?'<div class="actions match-pages"><button data-action="match-prev" '+(page===0?'disabled':'')+'>‹</button><span>'+(page+1)+' / '+pages+'</span><button data-action="match-next" '+(page===pages-1?'disabled':'')+'>›</button></div>':'';
  return title+warning+'<div class="panel match-list">'+cards+'</div>'+pagination+source;
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
  finally{fetching=false;if(view==='teams'||(view==='info'&&screens[slide]==='teams'))render();}
}
setInterval(refreshLeague,300000);
let eventData=null,eventPage=0,eventFetching=false;
function eventView(){
  const info=view==='info',d=eventData,title='<h1>'+t().upcoming+'</h1>';
  const source='<p class="match-source">'+tr('Quelle: NTC-Veranstaltungskalender','Source: NTC event calendar')+(d?.fetchedAt?' · '+tr('Stand ','Updated ')+esc(new Date(d.fetchedAt*1000).toLocaleString(lang==='de'?'de-DE':'en-GB',{timeZone:'Europe/Berlin',dateStyle:'short',timeStyle:'short'})):'')+(!info&&!pilot?' · <a href="https://ntc93.com/events/" target="_blank" rel="noopener noreferrer">'+tr('Original-Kalender','Original calendar')+'</a>':'')+'</p>';
  if(!d)return title+'<div class="panel"><p>'+tr('Termine werden geladen …','Loading events …')+'</p></div>';
  if(d.status==='unavailable')return title+'<div class="panel"><p>'+tr('Vereinskalender momentan nicht verfügbar.','Club calendar currently unavailable.')+'</p></div>'+source;
  const warning=d.status==='stale'?'<p class="message">'+tr('Zwischengespeicherte Termine – Aktualisierung fehlgeschlagen.','Cached events – refresh failed.')+'</p>':'';
  const locale=lang==='de'?'de-DE':'en-GB', fmt=(s,options)=>new Date(s).toLocaleString(locale,{timeZone:'Europe/Berlin',...options});
  const parts=new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const val=k=>parts.find(p=>p.type===k)?.value;const today=val('year')+'-'+val('month')+'-'+val('day');
  const rows=d.events.filter(e=>e.end.slice(0,10)>=today);
  if(!rows.length)return title+warning+'<div class="panel"><p>'+tr('Keine kommenden Veranstaltungen im abgerufenen Zeitraum.','No upcoming events in the retrieved period.')+'</p>'+(d.to?'<p>'+tr('Zeitraum bis ','Period until ')+esc(d.to)+'</p>':'')+'</div>'+source;
  const size=info?4:6,pages=Math.ceil(rows.length/size),page=info?0:Math.min(eventPage,pages-1);
  const cards=rows.slice(page*size,(page+1)*size).map(e=>{
    const multi=e.start.slice(0,10)!==e.end.slice(0,10),opts={weekday:'short',day:'2-digit',month:'2-digit',year:'numeric'};
    const date=fmt(e.start,opts)+(multi?' – '+fmt(e.end,opts):'');
    const time=e.allDay?tr('Ganztägig','All day'):fmt(e.start,{hour:'2-digit',minute:'2-digit'})+' – '+fmt(e.end,{hour:'2-digit',minute:'2-digit'});
    const link=!info&&!pilot&&typeof e.url==='string'&&/^https:\/\/(?:www\.)?ntc93\.com\//i.test(e.url)?'<a class="event-link" href="'+esc(e.url)+'" target="_blank" rel="noopener noreferrer">'+tr('Details','Details')+'</a>':'';
    return '<article class="event-row"><div class="event-date"><strong>'+esc(date)+'</strong><span>'+esc(time)+'</span></div><div><strong class="event-name">'+esc(e.title)+'</strong>'+(e.venue?'<p>'+esc(e.venue)+'</p>':'')+'</div>'+link+'</article>';
  }).join('');
  const paging=!info&&pages>1?'<div class="actions match-pages"><button data-action="event-prev" '+(page===0?'disabled':'')+'>‹</button><span>'+(page+1)+' / '+pages+'</span><button data-action="event-next" '+(page===pages-1?'disabled':'')+'>›</button></div>':'';
  return title+warning+'<div class="panel">'+cards+'</div>'+paging+source;
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
let terminalStatus=null,terminalChecking=false,adminAction='exit';
async function checkTerminal(){
  if(!pilot||terminalChecking)return;
  terminalChecking=true;
  try{
    const r=await fetch('terminal.php?status=1',{cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(8000)});
    const d=await r.json();terminalStatus=r.ok&&d.ready?'ready':d.configured?'locked':'unconfigured';
  }catch{terminalStatus='unavailable';}
  finally{terminalChecking=false;if(view==='courts')render();}
}
function ebusyView(){
  const head='<div class="booking-head"><h1>'+tr('Außenplätze buchen','Book outdoor courts')+'</h1>'+button(tr('Zurück','Back'),'home','primary')+'</div>';
  if(terminalStatus==='ready')return head+'<iframe class="booking-frame" title="'+tr('eBuSy Buchungsterminal','eBuSy booking terminal')+'" src="terminal.php" sandbox="allow-scripts allow-forms allow-same-origin" referrerpolicy="no-referrer"></iframe>';
  const label=terminalStatus==='locked'?tr('Terminal muss einmalig durch einen Administrator freigeschaltet werden.','An administrator must enable this terminal once.'):terminalStatus==='unconfigured'?tr('Terminal noch nicht eingerichtet.','Terminal not configured.'):terminalStatus==='unavailable'?tr('Terminal vorübergehend nicht verfügbar.','Terminal temporarily unavailable.'):tr('Terminal wird geprüft …','Checking terminal …');
  return head+'<div class="panel"><p>'+label+'</p>'+(terminalStatus==='locked'?button(tr('Terminal freischalten','Enable terminal'),'terminal-unlock','primary'):terminalStatus==='unavailable'?button(tr('Erneut prüfen','Retry'),'terminal-retry'):'')+'</div>';
}
function courtList(){return '<div class="grid">'+[1,2,3,4].map(i=>'<button class="tile" data-action="court:'+i+'" '+(i===2?'disabled':'')+'><strong>'+t().court+' '+i+'</strong><span class="'+(i===2?'occupied':'availability')+'">'+(i===2?t().busy:t().available)+'</span><small>'+t().slot+'</small></button>').join('')+'</div>'}
function render(){const l=t();document.documentElement.lang=lang;$('club').textContent=l.club;$('mode').textContent=view==='info'?l.info:l.touch;$('demo').textContent=tr('PILOT · TEILWEISE BEISPIELDATEN','PILOT · PARTIAL SAMPLE DATA');$('notice').textContent=pilot?tr('Vereinsmeldungen sind noch Beispiele · Buchung über eBuSy.','Club news is still sample data · Booking via eBuSy.'):tr('Platzbuchung und Meldungen sind noch Beispiele.','Court booking and news are still examples.');$('language').textContent=lang==='de'?'EN':'DE';syncFullscreen();
$('nav').innerHTML=[['home',l.home],['courts',l.courts],['calendar',l.calendar],['teams',l.teams],['news',l.news],['info',l.back]].map(([k,v])=>'<button data-action="'+k+'" aria-pressed="'+(view===k)+'">'+v+'</button>').join('');
const active=view==='info'?screens[slide]:view;let html='';
switch(active){
case 'welcome':html=hero(l.welcome,l.intro,(pilot?'<div class="panel"><h2>'+tr('Außenplätze','Outdoor courts')+'</h2><p>'+tr('Aktuelle Belegung im eBuSy-Buchungsplan.','Current availability in the eBuSy booking plan.')+'</p><div class="actions">'+button(tr('Buchungsplan öffnen','Open booking plan'),'courts','primary large')+'</div></div>':panel(l.today,[l.court+' 1',l.court+' 2',l.court+' 3'],[l.available,l.busy,l.available])));break;
case 'home':html='<h1>'+l.welcome+'</h1><p>'+l.intro+'</p><div class="grid">'+[['courts',l.booking],['calendar',l.upcoming],['teams',l.matches],['news',l.announcements]].map(([k,v])=>button(v,k,'tile large')).join('')+'</div>';break;
case 'courts':if(pilot){html=ebusyView();break;}html='<h1>'+l.booking+'</h1><p>'+l.select+'</p>'+courtList();if(court!==null)html+='<div class="panel" style="margin-top:1rem"><h2>'+l.court+' '+court+'</h2><p>'+l.slot+'</p><div class="actions">'+button(l.confirm,'confirm','primary')+button(l.reset,'reset')+'</div><p role="status" class="message">'+message+'</p></div>';break;
case 'calendar':html=eventView();break;
case 'teams':html=matchView();break;
case 'news':html='<h1>'+l.announcements+'</h1><div class="grid">'+l.newsItems.map(n=>'<article class="panel"><h2>'+n+'</h2><p>'+l.newsText+'</p></article>').join('')+'</div>';break;
case 'partner':html=hero(l.partner,l.partnerText);break;
}
$('language').disabled=pilot&&view==='courts';$('screen').innerHTML=html;clock();}
function clock(){const now=new Date();const locale=lang==='de'?'de-DE':'en-GB';$('clock').textContent=new Intl.DateTimeFormat(locale,{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Berlin'}).format(now);$('date').textContent=new Intl.DateTimeFormat(locale,{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Berlin'}).format(now);}
function go(next){if(pilot&&next==='courts'&&view!=='courts'){terminalStatus=null;checkTerminal();}if(next==='calendar')eventPage=0;if(next==='teams'){matchFilter='home';matchPage=0;}view=next;court=null;message='';last=started=Date.now();if(next==='info')slide=0;render();}
document.addEventListener('click',e=>{last=Date.now();const b=e.target.closest('[data-action]');if(!b){if(e.target.closest('a'))return;if(view==='info'&&e.target.closest('#screen'))go('home');return;}const a=b.dataset.action;if(a==='terminal-unlock'&&pilot&&view==='courts'){openAdmin('terminal');}else if(a==='terminal-retry'&&pilot&&view==='courts'){checkTerminal();}else if(a==='event-prev'){eventPage=Math.max(0,eventPage-1);render();}else if(a==='event-next'){eventPage++;render();}else if(a.startsWith('match:')){matchFilter=a.split(':')[1];matchPage=0;render();}else if(a==='match-prev'){matchPage=Math.max(0,matchPage-1);render();}else if(a==='match-next'){matchPage++;render();}else if(a.startsWith('court:')){court=Number(a.split(':')[1]);message='';render();}else if(a==='confirm'&&court!==null){message=t().success;render();}else if(a==='reset'){court=null;message='';render();}else if(['info','home','courts','calendar','teams','news'].includes(a))go(a);});
for(const event of ['pointerdown','keydown'])document.addEventListener(event,()=>{last=Date.now();});
$('language').addEventListener('click',()=>{if(pilot&&view==='courts')return;lang=lang==='de'?'en':'de';message='';render();});
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
  openAdmin('exit');
});
function openAdmin(action){
  if(adminDialog.open)return;
  adminAction=action;
  $('admin-title').textContent=tr('Admin-Freigabe','Admin approval');
  $('admin-label').textContent=tr('Admin-PIN','Admin PIN');
  $('admin-submit').textContent=action==='terminal'?tr('Terminal freischalten','Enable terminal'):tr('Vollbild verlassen','Exit fullscreen');
  $('admin-cancel').textContent=tr('Abbrechen','Cancel');
  adminPin.value='';adminStatus.textContent='';adminDialog.showModal();adminPin.focus();
}
$('admin-cancel').addEventListener('click',()=>{adminPin.value='';adminDialog.close();});
adminDialog.addEventListener('close',()=>{adminPin.value='';adminStatus.textContent='';last=Date.now();});
adminForm.addEventListener('submit',async e=>{
  e.preventDefault();
  if($('admin-submit').disabled)return;
  $('admin-submit').disabled=true;
  const pin=adminPin.value, action=adminAction;adminPin.value='';
  adminStatus.textContent=tr('Prüfe …','Checking …');
  try{
    const res=await fetch('admin-pin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pin,action}),cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(8000)});
    const data=await res.json();
    if(!adminDialog.open)return;
    if(res.ok&&data.ok===true){adminDialog.close();if(action==='terminal'){checkTerminal();}else if(document.fullscreenElement)await document.exitFullscreen();return;}
    adminStatus.textContent=res.status===429?tr('Zu viele Versuche. Bitte später erneut versuchen.','Too many attempts. Try again later.'):res.status===503?tr('Admin-PIN noch nicht eingerichtet.','Admin PIN not configured.'):tr('PIN nicht korrekt.','Incorrect PIN.');
  }catch{if(adminDialog.open)adminStatus.textContent=tr('Prüfung nicht verfügbar.','Verification unavailable.');}
  finally{$('admin-submit').disabled=false;if(adminDialog.open)adminPin.focus();}
});
syncFullscreen();
setInterval(()=>{const now=Date.now();clock();if(adminDialog.open)return;if(view==='info'){if(now-started>=dwell){slide=(slide+1)%screens.length;started=now;render();}$('progress').style.setProperty('--progress',Math.min(100,(now-started)/dwell*100)+'%');}else{if(!(pilot&&view==='courts')&&now-last>=idle)go('info');$('progress').style.setProperty('--progress','0%');}},250);
render();refreshLeague();refreshEvents();
