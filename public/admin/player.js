'use strict';
(()=>{
const $=id=>document.getElementById(id), preview=document.body.dataset.preview, token=location.hash.slice(1), VERSION='0.6.3';
let snapshot=null,slides=[],index=0,rotation=null,paused=false,lastOk=0,denied=false,generation=0,logoUrl=null,slideUrl=null,polling=false,signature='';
const tr=(de,en)=>snapshot?.language==='en'?en:de;
const node=(tag,value,cls)=>{const e=document.createElement(tag);if(value!==undefined)e.textContent=String(value);if(cls)e.className=cls;return e;};
function clearImage(kind){if(kind==='logo'){if(logoUrl)URL.revokeObjectURL(logoUrl);logoUrl=null;$('club-logo').hidden=true;$('club-logo').removeAttribute('src');}else{if(slideUrl)URL.revokeObjectURL(slideUrl);slideUrl=null;}}
async function request(action,id){
  const options={cache:'no-store',credentials:'same-origin',signal:AbortSignal.timeout(35000)};
  let url;
  if(preview){url=preview+(action==='image'?'&image='+encodeURIComponent(id):'');}
  else{url='./?device=1';options.method='POST';options.headers={'Content-Type':'application/json'};options.body=JSON.stringify({token,action,id,version:VERSION});}
  const res=await fetch(url,options);
  if(res.status===403){const e=new Error('Access denied');e.denied=true;throw e;}
  if(!res.ok)throw new Error('HTTP '+res.status);
  if(action==='image'){if(!res.headers.get('Content-Type')?.startsWith('image/png'))throw new Error('Invalid image');return res.blob();}
  return res.json();
}
function message(title,body){generation++;clearImage('slide');clearImage('logo');$('slide').className='welcome';$('slide').replaceChildren(node('h1',title),node('p',body));$('club').textContent='TENNILO ClubDisplay';$('screen').textContent='';$('position').textContent='–';}
function stopAccess(){denied=true;snapshot=null;slides=[];clearTimeout(rotation);message(tr('Zugang nicht verfügbar','Access unavailable'),tr('Bitte Gerätefreigabe oder Vereinsanmeldung prüfen.','Check device access or club login.'));$('status').textContent='Zugang nicht verfügbar / Access unavailable';}
function label(kind){return ({welcome:tr('Willkommen','Welcome'),weather:tr('Wetter','Weather'),calendar:tr('Termine','Events'),teams:tr('Mannschaften','Teams'),news:tr('Vereinsnachrichten','Club news'),partner:tr('Sponsoren','Sponsors'),restaurant:tr('Gastronomie','Dining'),courts:tr('Platzbuchung','Court booking')})[kind]||kind;}
function groups(items,size){const pages=[];for(let i=0;i<items.length;i+=size)pages.push(items.slice(i,i+size));return pages;}
function textPages(value){
  const text=String(value||'');if(text.length<=500)return [text];
  const pages=[];let rest=text;
  while(rest.length>500){let cut=Math.max(rest.lastIndexOf(' ',500),rest.lastIndexOf('\n',500));if(cut<200)cut=500;pages.push(rest.slice(0,cut));rest=rest.slice(cut).trimStart();}
  if(rest)pages.push(rest);return pages;
}
function build(s){
  const out=[];
  for(const kind of s.modules||[]){
    const start=out.length;
    if(kind==='welcome')out.push({key:'welcome',kind:'welcome'});
    if(kind==='restaurant'&&s.dining?.name){
      out.push({key:'dining',kind:'dining'});
      if(s.dining.hours?.length)out.push({key:'dining-hours',kind:'hours'});
      groups((s.dining.special||[]).filter(r=>r.date>=s.date),5).forEach((rows,i)=>out.push({key:'dining-special:'+i,kind:'special',rows}));
      groups(s.dining.offers_today||[],4).forEach((rows,i)=>out.push({key:'dining-offers:'+i,kind:'offers',rows}));
    }
    for(const row of (s.content||[]).filter(r=>r.kind===kind)){
      const pages=textPages(row.body);
      pages.forEach((body,i)=>out.push({key:'entry:'+row.id+':'+i,kind:'entry',row:{...row,body,has_image:i===0&&row.has_image},page:i,total:pages.length}));
    }
    if(kind==='courts'&&s.mode==='INTERACTIVE'&&s.booking_url)out.push({key:'booking',kind:'booking'});
    else if(s.feeds?.[kind]){
      const pages=groups(s.feeds[kind].rows||[],5);
      (pages.length?pages:[[]]).forEach((rows,i)=>out.push({key:'feed:'+kind+':'+i,kind:'feed',source:kind,rows}));
    }else if(['weather','teams','courts'].includes(kind)||s.sources?.[kind]==='pending')out.push({key:'pending:'+kind,kind:'pending',source:kind});
    for(let i=start;i<out.length;i++)out[i].module=kind;
  }
  return out.length?out:[{key:'welcome',kind:'welcome',module:'welcome'}];
}
const add=(el,...children)=>{children.filter(Boolean).forEach(c=>el.append(c));return el;};
function tile(title,body){return add(node('div',undefined,'tile'),node('strong',title),node('p',body));}
function heading(el,k,title){add(el,node('div',label(k),'eyebrow'),node('h1',title));}
function hoursText(h){if(!h||h.state==='unknown')return tr('Keine Angabe','Not specified');if(h.state==='closed')return tr('Geschlossen','Closed');return (h.slots||[]).map(s=>s.from+' – '+s.to).join(' · ');}
async function loadImage(img,id,seq,kind){
 try{const blob=await request('image',id);if(seq!==generation||denied)return;const url=URL.createObjectURL(blob);
 if(kind==='logo'){clearImage('logo');logoUrl=url;}else{clearImage('slide');slideUrl=url;}
 img.src=url;img.hidden=false;}catch(e){if(e.denied)stopAccess();}
}
function render(){
 if(!snapshot||!slides.length)return;
 generation++;const seq=generation;clearImage('slide');const main=$('slide'),slide=slides[index];main.className='';main.replaceChildren();main.scrollTop=0;
 $('club').textContent=snapshot.club;$('screen').textContent=snapshot.screen;$('position').textContent=(index+1)+' / '+slides.length;
 if(snapshot.has_logo&&!logoUrl)loadImage($('club-logo'),'logo',seq,'logo');
 if(slide.kind==='welcome'){main.className='welcome';heading(main,'welcome',snapshot.club);add(main,node('p',snapshot.welcome||tr('Herzlich willkommen!','Welcome!')));}
 else if(slide.kind==='entry'){
  const r=slide.row;heading(main,r.kind,r.title);if(slide.total>1)add(main,node('small',(slide.page+1)+' / '+slide.total));
  const layout=node('div',undefined,'entry-layout'+(r.has_image?'':' no-image'));
  const copy=node('div');if(r.starts||r.ends)add(copy,node('small',[r.starts,r.ends].filter(Boolean).join(' – ')));add(copy,node('p',r.body||''));if(r.url)add(copy,node('small',r.url));
  add(layout,copy);if(r.has_image){const img=node('img');img.alt=r.title;img.hidden=true;add(layout,img);loadImage(img,r.id,seq,'slide');}add(main,layout);
 }else if(slide.kind==='dining'){
  const d=snapshot.dining;heading(main,'restaurant',d.name);
  const cards=node('div',undefined,'cards');add(cards,tile(tr('Heute','Today'),hoursText(d.today)+(d.today_note?' · '+d.today_note:'')));
  if(d.phone||d.website)add(cards,tile(tr('Kontakt','Contact'),[d.phone,d.website].filter(Boolean).join('\n')));
  const ways=[];if(d.reservation_onsite)ways.push(tr('Vor Ort','On site'));if(d.reservation_phone)ways.push(tr('Telefonisch','By phone'));if(d.reservation_url)ways.push(d.reservation_url);if(ways.length)add(cards,tile(tr('Reservierung','Reservations'),ways.join('\n')));
  add(main,cards);if(d.note)add(main,node('p',d.note));
 }else if(slide.kind==='hours'){
  heading(main,'restaurant',tr('Reguläre Öffnungszeiten','Regular opening hours'));const regular=node('div',undefined,'cards');
  const days=snapshot.language==='en'?['Mon','Tue','Wed','Thu','Fri','Sat','Sun']:['Mo','Di','Mi','Do','Fr','Sa','So'];
  (snapshot.dining.hours||[]).forEach((h,i)=>add(regular,tile(days[i],hoursText(h))));add(main,regular);
 }else if(slide.kind==='special'){
  heading(main,'restaurant',tr('Sonderöffnungszeiten','Special opening hours'));for(const r of slide.rows)add(main,node('div',r.date+' · '+hoursText(r)+(r.note?' · '+r.note:''),'feed-row'));
 }else if(slide.kind==='offers'){
  heading(main,'restaurant',tr('Heutige Angebote','Today’s offers'));const cards=node('div',undefined,'cards');for(const offer of slide.rows)add(cards,tile(offer.title,[offer.price,offer.note].filter(Boolean).join(' · ')));add(main,cards);
 }else if(slide.kind==='booking'){
  heading(main,'courts',tr('Platz buchen','Book a court'));
  add(main,node('p',tr('Reservierung im freigegebenen externen Buchungssystem.','Reservation in the approved external booking system.')));
  if(preview)add(main,node('p',tr('Vorschau · Buchung nicht möglich.','Preview · booking disabled.'),'warning'));
  else{const url=new URL(snapshot.booking_url);if(url.protocol==='https:'&&(url.hostname.endsWith('.ebusy.de')||url.href==='https://clubdisplay.ntc93.de/terminal.php')){const a=node('a',tr('Buchungssystem öffnen','Open booking system'),'booking-button');a.href=url.href;a.target='_blank';a.rel='noopener noreferrer';add(main,a);}}
 }else if(slide.kind==='feed'){
  const f=snapshot.feeds[slide.source];heading(main,slide.source,label(slide.source));
  if(f.status!=='ok')add(main,node('p',f.status==='stale'?tr('Zwischengespeicherte Daten','Cached data'):tr('Daten derzeit nicht verfügbar','Data currently unavailable'),'warning'));
  if(slide.source==='weather'&&f.current){const c=f.current;const tiles=node('div',undefined,'cards');for(const [k,l,u] of [['temperature',tr('Temperatur','Temperature'),' °C'],['wind',tr('Wind','Wind'),' km/h'],['rain',tr('Niederschlag','Rain'),' mm']])if(typeof c[k]==='number')add(tiles,tile(l,c[k]+u));add(main,tiles);}
  else for(const r of slide.rows||[]){const title=r.title||[r.home||r.homeTeam,r.away||r.awayTeam].filter(Boolean).join(' – ');add(main,add(node('div',undefined,'feed-row'),node('strong',title||label(slide.source)),node('p',[r.start||r.date,r.time,r.venue,r.league,r.score,r.excerpt].filter(Boolean).join(' · '))));}
 }else{heading(main,slide.source,label(slide.source));add(main,node('p',tr('Noch keine freigegebene Datenquelle / Funktion.','No approved data source or feature yet.')));}
}
function slideSeconds(s,slide){const n=Number(s.module_dwell?.[slide?.module]);return Number.isFinite(n)&&n>=5&&n<=120?n:s.dwell;}
function schedule(){clearTimeout(rotation);if(!paused&&snapshot&&slides.length>1)rotation=setTimeout(()=>move(1),slideSeconds(snapshot,slides[index])*1000);}
function move(delta){if(!slides.length)return;index=(index+delta+slides.length)%slides.length;render();schedule();}
async function poll(){
 if(denied||polling)return;polling=true;
 try{
   const s=await request('snapshot');
   if(!s||!Array.isArray(s.modules)||!Array.isArray(s.content)||!Number.isFinite(s.dwell))throw new Error('Invalid config');
   lastOk=Date.now();
   const nextSignature=JSON.stringify(s);
   if(signature!==nextSignature){
     const key=slides[index]?.key;snapshot=s;signature=nextSignature;document.documentElement.lang=s.language==='en'?'en':'de';
     document.documentElement.style.setProperty('--accent',/^#[a-f0-9]{6}$/i.test(s.color)?s.color:'#21d5db');
     clearImage('logo');slides=build(s);index=Math.max(0,slides.findIndex(x=>x.key===key));render();schedule();
   }
   $('status').textContent=preview?tr('VORSCHAU · keine Geräteanmeldung','PREVIEW · no device login'):tr('Verbunden · '+new Date(lastOk).toLocaleTimeString('de-DE'),'Connected · '+new Date(lastOk).toLocaleTimeString('en-GB'));
 }catch(e){
   if(e.denied){stopAccess();return;}
   if(!lastOk||Date.now()-lastOk>180000){snapshot=null;slides=[];signature='';clearTimeout(rotation);message('Keine Verbindung / Offline','Daten momentan nicht verfügbar. Automatischer Neuversuch.');}
   $('status').textContent='Offline · erneuter Versuch / retrying';
 }finally{polling=false;if(!denied)setTimeout(poll,60000);}
}
$('prev').addEventListener('click',()=>move(-1));$('next').addEventListener('click',()=>move(1));$('pause').addEventListener('click',()=>{paused=!paused;$('pause').textContent=paused?'▶':'Ⅱ';$('pause').setAttribute('aria-label',paused?'Play':'Pause');schedule();});$('full').addEventListener('click',()=>{if(document.fullscreenElement)document.exitFullscreen().catch(()=>{});else document.documentElement.requestFullscreen().catch(()=>{});});
function clock(){const now=new Date(),l=snapshot?.language==='en'?'en-GB':'de-DE';$('clock').textContent=now.toLocaleTimeString(l,{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Berlin'});$('date').textContent=now.toLocaleDateString(l,{weekday:'long',day:'numeric',month:'long',timeZone:'Europe/Berlin'});}
clock();setInterval(clock,1000);
if(!preview&&!/^[a-f0-9]{64}$/.test(token)){message('Gerät nicht gekoppelt','Bitte die persönliche Geräteadresse aus der Vereinsadministration verwenden.');$('status').textContent='Nicht gekoppelt';}else poll();
})();
