<?php
declare(strict_types=1);
// Managed displays: scoped read-only delivery, no booking or arbitrary remote requests.
require_once __DIR__.'/providers.php';
const CD_SOURCE_MODULES=['teams'=>'nuLiga','calendar'=>'Vereinskalender','news'=>'WordPress / News','weather'=>'Wetter','courts'=>'eBuSy'];
function cd_managed_schema(PDO $db):void{
    $db->exec("CREATE TABLE IF NOT EXISTS source_config (club TEXT NOT NULL REFERENCES clubs(slug), kind TEXT NOT NULL, provider TEXT NOT NULL, reference TEXT NOT NULL, updated TEXT NOT NULL, PRIMARY KEY(club,kind));
    CREATE TABLE IF NOT EXISTS customer_contract (club TEXT PRIMARY KEY REFERENCES clubs(slug), screen_limit INTEGER NOT NULL, state TEXT NOT NULL, cycle TEXT NOT NULL, renewal TEXT NOT NULL, note TEXT NOT NULL, updated TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS device_access (club TEXT NOT NULL, display TEXT NOT NULL, token_hash TEXT NOT NULL UNIQUE, issued INTEGER NOT NULL, last_seen INTEGER, version TEXT NOT NULL DEFAULT '', PRIMARY KEY(club,display), FOREIGN KEY(club,display) REFERENCES displays(club,slug) ON DELETE CASCADE);
    CREATE TABLE IF NOT EXISTS source_cache (kind TEXT PRIMARY KEY, payload TEXT NOT NULL, fetched INTEGER NOT NULL, attempted INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS billing_profile (club TEXT PRIMARY KEY REFERENCES clubs(slug), recurring_cents INTEGER, setup_cents INTEGER, basis TEXT NOT NULL, invoice_ref TEXT NOT NULL, payment_state TEXT NOT NULL, updated TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS audit_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, role TEXT NOT NULL, club TEXT NOT NULL, action TEXT NOT NULL, target TEXT NOT NULL)");
}
function cd_sources(PDO $db,string $club,?string $scope):array{
    cd_scope($scope,$club);cd_club($db,$club);$q=$db->prepare('SELECT * FROM source_config WHERE club=?');$q->execute([$club]);return array_column($q->fetchAll(),null,'kind');
}
function cd_save_sources(PDO $db,array $in,?string $scope):void{
    $club=cd_text($in['club']??'',50,true);cd_scope($scope,$club);$c=cd_club($db,$club);
    $old=cd_sources($db,$club,$scope);$modules=json_decode($c['modules'],true)?:[];
    $db->beginTransaction();
    try{
        foreach(CD_SOURCE_MODULES as $kind=>$label){
            if(!in_array($kind,$modules,true))continue;
            $row=$in['sources'][$kind]??[];$provider=$row['provider']??'manual';
            $allowed=['manual','pending',...array_keys(CD_PROVIDER_OPTIONS[$kind]??[])];
            if($club==='ntc93')$allowed[]=$kind==='courts'?'ntc-booking':'ntc-feed';
            if(!in_array($provider,$allowed,true))throw new InvalidArgumentException('Ungültige Datenquelle.');
            $ref=cd_provider_reference($provider,cd_text($row['reference']??'',500));
            if(!in_array($provider,['manual','pending'],true)&&$scope!==null&&(($old[$kind]['provider']??'')!==$provider||($old[$kind]['reference']??'')!==$ref))throw new InvalidArgumentException('Nur TENNILO kann externe Datenquellen oder Terminallinks freischalten bzw. ändern. Bitte Prüfung erforderlich wählen.');
            // Manual and pending references are documentation only.
            $q=$db->prepare('INSERT INTO source_config VALUES (?,?,?,?,?) ON CONFLICT(club,kind) DO UPDATE SET provider=excluded.provider,reference=excluded.reference,updated=excluded.updated');
            $q->execute([$club,$kind,$provider,$ref,gmdate('c')]);
        }
        $db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
}
function cd_contract(PDO $db,string $club):array{
    cd_club($db,$club);$q=$db->prepare('SELECT * FROM customer_contract WHERE club=?');$q->execute([$club]);return $q->fetch()?:['screen_limit'=>1,'state'=>'pilot','cycle'=>'none','renewal'=>'','note'=>''];
}
function cd_save_contract(PDO $db,array $in,?string $scope):void{
    if($scope!==null)throw new InvalidArgumentException('Nur TENNILO darf Vertragsdaten ändern.');
    $club=cd_text($in['club']??'',50,true);cd_club($db,$club);
    $limit=filter_var($in['screen_limit']??null,FILTER_VALIDATE_INT);
    $state=$in['state']??'';$cycle=$in['cycle']??'';
    if($limit===false||$limit<1||$limit>100||!in_array($state,['pilot','active','paused'],true)||!in_array($cycle,['none','monthly','yearly'],true))throw new InvalidArgumentException('Ungültige Vertragsdaten.');
    $renewal=cd_date($in['renewal']??'');$note=cd_text($in['note']??'',1000);
    $db->exec('BEGIN IMMEDIATE');
    try{
        $q=$db->prepare('SELECT COUNT(*) FROM device_access WHERE club=?');$q->execute([$club]);
        if((int)$q->fetchColumn()>$limit)throw new InvalidArgumentException('Zuerst nicht mehr benötigte Gerätezugänge widerrufen.');
        $q=$db->prepare('INSERT INTO customer_contract VALUES (?,?,?,?,?,?,?) ON CONFLICT(club) DO UPDATE SET screen_limit=excluded.screen_limit,state=excluded.state,cycle=excluded.cycle,renewal=excluded.renewal,note=excluded.note,updated=excluded.updated');
        $q->execute([$club,$limit,$state,$cycle,$renewal,$note,gmdate('c')]);$db->exec('COMMIT');
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cd_display(PDO $db,string $club,string $slug,?string $scope):array{
    cd_scope($scope,$club);$c=cd_club($db,$club);
    $q=$db->prepare('SELECT * FROM displays WHERE club=? AND slug=?');$q->execute([$club,$slug]);$d=$q->fetch();
    if(!$d)throw new InvalidArgumentException('Bildschirm nicht gefunden.');
    return $d;
}
function cd_device_status(PDO $db,string $club,string $slug):?array{
    $q=$db->prepare('SELECT issued,last_seen,version FROM device_access WHERE club=? AND display=?');$q->execute([$club,$slug]);return $q->fetch()?:null;
}
function cd_device_issue(PDO $db,string $club,string $slug,?string $scope):string{
    cd_display($db,$club,$slug,$scope);
    $token=bin2hex(random_bytes(32));$db->exec('BEGIN IMMEDIATE');
    try{
        $d=cd_display($db,$club,$slug,$scope);$contract=cd_contract($db,$club);
        if(!$d['enabled']||$contract['state']==='paused')throw new InvalidArgumentException('Bildschirm oder Vertrag ist deaktiviert.');
        $q=$db->prepare('SELECT COUNT(*) FROM device_access WHERE club=? AND display<>?');$q->execute([$club,$slug]);
        if((int)$q->fetchColumn()>=(int)$contract['screen_limit'])throw new InvalidArgumentException('Bildschirmkontingent ausgeschöpft. TENNILO kontaktieren.');
        $q=$db->prepare("INSERT INTO device_access(club,display,token_hash,issued,last_seen,version) VALUES (?,?,?,?,NULL,'') ON CONFLICT(club,display) DO UPDATE SET token_hash=excluded.token_hash,issued=excluded.issued,last_seen=NULL,version=''");
        $q->execute([$club,$slug,hash('sha256',$token),time()]);$db->exec('COMMIT');return $token;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function cd_device_revoke(PDO $db,string $club,string $slug,?string $scope):void{
    cd_display($db,$club,$slug,$scope);$q=$db->prepare('DELETE FROM device_access WHERE club=? AND display=?');$q->execute([$club,$slug]);
}
function cd_device_auth(PDO $db,mixed $token):?array{
    if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
    $q=$db->prepare('SELECT a.club,a.display,a.token_hash FROM device_access a JOIN displays d ON d.club=a.club AND d.slug=a.display LEFT JOIN customer_contract c ON c.club=a.club WHERE a.token_hash=? AND d.enabled=1 AND (c.state IS NULL OR c.state<>?)');
    $q->execute([hash('sha256',$token),'paused']);return $q->fetch()?:null;
}
function cd_device_seen(PDO $db,array $a,string $version):void{
    $version=preg_match('/^[a-zA-Z0-9._-]{1,30}$/D',$version)?$version:'unknown';
    $q=$db->prepare('UPDATE device_access SET last_seen=?,version=? WHERE club=? AND display=? AND token_hash=?');$q->execute([time(),$version,$a['club'],$a['display'],$a['token_hash']]);
}
function cd_active_content(PDO $db,string $club,array $modules,string $today,string $slug):array{
    $modules=array_values(array_intersect($modules,array_keys(CD_CONTENT)));
    if(!$modules)return [];
    $placeholders=implode(',',array_fill(0,count($modules),'?'));
    $q=$db->prepare("SELECT c.id,c.kind,c.title,c.body,c.starts,c.ends,c.url,c.updated,EXISTS(SELECT 1 FROM content_media m WHERE m.content_id=c.id) AS has_image FROM club_content c LEFT JOIN content_audience a ON a.content_id=c.id WHERE c.club=? AND c.enabled=1 AND c.kind IN ($placeholders) AND (c.kind='calendar' OR c.starts='' OR c.starts<=?) AND (CASE WHEN c.kind='calendar' AND c.ends='' AND c.starts<>'' THEN c.starts ELSE c.ends END='' OR CASE WHEN c.kind='calendar' AND c.ends='' AND c.starts<>'' THEN c.starts ELSE c.ends END>=?) AND (a.mode IS NULL OR a.mode='all' OR (a.mode='selected' AND EXISTS(SELECT 1 FROM content_targets t WHERE t.content_id=c.id AND t.club=c.club AND t.display=?))) ORDER BY c.starts,c.id LIMIT 300");
    $q->execute([$club,...$modules,$today,$today,$slug]);return $q->fetchAll();
}
function cd_dining_today(array $d,DateTimeImmutable $now):array{
    $day=(int)$now->format('N')-1;$today=$now->format('Y-m-d');
    $hours=$d['hours'][$day]??['state'=>'unknown','slots'=>[]];$note='';
    foreach($d['special']??[] as $row)if($row['date']===$today){$hours=['state'=>$row['state'],'slots'=>$row['slots']];$note=$row['note'];break;}
    $d['today']=$hours;$d['today_note']=$note;
    $d['offers_today']=array_values(array_filter($d['offers']??[],fn($r)=>$r['day']===$day&&($r['starts']===''||$r['starts']<=$today)&&($r['ends']===''||$r['ends']>=$today)));
    return $d;
}
function cd_snapshot(PDO $db,string $club,string $slug,?string $scope,?DateTimeImmutable $now=null):array{
    $c=cd_club($db,$club);$d=cd_display($db,$club,$slug,$scope);
    $modules=array_values(array_intersect(json_decode($d['modules'],true)?:[],json_decode($c['modules'],true)?:[]));
    $layout=cd_layout($db,$club,$slug);
    $ordered=array_values(array_intersect($layout['order'],$modules));
    foreach($modules as $k)if(!in_array($k,$ordered,true))$ordered[]=$k;
    $modules=$ordered;
    $now??=new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin'));$today=$now->format('Y-m-d');
    $b=cd_brand($db,$club);$sources=cd_sources($db,$club,$scope);$activeSources=[];
    foreach($sources as $kind=>$row)if(in_array($kind,$modules,true))$activeSources[$kind]=$row['provider'];
    return ['club'=>$c['name'],'screen'=>$d['name'],'mode'=>$d['mode'],'language'=>$d['language'],'dwell'=>(int)$d['dwell'],'module_dwell'=>(object)array_intersect_key($layout['dwell'],array_flip($modules)),'color'=>$c['color'],'modules'=>$modules,'welcome'=>$layout['welcome']!==''?$layout['welcome']:$b['welcome'],'has_logo'=>(bool)$b['logo_size'],'brand_revision'=>$c['updated'],'date'=>$today,'content'=>cd_active_content($db,$club,$modules,$today,$slug),'dining'=>in_array('restaurant',$modules,true)?cd_dining_today(cd_dining_get($db,$club,$scope),$now):null,'sources'=>$activeSources];
}
function cd_snapshot_image(PDO $db,string $club,string $slug,?string $scope,mixed $id):?string{
    $snapshot=cd_snapshot($db,$club,$slug,$scope);
    if($id==='logo'&&$snapshot['has_logo']){$q=$db->prepare('SELECT logo FROM club_brand WHERE club=?');$q->execute([$club]);$v=$q->fetchColumn();return is_string($v)?$v:null;}
    $id=filter_var($id,FILTER_VALIDATE_INT);
    if(!$id)return null;
    foreach($snapshot['content'] as $row)if((int)$row['id']===$id&&$row['has_image'])return cd_media_get($db,$club,$id,$scope);
    return null;
}
function cd_ntc_feed(PDO $db,string $kind):array{
    $map=['teams'=>'nuliga.php','calendar'=>'events.php','news'=>'news.php','weather'=>'weather.php'];
    if(!isset($map[$kind]))return ['status'=>'unavailable'];
    $q=$db->prepare('SELECT * FROM source_cache WHERE kind=?');$q->execute([$kind]);$old=$q->fetch();$now=time();
    if($old&&$now-(int)$old['attempted']<300){$v=json_decode($old['payload'],true)?:[];if($now-(int)$old['fetched']>3600)$v=['status'=>'unavailable'];return $v;}
    $data=null;
    if(function_exists('curl_init')){
        $ch=curl_init('https://clubdisplay.ntc93.de/'.$map[$kind]);$body='';
        curl_setopt_array($ch,[CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body){if(strlen($body)+strlen($chunk)>1000000)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($ok&&$code===200){$raw=json_decode($body,true);if(is_array($raw)&&in_array($raw['status']??'',['ok','stale'],true)){
            $data=['status'=>$raw['status'],'fetchedAt'=>$raw['fetchedAt']??null];
            // Only display-safe fields, never HTML, booking data or arbitrary links.
            if($kind==='weather')$data['current']=array_intersect_key(is_array($raw['current']??null)?$raw['current']:[],array_flip(['temperature','wind','rain','icon']));
            else{$key=['teams'=>'matches','calendar'=>'events','news'=>'posts'][$kind];$fields=['home','away','homeTeam','awayTeam','date','time','league','score','title','start','end','allDay','cancelled','status','venue','excerpt'];$data['rows']=[];foreach(array_slice(is_array($raw[$key]??null)?$raw[$key]:[],0,20) as $r)if(is_array($r))$data['rows'][]=array_filter(array_intersect_key($r,array_flip($fields)),fn($v)=>is_scalar($v));}
        }}
    }
    $fetched=$now;if($data===null){$data=$old&&$now-(int)$old['fetched']<3600?(json_decode($old['payload'],true)?:[]):[];$data['status']=$data?'stale':'unavailable';if(!$old||$now-(int)$old['fetched']>=3600)$data=['status'=>'unavailable'];$fetched=(int)($old['fetched']??0);}
    $q=$db->prepare('INSERT INTO source_cache VALUES (?,?,?,?) ON CONFLICT(kind) DO UPDATE SET payload=excluded.payload,fetched=excluded.fetched,attempted=excluded.attempted');$q->execute([$kind,json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),$fetched,$now]);return $data;
}
function cd_snapshot_feeds(PDO $db,array $snapshot,string $club):array{
    $snapshot['feeds']=[];$refs=cd_sources($db,$club,$club);
    foreach($snapshot['sources'] as $kind=>$provider){
        if(($refs[$kind]['provider']??'')!==$provider)continue;
        $ref=$refs[$kind]['reference']??'';
        if($club==='ntc93'&&$provider==='ntc-feed'&&$kind!=='courts')$snapshot['feeds'][$kind]=cd_ntc_feed($db,$kind);
        elseif($kind==='courts'&&$snapshot['mode']==='INTERACTIVE'){
            if($club==='ntc93'&&$provider==='ntc-booking')$snapshot['booking_url']='https://clubdisplay.ntc93.de/terminal.php';
            elseif($provider==='ebusy-link')$snapshot['booking_url']=cd_provider_reference($provider,$ref);
        }elseif(isset(CD_PROVIDER_OPTIONS[$kind][$provider])&&$kind!=='courts')$snapshot['feeds'][$kind]=cd_provider_cached($db,$club,$kind,$provider,$ref);
    }
    return $snapshot;
}

function cd_amount(mixed $value):?int{
    $v=cd_text($value,20);if($v==='')return null;
    if(!preg_match('/^\d{1,7}(?:[,.]\d{1,2})?$/D',$v))throw new InvalidArgumentException('Betrag bitte als Euro ohne Tausendertrennzeichen eingeben.');
    $parts=explode('.',str_replace(',','.',$v));return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
}
function cd_amount_text(?int $v):string{return $v===null?'':number_format($v/100,2,',','');}
function cd_billing(PDO $db,string $club):array{
    $q=$db->prepare('SELECT * FROM billing_profile WHERE club=?');$q->execute([$club]);return $q->fetch()?:['recurring_cents'=>null,'setup_cents'=>null,'basis'=>'unspecified','invoice_ref'=>'','payment_state'=>'none'];
}
function cd_save_billing(PDO $db,array $in,?string $scope):void{
    if($scope!==null)throw new InvalidArgumentException('Nur TENNILO darf Abrechnungsdaten ändern.');
    $club=cd_text($in['club']??'',50,true);cd_club($db,$club);
    $a=cd_amount($in['recurring']??'');$b=cd_amount($in['setup']??'');$basis=$in['basis']??'unspecified';$state=$in['payment_state']??'none';
    if(!in_array($basis,['unspecified','net','gross'],true)||!in_array($state,['none','open','paid','waived'],true))throw new InvalidArgumentException('Ungültige Abrechnungsdaten.');
    $ref=cd_text($in['invoice_ref']??'',160);
    $q=$db->prepare('INSERT INTO billing_profile VALUES (?,?,?,?,?,?,?) ON CONFLICT(club) DO UPDATE SET recurring_cents=excluded.recurring_cents,setup_cents=excluded.setup_cents,basis=excluded.basis,invoice_ref=excluded.invoice_ref,payment_state=excluded.payment_state,updated=excluded.updated');
    $q->execute([$club,$a,$b,$basis,$ref,$state,gmdate('c')]);
}
function cd_csv_text(string $v):string{return preg_match('/^[\s]*[=+\-@]/u',$v)||preg_match('/^[\t\r\n]/',$v)?"'".$v:$v;}
function cd_billing_csv(PDO $db,$out):void{
    fputcsv($out,['Verein','Vereinskennung','Lizenz','Gerätekontingent','Vertrag','Intervall','Verlängerung','Betrag je Intervall EUR','Einrichtung EUR','Preisbasis','Rechnungsreferenz','Zahlungsstatus'],';','"','');
    foreach($db->query('SELECT slug,name,plan FROM clubs ORDER BY name') as $c){
        $a=cd_contract($db,$c['slug']);$b=cd_billing($db,$c['slug']);
        $row=[$c['name'],$c['slug'],cd_plan_label($c['plan']),(string)$a['screen_limit'],$a['state'],$a['cycle'],$a['renewal'],cd_amount_text($b['recurring_cents']===null?null:(int)$b['recurring_cents']),cd_amount_text($b['setup_cents']===null?null:(int)$b['setup_cents']),$b['basis'],$b['invoice_ref'],$b['payment_state']];
        fputcsv($out,array_map('cd_csv_text',$row),';','"','');
    }
}
function cd_audit(PDO $db,?string $scope,string $action,mixed $club='',mixed $target=''):void{
    try{
        $club=$scope??(is_string($club)&&preg_match('/^[a-z0-9-]{3,50}$/D',$club)?$club:'');
        $target=is_scalar($target)?(string)$target:'';
        if(!preg_match('/^[a-z0-9-]{1,50}$/D',$target))$target='';
        $q=$db->prepare('INSERT INTO audit_events(ts,role,club,action,target) VALUES (?,?,?,?,?)');$q->execute([gmdate('c'),$scope===null?'TENNILO':'Verein',$club,$action,$target]);
        $db->exec('DELETE FROM audit_events WHERE id <= (SELECT COALESCE(MAX(id),0)-5000 FROM audit_events)');
    }catch(Throwable $e){error_log('ClubDisplay audit write failed');}
}
function cd_audit_rows(PDO $db,?string $scope):array{
    if($scope===null)return $db->query('SELECT * FROM audit_events ORDER BY id DESC LIMIT 100')->fetchAll();
    $q=$db->prepare('SELECT * FROM audit_events WHERE club=? ORDER BY id DESC LIMIT 100');$q->execute([$scope]);return $q->fetchAll();
}
function cd_source_check(PDO $db,string $club,?string $scope):array{
    $c=cd_club($db,$club);$sources=cd_sources($db,$club,$scope);$out=[];$modules=json_decode($c['modules'],true)?:[];
    foreach($sources as $kind=>$row){
        if(!in_array($kind,$modules,true)||$kind==='courts')continue;
        $provider=$row['provider'];
        if($provider==='ntc-feed'&&$club==='ntc93')$result=cd_ntc_feed($db,$kind);
        elseif(isset(CD_PROVIDER_OPTIONS[$kind][$provider]))$result=cd_provider_cached($db,$club,$kind,$provider,$row['reference']);
        else continue;
        $out[]=(CD_SOURCE_MODULES[$kind]??$kind).': '.(['ok'=>'erreichbar','stale'=>'zwischengespeichert','unavailable'=>'nicht verfügbar'][$result['status']??'']??'nicht verfügbar');
    }
    return $out;
}
