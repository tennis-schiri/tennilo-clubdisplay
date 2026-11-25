'use strict';
// Copyright © 2026 TENNILO. All rights reserved.
const $ = id => document.getElementById(id);
const pilot = location.hostname === 'clubdisplay.ntc93.de';
let lang='de', view='info', slide=0, last=Date.now(), started=Date.now(), court=null, message='';
const dwell=12000, idle=60000;
const dictionary={
de:{club:pilot?'NTC 93 · Pilotbetrieb':'TENNILO · Entwicklungsdemo',info:'Informationsmodus',touch:'Interaktiver Modus',demo:'PROTOTYP · BEISPIELDATEN',notice:'Keine echten Buchungen oder Vereinsdaten.',welcome:'Willkommen im Clubhaus',intro:'Alles Wichtige rund um deinen Tennisverein. Berühre den Bildschirm und entdecke die Funktionen.',start:'Touch-Menü öffnen',today:'Heute auf der Anlage',courts:'Plätze',calendar:'Termine',teams:'Mannschaften',news:'Vereinsinfos',home:'Start',back:'Infomodus',available:'Frei',busy:'Belegt',court:'Platz',booking:'Platzbuchung',select:'Platz auswählen',slot:'Beispielzeit: 15:00–16:00',confirm:'Buchung simulieren',success:'Simulation erfolgreich. Es wurde nichts tatsächlich gebucht oder gespeichert.',upcoming:'Nächste Termine',events:['Vereinstraining','Mannschaftsspieltag','Clubturnier'],eventTimes:['Heute · 17:00','Samstag · 10:00','Sonntag · 11:00'],matches:'Mannschaftsspiele',matchNames:['Herren 30','Damen','Jugend U14'],matchDetails:['Heimspiel · Samstag','Auswärts · Sonntag','Training · Mittwoch'],announcements:'Neues aus dem Club',newsItems:['Willkommen zur neuen Saison','Aktuelle Platzhinweise','Informationen zu Vereinsveranstaltungen'],newsText:'Beispiel für eine Vereinsmeldung. Die späteren Inhalte werden zentral verwaltet.',partner:'Partner & Sponsoren',partnerText:'Dieser Bereich ist für freigegebene Vereinswerbung vorgesehen.',reset:'Zurück zur Auswahl'},
en:{club:pilot?'NTC 93 · Pilot':'TENNILO · Development demo',info:'Information mode',touch:'Interactive mode',demo:'PROTOTYPE · SAMPLE DATA',notice:'No real bookings or club data.',welcome:'Welcome to the clubhouse',intro:'Everything you need to know about your tennis club. Touch the screen to explore.',start:'Open touch menu',today:'Today at the club',courts:'Courts',calendar:'Events',teams:'Teams',news:'Club news',home:'Home',back:'Info mode',available:'Available',busy:'Occupied',court:'Court',booking:'Court booking',select:'Select a court',slot:'Example time: 15:00–16:00',confirm:'Simulate booking',success:'Simulation successful. Nothing was actually booked or stored.',upcoming:'Upcoming events',events:['Club training','Team match day','Club tournament'],eventTimes:['Today · 17:00','Saturday · 10:00','Sunday · 11:00'],matches:'Team matches',matchNames:['Men 30','Women','Junior U14'],matchDetails:['Home · Saturday','Away · Sunday','Training · Wednesday'],announcements:'Club updates',newsItems:['Welcome to the new season','Current court information','Club event information'],newsText:'Example club announcement. Future content will be managed centrally.',partner:'Partners & sponsors',partnerText:'Reserved for approved club advertising.',reset:'Back to selection'}
};
const t=()=>dictionary[lang];
const screens=['welcome','calendar','teams','news','partner'];
function button(label,action,cls=''){return '<button type="button" class="'+cls+'" data-action="'+action+'">'+label+'</button>'}
function line(a,b){return '<div class="line"><strong>'+a+'</strong><span class="muted">'+b+'</span></div>'}
function panel(title,names,details){return '<div class="panel"><h2>'+title+'</h2>'+names.map((n,i)=>line(n,details[i])).join('')+'</div>'}
function hero(title,description,extra=''){return '<div class="hero"><div><h1>'+title+'</h1><p>'+description+'</p><div class="actions">'+button(t().start,'home','primary large')+'</div></div>'+extra+'</div>'}
function courtList(){return '<div class="grid">'+[1,2,3,4].map(i=>'<button class="tile" data-action="court:'+i+'" '+(i===2?'disabled':'')+'><strong>'+t().court+' '+i+'</strong><span class="'+(i===2?'occupied':'availability')+'">'+(i===2?t().busy:t().available)+'</span><small>'+t().slot+'</small></button>').join('')+'</div>'}
function render(){const l=t();document.documentElement.lang=lang;$('club').textContent=l.club;$('mode').textContent=view==='info'?l.info:l.touch;$('demo').textContent=l.demo;$('notice').textContent=l.notice;$('language').textContent=lang==='de'?'EN':'DE';$('fullscreen').setAttribute('aria-label',lang==='de'?'Vollbild':'Fullscreen');
$('nav').innerHTML=[['home',l.home],['courts',l.courts],['calendar',l.calendar],['teams',l.teams],['news',l.news],['info',l.back]].map(([k,v])=>'<button data-action="'+k+'" aria-pressed="'+(view===k)+'">'+v+'</button>').join('');
const active=view==='info'?screens[slide]:view;let html='';
switch(active){
case 'welcome':html=hero(l.welcome,l.intro,panel(l.today,[l.court+' 1',l.court+' 2',l.court+' 3'],[l.available,l.busy,l.available]));break;
case 'home':html='<h1>'+l.welcome+'</h1><p>'+l.intro+'</p><div class="grid">'+[['courts',l.booking],['calendar',l.upcoming],['teams',l.matches],['news',l.announcements]].map(([k,v])=>button(v,k,'tile large')).join('')+'</div>';break;
case 'courts':html='<h1>'+l.booking+'</h1><p>'+l.select+'</p>'+courtList();if(court!==null)html+='<div class="panel" style="margin-top:1rem"><h2>'+l.court+' '+court+'</h2><p>'+l.slot+'</p><div class="actions">'+button(l.confirm,'confirm','primary')+button(l.reset,'reset')+'</div><p role="status" class="message">'+message+'</p></div>';break;
case 'calendar':html='<h1>'+l.upcoming+'</h1>'+panel(l.calendar,l.events,l.eventTimes);break;
case 'teams':html='<h1>'+l.matches+'</h1>'+panel(l.teams,l.matchNames,l.matchDetails);break;
case 'news':html='<h1>'+l.announcements+'</h1><div class="grid">'+l.newsItems.map(n=>'<article class="panel"><h2>'+n+'</h2><p>'+l.newsText+'</p></article>').join('')+'</div>';break;
case 'partner':html=hero(l.partner,l.partnerText);break;
}
$('screen').innerHTML=html;clock();}
function clock(){const now=new Date();const locale=lang==='de'?'de-DE':'en-GB';$('clock').textContent=new Intl.DateTimeFormat(locale,{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Berlin'}).format(now);$('date').textContent=new Intl.DateTimeFormat(locale,{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Berlin'}).format(now);}
function go(next){view=next;court=null;message='';last=started=Date.now();if(next==='info')slide=0;render();}
document.addEventListener('click',e=>{last=Date.now();const b=e.target.closest('[data-action]');if(!b){if(view==='info'&&e.target.closest('#screen'))go('home');return;}const a=b.dataset.action;if(a.startsWith('court:')){court=Number(a.split(':')[1]);message='';render();}else if(a==='confirm'&&court!==null){message=t().success;render();}else if(a==='reset'){court=null;message='';render();}else if(['info','home','courts','calendar','teams','news'].includes(a))go(a);});
for(const event of ['pointerdown','keydown'])document.addEventListener(event,()=>{last=Date.now();});
$('language').addEventListener('click',()=>{lang=lang==='de'?'en':'de';message='';render();});
$('fullscreen').addEventListener('click',async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen();}catch{ /* Browser may block fullscreen. */ }});
setInterval(()=>{const now=Date.now();clock();if(view==='info'){if(now-started>=dwell){slide=(slide+1)%screens.length;started=now;render();}$('progress').style.setProperty('--progress',Math.min(100,(now-started)/dwell*100)+'%');}else{if(now-last>=idle)go('info');$('progress').style.setProperty('--progress','0%');}},250);
render();
