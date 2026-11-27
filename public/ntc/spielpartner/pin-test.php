<?php
declare(strict_types=1);
// Admin-only pilot. Never return or persist a personal code or raw API response.
// Classify only fixed categories; never expose arbitrary upstream strings.
function cd_pin_api_error(array $payload):array{
    $error=$payload['error']??null;
    $parts=[];
    $collect=static function(mixed $v)use(&$parts):void{
        if(is_string($v))$parts[]=substr($v,0,1000);
        elseif(is_int($v))$parts[]=(string)$v;
    };
    $collect($error);$collect($payload['message']??null);
    if(is_array($error))foreach(['code','type','status','error','message','errorCode','errorMessage','name','reason'] as $key)$collect($error[$key]??null);
    $s=implode(' ', $parts);
    $s=preg_replace('/([a-z])([A-Z])/','$1 $2',$s)??$s;
    $s=str_replace(['_','-'], ' ', $s);
    $category='unknown';
    if(preg_match('/\b(?:401|403|unauthorized|forbidden|permission|permissions|privilege|privileges|access denied|not allowed|not authorized|not permitted|no access|insufficient rights|authorization|access violation)\b|berechtigung|zugriff.{0,30}(?:verweigert|untersagt|nicht erlaubt)|nicht berechtigt|keine rechte|fehlende rechte|nicht autorisiert/iu',$s))$category='permission';
    elseif(preg_match('/\b(?:404|not found|no such (?:person|user)|unknown (?:user|person)|does not exist|not exist|no (?:user|person) found|no result|entity not found)\b|nicht gefunden|nicht vorhanden|unbekannter benutzer|keine person|existiert nicht/iu',$s))$category='not_found';
    elseif(preg_match('/\b(?:429|rate limit|too many requests|throttled)\b|anfragelimit|zu viele anfragen/iu',$s))$category='limited';
    elseif(preg_match('/\b(?:400|bad request|invalid (?:argument|parameter|username)|validation failed|missing parameter)\b|ungültig|ungültiger parameter|fehlender parameter/iu',$s))$category='invalid';
    elseif(preg_match('/\b(?:500|502|503|504|internal server error|internal error|service unavailable|timeout|exception)\b|interner fehler|nicht verfügbar|zeitüberschreitung/iu',$s))$category='upstream';
    elseif($error===false||$error===''||$error===0||$error===[])$category='empty_marker';
    $type=match(gettype($error)){'string'=>'Text','boolean'=>'Boolean','integer','double'=>'Zahl','array'=>'Objekt/Liste',default=>'Sonstiges'};
    return ['status'=>'api_error','category'=>$category,'type'=>$type,'has_response'=>isset($payload['response'])];
}
function cd_pin_result(array $payload,string $pin):array{
    // These diagnostic categories are only rendered after Admin-PIN verification.
    // Never print raw API messages, identifiers, codes, lengths or other person data.
    if(($payload['error']??null)!==null)return cd_pin_api_error($payload);
    if(!array_key_exists('response',$payload))return ['status'=>'unexpected_envelope'];
    if($payload['response']===null||$payload['response']===[])return ['status'=>'not_found'];
    if(!is_array($payload['response'])||array_is_list($payload['response']))return ['status'=>'unexpected_person'];
    $p=$payload['response'];
    if(($p['archived']??null)===true)return ['status'=>'archived'];
    if(($p['archived']??null)!==false)return ['status'=>'archive_unknown'];
    if(!array_key_exists('code',$p))return ['status'=>'code_missing'];
    if($p['code']===null||$p['code']==='')return ['status'=>'code_empty'];
    if(!is_string($p['code']))return ['status'=>'code_type'];
    if(!hash_equals($p['code'],$pin))return ['status'=>'pin_mismatch'];
    $age='unknown';
    $birthday=$p['birthday']??null;
    if(is_string($birthday)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birthday)){
        $tz=new DateTimeZone('Europe/Berlin');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$birthday,$tz);
        $today=new DateTimeImmutable('today',$tz);
        if($d&&$d->format('Y-m-d')===$birthday&&$d<=$today){
            $years=$d->diff($today)->y;
            if($years<=120)$age=$years<16?'child':($years<18?'teen':'adult');
        }
    }
    return ['status'=>'matched','age'=>$age];
}
// Only explicitly selected documented lookups. No list scan or automatic fallback.
function cd_pin_path(string $kind,string $identifier):?string{
    if($kind==='person_id'&&!preg_match('/^[1-9][0-9]{0,18}$/D',$identifier))return null;
    if(!preg_match('/^[\p{L}\p{M}\p{N}_.@+ -]{1,100}$/uD',$identifier)||in_array($identifier,['.','..'],true))return null;
    $value=rawurlencode($identifier);
    return match($kind){
        'username'=>'general/person/by-username/'.$value,
        'person_id'=>'general/person/by-id/'.$value,
        'membership'=>'general/persons/by-membership-number/'.$value.'?offset=0&limit=2',
        'customer'=>'general/persons/by-customer-id/'.$value.'?offset=0&limit=2',
        default=>null
    };
}
function cd_pin_page_result(array $payload,string $pin):array{
    if(($payload['error']??null)!==null)return cd_pin_api_error($payload);
    $page=$payload['response']??null;
    if(!is_array($page)||!is_array($page['content']??null)||!array_is_list($page['content']))return ['status'=>'unexpected_page'];
    $total=$page['totalElements']??null;
    if(!is_int($total)||$total<0)return ['status'=>'unexpected_page'];
    if($total===0&&count($page['content'])===0)return ['status'=>'not_found'];
    if($total>1||count($page['content'])>1)return ['status'=>'ambiguous'];
    if($total!==1||count($page['content'])!==1||!is_array($page['content'][0]))return ['status'=>'unexpected_page'];
    return cd_pin_result(['error'=>null,'response'=>$page['content'][0]],$pin);
}
function cd_pin_normal_name(string $name):string{
    return trim(preg_replace('/\s+/u',' ',$name)??'');
}
function cd_pin_name_equal(string $a,string $b):bool{
    $a=cd_pin_normal_name($a);$b=cd_pin_normal_name($b);
    if(strlen($a)>200||strlen($b)>200||!preg_match("/^[\p{L}\p{M} .'\x{2019}-]+$/uD",$a)||!preg_match("/^[\p{L}\p{M} .'\x{2019}-]+$/uD",$b))return false;
    return preg_match('~\A'.preg_quote($a,'~').'\z~iu',$b)===1;
}
function cd_pin_id_result(array $payload,string $pin,string $id,?string $expectedName=null):array{
    if(($payload['error']??null)!==null)return cd_pin_api_error($payload);
    $p=$payload['response']??null;
    if(is_array($p)&&$p!==[]&&!array_is_list($p)){
        $actual=$p['id']??null;
        if((!is_int($actual)&&!is_string($actual))||(string)$actual!==$id)return ['status'=>'id_mismatch'];
        if($expectedName!==null){
            $first=$p['firstname']??null;$last=$p['lastname']??null;
            if(!is_string($first)||!is_string($last)||!cd_pin_name_equal($expectedName,$first.' '.$last))return ['status'=>'name_mismatch'];
        }
    }
    return cd_pin_result($payload,$pin);
}
// Private transport: callers supply only fixed, validated read-only paths.
// Raw API payloads never leave the request or enter the session/database.
function cd_pin_api_get(array $cfg,string $path):array{
    if(!is_string($cfg['user']??null)||$cfg['user']===''||!is_string($cfg['password']??null)||$cfg['password']===''||!function_exists('curl_init'))return ['status'=>'unconfigured'];
    $body='';$ch=curl_init('https://ntc93v8.ebusy.de/api/'.$path);
    if($ch===false)return ['status'=>'unavailable'];
    curl_setopt_array($ch,[CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$cfg['user'].':'.$cfg['password'],CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_WRITEFUNCTION=>static function($c,string $s)use(&$body):int{if(strlen($body)+strlen($s)>1000000)return 0;$body.=$s;return strlen($s);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if(in_array($status,[401,403],true))return ['status'=>'permission'];
    if($status===429)return ['status'=>'limited'];
    if($status===404)return ['status'=>'not_found'];
    if(!$ok||$status!==200)return ['status'=>'unavailable'];
    try{$payload=json_decode($body,true,64,JSON_THROW_ON_ERROR);return is_array($payload)?['status'=>'ok','payload'=>$payload]:['status'=>'unavailable'];}catch(Throwable){return ['status'=>'unavailable'];}
}
function cd_pin_lookup(array $cfg,string $username,string $pin,string $kind='username',?string $expectedName=null):array{
    $path=cd_pin_path($kind,$username);
    if($path===null||!preg_match('/^[0-9]{1,20}$/D',$pin))return ['status'=>'input_format'];
    $r=cd_pin_api_get($cfg,$path);
    if($r['status']!=='ok')return ['status'=>$r['status']];
    $payload=$r['payload'];
    return match($kind){'username'=>cd_pin_result($payload,$pin),'person_id'=>cd_pin_id_result($payload,$pin,$username,$expectedName),default=>cd_pin_page_result($payload,$pin)};
}
function cd_pin_directory_schema(PDO $db):void{
    $db->exec('CREATE TABLE IF NOT EXISTS name_directory (person_id TEXT PRIMARY KEY, name TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS name_directory_meta (id INTEGER PRIMARY KEY CHECK(id=1), updated INTEGER NOT NULL)');
}
function cd_pin_directory_import(PDO $db,string $csv,int $now):int{
    // Exact three-column input, no arbitrary eBuSy exports containing private fields.
    if(strlen($csv)>250000||!preg_match('//u',$csv))throw new InvalidArgumentException('Bitte eine UTF-8-Liste mit maximal 250 KB verwenden.');
    $csv=preg_replace('/^\xEF\xBB\xBF/','',$csv)??'';
    $lines=preg_split('/\r\n|\n|\r/',trim($csv));
    if(!$lines||count($lines)>5001||array_shift($lines)!=='Personen-ID;Vorname;Nachname')throw new InvalidArgumentException('Erste Zeile muss Personen-ID;Vorname;Nachname lauten. Maximal 5000 Personen.');
    $rows=[];
    foreach($lines as $line){
        if(trim($line)==='')continue;
        $fields=str_getcsv($line,';','"','');
        if(count($fields)!==3)throw new InvalidArgumentException('Nur drei Spalten erlaubt: Personen-ID, Vorname, Nachname.');
        [$id,$first,$last]=array_map(static fn($v)=>trim((string)$v),$fields);
        if(cd_pin_path('person_id',$id)===null||$first===''||$last===''||!cd_pin_name_equal($first,$first)||!cd_pin_name_equal($last,$last)||isset($rows[$id]))throw new InvalidArgumentException('Ungültiger Name, ungültige oder doppelte Personen-ID. Liste nicht übernommen.');
        $rows[$id]=cd_pin_normal_name($first.' '.$last);
        if(!cd_pin_name_equal($rows[$id],$rows[$id]))throw new InvalidArgumentException('Name zu lang. Liste nicht übernommen.');
    }
    if(!$rows)throw new InvalidArgumentException('Liste ist leer.');
    cd_pin_directory_schema($db);
    $db->beginTransaction();
    try{
        $db->exec('DELETE FROM name_directory');
        $q=$db->prepare('INSERT INTO name_directory(person_id,name) VALUES (?,?)');
        foreach($rows as $id=>$name)$q->execute([(string)$id,$name]);
        $db->prepare('INSERT INTO name_directory_meta(id,updated) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET updated=excluded.updated')->execute([$now]);
        $db->commit();return count($rows);
    }catch(Throwable $e){$db->rollBack();throw $e;}
}
function cd_pin_directory_find(PDO $db,string $name,int $now):array{
    cd_pin_directory_schema($db);
    $updated=(int)$db->query('SELECT updated FROM name_directory_meta WHERE id=1')->fetchColumn();
    if(!$updated)return ['status'=>'directory_missing'];
    if($updated>$now||$now-$updated>7*86400)return ['status'=>'directory_stale'];
    if(!cd_pin_name_equal($name,$name))return ['status'=>'input_format'];
    $ids=[];
    foreach($db->query('SELECT person_id,name FROM name_directory') as $row){
        if(cd_pin_name_equal($name,$row['name']))$ids[]=(string)$row['person_id'];
        if(count($ids)>1)return ['status'=>'ambiguous'];
    }
    return count($ids)===1?['status'=>'found','id'=>$ids[0]]:['status'=>'not_found'];
}
function cd_pin_membership_result(array $payload,string $personId,string $today):string{
    if(($payload['error']??null)!==null)return 'api_error';
    $page=$payload['response']??null;
    if(!is_array($page)||!is_array($page['content']??null)||!array_is_list($page['content']))return 'unknown';
    $unknown=false;
    foreach($page['content'] as $row){
        if(!is_array($row)||(!is_int($row['personId']??null)&&!is_string($row['personId']??null))||(string)$row['personId']!==$personId){$unknown=true;continue;}
        if(($row['archived']??null)===true)continue;
        if(($row['archived']??null)!==false||!is_string($row['status']??null)||!is_bool($row['consideredActive']??null)){$unknown=true;continue;}
        if($row['status']!=='ACTIVE'||$row['consideredActive']!==true)continue;
        $begin=$row['begin']??null;$end=$row['end']??null;
        $validDate=static function(mixed $v):bool{
            if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v))return false;
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v;
        };
        if(!$validDate($begin)||!array_key_exists('end',$row)||($end!==null&&!$validDate($end))){$unknown=true;continue;}
        if($begin<=$today&&($end===null||$end>=$today))return 'active';
    }
    if($unknown||($page['last']??null)!==true||!is_int($page['totalElements']??null)||$page['totalElements']!==count($page['content']))return 'unknown';
    return 'not_active';
}
function cd_pin_membership(array $cfg,string $module,string $id):string{
    if(cd_pin_path('person_id',$id)===null||cd_pin_path('person_id',$module)===null)return 'invalid_module';
    $r=cd_pin_api_get($cfg,'member/modules/'.$module.'/memberships/by-person-id/'.$id);
    if($r['status']!=='ok')return $r['status'];
    return cd_pin_membership_result($r['payload'],$id,(new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')))->format('Y-m-d'));
}
function cd_pin_modules(array $cfg):array{
    $r=cd_pin_api_get($cfg,'general/modules');
    if($r['status']!=='ok')return ['status'=>$r['status'],'modules'=>[]];
    $p=$r['payload'];
    if(($p['error']??null)!==null)return ['status'=>'api_error','modules'=>[]];
    $rows=$p['response']??null;
    if(is_array($rows)&&!array_is_list($rows))$rows=$rows['content']??null;
    if(!is_array($rows)||!array_is_list($rows))return ['status'=>'unknown','modules'=>[]];
    $out=[];
    foreach(array_slice($rows,0,100) as $row){
        if(!is_array($row))continue;
        $id=$row['id']??null;
        if((!is_int($id)&&!is_string($id))||cd_pin_path('person_id',(string)$id)===null)continue;
        $label='';
        foreach(['name','type','moduleType'] as $key){
            $v=$row[$key]??null;
            if(is_string($v)&&preg_match('/^[\p{L}\p{M}\p{N} _.,&()\/-]{1,100}$/uD',$v))$label.=($label===''?'':' · ').$v;
        }
        $out[]=['id'=>(string)$id,'label'=>$label?:'Modul'];
    }
    return ['status'=>'ok','modules'=>$out];
}
function cd_pin_attempt(PDO $db,int $now):bool{
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (at INTEGER NOT NULL)');
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('BEGIN IMMEDIATE');
    try{
        $db->prepare('DELETE FROM attempts WHERE at<=?')->execute([$now-900]);
        $allowed=(int)$db->query('SELECT COUNT(*) FROM attempts')->fetchColumn()<10;
        if($allowed)$db->prepare('INSERT INTO attempts VALUES(?)')->execute([$now]);
        $db->exec('COMMIT');return $allowed;
    }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
if(PHP_SAPI==='cli'&&defined('CD_PIN_UNIT_TEST'))return;
ini_set('display_errors','0');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if(($_SERVER['HTTP_HOST']??'')!=='clubdisplay.ntc93.de'||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(404);exit('Nicht verfügbar.');}
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);exit('Nicht verfügbar.');}
$msg='';$success=false;$age='';$diagnosis='';$lookupKind='name';$endpoint='';$paired=false;$membership='';$modules=[];$adminResult=false;$moduleId='';
function cd_pin_field(string $k):string{return is_string($_POST[$k]??null)?$_POST[$k]:'';}
function cd_pin_h(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
try{
    $root=dirname(__DIR__);
    $private=$root.'/devices-private';
    $eb=$root.'/ebusy-private';
    foreach([$private,$eb] as $dir)if(!is_file($dir.'/.htaccess')||!is_file($dir.'/config.php'))throw new RuntimeException('unconfigured');
    $admin=require $private.'/config.php';
    $cfg=require $eb.'/config.php';
    if(empty($admin['hash']))throw new RuntimeException('unconfigured');
    session_name('CD_NTC_PIN_TEST');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/spielpartner/pin-test.php','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_start();
    $_SESSION['csrf']??=bin2hex(random_bytes(32));
    if(($_SESSION['pin_name_pair']['until']??0)<=time())unset($_SESSION['pin_name_pair']);
    $paired=isset($_SESSION['pin_name_pair']);
    if($method==='POST'){
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        $site=$_SERVER['HTTP_SEC_FETCH_SITE']??'';
        if($origin!=='https://clubdisplay.ntc93.de'||($site!==''&&$site!=='same-origin')||(int)($_SERVER['CONTENT_LENGTH']??0)>(cd_pin_field('action')==='import_directory'?800000:4096)||!hash_equals($_SESSION['csrf'],cd_pin_field('csrf'))){http_response_code(403);exit('Anfrage abgelehnt.');}
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        $db=new PDO('sqlite:'.$private.'/pin-test.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if(!cd_pin_attempt($db,time())){$msg='Zu viele Testversuche. Bitte später erneut versuchen.';http_response_code(429);}
        elseif(!preg_match('/^\d{6,20}$/D',cd_pin_field('admin_pin'))||!password_verify(cd_pin_field('admin_pin'),$admin['hash'])){$msg='Admin-Freigabe fehlgeschlagen.';http_response_code(403);}
        elseif(cd_pin_field('action')==='import_directory'){
            try{
                if(cd_pin_field('authorized_roster')!=='yes')throw new InvalidArgumentException('Bitte bestätige die Berechtigung und PIN-freie Liste.');
                $n=cd_pin_directory_import($db,cd_pin_field('directory'),time());
                unset($_SESSION['pin_name_pair']);$paired=false;
                $msg=$n.' Namenszuordnungen übernommen. Automatischer Namensabgleich im Test aktiv.';$adminResult=true;
            }catch(InvalidArgumentException $e){$msg=$e->getMessage();}
        }
        elseif(cd_pin_field('action')==='clear_directory'){
            cd_pin_directory_schema($db);
            $db->beginTransaction();$db->exec('DELETE FROM name_directory');$db->exec('DELETE FROM name_directory_meta');$db->commit();
            unset($_SESSION['pin_name_pair']);$paired=false;$msg='Namensverzeichnis gelöscht.';$adminResult=true;
        }
        elseif(cd_pin_field('action')==='modules'){
            $m=cd_pin_modules($cfg);$modules=$m['modules'];$adminResult=$m['status']==='ok';
            $msg=$adminResult?($modules?'API-Module gefunden. Bitte Mitgliedschaftsmodul identifizieren.':'Keine auswertbaren Module gefunden.'):'Modulabfrage nicht möglich ('.$m['status'].').';
        }
        elseif(cd_pin_field('own_account')!=='yes'){$msg='Bitte bestätige, dass du ausschließlich dein eigenes Konto testest.';}
        else{
            $lookupKind=cd_pin_field('lookup_kind')?:'name';
            $endpoint=match($lookupKind){'name','person_id'=>'/general/person/by-id/{person_id}','username'=>'/general/person/by-username/{username}','membership'=>'/general/persons/by-membership-number/{membership_number}','customer'=>'/general/persons/by-customer-id/{customer_id}',default=>''};
            $identifier=trim(cd_pin_field('username'));$resolvedId='';
            $moduleId=trim(cd_pin_field('module_id'));
            if($lookupKind==='name'){
                $name=cd_pin_normal_name($identifier);
                $setupId=trim(cd_pin_field('setup_id'));
                $pair=$_SESSION['pin_name_pair']??null;
                if($setupId!==''){
                    $resolvedId=$setupId;
                    $result=cd_pin_lookup($cfg,$setupId,cd_pin_field('member_pin'),'person_id',$name);
                    if($result['status']==='matched'){
                        // Only the confirmed own-account mapping, never a PIN, is retained for 30 minutes.
                        $_SESSION['pin_name_pair']=['name'=>$name,'id'=>$setupId,'until'=>time()+1800];
                        $paired=true;
                    }
                }else{
                    $match=cd_pin_directory_find($db,$name,time());
                    if($match['status']==='found'){
                        $resolvedId=$match['id'];
                        $result=cd_pin_lookup($cfg,$resolvedId,cd_pin_field('member_pin'),'person_id',$name);
                    }elseif($match['status']==='directory_missing'&&is_array($pair)&&($pair['until']??0)>time()&&cd_pin_name_equal($name,$pair['name'])){
                        $resolvedId=$pair['id'];$result=cd_pin_lookup($cfg,$resolvedId,cd_pin_field('member_pin'),'person_id',$name);
                    }else $result=['status'=>$match['status']==='directory_missing'?'name_not_paired':$match['status']];
                }
            }else{
                $result=cd_pin_lookup($cfg,$identifier,cd_pin_field('member_pin'),$lookupKind);
                if($lookupKind==='person_id')$resolvedId=$identifier;
            }
            $success=$result['status']==='matched';
            if($success&&$moduleId!==''&&$resolvedId!==''){
                $membership=match(cd_pin_membership($cfg,$moduleId,$resolvedId)){
                    'active'=>'Aktive Mitgliedschaft laut gewähltem Modul bestätigt.',
                    'not_active'=>'Keine aktuell aktive Mitgliedschaft im gewählten Modul bestätigt.',
                    'permission'=>'Mitgliedschaftsabfrage nicht erlaubt (HTTP 401/403).',
                    'invalid_module'=>'Ungültige Modul-ID.',
                    'not_found'=>'Mitgliedschaft oder Modul nicht gefunden.',
                    'api_error'=>'eBuSy meldet einen Fehler bei der Mitgliedschaftsabfrage.',
                    default=>'Mitgliedschaft nicht sicher prüfbar.'
                };
            }
            $msg=match($result['status']){
                'matched'=>'PIN-Abgleich erfolgreich. Nicht archiviertes eBuSy-Konto bestätigt.',
                'permission'=>'Die API hat die Personenabfrage nicht erlaubt (HTTP 401/403).',
                'limited'=>'eBuSy meldet ein Anfragelimit. Bitte später erneut versuchen.',
                'unconfigured'=>'eBuSy-API nicht eingerichtet.',
                'unavailable'=>'Personenabfrage derzeit nicht verfügbar oder unerwartete API-Antwort.',
                'api_error'=>'eBuSy liefert eine API-Fehlermeldung im Antwortobjekt. Keine PIN-Prüfung möglich.',
                'unexpected_envelope'=>'API erreichbar, aber das dokumentierte Antwortfeld response fehlt.',
                'not_found'=>'Keine Person zur eingegebenen Kennung gefunden.',
                'ambiguous'=>'Mehrere Personen zur Kennung gefunden. Keine PIN-Prüfung möglich.',
                'id_mismatch'=>'Die gelieferte Personen-ID stimmt nicht mit der eingegebenen ID überein. Keine PIN-Prüfung möglich.',
                'name_mismatch'=>'Vor- und Nachname stimmen nicht mit der Person in eBuSy überein.',
                'name_not_paired'=>'Noch kein Namensverzeichnis vorhanden. Liste importieren oder einmalig eigene Personen-ID ergänzen.',
                'directory_stale'=>'Namensverzeichnis ist älter als sieben Tage. Bitte erneuern.',
                'unexpected_page'=>'Die API liefert keine eindeutig auswertbare Trefferliste.',
                'unexpected_person'=>'API erreichbar, aber das Personenobjekt hat ein unerwartetes Format.',
                'archived'=>'Person gefunden, Konto laut eBuSy archiviert.',
                'archive_unknown'=>'Person gefunden, Archivstatus fehlt oder hat ein unerwartetes Format.',
                'code_missing'=>'Person gefunden und nicht archiviert, aber das Feld code fehlt in der API-Antwort.',
                'code_empty'=>'Person gefunden und nicht archiviert, aber das Feld code ist leer.',
                'code_type'=>'Person gefunden und nicht archiviert, aber code ist nicht wie dokumentiert als Zeichenfolge verfügbar.',
                'pin_mismatch'=>'Person gefunden, nicht archiviert und code verfügbar. Der eingegebene PIN stimmt nicht exakt überein.',
                'input_format'=>'Benutzername oder PIN entspricht nicht dem erlaubten Eingabeformat.',
                default=>'PIN-Prüfung nicht möglich.'
            };
            if($result['status']==='api_error'){
                $diagnosis=match($result['category']??'unknown'){
                    'permission'=>'Hinweis: Berechtigungs- oder Autorisierungsfehler. Bitte die Lesefreigabe für Personenabfragen prüfen.',
                    'not_found'=>'Hinweis: Person oder angegebene Kennung nicht gefunden.',
                    'limited'=>'Hinweis: Anfragelimit der API.',
                    'invalid'=>'Hinweis: eBuSy meldet einen ungültigen oder fehlenden Anfrageparameter.',
                    'upstream'=>'Hinweis: Fehler im eBuSy-Dienst.',
                    'empty_marker'=>'Hinweis: Unerwarteter leerer Fehlerwert; gemäß Dokumentation müsste error null sein. Prüfung aus Sicherheitsgründen abgebrochen.',
                    default=>'Hinweis: Nicht eindeutig zuordenbarer API-Fehler. eBuSy-Support muss die Antwort serverseitig prüfen.'
                };
                $diagnosis.=' Fehlerfeld: '.($result['type']??'unbekannt').'. Antwortobjekt vorhanden: '.(!empty($result['has_response'])?'ja':'nein').'.';
            }
            $age=match($result['age']??'unknown'){'adult'=>'18 Jahre oder älter','teen'=>'16–17 Jahre','child'=>'Unter 16 Jahren',default=>'Nicht sicher bestimmbar'};
        }
        // No authenticated member session or credentials are kept.
        unset($_POST['admin_pin'],$_POST['member_pin'],$_POST['username'],$_POST['setup_id'],$_POST['directory']);
    }
    $csrf=cd_pin_h($_SESSION['csrf']);session_write_close();
}catch(Throwable){http_response_code(503);exit('PIN-Test derzeit nicht verfügbar.');}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>NTC · eBuSy PIN-Test</title><style>
*{box-sizing:border-box}body{margin:0;background:#edf3f2;color:#123b3d;font:16px/1.5 system-ui,sans-serif}main{max-width:640px;margin:auto;padding:22px}header{display:flex;align-items:center;gap:14px;margin-bottom:20px}header img{width:64px;height:64px;object-fit:contain}h1{font-size:clamp(23px,5vw,32px);line-height:1.15;margin:0}form,section{background:white;border:1px solid #d4dfdc;border-radius:14px;padding:20px;margin:16px 0}label{font-weight:650;display:block}select,input:not([type=checkbox]){width:100%;margin:7px 0 15px;padding:12px;border:1px solid #9ab0ad;border-radius:8px;font:inherit;min-height:44px}button{width:100%;background:#075357;color:white;border:0;border-radius:8px;padding:13px;font:700 16px system-ui;margin-top:16px}a{color:#075357}small{color:#526969}.notice{border-left:5px solid #ad7a19}.success{border-left:5px solid #16805a}.check{display:flex;gap:8px;align-items:flex-start;font-weight:400}.check input{margin-top:5px}h2{font-size:20px;margin:0 0 12px}
</style></head><body><main><header><img src="/assets/ntc93-logo.png" alt="NTC 93"><div><small>CLUBDISPLAY · SPIELPARTNERBÖRSE</small><h1>eBuSy-PIN · Admin-Test</h1></div></header>
<p>Geschützter technischer Test. Bitte ausschließlich dein eigenes eBuSy-Konto verwenden. Die Spielpartnerbörse bleibt unverändert. PINs und API-Rohdaten werden nicht gespeichert. Das private Namensverzeichnis enthält nur Name und Personen-ID. Der bisherige Einzelnachweis bleibt als 30-Minuten-Testzuordnung verfügbar.</p>
<?php if($msg!==''):?><section role="status" class="<?=($success||$adminResult)?'success':'notice'?>"><h2><?=cd_pin_h($msg)?></h2><?php if($diagnosis!==''):?><p><?=cd_pin_h($diagnosis)?></p><p><small>HTTP 200 · GET <?=cd_pin_h($endpoint)?> · <?=cd_pin_h(gmdate('Y-m-d H:i:s').' UTC')?></small></p><?php endif;?><?php if($success):?><p>Altersgruppe: <?=cd_pin_h($age)?></p><p>Vereinsmitgliedschaft: <?=cd_pin_h($membership!==''?$membership:'noch nicht geprüft.')?></p><p>Keine Freischaltung von Mitgliederfunktionen.</p><?php endif;?><?php if($modules):?><ul><?php foreach($modules as $m):?><li><?=cd_pin_h($m['id'].' · '.$m['label'])?></li><?php endforeach;?></ul><?php endif;?></section><?php endif;?>
<form method="post" action="/spielpartner/pin-test.php" autocomplete="off"><input type="hidden" name="csrf" value="<?=$csrf?>"><label for="admin_pin">ClubDisplay Admin-PIN</label><input id="admin_pin" name="admin_pin" type="password" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" required autocomplete="off"><label for="lookup_kind">Person suchen über</label><select id="lookup_kind" name="lookup_kind"><option value="name" <?=$lookupKind==='name'?'selected':''?>>Vor- und Nachname</option><option value="person_id" <?=$lookupKind==='person_id'?'selected':''?>>eBuSy-Personen-ID (nicht Benutzer-ID)</option><option value="username" <?=$lookupKind==='username'?'selected':''?>>eBuSy-Benutzername</option><option value="membership" <?=$lookupKind==='membership'?'selected':''?>>Mitgliedsnummer</option><option value="customer" <?=$lookupKind==='customer'?'selected':''?>>eBuSy-Kundennummer</option></select><label for="username">Vor- und Nachname bzw. Kennung (entsprechend der Auswahl)</label><input id="username" name="username" maxlength="100" required autocomplete="off" autocapitalize="none" spellcheck="false"><p><small><?=$paired?'Einzelzuordnung für diese Testsitzung vorhanden. Personen-ID kann leer bleiben.':'Bei importiertem Namensverzeichnis keine Personen-ID eingeben. Nur für den bisherigen Einzelnachweis ergänzen.'?></small></p><label for="setup_id">Personen-ID · nur für einmalige Testzuordnung</label><input id="setup_id" name="setup_id" inputmode="numeric" pattern="[1-9][0-9]{0,18}" maxlength="19" autocomplete="off"><label for="member_pin">Persönlicher eBuSy-PIN</label><input id="member_pin" name="member_pin" type="password" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required autocomplete="off"><label for="module_id">Mitgliedschaftsmodul-ID (optional, für Live-Prüfung)</label><input id="module_id" name="module_id" inputmode="numeric" pattern="[1-9][0-9]{0,18}" maxlength="19" value="<?=cd_pin_h($moduleId)?>"><label class="check"><input name="own_account" type="checkbox" value="yes" required> Ich teste nur mein eigenes eBuSy-Konto.</label><button type="submit">PIN-Abgleich testen</button></form>
<form method="post" action="/spielpartner/pin-test.php" autocomplete="off"><h2>Mitgliedschaftsmodul ermitteln</h2><p>Nur Modul-Metadaten abrufen, keine Personenliste.</p><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="modules"><label for="modules_admin">ClubDisplay Admin-PIN</label><input id="modules_admin" name="admin_pin" type="password" inputmode="numeric" pattern="[0-9]{6,20}" required autocomplete="off"><button>API-Module anzeigen</button></form>
<form method="post" action="/spielpartner/pin-test.php" autocomplete="off"><h2>Namensverzeichnis importieren</h2><p>Nur berechtigte Vereinsmitglieder. Ausschließlich drei Spalten, keine PINs, E-Mails, Geburtstage oder weiteren Daten. Ein Import ersetzt die Liste; nach sieben Tagen muss sie erneuert werden.</p><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="import_directory"><label for="directory_admin">ClubDisplay Admin-PIN</label><input id="directory_admin" name="admin_pin" type="password" inputmode="numeric" pattern="[0-9]{6,20}" required autocomplete="off"><label for="directory">CSV-Inhalt (UTF-8, Semikolon)</label><textarea id="directory" name="directory" required rows="6" placeholder="Personen-ID;Vorname;Nachname&#10;123;Max;Mustermann" style="width:100%;font:inherit;padding:12px;border:1px solid #9ab0ad;border-radius:8px"></textarea><label class="check"><input type="checkbox" name="authorized_roster" value="yes" required> Ich bin zum Import berechtigt und die Liste enthält keine PINs oder weiteren Daten.</label><button>Namensverzeichnis ersetzen</button></form>
<form method="post" action="/spielpartner/pin-test.php" autocomplete="off"><h2>Namensverzeichnis löschen</h2><input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="clear_directory"><label for="clear_admin">ClubDisplay Admin-PIN</label><input id="clear_admin" name="admin_pin" type="password" inputmode="numeric" pattern="[0-9]{6,20}" required autocomplete="off"><button>Verzeichnis vollständig löschen</button></form>
<p><a href="/spielpartner/">Zurück zur Spielpartnerbörse</a> · <a href="/admin/">Vereinsbackend</a></p><small>Diagnoseversion 0.5.18 · Nur lesende Einzelabfrage. Keine Buchung, keine Änderungen an eBuSy. Keine Weitergabe der PINs an das zentrale TENNILO-Backend.</small></main></body></html>
